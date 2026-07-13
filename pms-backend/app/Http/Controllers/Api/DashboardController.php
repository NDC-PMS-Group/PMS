<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function stats(Request $request, DashboardService $dashboardService): DashboardResource
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:' . (now()->year + 5)],
            'due_window' => ['nullable', Rule::in(['all', 'overdue', '7', '14', '30'])],
            'scope' => ['nullable', Rule::in(['mine', 'portfolio', 'all'])],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'stage_id' => ['nullable', 'integer', 'exists:project_stages,id'],
            'origin_track' => ['nullable', Rule::in(['bdg_investment', 'spg_traditional', 'spg_jv', 'spg_ndc_own'])],
            'lifecycle_phase' => ['nullable', Rule::in(['development', 'implementation_monitoring', 'post_investment', 'divestment', 'completed'])],
            'officer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return new DashboardResource($dashboardService->build($request->user(), $filters));
    }

    public function recentActivities(Request $request)
    {
        $user = $request->user();
        $projectIds = app(DashboardService::class)->visibleProjectIds($user);

        $activities = AuditLog::with('user')
            ->where(function ($query) use ($user, $projectIds) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($projectAuditQuery) use ($projectIds) {
                        $projectAuditQuery->whereIn('entity_type', [Project::class, 'Project', 'project'])
                            ->whereIn('entity_id', $projectIds);
                    });
            })
            ->latest('created_at')
            ->limit(20)
            ->get();

        return response()->json($activities);
    }

    public function upcomingDeadlines(Request $request)
    {
        $tasks = Task::active()
            ->where('assigned_to', $request->user()->id)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays(7))
            ->orderBy('due_date')
            ->with('project')
            ->get();

        return response()->json($tasks);
    }
}
