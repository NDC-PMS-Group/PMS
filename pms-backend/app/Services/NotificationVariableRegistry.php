<?php

namespace App\Services;

class NotificationVariableRegistry
{
    private const VARIABLES = [
        'user_name' => 'Recipient display name',
        'recipient_name' => 'Recipient display name',
        'approver_name' => 'Approver display name',
        'username' => 'User account name',
        'email' => 'User email address',
        'organization_name' => 'Organization name',
        'position' => 'Position or designation',
        'created_at' => 'Record creation date and time',
        'project_title' => 'Project title',
        'project_name' => 'Project title',
        'project_code' => 'Project reference code',
        'proponent_name' => 'Project proponent name',
        'current_step' => 'Current workflow step',
        'stage_name' => 'Current workflow stage',
        'reviewer_role' => 'Assigned reviewer role',
        'submitter_name' => 'Submitting user name',
        'submitted_by' => 'Submitting user name',
        'changed_by' => 'User who made the change',
        'old_status' => 'Previous status',
        'new_status' => 'New status',
        'decision' => 'Recorded decision',
        'reason' => 'Reason for the change',
        'remarks' => 'Review remarks',
        'task_title' => 'Task title',
        'task_description' => 'Task description',
        'priority' => 'Task priority',
        'item_title' => 'Reminder item title',
        'item_type' => 'Reminder item type',
        'days_remaining' => 'Days until due date',
        'due_date' => 'Target due date',
        'document_title' => 'Document title',
        'document_name' => 'Document title',
        'file_name' => 'Uploaded file name',
        'requirement_name' => 'Requirement name',
        'requirement_group' => 'Requirement group',
        'request_action' => 'Requested requirement action',
        'instructions' => 'Monitoring instructions',
        'submitted_at' => 'Submission date and time',
        'accepted_at' => 'Acceptance date and time',
        'member_role' => 'Project member role',
        'action_url' => 'Destination URL for the email action',
        'action_label' => 'Label for the email action',
    ];

    public function all(): array
    {
        return collect(self::VARIABLES)
            ->map(fn (string $description, string $key) => [
                'key' => $key,
                'token' => '{{'.$key.'}}',
                'description' => $description,
            ])
            ->values()
            ->all();
    }

    public function keys(): array
    {
        return array_keys(self::VARIABLES);
    }

    public function extract(string ...$content): array
    {
        preg_match_all('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i', implode("\n", $content), $matches);

        return collect($matches[1] ?? [])->map('strtolower')->unique()->sort()->values()->all();
    }

    public function sampleData(array $keys, array $provided = []): array
    {
        return collect($keys)->mapWithKeys(function (string $key) use ($provided) {
            return [$key => array_key_exists($key, $provided) ? (string) $provided[$key] : '['.str_replace('_', ' ', $key).']'];
        })->all();
    }
}
