<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveProjectRequest;
use App\Models\ProjectApproval;
use App\Models\ApprovalStepRecord;
use App\Models\ApprovalWorkflow;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_INITIAL_COMPLETENESS_CHECK = 'initial_completeness_check';
    private const STATUS_FOR_EVALUATION = 'for_evaluation';
    private const STATUS_FOR_IC_EVALUATION = 'for_ic_evaluation';
    private const STATUS_FOR_AGM_REVIEW = 'for_agm_review';
    private const STATUS_FOR_WORKGROUP_REVIEW = 'for_workgroup_review';
    private const STATUS_FOR_MANCOM_REVIEW = 'for_mancom_review';
    private const STATUS_FOR_BOARD_APPROVAL = 'for_board_approval';
    private const STATUS_FOR_FUND_RELEASE = 'for_fund_release';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_APPROVED_WITH_CONDITIONS = 'approved_with_conditions';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_RETURNED = 'returned';
    private const ACTIONABLE_APPROVAL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_INITIAL_COMPLETENESS_CHECK,
        self::STATUS_FOR_EVALUATION,
        self::STATUS_FOR_IC_EVALUATION,
        self::STATUS_FOR_AGM_REVIEW,
        self::STATUS_FOR_WORKGROUP_REVIEW,
        self::STATUS_FOR_MANCOM_REVIEW,
        self::STATUS_FOR_BOARD_APPROVAL,
        self::STATUS_FOR_FUND_RELEASE,
        'for_approval',
    ];

    public function index(Request $request)
    {
        $query = ProjectApproval::with(['project', 'workflow', 'currentStep']);

        if ($request->has('status')) {
            $query->where('overall_status', $request->status);
        }

        $approvals = $query->paginate(15);

        return response()->json($approvals);
    }

    public function pending(Request $request)
    {
        $user = Auth::user();
        $userRoleId = $user?->default_role_id;
        $isSuperAdmin = (int)$userRoleId === 1 || $user?->hasRole('superadmin');

        $approvals = ProjectApproval::with(['project', 'workflow', 'currentStep'])
            ->when(!$isSuperAdmin, function ($query) use ($userRoleId, $user) {
                $query->where(function ($roleQuery) use ($userRoleId, $user) {
                    $roleQuery
                        ->whereHas('currentStep', function ($stepQuery) use ($userRoleId) {
                            $stepQuery->where('role_id', $userRoleId);
                        })
                        ->orWhere(function ($proponentQuery) use ($user) {
                            $proponentQuery
                                ->whereHas('currentStep', function ($stepQuery) {
                                    $stepQuery->where('step_order', 1);
                                })
                                ->whereHas('project', function ($projectQuery) use ($user) {
                                    $projectQuery->where('created_by', $user?->id);
                                });
                        });
                });
            })
            ->whereIn('overall_status', self::ACTIONABLE_APPROVAL_STATUSES)
            ->paginate(15);

        return response()->json($approvals);
    }

    public function approved(Request $request)
    {
        $query = ProjectApproval::with(['project', 'workflow', 'currentStep'])
            ->whereIn('overall_status', [
                self::STATUS_APPROVED,
                self::STATUS_APPROVED_WITH_CONDITIONS,
                self::STATUS_COMPLETED,
            ]);

        $approvals = $query->paginate(15);

        return response()->json($approvals);
    }

    public function rejected(Request $request)
    {
        // Kept for backward compatibility; workflow now avoids rejected as terminal status.
        $query = ProjectApproval::with(['project', 'workflow', 'currentStep'])
            ->where('overall_status', 'rejected');

        $approvals = $query->paginate(15);

        return response()->json($approvals);
    }

    public function approve(ApproveProjectRequest $request, ProjectApproval $approval)
    {
        $approval->loadMissing(['workflow.steps', 'project', 'currentStep']);
        $currentStep = $approval->currentStep;

        if (!$currentStep) {
            return response()->json(['message' => 'Approval has no current step.'], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $isSuperAdmin = (int)$user->default_role_id === 1 || $user->hasRole('superadmin');
        if (!$isSuperAdmin) {
            $isProponentStep = (int)$currentStep->step_order === 1;
            if ($isProponentStep && (int)$approval->project->created_by !== (int)$user->id) {
                return response()->json(['message' => 'Only the project proponent can process this step.'], 403);
            }

            if (!$isProponentStep && (int)$currentStep->role_id !== (int)$user->default_role_id) {
                return response()->json(['message' => 'Current approval step is assigned to another role.'], 403);
            }
        }

        ApprovalStepRecord::updateOrCreate(
            [
                'project_approval_id' => $approval->id,
                'step_id' => $currentStep->id,
            ],
            [
                'approver_id' => $user->id,
                'status' => $request->status,
                'comments' => $request->comments,
                'conditions' => $request->conditions,
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]
        );

        $nextStep = $approval->workflow->steps()
            ->where('step_order', '>', $currentStep->step_order)
            ->orderBy('step_order')
            ->first();

        $project = $approval->project;
        $oldStatusId = $project->status_id;
        $oldStageId = $project->current_stage_id;

        if ($nextStep) {
            $newStatus = $this->deriveStatusForStep($nextStep);
            $approval->update([
                'current_step_id' => $nextStep->id,
                'overall_status' => $newStatus,
                'completed_at' => null,
            ]);

            $project->status_id = self::statusIdForWorkflowStatus($newStatus) ?? $project->status_id;

            $project->current_stage_id = self::stageIdByName($this->deriveStageForStep($nextStep))
                ?? $project->current_stage_id;
        } else {
            $finalStatus = $request->status === self::STATUS_APPROVED_WITH_CONDITIONS
                ? self::STATUS_APPROVED_WITH_CONDITIONS
                : self::STATUS_APPROVED;

            $approval->update([
                'overall_status' => $finalStatus,
                'completed_at' => now(),
                'current_step_id' => null,
            ]);

            $project->status_id = self::statusIdForWorkflowStatus($finalStatus) ?? $project->status_id;
            $project->current_stage_id = self::stageIdByName('Implementation & Monitoring') ?? $project->current_stage_id;
        }

        if ($project->isDirty(['status_id', 'current_stage_id'])) {
            $project->save();

            if ($project->wasChanged('status_id')) {
                \App\Models\ProjectStatusHistory::create([
                    'project_id' => $project->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id' => $project->status_id,
                    'changed_by' => $user->id,
                    'change_reason' => 'Approval workflow progression',
                ]);
            }

            if ($project->wasChanged('current_stage_id')) {
                \App\Models\ProjectStageHistory::create([
                    'project_id' => $project->id,
                    'from_stage_id' => $oldStageId,
                    'to_stage_id' => $project->current_stage_id,
                    'changed_by' => $user->id,
                'change_reason' => 'Approval workflow progression',
                ]);
            }
        }

        $approval->refresh()->load(['project', 'workflow', 'currentStep.role']);
        if ($nextStep) {
            self::notifyCurrentStepApprovers($approval, $user);
        } else {
            $this->notifyProjectStakeholdersOfApprovalResult($approval, $user, $request->status, $request->conditions);
        }

        return response()->json([
            'message' => 'Approval recorded successfully',
            'approval' => $approval->fresh()->load(['project', 'workflow', 'currentStep']),
        ]);
    }

    public function reject(Request $request, ProjectApproval $approval)
    {
        // Kept for backward compatibility endpoint; SOI flow does not include rejected terminal state.
        $request->validate([
            'comments' => 'required|string',
        ]);

        $approval->loadMissing(['workflow.steps']);

        $firstStep = $approval->workflow->steps()->orderBy('step_order')->first();
        if (!$firstStep) {
            return response()->json(['message' => 'Approval workflow has no steps.'], 422);
        }

        $userId = Auth::id();

        ApprovalStepRecord::updateOrCreate(
            [
                'project_approval_id' => $approval->id,
                'step_id' => $approval->current_step_id,
            ],
            [
                'approver_id' => $userId,
                'status' => 'returned',
                'comments' => $request->comments,
                'reviewed_at' => now(),
            ]
        );

        $approval->update([
            'overall_status' => self::STATUS_RETURNED,
            'current_step_id' => $firstStep->id,
            'completed_at' => null,
        ]);

        // Sync Project Status & Stage
        $project = $approval->project;
        if ($project) {
            $oldStatusId = $project->status_id;
            $oldStageId = $project->current_stage_id;
            $returnedStatusId = self::statusIdByName('Returned for Revision') ?? $project->status_id;
            $proposalStageId = self::stageIdByName('Intake') ?? $project->current_stage_id;
            $project->update([
                'status_id' => $returnedStatusId,
                'current_stage_id' => $proposalStageId,
            ]);

            \App\Models\ProjectStatusHistory::create([
                'project_id' => $project->id,
                'from_status_id' => $oldStatusId,
                'to_status_id' => $returnedStatusId,
                'changed_by' => $userId,
                'change_reason' => 'Project returned to proponent: ' . $request->comments,
            ]);

            \App\Models\ProjectStageHistory::create([
                'project_id' => $project->id,
                'from_stage_id' => $oldStageId,
                'to_stage_id' => $proposalStageId,
                'changed_by' => $userId,
                'change_reason' => 'Project returned to proponent: ' . $request->comments,
            ]);
        }

        $approval->refresh()->load(['project', 'workflow', 'currentStep']);
        $this->notifyProjectReturned($approval, Auth::user(), $request->comments);

        return response()->json([
            'message' => 'Project returned to proponent',
            'approval' => $approval,
        ]);
    }

    public function returnForRevision(Request $request, ProjectApproval $approval)
    {
        $request->validate([
            'comments' => 'required|string',
        ]);

        $approval->loadMissing(['workflow.steps']);

        $firstStep = $approval->workflow->steps()->orderBy('step_order')->first();
        if (!$firstStep) {
            return response()->json(['message' => 'Approval workflow has no steps.'], 422);
        }

        $userId = Auth::id();

        ApprovalStepRecord::updateOrCreate(
            [
                'project_approval_id' => $approval->id,
                'step_id' => $approval->current_step_id,
            ],
            [
                'approver_id' => $userId,
                'status' => 'returned',
                'comments' => $request->comments,
                'reviewed_at' => now(),
            ]
        );

        $approval->update([
            'overall_status' => self::STATUS_RETURNED,
            'current_step_id' => $firstStep->id,
            'completed_at' => null,
        ]);

        // Sync Project Status & Stage
        $project = $approval->project;
        if ($project) {
            $oldStatusId = $project->status_id;
            $oldStageId = $project->current_stage_id;
            $returnedStatusId = self::statusIdByName('Returned for Revision') ?? $project->status_id;
            $proposalStageId = self::stageIdByName('Intake') ?? $project->current_stage_id;
            $project->update([
                'status_id' => $returnedStatusId,
                'current_stage_id' => $proposalStageId,
            ]);

            \App\Models\ProjectStatusHistory::create([
                'project_id' => $project->id,
                'from_status_id' => $oldStatusId,
                'to_status_id' => $returnedStatusId,
                'changed_by' => $userId,
                'change_reason' => 'Project returned for revision: ' . $request->comments,
            ]);

            \App\Models\ProjectStageHistory::create([
                'project_id' => $project->id,
                'from_stage_id' => $oldStageId,
                'to_stage_id' => $proposalStageId,
                'changed_by' => $userId,
                'change_reason' => 'Project returned for revision: ' . $request->comments,
            ]);
        }

        $approval->refresh()->load(['project', 'workflow', 'currentStep']);
        $this->notifyProjectReturned($approval, Auth::user(), $request->comments);

        return response()->json([
            'message' => 'Project returned for revision',
            'approval' => $approval,
        ]);
    }

    public function approvalHistory(ProjectApproval $approval)
    {
        $history = ApprovalStepRecord::with(['step', 'approver'])
            ->where('project_approval_id', $approval->id)
            ->orderBy('reviewed_at', 'desc')
            ->get();

        return response()->json($history);
    }

    public function complete(ProjectApproval $approval)
    {
        if (!in_array($approval->overall_status, [self::STATUS_APPROVED, self::STATUS_APPROVED_WITH_CONDITIONS], true)) {
            return response()->json([
                'message' => 'Only approved workflows can be marked completed.'
            ], 422);
        }

        $approval->update([
            'overall_status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Approval workflow marked as completed.',
            'approval' => $approval->fresh(),
        ]);
    }

    public function bootstrap(ProjectApproval $approval)
    {
        // Utility endpoint: set status based on current step for legacy records.
        $approval->loadMissing('currentStep');
        $stepOrder = (int) ($approval->currentStep?->step_order ?? 0);

        $approval->update([
            'overall_status' => $this->deriveStatusForStep($approval->currentStep),
        ]);

        return response()->json([
            'message' => 'Approval workflow status bootstrapped.',
            'approval' => $approval->fresh(),
        ]);
    }

    private function deriveInProgressStatus(int $stepOrder): string
    {
        if ($stepOrder <= 1) {
            return self::STATUS_PENDING;
        }

        return match ($stepOrder) {
            2 => self::STATUS_INITIAL_COMPLETENESS_CHECK,
            3 => self::STATUS_FOR_EVALUATION,
            4 => self::STATUS_FOR_AGM_REVIEW,
            5 => self::STATUS_FOR_MANCOM_REVIEW,
            6 => self::STATUS_FOR_BOARD_APPROVAL,
            7 => self::STATUS_FOR_FUND_RELEASE,
            8 => self::STATUS_FOR_FUND_RELEASE,
            default => self::STATUS_FOR_WORKGROUP_REVIEW,
        };
    }

    private function deriveStatusForStep($step): string
    {
        if (!$step) {
            return self::STATUS_PENDING;
        }

        $name = strtolower((string) $step->step_name);
        if (str_contains($name, 'investment committee')) {
            return self::STATUS_FOR_IC_EVALUATION;
        }
        if (str_contains($name, 'project officer evaluation')) {
            return self::STATUS_FOR_EVALUATION;
        }
        if (str_contains($name, 'agm') || str_contains($name, 'workgroup')) {
            return self::STATUS_FOR_AGM_REVIEW;
        }
        if (str_contains($name, 'mancom')) {
            return self::STATUS_FOR_MANCOM_REVIEW;
        }
        if (str_contains($name, 'board')) {
            return self::STATUS_FOR_BOARD_APPROVAL;
        }
        if (str_contains($name, 'agreement') || str_contains($name, 'fund release')) {
            return self::STATUS_FOR_FUND_RELEASE;
        }

        return $this->deriveInProgressStatus((int) $step->step_order);
    }

    private function deriveStageForStep($step): string
    {
        $name = strtolower((string) $step->step_name);

        if (str_contains($name, 'completeness')) {
            return 'Requirements';
        }
        if (str_contains($name, 'validation') || str_contains($name, 'due diligence')) {
            return 'Due Diligence';
        }
        if (str_contains($name, 'investment committee') || str_contains($name, 'agm') || str_contains($name, 'workgroup') || str_contains($name, 'mancom')) {
            return 'Management Review';
        }
        if (str_contains($name, 'board')) {
            return 'Board Approval';
        }
        if (str_contains($name, 'agreement') || str_contains($name, 'fund release')) {
            return 'Agreement & Fund Release';
        }

        return 'Intake';
    }

    private static function statusIdForWorkflowStatus(string $status): ?int
    {
        $map = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_INITIAL_COMPLETENESS_CHECK => 'Initial Completeness Check',
            self::STATUS_FOR_EVALUATION => 'Evaluation Ongoing',
            self::STATUS_FOR_IC_EVALUATION => 'For IC Evaluation',
            self::STATUS_FOR_AGM_REVIEW => 'For AGM Review',
            self::STATUS_FOR_WORKGROUP_REVIEW => 'For Workgroup Review',
            self::STATUS_FOR_MANCOM_REVIEW => 'For ManCom Review',
            self::STATUS_FOR_BOARD_APPROVAL => 'For Board Approval',
            self::STATUS_FOR_FUND_RELEASE => 'For Fund Release',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_APPROVED_WITH_CONDITIONS => 'Approved with Conditions',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_RETURNED => 'Returned for Revision',
        ];

        return self::statusIdByName($map[$status] ?? $status);
    }

    private static function statusIdByName(string $name): ?int
    {
        return ProjectStatus::query()->where('name', $name)->value('id');
    }

    private static function stageIdByName(string $name): ?int
    {
        return ProjectStage::query()->where('name', $name)->value('id');
    }

    public static function createInitialApprovalForProject(int $projectId, ?int $projectTypeId, int $proponentUserId): ?ProjectApproval
    {
        $project = Project::query()->find($projectId);
        $preferredWorkflowName = $project?->is_svf
            ? 'NDC SVF Investment Approval'
            : 'NDC Standard Investment Approval';

        $workflow = ApprovalWorkflow::query()
            ->where('is_active', true)
            ->where('name', $preferredWorkflowName)
            ->first();

        if (!$workflow) {
            $workflow = ApprovalWorkflow::query()
            ->where('is_active', true)
            ->where(function ($query) use ($projectTypeId) {
                $query->where('project_type_id', $projectTypeId)
                      ->orWhereNull('project_type_id');
            })
            ->orderByRaw('project_type_id IS NULL')
            ->first();
        }

        if (!$workflow) {
            return null;
        }

        $steps = $workflow->steps()->orderBy('step_order')->get();
        if ($steps->isEmpty()) {
            return null;
        }

        $firstStep = $steps->first();
        $nextStep = $steps->skip(1)->first();
        $initialStatus = $nextStep && str_contains(strtolower((string) $nextStep->step_name), 'project officer evaluation')
            ? self::STATUS_FOR_EVALUATION
            : ($nextStep ? self::STATUS_INITIAL_COMPLETENESS_CHECK : self::STATUS_PENDING);

        $approval = ProjectApproval::updateOrCreate(
            ['project_id' => $projectId],
            [
                'workflow_id' => $workflow->id,
                'current_step_id' => $nextStep?->id ?? $firstStep->id,
                'overall_status' => $initialStatus,
                'started_at' => now(),
                'completed_at' => null,
            ]
        );

        // Auto-complete the Proponent step when project is created by proponent.
        ApprovalStepRecord::updateOrCreate(
            [
                'project_approval_id' => $approval->id,
                'step_id' => $firstStep->id,
            ],
            [
                'approver_id' => $proponentUserId,
                'status' => self::STATUS_APPROVED,
                'comments' => 'Project submitted by proponent.',
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]
        );

        if ($nextStep) {
            Project::query()
                ->whereKey($projectId)
                ->update([
                    'status_id' => self::statusIdForWorkflowStatus($initialStatus)
                        ?? self::statusIdByName('For Evaluation')
                        ?? $project?->status_id,
                    'current_stage_id' => self::stageIdByName('Requirements')
                        ?? self::stageIdByName('Evaluation')
                        ?? $project?->current_stage_id,
                ]);
        }

        self::notifyCurrentStepApprovers($approval->fresh(['project', 'workflow', 'currentStep.role']), User::find($proponentUserId));

        return $approval;
    }

    private static function notifyCurrentStepApprovers(ProjectApproval $approval, ?User $actor = null): void
    {
        $approval->loadMissing(['project.creator', 'project.members.user', 'currentStep.role']);

        if (!$approval->currentStep || !$approval->project) {
            return;
        }

        $recipients = self::currentStepRecipients($approval);
        if ($recipients->isEmpty()) {
            return;
        }

        $project = $approval->project;
        $stepName = $approval->currentStep->step_name;
        $title = "Approval required: {$project->project_code}";
        $message = "{$project->title} is waiting for {$stepName}.";

        try {
            app(NotificationService::class)->notifyUsers(
                $recipients,
                'approval_request',
                $title,
                $message,
                $project,
                'approval_request',
                [
                    'project_title' => $project->title,
                    'submitter_name' => $actor?->full_name ?? 'System',
                    'stage_name' => $stepName,
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Approval request notification failed.', [
                'project_id' => $project->id,
                'approval_id' => $approval->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private static function currentStepRecipients(ProjectApproval $approval)
    {
        $step = $approval->currentStep;
        $project = $approval->project;

        if ((int) $step->step_order === 1) {
            return collect([$project->creator])->filter();
        }

        $roleUsers = User::active()
            ->where('default_role_id', $step->role_id)
            ->get();

        $memberUsers = $project->members
            ->where('role_id', $step->role_id)
            ->whereNull('removed_at')
            ->where('can_approve', true)
            ->pluck('user');

        return collect($roleUsers)
            ->merge($memberUsers)
            ->filter()
            ->unique('id')
            ->values();
    }

    private function notifyProjectStakeholdersOfApprovalResult(
        ProjectApproval $approval,
        User $actor,
        string $approvalStatus,
        ?string $conditions
    ): void {
        $project = $approval->project;
        if (!$project) {
            return;
        }

        $isConditional = $approvalStatus === self::STATUS_APPROVED_WITH_CONDITIONS;
        $statusText = $isConditional ? 'Approved with Conditions' : 'Approved';
        $message = $isConditional && $conditions
            ? "{$project->title} was approved with conditions: {$conditions}"
            : "{$project->title} was approved and moved to implementation.";

        try {
            $notificationService = app(NotificationService::class);
            $notificationService->notifyUsers(
                $notificationService->projectStakeholders($project),
                'project_status_change',
                "{$statusText}: {$project->project_code}",
                $message,
                $project,
                'project_status_change',
                [
                    'project_title' => $project->title,
                    'old_status' => 'Approval Routing',
                    'new_status' => $statusText,
                    'changed_by' => $actor->full_name,
                    'reason' => $conditions ?: 'Approval workflow completed.',
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Approval result notification failed.', [
                'project_id' => $project->id,
                'approval_id' => $approval->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private function notifyProjectReturned(ProjectApproval $approval, ?User $actor, string $comments): void
    {
        $project = $approval->project;
        if (!$project) {
            return;
        }

        try {
            $notificationService = app(NotificationService::class);
            $notificationService->notifyUsers(
                $notificationService->projectStakeholders($project),
                'project_returned',
                "Revision required: {$project->project_code}",
                "{$project->title} was returned for revision. Reason: {$comments}",
                $project,
                'project_status_change',
                [
                    'project_title' => $project->title,
                    'old_status' => 'Approval Routing',
                    'new_status' => 'Returned for Revision',
                    'changed_by' => $actor?->full_name ?? 'System',
                    'reason' => $comments,
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('Project returned notification failed.', [
                'project_id' => $project->id,
                'approval_id' => $approval->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }
}
