<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\SavedReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function projects(Request $request)
    {
        $query = $this->projectReportQuery($request);

        return ProjectResource::collection(
            $query->paginate((int) $request->get('per_page', 25))
        )->additional([
            'summary' => $this->projectReportSummary($request),
        ]);
    }

    public function tasks(Request $request)
    {
        $query = Task::with(['project.currentStage', 'project.status', 'assignedTo'])
            ->active()
            ->whereHas('project', function ($projectQuery) use ($request) {
                $projectQuery->where('is_deleted', false);
                $this->scopeProjectsForUser($projectQuery, $request->user());
            });

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->get('priority'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->get('project_id'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query
                ->orderByRaw("
                    CASE priority
                        WHEN 'critical' THEN 1
                        WHEN 'urgent' THEN 2
                        WHEN 'high' THEN 3
                        WHEN 'medium' THEN 4
                        WHEN 'normal' THEN 5
                        WHEN 'low' THEN 6
                        ELSE 7
                    END ASC
                ")
                ->orderBy('due_date')
                ->paginate((int) $request->get('per_page', 25))
        );
    }

    public function financial(Request $request)
    {
        $data = Project::select(
            DB::raw('SUM(estimated_cost) as total_estimated'),
            DB::raw('SUM(actual_cost) as total_actual'),
            DB::raw('COUNT(*) as project_count')
        )->active()
            ->visibleDraftsTo($request->user())
            ->first();

        return response()->json($data);
    }

    public function export(Request $request)
    {
        return $this->exportProjects($request);
    }

    public function index(Request $request)
    {
        $reports = SavedReport::query()
            ->where(function ($query) use ($request) {
                $query
                    ->where('user_id', $request->user()?->id)
                    ->orWhere('is_public', true);
            })
            ->when($request->filled('report_type'), fn ($query) => $query->where('report_type', $request->get('report_type')))
            ->latest()
            ->paginate((int) $request->get('per_page', 15));

        return response()->json($reports);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'required|string|max:100',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'is_public' => 'nullable|boolean',
        ]);

        $report = SavedReport::create(array_merge($validated, [
            'user_id' => $request->user()?->id,
            'is_public' => $request->boolean('is_public'),
        ]));

        return response()->json($report, 201);
    }

    public function destroy(Request $request, SavedReport $saved_report)
    {
        if ((int) $saved_report->user_id !== (int) $request->user()?->id) {
            return response()->json(['message' => 'Unauthorized to delete this saved report'], 403);
        }

        $saved_report->delete();

        return response()->json(['message' => 'Saved report deleted']);
    }

    public function exportProjects(Request $request)
    {
        $projects = $this->projectReportQuery($request)
            ->withCount(['tasks', 'documents'])
            ->get();

        $preset = $request->get('report_preset', 'all');
        $isMonitoringReport = $preset === 'monitoring';
        $fileName = ($isMonitoringReport ? 'ndc-monitoring-compliance-' : 'ndc-projects-' . $preset . '-')
            . now()->format('Ymd-His') . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($isMonitoringReport ? 'Monitoring Compliance' : 'Project Register');

        $columnDefs = [
            'project_code' => [
                'header' => 'Project Code',
                'value' => fn($p, $m) => $p->project_code
            ],
            'title' => [
                'header' => 'Project Title',
                'value' => fn($p, $m) => $p->title
            ],
            'stage' => [
                'header' => 'Stage',
                'value' => fn($p, $m) => $p->currentStage?->name
            ],
            'status' => [
                'header' => 'Status',
                'value' => fn($p, $m) => $p->status?->name
            ],
            'process_track' => [
                'header' => 'Process Track',
                'value' => fn($p, $m) => $p->process_track
            ],
            'origin_track' => [
                'header' => 'Project Origin Route',
                'value' => fn($p, $m) => Str::headline($p->origin_track ?: $p->process_track)
            ],
            'lifecycle_phase' => [
                'header' => 'Lifecycle Phase',
                'value' => fn($p, $m) => Str::headline($p->lifecycle_phase ?: 'development')
            ],
            'project_type' => [
                'header' => 'Project Type',
                'value' => fn($p, $m) => $p->projectType?->name
            ],
            'industry' => [
                'header' => 'Industry',
                'value' => fn($p, $m) => $p->industry?->name
            ],
            'sector' => [
                'header' => 'Sector',
                'value' => fn($p, $m) => $p->sector?->name
            ],
            'proponent_name' => [
                'header' => 'Proponent',
                'value' => fn($p, $m) => $p->proponent_name
            ],
            'proponent_email' => [
                'header' => 'Proponent Email',
                'value' => fn($p, $m) => $p->proponent_email
            ],
            'project_officer' => [
                'header' => 'Project Officer',
                'value' => fn($p, $m) => $p->projectOfficer?->full_name
            ],
            'estimated_cost' => [
                'header' => 'Estimated Cost',
                'value' => fn($p, $m) => $p->estimated_cost !== null ? (float)$p->estimated_cost : null,
                'format' => 'currency'
            ],
            'actual_cost' => [
                'header' => 'Actual Cost',
                'value' => fn($p, $m) => $p->actual_cost !== null ? (float)$p->actual_cost : null,
                'format' => 'currency'
            ],
            'target_amount_to_raise' => [
                'header' => 'Target Amount to Raise',
                'value' => fn($p, $m) => $p->target_amount_to_raise !== null ? (float)$p->target_amount_to_raise : null,
                'format' => 'currency'
            ],
            'ndc_participation' => [
                'header' => 'NDC Participation',
                'value' => fn($p, $m) => $p->ndc_participation !== null ? (float)$p->ndc_participation : null,
                'format' => 'currency'
            ],
            'jobs_generated_direct' => [
                'header' => 'Direct Jobs',
                'value' => fn($p, $m) => isset($m['jobs_generated_direct']) ? (int)$m['jobs_generated_direct'] : null
            ],
            'jobs_direct_male' => [
                'header' => 'Direct Jobs - Male',
                'value' => fn($p, $m) => isset($m['jobs_direct_male']) ? (int) $m['jobs_direct_male'] : null
            ],
            'jobs_direct_female' => [
                'header' => 'Direct Jobs - Female',
                'value' => fn($p, $m) => isset($m['jobs_direct_female']) ? (int) $m['jobs_direct_female'] : null
            ],
            'jobs_generated_indirect' => [
                'header' => 'Indirect Jobs',
                'value' => fn($p, $m) => isset($m['jobs_generated_indirect']) ? (int)$m['jobs_generated_indirect'] : null
            ],
            'jobs_indirect_male' => [
                'header' => 'Indirect Jobs - Male',
                'value' => fn($p, $m) => isset($m['jobs_indirect_male']) ? (int) $m['jobs_indirect_male'] : null
            ],
            'jobs_indirect_female' => [
                'header' => 'Indirect Jobs - Female',
                'value' => fn($p, $m) => isset($m['jobs_indirect_female']) ? (int) $m['jobs_indirect_female'] : null
            ],
            'retained_jobs' => [
                'header' => 'Retained Jobs',
                'value' => fn($p, $m) => isset($m['retained_jobs']) ? (int)$m['retained_jobs'] : null
            ],
            'jobs_retained_male' => [
                'header' => 'Retained Jobs - Male',
                'value' => fn($p, $m) => isset($m['jobs_retained_male']) ? (int) $m['jobs_retained_male'] : null
            ],
            'jobs_retained_female' => [
                'header' => 'Retained Jobs - Female',
                'value' => fn($p, $m) => isset($m['jobs_retained_female']) ? (int) $m['jobs_retained_female'] : null
            ],
            'projected_revenue' => [
                'header' => 'Projected Revenue',
                'value' => fn($p, $m) => isset($m['projected_revenue']) ? (float)$m['projected_revenue'] : null,
                'format' => 'currency'
            ],
            'actual_revenue' => [
                'header' => 'Actual Revenue',
                'value' => fn($p, $m) => isset($m['actual_revenue']) ? (float)$m['actual_revenue'] : null,
                'format' => 'currency'
            ],
            'dividend_remittance' => [
                'header' => 'Dividend / Remittance',
                'value' => fn($p, $m) => isset($m['dividend_remittance']) ? (float)$m['dividend_remittance'] : null,
                'format' => 'currency'
            ],
            'gcg_relevance' => [
                'header' => 'GCG Relevant',
                'value' => fn($p, $m) => $this->yesNo($m['gcg_relevance'] ?? false)
            ],
            'gcg_score' => [
                'header' => 'GCG Score',
                'value' => fn($p, $m) => $m['gcg_score'] ?? null
            ],
            'reportable_to_gcg' => [
                'header' => 'Reportable to GCG',
                'value' => fn($p, $m) => $this->yesNo($m['reportable_to_gcg'] ?? $m['is_reportable'] ?? false)
            ],
            'monitoring_frequency' => [
                'header' => 'Monitoring Frequency',
                'value' => fn($p, $m) => $m['monitoring_frequency'] ?? null
            ],
            'reporting_period' => [
                'header' => 'Reporting Period',
                'value' => fn($p, $m) => $m['reporting_period'] ?? null
            ],
            'monitoring_status' => [
                'header' => 'Monitoring Cycle',
                'value' => fn($p, $m) => Str::headline($p->monitoring_status ?: 'closed')
            ],
            'monitoring_submission_status' => [
                'header' => 'Submission Status',
                'value' => fn($p, $m) => Str::headline($p->monitoring_submission_status === 'approved'
                    ? 'accepted'
                    : ($p->monitoring_submission_status ?: 'not_requested'))
            ],
            'monitoring_due_date' => [
                'header' => 'Compliance Due Date',
                'value' => fn($p, $m) => $p->monitoring_due_date?->toDateString()
            ],
            'monitoring_instructions' => [
                'header' => 'Submission Instructions',
                'value' => fn($p, $m) => $p->monitoring_instructions
            ],
            'monitoring_draft_saved_at' => [
                'header' => 'Draft Last Saved',
                'value' => fn($p, $m) => $p->monitoring_draft_saved_at?->toDateTimeString()
            ],
            'monitoring_submitted_at' => [
                'header' => 'Submitted At',
                'value' => fn($p, $m) => $p->monitoring_submitted_at?->toDateTimeString()
            ],
            'monitoring_submitted_by' => [
                'header' => 'Submitted By',
                'value' => fn($p, $m) => $p->monitoringSubmittedBy?->full_name
            ],
            'monitoring_reviewed_at' => [
                'header' => 'Reviewed At',
                'value' => fn($p, $m) => $p->monitoring_reviewed_at?->toDateTimeString()
            ],
            'monitoring_reviewed_by' => [
                'header' => 'Reviewed By',
                'value' => fn($p, $m) => $p->monitoringReviewedBy?->full_name
            ],
            'monitoring_review_notes' => [
                'header' => 'Review Notes',
                'value' => fn($p, $m) => $p->monitoring_review_notes
            ],
            'monitoring_proponent_access' => [
                'header' => 'Proponent Access',
                'value' => fn($p, $m) => $this->yesNo($p->monitoring_proponent_access)
            ],
            'monitoring_indicators' => [
                'header' => 'Monitoring Indicators / Milestones',
                'value' => fn($p, $m) => $m['monitoring_indicators'] ?? null
            ],
            'social_impact_notes' => [
                'header' => 'Social Impact Notes',
                'value' => fn($p, $m) => $m['social_impact_notes'] ?? null
            ],
            'gcg_metrics' => [
                'header' => 'GCG Metrics / Notes',
                'value' => fn($p, $m) => $m['gcg_metrics'] ?? null
            ],
            'progress_percentage' => [
                'header' => 'Progress Percentage',
                'value' => fn($p, $m) => $p->progress_percentage
            ],
            'tasks_count' => [
                'header' => 'Task Count',
                'value' => fn($p, $m) => $p->tasks_count
            ],
            'documents_count' => [
                'header' => 'Document Count',
                'value' => fn($p, $m) => $p->documents_count
            ],
            'target_completion_date' => [
                'header' => 'Target Completion',
                'value' => fn($p, $m) => $p->target_completion_date?->toDateString()
            ],
            'actual_completion_date' => [
                'header' => 'Actual Completion',
                'value' => fn($p, $m) => $p->actual_completion_date?->toDateString()
            ],
            'is_overdue' => [
                'header' => 'Overdue',
                'value' => fn($p, $m) => $this->yesNo($p->is_overdue)
            ],
            'location_address' => [
                'header' => 'Location',
                'value' => fn($p, $m) => $p->location_address
            ],
            'updated_at' => [
                'header' => 'Updated At',
                'value' => fn($p, $m) => $p->updated_at?->toDateTimeString()
            ],
        ];

        $selectedColumns = $request->get('columns');
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }
        $selectedColumns = array_filter((array) $selectedColumns);

        $activeColumns = $columnDefs;
        if (!empty($selectedColumns)) {
            $activeColumns = array_filter(
                $columnDefs,
                fn($key) => in_array($key, $selectedColumns, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        $headers = array_merge(['No.'], array_values(array_map(fn($col) => $col['header'], $activeColumns)));
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', 'NATIONAL DEVELOPMENT COMPANY');
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', $isMonitoringReport
            ? 'Monitoring Compliance Register'
            : $this->reportPresetLabel($preset) . ' - Project Register');
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->setCellValue('A3', 'Generated ' . now()->format('F d, Y h:i A') . ' | ' . $projects->count() . ' project(s)');
        $sheet->fromArray($headers, null, 'A5');

        foreach ($projects as $index => $project) {
            $metrics = (array) ($project->financial_metrics ?? []);
            $row = [$index + 1];
            foreach ($activeColumns as $key => $colDef) {
                $row[] = $colDef['value']($project, $metrics);
            }
            $sheet->fromArray($row, null, 'A' . ($index + 6));
        }

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '12325B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '12325B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A3:{$lastColumn}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6E8C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D7E1EA']]],
        ]);

        $lastRow = max(5, $projects->count() + 5);
        $sheet->getStyle("A6:{$lastColumn}{$lastRow}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D7E1EA']]],
        ]);
        $sheet->setAutoFilter("A5:{$lastColumn}{$lastRow}");
        $sheet->freezePane('A6');
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(5)->setRowHeight(34);

        foreach (range(1, count($headers)) as $columnIndex) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
            $headerName = $headers[$columnIndex - 1];
            $sheet->getColumnDimension($column)->setWidth(
                in_array($headerName, ['Project Title', 'Location'], true) ? 34 : 18
            );
        }

        $columnIndex = 2;
        foreach ($activeColumns as $key => $colDef) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
            if (isset($colDef['format']) && $colDef['format'] === 'currency') {
                $sheet->getStyle("{$columnLetter}6:{$columnLetter}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('₱#,##0.00;[Red]-₱#,##0.00');
            }
            $columnIndex++;
        }

        $note = $request->get('note') ?? $request->get('extraction_note') ?? '';
        if (!empty($note)) {
            $noteStartRow = $lastRow + 3;
            $sheet->setCellValue('A' . $noteStartRow, 'Extraction Note:');
            $sheet->getStyle('A' . $noteStartRow)->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '12325B']]
            ]);

            $sheet->mergeCells("A" . ($noteStartRow + 1) . ":{$lastColumn}" . ($noteStartRow + 3));
            $sheet->setCellValue('A' . ($noteStartRow + 1), $note);
            $sheet->getStyle("A" . ($noteStartRow + 1))->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ],
                'font' => ['italic' => true, 'color' => ['rgb' => '555555']]
            ]);
            $sheet->getRowDimension($noteStartRow + 1)->setRowHeight(45);
        }

        $summary = $spreadsheet->createSheet();
        $summary->setTitle('Summary');
        $summaryRows = [
            [$isMonitoringReport ? 'NDC Monitoring Compliance Summary' : 'NDC Project Export Summary', null],
            ['Report', $this->reportPresetLabel($preset)],
            ['Applied Filters', $this->reportFilterSummary($request)],
            ['Generated', now()->format('F d, Y h:i A')],
            ['Total Projects', $projects->count()],
            ['Total Estimated Cost', $projects->sum(fn ($project) => (float) $project->estimated_cost)],
            ['Total Actual Cost', $projects->sum(fn ($project) => (float) $project->actual_cost)],
            ['Total Direct Jobs', $projects->sum(fn ($project) => (int) data_get($project->financial_metrics, 'jobs_generated_direct', 0))],
            ['Total Indirect Jobs', $projects->sum(fn ($project) => (int) data_get($project->financial_metrics, 'jobs_generated_indirect', 0))],
            ['Reportable to GCG', $projects->filter(fn ($project) => filter_var(data_get($project->financial_metrics, 'reportable_to_gcg', false), FILTER_VALIDATE_BOOLEAN))->count()],
        ];
        if ($isMonitoringReport) {
            $summaryRows = array_merge($summaryRows, [
                ['Active Monitoring Periods', $projects->where('monitoring_status', 'active')->count()],
                ['Awaiting NDC Review', $projects->where('monitoring_submission_status', 'submitted')->count()],
                ['Returned for Correction', $projects->where('monitoring_submission_status', 'returned')->count()],
                ['Accepted Submissions', $projects->whereIn('monitoring_submission_status', ['accepted', 'approved'])->count()],
                ['Overdue Submissions', $projects->filter(fn ($project) => $project->monitoring_due_date
                    && $project->monitoring_due_date->isPast()
                    && !in_array($project->monitoring_submission_status, ['accepted', 'approved'], true))->count()],
                ['Total Actual Revenue', $projects->sum(fn ($project) => (float) data_get($project->financial_metrics, 'actual_revenue', 0))],
                ['Total Dividend / Remittance', $projects->sum(fn ($project) => (float) data_get($project->financial_metrics, 'dividend_remittance', 0))],
            ]);
        }
        $summary->fromArray($summaryRows, null, 'A1');
        $summary->mergeCells('A1:B1');
        $summary->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '12325B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $summaryLastRow = count($summaryRows);
        $summary->getStyle("A2:A{$summaryLastRow}")->getFont()->setBold(true);
        $summary->getColumnDimension('A')->setWidth(28);
        $summary->getColumnDimension('B')->setWidth(30);
        $summary->getStyle('B6:B7')->getNumberFormat()->setFormatCode('₱#,##0.00');
        if ($isMonitoringReport) {
            $summary->getStyle('B16:B17')->getNumberFormat()->setFormatCode('₱#,##0.00');
        }

        if (!empty($note)) {
            $noteLabelRow = $summaryLastRow + 2;
            $noteBodyRow = $noteLabelRow + 1;
            $summary->setCellValue("A{$noteLabelRow}", 'Extraction Note:');
            $summary->getStyle("A{$noteLabelRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '12325B']]
            ]);
            $summary->mergeCells("A{$noteBodyRow}:B" . ($noteBodyRow + 2));
            $summary->setCellValue("A{$noteBodyRow}", $note);
            $summary->getStyle("A{$noteBodyRow}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ],
                'font' => ['italic' => true, 'color' => ['rgb' => '555555']]
            ]);
        }

        $directory = storage_path('app/report-exports');
        File::ensureDirectoryExists($directory);
        $path = $directory . DIRECTORY_SEPARATOR . $fileName;
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download(
            $path,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function projectReportQuery(Request $request)
    {
        $query = Project::query()
            ->with([
                'projectType', 'industry', 'sector', 'currentStage', 'status',
                'projectOfficer', 'monitoringSubmittedBy', 'monitoringReviewedBy',
            ])
            ->where('is_deleted', false);

        $this->scopeProjectsForUser($query, $request->user());
        $this->applyProjectFilters($query, $request);

        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'updated_at', 'title', 'estimated_cost', 'actual_cost', 'target_completion_date'];

        return $query->orderBy(in_array($sortBy, $allowedSorts, true) ? $sortBy : 'updated_at', $sortOrder);
    }

    private function applyProjectFilters($query, Request $request): void
    {
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        } else {
            $query->where('is_archived', false);
        }

        foreach ([
            'stage_id' => 'current_stage_id',
            'status_id' => 'status_id',
            'project_type_id' => 'project_type_id',
            'industry_id' => 'industry_id',
            'sector_id' => 'sector_id',
        ] as $requestKey => $column) {
            if ($request->filled($requestKey)) {
                $query->where($column, $request->get($requestKey));
            }
        }

        if ($request->filled('process_track')) {
            $query->where('process_track', $request->get('process_track'));
        }

        if ($request->filled('monitoring_status')) {
            $query->where('monitoring_status', $request->get('monitoring_status'));
        }

        if ($request->filled('monitoring_submission_status')) {
            $submissionStatus = $request->get('monitoring_submission_status');
            $submissionStatus === 'accepted'
                ? $query->whereIn('monitoring_submission_status', ['accepted', 'approved'])
                : $query->where('monitoring_submission_status', $submissionStatus);
        }

        if ($request->boolean('monitoring_overdue')) {
            $query->whereDate('monitoring_due_date', '<', today())
                ->whereNotIn('monitoring_submission_status', ['accepted', 'approved']);
        }

        if ($request->has('is_svf')) {
            $query->where('is_svf', $request->boolean('is_svf'));
        }

        if ($request->has('is_overdue')) {
            $request->boolean('is_overdue')
                ? $query->whereNotNull('target_completion_date')
                    ->whereNull('actual_completion_date')
                    ->whereDate('target_completion_date', '<', now()->toDateString())
                : $query->where(function ($overdueQuery) {
                    $overdueQuery
                        ->whereNull('target_completion_date')
                        ->orWhereNotNull('actual_completion_date')
                        ->orWhereDate('target_completion_date', '>=', now()->toDateString());
                });
        }

        if ($request->has('reportable_to_gcg')) {
            $request->boolean('reportable_to_gcg')
                ? $query->where(function ($reportableQuery) {
                    $reportableQuery
                        ->where('financial_metrics->reportable_to_gcg', true)
                        ->orWhere('financial_metrics->is_reportable', true);
                })
                : $query->where(function ($reportableQuery) {
                    $reportableQuery
                        ->where(function ($metricQuery) {
                            $metricQuery
                                ->whereNull('financial_metrics->reportable_to_gcg')
                                ->orWhere('financial_metrics->reportable_to_gcg', false);
                        })
                        ->where(function ($metricQuery) {
                            $metricQuery
                                ->whereNull('financial_metrics->is_reportable')
                                ->orWhere('financial_metrics->is_reportable', false);
                        });
                });
        }

        foreach ([
            'estimated_cost_min' => ['estimated_cost', '>='],
            'estimated_cost_max' => ['estimated_cost', '<='],
            'actual_cost_min' => ['actual_cost', '>='],
            'actual_cost_max' => ['actual_cost', '<='],
        ] as $requestKey => [$column, $operator]) {
            if ($request->filled($requestKey) && is_numeric($request->get($requestKey))) {
                $query->where($column, $operator, $request->get($requestKey));
            }
        }

        $progressExpression = $this->projectProgressExpression();
        if ($request->filled('progress_min') && is_numeric($request->get('progress_min'))) {
            $query->whereRaw("{$progressExpression} >= ?", [(int) $request->get('progress_min')]);
        }

        if ($request->filled('progress_max') && is_numeric($request->get('progress_max'))) {
            $query->whereRaw("{$progressExpression} <= ?", [(int) $request->get('progress_max')]);
        }

        $dateField = in_array($request->get('date_field'), [
            'created_at',
            'updated_at',
            'proposal_date',
            'start_date',
            'target_completion_date',
            'actual_completion_date',
            'monitoring_due_date',
            'monitoring_submitted_at',
            'monitoring_reviewed_at',
        ], true) ? $request->get('date_field') : 'created_at';

        if ($request->filled('date_from')) {
            $query->whereDate($dateField, '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate($dateField, '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('proponent_name', 'like', "%{$search}%")
                    ->orWhere('proponent_email', 'like', "%{$search}%");
            });
        }

        $this->applyReportPreset($query, (string) $request->get('report_preset', 'all'));
    }

    private function applyReportPreset($query, string $preset): void
    {
        match ($preset) {
            'approved' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->whereHas('status', fn ($statusQuery) => $statusQuery->whereIn('name', [
                        'Approved',
                        'Approved with Conditions',
                        'For Agreement Signing',
                        'For Fund Release',
                    ]))
                    ->orWhereHas('approvals', fn ($approvalQuery) => $approvalQuery->whereIn('overall_status', [
                        'approved',
                        'approved_with_conditions',
                    ]));
            }),
            'ongoing' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->whereHas('status', fn ($statusQuery) => $statusQuery
                        ->where('name', 'like', '%Ongoing%')
                        ->orWhereIn('name', [
                            'Requirements Requested',
                            'Requirements Received',
                            'Due Diligence Ongoing',
                            'For IC Evaluation',
                            'For Workgroup Review',
                            'For ManCom Review',
                            'For Board Approval',
                            'For Agreement Signing',
                            'For Fund Release',
                        ]))
                    ->orWhereHas('currentStage', fn ($stageQuery) => $stageQuery->whereIn('name', [
                        'Requirements',
                        'Due Diligence',
                        'Management Review',
                        'Board Approval',
                        'Agreement & Fund Release',
                        'Implementation & Monitoring',
                        'Post-Investment Strategy',
                        'Divestment',
                    ]));
            }),
            'completed' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->whereNotNull('actual_completion_date')
                    ->orWhereHas('status', fn ($statusQuery) => $statusQuery->whereIn('name', [
                        'Completed',
                        'Archived',
                        'Divested',
                    ]))
                    ->orWhereHas('currentStage', fn ($stageQuery) => $stageQuery->whereIn('name', [
                        'Completion',
                        'Divestment',
                    ]))
                    ->orWhereHas('approvals', fn ($approvalQuery) => $approvalQuery->where('overall_status', 'completed'));
            }),
            'categorized' => $query->whereNotNull('project_type_id')
                ->whereNotNull('industry_id')
                ->whereNotNull('sector_id'),
            'reportable' => $query->where(function ($presetQuery) {
                $presetQuery
                    ->where('financial_metrics->reportable_to_gcg', true)
                    ->orWhere('financial_metrics->is_reportable', true)
                    ->orWhereHas('currentStage', fn ($stageQuery) => $stageQuery->whereIn('name', [
                        'Implementation & Monitoring',
                        'Post-Investment Strategy',
                        'Divestment',
                        'Completion',
                    ]))
                    ->orWhereHas('status', fn ($statusQuery) => $statusQuery->whereIn('name', [
                        'Approved',
                        'Approved with Conditions',
                        'Implementation Ongoing',
                        'Monitoring Ongoing',
                        'Completed',
                    ]));
            }),
            'monitoring' => $query->where('monitoring_status', '!=', 'closed'),
            default => null,
        };
    }

    private function projectProgressExpression(): string
    {
        $totalTasks = "(select count(*) from tasks where tasks.project_id = projects.id and tasks.is_deleted = 0)";
        $completedTasks = "(select count(*) from tasks where tasks.project_id = projects.id and tasks.is_deleted = 0 and tasks.status = 'completed')";

        return "(case when {$totalTasks} = 0 then 0 else round(({$completedTasks} * 100.0) / {$totalTasks}) end)";
    }

    private function projectReportSummary(Request $request): array
    {
        $base = Project::query()->where('is_deleted', false)->where('is_archived', false);
        $this->scopeProjectsForUser($base, $request->user());

        $summary = [];
        foreach (['approved', 'ongoing', 'completed', 'categorized', 'reportable'] as $preset) {
            $query = clone $base;
            $this->applyReportPreset($query, $preset);
            $summary[$preset] = $query->count();
        }

        return $summary;
    }

    private function scopeProjectsForUser($query, $user): void
    {
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->visibleDraftsTo($user);

        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));
        $isSuperAdmin = (int) $user->default_role_id === 1 || $roleName === 'superadmin';
        $hasGlobalView = !$this->isExternalProponent($user) && $this->hasAnyPermission($user, [
            'projects.view',
            'project.view',
            'view_project',
        ]);

        if ($isSuperAdmin || $hasGlobalView) {
            return;
        }

        $query->where(function ($projectQuery) use ($user) {
            $projectQuery
                ->where('created_by', $user->id)
                ->orWhere('project_officer_id', $user->id)
                ->orWhere('workgroup_head_id', $user->id)
                ->orWhereHas('members', fn ($memberQuery) => $memberQuery
                    ->active()
                    ->where('user_id', $user->id));
        });
    }

    private function hasAnyPermission($user, array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    private function isExternalProponent($user): bool
    {
        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));

        return (int) $user->default_role_id === 7 || $roleName === 'proponent';
    }

    private function reportPresetLabel(string $preset): string
    {
        return [
            'approved' => 'Approved Projects',
            'ongoing' => 'Ongoing Projects',
            'completed' => 'Completed Projects',
            'categorized' => 'Categorized Projects',
            'reportable' => 'Reportable Projects',
            'monitoring' => 'Monitoring Compliance',
        ][$preset] ?? 'All Projects';
    }

    private function reportFilterSummary(Request $request): string
    {
        $parts = [];

        foreach ([
            'search' => 'Search',
            'process_track' => 'SOI Track',
            'date_field' => 'Date Field',
            'date_from' => 'From',
            'date_to' => 'To',
            'estimated_cost_min' => 'Estimated Min',
            'estimated_cost_max' => 'Estimated Max',
            'actual_cost_min' => 'Actual Min',
            'actual_cost_max' => 'Actual Max',
            'progress_min' => 'Progress Min',
            'progress_max' => 'Progress Max',
            'monitoring_status' => 'Monitoring Cycle',
            'monitoring_submission_status' => 'Submission Status',
        ] as $key => $label) {
            if ($request->filled($key)) {
                $parts[] = "{$label}: {$request->get($key)}";
            }
        }

        foreach ([
            'is_archived' => 'Archived',
            'is_svf' => 'SVF',
            'is_overdue' => 'Overdue',
            'reportable_to_gcg' => 'GCG Reportable',
            'monitoring_overdue' => 'Monitoring Overdue',
        ] as $key => $label) {
            if ($request->has($key)) {
                $parts[] = "{$label}: " . ($request->boolean($key) ? 'Yes' : 'No');
            }
        }

        return $parts ? implode(' | ', $parts) : 'None';
    }

    private function yesNo($value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
    }
}
