<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectMonitoringReportResource;
use App\Models\Project;
use App\Models\ProjectMonitoringCycle;
use App\Models\ProjectMonitoringReport;
use App\Models\User;
use App\Services\MonitoringCycleService;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProjectMonitoringReportController extends Controller
{
    public function index(Request $request, MonitoringCycleService $cycleService)
    {
        if ($request->get('view') === 'grouped') {
            return $this->groupedIndex($request, $cycleService);
        }

        $base = $this->visibleReports($request);
        $this->applyReportFilters($base, $request);
        $reports = $base->with($this->relations())
            ->orderByDesc('reporting_year')
            ->orderByDesc('quarter')
            ->paginate(max(10, min((int) $request->get('per_page', 25), 100)));

        return ProjectMonitoringReportResource::collection($reports)->additional([
            'summary' => $this->reportSummary($this->visibleReports($request)),
        ]);
    }

    public function store(Request $request, Project $project, MonitoringCycleService $cycleService)
    {
        $cycle = $cycleService->activeCycle($project);
        if (! $cycle) {
            return response()->json(['message' => 'Monitoring has not been opened for this project.'], 422);
        }
        if (! $this->canEditProjectReport($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to submit a report for this project.'], 403);
        }

        $type = (string) $request->input('compliance_type');
        $this->normalizeReportPeriod($request, $cycle, $type);
        $validated = $this->validateReport($request, null, true);
        $report = DB::transaction(function () use ($validated, $project, $cycle, $request, $cycleService) {
            if (! in_array($validated['compliance_type'], $cycle->requested_compliance_types, true)) {
                $cycle->update([
                    'requested_compliance_types' => array_values(array_unique([
                        ...$cycle->requested_compliance_types,
                        $validated['compliance_type'],
                    ])),
                ]);
            }
            $report = $project->monitoringReports()->create(array_merge($this->prepareMetrics($validated), [
                'monitoring_cycle_id' => $cycle->id,
                'status' => 'submitted',
                'created_by' => $request->user()?->id,
                'submitted_by' => $request->user()?->id,
                'submitted_at' => now(),
            ]));
            $this->mirrorReportMetrics($project, $report);
            $cycleService->syncProject($cycle->fresh('reports'));

            return $report;
        });

        $this->notifySubmitted($report->fresh(['project', 'cycle']), $request->user());

        return response()->json([
            'message' => $this->typeLabel($report->compliance_type) . ' compliance submitted to NDC.',
            'data' => new ProjectMonitoringReportResource($report->load($this->relations())),
        ], 201);
    }

    public function update(ProjectMonitoringReport $monitoringReport)
    {
        return response()->json([
            'message' => 'Saving compliance drafts is no longer supported. Submit the completed report instead.',
            'report_id' => $monitoringReport->id,
        ], 410);
    }

    public function submit(Request $request, ProjectMonitoringReport $monitoringReport, MonitoringCycleService $cycleService)
    {
        $monitoringReport->loadMissing(['project', 'cycle']);
        if (! $this->canEditProjectReport($request->user(), $monitoringReport->project, $monitoringReport)) {
            return response()->json(['message' => 'Unauthorized to submit this report.'], 403);
        }
        if (! in_array($monitoringReport->status, ['draft', 'returned'], true)) {
            return response()->json(['message' => 'This compliance report has already been submitted.'], 409);
        }
        if (! $monitoringReport->cycle || $monitoringReport->cycle->status !== 'open') {
            return response()->json(['message' => 'The monitoring cycle for this report is no longer open.'], 422);
        }

        $this->normalizeReportPeriod($request, $monitoringReport->cycle, $monitoringReport->compliance_type);
        $validated = $this->validateReport($request, $monitoringReport, true);

        DB::transaction(function () use ($monitoringReport, $validated, $request, $cycleService) {
            $monitoringReport->update(array_merge($this->prepareMetrics($validated), [
                'status' => 'submitted',
                'submitted_by' => $request->user()?->id,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]));
            $this->mirrorReportMetrics($monitoringReport->project, $monitoringReport->fresh());
            $cycleService->syncProject($monitoringReport->cycle->fresh('reports'));
        });

        $this->notifySubmitted($monitoringReport->fresh(['project', 'cycle']), $request->user());

        return response()->json([
            'message' => $this->typeLabel($monitoringReport->compliance_type) . ' compliance submitted to NDC.',
            'data' => new ProjectMonitoringReportResource($monitoringReport->fresh()->load($this->relations())),
        ]);
    }

    public function review(Request $request, ProjectMonitoringReport $monitoringReport, MonitoringCycleService $cycleService)
    {
        $monitoringReport->loadMissing(['project', 'cycle']);
        if (! $this->canReview($request->user(), $monitoringReport->project)) {
            return response()->json(['message' => 'Unauthorized to review this report.'], 403);
        }
        if ($monitoringReport->status !== 'submitted') {
            return response()->json(['message' => 'Only submitted reports can be reviewed.'], 422);
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['accepted', 'returned'])],
            'remarks' => [Rule::requiredIf($request->input('action') === 'returned'), 'nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($monitoringReport, $request, $validated, $cycleService) {
            $monitoringReport->update([
                'status' => $validated['action'],
                'review_notes' => $validated['remarks'] ?? null,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
            if ($monitoringReport->cycle) {
                $cycleService->syncProject($monitoringReport->cycle->fresh('reports'));
            }
        });

        $project = $monitoringReport->project->fresh();
        app(NotificationService::class)->notifyProjectProponent(
            $project,
            $validated['action'] === 'accepted' ? 'monitoring_accepted' : 'monitoring_returned',
            ($validated['action'] === 'accepted' ? 'Compliance report accepted: ' : 'Compliance report returned: ') . $project->project_code,
            $validated['action'] === 'accepted'
                ? "Q{$monitoringReport->quarter} {$monitoringReport->reporting_year} {$this->typeLabel($monitoringReport->compliance_type)} compliance was accepted by NDC."
                : ($validated['remarks'] ?? 'Please revise and resubmit the compliance report.'),
            null,
            $this->notificationData($monitoringReport, $project, $validated['remarks'] ?? null)
        );

        return response()->json([
            'message' => $validated['action'] === 'accepted' ? 'Compliance report accepted.' : 'Compliance report returned for correction.',
            'data' => new ProjectMonitoringReportResource($monitoringReport->fresh()->load($this->relations())),
        ]);
    }

    public function export(Request $request)
    {
        $reports = $this->exportReportsQuery($request)->get();
        $headers = [
            'Cycle ID', 'Cycle Status', 'Project Code', 'Project Title', 'Proponent', 'Project Officer', 'Quarter', 'Compliance Type',
            'Quarter Start', 'Quarter End', 'Report Period Start', 'Report Period End', 'Due Date', 'Status',
            'Jobs Generated - Male', 'Jobs Generated - Female', 'Jobs Generated - Total', 'Jobs Retained - Male',
            'Jobs Retained - Female', 'Jobs Retained - Total', 'Revenue (PHP)', 'Remittance (PHP)', 'Milestones', 'Impact',
            'Monitoring Narrative', 'Submitted By', 'Submitted At', 'Reviewed By', 'Reviewed At', 'Review Notes', 'Classification', 'Investment Lifecycle',
        ];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring Compliance');
        $sheet->fromArray($headers, null, 'A1');

        foreach ($reports as $index => $report) {
            $sheet->fromArray([
                $report->monitoring_cycle_id,
                ucfirst($report->cycle?->status ?: 'legacy'),
                $report->project?->project_code,
                $report->project?->title,
                $report->project?->proponent_name,
                $report->project?->projectOfficer?->full_name,
                "Q{$report->quarter} {$report->reporting_year}",
                $this->typeLabel($report->compliance_type),
                $report->period_start?->toDateString(),
                $report->period_end?->toDateString(),
                $this->reportPeriodStart($report),
                $this->reportPeriodEnd($report),
                $report->cycle?->due_date?->toDateString() ?: $report->due_date?->toDateString(),
                ucfirst($report->status),
                $report->jobs_generated_male,
                $report->jobs_generated_female,
                $report->jobs_generated,
                $report->jobs_retained_male,
                $report->jobs_retained_female,
                $report->jobs_retained,
                (float) $report->revenue,
                (float) $report->remittance,
                $report->milestones,
                $report->impact,
                $report->monitoring_narrative,
                $report->submittedBy?->full_name,
                $report->submitted_at?->toDateTimeString(),
                $report->reviewedBy?->full_name,
                $report->reviewed_at?->toDateTimeString(),
                $report->review_notes,
                match ($report->project?->record_type) {
                    'project' => 'NDC Project',
                    'investment' => 'Investment',
                    default => 'Needs classification',
                },
                \App\Support\InvestmentLifecycle::LABELS[$report->project?->investment_status ?? ''] ?? null,
            ], null, 'A' . ($index + 2));
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1D4ED8');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->getStyle('O2:V' . max(2, $reports->count() + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('W2:Y' . max(2, $reports->count() + 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $fileName = 'ndc-monitoring-compliance-' . now()->format('Ymd-His') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'monitoring-report-');
        (new Xlsx($spreadsheet))->save($temp);

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $reports = $this->exportReportsQuery($request)->get();
        $summary = [
            'total' => $reports->count(),
            'needs_review' => $reports->where('status', 'submitted')->count(),
            'returned' => $reports->where('status', 'returned')->count(),
            'accepted' => $reports->where('status', 'accepted')->count(),
        ];

        $pdf = Pdf::loadView('reports.monitoring-compliance-pdf', [
            'reports' => $reports,
            'summary' => $summary,
            'generatedAt' => now(),
            'filters' => $this->exportFilterLabels($request),
            'typeLabel' => fn (string $type): string => $this->typeLabel($type),
            'reportPeriodStart' => fn (ProjectMonitoringReport $report): ?string => $this->reportPeriodStart($report),
            'reportPeriodEnd' => fn (ProjectMonitoringReport $report): ?string => $this->reportPeriodEnd($report),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('ndc-monitoring-compliance-' . now()->format('Ymd-His') . '.pdf');
    }

    private function groupedIndex(Request $request, MonitoringCycleService $cycleService)
    {
        $scope = $request->get('scope', 'active');
        $query = ProjectMonitoringCycle::query()
            ->whereIn('project_id', $this->visibleProjects($request)->select('projects.id'))
            ->with(['project' => fn ($projects) => $projects->withInvestmentState(), 'project.projectOfficer', 'reports' => fn ($reportQuery) => $reportQuery->with($this->relations())->latest('submitted_at')->latest('id')]);

        $scope === 'history' ? $query->where('status', 'closed') : $query->where('status', 'open');
        $query->when($request->filled('year'), fn ($q) => $q->where('reporting_year', $request->integer('year')))
            ->when($request->filled('quarter'), fn ($q) => $q->where('quarter', $request->integer('quarter')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->get('search');
                $q->whereHas('project', fn ($project) => $project
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('proponent_name', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->whereHas('reports', fn ($reports) => $reports->where('status', $request->get('status'))))
            ->when($request->filled('compliance_type'), fn ($q) => $q->whereJsonContains('requested_compliance_types', $request->get('compliance_type')));

        $summaryQuery = ProjectMonitoringCycle::query()
            ->whereIn('project_id', $this->visibleProjects($request)->select('projects.id'))
            ->where('status', 'open');
        $activeCycles = (clone $summaryQuery)->with('reports')->get();
        $summary = [
            'active_cycles' => $activeCycles->count(),
            'needs_review' => $activeCycles->sum(fn ($cycle) => $cycle->reports->where('status', 'submitted')->count()),
            'returned' => $activeCycles->sum(fn ($cycle) => $cycle->reports->where('status', 'returned')->count()),
            'missing' => $activeCycles->sum(function ($cycle): int {
                $submittedTypes = $cycle->reports
                    ->pluck('compliance_type')
                    ->filter()
                    ->unique();

                return collect($cycle->requested_compliance_types)
                    ->filter()
                    ->unique()
                    ->diff($submittedTypes)
                    ->count();
            }),
            'overdue' => $activeCycles->filter(fn ($cycle) => $cycle->due_date?->isPast() && ! $cycleService->isComplete($cycle))->count(),
        ];

        $cycles = $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('reporting_year')->orderByDesc('quarter')
            ->paginate(max(5, min((int) $request->get('per_page', 15), 50)));
        $cycles->getCollection()->transform(function (ProjectMonitoringCycle $cycle) use ($request, $cycleService) {
            $reports = $cycle->reports
                ->sortByDesc(fn ($report) => $report->submitted_at?->getTimestamp() ?: $report->id)
                ->groupBy('compliance_type')
                ->map(fn ($items) => $items->first());
            $reportMap = collect($cycle->requested_compliance_types)->mapWithKeys(function (string $type) use ($reports, $request) {
                $report = $reports->get($type);
                return [$type => $report ? (new ProjectMonitoringReportResource($report))->resolve($request) : null];
            });

            return [
                'id' => $cycle->id,
                'project_id' => $cycle->project_id,
                'reporting_year' => $cycle->reporting_year,
                'quarter' => $cycle->quarter,
                'period_start' => $cycle->period_start?->toDateString(),
                'period_end' => $cycle->period_end?->toDateString(),
                'due_date' => $cycle->due_date?->toDateString(),
                'instructions' => $cycle->instructions,
                'requested_compliance_types' => $cycle->requested_compliance_types,
                'status' => $cycle->status,
                'aggregate_status' => $cycleService->aggregateStatus($cycle),
                'opened_at' => $cycle->opened_at?->toDateTimeString(),
                'closed_at' => $cycle->closed_at?->toDateTimeString(),
                'project' => [
                    'id' => $cycle->project?->id,
                    'record_type' => $cycle->project?->record_type,
                    'record_type_label' => match ($cycle->project?->record_type) { 'project' => 'NDC Project', 'investment' => 'Investment', default => 'Needs classification' },
                    'investment_status_label' => \App\Support\InvestmentLifecycle::LABELS[$cycle->project?->investment_status ?? ''] ?? null,
                    'project_code' => $cycle->project?->project_code,
                    'title' => $cycle->project?->title,
                    'proponent_name' => $cycle->project?->proponent_name,
                    'monitoring_proponent_access' => (bool) $cycle->project?->monitoring_proponent_access,
                    'project_officer' => $cycle->project?->projectOfficer ? [
                        'id' => $cycle->project->projectOfficer->id,
                        'full_name' => $cycle->project->projectOfficer->full_name,
                    ] : null,
                ],
                'reports' => $reportMap,
                'reports_list' => $cycle->reports
                    ->sortByDesc(fn ($report) => $report->submitted_at?->getTimestamp() ?: $report->id)
                    ->values()
                    ->map(fn ($report) => (new ProjectMonitoringReportResource($report))->resolve($request))
                    ->all(),
            ];
        });

        return response()->json([
            'data' => $cycles->items(),
            'meta' => [
                'current_page' => $cycles->currentPage(),
                'last_page' => $cycles->lastPage(),
                'per_page' => $cycles->perPage(),
                'total' => $cycles->total(),
            ],
            'summary' => $summary,
        ]);
    }

    private function validateReport(Request $request, ?ProjectMonitoringReport $report = null, bool $submitting = false): array
    {
        $type = (string) ($request->input('compliance_type') ?: $report?->compliance_type);
        $required = $submitting ? 'required' : 'nullable';
        $rules = [
            'reporting_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['required', 'integer', 'between:1,4'],
            'compliance_type' => ['required', Rule::in($report ? [$report->compliance_type] : ProjectMonitoringCycle::COMPLIANCE_TYPES)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date'],
        ];

        if ($type === 'employment') {
            $rules += [
                'employment_period_start' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:period_end'],
                'employment_period_end' => ['required', 'date', 'after_or_equal:employment_period_start', 'before_or_equal:period_end'],
                'jobs_generated_male' => [$required, 'integer', 'min:0'],
                'jobs_generated_female' => [$required, 'integer', 'min:0'],
                'jobs_retained_male' => [$required, 'integer', 'min:0'],
                'jobs_retained_female' => [$required, 'integer', 'min:0'],
            ];
        } elseif ($type === 'financial') {
            $rules += [
                'financial_period_start' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:period_end'],
                'financial_period_end' => ['required', 'date', 'after_or_equal:financial_period_start', 'before_or_equal:period_end'],
                'revenue' => [$required, 'numeric', 'min:0'],
                'remittance' => [$required, 'numeric', 'min:0'],
            ];
        } elseif ($type === 'progress') {
            $rules += [
                'narrative_period_start' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:period_end'],
                'narrative_period_end' => ['required', 'date', 'after_or_equal:narrative_period_start', 'before_or_equal:period_end'],
                'milestones' => [$required, 'string', 'max:10000'],
                'impact' => [$required, 'string', 'max:10000'],
                'monitoring_narrative' => [$required, 'string', 'max:10000'],
            ];
        }

        return $request->validate($rules);
    }

    private function normalizeReportPeriod(Request $request, ProjectMonitoringCycle $cycle, string $type): void
    {
        $payload = [
            'compliance_type' => $type,
            'due_date' => $cycle->due_date?->toDateString(),
        ];

        if ($type === 'employment') {
            $year = (int) $request->input('reporting_year');
            $quarter = (int) $request->input('quarter');
            if ($year >= 2000 && $year <= 2100 && $quarter >= 1 && $quarter <= 4) {
                [$start, $end] = app(MonitoringCycleService::class)->quarterDates($year, $quarter);
                $payload += [
                    'period_start' => $start,
                    'period_end' => $end,
                    'employment_period_start' => $start,
                    'employment_period_end' => $end,
                ];
            }
        } else {
            $prefix = $type === 'financial' ? 'financial' : 'narrative';
            $start = $request->input("{$prefix}_period_start");
            $end = $request->input("{$prefix}_period_end");
            $reference = $end ?: $start;

            if ($start && $end) {
                $payload['period_start'] = $start;
                $payload['period_end'] = $end;
            }
            if ($reference && strtotime((string) $reference) !== false) {
                $timestamp = strtotime((string) $reference);
                $payload['reporting_year'] = (int) date('Y', $timestamp);
                $payload['quarter'] = (int) ceil(((int) date('n', $timestamp)) / 3);
            }
        }

        $request->merge($payload);
    }

    private function visibleReports(Request $request): Builder
    {
        return ProjectMonitoringReport::query()->whereIn('project_id', $this->visibleProjects($request)->select('projects.id'));
    }

    private function visibleProjects(Request $request): Builder
    {
        $query = Project::query()->active()->classified($request->input('record_type'), $request->input('investment_status'));
        if ($request->user() && ($request->user()->hasRole('superadmin') || $request->user()->hasRole('admin'))) {
            return $query->visibleDraftsTo($request->user());
        }

        return $query->accessibleTo($request->user(), ['projects.view', 'project.view', 'view_project'], $this->isProponent($request->user()));
    }

    private function applyReportFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('compliance_type'), fn ($q) => $q->where('compliance_type', $request->get('compliance_type')))
            ->when($request->filled('year'), fn ($q) => $q->where('reporting_year', $request->integer('year')))
            ->when($request->filled('quarter'), fn ($q) => $q->where('quarter', $request->integer('quarter')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('scope'), fn ($q) => $q->whereHas('cycle', fn ($cycle) => $cycle->where('status', $request->get('scope') === 'history' ? 'closed' : 'open')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->get('search');
                $q->whereHas('project', fn ($project) => $project->where('title', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('proponent_name', 'like', "%{$search}%"));
            });
    }

    private function exportReportsQuery(Request $request): Builder
    {
        $query = $this->visibleReports($request);
        $this->applyReportFilters($query, $request);

        return $query->with($this->relations())
            ->orderByDesc('reporting_year')
            ->orderByDesc('quarter')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');
    }

    private function exportFilterLabels(Request $request): array
    {
        return collect([
            'Classification' => match ($request->get('record_type')) {
                'project' => 'NDC Project', 'investment' => 'Investment', 'unclassified' => 'Needs classification', default => 'All',
            },
            'Investment lifecycle' => \App\Support\InvestmentLifecycle::LABELS[$request->get('investment_status', '')] ?? 'All',
            'Scope' => $request->filled('scope') ? ucfirst((string) $request->get('scope')) : 'All',
            'Year' => $request->filled('year') ? (string) $request->get('year') : 'All',
            'Quarter' => $request->filled('quarter') ? 'Q' . $request->get('quarter') : 'All',
            'Type' => $request->filled('compliance_type') ? $this->typeLabel((string) $request->get('compliance_type')) : 'All',
            'Status' => $request->filled('status') ? ucfirst((string) $request->get('status')) : 'All',
            'Search' => $request->filled('search') ? (string) $request->get('search') : null,
        ])->filter(fn ($value) => $value !== null)->all();
    }

    private function reportSummary(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'submitted' => (clone $query)->where('status', 'submitted')->count(),
            'returned' => (clone $query)->where('status', 'returned')->count(),
            'accepted' => (clone $query)->where('status', 'accepted')->count(),
        ];
    }

    private function canEditProjectReport(?User $user, Project $project, ?ProjectMonitoringReport $report = null): bool
    {
        if ($this->canReview($user, $project)) {
            return true;
        }
        if (! $user || $project->monitoring_status !== 'active') {
            return false;
        }

        $belongsToProject = (int) $project->created_by === (int) $user->id
            || strcasecmp((string) $project->proponent_email, (string) $user->email) === 0
            || $project->members()->where('user_id', $user->id)->whereNull('removed_at')->where('can_edit', true)->exists();

        if (! $belongsToProject) {
            return false;
        }

        // A returned report must remain correctable by its authorized project user.
        return (bool) $project->monitoring_proponent_access
            || in_array($report?->status, ['draft', 'returned'], true);
    }

    private function canReview(?User $user, Project $project): bool
    {
        if (! $user || $this->isProponent($user)) {
            return false;
        }

        return (int) $user->default_role_id === 1
            || $user->hasRole('superadmin')
            || $user->hasRole('admin')
            || $user->hasPermissionTo('projects.update')
            || (int) $project->project_officer_id === (int) $user->id
            || (int) $project->workgroup_head_id === (int) $user->id;
    }

    private function isProponent(?User $user): bool
    {
        return $user && ((int) $user->default_role_id === 7 || $user->hasRole('Proponent'));
    }

    private function mirrorReportMetrics(Project $project, ProjectMonitoringReport $report): void
    {
        $metrics = (array) ($project->financial_metrics ?? []);
        if ($report->compliance_type === 'employment') {
            $metrics = array_merge($metrics, [
                'jobs_generated' => $report->jobs_generated,
                'jobs_generated_male' => $report->jobs_generated_male,
                'jobs_generated_female' => $report->jobs_generated_female,
                'jobs_retained' => $report->jobs_retained,
                'jobs_retained_male' => $report->jobs_retained_male,
                'jobs_retained_female' => $report->jobs_retained_female,
            ]);
        } elseif ($report->compliance_type === 'financial') {
            $metrics = array_merge($metrics, ['actual_revenue' => (float) $report->revenue, 'dividend_remittance' => (float) $report->remittance]);
        } else {
            $metrics = array_merge($metrics, ['monitoring_indicators' => $report->milestones, 'social_impact_notes' => $report->impact, 'monitoring_narrative' => $report->monitoring_narrative]);
        }
        $metrics['reporting_period'] = "Q{$report->quarter} {$report->reporting_year}";
        $metrics['monitoring_frequency'] = 'Quarterly';
        $project->update(['financial_metrics' => $metrics]);
    }

    private function prepareMetrics(array $validated): array
    {
        if (($validated['compliance_type'] ?? null) === 'employment') {
            $validated['jobs_generated'] = (int) ($validated['jobs_generated_male'] ?? 0) + (int) ($validated['jobs_generated_female'] ?? 0);
            $validated['jobs_retained'] = (int) ($validated['jobs_retained_male'] ?? 0) + (int) ($validated['jobs_retained_female'] ?? 0);
        }

        return $validated;
    }

    private function notifySubmitted(ProjectMonitoringReport $report, ?User $actor): void
    {
        $project = $report->project;
        app(NotificationService::class)->notifyUsers(
            app(NotificationService::class)->internalProjectStakeholders($project, $actor),
            'monitoring_submitted',
            $this->typeLabel($report->compliance_type) . " compliance submitted: {$project->project_code}",
            "Q{$report->quarter} {$report->reporting_year} {$this->typeLabel($report->compliance_type)} compliance is ready for review.",
            $project,
            null,
            $this->notificationData($report, $project)
        );
    }

    private function relations(): array
    {
        return ['project' => fn ($projects) => $projects->withInvestmentState(), 'project.projectOfficer', 'cycle', 'creator.defaultRole', 'submittedBy.defaultRole', 'reviewedBy.defaultRole'];
    }

    private function notificationData(ProjectMonitoringReport $report, Project $project, ?string $remarks = null): array
    {
        return [
            'project_code' => $project->project_code,
            'project_title' => $project->title,
            'reporting_period' => "Q{$report->quarter} {$report->reporting_year}",
            'compliance_type' => $report->compliance_type,
            'compliance_type_label' => $this->typeLabel($report->compliance_type),
            'due_date' => $report->cycle?->due_date?->format('M d, Y') ?? 'No due date',
            'remarks' => $remarks ?: 'No remarks provided.',
            'action_url' => '/implementation-monitoring?report_id=' . $report->id,
            'action_label' => 'Open Monitoring Compliance',
        ];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'employment' => 'Employment',
            'financial' => 'Financial',
            default => 'Progress',
        };
    }

    private function reportPeriodStart(ProjectMonitoringReport $report): ?string
    {
        return match ($report->compliance_type) {
            'employment' => $report->employment_period_start?->toDateString(),
            'financial' => $report->financial_period_start?->toDateString(),
            default => $report->narrative_period_start?->toDateString(),
        };
    }

    private function reportPeriodEnd(ProjectMonitoringReport $report): ?string
    {
        return match ($report->compliance_type) {
            'employment' => $report->employment_period_end?->toDateString(),
            'financial' => $report->financial_period_end?->toDateString(),
            default => $report->narrative_period_end?->toDateString(),
        };
    }
}
