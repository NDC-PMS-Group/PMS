<?php

namespace App\Services;

use App\Models\DefaultTask;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProjectTaskTemplateService
{
    public function sync(Project $project, string $track, User $actor): int
    {
        $templates = DefaultTask::query()
            ->where('track', $track)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        $project->loadMissing(['projectOfficer', 'workgroupHead', 'proponentUser']);
        $baseDate = Carbon::parse($project->start_date ?: $project->created_at ?: today());
        $createdByTitle = [];
        $createdCount = 0;

        foreach ($templates as $template) {
            $source = "soi:{$track}:{$template->id}";
            $existing = $project->tasks()
                ->where(function ($query) use ($source, $template) {
                    $query->where('template_source', $source)
                        ->orWhere(function ($legacy) use ($template) {
                            $legacy->where('title', $template->title)
                                ->where('soi_section', $template->soi_section);
                        });
                })
                ->first();

            if ($existing) {
                if ($existing->task_scope === 'legacy_soi'
                    && $existing->archive_reason === 'Archived during implementation task alignment') {
                    $existing->update([
                        'task_scope' => 'workflow',
                        'template_source' => $source,
                        'archived_at' => null,
                        'archive_reason' => null,
                    ]);
                }
                $createdByTitle[$template->title] = $existing->id;
                continue;
            }

            $task = Task::create([
                'project_id' => $project->id,
                'title' => $template->title,
                'description' => $template->description,
                'task_type' => $template->task_type ?: 'workflow',
                'soi_section' => $template->soi_section,
                'task_scope' => 'workflow',
                'workstream' => $this->sectionLabel($template->soi_section),
                'template_source' => $source,
                'assigned_to' => $this->assigneeId($project, $template->assigned_role, $actor),
                'assigned_by' => $actor->id,
                'start_date' => $baseDate->copy(),
                'due_date' => $baseDate->copy()->addDays(max(0, (int) $template->days)),
                'status' => 'pending',
                'progress_percentage' => 0,
                'priority' => $template->priority ?: 'normal',
                'parent_task_id' => $template->parent_task_title
                    ? ($createdByTitle[$template->parent_task_title] ?? null)
                    : null,
                'is_milestone' => (bool) $template->is_milestone,
                'is_deleted' => false,
            ]);

            $createdByTitle[$template->title] = $task->id;
            $createdCount++;

            TaskStatusHistory::create([
                'task_id' => $task->id,
                'from_status' => null,
                'to_status' => 'pending',
                'from_progress' => null,
                'to_progress' => 0,
                'changed_by' => $actor->id,
                'event_type' => 'created',
                'notes' => "Created from the {$track} SOI workflow template.",
                'changed_at' => now(),
            ]);
        }

        return $createdCount;
    }

    private function assigneeId(Project $project, ?string $role, User $actor): int
    {
        return match (strtolower((string) $role)) {
            'proponent' => $project->proponent_user_id ?: $actor->id,
            'workgroup head' => $project->workgroup_head_id ?: $project->project_officer_id ?: $actor->id,
            default => $project->project_officer_id ?: $actor->id,
        };
    }

    private function sectionLabel(?string $section): string
    {
        return match ($section) {
            'intake' => 'Intake',
            'requirements' => 'Requirements',
            'due_diligence' => 'Due Diligence',
            'management_review' => 'Management Review',
            'board_approval' => 'Board Approval',
            'agreement_fund_release' => 'Agreement & Fund Release',
            'implementation_monitoring' => 'Implementation & Monitoring',
            'post_investment_strategy' => 'Post-Investment Strategy',
            'divestment' => 'Divestment / Exit',
            'completion' => 'Completion',
            default => 'Project Work',
        };
    }
}
