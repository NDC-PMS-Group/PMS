<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NdcRealisticProjectDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreDataSeeder::class,
            ApprovalWorkflowSeeder::class,
        ]);

        DB::transaction(function () {
            $now = Carbon::now();
            $users = DB::table('users')->pluck('id', 'email');
            $roles = DB::table('roles')->pluck('id', 'name');
            $stages = DB::table('project_stages')->pluck('id', 'name');
            $statuses = DB::table('project_statuses')->pluck('id', 'name');
            $types = DB::table('project_types')->pluck('id', 'name');
            $industries = DB::table('industries')->pluck('id', 'name');
            $sectors = DB::table('sectors')->pluck('id', 'name');
            $investments = DB::table('investment_types')->pluck('id', 'name');
            $fundingSources = DB::table('funding_sources')->pluck('id', 'name');

            $workflowId = DB::table('approval_workflows')
                ->where('name', 'SOI Sequential Approval')
                ->value('id');
            $steps = DB::table('approval_steps')
                ->where('workflow_id', $workflowId)
                ->orderBy('step_order')
                ->get()
                ->keyBy('step_order');

            $superAdminId = $users['sa@gmail.com'];
            $proponentId = $users['proponent@ndc.gov.ph'] ?? $superAdminId;
            $projectOfficerId = $users['pdo@ndc.gov.ph'] ?? $superAdminId;
            $workgroupHeadId = $users['wgh@ndc.gov.ph'] ?? $superAdminId;
            $mancomId = $users['mancom@ndc.gov.ph'] ?? $superAdminId;
            $boardId = $users['board@ndc.gov.ph'] ?? $superAdminId;

            $projects = [
                [
                    'project_code' => 'MR25-11-153-PMS',
                    'title' => 'Procurement of Project Management System for NDC',
                    'description' => 'Procurement, configuration, rollout, and handover of a project management system aligned with NDC project monitoring, approval routing, reporting, GIS location tagging, and document control requirements.',
                    'project_type_id' => $types['Business Development'],
                    'industry_id' => $industries['Technology'],
                    'sector_id' => $sectors['Government'],
                    'investment_type_id' => $investments['Grant'],
                    'funding_source_id' => $fundingSources['Government Budget'],
                    'estimated_cost' => 4850000,
                    'actual_cost' => 1125000,
                    'current_stage_id' => $stages['Implementation'],
                    'status_id' => $statuses['Implementation Ongoing'],
                    'proposal_date' => '2026-01-20',
                    'start_date' => '2026-03-02',
                    'target_completion_date' => '2026-10-30',
                    'location_address' => 'NDC Building, 116 Tordesillas Street, Salcedo Village, Makati City, Metro Manila',
                    'location_region_code' => '130000000',
                    'location_region_name' => 'National Capital Region - NCR',
                    'location_province_code' => null,
                    'location_province_name' => null,
                    'location_city_code' => '137602000',
                    'location_city_name' => 'City of Makati',
                    'location_barangay_code' => '137602022',
                    'location_barangay_name' => 'Bel-Air',
                    'location_street' => '116 Tordesillas Street, Salcedo Village',
                    'location_lat' => 14.560742,
                    'location_lng' => 121.024832,
                    'project_officer_id' => $projectOfficerId,
                    'workgroup_head_id' => $workgroupHeadId,
                    'proponent_name' => 'National Development Company - Corporate Planning Department',
                    'proponent_contact' => '+63 2 8840 4830',
                    'proponent_email' => 'proponent@ndc.gov.ph',
                    'created_by' => $proponentId,
                    'stage_history' => [
                        [null, 'Proposal', 'Statement of intent and procurement request encoded.', '2026-01-20 09:15:00', $proponentId],
                        ['Proposal', 'Evaluation', 'Technical and completeness review started.', '2026-02-03 10:30:00', $projectOfficerId],
                        ['Evaluation', 'Approval', 'Submitted for sequential approval routing.', '2026-02-18 14:00:00', $workgroupHeadId],
                        ['Approval', 'Implementation', 'Approval completed with rollout conditions.', '2026-03-02 08:45:00', $boardId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'Initial project record submitted by proponent.', '2026-01-20 09:15:00', $proponentId],
                        ['Submitted', 'Initial Completeness Check', 'Project Officer opened completeness review.', '2026-02-03 10:30:00', $projectOfficerId],
                        ['Initial Completeness Check', 'For Board Approval', 'Elevated after Workgroup and ManCom endorsements.', '2026-02-25 16:20:00', $mancomId],
                        ['For Board Approval', 'Approved with Conditions', 'Board approved subject to phased rollout and data migration controls.', '2026-03-01 17:10:00', $boardId],
                        ['Approved with Conditions', 'Implementation Ongoing', 'Kickoff and implementation mobilization completed.', '2026-03-02 08:45:00', $projectOfficerId],
                    ],
                    'approval' => [
                        'overall_status' => 'approved_with_conditions',
                        'current_step_order' => null,
                        'started_at' => '2026-02-18 14:00:00',
                        'completed_at' => '2026-03-01 17:10:00',
                        'records' => [
                            [1, $proponentId, 'approved', 'SOI, TOR, and procurement package submitted for review.', null, '2026-02-18 14:00:00', '2026-02-18 14:20:00'],
                            [2, $projectOfficerId, 'approved', 'Technical scope is consistent with PMS requirements and implementation timeline.', null, '2026-02-18 14:20:00', '2026-02-20 11:30:00'],
                            [3, $workgroupHeadId, 'approved', 'Workgroup endorses procurement subject to change management plan.', null, '2026-02-20 11:30:00', '2026-02-24 15:45:00'],
                            [4, $mancomId, 'approved', 'Recommended for Board action with quarterly progress reporting.', null, '2026-02-24 15:45:00', '2026-02-25 16:20:00'],
                            [5, $boardId, 'approved_with_conditions', 'Approved subject to pilot acceptance and data privacy compliance sign-off.', 'Submit pilot acceptance report before full rollout.', '2026-02-25 16:20:00', '2026-03-01 17:10:00'],
                        ],
                    ],
                    'tasks' => [
                        ['Finalize TOR and procurement baseline', 'Complete final scope, deliverables, acceptance criteria, and procurement timeline.', 'procurement', $projectOfficerId, '2026-01-22', '2026-02-05', 'completed', 100, 'high', 32, 35, false],
                        ['Conduct pre-bid and vendor clarification conference', 'Address vendor questions and issue clarifications for the PMS procurement package.', 'procurement', $projectOfficerId, '2026-02-06', '2026-02-16', 'completed', 100, 'normal', 18, 20, false],
                        ['Contract kickoff and implementation planning', 'Confirm implementation team, governance cadence, reporting format, and risk register.', 'milestone', $workgroupHeadId, '2026-03-02', '2026-03-08', 'completed', 100, 'high', 16, 18, true],
                        ['Configure project lifecycle workflows', 'Configure proposal, evaluation, approval, implementation, completion, and divestment stages.', 'configuration', $projectOfficerId, '2026-03-09', '2026-05-22', 'in_progress', 65, 'urgent', 120, 82, false, [
                            ['Map NDC approval roles and routing matrix', 'configuration', $projectOfficerId, '2026-03-09', '2026-03-18', 'completed', 100, 'high', 18, 20],
                            ['Configure SOI approval steps and permissions', 'configuration', $projectOfficerId, '2026-03-19', '2026-04-18', 'completed', 100, 'urgent', 42, 45],
                            ['Validate status transitions with sample projects', 'qa', $workgroupHeadId, '2026-04-19', '2026-05-22', 'in_progress', 35, 'high', 36, 17],
                        ]],
                        ['Integrate PSGC location and geocoding workflow', 'Implement cascading region, province, city, barangay selection and coordinate lookup.', 'configuration', $projectOfficerId, '2026-04-15', '2026-06-10', 'in_progress', 45, 'high', 80, 38, false, [
                            ['Connect PSGC region/province/city/barangay endpoints', 'integration', $projectOfficerId, '2026-04-15', '2026-05-05', 'completed', 100, 'high', 28, 31],
                            ['Add address-based coordinate lookup', 'integration', $projectOfficerId, '2026-05-06', '2026-05-24', 'in_progress', 55, 'high', 24, 14],
                            ['Test map preview and coordinate persistence', 'qa', $projectOfficerId, '2026-05-25', '2026-06-10', 'pending', 0, 'normal', 18, null],
                        ]],
                        ['User acceptance testing and pilot sign-off', 'Run role-based UAT with proponent, PDO, WGH, ManCom, Board, and Super Admin accounts.', 'milestone', $mancomId, '2026-07-01', '2026-07-31', 'pending', 0, 'high', 64, null, true],
                        ['Production deployment and turnover', 'Deploy to production, turn over admin guides, and close implementation acceptance items.', 'deployment', $projectOfficerId, '2026-09-15', '2026-10-30', 'pending', 0, 'urgent', 72, null, true],
                    ],
                ],
                [
                    'project_code' => 'NDC-JV-2026-002',
                    'title' => 'Central Luzon Agri-Cold Storage Joint Venture',
                    'description' => 'Evaluation of a joint venture cold storage and consolidation facility to support onion, vegetable, and high-value crop producers in Central Luzon.',
                    'project_type_id' => $types['Joint Venture'],
                    'industry_id' => $industries['Agriculture'],
                    'sector_id' => $sectors['Private'],
                    'investment_type_id' => $investments['Equity'],
                    'funding_source_id' => $fundingSources['Private Investors'],
                    'estimated_cost' => 185000000,
                    'actual_cost' => null,
                    'current_stage_id' => $stages['Approval'],
                    'status_id' => $statuses['For ManCom Review'],
                    'proposal_date' => '2026-02-12',
                    'start_date' => '2026-04-01',
                    'target_completion_date' => '2027-03-31',
                    'location_address' => 'Barangay Caalibangbangan, Cabanatuan City, Nueva Ecija',
                    'location_region_code' => '030000000',
                    'location_region_name' => 'Region III - Central Luzon',
                    'location_province_code' => '034900000',
                    'location_province_name' => 'Nueva Ecija',
                    'location_city_code' => '034903000',
                    'location_city_name' => 'Cabanatuan City',
                    'location_barangay_code' => '034903008',
                    'location_barangay_name' => 'Caalibangbangan',
                    'location_street' => 'Maharlika Highway corridor',
                    'location_lat' => 15.498721,
                    'location_lng' => 120.967642,
                    'project_officer_id' => $projectOfficerId,
                    'workgroup_head_id' => $workgroupHeadId,
                    'proponent_name' => 'Luzon Agro-Logistics Consortium',
                    'proponent_contact' => '+63 917 555 0142',
                    'proponent_email' => 'projects@luzonagrologistics.test',
                    'created_by' => $proponentId,
                    'stage_history' => [
                        [null, 'Proposal', 'JV concept note received and encoded.', '2026-02-12 09:00:00', $proponentId],
                        ['Proposal', 'Evaluation', 'Project Officer accepted for preliminary due diligence.', '2026-02-19 13:30:00', $projectOfficerId],
                        ['Evaluation', 'Approval', 'Due diligence pack elevated to Workgroup and ManCom approval route.', '2026-04-28 15:00:00', $workgroupHeadId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'JV proposal submitted by external proponent.', '2026-02-12 09:00:00', $proponentId],
                        ['Submitted', 'For Evaluation', 'Completeness check passed.', '2026-02-19 13:30:00', $projectOfficerId],
                        ['For Evaluation', 'For Workgroup Review', 'Financial model and site validation package completed.', '2026-04-20 10:45:00', $projectOfficerId],
                        ['For Workgroup Review', 'For ManCom Review', 'Workgroup endorsed for ManCom deliberation.', '2026-04-28 15:00:00', $workgroupHeadId],
                    ],
                    'approval' => [
                        'overall_status' => 'for_approval',
                        'current_step_order' => 4,
                        'started_at' => '2026-04-20 10:45:00',
                        'completed_at' => null,
                        'records' => [
                            [1, $proponentId, 'approved', 'Complete JV proposal and supporting farm-gate demand data submitted.', null, '2026-04-20 10:45:00', '2026-04-20 11:00:00'],
                            [2, $projectOfficerId, 'approved', 'Initial valuation and risk screen are sufficient for committee review.', null, '2026-04-20 11:00:00', '2026-04-23 16:00:00'],
                            [3, $workgroupHeadId, 'approved', 'Endorsed with request for sensitivity analysis on utilization rates.', null, '2026-04-23 16:00:00', '2026-04-28 15:00:00'],
                            [4, null, 'pending', 'Awaiting ManCom deliberation.', null, '2026-04-28 15:00:00', null],
                        ],
                    ],
                    'tasks' => [
                        ['Validate market demand and catchment area', 'Confirm crop volumes, farmer cooperative participation, and seasonal demand assumptions.', 'due_diligence', $projectOfficerId, '2026-02-20', '2026-03-15', 'completed', 100, 'high', 56, 61, false],
                        ['Conduct site inspection and utility readiness review', 'Validate access road, power, water, and logistics constraints at the proposed site.', 'fieldwork', $workgroupHeadId, '2026-03-04', '2026-03-20', 'completed', 100, 'high', 40, 44, false],
                        ['Prepare financial model and investment structure', 'Model NDC equity participation, private proponent contribution, IRR, payback, and downside cases.', 'analysis', $projectOfficerId, '2026-03-18', '2026-05-15', 'in_progress', 75, 'urgent', 96, 70, false, [
                            ['Build base-case utilization forecast', 'analysis', $projectOfficerId, '2026-03-18', '2026-04-05', 'completed', 100, 'high', 24, 26],
                            ['Run downside sensitivity scenarios', 'analysis', $projectOfficerId, '2026-04-06', '2026-04-28', 'completed', 100, 'urgent', 28, 30],
                            ['Draft investment return summary', 'analysis', $projectOfficerId, '2026-04-29', '2026-05-15', 'in_progress', 45, 'high', 20, 9],
                        ]],
                        ['Complete environmental and social screening', 'Document permitting requirements, cold-chain waste handling, and local stakeholder risks.', 'compliance', $projectOfficerId, '2026-04-08', '2026-05-20', 'in_progress', 40, 'normal', 48, 18, false],
                        ['Prepare ManCom decision brief', 'Summarize commercial terms, risks, recommended investment cap, and proposed next actions.', 'approval', $mancomId, '2026-05-01', '2026-05-16', 'pending', 15, 'urgent', 32, 6, true, [
                            ['Compile risk register and mitigations', 'approval', $projectOfficerId, '2026-05-01', '2026-05-07', 'in_progress', 50, 'high', 10, 5],
                            ['Finalize recommendation memo', 'approval', $mancomId, '2026-05-08', '2026-05-16', 'pending', 0, 'urgent', 12, null],
                        ]],
                        ['Draft Board approval package', 'Prepare Board-level recommendation after ManCom action.', 'approval', $boardId, '2026-05-17', '2026-06-14', 'pending', 0, 'high', 40, null, true],
                    ],
                ],
                [
                    'project_code' => 'NDC-SVF-2026-003',
                    'title' => 'Mindanao Renewable Microgrid SVF Investment',
                    'description' => 'Startup Venture Fund evaluation for a renewable microgrid operator serving island communities in Northern Mindanao, currently returned for additional technical and permitting documents.',
                    'project_type_id' => $types['SVF Project'],
                    'industry_id' => $industries['Energy'],
                    'sector_id' => $sectors['Private'],
                    'investment_type_id' => $investments['Venture Capital'],
                    'funding_source_id' => $fundingSources['SVF Pool'],
                    'estimated_cost' => 45000000,
                    'actual_cost' => null,
                    'current_stage_id' => $stages['Proposal'],
                    'status_id' => $statuses['Returned for Revision'],
                    'proposal_date' => '2026-03-10',
                    'start_date' => null,
                    'target_completion_date' => '2026-12-15',
                    'location_address' => 'Barangay Poblacion, Mambajao, Camiguin',
                    'location_region_code' => '100000000',
                    'location_region_name' => 'Region X - Northern Mindanao',
                    'location_province_code' => '101800000',
                    'location_province_name' => 'Camiguin',
                    'location_city_code' => '101803000',
                    'location_city_name' => 'Mambajao',
                    'location_barangay_code' => '101803013',
                    'location_barangay_name' => 'Poblacion',
                    'location_street' => 'Municipal port and poblacion service area',
                    'location_lat' => 9.250402,
                    'location_lng' => 124.716102,
                    'project_officer_id' => $projectOfficerId,
                    'workgroup_head_id' => $workgroupHeadId,
                    'proponent_name' => 'IslaGrid Renewables Inc.',
                    'proponent_contact' => '+63 917 555 0188',
                    'proponent_email' => 'investment@islagrid.test',
                    'created_by' => $proponentId,
                    'stage_history' => [
                        [null, 'Proposal', 'SVF investment application received.', '2026-03-10 10:10:00', $proponentId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'Application submitted through proponent account.', '2026-03-10 10:10:00', $proponentId],
                        ['Submitted', 'Initial Completeness Check', 'Project Officer started completeness screening.', '2026-03-12 09:30:00', $projectOfficerId],
                        ['Initial Completeness Check', 'Returned for Revision', 'Returned for missing DOE endorsement, updated cap table, and interconnection assumptions.', '2026-03-19 16:40:00', $projectOfficerId],
                    ],
                    'approval' => [
                        'overall_status' => 'returned',
                        'current_step_order' => 1,
                        'started_at' => '2026-03-10 10:10:00',
                        'completed_at' => null,
                        'records' => [
                            [1, $proponentId, 'approved', 'SVF application submitted for completeness checking.', null, '2026-03-10 10:10:00', '2026-03-10 10:20:00'],
                            [2, $projectOfficerId, 'returned', 'Please attach DOE endorsement, updated cap table, and revised demand forecast.', 'Resubmit complete documents within 10 working days.', '2026-03-12 09:30:00', '2026-03-19 16:40:00'],
                        ],
                    ],
                    'tasks' => [
                        ['Review SVF application completeness', 'Check submitted corporate, technical, financial, and legal documents against SVF checklist.', 'screening', $projectOfficerId, '2026-03-12', '2026-03-19', 'completed', 100, 'high', 24, 27, false],
                        ['Request DOE endorsement and permitting evidence', 'Ask proponent to provide DOE endorsement or proof of active permitting process.', 'compliance', $proponentId, '2026-03-20', '2026-04-08', 'in_progress', 50, 'urgent', 16, 8, false, [
                            ['Upload DOE correspondence or application receipt', 'compliance', $proponentId, '2026-03-20', '2026-03-28', 'completed', 100, 'urgent', 6, 6],
                            ['Attach LGU endorsement status', 'compliance', $proponentId, '2026-03-29', '2026-04-08', 'in_progress', 25, 'high', 8, 2],
                        ]],
                        ['Revise cap table and funding round assumptions', 'Update ownership, committed subscriptions, valuation basis, and investor rights schedule.', 'finance', $proponentId, '2026-03-20', '2026-04-12', 'in_progress', 35, 'high', 24, 9, false, [
                            ['Update founder and investor ownership schedule', 'finance', $proponentId, '2026-03-20', '2026-03-31', 'in_progress', 60, 'high', 8, 5],
                            ['Reconcile committed subscriptions', 'finance', $proponentId, '2026-04-01', '2026-04-12', 'pending', 0, 'normal', 8, null],
                        ]],
                        ['Update island demand forecast and tariff sensitivity', 'Refresh household, commercial, and public facility load forecast with downside tariff scenario.', 'analysis', $proponentId, '2026-03-22', '2026-04-18', 'pending', 0, 'normal', 40, null, false],
                        ['Resubmit revised SVF package', 'Submit complete revised application for Project Officer re-check.', 'milestone', $proponentId, '2026-04-19', '2026-04-22', 'pending', 0, 'urgent', 8, null, true],
                    ],
                ],
            ];

            foreach ($projects as $projectData) {
                $this->seedProject(
                    $projectData,
                    $now,
                    $workflowId,
                    $steps,
                    [
                        'superadmin' => $superAdminId,
                        'proponent' => $proponentId,
                        'project_officer' => $projectOfficerId,
                        'workgroup_head' => $workgroupHeadId,
                        'mancom' => $mancomId,
                        'board' => $boardId,
                    ],
                    $roles,
                    $stages,
                    $statuses
                );
            }
        });

        $this->command?->info('Seeded 3 realistic NDC demo projects with tasks, histories, and approval workflow data.');
    }

    private function seedProject(
        array $data,
        Carbon $now,
        int $workflowId,
        $steps,
        array $users,
        $roles,
        $stages,
        $statuses
    ): void {
        $stageHistory = $data['stage_history'];
        $statusHistory = $data['status_history'];
        $approval = $data['approval'];
        $tasks = $data['tasks'];
        unset($data['stage_history'], $data['status_history'], $data['approval'], $data['tasks']);

        DB::table('projects')->updateOrInsert(
            ['project_code' => $data['project_code']],
            array_merge($data, [
                'currency' => 'PHP',
                'map_layer' => 'openstreetmap',
                'is_svf' => str_contains($data['project_code'], 'SVF'),
                'is_archived' => false,
                'is_deleted' => false,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ])
        );

        $projectId = DB::table('projects')->where('project_code', $data['project_code'])->value('id');
        $taskIds = DB::table('tasks')->where('project_id', $projectId)->pluck('id');

        if ($taskIds->isNotEmpty()) {
            DB::table('task_dependencies')
                ->whereIn('task_id', $taskIds)
                ->orWhereIn('depends_on_task_id', $taskIds)
                ->delete();
        }

        DB::table('tasks')->where('project_id', $projectId)->delete();
        DB::table('project_members')->where('project_id', $projectId)->delete();
        DB::table('project_stage_history')->where('project_id', $projectId)->delete();
        DB::table('project_status_history')->where('project_id', $projectId)->delete();

        $approvalIds = DB::table('project_approvals')->where('project_id', $projectId)->pluck('id');
        if ($approvalIds->isNotEmpty()) {
            DB::table('approval_step_records')->whereIn('project_approval_id', $approvalIds)->delete();
            DB::table('project_approvals')->whereIn('id', $approvalIds)->delete();
        }

        $memberRows = [
            [$users['superadmin'], $roles['superadmin'], 'oversight', true, true, true, true, true],
            [$users['proponent'], $roles['Proponent'], 'owner', true, true, false, false, false],
            [$users['project_officer'], $roles['Project Officer'], 'project_officer', true, true, false, true, true],
            [$users['workgroup_head'], $roles['Workgroup Head'], 'reviewer', true, true, false, true, false],
            [$users['mancom'], $roles['ManCom'], 'reviewer', true, false, false, true, false],
            [$users['board'], $roles['Board'], 'approver', true, false, false, true, false],
        ];

        foreach ($memberRows as [$userId, $roleId, $assignmentType, $canView, $canEdit, $canDelete, $canApprove, $canManage]) {
            DB::table('project_members')->insert([
                'project_id' => $projectId,
                'user_id' => $userId,
                'role_id' => $roleId,
                'assignment_type' => $assignmentType,
                'can_view' => $canView,
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
                'can_approve' => $canApprove,
                'can_manage_members' => $canManage,
                'assigned_by' => $users['superadmin'],
                'assigned_at' => $now,
            ]);
        }

        foreach ($stageHistory as [$from, $to, $reason, $changedAt, $changedBy]) {
            DB::table('project_stage_history')->insert([
                'project_id' => $projectId,
                'from_stage_id' => $from ? $stages[$from] : null,
                'to_stage_id' => $stages[$to],
                'changed_by' => $changedBy,
                'change_reason' => $reason,
                'changed_at' => $changedAt,
            ]);
        }

        foreach ($statusHistory as [$from, $to, $reason, $changedAt, $changedBy]) {
            DB::table('project_status_history')->insert([
                'project_id' => $projectId,
                'from_status_id' => $from ? $statuses[$from] : null,
                'to_status_id' => $statuses[$to],
                'changed_by' => $changedBy,
                'change_reason' => $reason,
                'changed_at' => $changedAt,
            ]);
        }

        $approvalId = DB::table('project_approvals')->insertGetId([
            'project_id' => $projectId,
            'workflow_id' => $workflowId,
            'current_step_id' => $approval['current_step_order'] ? $steps[$approval['current_step_order']]->id : null,
            'overall_status' => $approval['overall_status'],
            'started_at' => $approval['started_at'],
            'completed_at' => $approval['completed_at'],
        ]);

        foreach ($approval['records'] as [$stepOrder, $approverId, $recordStatus, $comments, $conditions, $submittedAt, $reviewedAt]) {
            DB::table('approval_step_records')->insert([
                'project_approval_id' => $approvalId,
                'step_id' => $steps[$stepOrder]->id,
                'approver_id' => $approverId,
                'status' => $recordStatus,
                'comments' => $comments,
                'conditions' => $conditions,
                'submitted_at' => $submittedAt,
                'reviewed_at' => $reviewedAt,
            ]);
        }

        $previousTaskId = null;
        foreach ($tasks as $taskData) {
            [
                $title,
                $description,
                $taskType,
                $assignedTo,
                $startDate,
                $dueDate,
                $taskStatus,
                $progress,
                $priority,
                $estimatedHours,
                $actualHours,
                $isMilestone,
                $subtasks,
            ] = array_pad($taskData, 13, []);

            $taskId = DB::table('tasks')->insertGetId([
                'project_id' => $projectId,
                'title' => $title,
                'description' => $description,
                'task_type' => $taskType,
                'assigned_to' => $assignedTo,
                'assigned_by' => $users['project_officer'],
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'completion_date' => $taskStatus === 'completed' ? $dueDate : null,
                'status' => $taskStatus,
                'progress_percentage' => $progress,
                'priority' => $priority,
                'estimated_hours' => $estimatedHours,
                'actual_hours' => $actualHours,
                'parent_task_id' => null,
                'is_milestone' => $isMilestone,
                'is_deleted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($subtasks as [$subTitle, $subTaskType, $subAssignedTo, $subStartDate, $subDueDate, $subStatus, $subProgress, $subPriority, $subEstimatedHours, $subActualHours]) {
                DB::table('tasks')->insert([
                    'project_id' => $projectId,
                    'title' => $subTitle,
                    'description' => null,
                    'task_type' => $subTaskType,
                    'assigned_to' => $subAssignedTo,
                    'assigned_by' => $users['project_officer'],
                    'start_date' => $subStartDate,
                    'due_date' => $subDueDate,
                    'completion_date' => $subStatus === 'completed' ? $subDueDate : null,
                    'status' => $subStatus,
                    'progress_percentage' => $subProgress,
                    'priority' => $subPriority,
                    'estimated_hours' => $subEstimatedHours,
                    'actual_hours' => $subActualHours,
                    'parent_task_id' => $taskId,
                    'is_milestone' => false,
                    'is_deleted' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($previousTaskId) {
                DB::table('task_dependencies')->insert([
                    'task_id' => $taskId,
                    'depends_on_task_id' => $previousTaskId,
                    'dependency_type' => 'finish_to_start',
                    'created_at' => $now,
                ]);
            }

            $previousTaskId = $taskId;
        }
    }
}
