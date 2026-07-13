<?php

namespace Tests\Feature;

use App\Models\DivestmentCase;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DivestmentCaseApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'id' => 1,
            'name' => 'superadmin',
            'description' => 'System administrator',
            'is_system_role' => true,
        ]);

        $this->admin = User::create([
            'username' => 'exit-admin',
            'email' => 'exit-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Exit',
            'last_name' => 'Admin',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);

        $stage = ProjectStage::create([
            'name' => 'Post-Investment Strategy',
            'sequence_order' => 1,
            'is_active' => true,
        ]);
        $status = ProjectStatus::create([
            'name' => 'Approved',
            'color_code' => '#16a34a',
            'is_active' => true,
        ]);

        $this->project = Project::create([
            'project_code' => 'BDG-2026-900',
            'title' => 'Exit Candidate',
            'description' => 'Portfolio company ready for exit planning.',
            'process_track' => 'bdg_investment',
            'origin_track' => 'bdg_investment',
            'lifecycle_phase' => 'implementation_monitoring',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'created_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_open_a_divestment_case_and_project_enters_divestment_lifecycle(): void
    {
        $response = $this->postJson('/api/divestment-cases', $this->casePayload());

        $response->assertCreated()
            ->assertJsonPath('data.phase', 'assessment')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.project.origin_track', 'bdg_investment')
            ->assertJsonCount(1, 'data.transitions');

        $this->assertSame('divestment', $this->project->fresh()->lifecycle_phase);
        $this->assertDatabaseHas('divestment_cases', ['project_id' => $this->project->id]);
    }

    public function test_duplicate_project_case_returns_conflict(): void
    {
        $this->postJson('/api/divestment-cases', $this->casePayload())->assertCreated();

        $this->postJson('/api/divestment-cases', $this->casePayload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'A divestment case already exists for this project.');
    }

    public function test_transitions_must_follow_the_defined_phase_sequence(): void
    {
        $caseId = $this->postJson('/api/divestment-cases', $this->casePayload())->json('data.id');

        $this->postJson("/api/divestment-cases/{$caseId}/transition", [
            'to_phase' => 'board_approval',
            'notes' => 'Attempted skip.',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The next allowed phase is due_diligence.');

        $this->postJson("/api/divestment-cases/{$caseId}/transition", [
            'to_phase' => 'due_diligence',
            'notes' => 'Initial assessment completed.',
        ])->assertOk()->assertJsonPath('data.phase', 'due_diligence');
    }

    public function test_execution_requires_board_approval_evidence(): void
    {
        $case = $this->openCaseAtBoardApproval();

        $this->postJson("/api/divestment-cases/{$case->id}/transition", [
            'to_phase' => 'execution',
            'notes' => 'Proceed to transaction execution.',
        ])->assertUnprocessable()
            ->assertJsonPath('missing_gates.0', 'board_approved_at');

        $this->patchJson("/api/divestment-cases/{$case->id}", [
            'board_approved_at' => now()->toDateTimeString(),
        ])->assertOk();

        $this->postJson("/api/divestment-cases/{$case->id}/transition", [
            'to_phase' => 'execution',
            'notes' => 'Board approval received.',
        ])->assertOk()->assertJsonPath('data.phase', 'execution');
    }

    public function test_closure_requires_execution_and_all_closure_evidence(): void
    {
        $case = $this->openCaseAtBoardApproval();
        $this->patchJson("/api/divestment-cases/{$case->id}", [
            'board_approved_at' => now()->subDays(5)->toDateTimeString(),
        ])->assertOk();
        $this->postJson("/api/divestment-cases/{$case->id}/transition", [
            'to_phase' => 'execution',
            'notes' => 'Exit documents are being executed.',
        ])->assertOk();

        $this->postJson("/api/divestment-cases/{$case->id}/close", [
            'actual_proceeds' => 1200000,
            'closure_notes' => 'Attempt without evidence.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'board_approved_at',
                'transfer_completed_at',
                'proceeds_collected_at',
                'closing_documents_completed_at',
            ]);

        $closed = $this->postJson("/api/divestment-cases/{$case->id}/close", [
            'board_approved_at' => now()->subDays(5)->toDateTimeString(),
            'transfer_completed_at' => now()->subDay()->toDateTimeString(),
            'proceeds_collected_at' => now()->toDateTimeString(),
            'closing_documents_completed_at' => now()->toDateTimeString(),
            'actual_proceeds' => 1200000,
            'closure_notes' => 'Transfer, collection, and closing file completed.',
        ]);

        $closed->assertOk()
            ->assertJsonPath('data.phase', 'closure')
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.progress_percentage', 100)
            ->assertJsonCount(0, 'data.missing_closure_gates');
        $this->assertSame('completed', $this->project->fresh()->lifecycle_phase);
    }

    private function openCaseAtBoardApproval(): DivestmentCase
    {
        $caseId = $this->postJson('/api/divestment-cases', $this->casePayload())->json('data.id');

        foreach ([
            'due_diligence' => 'Assessment complete.',
            'management_approval' => 'Due diligence complete.',
            'board_approval' => 'Management endorsed the exit.',
        ] as $phase => $notes) {
            $this->postJson("/api/divestment-cases/{$caseId}/transition", compact('notes') + [
                'to_phase' => $phase,
            ])->assertOk();
        }

        return DivestmentCase::findOrFail($caseId);
    }

    private function casePayload(): array
    {
        return [
            'project_id' => $this->project->id,
            'exit_strategy' => 'Competitive sale after financial and legal due diligence.',
            'target_exit_date' => now()->addMonths(6)->toDateString(),
            'estimated_proceeds' => 1500000,
            'notes' => 'Board strategy discussion requested.',
        ];
    }
}
