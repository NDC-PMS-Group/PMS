<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowCatalogManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_and_rename_a_workflow_without_changing_its_key(): void
    {
        $this->seed(ApprovalWorkflowSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'SuperAdmin'],
            ['description' => 'Administrator', 'is_system_role' => true]
        );
        $user = User::create([
            'username' => 'workflow-admin',
            'email' => 'workflow-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Workflow',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/access-settings/workflows', [
            'display_name' => 'Special Development Route',
            'description' => 'A configurable route for exceptional projects.',
            'workflow_group' => 'origin',
            'audiences' => ['internal'],
        ])->assertCreated();

        $workflowId = $created->json('data.id');
        $workflowKey = $created->json('data.workflow_key');

        $this->getJson('/api/project-workflow-catalog')
            ->assertOk()
            ->assertJsonMissing(['key' => $workflowKey]);

        $this->putJson("/api/access-settings/workflows/{$workflowId}", [
            'display_name' => 'Special Projects Route',
            'description' => 'Renamed without changing existing project references.',
            'is_active' => false,
            'audiences' => ['internal', 'proponent'],
        ])->assertOk()
            ->assertJsonPath('data.workflow_key', $workflowKey)
            ->assertJsonPath('data.display_name', 'Special Projects Route');

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $workflowId,
            'workflow_key' => $workflowKey,
            'display_name' => 'Special Projects Route',
        ]);
    }

    public function test_lifecycle_workflows_remain_separate_from_project_origins(): void
    {
        $this->seed(ApprovalWorkflowSeeder::class);
        $role = Role::query()->where('name', 'SuperAdmin')->first()
            ?: Role::create(['name' => 'SuperAdmin', 'description' => 'Administrator', 'is_system_role' => true]);
        Sanctum::actingAs(User::create([
            'username' => 'catalog-admin',
            'email' => 'catalog-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Catalog',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]));

        $catalog = $this->getJson('/api/project-workflow-catalog')->assertOk();

        $originKeys = collect($catalog->json('data.origins'))->pluck('key');
        $lifecycleKeys = collect($catalog->json('data.lifecycle_workflows'))->pluck('key');

        $this->assertFalse($originKeys->contains('implementation_monitoring'));
        $this->assertFalse($originKeys->contains('divestment'));
        $this->assertTrue($lifecycleKeys->contains('implementation_monitoring'));
        $this->assertTrue($lifecycleKeys->contains('divestment'));
    }
}
