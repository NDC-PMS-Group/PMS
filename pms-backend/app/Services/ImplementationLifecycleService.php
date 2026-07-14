<?php

namespace App\Services;

use App\Models\ImplementationTaskTemplate;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStageHistory;
use App\Models\ProjectStatus;
use App\Models\ProjectStatusHistory;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImplementationLifecycleService
{
    private const ACCEPTED_REQUIREMENT_STATUSES = [
        'received',
        'approved',
        'approved_with_conditions',
        'waived',
    ];

    private const DEVELOPMENT_WORKFLOWS = [
        'NDC BDG Investment Approval',
        'NDC SVF Investment Approval',
        'SPG Joint Venture Project Approval',
        'SPG Traditional Equity Funding Approval',
        'SPG NDC-Owned Project Approval',
    ];

    public function readiness(Project $project): array
    {
        $project->loadMissing([
            'projectType',
            'currentStage',
            'status',
            'approvals.workflow',
            'requirements',
            'fundReleases',
        ]);

        $blockers = [];
        $approvedDevelopment = $project->approvals
            ->filter(fn ($approval) => in_array($approval->workflow?->name, self::DEVELOPMENT_WORKFLOWS, true))
            ->contains(fn ($approval) => in_array($approval->overall_status, ['approved', 'approved_with_conditions', 'completed'], true));

        if (! $approvedDevelopment) {
            $blockers[] = [
                'code' => 'development_approval',
                'label' => 'Development approval is incomplete',
                'detail' => 'Complete the applicable SOI approval workflow before implementation starts.',
            ];
        }

        $missingAgreementItems = $project->requirements
            ->filter(fn ($requirement) => $requirement->is_required
                && $requirement->is_applicable !== false
                && $requirement->soi_section === 'agreement_fund_release'
                && ! in_array($requirement->status, self::ACCEPTED_REQUIREMENT_STATUSES, true))
            ->pluck('item_name')
            ->values();

        if ($missingAgreementItems->isNotEmpty()) {
            $blockers[] = [
                'code' => 'agreement_requirements',
                'label' => 'Agreement or release evidence is incomplete',
                'detail' => $missingAgreementItems->take(4)->implode(', '),
                'count' => $missingAgreementItems->count(),
            ];
        }

        $requiresFundRelease = (float) ($project->ndc_participation ?? 0) > 0
            && $project->requirements->contains(fn ($requirement) => $requirement->is_required
                && $requirement->is_applicable !== false
                && $requirement->gate_step === 'fund_release');

        if ($requiresFundRelease) {
            $released = $project->fundReleases->where('status', 'released');
            if ($released->isEmpty()) {
                $blockers[] = [
                    'code' => 'fund_release',
                    'label' => 'Required fund release is not recorded',
                    'detail' => 'Record and release the applicable fund transaction before implementation starts.',
                ];
            }
        }

        $alreadyStarted = $project->lifecycle_phase === 'implementation_monitoring'
            && ($project->implementation_started_by || $project->tasks()->implementation()->active()->exists());

        return [
            'ready' => $blockers === [] && ! $alreadyStarted,
            'already_started' => $alreadyStarted,
            'lifecycle_phase' => $project->lifecycle_phase ?: 'development',
            'template' => $this->templateKey($project),
            'blockers' => $blockers,
        ];
    }

    public function start(Project $project, User $actor): Project
    {
        $readiness = $this->readiness($project);

        if ($readiness['already_started']) {
            throw new ImplementationAlreadyStartedException();
        }

        if (! $readiness['ready']) {
            throw new ImplementationNotReadyException($readiness['blockers']);
        }

        return DB::transaction(function () use ($project, $actor) {
            $project->refresh();
            $readiness = $this->readiness($project);
            if ($readiness['already_started']) {
                throw new ImplementationAlreadyStartedException();
            }
            if (! $readiness['ready']) {
                throw new ImplementationNotReadyException($readiness['blockers']);
            }

            $stageId = ProjectStage::query()->where('name', 'Implementation & Monitoring')->value('id');
            $statusId = ProjectStatus::query()->where('name', 'Implementation Ongoing')->value('id')
                ?: ProjectStatus::query()->where('name', 'Monitoring Ongoing')->value('id');
            $oldStageId = $project->current_stage_id;
            $oldStatusId = $project->status_id;

            $project->update(array_filter([
                'lifecycle_phase' => 'implementation_monitoring',
                'lifecycle_phase_started_at' => now(),
                'implementation_started_by' => $actor->id,
                'current_stage_id' => $stageId,
                'status_id' => $statusId,
                'start_date' => $project->start_date ?: today(),
            ], fn ($value) => $value !== null));

            if ($stageId && (int) $oldStageId !== (int) $stageId) {
                ProjectStageHistory::create([
                    'project_id' => $project->id,
                    'from_stage_id' => $oldStageId,
                    'to_stage_id' => $stageId,
                    'changed_by' => $actor->id,
                    'change_reason' => 'Implementation started',
                ]);
            }

            if ($statusId && (int) $oldStatusId !== (int) $statusId) {
                ProjectStatusHistory::create([
                    'project_id' => $project->id,
                    'from_status_id' => $oldStatusId,
                    'to_status_id' => $statusId,
                    'changed_by' => $actor->id,
                    'change_reason' => 'Implementation started',
                ]);
            }

            $this->initializeTasks($project, $actor);

            DB::afterCommit(function () use ($project, $actor) {
                try {
                    $fresh = $project->fresh(['creator', 'projectOfficer', 'workgroupHead', 'members.user']);
                    $notifications = app(NotificationService::class);
                    $notifications->notifyUsers(
                        $notifications->internalProjectStakeholders($fresh, $actor),
                        'implementation_started',
                        "Implementation started: {$fresh->project_code}",
                        "{$fresh->title} is now in implementation and its delivery work plan is available.",
                        $fresh,
                        null,
                        [
                            'action_url' => rtrim((string) config('app.frontend_url'), '/') . "/projects/{$fresh->id}/tasks",
                            'action_label' => 'Open Implementation Plan',
                        ]
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

            return $project->fresh([
                'projectType', 'industry', 'sector', 'currentStage', 'status',
                'projectOfficer', 'workgroupHead', 'implementationStartedBy',
                'creator', 'members.user', 'members.role', 'requirements',
            ]);
        });
    }

    private function initializeTasks(Project $project, User $actor): void
    {
        if ($project->tasks()->implementation()->active()->exists()) {
            return;
        }

        $templateKey = $this->templateKey($project);
        $templates = ImplementationTaskTemplate::query()
            ->where('is_active', true)
            ->where('template_key', $templateKey)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            $templateKey = 'generic';
            $templates = ImplementationTaskTemplate::query()
                ->where('is_active', true)
                ->where('template_key', 'generic')
                ->orderBy('sort_order')
                ->get();
        }

        $ownerId = $project->project_officer_id ?: $actor->id;
        $workgroupHeadId = $project->workgroup_head_id ?: $ownerId;
        $start = $project->start_date?->copy() ?: today();
        $created = [];

        foreach ($templates as $template) {
            $parentId = $template->parent_template_title
                ? ($created[$template->parent_template_title] ?? null)
                : null;
            $taskStart = $start->copy()->addDays($template->start_offset_days);
            $task = Task::create([
                'project_id' => $project->id,
                'title' => $template->title,
                'description' => $template->description,
                'task_type' => 'implementation',
                'soi_section' => null,
                'task_scope' => 'implementation',
                'workstream' => $template->workstream,
                'template_source' => $templateKey,
                'assigned_to' => $template->assigned_role === 'Workgroup Head' ? $workgroupHeadId : $ownerId,
                'assigned_by' => $actor->id,
                'start_date' => $taskStart,
                'due_date' => $taskStart->copy()->addDays($template->duration_days),
                'status' => 'pending',
                'progress_percentage' => 0,
                'priority' => $template->priority,
                'parent_task_id' => $parentId,
                'is_milestone' => $template->is_milestone,
                'is_deleted' => false,
            ]);

            $created[$template->title] = $task->id;
            TaskStatusHistory::create([
                'task_id' => $task->id,
                'from_status' => null,
                'to_status' => 'pending',
                'from_progress' => null,
                'to_progress' => 0,
                'changed_by' => $actor->id,
                'event_type' => 'created',
                'notes' => "Created from the {$templateKey} implementation template.",
                'changed_at' => now(),
            ]);
        }
    }

    private function templateKey(Project $project): string
    {
        if ($project->is_svf) {
            return 'svf';
        }

        return match ($project->projectType?->name) {
            'Infrastructure' => 'infrastructure',
            'Business Development' => 'business_development',
            'Joint Venture' => 'joint_venture',
            'Public-Private Partnership' => 'ppp',
            'Research & Development' => 'research_development',
            'SVF Project' => 'svf',
            default => 'generic',
        };
    }
}
