<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveProjectRequest;
use App\Models\ProjectApproval;
use App\Models\ApprovalStep;
use App\Models\ApprovalStepRecord;
use App\Models\ApprovalWorkflow;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectRequirement;
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
    private const STATUS_FOR_NEDA_ICC_REVIEW = 'for_neda_icc_review';
    private const STATUS_FOR_JV_SELECTION = 'for_jv_selection';
    private const STATUS_FOR_FUND_RELEASE = 'for_fund_release';
    private const STATUS_MILESTONES_SETUP = 'milestones_setup';
    private const STATUS_FOR_MONITORING_UPDATE = 'for_monitoring_update';
    private const STATUS_FOR_DIVESTMENT_APPROVAL = 'for_divestment_approval';
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
        self::STATUS_FOR_NEDA_ICC_REVIEW,
        self::STATUS_FOR_JV_SELECTION,
        self::STATUS_FOR_FUND_RELEASE,
        self::STATUS_MILESTONES_SETUP,
        self::STATUS_FOR_MONITORING_UPDATE,
        self::STATUS_FOR_DIVESTMENT_APPROVAL,
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
                                    $stepQuery->where(function ($submitterStepQuery) {
                                        $submitterStepQuery
                                            ->whereHas('role', function ($roleQuery) {
                                                $roleQuery->whereRaw('LOWER(name) = ?', ['proponent']);
                                            })
                                            ->orWhereRaw('LOWER(step_name) LIKE ?', ['%proponent submission%']);
                                    });
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

        $isSuperAdmin = $user->hasRole('superadmin');
        if (!$isSuperAdmin) {
            $isProponentStep = self::shouldAutoCompleteSubmitterStep($currentStep);
            if ($isProponentStep && (int)$approval->project->created_by !== (int)$user->id) {
                return response()->json(['message' => 'Only the project proponent can process this step.'], 403);
            }

            if (!$isProponentStep && (int)$currentStep->role_id !== (int)$user->default_role_id) {
                return response()->json(['message' => 'Current approval step is assigned to another role.'], 403);
            }

            $missingEndorsementArtifacts = $this->missingEndorsementArtifacts($approval);
            if ($missingEndorsementArtifacts->isNotEmpty()) {
                return response()->json([
                    'message' => 'Upload, approve, or waive the required internal SOI artifacts before completing this endorsement step.',
                    'missing_requirements' => $missingEndorsementArtifacts->values(),
                ], 422);
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
                ...self::timingForStep($nextStep),
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
                ...self::timingForStep(null),
            ]);

            $projectStatusName = $finalStatus === self::STATUS_APPROVED_WITH_CONDITIONS
                ? 'Approved with Conditions'
                : $this->finalProjectStatusNameForWorkflow($approval->workflow?->name);
            $projectStageName = $this->finalProjectStageNameForWorkflow($approval->workflow?->name);

            $project->status_id = self::statusIdByName($projectStatusName)
                ?? self::statusIdForWorkflowStatus($finalStatus)
                ?? $project->status_id;
            $project->current_stage_id = self::stageIdByName($projectStageName) ?? $project->current_stage_id;
        }

        if ($project->isDirty(['status_id', 'current_stage_id'])) {
            $project->save();

            if ($project->wasChanged('status_id')) {
                \App\Models\ProjectStatusHistory::create([
                    'project_id' => $project->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id' => $project->status_id,
                    'changed_by' => $user->id,
                    'change_reason' => 'SOI workflow progression',
                ]);
            }

            if ($project->wasChanged('current_stage_id')) {
                \App\Models\ProjectStageHistory::create([
                    'project_id' => $project->id,
                    'from_stage_id' => $oldStageId,
                    'to_stage_id' => $project->current_stage_id,
                    'changed_by' => $user->id,
                    'change_reason' => 'SOI workflow progression',
                ]);
            }

            self::autoRequestProponentRequirements($project);
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

        $returnStep = $this->previousReturnStep($approval);
        if (!$returnStep) {
            return response()->json(['message' => 'SOI workflow has no steps.'], 422);
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
            'current_step_id' => $returnStep->id,
            'completed_at' => null,
            ...self::timingForStep($returnStep),
        ]);

        // Sync Project Status & Stage
        $project = $approval->project;
        if ($project) {
            $oldStatusId = $project->status_id;
            $oldStageId = $project->current_stage_id;
            $returnedStatusId = self::statusIdByName('Returned for Revision') ?? $project->status_id;
            $returnedStageId = self::stageIdByName($this->deriveStageForStep($returnStep)) ?? $project->current_stage_id;
            $project->update([
                'status_id' => $returnedStatusId,
                'current_stage_id' => $returnedStageId,
            ]);

            \App\Models\ProjectStatusHistory::create([
                'project_id' => $project->id,
                'from_status_id' => $oldStatusId,
                'to_status_id' => $returnedStatusId,
                'changed_by' => $userId,
                'change_reason' => 'Project returned to prior SOI step: ' . $request->comments,
            ]);

            \App\Models\ProjectStageHistory::create([
                'project_id' => $project->id,
                'from_stage_id' => $oldStageId,
                'to_stage_id' => $returnedStageId,
                'changed_by' => $userId,
                'change_reason' => 'Project returned to prior SOI step: ' . $request->comments,
            ]);
        }

        $approval->refresh()->load(['project', 'workflow', 'currentStep.role']);
        $this->notifyProjectReturned($approval, Auth::user(), $request->comments);

        return response()->json([
            'message' => 'Project returned for revision',
            'approval' => $approval,
        ]);
    }

    public function returnForRevision(Request $request, ProjectApproval $approval)
    {
        $request->validate([
            'comments' => 'required|string',
        ]);

        $approval->loadMissing(['workflow.steps']);

        $returnStep = $this->previousReturnStep($approval);
        if (!$returnStep) {
            return response()->json(['message' => 'SOI workflow has no steps.'], 422);
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
            'current_step_id' => $returnStep->id,
            'completed_at' => null,
            ...self::timingForStep($returnStep),
        ]);

        // Sync Project Status & Stage
        $project = $approval->project;
        if ($project) {
            $oldStatusId = $project->status_id;
            $oldStageId = $project->current_stage_id;
            $returnedStatusId = self::statusIdByName('Returned for Revision') ?? $project->status_id;
            $returnedStageId = self::stageIdByName($this->deriveStageForStep($returnStep)) ?? $project->current_stage_id;
            $project->update([
                'status_id' => $returnedStatusId,
                'current_stage_id' => $returnedStageId,
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
                'to_stage_id' => $returnedStageId,
                'changed_by' => $userId,
                'change_reason' => 'Project returned for revision: ' . $request->comments,
            ]);
        }

        $approval->refresh()->load(['project', 'workflow', 'currentStep.role']);
        $this->notifyProjectReturned($approval, Auth::user(), $request->comments);

        return response()->json([
            'message' => 'Project returned for revision',
            'approval' => $approval,
        ]);
    }

    private function previousReturnStep(ProjectApproval $approval)
    {
        $workflowId = (int) $approval->workflow_id;
        if (!$workflowId) {
            return null;
        }

        $currentStepId = (int) ($approval->getRawOriginal('current_step_id') ?: $approval->current_step_id);
        $currentStep = $currentStepId
            ? ApprovalStep::query()->whereKey($currentStepId)->first()
            : null;

        $currentOrder = (int) ($currentStep?->step_order ?? 0);
        if ($currentOrder <= 0) {
            return ApprovalStep::query()
                ->where('workflow_id', $workflowId)
                ->orderBy('step_order')
                ->first();
        }

        return ApprovalStep::query()
            ->where('workflow_id', $workflowId)
            ->where('step_order', '<', $currentOrder)
            ->orderByDesc('step_order')
            ->first()
            ?: ApprovalStep::query()
                ->where('workflow_id', $workflowId)
                ->orderBy('step_order')
                ->first();
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
            'message' => 'SOI workflow marked as completed.',
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
            'message' => 'SOI workflow status bootstrapped.',
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
        if (str_contains($name, 'pre-screening') || str_contains($name, 'kyc') || str_contains($name, 'loi') || str_contains($name, 'conceptualization')) {
            return self::STATUS_PENDING;
        }
        if (str_contains($name, 'completeness') || str_contains($name, 'requirements') || str_contains($name, 'response letter')) {
            return self::STATUS_INITIAL_COMPLETENESS_CHECK;
        }
        if (str_contains($name, 'divestment')) {
            return self::STATUS_FOR_DIVESTMENT_APPROVAL;
        }
        if (str_contains($name, 'project officer evaluation') || str_contains($name, 'initial review') || str_contains($name, 'validation') || str_contains($name, 'due diligence') || str_contains($name, 'study')) {
            return self::STATUS_FOR_EVALUATION;
        }
        if (str_contains($name, 'agm') || str_contains($name, 'workgroup')) {
            return self::STATUS_FOR_AGM_REVIEW;
        }
        if (str_contains($name, 'mancom')) {
            return self::STATUS_FOR_MANCOM_REVIEW;
        }
        if (str_contains($name, 'neda')) {
            return self::STATUS_FOR_NEDA_ICC_REVIEW;
        }
        if (str_contains($name, 'jv partner selection') || str_contains($name, 'selection and award')) {
            return self::STATUS_FOR_JV_SELECTION;
        }
        if (str_contains($name, 'board')) {
            return self::STATUS_FOR_BOARD_APPROVAL;
        }
        if (str_contains($name, 'construction implementation') || str_contains($name, 'turn-over') || str_contains($name, 'turnover')) {
            return self::STATUS_FOR_MONITORING_UPDATE;
        }
        if (str_contains($name, 'construction')) {
            return self::STATUS_FOR_FUND_RELEASE;
        }
        if (str_contains($name, 'consolidation of milestones') || str_contains($name, 'setting of milestones')) {
            return self::STATUS_MILESTONES_SETUP;
        }
        if (str_contains($name, 'monitoring') || str_contains($name, 'management update')) {
            return self::STATUS_FOR_MONITORING_UPDATE;
        }
        if (str_contains($name, 'post-investment')) {
            return self::STATUS_FOR_MONITORING_UPDATE;
        }
        if (str_contains($name, 'agreement') || str_contains($name, 'fund release') || str_contains($name, 'signing') || str_contains($name, 'jva')) {
            return self::STATUS_FOR_FUND_RELEASE;
        }

        return $this->deriveInProgressStatus((int) $step->step_order);
    }

    private function deriveStageForStep($step): string
    {
        $name = strtolower((string) $step->step_name);

        if (str_contains($name, 'consolidation of milestones') || str_contains($name, 'setting of milestones') || str_contains($name, 'monitoring') || str_contains($name, 'management update') || str_contains($name, 'construction')) {
            return 'Implementation & Monitoring';
        }
        if (str_contains($name, 'post-investment')) {
            return 'Post-Investment Strategy';
        }
        if (str_contains($name, 'divestment')) {
            return 'Divestment';
        }
        if (str_contains($name, 'completeness') || str_contains($name, 'requirements') || str_contains($name, 'response letter')) {
            return 'Requirements';
        }
        if (str_contains($name, 'validation') || str_contains($name, 'due diligence') || str_contains($name, 'initial review') || str_contains($name, 'study')) {
            return 'Due Diligence';
        }
        if (str_contains($name, 'investment committee') || str_contains($name, 'agm') || str_contains($name, 'workgroup') || str_contains($name, 'mancom')) {
            return 'Management Review';
        }
        if (str_contains($name, 'board') || str_contains($name, 'neda') || str_contains($name, 'icc') || str_contains($name, 'jv partner selection') || str_contains($name, 'selection and award')) {
            return 'Board Approval';
        }
        if (str_contains($name, 'agreement') || str_contains($name, 'fund release') || str_contains($name, 'signing') || str_contains($name, 'jva')) {
            return 'Agreement & Fund Release';
        }

        return 'Intake';
    }

    private function finalProjectStageNameForWorkflow(?string $workflowName): string
    {
        return match ($workflowName) {
            'SPG NDC-Owned Project Approval' => 'Completion',
            'NDC Divestment Approval' => 'Divestment',
            'NDC Implementation and Monitoring Workflow' => 'Post-Investment Strategy',
            default => 'Implementation & Monitoring',
        };
    }

    private function finalProjectStatusNameForWorkflow(?string $workflowName): string
    {
        return match ($workflowName) {
            'SPG NDC-Owned Project Approval' => 'Completed',
            'SPG Joint Venture Project Approval' => 'Implementation Ongoing',
            'NDC Divestment Approval' => 'Divested',
            'NDC Implementation and Monitoring Workflow' => 'Post-Investment Review',
            default => 'Approved',
        };
    }

    private static function statusIdForWorkflowStatus(string $status): ?int
    {
        $map = [
            self::STATUS_PENDING => 'LOI Received',
            self::STATUS_INITIAL_COMPLETENESS_CHECK => 'Requirements Requested',
            self::STATUS_FOR_EVALUATION => 'Due Diligence Ongoing',
            self::STATUS_FOR_IC_EVALUATION => 'For IC Evaluation',
            self::STATUS_FOR_AGM_REVIEW => 'For Workgroup Review',
            self::STATUS_FOR_WORKGROUP_REVIEW => 'For Workgroup Review',
            self::STATUS_FOR_MANCOM_REVIEW => 'For ManCom Review',
            self::STATUS_FOR_BOARD_APPROVAL => 'For Board Approval',
            self::STATUS_FOR_NEDA_ICC_REVIEW => 'For NEDA-ICC Review',
            self::STATUS_FOR_JV_SELECTION => 'For JV Selection',
            self::STATUS_FOR_FUND_RELEASE => 'For Fund Release',
            self::STATUS_MILESTONES_SETUP => 'Milestones Setup',
            self::STATUS_FOR_MONITORING_UPDATE => 'For Monitoring Update',
            self::STATUS_FOR_DIVESTMENT_APPROVAL => 'For Divestment Approval',
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
        $preferredWorkflowName = self::workflowNameForProject($project);

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
        $autoCompleteFirstStep = self::shouldAutoCompleteSubmitterStep($firstStep);
        $nextStep = $autoCompleteFirstStep ? $steps->skip(1)->first() : null;
        $currentStep = $nextStep ?? $firstStep;
        $initialStatus = (new self())->deriveStatusForStep($currentStep);
        $oldStatusId = $project?->status_id;
        $oldStageId = $project?->current_stage_id;

        $approval = ProjectApproval::updateOrCreate(
            ['project_id' => $projectId],
            [
                'workflow_id' => $workflow->id,
                'current_step_id' => $currentStep?->id,
                'overall_status' => $initialStatus,
                'started_at' => now(),
                'completed_at' => null,
                ...self::timingForStep($currentStep),
            ]
        );

        if ($autoCompleteFirstStep) {
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
        }

        if ($currentStep) {
            $controller = new self();
            $newStatusId = self::statusIdForWorkflowStatus($initialStatus)
                ?? self::statusIdByName('LOI Received')
                ?? $project?->status_id;
            $newStageId = self::stageIdByName($controller->deriveStageForStep($currentStep))
                ?? $project?->current_stage_id;

            Project::query()->whereKey($projectId)->update([
                'status_id' => $newStatusId,
                'current_stage_id' => $newStageId,
            ]);

            if ($project && $oldStatusId && $newStatusId && (int) $oldStatusId !== (int) $newStatusId) {
                \App\Models\ProjectStatusHistory::create([
                    'project_id' => $projectId,
                    'from_status_id' => $oldStatusId,
                    'to_status_id' => $newStatusId,
                    'changed_by' => $proponentUserId,
                    'change_reason' => 'SOI package submitted',
                ]);
            }

            if ($project && $oldStageId && $newStageId && (int) $oldStageId !== (int) $newStageId) {
                \App\Models\ProjectStageHistory::create([
                    'project_id' => $projectId,
                    'from_stage_id' => $oldStageId,
                    'to_stage_id' => $newStageId,
                    'changed_by' => $proponentUserId,
                    'change_reason' => 'SOI package submitted',
                ]);
            }

            $freshProject = Project::query()->find($projectId);
            if ($freshProject) {
                self::autoRequestProponentRequirements($freshProject);
            }
        }

        self::notifyCurrentStepApprovers($approval->fresh(['project', 'workflow', 'currentStep.role']), User::find($proponentUserId));

        return $approval;
    }

    private static function workflowNameForProject(?Project $project): string
    {
        if ($project?->is_svf) {
            return 'NDC SVF Investment Approval';
        }

        return match ($project?->process_track) {
            'spg_traditional' => 'SPG Traditional Equity Funding Approval',
            'spg_ndc_own' => 'SPG NDC-Owned Project Approval',
            'spg_jv' => 'SPG Joint Venture Project Approval',
            'implementation_monitoring' => 'NDC Implementation and Monitoring Workflow',
            'divestment' => 'NDC Divestment Approval',
            default => 'NDC BDG Investment Approval',
        };
    }

    private static function shouldAutoCompleteSubmitterStep($step): bool
    {
        if (!$step) {
            return false;
        }

        $roleName = strtolower((string) ($step->role?->name ?? ''));
        $stepName = strtolower((string) $step->step_name);

        return $roleName === 'proponent' || str_contains($stepName, 'proponent submission');
    }

    private static function notifyCurrentStepApprovers(ProjectApproval $approval, ?User $actor = null): void
    {
        $approval->loadMissing(['project.creator', 'project.members.user', 'currentStep.role']);

        if (!$approval->currentStep || !$approval->project) {
            return;
        }

        $project = $approval->project;
        $stepName = $approval->currentStep->step_name;
        $roleName = $approval->currentStep->role?->name ?? 'Reviewer';
        $actionUrl = self::projectActionUrl($project, 'approval');
        $recipients = self::currentStepRecipients($approval);

        if ($recipients->isNotEmpty()) {
            $title = "Approval required: {$project->project_code} - {$project->title}";
            $message = "{$project->project_code} - {$project->title} is waiting for {$stepName}.";

            try {
                app(NotificationService::class)->notifyUsers(
                    $recipients,
                    'approval_request',
                    $title,
                    $message,
                    $project,
                    'approval_request',
                    [
                        'project_code' => $project->project_code,
                        'project_title' => $project->title,
                        'submitter_name' => $actor?->full_name ?? 'System',
                        'stage_name' => $stepName,
                        'current_step' => $stepName,
                        'reviewer_role' => $roleName,
                        'action_url' => $actionUrl,
                        'action_label' => 'Open SOI Flow',
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

        if (!self::shouldAutoCompleteSubmitterStep($approval->currentStep)) {
            self::notifyProponentOfSoiStepChange($approval, $actor, $actionUrl);
        }
    }

    private static function notifyProponentOfSoiStepChange(ProjectApproval $approval, ?User $actor, string $actionUrl): void
    {
        $project = $approval->project;
        $step = $approval->currentStep;

        if (!$project || !$step) {
            return;
        }

        $stepName = $step->step_name;
        $roleName = $step->role?->name ?? 'NDC reviewer';

        try {
            app(NotificationService::class)->notifyProjectProponent(
                $project,
                'soi_step_changed',
                "SOI progress update: {$project->project_code}",
                "{$project->project_code} - {$project->title} moved to {$stepName}. Current reviewer: {$roleName}.",
                'soi_step_changed',
                [
                    'project_code' => $project->project_code,
                    'project_title' => $project->title,
                    'current_step' => $stepName,
                    'stage_name' => $stepName,
                    'reviewer_role' => $roleName,
                    'changed_by' => $actor?->full_name ?? 'System',
                    'action_url' => $actionUrl,
                    'action_label' => 'View SOI Flow',
                ]
            );
        } catch (\Throwable $notificationException) {
            \Log::warning('SOI progress notification failed.', [
                'project_id' => $project->id,
                'approval_id' => $approval->id,
                'step_id' => $step->id,
                'error' => $notificationException->getMessage(),
            ]);
        }
    }

    private static function projectActionUrl(Project $project, string $tab = 'approval', array $query = []): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');
        $params = array_merge([
            'project_id' => $project->id,
            'tab' => $tab,
        ], $query);

        return "{$frontendUrl}/projects?" . http_build_query($params);
    }

    private static function currentStepRecipients(ProjectApproval $approval)
    {
        $step = $approval->currentStep;
        $project = $approval->project;

        if (self::shouldAutoCompleteSubmitterStep($step)) {
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

    private function missingEndorsementArtifacts(ProjectApproval $approval)
    {
        $approval->loadMissing(['project', 'currentStep.role']);
        $project = $approval->project;
        $step = $approval->currentStep;

        if (!$project || !$step) {
            return collect();
        }

        $gates = $this->gatesForApprovalStep(
            (string) $step->step_name,
            (string) ($step->role?->name ?? ''),
            (string) ($project->process_track ?: '')
        );
        if (empty($gates)) {
            return collect();
        }

        return ProjectRequirement::query()
            ->where('project_id', $project->id)
            ->where('owner_type', 'internal')
            ->where('is_required', true)
            ->whereIn('gate_step', $gates)
            ->get()
            ->filter(function (ProjectRequirement $requirement) {
                if (in_array((string) $requirement->status, ['received', 'approved', 'approved_with_conditions'], true)) {
                    return false;
                }

                return !(
                    (string) $requirement->status === 'waived'
                    && trim((string) $requirement->remarks) !== ''
                );
            })
            ->map(fn (ProjectRequirement $requirement) => $requirement->item_name);
    }

    private function gatesForApprovalStep(string $stepName, string $roleName, string $projectTrack = ''): array
    {
        $text = strtolower($stepName . ' ' . $roleName);

        if ($projectTrack === 'spg_jv') {
            return match (true) {
                str_contains($text, 'mancom approval to proceed'),
                str_contains($text, 'jv project conceptualization'),
                str_contains($text, 'procurement of consultancy') => [],
                str_contains($text, 'mancom jv project decision') => ['spg_jv_mancom_project_decision'],
                str_contains($text, 'board approval of jv project') => ['spg_jv_board_project_approval'],
                str_contains($text, 'neda-icc') || str_contains($text, 'neda icc') => ['spg_jv_neda_icc'],
                str_contains($text, 'jva terms') || str_contains($text, 'jv-sc') || str_contains($text, 'jv sc') => ['spg_jv_jva_terms_jvsc'],
                str_contains($text, 'jv partner selection') => ['spg_jv_selection_award'],
                str_contains($text, 'final board approval') => ['spg_jv_final_award'],
                str_contains($text, 'signing of jva') => ['spg_jv_jva_signing'],
                default => [],
            };
        }

        if ($projectTrack === 'spg_ndc_own') {
            return match (true) {
                str_contains($text, 'mancom approval to proceed'),
                str_contains($text, 'project conceptualization'),
                str_contains($text, 'procurement of consultancy') => [],
                str_contains($text, 'mancom project decision') => ['spg_ndc_own_mancom_project_decision'],
                trim(strtolower($stepName)) === 'board approval' => ['spg_ndc_own_board_approval'],
                str_contains($text, 'ded') || str_contains($text, 'construction procurement') || str_contains($text, 'construction agreement') => ['spg_ndc_own_ded_construction'],
                str_contains($text, 'construction implementation') || str_contains($text, 'turn-over') || str_contains($text, 'turnover') => ['spg_ndc_own_turnover'],
                default => [],
            };
        }

        $gates = [];

        if (str_contains($text, 'mancom') || str_contains($text, 'management committee')) {
            $gates[] = 'mancom';
        }

        if (str_contains($text, 'board')) {
            $gates[] = 'board';
        }

        if (str_contains($text, 'legal') || str_contains($text, 'finance') || str_contains($text, 'agreement') || str_contains($text, 'fund release') || str_contains($text, 'signing')) {
            $gates[] = 'fund_release';
        }

        if (str_contains($text, 'neda') || str_contains($text, 'icc') || str_contains($text, 'selection') || str_contains($text, 'award') || str_contains($text, 'partner selection')) {
            $gates[] = 'jv';
        }

        if (str_contains($text, 'monitor') || str_contains($text, 'milestone') || str_contains($text, 'adjustment')) {
            $gates[] = 'monitoring';
        }

        if (str_contains($text, 'divest')) {
            $gates[] = 'divestment';
        }

        return array_values(array_unique($gates));
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
            $notificationService->notifyProjectStakeholders(
                $project,
                'approval_result',
                "{$statusText}: {$project->project_code}",
                $message,
                'project_status_change',
                [
                    'project_code' => $project->project_code,
                    'project_title' => $project->title,
                    'old_status' => 'SOI Routing',
                    'new_status' => $statusText,
                    'changed_by' => $actor->full_name,
                    'reason' => $conditions ?: 'SOI workflow completed.',
                    'action_url' => self::projectActionUrl($project, 'approval'),
                    'action_label' => 'Open SOI Flow',
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
            $notificationService->notifyProjectStakeholders(
                $project,
                'project_returned',
                "Revision required: {$project->project_code}",
                "{$project->title} was returned for revision. Reason: {$comments}",
                'project_status_change',
                [
                    'project_code' => $project->project_code,
                    'project_title' => $project->title,
                    'old_status' => 'SOI Routing',
                    'new_status' => 'Returned for Revision',
                    'changed_by' => $actor?->full_name ?? 'System',
                    'reason' => $comments,
                    'action_url' => self::projectActionUrl($project, 'approval'),
                    'action_label' => 'Open SOI Flow',
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

    private static function autoRequestProponentRequirements(Project $project): void
    {
        $project->loadMissing('currentStage');
        $stageName = $project->currentStage?->name;
        if (!$stageName) {
            return;
        }

        // Map Stage Name to soi_section
        $sectionMap = [
            'Intake' => ['intake'],
            'Requirements' => ['requirements'],
            'Due Diligence' => ['due_diligence'],
            'Management Review' => ['management_review'],
            'Board Approval' => ['board_approval'],
            'Agreement & Fund Release' => ['agreement_fund_release'],
            'Implementation & Monitoring' => ['implementation_monitoring'],
            'Post-Investment Strategy' => ['post_investment_strategy'],
            'Divestment' => ['divestment'],
            'Completion' => ['completion'],
        ];

        $sections = $sectionMap[$stageName] ?? [];
        if (empty($sections)) {
            return;
        }

        // Get pending proponent requirements for this stage
        $pendingReqs = \App\Models\ProjectRequirement::query()
            ->where('project_id', $project->id)
            ->where('owner_type', 'proponent')
            ->where('status', 'pending')
            ->whereIn('soi_section', $sections)
            ->get();

        if ($pendingReqs->isEmpty()) {
            return;
        }

        // Update all to requested
        foreach ($pendingReqs as $req) {
            $req->update(['status' => 'requested']);
        }

        // Send a consolidated notification to the proponent
        try {
            $count = $pendingReqs->count();
            $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');
            $actionUrl = "{$frontendUrl}/projects?project_id={$project->id}&tab=requirements";
            
            $message = "NDC has automatically requested {$count} new requirement" . ($count > 1 ? 's' : '') . " for {$project->title} as it entered the {$stageName} phase. Please review and upload the files.";

            app(NotificationService::class)->notifyProjectProponent(
                $project,
                'requirement_status_change',
                "New requirements requested: {$project->project_code}",
                $message,
                'requirement_status_change',
                [
                    'project_title' => $project->title,
                    'project_code' => $project->project_code,
                    'new_status' => 'Requested',
                    'changed_by' => 'System',
                    'remarks' => $message,
                    'action_url' => $actionUrl,
                    'action_label' => 'View Requirements',
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Consolidated auto-requirement notification failed.', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function timingForStep(?ApprovalStep $step): array
    {
        if (!$step) {
            return [
                'current_step_started_at' => null,
                'sla_due_at' => null,
            ];
        }

        $startedAt = now();

        return [
            'current_step_started_at' => $startedAt,
            'sla_due_at' => $step->sla_days ? $startedAt->copy()->addDays($step->sla_days) : null,
        ];
    }
}
