<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectAgreementFormResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\ProjectAgreementForm;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectAgreementFormController extends Controller
{
    public function show(Request $request, Project $project)
    {
        if (!$this->canViewProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to view this project agreement form.'], 403);
        }

        $form = $project->agreementForm()
            ->with(['document.uploadedBy', 'document.submittedBy', 'preparedBy', 'submittedBy', 'returnedBy'])
            ->first();
        $context = $this->agreementContext($request->user(), $project, $form);

        return response()->json([
            'data' => $form ? new ProjectAgreementFormResource($form) : null,
            'context' => $context,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        if (!$this->canSubmitForm($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to save this project agreement form.'], 403);
        }

        $existing = $project->agreementForm()->first();
        if ($existing?->status === ProjectAgreementForm::STATUS_SUBMITTED) {
            return response()->json([
                'message' => 'Submitted agreement forms must be returned by Legal before they can be edited.',
            ], 409);
        }

        $payload = $this->validateDraftPayload($request);
        $form = $this->upsertForm($project, $request->user(), $payload, ProjectAgreementForm::STATUS_DRAFT);

        return response()->json([
            'message' => 'Draft agreement form saved.',
            'data' => new ProjectAgreementFormResource($form->load([
                'document.uploadedBy',
                'document.submittedBy',
                'preparedBy',
                'submittedBy',
                'returnedBy',
            ])),
            'context' => $this->agreementContext($request->user(), $project, $form),
        ]);
    }

    public function submit(Request $request, Project $project)
    {
        if (!$this->canSubmitForm($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to submit this project agreement form.'], 403);
        }

        $payload = $this->validateSubmitPayload($request);

        $form = DB::transaction(function () use ($project, $request, $payload) {
            $form = $this->upsertForm($project, $request->user(), $payload, ProjectAgreementForm::STATUS_SUBMITTED);
            $document = $this->createReferenceDocument($project, $form, $request->user());

            $form->forceFill([
                'document_id' => $document->id,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'returned_by' => null,
                'returned_at' => null,
                'return_reason' => null,
            ])->save();

            return $form;
        });

        return response()->json([
            'message' => 'Draft agreement form submitted for Legal reference.',
            'data' => new ProjectAgreementFormResource($form->load([
                'document.uploadedBy',
                'document.submittedBy',
                'preparedBy',
                'submittedBy',
                'returnedBy',
            ])),
            'context' => $this->agreementContext($request->user(), $project, $form),
        ]);
    }

    public function return(Request $request, Project $project)
    {
        if (!$this->canReturnForm($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to return this project agreement form.'], 403);
        }

        $payload = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $form = $project->agreementForm()->first();
        if (!$form) {
            return response()->json(['message' => 'No agreement form has been submitted for this project.'], 404);
        }

        $form->forceFill([
            'status' => ProjectAgreementForm::STATUS_RETURNED,
            'return_reason' => $payload['reason'],
            'returned_by' => $request->user()->id,
            'returned_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Draft agreement form returned for correction.',
            'data' => new ProjectAgreementFormResource($form->load([
                'document.uploadedBy',
                'document.submittedBy',
                'preparedBy',
                'submittedBy',
                'returnedBy',
            ])),
            'context' => $this->agreementContext($request->user(), $project, $form),
        ]);
    }

    public function downloadReferencePdf(Request $request, Project $project)
    {
        if (!$this->canViewProject($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to download this project agreement reference.'], 403);
        }

        $form = $project->agreementForm()
            ->with(['preparedBy', 'submittedBy', 'returnedBy'])
            ->first();

        if (!$form) {
            return response()->json(['message' => 'No agreement form has been submitted for this project.'], 404);
        }

        return $this->referencePdf($project, $form)
            ->download($this->referenceFileName($project));
    }

    private function validateDraftPayload(Request $request): array
    {
        $payload = $request->validate([
            'agreement_type' => ['nullable', 'string', 'max:255'],
            'term_sheet' => ['nullable', 'string', 'max:20000'],
            'parties' => ['nullable', 'array'],
            'parties.*.label' => ['nullable', 'string', 'max:80'],
            'parties.*.company_name' => ['nullable', 'string', 'max:255'],
            'parties.*.office_address' => ['nullable', 'string', 'max:1000'],
            'parties.*.authorized_signatory' => ['nullable', 'string', 'max:255'],
            'parties.*.position' => ['nullable', 'string', 'max:255'],
            'parties.*.ctc_passport_id' => ['nullable', 'string', 'max:120'],
            'parties.*.issue_date_place' => ['nullable', 'string', 'max:255'],
        ]);

        $payload['parties'] = $this->normalizeParties($payload['parties'] ?? []);

        return $payload;
    }

    private function validateSubmitPayload(Request $request): array
    {
        $payload = $request->validate([
            'agreement_type' => ['required', 'string', 'max:255'],
            'term_sheet' => ['required', 'string', 'min:10', 'max:20000'],
            'parties' => ['required', 'array', 'min:2'],
            'parties.*.label' => ['nullable', 'string', 'max:80'],
            'parties.*.company_name' => ['required', 'string', 'max:255'],
            'parties.*.office_address' => ['required', 'string', 'max:1000'],
            'parties.*.authorized_signatory' => ['required', 'string', 'max:255'],
            'parties.*.position' => ['nullable', 'string', 'max:255'],
            'parties.*.ctc_passport_id' => ['required', 'string', 'max:120'],
            'parties.*.issue_date_place' => ['required', 'string', 'max:255'],
        ]);

        $payload['parties'] = $this->normalizeParties($payload['parties'] ?? []);

        if (count($payload['parties']) < 2) {
            throw ValidationException::withMessages([
                'parties' => 'At least two parties are required.',
            ]);
        }

        foreach ($payload['parties'] as $index => $party) {
            if ($index > 0 && trim((string) ($party['position'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "parties.$index.position" => 'Position is required for this party.',
                ]);
            }
        }

        return $payload;
    }

    private function upsertForm(Project $project, User $user, array $payload, string $status): ProjectAgreementForm
    {
        $approval = $project->approvals()->whereNotNull('current_step_id')->latest('id')->first();

        return ProjectAgreementForm::updateOrCreate(
            ['project_id' => $project->id],
            [
                'project_approval_id' => $approval?->id,
                'approval_step_id' => $approval?->current_step_id,
                'agreement_type' => $payload['agreement_type'] ?? null,
                'parties' => $payload['parties'] ?? [],
                'term_sheet' => $payload['term_sheet'] ?? null,
                'status' => $status,
                'prepared_by' => $user->id,
            ]
        );
    }

    private function normalizeParties(array $parties): array
    {
        return collect($parties)
            ->map(function (array $party, int $index) {
                return [
                    'label' => trim((string) ($party['label'] ?? $this->partyLabel($index))),
                    'company_name' => trim((string) ($party['company_name'] ?? '')),
                    'office_address' => trim((string) ($party['office_address'] ?? '')),
                    'authorized_signatory' => trim((string) ($party['authorized_signatory'] ?? '')),
                    'position' => trim((string) ($party['position'] ?? '')),
                    'ctc_passport_id' => trim((string) ($party['ctc_passport_id'] ?? '')),
                    'issue_date_place' => trim((string) ($party['issue_date_place'] ?? '')),
                ];
            })
            ->filter(fn (array $party) => collect($party)->except('label')->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty())
            ->values()
            ->all();
    }

    private function partyLabel(int $index): string
    {
        return match ($index) {
            0 => '1st Party',
            1 => '2nd Party',
            2 => '3rd Party',
            default => ($index + 1) . 'th Party',
        };
    }

    private function createReferenceDocument(Project $project, ProjectAgreementForm $form, User $user): Document
    {
        $fileName = $this->referenceFileName($project, now()->format('YmdHis'));
        $path = "documents/{$fileName}";
        $content = $this->referencePdf($project, $form)->output();

        Storage::disk('public')->put($path, $content);

        $document = Document::create([
            'project_id' => $project->id,
            'title' => 'Draft Agreement Form - ' . ($project->project_code ?: $project->id),
            'description' => 'Structured Draft Agreement Form submitted before Legal agreement drafting.',
            'file_name' => $fileName,
            'file_path' => $path,
            'file_size' => strlen($content),
            'file_type' => 'application/pdf',
            'category' => 'Draft Agreement Reference',
            'version' => 1,
            'is_public' => false,
            'requires_approval' => false,
            'submission_status' => 'submitted',
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'is_deleted' => false,
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_name' => $fileName,
            'file_path' => $path,
            'file_size' => strlen($content),
            'change_description' => 'Generated from submitted Draft Agreement Form.',
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        return $document;
    }

    private function referencePdf(Project $project, ProjectAgreementForm $form)
    {
        return Pdf::loadView('agreement.reference-pdf', [
            'project' => $project,
            'form' => $form,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');
    }

    private function referenceFileName(Project $project, ?string $timestamp = null): string
    {
        $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($project->project_code ?: 'project-' . $project->id));
        $suffix = $timestamp ? "-{$timestamp}" : '';

        return "draft-agreement-form-{$code}{$suffix}.pdf";
    }

    private function canViewProject(?User $user, Project $project): bool
    {
        if (!$user) return false;
        if ((int) $project->created_by === (int) $user->id) return true;
        if ((string) $project->proponent_email !== '' && strtolower((string) $project->proponent_email) === strtolower((string) $user->email)) return true;
        if ($this->isSuperAdmin($user) || $this->hasAnyPermission($user, ['projects.view', 'project.view', 'view_project'])) return true;
        if (in_array((int) $user->id, [(int) $project->project_officer_id, (int) $project->workgroup_head_id], true)) return true;

        return $project->members()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->where('can_view', true)
            ->exists();
    }

    private function canEditProject(?User $user, Project $project): bool
    {
        if (!$user) return false;
        if ((int) $project->created_by === (int) $user->id) return true;
        if ($this->isSuperAdmin($user) || $this->hasAnyPermission($user, ['projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project'])) return true;

        return $project->members()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->where('can_edit', true)
            ->exists();
    }

    private function canSubmitForm(?User $user, Project $project): bool
    {
        if (!$user) return false;
        if ($this->isSuperAdmin($user)) return true;

        $approval = $this->currentApproval($project);
        $currentStep = $approval?->currentStep;
        if (!$currentStep || !$this->agreementFormRelevant($project, $approval)) {
            return false;
        }

        if (in_array((int) $user->id, [(int) $project->project_officer_id, (int) $project->workgroup_head_id], true)) return true;

        $currentRoleName = strtolower((string) ($currentStep->role?->name ?? ''));
        if ($currentRoleName === 'legal') {
            return false;
        }

        if ((int) $user->default_role_id === (int) $currentStep->role_id) {
            return true;
        }

        return $project->members()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->where('role_id', $currentStep->role_id)
            ->exists();
    }

    private function canReturnForm(?User $user, Project $project): bool
    {
        if (!$user) return false;
        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));

        return $this->isSuperAdmin($user)
            || $roleName === 'legal';
    }

    private function agreementContext(?User $user, Project $project, ?ProjectAgreementForm $form): array
    {
        $approval = $this->currentApproval($project);
        $currentStep = $approval?->currentStep;
        $nextStep = $this->nextStep($approval);
        $gateStep = $this->agreementGateStep($nextStep) ?: $this->agreementGateStep($currentStep);
        $isSubmitted = $form?->status === ProjectAgreementForm::STATUS_SUBMITTED;
        $isReturned = $form?->status === ProjectAgreementForm::STATUS_RETURNED;
        $canSubmit = $this->canSubmitForm($user, $project) && !$isSubmitted;
        $canReturn = $isSubmitted && $this->canReturnForm($user, $project);
        $roleName = strtolower((string) ($user?->defaultRole?->name ?? ''));
        $isLegalUser = $roleName === 'legal';

        return [
            'show_panel' => (bool) ($form || $gateStep || $isLegalUser),
            'can_submit' => $canSubmit,
            'can_return' => $canReturn,
            'read_only' => !$canSubmit || ($isSubmitted && $isLegalUser),
            'is_legal_user' => $isLegalUser,
            'requires_before_legal' => (bool) ($this->agreementGateStep($nextStep) && !$isSubmitted),
            'current_step' => $currentStep ? [
                'id' => $currentStep->id,
                'step_name' => $currentStep->step_name,
                'role_id' => $currentStep->role_id,
                'role_name' => $currentStep->role?->name,
            ] : null,
            'gate_step' => $gateStep ? [
                'id' => $gateStep->id,
                'step_name' => $gateStep->step_name,
                'role_id' => $gateStep->role_id,
                'role_name' => $gateStep->role?->name,
            ] : null,
            'status_message' => $isSubmitted
                ? 'Submitted for Legal reference.'
                : ($isReturned ? 'Returned by Legal. Correct and resubmit before routing to Legal.' : 'Required before routing this project to Legal.'),
        ];
    }

    private function currentApproval(Project $project)
    {
        return $project->approvals()
            ->with(['workflow.steps.role', 'currentStep.role'])
            ->whereNotNull('current_step_id')
            ->latest('id')
            ->first();
    }

    private function nextStep($approval)
    {
        if (!$approval?->workflow || !$approval?->currentStep) {
            return null;
        }

        return $approval->workflow->steps
            ->where('step_order', '>', $approval->currentStep->step_order)
            ->sortBy('step_order')
            ->first();
    }

    private function agreementFormRelevant(Project $project, $approval): bool
    {
        if ($project->agreementForm()->exists()) {
            return true;
        }

        return (bool) ($this->agreementGateStep($this->nextStep($approval)) || $this->agreementGateStep($approval?->currentStep));
    }

    private function agreementGateStep($step)
    {
        if (!$step || !(bool) ($step->requires_agreement_form ?? false)) {
            return null;
        }

        return $step;
    }

    private function isSuperAdmin(?User $user): bool
    {
        $roleName = strtolower((string) ($user?->defaultRole?->name ?? ''));

        return $user && ((int) $user->default_role_id === 1 || $roleName === 'superadmin');
    }

    private function hasAnyPermission(User $user, array $permissionNames): bool
    {
        return $user->defaultRole()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', $permissionNames))
            ->exists();
    }
}
