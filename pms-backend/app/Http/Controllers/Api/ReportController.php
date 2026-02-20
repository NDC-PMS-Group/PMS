<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\SavedReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function projects(Request $request)
    {
        $query = Project::with(['projectType', 'industry', 'currentStage', 'status']);

        // Apply filters from request
        if ($request->has('stage_id')) {
            $query->where('current_stage_id', $request->stage_id);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        return response()->json($query->get());
    }

    public function financial(Request $request)
    {
        $data = Project::select(
            DB::raw('SUM(estimated_cost) as total_estimated'),
            DB::raw('SUM(actual_cost) as total_actual'),
            DB::raw('COUNT(*) as project_count')
        )->active()->first();

        return response()->json($data);
    }

    public function export(Request $request)
    {
        // TODO: Implement export logic (Excel, PDF, CSV)
        return response()->json(['message' => 'Export functionality coming soon']);
    }
}