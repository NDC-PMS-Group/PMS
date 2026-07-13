<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\NotificationTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationTemplateService
{
    public function __construct(private readonly NotificationVariableRegistry $variables) {}

    public function saveDraft(EmailTemplate $template, array $data, User $actor): NotificationTemplateVersion
    {
        $this->validateContent($data['subject'], $data['body']);
        $usedVariables = $this->variables->extract($data['subject'], $data['body']);

        return DB::transaction(function () use ($template, $data, $actor, $usedVariables) {
            $draft = $template->versions()->where('status', 'draft')->lockForUpdate()->first();
            if (! $draft) {
                $draft = new NotificationTemplateVersion([
                    'version' => ((int) $template->versions()->max('version')) + 1,
                    'status' => 'draft',
                    'created_by' => $actor->id,
                ]);
                $draft->template()->associate($template);
            }

            $draft->fill([
                'subject' => trim($data['subject']),
                'body' => trim($data['body']),
                'variables' => $usedVariables,
            ])->save();

            return $draft->fresh(['author']);
        });
    }

    public function preview(EmailTemplate $template, array $data): array
    {
        $subject = trim((string) ($data['subject'] ?? $template->draftVersion?->subject ?? $template->subject));
        $body = trim((string) ($data['body'] ?? $template->draftVersion?->body ?? $template->body));
        $this->validateContent($subject, $body);
        $keys = $this->variables->extract($subject, $body);
        $sample = $this->variables->sampleData($keys, $data['sample_data'] ?? []);

        return $this->render($subject, $body, $sample) + ['variables' => $keys];
    }

    public function publish(EmailTemplate $template, User $actor): NotificationTemplateVersion
    {
        return DB::transaction(function () use ($template, $actor) {
            $draft = $template->versions()->where('status', 'draft')->lockForUpdate()->first();
            if (! $draft) {
                throw ValidationException::withMessages(['template' => 'Save a draft before publishing.']);
            }

            $this->validateContent($draft->subject, $draft->body);
            $variables = $this->variables->extract($draft->subject, $draft->body);
            $draft->update([
                'status' => 'published',
                'variables' => $variables,
                'published_by' => $actor->id,
                'published_at' => now(),
            ]);

            // Keep existing name-based notification mappings and callers authoritative.
            $template->update([
                'subject' => $draft->subject,
                'body' => $draft->body,
                'variables' => $variables,
                'is_active' => true,
            ]);

            return $draft->fresh(['author', 'publisher']);
        });
    }

    public function restore(EmailTemplate $template, NotificationTemplateVersion $version, User $actor): NotificationTemplateVersion
    {
        abort_unless($version->email_template_id === $template->id, 404);

        return DB::transaction(function () use ($template, $version, $actor) {
            $template->versions()->where('status', 'draft')->delete();

            return $template->versions()->create([
                'version' => ((int) $template->versions()->max('version')) + 1,
                'status' => 'draft',
                'subject' => $version->subject,
                'body' => $version->body,
                'variables' => $version->variables,
                'created_by' => $actor->id,
                'restored_from_id' => $version->id,
            ])->fresh(['author']);
        });
    }

    public function render(string $subject, string $body, array $data): array
    {
        foreach ($data as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', (string) $value, $subject);
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    private function validateContent(string $subject, string $body): void
    {
        $unknown = array_values(array_diff($this->variables->extract($subject, $body), $this->variables->keys()));
        $errors = [];

        if ($unknown !== []) {
            $errors['variables'] = 'Unknown variables: '.implode(', ', $unknown).'.';
        }
        if (preg_match('/<\/?[a-z][^>]*>/i', $subject."\n".$body)) {
            $errors['body'] = 'Templates must use plain text; HTML tags are not allowed.';
        }
        $withoutValidTokens = preg_replace('/\{\{\s*[a-z][a-z0-9_]*\s*\}\}/i', '', $subject."\n".$body);
        if (str_contains($withoutValidTokens, '{{') || str_contains($withoutValidTokens, '}}')) {
            $errors['variables'] = 'One or more template variables are malformed.';
        }
        if (str_contains($subject, "\n")) {
            $errors['subject'] = 'The subject must be a single line.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
