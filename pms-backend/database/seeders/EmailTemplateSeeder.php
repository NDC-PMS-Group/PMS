<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'task_assigned',
                'subject' => 'New Task Assigned: {{task_title}}',
                'body' => 'Hello {{user_name}},

A new task has been assigned to you under the project "{{project_name}}".

Task Title: {{task_title}}
Project Name: {{project_name}}
Target Due Date: {{due_date}}
Priority Level: {{priority}}

Task Description:
{{task_description}}

Please log in to the NDC PMS to review the task, update the completion percentage, and track progress.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'task_title', 'project_name', 'due_date', 'priority', 'task_description']),
                'is_active' => true,
            ],
            [
                'name' => 'approval_request',
                'subject' => 'Approval Required: {{project_code}} - {{project_title}}',
                'body' => 'Hello {{approver_name}},

A project has been routed to your queue and requires your review or approval action.

Project Title: {{project_title}}
Project Code: {{project_code}}
Current Stage: {{current_step}}
Reviewer Role: {{reviewer_role}}
Routed By: {{submitter_name}}

Please log in and access the SOI Flow dashboard to view the documents and submit your review.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['approver_name', 'project_code', 'project_title', 'current_step', 'reviewer_role', 'submitter_name', 'action_url', 'action_label']),
                'is_active' => true,
            ],
            [
                'name' => 'soi_step_changed',
                'subject' => 'SOI Progress Update: {{project_code}} - {{project_title}}',
                'body' => 'Hello {{user_name}},

The Standard Operating Instructions (SOI) workflow stage for your project has been updated.

Project Title: {{project_title}}
Project Code: {{project_code}}
New Stage: {{current_step}}
Assigned Role: {{reviewer_role}}
Updated By: {{changed_by}}

This is an automated status update for your project tracking. No immediate action is required from you at this step.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'project_code', 'project_title', 'current_step', 'reviewer_role', 'changed_by', 'action_url', 'action_label']),
                'is_active' => true,
            ],
            [
                'name' => 'deadline_reminder',
                'subject' => 'Deadline Reminder: {{item_title}}',
                'body' => 'Hello {{user_name}},

This is an automated reminder that a milestone or task is approaching its deadline.

Action Item: {{item_title}}
Type of Item: {{item_type}}
Target Due Date: {{due_date}}
Days Remaining: {{days_remaining}}

Please log in to the system and complete the pending tasks to avoid delay in the SOI timeline.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'item_title', 'item_type', 'due_date', 'days_remaining']),
                'is_active' => true,
            ],
            [
                'name' => 'project_status_change',
                'subject' => 'Project Status Updated: {{project_title}}',
                'body' => 'Hello {{user_name}},

The overall status of your project has been updated.

Project Title: {{project_title}}
Previous Status: {{old_status}}
New Status: {{new_status}}
Updated By: {{changed_by}}
Reason for Change: {{reason}}

Please visit your project details page to review the updated timeline.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'project_title', 'old_status', 'new_status', 'changed_by', 'reason']),
                'is_active' => true,
            ],
            [
                'name' => 'requirement_status_change',
                'subject' => 'SOI Requirement Update: {{project_code}} - {{requirement_name}}',
                'body' => 'Hello {{user_name}},

An update has been made regarding a checklist requirement for your project.

Project Title: {{project_title}}
Project Code: {{project_code}}
Requirement Name: {{requirement_name}}
Document Group: {{requirement_group}}
Current Status: {{new_status}}
Target Due Date: {{due_date}}
Updated By: {{changed_by}}

Instructions / Remarks:
{{remarks}}

Please open the project\'s requirements page to review any requested files or upload the required documents.

Best regards,
NDC Project Management System',
                'variables' => json_encode([
                    'user_name',
                    'project_title',
                    'project_code',
                    'requirement_name',
                    'requirement_group',
                    'new_status',
                    'due_date',
                    'changed_by',
                    'remarks',
                    'request_action',
                    'action_url',
                    'action_label',
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'monitoring_request',
                'subject' => 'Monitoring Compliance Opened: {{project_code}} - {{project_title}}',
                'body' => 'Hello {{user_name}},

NDC has opened the monitoring and compliance period for your project.

Project Title: {{project_title}}
Project Code: {{project_code}}
Compliance Due Date: {{due_date}}

Instructions:
{{instructions}}

Please log in, navigate to the Implementation Monitoring tab, update your indicators, and submit the requested progress files.

Best regards,
NDC Project Management System',
                'variables' => json_encode([
                    'user_name',
                    'project_title',
                    'project_code',
                    'due_date',
                    'instructions',
                    'action_url',
                    'action_label',
                ]),
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')->insertOrIgnore([
                array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            ]);

            if (Schema::hasTable('notification_template_versions')) {
                $stored = DB::table('email_templates')->where('name', $template['name'])->first();
                DB::table('notification_template_versions')->insertOrIgnore([[
                    'email_template_id' => $stored->id,
                    'version' => 1,
                    'status' => 'published',
                    'subject' => $stored->subject,
                    'body' => $stored->body,
                    'variables' => $stored->variables,
                    'published_at' => $stored->updated_at,
                    'created_at' => $stored->created_at,
                    'updated_at' => $stored->updated_at,
                ]]);
            }
        }
    }
}
