<?php

namespace Tests\Feature;

use App\Models\InvestmentCriterion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectFinancialTaxonomyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_form_lookups_expose_current_catalog_and_dynamic_criteria(): void
    {
        $user = $this->administrator();
        Sanctum::actingAs($user);

        $this->getJson('/api/lookup/investment-types')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.name', 'Equity')
            ->assertJsonPath('data.1.name', 'Convertible Notes')
            ->assertJsonPath('data.2.name', 'SAFE Notes')
            ->assertJsonPath('data.3.name', 'Bonds')
            ->assertJsonPath('data.4.name', 'Others');

        $fundingSources = $this->getJson('/api/lookup/funding-sources')->assertOk()->json('data');
        $this->assertContains('Others', array_column($fundingSources, 'name'));
        $this->assertNotContains('SVF Pool', array_column($fundingSources, 'name'));

        $criteria = $this->getJson('/api/lookup/investment-criteria')->assertOk()->json('data');
        $this->assertContains('Score Card Target', array_column($criteria, 'name'));
        $this->assertContains('Others', array_column($criteria, 'name'));
    }

    public function test_administrator_can_manage_investment_criteria_catalog(): void
    {
        $administrator = $this->administrator();
        Sanctum::actingAs($administrator);

        $inactive = InvestmentCriterion::create([
            'key' => 'archived_test',
            'name' => 'Archived Test',
            'description' => 'Hidden from new project forms',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $settingsCriteria = $this->getJson('/api/settings/investment-criteria')
            ->assertOk()
            ->json('data');

        $this->assertContains('Archived Test', array_column($settingsCriteria, 'name'));

        $lookupCriteria = $this->getJson('/api/lookup/investment-criteria')
            ->assertOk()
            ->json('data');

        $this->assertNotContains('Archived Test', array_column($lookupCriteria, 'name'));

        $createdId = $this->postJson('/api/settings/investment-criteria', [
            'name' => 'Climate Resilient',
            'description' => 'Supports climate adaptation or resilience outcomes',
            'sort_order' => 120,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.key', 'climate_resilient')
            ->json('data.id');

        $this->putJson("/api/settings/investment-criteria/{$createdId}", [
            'name' => 'Climate Resilience',
            'description' => null,
            'sort_order' => 125,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Climate Resilience')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/settings/investment-criteria/{$inactive->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('investment_criteria', [
            'id' => $inactive->id,
        ]);
    }

    public function test_non_administrator_cannot_manage_investment_criteria_catalog(): void
    {
        $role = Role::create(['name' => 'Viewer', 'is_system_role' => false]);
        $user = User::create([
            'username' => 'criteria-viewer',
            'email' => 'criteria-viewer@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Criteria',
            'last_name' => 'Viewer',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $criterion = InvestmentCriterion::firstOrCreate(
            ['key' => 'viewer_test'],
            ['name' => 'Viewer Test', 'sort_order' => 10, 'is_active' => true]
        );

        $this->getJson('/api/settings/investment-criteria')
            ->assertOk();

        $this->postJson('/api/settings/investment-criteria', [
            'name' => 'Cannot Create',
        ])->assertForbidden();

        $this->putJson("/api/settings/investment-criteria/{$criterion->id}", [
            'name' => 'Cannot Update',
            'description' => null,
            'sort_order' => 10,
            'is_active' => true,
        ])->assertForbidden();

        $this->deleteJson("/api/settings/investment-criteria/{$criterion->id}")
            ->assertForbidden();
    }

    public function test_internal_required_document_can_assign_a_responsible_role(): void
    {
        $administrator = $this->administrator();
        $ownerRole = Role::firstOrCreate(
            ['name' => 'Legal'],
            ['description' => 'Document owner', 'is_system_role' => true]
        );
        Sanctum::actingAs($administrator);

        $this->postJson('/api/access-settings/default-requirements', [
            'track' => 'bdg_investment',
            'group_name' => 'Agreement Documents',
            'item_name' => 'Signed investment agreement',
            'source_document' => 'SOI checklist',
            'owner_type' => 'internal',
            'responsible_role_id' => $ownerRole->id,
            'visibility' => 'internal_only',
            'soi_section' => 'agreement_fund_release',
            'gate_step' => 'fund_release',
            'is_required' => true,
            'svf_only' => false,
            'sort_order' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.responsible_role.id', $ownerRole->id)
            ->assertJsonPath('data.responsible_role.name', 'Legal');

        $this->assertDatabaseHas('default_requirements', [
            'owner_type' => 'internal',
            'responsible_role_id' => $ownerRole->id,
        ]);
    }

    private function administrator(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'SuperAdmin'],
            ['description' => 'System administrator', 'is_system_role' => true]
        );

        foreach (['create', 'update', 'delete'] as $action) {
            $permission = Permission::firstOrCreate(
                ['name' => "system_settings.{$action}"],
                [
                    'resource' => 'system_settings',
                    'action' => $action,
                    'description' => "Manage system settings {$action} actions",
                ]
            );

            $role->permissions()->syncWithoutDetaching($permission->id);
        }

        return User::create([
            'username' => 'taxonomy-admin',
            'email' => 'taxonomy-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Taxonomy',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
