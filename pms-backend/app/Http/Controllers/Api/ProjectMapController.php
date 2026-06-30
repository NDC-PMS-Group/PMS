<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;

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
            'images.uploadedBy',
            'tasks' => fn ($taskQuery) => $taskQuery->active()->with('assignedTo'),
        ])
        ->whereNotNull('location_lat')
        ->whereNotNull('location_lng')
        ->active()
        ->visibleDraftsTo($user); // Draft proposals remain private to their creator until submission.

        // Respect same visibility rules as ProjectController
        if (!$this->hasGlobalProjectPermission($user, ['projects.view', 'project.view', 'view_project'])) {
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

        if ($request->filled('region_code')) {
            $query->where('location_region_code', $request->region_code);
        }

        if ($request->filled('province_code')) {
            $query->where('location_province_code', $request->province_code);
        }

        if ($request->filled('city_code')) {
            $query->where('location_city_code', $request->city_code);
        }

        if ($request->filled('barangay_code')) {
            $query->where('location_barangay_code', $request->barangay_code);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('project_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('proponent_name', 'like', "%{$search}%")
                    ->orWhere('location_address', 'like', "%{$search}%");
            });
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

    // -------------------------------------------------------
    // Permission helper — mirrors ProjectController exactly
    // so behavior is consistent across both controllers.
    // -------------------------------------------------------

    private function hasAnyPermission($user, array $permissions): bool
    {
        if (!$user) return false;

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    private function isSuperAdmin($user): bool
    {
        return $user && ((int) $user->default_role_id === 1 || $user->hasRole('superadmin'));
    }

    private function isExternalProponent($user): bool
    {
        return $user && ((int) $user->default_role_id === 7 || $user->hasRole('Proponent'));
    }

    private function hasGlobalProjectPermission($user, array $permissions): bool
    {
        if (!$user) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isExternalProponent($user)) {
            return false;
        }

        return $this->hasAnyPermission($user, $permissions);
    }
}
