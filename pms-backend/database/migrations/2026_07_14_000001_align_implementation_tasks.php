<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('implementation_started_by')
                ->nullable()
                ->after('lifecycle_phase_started_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_scope', 40)->default('implementation')->after('soi_section')->index();
            $table->string('workstream', 100)->nullable()->after('task_scope')->index();
            $table->string('template_source', 120)->nullable()->after('workstream');
            $table->timestamp('archived_at')->nullable()->after('template_source')->index();
            $table->string('archive_reason', 255)->nullable()->after('archived_at');
        });

        Schema::create('implementation_task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_type_id')->nullable()->constrained('project_types')->cascadeOnDelete();
            $table->string('template_key', 80)->index();
            $table->string('workstream', 100)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('assigned_role', 50)->default('Project Officer');
            $table->unsignedInteger('start_offset_days')->default(0);
            $table->unsignedInteger('duration_days')->default(14);
            $table->string('priority', 20)->default('medium');
            $table->boolean('is_milestone')->default(false);
            $table->string('parent_template_title')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('tasks')->update([
            'task_scope' => 'legacy_soi',
            'archived_at' => $now,
            'archive_reason' => 'Archived during implementation task alignment',
        ]);

        DB::table('tasks')
            ->where('soi_section', 'implementation_monitoring')
            ->whereIn('project_id', function ($query) {
                $query->select('id')
                    ->from('projects')
                    ->where('lifecycle_phase', 'implementation_monitoring');
            })
            ->update([
                'task_scope' => 'implementation',
                'workstream' => 'Implementation & Monitoring',
                'archived_at' => null,
                'archive_reason' => null,
            ]);

        $types = DB::table('project_types')->pluck('id', 'name');
        $rows = [];
        foreach ($this->templates() as $templateKey => $definition) {
            $projectTypeId = $definition['project_type']
                ? ($types[$definition['project_type']] ?? null)
                : null;

            foreach ($definition['tasks'] as $index => $task) {
                $rows[] = [
                    'project_type_id' => $projectTypeId,
                    'template_key' => $templateKey,
                    'workstream' => $task[0],
                    'title' => $task[1],
                    'description' => $task[2],
                    'assigned_role' => $task[3] ?? 'Project Officer',
                    'start_offset_days' => $task[4] ?? ($index * 14),
                    'duration_days' => $task[5] ?? 14,
                    'priority' => $task[6] ?? 'medium',
                    'is_milestone' => $task[7] ?? false,
                    'parent_template_title' => $task[8] ?? null,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('implementation_task_templates')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_task_templates');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['task_scope']);
            $table->dropIndex(['workstream']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['task_scope', 'workstream', 'template_source', 'archived_at', 'archive_reason']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('implementation_started_by');
        });
    }

    private function templates(): array
    {
        return [
            'infrastructure' => [
                'project_type' => 'Infrastructure',
                'tasks' => [
                    ['Mobilization & Design', 'Confirm implementation baseline and mobilization plan', 'Confirm approved scope, schedule, site controls, permits, and mobilization responsibilities.', 'Project Officer', 0, 14, 'high', true],
                    ['Procurement', 'Complete implementation procurement and contractor onboarding', 'Track implementation procurement, notices, contracts, bonds, and contractor readiness.', 'Project Officer', 7, 45, 'high', true],
                    ['Construction', 'Execute construction work packages', 'Track physical works against the approved construction schedule and quantities.', 'Project Officer', 30, 180, 'high', true],
                    ['Quality & Safety', 'Monitor quality, safety, and environmental controls', 'Record inspections, tests, incidents, corrective actions, and compliance evidence.', 'Project Officer', 30, 180, 'high', false],
                    ['Testing & Commissioning', 'Complete testing and commissioning', 'Verify systems, punch lists, acceptance tests, and operational readiness.', 'Project Officer', 180, 30, 'high', true],
                    ['Turnover', 'Complete acceptance and turnover', 'Close defects, collect as-built records, train operators, and secure final acceptance.', 'Project Officer', 210, 30, 'high', true],
                ],
            ],
            'business_development' => [
                'project_type' => 'Business Development',
                'tasks' => [
                    ['Conditions', 'Complete conditions precedent and implementation kickoff', 'Confirm agreements, governance, reporting lines, and approved implementation conditions.', 'Project Officer', 0, 14, 'high', true],
                    ['Fund Utilization', 'Track approved fund utilization', 'Monitor drawdowns and uses of funds against the approved purpose and schedule.', 'Project Officer', 7, 90, 'high', false],
                    ['Operating Milestones', 'Deliver commercial and operating milestones', 'Track launch, production, sales, partnerships, and other project-specific delivery milestones.', 'Project Officer', 14, 120, 'high', true],
                    ['Performance', 'Monitor implementation KPIs and benefits', 'Track financial, operational, developmental, and jobs-generated indicators.', 'Project Officer', 30, 120, 'medium', false],
                    ['Compliance', 'Complete implementation compliance reporting', 'Maintain covenant, regulatory, financial, and implementation evidence.', 'Project Officer', 30, 120, 'medium', false],
                    ['Closeout', 'Complete implementation closeout review', 'Confirm deliverables, unresolved actions, and transition to regular monitoring.', 'Project Officer', 135, 15, 'high', true],
                ],
            ],
            'svf' => [
                'project_type' => 'SVF Project',
                'tasks' => [
                    ['Conditions', 'Complete investment conditions and startup kickoff', 'Confirm investment conditions, governance, reporting, and milestone ownership.', 'Project Officer', 0, 14, 'high', true],
                    ['Fund Utilization', 'Track startup fund utilization', 'Monitor released funds against the approved runway and use-of-proceeds plan.', 'Project Officer', 7, 90, 'high', false],
                    ['Product & Market', 'Deliver product and market milestones', 'Track product releases, customer validation, revenue, and commercial partnerships.', 'Project Officer', 14, 120, 'high', true],
                    ['Performance', 'Monitor startup KPIs and investment conditions', 'Track traction, runway, jobs, governance, and investment-specific indicators.', 'Project Officer', 30, 120, 'medium', false],
                    ['Compliance', 'Complete startup reporting and compliance', 'Maintain financial, legal, regulatory, and Board reporting evidence.', 'Project Officer', 30, 120, 'medium', false],
                    ['Closeout', 'Complete implementation closeout review', 'Confirm delivery results and transition the investment to portfolio monitoring.', 'Project Officer', 135, 15, 'high', true],
                ],
            ],
            'joint_venture' => [
                'project_type' => 'Joint Venture',
                'tasks' => [
                    ['Conditions Precedent', 'Complete JVA conditions precedent', 'Close all implementation conditions, permits, contributions, and required corporate actions.', 'Project Officer', 0, 30, 'high', true],
                    ['Governance', 'Establish JV governance and reporting', 'Operationalize the Board, management, reserved matters, and reporting calendar.', 'Workgroup Head', 0, 30, 'high', true],
                    ['Partner Obligations', 'Track partner contributions and obligations', 'Monitor equity, assets, services, and other committed partner contributions.', 'Project Officer', 15, 90, 'high', false],
                    ['Delivery', 'Execute JV implementation plan', 'Track procurement, construction, launch, or other approved delivery packages.', 'Project Officer', 30, 150, 'high', true],
                    ['Performance & Compliance', 'Monitor JV performance and compliance', 'Track KPIs, covenants, approvals, risks, and partner reporting.', 'Project Officer', 45, 150, 'medium', false],
                    ['Operations', 'Transition the JV to operations', 'Complete acceptance, operating readiness, and implementation closeout.', 'Project Officer', 180, 30, 'high', true],
                ],
            ],
            'ppp' => [
                'project_type' => 'Public-Private Partnership',
                'tasks' => [
                    ['Conditions Precedent', 'Complete PPP conditions precedent', 'Close contractual, permitting, financing, and implementation conditions.', 'Project Officer', 0, 30, 'high', true],
                    ['Governance', 'Establish project governance and reporting', 'Confirm public and private party governance, escalation, and reporting arrangements.', 'Workgroup Head', 0, 30, 'high', true],
                    ['Partner Obligations', 'Track public and private partner obligations', 'Monitor land, financing, permits, assets, services, and other commitments.', 'Project Officer', 15, 90, 'high', false],
                    ['Delivery', 'Execute the approved PPP delivery plan', 'Track design, procurement, construction, commissioning, or service launch.', 'Project Officer', 30, 180, 'high', true],
                    ['Performance & Compliance', 'Monitor PPP performance and compliance', 'Track service levels, contract obligations, risks, and government reporting.', 'Project Officer', 45, 180, 'medium', false],
                    ['Operations', 'Transition the project to operations', 'Complete acceptance and establish the operating performance baseline.', 'Project Officer', 210, 30, 'high', true],
                ],
            ],
            'research_development' => [
                'project_type' => 'Research & Development',
                'tasks' => [
                    ['Inception', 'Approve implementation protocol and inception plan', 'Confirm scope, methods, ethics, resources, deliverables, and schedule.', 'Project Officer', 0, 14, 'high', true],
                    ['Methodology', 'Execute research methodology and data plan', 'Complete protocols, data collection, analysis, and quality controls.', 'Project Officer', 14, 60, 'high', false],
                    ['Prototype & Data', 'Develop prototype, dataset, or research output', 'Produce and document the primary technical outputs.', 'Project Officer', 30, 90, 'high', true],
                    ['Validation', 'Validate results and address findings', 'Complete testing, peer review, user validation, and corrective work.', 'Project Officer', 90, 30, 'high', true],
                    ['Deliverables & IP', 'Finalize deliverables and intellectual property records', 'Complete reports, knowledge transfer, IP, licensing, and publication decisions.', 'Project Officer', 120, 30, 'medium', false],
                    ['Closeout', 'Complete research implementation closeout', 'Accept final outputs and document recommendations for adoption or scale-up.', 'Project Officer', 150, 15, 'high', true],
                ],
            ],
            'generic' => [
                'project_type' => null,
                'tasks' => [
                    ['Planning', 'Confirm implementation baseline', 'Confirm approved scope, schedule, budget, owners, and delivery controls.', 'Project Officer', 0, 14, 'high', true],
                    ['Mobilization', 'Mobilize the implementation team and resources', 'Complete kickoff, assignments, resources, access, and operating arrangements.', 'Project Officer', 7, 21, 'high', false],
                    ['Execution', 'Execute project deliverables', 'Track the project-specific implementation packages and outputs.', 'Project Officer', 14, 90, 'high', true],
                    ['Monitoring', 'Monitor progress, risks, and changes', 'Track schedule, cost, issues, dependencies, and corrective actions.', 'Project Officer', 14, 90, 'medium', false],
                    ['Acceptance', 'Complete acceptance and readiness checks', 'Verify deliverables, defects, evidence, and stakeholder acceptance.', 'Project Officer', 105, 15, 'high', true],
                    ['Closeout', 'Complete implementation closeout', 'Close remaining actions and transition the project to regular monitoring.', 'Project Officer', 120, 15, 'high', true],
                ],
            ],
        ];
    }
};
