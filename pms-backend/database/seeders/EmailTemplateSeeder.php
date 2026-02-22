<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'task_assigned',
                'subject' => 'New Task Assigned: {{task_title}}',
                'body' => 'Hello {{user_name}},

You have been assigned a new task: {{task_title}}

Project: {{project_name}}
Due Date: {{due_date}}
Priority: {{priority}}

Description:
{{task_description}}

Please log in to the system to view more details.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'task_title', 'project_name', 'due_date', 'priority', 'task_description']),
                'is_active' => true,
            ],
            [
                'name' => 'approval_request',
                'subject' => 'Approval Required: {{project_title}}',
                'body' => 'Hello {{approver_name}},

A project requires your approval:

Project: {{project_title}}
Submitted by: {{submitter_name}}
Stage: {{stage_name}}

Please review and approve/reject this project in the system.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['approver_name', 'project_title', 'submitter_name', 'stage_name']),
                'is_active' => true,
            ],
            [
                'name' => 'deadline_reminder',
                'subject' => 'Deadline Reminder: {{item_title}}',
                'body' => 'Hello {{user_name}},

This is a reminder that the following item is due soon:

Item: {{item_title}}
Type: {{item_type}}
Due Date: {{due_date}}
Days Remaining: {{days_remaining}}

Please take necessary action.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'item_title', 'item_type', 'due_date', 'days_remaining']),
                'is_active' => true,
            ],
            [
                'name' => 'project_status_change',
                'subject' => 'Project Status Updated: {{project_title}}',
                'body' => 'Hello {{user_name}},

The status of project "{{project_title}}" has been updated.

Previous Status: {{old_status}}
New Status: {{new_status}}
Changed by: {{changed_by}}
Reason: {{reason}}

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'project_title', 'old_status', 'new_status', 'changed_by', 'reason']),
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $template['name']],
                array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
