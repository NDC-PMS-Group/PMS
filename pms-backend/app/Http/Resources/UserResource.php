<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Identity
            'id'                => $this->id,
            'username'          => $this->username,
            'email'             => $this->email,

            // Name
            'first_name'        => $this->first_name,
            'middle_name'       => $this->middle_name,
            'last_name'         => $this->last_name,
            'suffix'            => $this->suffix,
            'full_name'         => $this->full_name,
            'initials'          => $this->initials,

            // Contact & Address
            'phone_number'      => $this->phone_number,
            'address'           => $this->address,
            'organization_name'  => $this->organization_name,
            'organization_type'  => $this->organization_type,
            'organization_registration_no' => $this->organization_registration_no,
            'proponent_profile' => $this->proponent_profile ?? [],
            'registration_documents' => $this->whenLoaded('registrationDocuments', fn () => $this->registrationDocuments->map(fn ($document) => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'title' => $document->title,
                'file_name' => $document->file_name,
                'file_size' => $document->file_size,
                'file_type' => $document->file_type,
                'review_status' => $document->review_status,
                'review_remarks' => $document->review_remarks,
                'uploaded_at' => $document->uploaded_at?->toDateTimeString(),
            ])->values()),
            'previous_projects' => $this->whenLoaded('previousProjects', fn () => $this->previousProjects->map(fn ($project) => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'client_partner' => $project->client_partner,
                'project_value' => $project->project_value,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'status' => $project->status,
            ])->values()),
            'received_invitations' => $this->whenLoaded('receivedInvitations', fn () => $this->receivedInvitations->map(fn ($invite) => [
                'id' => $invite->id,
                'project_id' => $invite->project_id,
                'project' => $invite->project ? [
                    'id' => $invite->project->id,
                    'project_code' => $invite->project->project_code,
                    'title' => $invite->project->title
                ] : null,
                'email' => $invite->email,
                'assignment_type' => $invite->assignment_type,
                'can_view' => $invite->can_view,
                'can_edit' => $invite->can_edit,
                'status' => $invite->status,
                'invited_by' => $invite->invitedBy ? [
                    'id' => $invite->invitedBy->id,
                    'full_name' => $invite->invitedBy->full_name
                ] : null,
                'created_at' => $invite->created_at?->toDateTimeString(),
            ])->values()),

            // Profile
            'profile_photo_url'     => $this->profile_photo_url,

            // Employment
            'employee_id'       => $this->employee_id,
            'department'        => $this->department,
            'position'          => $this->position,
            'date_hired'        => $this->date_hired?->toDateString(),
            'birth_date'        => $this->birth_date?->toDateString(),

            // Access
            'role'              => new RoleResource($this->whenLoaded('defaultRole')),
            'is_active'         => $this->is_active,
            'staff_invitation_expires_at' => $this->staff_invitation_expires_at?->toDateTimeString(),
            'staff_invitation_accepted_at' => $this->staff_invitation_accepted_at?->toDateTimeString(),
            'invited_by' => new UserResource($this->whenLoaded('invitedBy')),

            // Timestamps
            'last_login'        => $this->last_login?->toDateTimeString(),
            'created_at'        => $this->created_at->toDateTimeString(),
        ];
    }
}
