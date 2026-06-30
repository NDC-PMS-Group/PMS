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
        $fileName = 'ndc-projects-' . $preset . '-' . now()->format('Ymd-His') . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Project Register');

        $headers = [
                'No.',
                'Project Code',
                'Project Title',
                'Stage',
                'Status',
                'Process Track',
                'Project Type',
                'Industry',
                'Sector',
                'Proponent',
                'Proponent Email',
                'Estimated Cost',
                'Actual Cost',
                'Target Amount to Raise',
                'NDC Participation',
                'Direct Jobs',
                'Indirect Jobs',
                'Retained Jobs',
                'Projected Revenue',
                'Actual Revenue',
                'Dividend / Remittance',
                'GCG Relevant',
                'GCG Score',
                'Reportable to GCG',
                'Monitoring Frequency',
                'Reporting Period',
                'Progress Percentage',
                'Task Count',
                'Document Count',
                'Target Completion',
                'Actual Completion',
                'Overdue',
                'Location',
                'Updated At',
        ];

        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', 'NATIONAL DEVELOPMENT COMPANY');
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', $this->reportPresetLabel($preset) . ' - Project Register');
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->setCellValue('A3', 'Generated ' . now()->format('F d, Y h:i A') . ' | ' . $projects->count() . ' project(s)');
        $sheet->fromArray($headers, null, 'A5');

        foreach ($projects as $index => $project) {
            $metrics = (array) ($project->financial_metrics ?? []);
            $sheet->fromArray([
                    $index + 1,
                    $project->project_code,
                    $project->title,
                    $project->currentStage?->name,
                    $project->status?->name,
                    $project->process_track,
                    $project->projectType?->name,
                    $project->industry?->name,
                    $project->sector?->name,
                    $project->proponent_name,
                    $project->proponent_email,
                    $project->estimated_cost,
                    $project->actual_cost,
                    $project->target_amount_to_raise,
                    $project->ndc_participation,
                    $metrics['jobs_generated_direct'] ?? null,
                    $metrics['jobs_generated_indirect'] ?? null,
                    $metrics['retained_jobs'] ?? null,
                    $metrics['projected_revenue'] ?? null,
                    $metrics['actual_revenue'] ?? null,
                    $metrics['dividend_remittance'] ?? null,
                    $this->yesNo($metrics['gcg_relevance'] ?? false),
                    $metrics['gcg_score'] ?? null,
                    $this->yesNo($metrics['reportable_to_gcg'] ?? $metrics['is_reportable'] ?? false),
                    $metrics['monitoring_frequency'] ?? null,
                    $metrics['reporting_period'] ?? null,
                    $project->progress_percentage,
                    $project->tasks_count,
                    $project->documents_count,
                    $project->target_completion_date?->toDateString(),
                    $project->actual_completion_date?->toDateString(),
                    $this->yesNo($project->is_overdue),
                    $project->location_address,
                    $project->updated_at?->toDateTimeString(),
            ], null, 'A' . ($index + 6));
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
            $sheet->getColumnDimension($column)->setWidth(
                in_array($headers[$columnIndex - 1], ['Project Title', 'Location'], true) ? 34 : 18
            );
        }

        foreach (['L', 'M', 'N', 'O', 'S', 'T', 'U'] as $currencyColumn) {
            $sheet->getStyle("{$currencyColumn}6:{$currencyColumn}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('₱#,##0.00;[Red]-₱#,##0.00');
        }

        $summary = $spreadsheet->createSheet();
        $summary->setTitle('Summary');
        $summary->fromArray([
            ['NDC Project Export Summary', null],
            ['Report', $this->reportPresetLabel($preset)],
            ['Applied Filters', $this->reportFilterSummary($request)],
            ['Generated', now()->format('F d, Y h:i A')],
            ['Total Projects', $projects->count()],
            ['Total Estimated Cost', $projects->sum(fn ($project) => (float) $project->estimated_cost)],
            ['Total Actual Cost', $projects->sum(fn ($project) => (float) $project->actual_cost)],
            ['Total Direct Jobs', $projects->sum(fn ($project) => (int) data_get($project->financial_metrics, 'jobs_generated_direct', 0))],
            ['Total Indirect Jobs', $projects->sum(fn ($project) => (int) data_get($project->financial_metrics, 'jobs_generated_indirect', 0))],
            ['Reportable to GCG', $projects->filter(fn ($project) => filter_var(data_get($project->financial_metrics, 'reportable_to_gcg', false), FILTER_VALIDATE_BOOLEAN))->count()],
        ], null, 'A1');
        $summary->mergeCells('A1:B1');
        $summary->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '12325B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $summary->getStyle('A2:A10')->getFont()->setBold(true);
        $summary->getColumnDimension('A')->setWidth(28);
        $summary->getColumnDimension('B')->setWidth(30);
        $summary->getStyle('B6:B7')->getNumberFormat()->setFormatCode('₱#,##0.00');

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
            ->with(['projectType', 'industry', 'sector', 'currentStage', 'status'])
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
