<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveProjectRequest;
use App\Models\ProjectApproval;
use App\Models\ApprovalStepRecord;
use App\Models\ApprovalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_FOR_EVALUATION = 'for_evaluation';
    private const STATUS_FOR_APPROVAL = 'for_approval';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_APPROVED_WITH_CONDITIONS = 'approved_with_conditions';
    private const STATUS_COMPLETED = 'completed';

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

        $approvals = ProjectApproval::with(['project', 'workflow', 'currentStep'])
            ->where(function ($query) use ($userRoleId, $user) {
                $query->whereHas('currentStep', function ($stepQuery) use ($userRoleId) {
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
            })
            ->whereIn('overall_status', [
                self::STATUS_PENDING,
                self::STATUS_FOR_EVALUATION,
                self::STATUS_FOR_APPROVAL,
            ])
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

        $isProponentStep = (int)$currentStep->step_order === 1;
        if ($isProponentStep) {
            if ((int)$approval->project->created_by !== (int)$user->id) {
                return response()->json(['message' => 'Only the project proponent can process this step.'], 403);
            }
        } else {
            if ((int)$currentStep->role_id !== (int)$user->default_role_id) {
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

        if ($nextStep) {
            $approval->update([
                'current_step_id' => $nextStep->id,
                'overall_status' => $this->deriveInProgressStatus((int)$nextStep->step_order),
                'completed_at' => null,
            ]);
        } else {
            $finalStatus = $request->status === self::STATUS_APPROVED_WITH_CONDITIONS
                ? self::STATUS_APPROVED_WITH_CONDITIONS
                : self::STATUS_APPROVED;

            $approval->update([
                'overall_status' => $finalStatus,
                'completed_at' => now(),
                'current_step_id' => null,
            ]);
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
            'overall_status' => self::STATUS_PENDING,
            'current_step_id' => $firstStep->id,
            'completed_at' => null,
        ]);

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
            'overall_status' => self::STATUS_PENDING,
            'current_step_id' => $firstStep->id,
            'completed_at' => null,
        ]);

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
            'overall_status' => $this->deriveInProgressStatus($stepOrder),
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

        if ($stepOrder === 2) {
            return self::STATUS_FOR_EVALUATION;
        }

        return self::STATUS_FOR_APPROVAL;
    }

    public static function createInitialApprovalForProject(int $projectId, ?int $projectTypeId, int $proponentUserId): ?ProjectApproval
    {
        $workflow = ApprovalWorkflow::query()
            ->where('is_active', true)
            ->where('name', 'SOI Sequential Approval')
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

        $approval = ProjectApproval::updateOrCreate(
            ['project_id' => $projectId],
            [
                'workflow_id' => $workflow->id,
                'current_step_id' => $nextStep?->id ?? $firstStep->id,
                'overall_status' => $nextStep ? self::STATUS_FOR_EVALUATION : self::STATUS_PENDING,
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

        return $approval;
    }
}
