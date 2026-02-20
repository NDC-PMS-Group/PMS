<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'total_projects' => Project::active()->count(),
            'my_projects' => Project::active()
                ->where(function($q) use ($userId) {
                    $q->where('project_officer_id', $userId)
                      ->orWhere('created_by', $userId);
                })->count(),
            'pending_approvals' => Project::where('status_id', function($q) {
                $q->select('id')->from('project_statuses')
                  ->where('name', 'For Approval')->limit(1);
            })->count(),
            'overdue_tasks' => Task::active()->overdue()->count(),
            'my_tasks' => Task::active()
                ->where('assigned_to', $userId)
                ->count(),
            'completed_this_month' => Project::active()
                ->whereMonth('actual_completion_date', now()->month)
                ->count(),
            'projects_by_stage' => Project::active()
                ->select('current_stage_id', DB::raw('count(*) as count'))
                ->with('currentStage')
                ->groupBy('current_stage_id')
                ->get(),
            'projects_by_status' => Project::active()
                ->select('status_id', DB::raw('count(*) as count'))
                ->with('status')
                ->groupBy('status_id')
                ->get(),
        ]);
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