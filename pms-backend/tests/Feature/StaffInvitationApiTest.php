<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $staffRole;
    private Role $superadminRole;
    private Role $proponentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadminRole = Role::create(['name' => 'superadmin', 'is_system_role' => true]);
        $adminRole = Role::create(['name' => 'Admin', 'is_system_role' => true]);
        $this->staffRole = Role::create(['name' => 'Staff', 'is_system_role' => true]);
        $this->proponentRole = Role::create(['name' => 'Proponent', 'is_system_role' => true]);

        $permission = Permission::create([
            'name' => 'organization.create',
            'resource' => 'organization',
            'action' => 'create',
        ]);
        $adminRole->permissions()->attach($permission);

        $this->admin = User::create([
            'username' => 'people-admin',
            'email' => 'people-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'People',
            'last_name' => 'Admin',
            'default_role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_authorized_admin_can_invite_internal_staff_with_required_context(): void
    {
        $response = $this->postJson('/api/users/invite-staff', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('user.email', 'new.staff@ndc.gov.ph')
            ->assertJsonPath('user.department', 'Project Development')
            ->assertJsonPath('user.position', 'Project Analyst');

        $this->assertDatabaseHas('users', [
            'email' => 'new.staff@ndc.gov.ph',
            'default_role_id' => $this->staffRole->id,
            'is_active' => false,
            'invited_by_id' => $this->admin->id,
        ]);
    }

    public function test_department_and_position_are_required_for_staff_invites(): void
    {
        $payload = $this->payload();
        unset($payload['department'], $payload['position']);

        $this->postJson('/api/users/invite-staff', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department', 'position']);
    }

    public function test_public_proponent_role_cannot_be_assigned_by_staff_invitation(): void
    {
        $this->postJson('/api/users/invite-staff', [
            ...$this->payload(),
            'default_role_id' => $this->proponentRole->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Use public proponent registration for external proponents.');
    }

    public function test_non_superadmin_cannot_invite_a_superadmin(): void
    {
        $this->postJson('/api/users/invite-staff', [
            ...$this->payload(),
            'default_role_id' => $this->superadminRole->id,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Only a superadmin may invite another superadmin.');
    }

    private function payload(): array
    {
        return [
            'email' => 'new.staff@ndc.gov.ph',
            'first_name' => 'New',
            'last_name' => 'Staff',
            'default_role_id' => $this->staffRole->id,
            'department' => 'Project Development',
            'position' => 'Project Analyst',
        ];
    }
}
