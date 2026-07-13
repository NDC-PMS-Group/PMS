<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationEventSettingSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['account_registered', 'New account registration', 'Accounts', 'Notify administrators when a proponent registers.', null],
            ['account_approved', 'Account approved', 'Accounts', 'Notify a proponent that the account can now be used.', null],
            ['account_rejected', 'Account rejected', 'Accounts', 'Notify a proponent that registration was declined.', null],
            ['account_deactivated', 'Account deactivated', 'Accounts', 'Notify a user when system access is deactivated.', null],
            ['proposal_submitted', 'Proposal submitted', 'Project development', 'Notify NDC reviewers when a proposal enters SOI screening.', 'approval_request'],
            ['project_updated', 'Project details updated', 'Project development', 'Notify project stakeholders when editable details change.', 'project_status_change'],
            ['project_status_change', 'Project status changed', 'Project development', 'Notify stakeholders when the SOI or operational status changes.', 'project_status_change'],
            ['project_archived', 'Project archived', 'Project development', 'Notify stakeholders when a project is archived.', 'project_status_change'],
            ['project_unarchived', 'Project restored', 'Project development', 'Notify stakeholders when an archived project is restored.', 'project_status_change'],
            ['project_member_added', 'Project member added', 'Project team', 'Notify a user when added to a project.', null],
            ['project_member_removed', 'Project member removed', 'Project team', 'Notify a user when removed from a project.', null],
            ['approval_request', 'Approval action required', 'Approvals', 'Notify the role responsible for the current SOI approval step.', 'approval_request'],
            ['soi_step_changed', 'SOI progress changed', 'Approvals', 'Notify proponents when an SOI project moves to a new internal review step.', 'soi_step_changed'],
            ['approval_result', 'Approval decision recorded', 'Approvals', 'Notify stakeholders when an approval decision is recorded.', null],
            ['project_returned', 'Proposal returned for revision', 'Approvals', 'Notify the proponent and project team of required revisions.', null],
            ['requirement_status_change', 'Requirement requested or reviewed', 'Requirements', 'Notify the proponent when NDC requests, returns, accepts, or waives a requirement.', 'requirement_status_change'],
            ['document_uploaded', 'Draft document uploaded', 'Documents', 'Notify project stakeholders when a draft document is added.', null],
            ['document_submitted', 'Document submitted', 'Documents', 'Notify reviewers when a draft is formally submitted.', null],
            ['document_update_requested', 'Document update requested', 'Documents', 'Notify the document owner when a submitted file must be revised.', null],
            ['task_assigned', 'Task assigned', 'Work plan', 'Notify the assignee when an SOI work-plan task is assigned.', 'task_assigned'],
            ['task_reassigned', 'Task reassigned', 'Work plan', 'Notify the new assignee when responsibility changes.', 'task_assigned'],
            ['task_updated', 'Task updated', 'Work plan', 'Notify affected users when a task changes.', null],
            ['task_progress_updated', 'Task progress updated', 'Work plan', 'Notify the task owner when progress changes.', null],
            ['task_completed', 'Task completed', 'Work plan', 'Notify project stakeholders when a task is completed.', null],
            ['task_deleted', 'Task removed', 'Work plan', 'Notify the assignee when a task is removed.', null],
            ['monitoring_activated', 'Monitoring compliance opened', 'Implementation and monitoring', 'Notify the proponent and project team when NDC opens a monitoring period.', 'monitoring_request'],
            ['monitoring_updated', 'Monitoring report updated', 'Implementation and monitoring', 'Notify stakeholders when an NDC reviewer updates monitoring indicators.', null],
            ['monitoring_submitted', 'Monitoring report submitted', 'Implementation and monitoring', 'Notify NDC reviewers when a proponent submits a monitoring report.', null],
            ['monitoring_returned', 'Monitoring report returned', 'Implementation and monitoring', 'Notify the proponent when a monitoring report requires correction.', null],
            ['monitoring_accepted', 'Monitoring report accepted', 'Implementation and monitoring', 'Notify the proponent and project team when NDC accepts a monitoring report.', null],
            ['monitoring_closed', 'Monitoring period closed', 'Implementation and monitoring', 'Notify stakeholders when NDC closes a monitoring period.', null],
        ];

        foreach ($events as [$key, $label, $category, $description, $template]) {
            DB::table('notification_event_settings')->insertOrIgnore([
                [
                    'event_key' => $key,
                    'label' => $label,
                    'category' => $category,
                    'description' => $description,
                    'in_app_enabled' => true,
                    'email_enabled' => true,
                    'template_name' => $template,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            ]);
        }
    }
}
