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
    ProjectMapController,
    LocationController,
    NotificationEventSettingController,
    NotificationTemplateController,
    NotificationDeliveryController,
    NotificationPreferenceController,
    WorkflowSettingsController,
    InvitationController,
    ProjectFundReleaseController,
    SystemSettingController,
    DivestmentCaseController
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
Route::get('/settings/public', [SystemSettingController::class, 'publicSettings']);
Route::post('/staff-invitations/{token}/accept', [UserController::class, 'acceptStaffInvitation']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendEmailVerification'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Profile (own)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);
    Route::get('/profile/previous-projects', [ProfileController::class, 'listPreviousProjects']);
    Route::post('/profile/previous-projects', [ProfileController::class, 'storePreviousProject']);
    Route::put('/profile/previous-projects/{id}', [ProfileController::class, 'updatePreviousProject']);
    Route::delete('/profile/previous-projects/{id}', [ProfileController::class, 'deletePreviousProject']);

    // Admin viewing another user's profile
    Route::get('/users/{user}/profile', [ProfileController::class, 'showUser']);
    Route::get('/users/{user}/projects', [ProfileController::class, 'userProjects']);
    Route::get('/users/{user}/tasks', [ProfileController::class, 'userTasks']);
    Route::get('/users/{user}/activity', [ProfileController::class, 'userActivity']);
    
    // Projects
    Route::get('project-workflow-catalog', [ProjectController::class, 'workflowCatalog']);
    Route::get('projects/map', [ProjectMapController::class, 'index']);
    Route::get('projects/proponent-history', [ProjectController::class, 'proponentHistory']);
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/submit-proposal', [ProjectController::class, 'submitProposal']);
    Route::post('projects/{project}/images', [ProjectController::class, 'uploadImages']);
    Route::patch('projects/{project}/images/{image}/thumbnail', [ProjectController::class, 'setThumbnailImage']);
    Route::delete('projects/{project}/images/{image}', [ProjectController::class, 'deleteImage']);
    Route::post('projects/{project}/members', [ProjectController::class, 'addMember']);
    Route::delete('projects/{project}/members/{member}', [ProjectController::class, 'removeMember']);
    Route::patch('projects/{project}/requirements/{requirement}', [ProjectController::class, 'updateRequirement']);
    Route::get('projects/{project}/fund-releases/anchors', [ProjectFundReleaseController::class, 'anchors']);
    Route::get('projects/{project}/fund-releases', [ProjectFundReleaseController::class, 'index']);
    Route::post('projects/{project}/fund-releases', [ProjectFundReleaseController::class, 'store']);
    Route::patch('projects/{project}/fund-releases/{fundRelease}', [ProjectFundReleaseController::class, 'update']);
    Route::delete('projects/{project}/fund-releases/{fundRelease}', [ProjectFundReleaseController::class, 'destroy']);
    Route::get('projects/{project}/timeline', [ProjectController::class, 'timeline']);
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive']);
    Route::post('projects/{project}/monitoring/activate', [ProjectController::class, 'activateMonitoring']);
    Route::get('projects/{project}/implementation/readiness', [ProjectController::class, 'implementationReadiness']);
    Route::post('projects/{project}/implementation/start', [ProjectController::class, 'startImplementation']);
    Route::put('projects/{project}/monitoring', [ProjectController::class, 'updateMonitoring']);
    Route::post('projects/{project}/monitoring/submit', [ProjectController::class, 'submitMonitoring']);
    Route::post('projects/{project}/monitoring/review', [ProjectController::class, 'reviewMonitoring']);
    Route::post('projects/{project}/monitoring/close', [ProjectController::class, 'closeMonitoring']);
    Route::get('post-monitoring', [ProjectController::class, 'monitoringIndex']);

    Route::get('divestment-cases', [DivestmentCaseController::class, 'index']);
    Route::post('divestment-cases', [DivestmentCaseController::class, 'store']);
    Route::get('divestment-cases/{divestmentCase}', [DivestmentCaseController::class, 'show']);
    Route::patch('divestment-cases/{divestmentCase}', [DivestmentCaseController::class, 'update']);
    Route::post('divestment-cases/{divestmentCase}/transition', [DivestmentCaseController::class, 'transition']);
    Route::post('divestment-cases/{divestmentCase}/close', [DivestmentCaseController::class, 'close']);
    // Stable public aliases; legacy divestment-cases routes remain supported.
    Route::get('divestments', [DivestmentCaseController::class, 'index']);
    Route::post('projects/{project}/divestments', [DivestmentCaseController::class, 'store']);
    Route::get('divestments/{divestmentCase}', [DivestmentCaseController::class, 'show']);
    Route::patch('divestments/{divestmentCase}', [DivestmentCaseController::class, 'update']);
    Route::post('divestments/{divestmentCase}/transitions', [DivestmentCaseController::class, 'transition']);
    Route::post('divestments/{divestmentCase}/close', [DivestmentCaseController::class, 'close']);
    
    // Project Invitations
    Route::post('projects/{project}/invitations', [InvitationController::class, 'invite']);
    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept']);
    Route::post('invitations/{invitation}/decline', [InvitationController::class, 'decline']);
    
    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress']);
    Route::patch('tasks/{task}/completion', [TaskController::class, 'updateCompletion']);
    
    // Documents
    Route::post('projects/{project}/documents/submit-drafts', [DocumentController::class, 'submitDrafts']);
    Route::apiResource('documents', DocumentController::class)->except(['update']);
    Route::post('documents/{document}/submit', [DocumentController::class, 'submit']);
    Route::post('documents/{document}/request-update', [DocumentController::class, 'requestUpdate']);
    Route::get('documents/{document}/view', [DocumentController::class, 'view']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download']);
    
    // Users
    Route::post('users/invite-staff', [UserController::class, 'inviteStaff']);
    Route::get('users/{user}/registration-documents/{document}/view', [UserController::class, 'viewRegistrationDocument']);
    Route::get('users/{user}/registration-documents/{document}/download', [UserController::class, 'downloadRegistrationDocument']);
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
    Route::get('notification-event-settings', [NotificationEventSettingController::class, 'index']);
    Route::put('notification-event-settings/{notification_event_setting}', [NotificationEventSettingController::class, 'update']);
    Route::get('notification-templates', [NotificationTemplateController::class, 'index']);
    Route::get('notification-templates/{notification_template}', [NotificationTemplateController::class, 'show']);
    Route::put('notification-templates/{notification_template}/draft', [NotificationTemplateController::class, 'saveDraft']);
    Route::post('notification-templates/{notification_template}/preview', [NotificationTemplateController::class, 'preview']);
    Route::post('notification-templates/{notification_template}/publish', [NotificationTemplateController::class, 'publish']);
    Route::post('notification-templates/{notification_template}/versions/{version}/restore', [NotificationTemplateController::class, 'restore']);
    Route::post('notification-templates/{notification_template}/test', [NotificationTemplateController::class, 'sendTest']);
    Route::get('notification-management/overview', [NotificationDeliveryController::class, 'overview']);
    Route::get('notification-deliveries', [NotificationDeliveryController::class, 'index']);
    Route::get('notification-deliveries/{notificationDelivery}', [NotificationDeliveryController::class, 'show']);
    Route::post('notification-deliveries/{notificationDelivery}/retry', [NotificationDeliveryController::class, 'retry']);
    Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);
    
    // Reports
    Route::get('reports/projects', [ReportController::class, 'projects']);
    Route::get('reports/projects/export', [ReportController::class, 'exportProjects']);
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

    Route::prefix('locations')->group(function () {
        Route::get('regions', [LocationController::class, 'regions']);
        Route::get('regions/{regionCode}/provinces', [LocationController::class, 'provinces']);
        Route::get('cities-municipalities', [LocationController::class, 'citiesMunicipalities']);
        Route::get('barangays', [LocationController::class, 'barangays']);
        Route::post('geocode', [LocationController::class, 'geocode']);
    });
    
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

        // Dynamic SOI Workflows & Steps
        Route::get('workflows', [WorkflowSettingsController::class, 'indexWorkflows']);
        Route::put('workflows/{workflow}', [WorkflowSettingsController::class, 'updateWorkflow']);
        Route::put('workflows/{workflow}/steps', [WorkflowSettingsController::class, 'updateSteps']);
        
        // Checklist templates (default requirements)
        Route::get('default-requirements', [WorkflowSettingsController::class, 'indexDefaultRequirements']);
        Route::post('default-requirements', [WorkflowSettingsController::class, 'storeDefaultRequirement']);
        Route::put('default-requirements/{id}', [WorkflowSettingsController::class, 'updateDefaultRequirement']);
        Route::delete('default-requirements/{id}', [WorkflowSettingsController::class, 'destroyDefaultRequirement']);
        Route::post('default-requirements/{id}/upload-template', [WorkflowSettingsController::class, 'uploadTemplate']);

        // Work plan templates (default tasks)
        Route::get('default-tasks', [WorkflowSettingsController::class, 'indexDefaultTasks']);
        Route::post('default-tasks', [WorkflowSettingsController::class, 'storeDefaultTask']);
        Route::put('default-tasks/{id}', [WorkflowSettingsController::class, 'updateDefaultTask']);
        Route::delete('default-tasks/{id}', [WorkflowSettingsController::class, 'destroyDefaultTask']);
    });

    // Lookup/Reference Data
    Route::get('lookup/templates/download', [WorkflowSettingsController::class, 'downloadTemplate']);

    // System Settings
    Route::get('settings', [SystemSettingController::class, 'index']);
    Route::put('settings', [SystemSettingController::class, 'update']);
    Route::post('settings/upload-logo', [SystemSettingController::class, 'uploadLogo']);
    Route::get('settings/{key}', [SystemSettingController::class, 'show']);
    Route::put('settings/{key}', [SystemSettingController::class, 'updateSingle']);

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
