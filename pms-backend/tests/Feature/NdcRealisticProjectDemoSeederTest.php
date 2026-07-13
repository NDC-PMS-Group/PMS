<?php

namespace Tests\Feature;

use Database\Seeders\NdcRealisticProjectDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NdcRealisticProjectDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_realistic_portfolio_is_complete_idempotent_and_preserves_account_history(): void
    {
        $this->seed(NdcRealisticProjectDemoSeeder::class);

        $userId = DB::table('users')->where('email', 'sa@gmail.com')->value('id');
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type' => 'account_security',
            'title' => 'Security notice',
            'message' => 'This account-level notification must survive project replacement.',
            'related_entity_type' => 'User',
            'related_entity_id' => $userId,
            'is_read' => false,
            'is_email_sent' => false,
            'created_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'email' => 'sa@gmail.com',
            'entity_type' => 'User',
            'entity_id' => $userId,
            'action' => 'login',
            'description' => 'Account audit history that must be preserved.',
            'created_at' => now(),
        ]);

        $this->seed(NdcRealisticProjectDemoSeeder::class);

        $this->assertDatabaseCount('projects', 10);
        $this->assertDatabaseCount('project_approvals', 10);
        $this->assertDatabaseCount('project_stage_history', 36);
        $this->assertDatabaseCount('project_status_history', 40);
        $this->assertDatabaseCount('task_status_history', 127);
        $this->assertDatabaseCount('project_fund_releases', 4);
        $this->assertDatabaseCount('divestment_cases', 2);
        $this->assertDatabaseCount('divestment_case_transitions', 8);
        $this->assertDatabaseHas('notifications', [
            'type' => 'account_security',
            'related_entity_type' => 'User',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'User',
            'action' => 'login',
        ]);

        $this->assertDatabaseHas('projects', [
            'project_code' => 'NDC-MON-2025-009',
            'origin_track' => 'spg_traditional',
            'lifecycle_phase' => 'post_investment',
        ]);
        $this->assertDatabaseHas('projects', [
            'project_code' => 'NDC-DIV-2025-010',
            'lifecycle_phase' => 'completed',
        ]);
        $this->assertDatabaseHas('divestment_cases', [
            'case_number' => 'EXIT-2025-010',
            'phase' => 'closure',
            'status' => 'closed',
            'actual_proceeds' => 137500000,
        ]);
    }
}
