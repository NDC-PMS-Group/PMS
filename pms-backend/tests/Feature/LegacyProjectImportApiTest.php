<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectLegacyDetail;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LegacyProjectImportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create([
            'name' => 'Project Admin',
            'description' => 'Legacy import admin',
            'is_system_role' => true,
        ]);

        foreach (['projects.view', 'projects.create', 'projects.update'] as $permissionName) {
            $permission = Permission::create([
                'name' => $permissionName,
                'resource' => 'projects',
                'action' => str($permissionName)->after('.')->toString(),
                'description' => $permissionName,
            ]);
            $this->role->permissions()->attach($permission->id);
        }

        $this->admin = User::create([
            'username' => 'legacy-admin',
            'email' => 'legacy-admin@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Legacy',
            'last_name' => 'Admin',
            'default_role_id' => $this->role->id,
            'is_active' => true,
        ]);

        foreach ([
            'Intake',
            'Due Diligence',
            'Implementation & Monitoring',
            'Divestment',
        ] as $index => $stage) {
            ProjectStage::create([
                'name' => $stage,
                'sequence_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        foreach ([
            'Pre-screening / KYC',
            'Due Diligence Ongoing',
            'Milestones Setup',
            'Implementation Ongoing',
            'Monitoring Ongoing',
            'For Divestment',
            'On Hold',
        ] as $status) {
            ProjectStatus::create([
                'name' => $status,
                'color_code' => '#2563eb',
                'is_active' => true,
            ]);
        }
    }

    public function test_preview_parses_legacy_workbook_without_creating_projects(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->post('/api/legacy-projects/import/preview', [
            'file' => $this->workbook([
                ['Development of Vaccine Facility', 'For Implementation', 'Health Technology', 'Taysan Batangas', '7.5Bn', 150000000, 'GLC', 'Priority facility'],
                ['Shelved Sample', 'Shelved Projects', 'Energy', 'Laguna', '450M', null, 'NDC', 'Pending documents'],
            ]),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.header_row', 3)
            ->assertJsonPath('data.summary.total_rows', 2)
            ->assertJsonPath('data.summary.importable_rows', 2)
            ->assertJsonPath('data.rows.0.mapped_stage', 'Implementation & Monitoring')
            ->assertJsonPath('data.rows.0.mapped_status', 'Milestones Setup')
            ->assertJsonPath('data.rows.0.estimated_cost', 7500000000);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_commit_creates_projects_legacy_details_and_converts_statuses(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->post('/api/legacy-projects/import/commit', [
            'file' => $this->workbook([
                ['Operational Project', 'Operational', 'Trading', 'Pasay', '200M', 60000000, 'MECI', 'Operating'],
                ['Shelved Sample', 'Shelved Projects', 'Energy', 'Laguna', '450M', null, 'NDC', 'Pending documents'],
            ]),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.skipped_count', 0);

        $operational = Project::with(['currentStage', 'status', 'legacyDetail'])->where('title', 'Operational Project')->firstOrFail();
        $this->assertTrue($operational->is_legacy);
        $this->assertSame('Implementation & Monitoring', $operational->currentStage->name);
        $this->assertSame('Monitoring Ongoing', $operational->status->name);
        $this->assertSame(200000000.00, (float) $operational->estimated_cost);
        $this->assertSame(60000000.00, (float) $operational->actual_cost);
        $this->assertSame(ProjectLegacyDetail::STATUS_NEEDS_DETAILS, $operational->legacyDetail->detail_status);

        $shelved = Project::where('title', 'Shelved Sample')->firstOrFail();
        $this->assertTrue($shelved->is_archived);
    }

    public function test_duplicate_commit_skips_matching_legacy_rows(): void
    {
        Sanctum::actingAs($this->admin);

        $rows = [
            ['Duplicate Project', 'Identified', 'Energy', 'Cavite', '450M', null, 'NDC', 'First pass'],
        ];

        $this->post('/api/legacy-projects/import/commit', ['file' => $this->workbook($rows)])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 1);

        $this->post('/api/legacy-projects/import/commit', ['file' => $this->workbook($rows)])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 0)
            ->assertJsonPath('data.skipped_count', 1);

        $this->assertSame(1, Project::where('title', 'Duplicate Project')->count());
    }

    public function test_staff_can_update_and_complete_legacy_project_details(): void
    {
        Sanctum::actingAs($this->admin);

        $this->post('/api/legacy-projects/import/commit', [
            'file' => $this->workbook([
                ['Completion Target', 'Under Evaluation', 'Mining', 'Benguet', '10M', null, 'Partner', 'Needs review'],
            ]),
        ])->assertCreated();

        $projectId = Project::where('title', 'Completion Target')->value('id');

        $statusId = ProjectStatus::where('name', 'Due Diligence Ongoing')->value('id');

        $this->patchJson("/api/legacy-projects/{$projectId}", [
            'title' => 'Completion Target Updated',
            'status_id' => $statusId,
            'current_stage_id' => ProjectStage::where('name', 'Due Diligence')->value('id'),
            'detail_status' => ProjectLegacyDetail::STATUS_IN_PROGRESS,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Completion Target Updated')
            ->assertJsonPath('data.legacy_detail.detail_status', ProjectLegacyDetail::STATUS_IN_PROGRESS);

        $this->postJson("/api/legacy-projects/{$projectId}/complete")
            ->assertOk()
            ->assertJsonPath('data.legacy_detail.detail_status', ProjectLegacyDetail::STATUS_COMPLETE);
    }

    public function test_staff_can_link_an_active_proponent_account_to_a_legacy_project(): void
    {
        Sanctum::actingAs($this->admin);

        $proponentRole = Role::create([
            'name' => 'Proponent',
            'description' => 'External project contact',
            'is_system_role' => true,
        ]);
        $proponent = User::create([
            'username' => 'legacy-proponent',
            'email' => 'legacy-proponent@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Legacy',
            'last_name' => 'Proponent',
            'default_role_id' => $proponentRole->id,
            'is_active' => true,
        ]);

        $this->post('/api/legacy-projects/import/commit', [
            'file' => $this->workbook([
                ['Monitoring Contact Project', 'Operational', 'Energy', 'Cavite', '50M', null, 'Partner', 'Ready for monitoring'],
            ]),
        ])->assertCreated();

        $project = Project::where('title', 'Monitoring Contact Project')->firstOrFail();

        $this->patchJson("/api/legacy-projects/{$project->id}", [
            'proponent_user_id' => $proponent->id,
        ])->assertOk()
            ->assertJsonPath('data.proponent_email', $proponent->email)
            ->assertJsonPath('data.proponent_user.id', $proponent->id)
            ->assertJsonPath('data.legacy_review.monitoring_ready', true);

        $this->patchJson("/api/legacy-projects/{$project->id}", [
            'proponent_user_id' => $this->admin->id,
        ])->assertStatus(422);
    }

    public function test_project_reports_can_filter_legacy_projects_and_export_their_source_columns(): void
    {
        Sanctum::actingAs($this->admin);

        $this->post('/api/legacy-projects/import/commit', [
            'file' => $this->workbook([
                ['Reportable Legacy Project', 'Operational', 'Energy', 'Cavite', '50M', null, 'Partner', 'Report source'],
            ]),
        ])->assertCreated();

        $this->getJson('/api/reports/projects?is_legacy=true')
            ->assertOk()
            ->assertJsonPath('data.0.is_legacy', true);

        $this->get('/api/reports/projects/export?is_legacy=true&columns=project_code,record_source,legacy_detail_status,legacy_source_status')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_normal_project_index_excludes_legacy_records_by_default(): void
    {
        Sanctum::actingAs($this->admin);

        Project::create([
            'project_code' => 'BDG-2026-900',
            'title' => 'Current PMS Project',
            'description' => 'Normal project list record',
            'process_track' => 'bdg_investment',
            'currency' => 'PHP',
            'current_stage_id' => ProjectStage::where('name', 'Intake')->value('id'),
            'status_id' => ProjectStatus::where('name', 'Pre-screening / KYC')->value('id'),
            'proponent_name' => 'Current Partner',
            'proponent_email' => 'current-partner@example.com',
            'is_legacy' => false,
            'is_archived' => false,
            'is_deleted' => false,
            'created_by' => $this->admin->id,
        ]);

        $this->post('/api/legacy-projects/import/commit', [
            'file' => $this->workbook([
                ['Legacy Only Project', 'Operational', 'Energy', 'Cavite', '50M', null, 'Partner', 'Legacy source'],
            ]),
        ])->assertCreated();

        $defaultTitles = collect($this->getJson('/api/projects?per_page=50')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertTrue($defaultTitles->contains('Current PMS Project'));
        $this->assertFalse($defaultTitles->contains('Legacy Only Project'));

        $legacyTitles = collect($this->getJson('/api/projects?is_legacy=true&per_page=50')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertFalse($legacyTitles->contains('Current PMS Project'));
        $this->assertTrue($legacyTitles->contains('Legacy Only Project'));
    }

    public function test_import_requires_project_create_permission(): void
    {
        $viewerRole = Role::create([
            'name' => 'Viewer',
            'description' => 'View only',
            'is_system_role' => true,
        ]);
        $viewerRole->permissions()->attach(
            Permission::where('name', 'projects.view')->value('id')
        );

        $viewer = User::create([
            'username' => 'legacy-viewer',
            'email' => 'legacy-viewer@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Legacy',
            'last_name' => 'Viewer',
            'default_role_id' => $viewerRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($viewer);

        $this->post('/api/legacy-projects/import/preview', [
            'file' => $this->workbook([
                ['No Access Project', 'Identified', 'Energy', 'Cavite', '1M', null, 'NDC', null],
            ]),
        ])->assertForbidden();
    }

    public function test_proponent_can_only_view_linked_legacy_projects(): void
    {
        $proponentRole = Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);
        $proponentRole->permissions()->attach(
            Permission::where('name', 'projects.view')->value('id')
        );

        $proponent = User::create([
            'username' => 'legacy-linked-proponent',
            'email' => 'linked-proponent@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Linked',
            'last_name' => 'Proponent',
            'default_role_id' => $proponentRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $this->post('/api/legacy-projects/import/commit', [
            'file' => $this->workbook([
                ['Linked Legacy Project', 'Operational', 'Energy', 'Cavite', '50M', null, 'Partner A', 'Visible'],
                ['Unlinked Legacy Project', 'Operational', 'Energy', 'Cavite', '60M', null, 'Partner B', 'Hidden'],
            ]),
        ])->assertCreated();

        $linked = Project::where('title', 'Linked Legacy Project')->firstOrFail();
        $unlinked = Project::where('title', 'Unlinked Legacy Project')->firstOrFail();
        $linked->update(['proponent_email' => $proponent->email]);

        Sanctum::actingAs($proponent);

        $titles = collect($this->getJson('/api/legacy-projects?per_page=50')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertTrue($titles->contains('Linked Legacy Project'));
        $this->assertFalse($titles->contains('Unlinked Legacy Project'));

        $this->getJson("/api/legacy-projects/{$linked->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Linked Legacy Project');

        $this->getJson("/api/legacy-projects/{$unlinked->id}")
            ->assertNotFound();
    }

    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'SPG - PROJECTS DASHBOARD');
        $sheet->setCellValue('A2', 'As of May 8, 2026');

        $headers = [
            'PROJECT TITLE or NAME',
            'STATUS / MILESTONE',
            'SECTOR',
            'LOCATION',
            'INDICATIVE PROJECT COST',
            'FUND RELEASED',
            'PROPONENT/ PARTNER',
            'REMARKS',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '3', $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 4), $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'legacy_import_');
        unlink($path);
        $path .= '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'NDC Project List.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
