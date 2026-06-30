<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectRequirement;
use App\Models\Task;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
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

    public function stats(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $visibleProjects = $this->visibleProjectQuery($user);
        $pendingActionQuery = $this->pendingApprovalActionQuery($user);
        $revisionRequestQuery = $this->revisionRequestQuery($user);
        $monitoringSummary = $this->monitoringSummary((clone $visibleProjects)
            ->where('monitoring_status', 'active')
            ->get([
            'financial_metrics',
            'estimated_cost',
            'actual_cost',
        ]));
        $lifecyclePipeline = $this->lifecyclePipeline(clone $visibleProjects);
        $overdueRequirements = ProjectRequirement::query()
            ->whereIn('status', ['requested', 'deferred', 'for_further_evaluation'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->whereHas('project', fn ($query) => $this->scopeProjectsForUser($query->active(), $user))
            ->count();
        $monitoringDue = (clone $visibleProjects)
            ->where('monitoring_status', 'active')
            ->whereNotNull('monitoring_due_date')
            ->whereDate('monitoring_due_date', '<=', today()->addDays(14))
            ->count();

        return response()->json([
            'total_projects' => (clone $visibleProjects)->count(),
            'my_projects' => (clone $visibleProjects)->count(),
            'pending_approvals' => (clone $pendingActionQuery)->count(),
            'overdue_tasks' => $this->visibleTaskQuery($user)->overdue()->count(),
            'my_tasks' => Task::active()
                ->where('assigned_to', $userId)
                ->count(),
            'completed_this_month' => (clone $visibleProjects)
                ->whereMonth('actual_completion_date', now()->month)
                ->count(),
            'approved_with_conditions' => ProjectApproval::where('overall_status', 'approved_with_conditions')
                ->whereHas('project', fn ($projectQuery) => $this->scopeProjectsForUser($projectQuery->active(), $user))
                ->count(),
            'revision_requests_count' => (clone $revisionRequestQuery)->count(),
            'active_workflows' => ProjectApproval::whereIn('overall_status', self::ACTIONABLE_APPROVAL_STATUSES)
                ->whereHas('project', fn ($projectQuery) => $this->scopeProjectsForUser($projectQuery->active(), $user))
                ->count(),
            'pending_actions' => (clone $pendingActionQuery)
                ->with(['project.status', 'project.currentStage', 'currentStep.role'])
                ->latest('started_at')
                ->limit(8)
                ->get()
                ->map(fn ($approval) => [
                    'approval_id' => $approval->id,
                    'project_id' => $approval->project_id,
                    'project_code' => $approval->project?->project_code,
                    'title' => $approval->project?->title,
                    'overall_status' => $approval->overall_status,
                    'current_step' => $approval->currentStep?->step_name,
                    'role' => $approval->currentStep?->role?->name,
                    'stage' => $approval->project?->currentStage?->name,
                    'status' => $approval->project?->status?->name,
                    'started_at' => $approval->started_at?->toDateTimeString(),
                ]),
            'revision_requests' => (clone $revisionRequestQuery)
                ->with(['project.status', 'project.currentStage', 'currentStep.role'])
                ->latest('started_at')
                ->limit(8)
                ->get()
                ->map(fn ($approval) => [
                    'approval_id' => $approval->id,
                    'project_id' => $approval->project_id,
                    'project_code' => $approval->project?->project_code,
                    'title' => $approval->project?->title,
                    'overall_status' => $approval->overall_status,
                    'current_step' => $approval->currentStep?->step_name,
                    'role' => $approval->currentStep?->role?->name,
                    'stage' => $approval->project?->currentStage?->name,
                    'status' => $approval->project?->status?->name,
                    'started_at' => $approval->started_at?->toDateTimeString(),
                ]),
            'workflow_summary' => ProjectApproval::select('overall_status', DB::raw('count(*) as count'))
                ->whereHas('project', fn ($projectQuery) => $this->scopeProjectsForUser($projectQuery->active(), $user))
                ->groupBy('overall_status')
                ->orderBy('overall_status')
                ->get(),
            'projects_by_stage' => (clone $visibleProjects)
                ->select('current_stage_id', DB::raw('count(*) as count'), DB::raw('sum(estimated_cost) as total_investment'))
                ->with('currentStage')
                ->groupBy('current_stage_id')
                ->get(),
            'projects_by_status' => (clone $visibleProjects)
                ->select('status_id', DB::raw('count(*) as count'))
                ->with('status')
                ->groupBy('status_id')
                ->get(),
            'projects_by_sector' => (clone $visibleProjects)
                ->select('sector_id', DB::raw('count(*) as count'), DB::raw('sum(estimated_cost) as total_investment'))
                ->with('sector')
                ->groupBy('sector_id')
                ->get(),
            'monitoring_summary' => $monitoringSummary,
            'lifecycle_pipeline' => $lifecyclePipeline,
            'attention_summary' => [
                'approval_actions' => (clone $pendingActionQuery)->count(),
                'revision_requests' => (clone $revisionRequestQuery)->count(),
                'overdue_requirements' => $overdueRequirements,
                'overdue_tasks' => $this->visibleTaskQuery($user)->overdue()->count(),
                'monitoring_due' => $monitoringDue,
            ],
        ]);
    }

    private function visibleProjectQuery($user)
    {
        return $this->scopeProjectsForUser(Project::active(), $user);
    }

    private function visibleTaskQuery($user)
    {
        $roleId = (int) $user->default_role_id;
        $isSuperAdmin = $roleId === 1 || $user->hasRole('superadmin');

        return Task::active()
            ->when(!$isSuperAdmin, function ($query) use ($user) {
                $query->where(function ($taskQuery) use ($user) {
                    $taskQuery
                        ->where('assigned_to', $user->id)
                        ->orWhereHas('project', fn ($projectQuery) => $this->scopeProjectsForUser($projectQuery->active(), $user));
                });
            });
    }

    private function scopeProjectsForUser($query, $user)
    {
        $query->visibleDraftsTo($user);

        $roleId = (int) $user->default_role_id;
        $isSuperAdmin = $roleId === 1 || $user->hasRole('superadmin');

        if ($isSuperAdmin) {
            return $query;
        }

        return $query->where(function ($projectQuery) use ($user) {
            $projectQuery
                ->where('created_by', $user->id)
                ->orWhere('project_officer_id', $user->id)
                ->orWhere('workgroup_head_id', $user->id)
                ->orWhereHas('members', fn ($memberQuery) => $memberQuery
                    ->active()
                    ->where('user_id', $user->id));
        });
    }

    private function pendingApprovalActionQuery($user)
    {
        $roleId = (int) $user->default_role_id;
        $isSuperAdmin = $roleId === 1 || $user->hasRole('superadmin');

        return ProjectApproval::query()
            ->whereNotNull('current_step_id')
            ->whereIn('overall_status', self::ACTIONABLE_APPROVAL_STATUSES)
            ->whereHas('project', fn ($projectQuery) => $projectQuery->active())
            ->when(!$isSuperAdmin, function ($query) use ($user, $roleId) {
                $query->where(function ($scoped) use ($user, $roleId) {
                    $scoped->whereHas('currentStep', fn ($stepQuery) => $stepQuery->where('role_id', $roleId))
                        ->orWhere(function ($proponentQuery) use ($user) {
                            $proponentQuery
                                ->whereHas('currentStep', fn ($stepQuery) => $stepQuery->where('step_order', 1))
                                ->whereHas('project', fn ($projectQuery) => $projectQuery->where('created_by', $user->id));
                        });
                });
            });
    }

    private function revisionRequestQuery($user)
    {
        $roleId = (int) $user->default_role_id;
        $isSuperAdmin = $roleId === 1 || $user->hasRole('superadmin');

        return ProjectApproval::query()
            ->where('overall_status', 'returned')
            ->whereHas('project', fn ($projectQuery) => $projectQuery->active())
            ->when(!$isSuperAdmin, function ($query) use ($user) {
                $query->whereHas('project', function ($projectQuery) use ($user) {
                    $projectQuery
                        ->where('created_by', $user->id)
                        ->orWhere('project_officer_id', $user->id)
                        ->orWhere('workgroup_head_id', $user->id)
                        ->orWhereHas('members', fn ($memberQuery) => $memberQuery
                            ->active()
                            ->where('user_id', $user->id));
                });
            });
    }

    private function monitoringSummary($projects): array
    {
        $summary = [
            'direct_jobs' => 0,
            'indirect_jobs' => 0,
            'retained_jobs' => 0,
            'total_jobs' => 0,
            'projected_revenue' => 0,
            'actual_revenue' => 0,
            'dividend_remittance' => 0,
            'estimated_cost' => 0,
            'actual_cost' => 0,
            'reportable_projects' => 0,
            'gcg_relevant_projects' => 0,
            'projects_with_indicators' => 0,
        ];

        foreach ($projects as $project) {
            $metrics = (array) ($project->financial_metrics ?? []);

            $direct = $this->metricNumber($metrics['jobs_generated_direct'] ?? null);
            $indirect = $this->metricNumber($metrics['jobs_generated_indirect'] ?? null);
            $retained = $this->metricNumber($metrics['retained_jobs'] ?? null);

            $summary['direct_jobs'] += $direct;
            $summary['indirect_jobs'] += $indirect;
            $summary['retained_jobs'] += $retained;
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

            if (
                trim((string) ($metrics['monitoring_indicators'] ?? '')) !== ''
                || trim((string) ($metrics['gcg_metrics'] ?? '')) !== ''
                || trim((string) ($metrics['social_impact_notes'] ?? '')) !== ''
            ) {
                $summary['projects_with_indicators']++;
            }
        }

        $summary['total_jobs'] = $summary['direct_jobs'] + $summary['indirect_jobs'] + $summary['retained_jobs'];

        return $summary;
    }

    private function lifecyclePipeline($query): array
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

        return collect($groups)
            ->map(function (array $stages, string $label) use ($query) {
                return [
                    'label' => $label,
                    'count' => (clone $query)
                        ->whereHas('currentStage', fn ($stageQuery) => $stageQuery->whereIn('name', $stages))
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function metricNumber($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function metricBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function recentActivities(Request $request)
    {
        $activities = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json($activities);
    }

    public function upcomingDeadlines(Request $request)
    {
        $userId = $request->user()->id;

        $tasks = Task::active()
            ->where('assigned_to', $userId)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->orderBy('due_date')
            ->with('project')
            ->get();

        return response()->json($tasks);
    }
}
