<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectMapController extends Controller
{
    /**
     * Return all projects with coordinates for map plotting.
     * No pagination — map needs all pins at once.
     * Respects same visibility rules as ProjectController@index.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Project::with([
            'projectType',
            'currentStage',
            'status',
            'projectOfficer',
            'address',
        ])
        ->whereNotNull('location_lat')
        ->whereNotNull('location_lng')
        ->active(); // uses the scopeActive() from Project model: is_deleted=false, is_archived=false

        // Respect same visibility rules as ProjectController
        if (!$this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', function ($memberQuery) use ($user) {
                      $memberQuery->where('user_id', $user->id)
                                  ->whereNull('removed_at')
                                  ->where('can_view', true);
                  });
            });
        }

        // Optional filters
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('project_type_id')) {
            $query->where('project_type_id', $request->project_type_id);
        }

        if ($request->filled('stage_id')) {
            $query->where('current_stage_id', $request->stage_id);
        }

        // Bounding box filter — useful when map viewport changes
        // Usage: ?bounds=lat_min,lng_min,lat_max,lng_max
        if ($request->filled('bounds')) {
            $bounds = explode(',', $request->bounds);
            if (count($bounds) === 4) {
                [$latMin, $lngMin, $latMax, $lngMax] = $bounds;
                $query->whereBetween('location_lat', [(float) $latMin, (float) $latMax])
                      ->whereBetween('location_lng', [(float) $lngMin, (float) $lngMax]);
            }
        }

        $projects = $query->get();

        // Reuse existing ProjectResource — no need for a separate map resource
        return ProjectResource::collection($projects);
    }

    /**
     * GET /api/projects/stats/by-region
     * Aggregate count of active projects per region, derived from the
     * project_addresses child table. Used by the dashboard card.
     */
    public function statsByRegion(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Project::query()
            ->active()
            ->join('project_addresses', 'projects.id', '=', 'project_addresses.project_id')
            ->whereNotNull('project_addresses.region')
            ->where('project_addresses.region', '!=', '');

        // Same visibility rules as the index/map endpoints.
        if (!$this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) {
            $query->where(function ($q) use ($user) {
                $q->where('projects.created_by', $user->id)
                  ->orWhereExists(function ($sub) use ($user) {
                      $sub->select(DB::raw(1))
                          ->from('project_members')
                          ->whereColumn('project_members.project_id', 'projects.id')
                          ->where('project_members.user_id', $user->id)
                          ->whereNull('project_members.removed_at')
                          ->where('project_members.can_view', true);
                  });
            });
        }

        $rows = $query
            ->select('project_addresses.region', DB::raw('COUNT(projects.id) as count'))
            ->groupBy('project_addresses.region')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'region' => (string) $r->region,
                'count'  => (int) $r->count,
            ])->values(),
        ]);
    }

    // -------------------------------------------------------
    // Permission helper — mirrors ProjectController exactly
    // so behavior is consistent across both controllers.
    // -------------------------------------------------------

    private function hasAnyPermission($user, array $permissions): bool
    {
        if (!$user) return false;

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}