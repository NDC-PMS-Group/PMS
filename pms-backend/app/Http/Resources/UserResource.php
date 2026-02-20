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

            // Profile
            'profile_photo'     => $this->profile_photo,

            // Employment
            'employee_id'       => $this->employee_id,
            'department'        => $this->department,
            'position'          => $this->position,
            'date_hired'        => $this->date_hired?->toDateString(),
            'birth_date'        => $this->birth_date?->toDateString(),

            // Access
            'role'              => new RoleResource($this->whenLoaded('defaultRole')),
            'is_active'         => $this->is_active,

            // Timestamps
            'last_login'        => $this->last_login?->toDateTimeString(),
            'created_at'        => $this->created_at->toDateTimeString(),
        ];
    }
}