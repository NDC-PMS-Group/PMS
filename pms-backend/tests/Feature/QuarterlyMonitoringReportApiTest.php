<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\ProjectMonitoringReport;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuarterlyMonitoringReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_types_are_submitted_separately_and_preserve_history(): void
    {
        $proponentRole = Role::create(['name' => 'Proponent', 'description' => 'External proponent', 'is_system_role' => true]);
        $adminRole = Role::create(['name' => 'SuperAdmin', 'description' => 'NDC administrator', 'is_system_role' => true]);
        $proponent = $this->user('proponent@example.com', $proponentRole, 'Project', 'Proponent');
        $admin = $this->user('admin@example.com', $adminRole, 'NDC', 'Reviewer');
        $project = $this->project($proponent);
        $project->update(['record_type' => 'investment']);

        Sanctum::actingAs($proponent);
        $create = $this->postJson("/api/projects/{$project->id}/monitoring-reports", [
            'compliance_type' => 'employment',
            'reporting_year' => 2026,
            'quarter' => 2,
            'jobs_generated_male' => 70,
            'jobs_generated_female' => 55,
            'jobs_retained_male' => 48,
            'jobs_retained_female' => 40,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.project.record_type_label', 'Investment')
            ->assertJsonPath('data.project.investment_status_label', 'Investment Under Evaluation')
            ->assertJsonPath('data.jobs_generated', 125);
        $reportId = $create->json('data.id');

        $this->postJson("/api/projects/{$project->id}/monitoring-reports", [
            'compliance_type' => 'employment',
            'reporting_year' => 2026,
            'quarter' => 2,
            'jobs_generated_male' => 1,
            'jobs_generated_female' => 1,
            'jobs_retained_male' => 1,
            'jobs_retained_female' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.compliance_type', 'employment')
            ->assertJsonPath('data.jobs_generated', 2);

        $this->postJson("/api/projects/{$project->id}/monitoring-reports", [
            'compliance_type' => 'financial',
            'financial_period_start' => '2026-04-01',
            'financial_period_end' => '2026-06-30',
            'revenue' => 12500000,
            'remittance' => 750000,
        ])->assertCreated()->assertJsonPath('data.compliance_type', 'financial');

        $this->putJson("/api/monitoring-reports/{$reportId}", [])->assertStatus(410);

        Sanctum::actingAs($admin);
        $this->postJson("/api/monitoring-reports/{$reportId}/review", [
            'action' => 'accepted',
        ])->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->postJson("/api/projects/{$project->id}/monitoring/close")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Accept every requested compliance report before closing the period.');

        $this->getJson('/api/monitoring-reports?view=grouped&scope=active&year=2026&quarter=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(3, 'data.0.reports_list')
            ->assertJsonPath('data.0.reports.employment.jobs_generated_female', 1)
            ->assertJsonPath('data.0.reports.progress', null);

        $this->getJson('/api/monitoring-reports?view=grouped&record_type=project')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/monitoring-reports?view=grouped&record_type=investment&investment_status=under_evaluation')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.project.record_type_label', 'Investment');

        $this->get('/api/monitoring-reports/export?year=2026&quarter=2')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $pdf = $this->get('/api/monitoring-reports/export/pdf?year=2026&quarter=2')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_return_requires_review_remarks(): void
    {
        $role = Role::create(['name' => 'Admin', 'description' => 'NDC administrator', 'is_system_role' => true]);
        $admin = $this->user('admin@example.com', $role, 'NDC', 'Reviewer');
        $project = $this->project($admin);
        $report = $project->monitoringReports()->create([
            'reporting_year' => 2026,
            'quarter' => 1,
            'compliance_type' => 'progress',
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'employment_period_start' => '2026-01-01',
            'employment_period_end' => '2026-03-31',
            'financial_period_start' => '2026-01-01',
            'financial_period_end' => '2026-03-31',
            'narrative_period_start' => '2026-01-01',
            'narrative_period_end' => '2026-03-31',
            'status' => 'submitted',
            'jobs_generated' => 1,
            'jobs_retained' => 1,
            'revenue' => 1,
            'remittance' => 1,
            'milestones' => 'Milestone',
            'impact' => 'Impact',
            'monitoring_narrative' => 'Narrative',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/monitoring-reports/{$report->id}/review", ['action' => 'returned'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remarks');
    }

    public function test_employment_period_is_derived_from_the_selected_year_and_quarter(): void
    {
        $role = Role::create(['name' => 'Admin', 'description' => 'NDC administrator', 'is_system_role' => true]);
        $admin = $this->user('range-admin@example.com', $role, 'Range', 'Reviewer');
        $project = $this->project($admin);

        Sanctum::actingAs($admin);
        $this->postJson("/api/projects/{$project->id}/monitoring-reports", [
            'compliance_type' => 'employment',
            'reporting_year' => 2025,
            'quarter' => 4,
            'jobs_generated_male' => 1,
            'jobs_generated_female' => 1,
            'jobs_retained_male' => 1,
            'jobs_retained_female' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.period_start', '2025-10-01')
            ->assertJsonPath('data.period_end', '2025-12-31')
            ->assertJsonPath('data.employment_period_start', '2025-10-01')
            ->assertJsonPath('data.employment_period_end', '2025-12-31');
    }

    public function test_unopened_projects_cannot_submit_and_legacy_drafts_can_submit_once(): void
    {
        $role = Role::create(['name' => 'Admin', 'description' => 'NDC administrator', 'is_system_role' => true]);
        $admin = $this->user('cycle-admin@example.com', $role, 'Cycle', 'Admin');
        $project = $this->project($admin);
        $cycle = $project->activeMonitoringCycle()->first();
        $cycle->update(['status' => 'closed']);
        $project->update(['monitoring_status' => 'completed']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/projects/{$project->id}/monitoring-reports", [
            'compliance_type' => 'employment',
        ])->assertUnprocessable()->assertJsonPath('message', 'Monitoring has not been opened for this project.');

        $cycle->update(['status' => 'open']);
        $project->update(['monitoring_status' => 'active']);
        $draft = ProjectMonitoringReport::create([
            'project_id' => $project->id,
            'monitoring_cycle_id' => $cycle->id,
            'reporting_year' => 2026,
            'quarter' => 2,
            'compliance_type' => 'progress',
            'period_start' => '2026-04-01',
            'period_end' => '2026-06-30',
            'narrative_period_start' => '2026-04-01',
            'narrative_period_end' => '2026-06-30',
            'status' => 'draft',
        ]);

        $payload = [
            'milestones' => 'Commissioning completed.',
            'impact' => 'Reliable service expanded.',
            'monitoring_narrative' => 'The project remains on schedule.',
            'narrative_period_start' => '2026-04-01',
            'narrative_period_end' => '2026-06-30',
        ];
        $this->postJson("/api/monitoring-reports/{$draft->id}/submit", $payload)
            ->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->postJson("/api/monitoring-reports/{$draft->id}/submit", $payload)->assertStatus(409);
    }

    public function test_returned_report_remains_available_to_the_authorized_proponent(): void
    {
        $role = Role::create(['name' => 'Proponent', 'description' => 'External proponent', 'is_system_role' => true]);
        $proponent = $this->user('returned@example.com', $role, 'Returned', 'Proponent');
        $project = $this->project($proponent);
        $cycle = $project->activeMonitoringCycle()->firstOrFail();
        $project->update(['monitoring_proponent_access' => false]);
        $report = $project->monitoringReports()->create([
            'monitoring_cycle_id' => $cycle->id,
            'reporting_year' => 2026,
            'quarter' => 2,
            'compliance_type' => 'financial',
            'period_start' => '2026-04-01',
            'period_end' => '2026-06-30',
            'financial_period_start' => '2026-04-01',
            'financial_period_end' => '2026-06-30',
            'status' => 'returned',
            'revenue' => 100,
            'remittance' => 10,
        ]);

        Sanctum::actingAs($proponent);
        $this->postJson("/api/monitoring-reports/{$report->id}/submit", [
            'financial_period_start' => '2026-04-01',
            'financial_period_end' => '2026-06-30',
            'revenue' => 200,
            'remittance' => 20,
        ])->assertOk()->assertJsonPath('data.status', 'submitted');
    }

    private function user(string $email, Role $role, string $firstName, string $lastName): User
    {
        return User::create([
            'username' => str_replace(['@', '.'], '-', $email),
            'email' => $email,
            'password_hash' => Hash::make('Password123!'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function project(User $owner): Project
    {
        $stage = ProjectStage::create(['name' => 'Implementation & Monitoring', 'sequence_order' => 6, 'is_active' => true]);
        $status = ProjectStatus::create(['name' => 'Implementation Ongoing', 'color_code' => '#2563eb', 'is_active' => true]);
        $type = ProjectType::create(['name' => 'Infrastructure', 'description' => 'Infrastructure']);
        $industry = Industry::create(['name' => 'Energy', 'description' => 'Energy']);
        $sector = Sector::create(['name' => 'Public', 'description' => 'Public']);

        $project = Project::create([
            'project_code' => 'MON-2026-001',
            'title' => 'Quarterly Monitoring Project',
            'description' => 'Quarterly monitoring test project',
            'process_track' => 'spg_traditional',
            'origin_track' => 'spg_traditional',
            'lifecycle_phase' => 'implementation_monitoring',
            'project_type_id' => $type->id,
            'industry_id' => $industry->id,
            'sector_id' => $sector->id,
            'currency' => 'PHP',
            'current_stage_id' => $stage->id,
            'status_id' => $status->id,
            'proponent_name' => 'Quarterly Project Company',
            'proponent_email' => $owner->email,
            'monitoring_status' => 'active',
            'monitoring_submission_status' => 'draft',
            'monitoring_proponent_access' => true,
            'is_svf' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $owner->id,
        ]);

        $project->monitoringCycles()->create([
            'reporting_year' => 2026,
            'quarter' => 2,
            'period_start' => '2026-04-01',
            'period_end' => '2026-06-30',
            'due_date' => '2026-07-15',
            'instructions' => 'Submit quarterly compliance.',
            'requested_compliance_types' => ['employment', 'financial', 'progress'],
            'status' => 'open',
            'opened_by' => $owner->id,
            'opened_at' => now(),
        ]);

        return $project;
    }
}
