<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectFundRelease;
use App\Models\ProjectRequirement;
use App\Models\ProjectStage;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\ProjectCategory;

class DashboardService
{
    private const ACTIONABLE_APPROVAL_STATUSES = [
        'pending',
        'initial_completeness_check',
        'for_evaluation',
        'for_ic_evaluation',
        'for_agm_review',
        'for_workgroup_review',
        'for_mancom_review',
        'for_board_approval',
        'for_neda_icc_review',
        'for_jv_selection',
        'for_fund_release',
        'milestones_setup',
        'for_monitoring_update',
        'for_divestment_approval',
        'for_approval',
    ];

    private const PORTFOLIO_ROLES = [
        'superadmin',
        'admin',
        'workgroup head',
    ];

    public function build(User $user, array $inputFilters): array
    {
        $filters = $this->normalizeFilters($user, $inputFilters);
        $visibleProjects = $this->projectQuery($user, $filters);
        $personalProjects = $this->projectQuery($user, [...$filters, 'scope' => 'mine']);
        $pendingActions = $this->pendingApprovalActionQuery($user, $filters);
        $revisionRequests = $this->revisionRequestQuery($user, $filters);
        $visibleTasks = $this->visibleTaskQuery($user, $filters);
        $projects = (clone $visibleProjects)
            ->withInvestmentState()
            ->with([
                'currentStage:id,name',
                'status:id,name',
                'sector:id,name',
                'projectOfficer:id,first_name,last_name',
                'workgroupHead:id,first_name,last_name',
                'legacyDetail:id,project_id,detail_status',
            ])
            ->withCount([
                'tasks as open_tasks_count' => fn ($query) => $query->active(),
                'tasks as overdue_tasks_count' => fn ($query) => $query->active()->overdue(),
                'requirements as overdue_requirements_count' => fn ($query) => $query
                    ->whereIn('status', ['requested', 'deferred', 'for_further_evaluation'])
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', today()),
            ])
            ->get();

        $monitoringProjects = $projects->where('monitoring_status', 'active');
        $legacyProjects = $projects->where('is_legacy', true);
        $projectIds = $projects->pluck('id');
        $releasedFunds = $projectIds->isEmpty()
            ? 0.0
            : (float) ProjectFundRelease::query()
                ->whereIn('project_id', $projectIds)
                ->whereIn('status', ProjectFundRelease::RELEASED_STATUSES)
                ->sum('amount');
        $monitoringSummary = $this->monitoringSummary($monitoringProjects);
        $overdueRequirements = ProjectRequirement::query()
            ->whereIn('status', ['requested', 'deferred', 'for_further_evaluation'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereHas('project', fn (Builder $query) => $this->applyProjectFilters($this->scopeProjectsForUser($query->active(), $user, $filters['scope']), $filters))
            ->count();
        $monitoringDue = $monitoringProjects
            ->filter(fn (Project $project) => $this->isDueWithin($project->monitoring_due_date, 14, false))
            ->reject(fn (Project $project) => $project->monitoring_submission_status === 'accepted')
            ->count();

        return [
            // Existing response keys remain stable for current consumers.
            'total_projects' => $projects->count(),
            'my_projects' => (clone $personalProjects)->count(),
            'pending_approvals' => (clone $pendingActions)->count(),
            'overdue_tasks' => (clone $visibleTasks)->overdue()->count(),
            'my_tasks' => Task::active()->where('assigned_to', $user->id)->count(),
            'completed_this_month' => (clone $visibleProjects)
                ->whereYear('actual_completion_date', now()->year)
                ->whereMonth('actual_completion_date', now()->month)
                ->count(),
            'approved_with_conditions' => ProjectApproval::where('overall_status', 'approved_with_conditions')
                ->whereHas('project', fn (Builder $query) => $this->applyProjectFilters($this->scopeProjectsForUser($query->active(), $user, $filters['scope']), $filters))
                ->count(),
            'revision_requests_count' => (clone $revisionRequests)->count(),
            'active_workflows' => ProjectApproval::whereIn('overall_status', self::ACTIONABLE_APPROVAL_STATUSES)
                ->whereHas('project', fn (Builder $query) => $this->applyProjectFilters($this->scopeProjectsForUser($query->active(), $user, $filters['scope']), $filters))
                ->count(),
            'pending_actions' => $this->approvalItems((clone $pendingActions)->latest('started_at')->limit(8)->get()),
            'revision_requests' => $this->approvalItems((clone $revisionRequests)->latest('started_at')->limit(8)->get()),
            'workflow_summary' => ProjectApproval::select('overall_status', DB::raw('count(*) as count'))
                ->whereHas('project', fn (Builder $query) => $this->applyProjectFilters($this->scopeProjectsForUser($query->active(), $user, $filters['scope']), $filters))
                ->groupBy('overall_status')
                ->orderBy('overall_status')
                ->get(),
            'projects_by_stage' => (clone $visibleProjects)
                ->select('current_stage_id', DB::raw('count(*) as count'), DB::raw("sum(case when record_type = 'investment' and currency = 'PHP' then ndc_participation else 0 end) as total_investment"))
                ->with('currentStage')
                ->groupBy('current_stage_id')
                ->get(),
            'projects_by_status' => (clone $visibleProjects)
                ->select('status_id', DB::raw('count(*) as count'))
                ->with('status')
                ->groupBy('status_id')
                ->get(),
            'projects_by_sector' => (clone $visibleProjects)
                ->select('sector_id', DB::raw('count(*) as count'), DB::raw("sum(case when record_type = 'investment' and currency = 'PHP' then ndc_participation else 0 end) as total_investment"))
                ->with('sector')
                ->groupBy('sector_id')
                ->get(),
            'monitoring_summary' => $monitoringSummary,
            'lifecycle_pipeline' => $this->lifecyclePipeline(clone $visibleProjects),
            'attention_summary' => [
                'approval_actions' => (clone $pendingActions)->count(),
                'revision_requests' => (clone $revisionRequests)->count(),
                'overdue_requirements' => $overdueRequirements,
                'overdue_tasks' => (clone $visibleTasks)->overdue()->count(),
                'monitoring_due' => $monitoringDue,
            ],

            // Decision-support additions.
            'decision_queue' => $this->decisionQueue($user, $filters, $pendingActions, $revisionRequests, $projects),
            'risk_projects' => $this->riskProjects($projects, $filters['due_window']),
            'workload' => $this->workload($projects, $user, $filters),
            'monitoring_compliance' => $this->monitoringCompliance($monitoringProjects, $filters['due_window']),
            'data_quality' => $this->dataQuality($projects),
            'legacy_portfolio' => [
                'active_records' => $legacyProjects->count(),
                'needs_details' => $legacyProjects->filter(fn (Project $project) => ($project->legacyDetail?->detail_status ?? 'needs_details') === 'needs_details')->count(),
                'in_progress' => $legacyProjects->filter(fn (Project $project) => ($project->legacyDetail?->detail_status ?? 'needs_details') === 'in_progress')->count(),
                'monitoring_active' => $legacyProjects->where('monitoring_status', 'active')->count(),
            ],
            'portfolio_summary' => $this->portfolioSummary($projects, $releasedFunds),
            'portfolio_trend' => $this->portfolioTrend($projects, $filters['year']),
            'decision_aging' => $this->decisionAging($pendingActions, $revisionRequests),
            'stage_breakdown' => $this->stageBreakdown($projects, $filters['record_source']),
            'sector_breakdown' => $this->sectorBreakdown($projects, $filters['record_source']),
            'filters' => $this->filterPayload($user, $filters),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function visibleProjectIds(User $user): array
    {
        return $this->scopeProjectsForUser(Project::active(), $user, $this->isPortfolioUser($user) ? 'portfolio' : 'mine')
            ->pluck('projects.id')
            ->all();
    }

    private function normalizeFilters(User $user, array $filters): array
    {
        $canViewPortfolio = $this->isPortfolioUser($user);
        $defaultScope = $canViewPortfolio ? 'portfolio' : 'mine';
        $scope = in_array($filters['scope'] ?? $defaultScope, ['portfolio', 'all'], true) ? 'portfolio' : 'mine';

        if (!$canViewPortfolio) {
            $scope = 'mine';
        }

        return [
            'year' => isset($filters['year']) ? (int) $filters['year'] : null,
            'due_window' => (string) ($filters['due_window'] ?? '14'),
            'scope' => $scope,
            'sector_id' => isset($filters['sector_id']) ? (int) $filters['sector_id'] : null,
            'stage_id' => isset($filters['stage_id']) ? (int) $filters['stage_id'] : null,
            'origin_track' => $filters['origin_track'] ?? null,
            'lifecycle_phase' => $filters['lifecycle_phase'] ?? null,
            'officer_id' => isset($filters['officer_id']) ? (int) $filters['officer_id'] : null,
            'record_source' => in_array($filters['record_source'] ?? null, ['pms', 'legacy'], true)
                ? $filters['record_source']
                : 'all',
        ];
    }

    private function projectQuery(User $user, array $filters): Builder
    {
        return $this->applyProjectFilters(
            $this->scopeProjectsForUser(Project::active(), $user, $filters['scope']),
            $filters
        );
    }

    private function scopeProjectsForUser(Builder $query, User $user, string $scope): Builder
    {
        return $query->accessibleTo(
            $user,
            ['dashboard.view', 'projects.view'],
            $scope !== 'portfolio' || ! $this->isAdministrator($user)
        );
    }

    private function applyProjectFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['sector_id'], fn (Builder $builder, int $sectorId) => $builder->where('sector_id', $sectorId))
            ->when($filters['stage_id'], fn (Builder $builder, int $stageId) => $builder->where('current_stage_id', $stageId))
            ->when($filters['origin_track'], fn (Builder $builder, string $track) => ProjectCategory::applyFilter($builder, ProjectCategory::categoryKey($track)))
            ->when($filters['lifecycle_phase'], fn (Builder $builder, string $phase) => $builder->where('lifecycle_phase', $phase))
            ->when($filters['officer_id'], fn (Builder $builder, int $officerId) => $builder->where('project_officer_id', $officerId))
            ->when($filters['record_source'] === 'pms', fn (Builder $builder) => $builder->where('is_legacy', false))
            ->when($filters['record_source'] === 'legacy', fn (Builder $builder) => $builder->where('is_legacy', true))
            ->when($filters['year'], function (Builder $builder, int $year) {
                $builder->where(function (Builder $dateQuery) use ($year) {
                    $dateQuery->whereYear('date_of_application', $year)
                        ->orWhere(function (Builder $fallback) use ($year) {
                            $fallback->whereNull('date_of_application')->whereYear('proposal_date', $year);
                        })
                        ->orWhere(function (Builder $fallback) use ($year) {
                            $fallback->whereNull('date_of_application')
                                ->whereNull('proposal_date')
                                ->whereYear('created_at', $year);
                        });
                });
            });
    }

    private function visibleTaskQuery(User $user, array $filters): Builder
    {
        return Task::active()->where(function (Builder $query) use ($user, $filters) {
            $query->where('assigned_to', $user->id)
                ->orWhereHas('project', fn (Builder $projectQuery) => $this->applyProjectFilters(
                    $this->scopeProjectsForUser($projectQuery->active(), $user, $filters['scope']),
                    $filters
                ));
        });
    }

    private function pendingApprovalActionQuery(User $user, array $filters): Builder
    {
        return ProjectApproval::query()
            ->whereNotNull('current_step_id')
            ->whereIn('overall_status', self::ACTIONABLE_APPROVAL_STATUSES)
            ->whereHas('project', fn (Builder $query) => $this->applyProjectFilters(
                $this->scopeProjectsForUser($query->active(), $user, $filters['scope']),
                $filters
            ))
            ->when(!$this->isAdministrator($user), function (Builder $query) use ($user) {
                $query->where(function (Builder $scoped) use ($user) {
                    $scoped->whereHas('currentStep', fn (Builder $step) => $step->where('role_id', $user->default_role_id))
                        ->orWhere(function (Builder $proponent) use ($user) {
                            $proponent->whereHas('currentStep', fn (Builder $step) => $step->where('step_order', 1))
                                ->whereHas('project', fn (Builder $project) => $project->where('created_by', $user->id));
                        });
                });
            });
    }

    private function revisionRequestQuery(User $user, array $filters): Builder
    {
        return ProjectApproval::query()
            ->where('overall_status', 'returned')
            ->whereHas('project', fn (Builder $query) => $this->applyProjectFilters(
                $this->scopeProjectsForUser($query->active(), $user, $filters['scope']),
                $filters
            ));
    }

    private function approvalItems(Collection $approvals): Collection
    {
        $approvals->loadMissing(['project.status', 'project.currentStage', 'currentStep.role']);

        return $approvals->map(fn (ProjectApproval $approval) => [
            'approval_id' => $approval->id,
            'project_id' => $approval->project_id,
            'project_code' => $approval->project?->project_code,
            'title' => $approval->project?->title ?? 'Untitled project',
            'is_legacy' => (bool) $approval->project?->is_legacy,
            'overall_status' => $approval->overall_status,
            'current_step' => $approval->currentStep?->step_name ?? 'Unassigned step',
            'role' => $approval->currentStep?->role?->name ?? 'Unassigned role',
            'stage' => $approval->project?->currentStage?->name ?? 'Unassigned',
            'status' => $approval->project?->status?->name ?? 'Unassigned',
            'started_at' => $approval->started_at?->toDateTimeString(),
        ]);
    }

    private function decisionQueue(User $user, array $filters, Builder $pending, Builder $revisions, Collection $projects): array
    {
        $approvalItems = $this->approvalItems((clone $pending)->latest('started_at')->limit(12)->get())
            ->map(fn (array $item) => [
                ...$item,
                'type' => 'approval',
                'priority' => $this->waitingPriority($item['started_at']),
                'due_date' => null,
                'action_label' => 'Review decision',
                'route' => $this->projectRoute($item['project_id'], $item['is_legacy'], 'approval'),
            ]);
        $revisionItems = $this->approvalItems((clone $revisions)->latest('started_at')->limit(12)->get())
            ->map(fn (array $item) => [
                ...$item,
                'type' => 'revision',
                'priority' => $this->waitingPriority($item['started_at']),
                'due_date' => null,
                'action_label' => 'Resolve revision',
                'route' => $this->projectRoute($item['project_id'], $item['is_legacy'], 'approval'),
            ]);

        $monitoringItems = collect();
        if ($this->isPortfolioUser($user)) {
            $monitoringItems = $projects
                ->where('monitoring_status', 'active')
                ->where('monitoring_submission_status', 'submitted')
                ->map(fn (Project $project) => [
                    'approval_id' => null,
                    'project_id' => $project->id,
                    'project_code' => $project->project_code,
                    'title' => $project->title,
                    'overall_status' => 'submitted',
                    'current_step' => 'Monitoring review',
                    'role' => $project->workgroupHead?->full_name ?? 'Portfolio reviewer',
                    'stage' => $project->currentStage?->name ?? 'Unassigned',
                    'status' => $project->status?->name ?? 'Unassigned',
                    'started_at' => $project->monitoring_submitted_at?->toDateTimeString(),
                    'type' => 'monitoring',
                    'priority' => $this->datePriority($project->monitoring_due_date),
                    'due_date' => $project->monitoring_due_date?->toDateString(),
                    'action_label' => 'Review report',
                    'route' => $this->projectRoute($project->id, (bool) $project->is_legacy, 'monitoring'),
                ]);
        }

        return $approvalItems
            ->concat($revisionItems)
            ->concat($monitoringItems)
            ->sortBy(fn (array $item) => sprintf(
                '%d|%s',
                match ($item['priority']) { 'critical' => 0, 'high' => 1, default => 2 },
                $item['due_date'] ?? $item['started_at'] ?? '9999-12-31'
            ))
            ->take(16)
            ->values()
            ->all();
    }

    private function riskProjects(Collection $projects, string $dueWindow): array
    {
        return $projects->map(function (Project $project) {
            $reasons = [];
            $score = 0;

            if ($project->target_completion_date?->isPast() && !$project->actual_completion_date) {
                $reasons[] = ['code' => 'completion_overdue', 'label' => 'Target completion overdue'];
                $score += 5;
            }
            if ($project->overdue_tasks_count > 0) {
                $reasons[] = ['code' => 'tasks_overdue', 'label' => $project->overdue_tasks_count . ' overdue task(s)'];
                $score += min(4, $project->overdue_tasks_count);
            }
            if ($project->overdue_requirements_count > 0) {
                $reasons[] = ['code' => 'requirements_overdue', 'label' => $project->overdue_requirements_count . ' overdue requirement(s)'];
                $score += min(4, $project->overdue_requirements_count);
            }
            if ($project->monitoring_status === 'active' && $project->monitoring_submission_status !== 'accepted' && $project->monitoring_due_date?->isPast()) {
                $reasons[] = ['code' => 'monitoring_overdue', 'label' => 'Monitoring report overdue'];
                $score += 5;
            }
            if ($project->estimated_cost && $project->actual_cost && (float) $project->actual_cost > (float) $project->estimated_cost) {
                $reasons[] = ['code' => 'cost_overrun', 'label' => 'Actual cost exceeds estimate'];
                $score += 3;
            }

            return [
                'project_id' => $project->id,
                'project_code' => $project->project_code,
                'title' => $project->title,
                'stage' => $project->currentStage?->name ?? 'Unassigned',
                'officer' => $project->projectOfficer?->full_name ?? 'Unassigned',
                'risk_score' => $score,
                'risk_level' => $score >= 7 ? 'critical' : ($score >= 3 ? 'high' : 'watch'),
                'reasons' => $reasons,
                'target_completion_date' => $project->target_completion_date?->toDateString(),
                'monitoring_due_date' => $project->monitoring_due_date?->toDateString(),
                'route' => $this->projectRoute($project->id, (bool) $project->is_legacy),
            ];
        })->filter(function (array $item) use ($dueWindow) {
            if ($item['risk_score'] === 0) {
                return false;
            }
            if ($dueWindow === 'all') {
                return true;
            }

            $dates = collect([$item['target_completion_date'], $item['monitoring_due_date']])->filter();
            if ($dates->isEmpty()) {
                return true;
            }
            if ($dueWindow === 'overdue') {
                return $dates->contains(fn (string $date) => Carbon::parse($date)->lt(today()));
            }

            return $dates->contains(fn (string $date) => Carbon::parse($date)->betweenIncluded(today(), today()->addDays((int) $dueWindow)) || Carbon::parse($date)->lt(today()));
        })->sortByDesc('risk_score')->take(12)->values()->all();
    }

    private function workload(Collection $projects, User $user, array $filters): array
    {
        $officerIds = $projects->pluck('project_officer_id')->filter()->unique()->values();
        $officers = User::query()->whereIn('id', $officerIds)->get()->keyBy('id');
        $tasks = Task::active()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereIn('project_id', $projects->pluck('id'))
            ->get();

        $rows = $officerIds->map(function (int $officerId) use ($projects, $officers, $tasks) {
            $officerProjects = $projects->where('project_officer_id', $officerId);
            $officerTasks = $tasks->where('assigned_to', $officerId);
            $overdue = $officerTasks->filter(fn (Task $task) => $task->due_date?->isPast())->count();

            return [
                'user_id' => $officerId,
                'name' => $officers->get($officerId)?->full_name ?? 'Unknown officer',
                'active_projects' => $officerProjects->count(),
                'open_tasks' => $officerTasks->count(),
                'overdue_tasks' => $overdue,
                'load_level' => $overdue > 2 || $officerProjects->count() > 8 ? 'high' : ($overdue > 0 || $officerProjects->count() > 4 ? 'moderate' : 'balanced'),
            ];
        })->sortByDesc(fn (array $row) => $row['overdue_tasks'] * 10 + $row['active_projects'])->values();

        return [
            'mode' => $this->isPortfolioUser($user) ? 'team' : 'personal',
            'totals' => [
                'officers' => $rows->count(),
                'active_projects' => $projects->count(),
                'open_tasks' => $tasks->count(),
                'overdue_tasks' => $tasks->filter(fn (Task $task) => $task->due_date?->isPast())->count(),
                'unassigned_projects' => $projects->whereNull('project_officer_id')->count(),
            ],
            'officers' => $rows->all(),
        ];
    }

    private function monitoringCompliance(Collection $projects, string $dueWindow): array
    {
        $dueProjects = $projects->filter(fn (Project $project) => $this->matchesDueWindow($project->monitoring_due_date, $dueWindow));
        $submittedStatuses = ['submitted', 'accepted'];

        return [
            'active' => $projects->count(),
            'due_in_window' => $dueProjects->count(),
            'overdue' => $projects->filter(fn (Project $project) => $project->monitoring_due_date?->isPast() && $project->monitoring_submission_status !== 'accepted')->count(),
            'submitted' => $projects->where('monitoring_submission_status', 'submitted')->count(),
            'accepted' => $projects->where('monitoring_submission_status', 'accepted')->count(),
            'missing_due_date' => $projects->whereNull('monitoring_due_date')->count(),
            'compliance_rate' => $dueProjects->count() > 0
                ? round(($dueProjects->whereIn('monitoring_submission_status', $submittedStatuses)->count() / $dueProjects->count()) * 100, 1)
                : 100.0,
            'projects' => $dueProjects->sortBy('monitoring_due_date')->take(10)->map(fn (Project $project) => [
                'project_id' => $project->id,
                'project_code' => $project->project_code,
                'title' => $project->title,
                'is_legacy' => (bool) $project->is_legacy,
                'due_date' => $project->monitoring_due_date?->toDateString(),
                'submission_status' => $project->monitoring_submission_status ?? 'not_started',
                'is_overdue' => $project->monitoring_due_date?->isPast() && $project->monitoring_submission_status !== 'accepted',
            ])->values()->all(),
        ];
    }

    private function dataQuality(Collection $projects): array
    {
        $records = $projects->map(function (Project $project) {
            $missing = collect([
                'project_officer' => $project->project_officer_id,
                'sector' => $project->sector_id,
                'estimated_cost' => $project->estimated_cost,
                'proposal_date' => $project->date_of_application ?? $project->proposal_date,
                'target_completion_date' => $project->target_completion_date,
            ])->filter(fn ($value) => $value === null || $value === '')->keys()->values()->all();

            if ($project->monitoring_status === 'active' && !$project->monitoring_due_date) {
                $missing[] = 'monitoring_due_date';
            }

            return [
                'project_id' => $project->id,
                'project_code' => $project->project_code,
                'title' => $project->title,
                'is_legacy' => (bool) $project->is_legacy,
                'missing_fields' => $missing,
                'completeness' => round(((6 - min(6, count($missing))) / 6) * 100),
            ];
        });
        $complete = $records->where('completeness', 100)->count();

        return [
            'total_projects' => $projects->count(),
            'complete_projects' => $complete,
            'projects_with_issues' => $records->count() - $complete,
            'completeness_rate' => $projects->count() > 0 ? round(($complete / $projects->count()) * 100, 1) : 100.0,
            'records' => $records->where('completeness', '<', 100)->sortBy('completeness')->take(10)->values()->all(),
        ];
    }

    private function filterPayload(User $user, array $filters): array
    {
        $base = $this->scopeProjectsForUser(Project::active(), $user, $filters['scope']);
        $years = (clone $base)->get(['date_of_application', 'proposal_date', 'created_at'])
            ->map(fn (Project $project) => ($project->date_of_application ?? $project->proposal_date ?? $project->created_at)?->year)
            ->filter()->unique()->sortDesc()->values()->all();

        return [
            'applied' => $filters,
            'available_years' => $years,
            'due_windows' => [
                ['value' => 'overdue', 'label' => 'Overdue only'],
                ['value' => '7', 'label' => 'Next 7 days'],
                ['value' => '14', 'label' => 'Next 14 days'],
                ['value' => '30', 'label' => 'Next 30 days'],
                ['value' => 'all', 'label' => 'All dates'],
            ],
            'scopes' => $this->isPortfolioUser($user)
                ? [['value' => 'portfolio', 'label' => 'Portfolio'], ['value' => 'mine', 'label' => 'My assignments']]
                : [['value' => 'mine', 'label' => 'My assignments']],
            'sectors' => Sector::query()->orderBy('name')->get(['id', 'name']),
            'stages' => ProjectStage::query()->where('is_active', true)->orderBy('sequence_order')->get(['id', 'name']),
            'origin_tracks' => [
                ['value' => ProjectCategory::TRADITIONAL_EXTERNAL, 'label' => 'Traditional / External Investment'],
                ['value' => ProjectCategory::STARTUP_VENTURE, 'label' => 'Startup Venture'],
                ['value' => ProjectCategory::JOINT_VENTURE, 'label' => 'Joint Venture'],
                ['value' => ProjectCategory::NDC_INITIATED, 'label' => 'NDC-Initiated'],
            ],
            'lifecycle_phases' => [
                ['value' => 'development', 'label' => 'Development'],
                ['value' => 'implementation_monitoring', 'label' => 'Implementation & Monitoring'],
                ['value' => 'post_investment', 'label' => 'Post-Investment'],
                ['value' => 'divestment', 'label' => 'Divestment'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
            'officers' => User::query()
                ->whereIn('id', (clone $base)->whereNotNull('project_officer_id')->distinct()->pluck('project_officer_id'))
                ->orderBy('first_name')->orderBy('last_name')->get()
                ->map(fn (User $officer) => ['id' => $officer->id, 'name' => $officer->full_name]),
            'record_sources' => [
                ['value' => 'all', 'label' => 'All records'],
                ['value' => 'pms', 'label' => 'PMS projects'],
                ['value' => 'legacy', 'label' => 'Legacy projects'],
            ],
            'role' => [
                'name' => $user->defaultRole?->name ?? 'User',
                'mode' => $this->isPortfolioUser($user) ? 'portfolio' : 'officer',
                'can_view_portfolio' => $this->isPortfolioUser($user),
                'default_scope' => $this->isPortfolioUser($user) ? 'portfolio' : 'mine',
            ],
        ];
    }

    private function portfolioSummary(Collection $projects, float $releasedFunds): array
    {
        $investments = $projects->where('record_type', 'investment');
        return [
            'active_ndc_projects' => $projects->where('record_type', 'project')->whereNotIn('lifecycle_phase', ['completed'])->count(),
            'investments_under_evaluation' => $investments->where('investment_status', 'under_evaluation')->count(),
            'board_approved_investments' => $investments->where('investment_status', 'board_approved')->count(),
            'investment_portfolio' => $investments->where('investment_status', 'portfolio')->count(),
            'unclassified_records' => $projects->whereNull('record_type')->count(),
            'ndc_investment_by_currency' => $investments->groupBy('currency')->map(fn ($group) => round((float) $group->sum('ndc_participation'), 2)),
            'investment_amount_missing' => $investments->whereNull('ndc_participation')->count(),
            'active_projects' => $projects->count(),
            'pms_projects' => $projects->where('is_legacy', false)->count(),
            'legacy_projects' => $projects->where('is_legacy', true)->count(),
            'estimated_investment' => round((float) $investments->where('currency', 'PHP')->sum('ndc_participation'), 2),
            'actual_cost' => round((float) $projects->sum(fn (Project $project) => (float) ($project->actual_cost ?? 0)), 2),
            'released_funds' => round($releasedFunds, 2),
            'completed_ytd' => $projects->filter(fn (Project $project) => $project->actual_completion_date?->year === now()->year)->count(),
            'unassigned_projects' => $projects->whereNull('project_officer_id')->count(),
        ];
    }

    private function portfolioTrend(Collection $projects, ?int $year): array
    {
        $start = $year
            ? Carbon::create($year, 1, 1)->startOfMonth()
            : now()->startOfMonth()->subMonths(11);

        return collect(range(0, 11))->map(function (int $offset) use ($projects, $start) {
            $month = $start->copy()->addMonths($offset);
            $intakes = $projects->filter(function (Project $project) use ($month) {
                $date = $project->date_of_application ?? $project->proposal_date ?? $project->created_at;
                return $date?->year === $month->year && $date?->month === $month->month;
            })->count();
            $completions = $projects->filter(fn (Project $project) =>
                $project->actual_completion_date?->year === $month->year
                && $project->actual_completion_date?->month === $month->month
            )->count();

            return [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'intakes' => $intakes,
                'completions' => $completions,
            ];
        })->all();
    }

    private function decisionAging(Builder $pending, Builder $revisions): array
    {
        $decisions = (clone $pending)->get(['id', 'started_at', 'current_step_started_at', 'sla_due_at'])
            ->concat((clone $revisions)->get(['id', 'started_at', 'current_step_started_at', 'sla_due_at']))
            ->unique('id');

        $ages = $decisions->map(function (ProjectApproval $approval) {
            $started = $approval->current_step_started_at ?? $approval->started_at;
            return $started ? $started->diffInDays(now()) : 0;
        });

        return [
            'total' => $decisions->count(),
            'within_7_days' => $ages->filter(fn (int $days) => $days < 7)->count(),
            'days_7_to_13' => $ages->filter(fn (int $days) => $days >= 7 && $days < 14)->count(),
            'days_14_plus' => $ages->filter(fn (int $days) => $days >= 14)->count(),
            'sla_breached' => $decisions->filter(function (ProjectApproval $approval) {
                if ($approval->sla_due_at) {
                    return $approval->sla_due_at->isPast();
                }
                $started = $approval->current_step_started_at ?? $approval->started_at;
                return $started?->lte(now()->subDays(14)) ?? false;
            })->count(),
        ];
    }

    private function stageBreakdown(Collection $projects, string $recordSource): array
    {
        return $this->breakdown(
            $projects,
            fn (Project $project) => $project->current_stage_id ?: 0,
            fn (Project $project) => $project->currentStage?->name ?? 'Unassigned',
            $recordSource,
            'stage_id'
        );
    }

    private function sectorBreakdown(Collection $projects, string $recordSource): array
    {
        return $this->breakdown(
            $projects,
            fn (Project $project) => $project->sector_id ?: 0,
            fn (Project $project) => $project->sector?->name ?? 'Unassigned',
            $recordSource,
            'sector_id'
        );
    }

    private function breakdown(
        Collection $projects,
        callable $keyResolver,
        callable $labelResolver,
        string $recordSource,
        string $filterKey
    ): array {
        $total = max(1, $projects->count());
        $routePath = $recordSource === 'legacy' ? '/projects/legacy' : '/admin/reports';

        return $projects->groupBy($keyResolver)->map(function (Collection $group) use ($labelResolver, $total, $routePath, $recordSource, $filterKey) {
            $project = $group->first();
            $id = (int) ($filterKey === 'stage_id' ? $project->current_stage_id : $project->sector_id);

            return [
                'id' => $id ?: null,
                'label' => $labelResolver($project),
                'count' => $group->count(),
                'investment' => round((float) $group->sum(fn (Project $item) => (float) ($item->record_type === 'investment' && $item->currency === 'PHP' ? $item->ndc_participation : 0)), 2),
                'percentage' => round(($group->count() / $total) * 100, 1),
                'route' => [
                    'path' => $routePath,
                    'query' => array_filter([
                        $filterKey => $id ?: null,
                        'is_legacy' => $recordSource === 'all' ? null : ($recordSource === 'legacy' ? 1 : 0),
                    ], fn ($value) => $value !== null),
                ],
            ];
        })->sortByDesc('count')->values()->all();
    }

    private function monitoringSummary(Collection $projects): array
    {
        $summary = [
            'direct_jobs' => 0.0,
            'indirect_jobs' => 0.0,
            'retained_jobs' => 0.0,
            'total_jobs' => 0.0,
            'projected_revenue' => 0.0,
            'actual_revenue' => 0.0,
            'dividend_remittance' => 0.0,
            'estimated_cost' => 0.0,
            'actual_cost' => 0.0,
            'reportable_projects' => 0,
            'gcg_relevant_projects' => 0,
            'projects_with_indicators' => 0,
        ];

        foreach ($projects as $project) {
            $metrics = (array) ($project->financial_metrics ?? []);
            $summary['direct_jobs'] += $this->metricNumber($metrics['jobs_generated_direct'] ?? null);
            $summary['indirect_jobs'] += $this->metricNumber($metrics['jobs_generated_indirect'] ?? null);
            $summary['retained_jobs'] += $this->metricNumber($metrics['retained_jobs'] ?? null);
            $summary['projected_revenue'] += $this->metricNumber($metrics['projected_revenue'] ?? null);
            $summary['actual_revenue'] += $this->metricNumber($metrics['actual_revenue'] ?? null);
            $summary['dividend_remittance'] += $this->metricNumber($metrics['dividend_remittance'] ?? null);
            $summary['estimated_cost'] += $this->metricNumber($project->estimated_cost);
            $summary['actual_cost'] += $this->metricNumber($project->actual_cost);

            if ($this->metricBool($metrics['reportable_to_gcg'] ?? $metrics['is_reportable'] ?? false)) {
                $summary['reportable_projects']++;
            }
            if ($this->metricBool($metrics['gcg_relevance'] ?? false) || trim((string) ($metrics['gcg_metrics'] ?? '')) !== '') {
                $summary['gcg_relevant_projects']++;
            }
            if (collect(['monitoring_indicators', 'gcg_metrics', 'social_impact_notes'])->contains(fn (string $key) => trim((string) ($metrics[$key] ?? '')) !== '')) {
                $summary['projects_with_indicators']++;
            }
        }

        $summary['total_jobs'] = $summary['direct_jobs'] + $summary['indirect_jobs'] + $summary['retained_jobs'];

        return $summary;
    }

    private function lifecyclePipeline(Builder $query): array
    {
        $groups = [
            'Intake' => ['Intake'],
            'Requirements' => ['Requirements'],
            'Evaluation' => ['Due Diligence'],
            'Management Approval' => ['Management Review', 'Board Approval'],
            'Agreement & Release' => ['Agreement & Fund Release'],
            'Implementation' => ['Implementation & Monitoring'],
            'Post-Investment' => ['Post-Investment Strategy', 'Divestment', 'Completion'],
        ];

        return collect($groups)->map(fn (array $stages, string $label) => [
            'label' => $label,
            'count' => (clone $query)->whereHas('currentStage', fn (Builder $stage) => $stage->whereIn('name', $stages))->count(),
        ])->values()->all();
    }

    private function matchesDueWindow(?CarbonInterface $date, string $window): bool
    {
        if (!$date) {
            return false;
        }
        if ($window === 'all') {
            return true;
        }
        if ($window === 'overdue') {
            return $date->lt(today());
        }

        return $date->betweenIncluded(today(), today()->addDays((int) $window));
    }

    private function isDueWithin(?CarbonInterface $date, int $days, bool $includeOverdue = true): bool
    {
        if (!$date) {
            return false;
        }

        return $includeOverdue
            ? $date->lte(today()->addDays($days))
            : $date->betweenIncluded(today(), today()->addDays($days));
    }

    private function waitingPriority(?string $startedAt): string
    {
        if (!$startedAt) {
            return 'normal';
        }
        $days = Carbon::parse($startedAt)->diffInDays(now());

        return $days >= 14 ? 'critical' : ($days >= 7 ? 'high' : 'normal');
    }

    private function projectRoute(int $projectId, bool $isLegacy, string $tab = 'overview'): array
    {
        return [
            'path' => $isLegacy ? '/projects/legacy' : '/projects',
            'query' => array_filter([
                'project_id' => $projectId,
                'tab' => $isLegacy ? null : $tab,
            ], fn ($value) => $value !== null),
        ];
    }

    private function datePriority(?CarbonInterface $date): string
    {
        if (!$date) {
            return 'normal';
        }
        if ($date->isPast()) {
            return 'critical';
        }

        return $date->lte(today()->addDays(7)) ? 'high' : 'normal';
    }

    private function isPortfolioUser(User $user): bool
    {
        return in_array(strtolower((string) $user->defaultRole?->name), self::PORTFOLIO_ROLES, true)
            || (int) $user->default_role_id === 1;
    }

    private function isAdministrator(User $user): bool
    {
        return in_array(strtolower((string) $user->defaultRole?->name), ['superadmin', 'admin'], true)
            || (int) $user->default_role_id === 1;
    }

    private function metricNumber($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function metricBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
