<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    ProjectController,
    TaskController,
    UserController,
    DocumentController,
    ApprovalController,
    NotificationController,
    ReportController,
    DashboardController,
    LookupController,
    SvfController,
    AccessSettingsController,
    AuditLogController,
    ProfileController,
    ProjectMapController
};


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Test routes (remove in production)
Route::get('/test', function() {
    return response()->json(['message' => 'GET test working']);
});

Route::post('/test', function() {
    return response()->json([
        'message' => 'POST test working',
        'data' => request()->all()
    ], 201);
});

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Profile (own)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // Admin viewing another user's profile
    Route::get('/users/{user}/profile', [ProfileController::class, 'showUser']);
    Route::get('/users/{user}/projects', [ProfileController::class, 'userProjects']);
    Route::get('/users/{user}/tasks', [ProfileController::class, 'userTasks']);
    Route::get('/users/{user}/activity', [ProfileController::class, 'userActivity']);
    
    // Projects
    Route::get('projects/map', [ProjectMapController::class, 'index']);
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/members', [ProjectController::class, 'addMember']);
    Route::delete('projects/{project}/members/{member}', [ProjectController::class, 'removeMember']);
    Route::get('projects/{project}/timeline', [ProjectController::class, 'timeline']);
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive']);
    
    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress']);
    
    // Documents
    Route::apiResource('documents', DocumentController::class)->except(['update']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download']);
    
    // Users
    Route::apiResource('users', UserController::class);
    
    // Approvals
    Route::get('approvals', [ApprovalController::class, 'index']);
    Route::get('approvals/pending', [ApprovalController::class, 'pending']);
    Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve']);
    Route::post('approvals/{approval}/reject', [ApprovalController::class, 'reject']);
    Route::post('approvals/{approval}/complete', [ApprovalController::class, 'complete']);
    Route::post('approvals/{approval}/bootstrap', [ApprovalController::class, 'bootstrap']);
    
    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread', [NotificationController::class, 'unread']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    
    // Reports
    Route::get('reports/projects', [ReportController::class, 'projects']);
    Route::get('reports/tasks', [ReportController::class, 'tasks']);
    Route::get('reports/financial', [ReportController::class, 'financial']);
    Route::post('reports/export', [ReportController::class, 'export']);
    Route::apiResource('saved-reports', ReportController::class)->only(['index', 'store', 'destroy']);
    
    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/recent-activities', [DashboardController::class, 'recentActivities']);
    Route::get('dashboard/upcoming-deadlines', [DashboardController::class, 'upcomingDeadlines']);
    
    // Lookup/Reference Data
    Route::get('lookup/roles', [LookupController::class, 'roles']);
    Route::get('lookup/permissions', [LookupController::class, 'permissions']);
    Route::get('lookup/project-types', [LookupController::class, 'projectTypes']);
    Route::get('lookup/industries', [LookupController::class, 'industries']);
    Route::get('lookup/sectors', [LookupController::class, 'sectors']);
    Route::get('lookup/investment-types', [LookupController::class, 'investmentTypes']);
    Route::get('lookup/funding-sources', [LookupController::class, 'fundingSources']);
    Route::get('lookup/project-stages', [LookupController::class, 'projectStages']);
    Route::get('lookup/project-statuses', [LookupController::class, 'projectStatuses']);
    Route::get('lookup/tags', [LookupController::class, 'tags']);
    
    // SVF (Startup Venture Fund)
    Route::apiResource('svf-applications', SvfController::class);
    Route::post('svf-applications/{application}/evaluate', [SvfController::class, 'evaluate']);
    Route::get('svf-applications/{application}/evaluations', [SvfController::class, 'evaluations']);

    // Access Settings (Roles & Permissions Management)
    Route::prefix('access-settings')->group(function () {
        // Permissions
        Route::get('permissions', [AccessSettingsController::class, 'indexPermissions']);
        Route::post('permissions', [AccessSettingsController::class, 'storePermission']);
        Route::put('permissions/{permission}', [AccessSettingsController::class, 'updatePermission']);
        Route::delete('permissions/{permission}', [AccessSettingsController::class, 'destroyPermission']);
        
        // Roles
        Route::get('roles', [AccessSettingsController::class, 'indexRoles']);
        Route::post('roles', [AccessSettingsController::class, 'storeRole']);
        Route::put('roles/{role}', [AccessSettingsController::class, 'updateRole']);
        Route::delete('roles/{role}', [AccessSettingsController::class, 'destroyRole']);
        
        // Role Permissions
        Route::post('roles/{role}/permissions/assign', [AccessSettingsController::class, 'assignPermissions']);
        Route::post('roles/{role}/permissions/remove', [AccessSettingsController::class, 'removePermissions']);
        Route::post('roles/{role}/permissions/sync', [AccessSettingsController::class, 'syncPermissions']);
    });

    // Activity Logs (Admin)
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('statistics', [AuditLogController::class, 'statistics']);
        Route::get('settings', [AuditLogController::class, 'getSettings']);
        Route::put('settings', [AuditLogController::class, 'updateSettings']);
        Route::post('settings/cleanup', [AuditLogController::class, 'cleanup']);
        Route::get('export/data', [AuditLogController::class, 'exportData']);
    });
});
