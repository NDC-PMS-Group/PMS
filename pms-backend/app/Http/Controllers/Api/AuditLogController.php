<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ActivityLogSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\AuditLogResource;

class AuditLogController extends Controller
{
    /**
     * Display a listing of activity logs with filtering and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,first_name,middle_name,last_name,suffix,email')
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Action type filter
        if ($request->filled('action_type') && $request->action_type !== 'all') {
            $query->byActionType($request->action_type);
        }

        // Date range filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        } elseif ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->paginate($perPage);

        return AuditLogResource::collection($logs)->response();
    }

    /**
     * Get activity log statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        // Apply date filters if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $stats = [
            'total_activities' => (clone $query)->count(),
            'total_logins' => (clone $query)->where('action', 'login')->count(),
            'total_creates' => (clone $query)->where('action', 'created')->count(),
            'total_updates' => (clone $query)->where('action', 'updated')->count(),
            'total_deletes' => (clone $query)->where('action', 'deleted')->count(),
            'unique_users' => (clone $query)->distinct('user_id')->whereNotNull('user_id')->count('user_id'),
        ];

        return response()->json($stats);
    }

    /**
     * Get activity log settings.
     *
     * @return JsonResponse
     */
    public function getSettings(): JsonResponse
    {
        $settings = ActivityLogSettings::getSettings();

        return response()->json([
            'retention_months' => $settings->retention_months,
            'max_id' => $settings->max_id,
            'auto_cleanup_enabled' => $settings->auto_cleanup_enabled,
            'last_cleanup_at' => $settings->last_cleanup_at,
        ]);
    }

    /**
     * Update activity log settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'retention_months' => 'required|integer|min:1|max:120',
            'max_id' => 'required|integer|min:1000',
            'auto_cleanup_enabled' => 'required|boolean',
        ]);

        $settings = ActivityLogSettings::updateSettings($validated);

        return response()->json([
            'message' => 'Settings updated successfully',
            'retention_months' => $settings->retention_months,
            'max_id' => $settings->max_id,
            'auto_cleanup_enabled' => $settings->auto_cleanup_enabled,
            'last_cleanup_at' => $settings->last_cleanup_at,
        ]);
    }

    /**
     * Manually trigger cleanup of old activity logs.
     *
     * @return JsonResponse
     */
    public function cleanup(): JsonResponse
    {
        $settings = ActivityLogSettings::getSettings();
        $deletedCount = $settings->performCleanup();

        return response()->json([
            'message' => "Successfully deleted {$deletedCount} old logs",
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Export activity logs data for CSV download.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function exportData(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,first_name,middle_name,last_name,suffix,email')
            ->orderBy('created_at', 'desc');

        // Apply same filters as index method
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('action_type') && $request->action_type !== 'all') {
            $query->byActionType($request->action_type);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        } elseif ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        // Limit export to prevent memory issues
        $logs = $query->limit(10000)->get();

        $exportData = $logs->map(function ($log) {
            $employeeName = 'N/A';
            if ($log->user) {
                $employeeName = trim(implode(' ', array_filter([
                    $log->user->first_name,
                    $log->user->middle_name,
                    $log->user->last_name,
                    $log->user->suffix,
                ])));
            }

            $browser = $log->browser;
            if ($log->browser_version) {
                $browser .= ' ' . $log->browser_version;
            }

            $platform = $log->platform;
            if ($log->platform_version) {
                $platform .= ' ' . $log->platform_version;
            }

            return [
                'id' => $log->id,
                'date' => $log->created_at->format('Y-m-d H:i:s'),
                'employee_name' => $employeeName,
                'email' => $log->email,
                'action_type' => $log->action_type,
                'description' => $log->description ?? 'N/A',
                'ip_address' => $log->ip_address ?? 'N/A',
                'device_type' => $log->device_type ?? 'N/A',
                'browser' => $browser ?? 'N/A',
                'platform' => $platform ?? 'N/A',
            ];
        });

        return response()->json([
            'data' => $exportData,
        ]);
    }
}