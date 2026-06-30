<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_event_settings')->updateOrInsert(
            ['event_key' => 'soi_step_changed'],
            [
                'label' => 'SOI progress changed',
                'category' => 'Approvals',
                'description' => 'Notify proponents when an SOI project moves to a new internal review step.',
                'in_app_enabled' => true,
                'email_enabled' => true,
                'template_name' => 'soi_step_changed',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('email_templates')->updateOrInsert(
            ['name' => 'approval_request'],
            [
                'subject' => 'Approval Required: {{project_code}} - {{project_title}}',
                'body' => 'Hello {{approver_name}},

A project requires your approval:

Project Code: {{project_code}}
Project: {{project_title}}
Current Step: {{current_step}}
Reviewer Role: {{reviewer_role}}
Submitted / Updated By: {{submitter_name}}

Please review and record your approval action in the SOI Flow.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['approver_name', 'project_code', 'project_title', 'current_step', 'reviewer_role', 'submitter_name', 'action_url', 'action_label']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('email_templates')->updateOrInsert(
            ['name' => 'soi_step_changed'],
            [
                'subject' => 'SOI Progress Update: {{project_code}} - {{project_title}}',
                'body' => 'Hello {{user_name}},

Your project has moved to the next SOI review step.

Project Code: {{project_code}}
Project: {{project_title}}
Current Step: {{current_step}}
Current Reviewer: {{reviewer_role}}
Updated By: {{changed_by}}

You do not need to approve this step. This notice is for tracking your project status.

Best regards,
NDC Project Management System',
                'variables' => json_encode(['user_name', 'project_code', 'project_title', 'current_step', 'reviewer_role', 'changed_by', 'action_url', 'action_label']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('email_templates')->where('name', 'monitoring_request')->update([
            'subject' => 'Monitoring Compliance Opened: {{project_code}} - {{project_title}}',
            'body' => 'Hello {{user_name}},

NDC has opened a monitoring and compliance period for:

Project: {{project_title}}
Project Code: {{project_code}}
Due Date: {{due_date}}

Instructions:
{{instructions}}

Please log in to the system, open the Implementation Monitoring tab, provide the requested indicators, and attach supporting evidence under the applicable requirements.

Best regards,
NDC Project Management System',
            'variables' => json_encode(['user_name', 'project_title', 'project_code', 'due_date', 'instructions', 'action_url', 'action_label']),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_event_settings')->where('event_key', 'soi_step_changed')->delete();
        DB::table('email_templates')->where('name', 'soi_step_changed')->delete();
    }
};
