<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleIdentityAuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_role_does_not_gain_admin_access_from_its_numeric_id(): void
    {
        // The Legal/Finance migration can create Legal before the canonical role seed runs.
        $role = Role::where('name', 'Legal')->firstOrFail();
        $user = $this->userFor($role, 'identity-legal');
        Sanctum::actingAs($user);
        $this->getJson('/api/divestment-cases')->assertForbidden();
        $this->getJson('/api/notification-event-settings')->assertForbidden();
        $this->getJson('/api/notification-templates')->assertForbidden();
        $this->getJson('/api/notification-deliveries')->assertForbidden();
        $this->postJson('/api/settings/investment-criteria', ['name' => 'Unauthorized criterion'])->assertForbidden();
    }

    public function test_superadmin_uses_role_identity_without_a_fixed_numeric_id(): void
    {
        $role = Role::create(['name' => 'SuperAdmin', 'is_system_role' => true]);
        $user = $this->userFor($role, 'identity-superadmin');
        Sanctum::actingAs($user);
        $this->getJson('/api/divestment-cases')->assertOk();
        $this->getJson('/api/notification-event-settings')->assertOk();
    }

    public function test_explicit_delegated_permission_remains_effective(): void
    {
        $role = Role::where('name', 'Legal')->firstOrFail();
        $permission = Permission::create(['name' => 'system_settings.update', 'resource' => 'system_settings', 'action' => 'update']);
        $role->permissions()->attach($permission->id);
        Sanctum::actingAs($this->userFor($role, 'identity-delegate'));
        $this->getJson('/api/notification-event-settings')->assertOk();
    }

    private function userFor(Role $role, string $username): User
    {
        return User::create([
            'username' => $username, 'email' => $username.'@example.test', 'password_hash' => Hash::make('password'),
            'first_name' => 'Permission', 'last_name' => 'Test', 'default_role_id' => $role->id, 'is_active' => true,
        ]);
    }
}
