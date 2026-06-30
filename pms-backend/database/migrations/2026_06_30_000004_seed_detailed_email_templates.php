<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'name' => 'account_registered',
                'subject' => 'New Proponent Registered: {{user_name}}',
                'body' => "Hello Administrator,

A new proponent has registered on the NDC Project Management System and is waiting for your account review and approval.

Registrant Name: {{user_name}}
Organization: {{organization_name}}
Email Address: {{email}}
Designation: {{position}}
Registration Date: {{created_at}}

Please log in to the NDC PMS admin panel to review their uploaded registration and representative authorization documents and approve or decline the account.",
                'variables' => json_encode(['user_name', 'organization_name', 'email', 'position', 'created_at']),
                'is_active' => true,
            ],
            [
                'name' => 'account_approved',
                'subject' => 'NDC PMS Account Approved: Welcome to NDC PMS',
                'body' => "Hello {{user_name}},

We are pleased to inform you that your registration request has been approved by the NDC administration. Your account is now active.

User Account: {{username}}
Organization: {{organization_name}}
Status: Active

You can now log in to the NDC Project Management System using your credentials, submit project proposals, upload draft documents, and collaborate on your project workflows.",
                'variables' => json_encode(['user_name', 'username', 'organization_name']),
                'is_active' => true,
            ],
            [
                'name' => 'account_rejected',
                'subject' => 'NDC PMS Account Registration Declined',
                'body' => "Hello {{user_name}},

We regret to inform you that your registration request on the NDC Project Management System has been declined.

Registrant Name: {{user_name}}
Organization: {{organization_name}}
Reason: {{remarks}}

If you believe this was in error or if you wish to provide additional documentation, please contact the NDC support team.",
                'variables' => json_encode(['user_name', 'organization_name', 'remarks']),
                'is_active' => true,
            ],
            [
                'name' => 'project_member_added',
                'subject' => 'You have been added to Project: {{project_title}}',
                'body' => "Hello {{user_name}},

You have been added as a project team member/stakeholder for the following project:

Project Title: {{project_title}}
Project Code: {{project_code}}
Your Role: {{member_role}}
Added By: {{changed_by}}

Please log in to the NDC PMS to view the project details, access task boards, review checklists, and participate in the workflow.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'member_role', 'changed_by']),
                'is_active' => true,
            ],
            [
                'name' => 'approval_result',
                'subject' => 'SOI Step Reviewed: {{project_code}} - {{project_title}}',
                'body' => "Hello {{user_name}},

An approval review decision has been recorded for your project's current Standard Operating Instructions (SOI) workflow stage.

Project Title: {{project_title}}
Project Code: {{project_code}}
Reviewed Step: {{current_step}}
Decision: {{decision}}
Reviewed By: {{changed_by}}
Remarks / Feedback: {{remarks}}

Please log in to the NDC PMS to check the workflow timeline and any required actions.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'current_step', 'decision', 'changed_by', 'remarks']),
                'is_active' => true,
            ],
            [
                'name' => 'project_returned',
                'subject' => 'Proposal Returned for Revision: {{project_code}} - {{project_title}}',
                'body' => "Hello {{user_name}},

Your project proposal has been reviewed and returned to your queue for required revisions.

Project Title: {{project_title}}
Project Code: {{project_code}}
Returned By: {{changed_by}}
Revision Details: {{remarks}}

Please log in to the system, review the requested modifications, update the necessary files, and resubmit the proposal for evaluation.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'changed_by', 'remarks']),
                'is_active' => true,
            ],
            [
                'name' => 'document_submitted',
                'subject' => 'Document Submitted for Review: {{project_code}} - {{document_title}}',
                'body' => "Hello {{user_name}},

A draft document has been formally submitted and is ready for your evaluation.

Project Title: {{project_title}}
Project Code: {{project_code}}
Document Title: {{document_title}}
Submitted By: {{changed_by}}
Submission Date: {{submitted_at}}

Please log in to the NDC PMS to inspect the submitted files and record your review evaluation.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'document_title', 'changed_by', 'submitted_at']),
                'is_active' => true,
            ],
            [
                'name' => 'document_update_requested',
                'subject' => 'Revision Requested for Document: {{document_title}}',
                'body' => "Hello {{user_name}},

Your submitted document has been reviewed and requires updates before it can be accepted.

Project Title: {{project_title}}
Project Code: {{project_code}}
Document Title: {{document_title}}
Requested By: {{changed_by}}
Required Changes: {{remarks}}

Please upload the updated draft document in the system and submit it again for evaluation.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'document_title', 'changed_by', 'remarks']),
                'is_active' => true,
            ],
            [
                'name' => 'monitoring_submitted',
                'subject' => 'Monitoring Report Submitted: {{project_code}} - {{project_title}}',
                'body' => "Hello {{user_name}},

A compliance monitoring progress report has been submitted by the proponent.

Project Title: {{project_title}}
Project Code: {{project_code}}
Submitted By: {{changed_by}}
Submission Date: {{submitted_at}}

Please log in to the system, navigate to the Implementation Monitoring section, and evaluate the progress indicators and files.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'changed_by', 'submitted_at']),
                'is_active' => true,
            ],
            [
                'name' => 'monitoring_returned',
                'subject' => 'Monitoring Report Returned: {{project_code}} - {{project_title}}',
                'body' => "Hello {{user_name}},

Your submitted compliance monitoring report has been reviewed and returned for corrections.

Project Title: {{project_title}}
Project Code: {{project_code}}
Returned By: {{changed_by}}
Required Corrections: {{remarks}}

Please log in to the NDC PMS, update the monitoring indicators, and resubmit the progress details.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'changed_by', 'remarks']),
                'is_active' => true,
            ],
            [
                'name' => 'monitoring_accepted',
                'subject' => 'Monitoring Compliance Report Accepted: {{project_code}} - {{project_title}}',
                'body' => "Hello {{user_name}},

Great news! NDC has accepted your compliance progress report for the current monitoring period.

Project Title: {{project_title}}
Project Code: {{project_code}}
Accepted By: {{changed_by}}
Acceptance Date: {{accepted_at}}
NDC Remarks: {{remarks}}

Thank you for your compliance reporting.",
                'variables' => json_encode(['user_name', 'project_title', 'project_code', 'changed_by', 'accepted_at', 'remarks']),
                'is_active' => true,
            ],
        ];

        // 1. Seed the email templates
        foreach ($templates as $template) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $template['name']],
                array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // 2. Link events to the templates
        $eventLinks = [
            'account_registered' => 'account_registered',
            'account_approved' => 'account_approved',
            'account_rejected' => 'account_rejected',
            'project_member_added' => 'project_member_added',
            'approval_result' => 'approval_result',
            'project_returned' => 'project_returned',
            'document_submitted' => 'document_submitted',
            'document_update_requested' => 'document_update_requested',
            'monitoring_submitted' => 'monitoring_submitted',
            'monitoring_returned' => 'monitoring_returned',
            'monitoring_accepted' => 'monitoring_accepted',
        ];

        foreach ($eventLinks as $event => $template) {
            DB::table('notification_event_settings')
                ->where('event_key', $event)
                ->update(['template_name' => $template, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $templateNames = [
            'account_registered',
            'account_approved',
            'account_rejected',
            'project_member_added',
            'approval_result',
            'project_returned',
            'document_submitted',
            'document_update_requested',
            'monitoring_submitted',
            'monitoring_returned',
            'monitoring_accepted',
        ];

        DB::table('email_templates')->whereIn('name', $templateNames)->delete();

        DB::table('notification_event_settings')
            ->whereIn('event_key', $templateNames)
            ->update(['template_name' => null, 'updated_at' => now()]);
    }
};
