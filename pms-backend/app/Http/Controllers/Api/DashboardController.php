<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectApproval;
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
        'for_workgroup_review',
        'for_mancom_review',
        'for_board_approval',
        'for_approval',
    ];

    public function stats(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $visibleProjects = $this->visibleProjectQuery($user);
        $pendingActionQuery = $this->pendingApprovalActionQuery($user);
        $revisionRequestQuery = $this->revisionRequestQuery($user);

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
                ->select('current_stage_id', DB::raw('count(*) as count'))
                ->with('currentStage')
                ->groupBy('current_stage_id')
                ->get(),
            'projects_by_status' => (clone $visibleProjects)
                ->select('status_id', DB::raw('count(*) as count'))
                ->with('status')
                ->groupBy('status_id')
                ->get(),
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
