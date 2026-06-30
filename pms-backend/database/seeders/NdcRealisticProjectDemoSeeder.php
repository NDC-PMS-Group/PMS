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
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('task_dependencies')->delete();
            DB::table('task_resources')->delete();
            DB::table('project_resources')->delete();
            DB::table('resources')->delete();
            DB::table('task_status_history')->delete();
            DB::table('tasks')->delete();
            DB::table('project_requirements')->delete();
            DB::table('project_members')->delete();
            DB::table('project_member_permissions')->delete();
            DB::table('project_stage_history')->delete();
            DB::table('project_status_history')->delete();
            DB::table('approval_step_records')->delete();
            DB::table('project_approvals')->delete();
            DB::table('project_images')->delete();
            DB::table('document_versions')->delete();
            DB::table('documents')->delete();
            DB::table('project_kpis')->delete();
            DB::table('notifications')->delete();
            DB::table('projects')->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

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


            $superAdminId = $users['sa@gmail.com'];
            $proponentId = $users['alvindalejoyosa30@gmail.com'] ?? $superAdminId;
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
                    'process_track' => 'bdg_investment',
                    'date_of_application' => '2026-01-20',
                    'target_amount_to_raise' => 4850000,
                    'ndc_participation' => 4850000,
                    'ndc_investment_criteria' => json_encode(['developmental', 'sustainable', 'inclusive', 'innovative']),
                    'project_rationale' => 'Standardize NDC project monitoring, approval routing, document control, GIS tagging, and management reporting under one system.',
                    'company_background' => 'National Development Company internal project led by Corporate Planning and Business Development stakeholders.',
                    'target_beneficiaries' => 'NDC project officers, workgroup heads, ManCom, Board reviewers, and proponents.',
                    'expected_benefits' => 'Improved processing visibility, reduced manual tracking, cleaner approval evidence, and stronger project monitoring discipline.',
                    'risk_analysis' => 'Key risks include data migration quality, role-based access alignment, adoption readiness, and privacy controls.',
                    'financial_metrics' => json_encode([
                        'jobs_generated_direct' => 12,
                        'jobs_generated_indirect' => 18,
                        'retained_jobs' => 0,
                        'projected_revenue' => 0,
                        'actual_revenue' => 0,
                        'dividend_remittance' => 0,
                        'gcg_relevance' => true,
                        'gcg_score' => 92,
                        'reportable_to_gcg' => true,
                        'monitoring_frequency' => 'Quarterly',
                        'reporting_period' => 'FY 2026',
                        'monitoring_indicators' => 'Implementation milestones, user adoption, training completion, data migration acceptance, privacy controls, and turnaround-time improvement.',
                        'gcg_metrics' => 'Digital transformation and process efficiency contribution to corporate governance reporting.',
                        'social_impact_notes' => 'Improves NDC project transparency and monitoring discipline for public investment programs.',
                    ]),
                    'issues_problems' => 'Board approval included pilot acceptance and data privacy sign-off conditions.',
                    'next_steps' => 'Complete pilot acceptance, close privacy controls, then proceed to full rollout.',
                    'current_stage_id' => $stages['Implementation & Monitoring'],
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
                    'proponent_email' => 'alvindalejoyosa30@gmail.com',
                    'created_by' => $proponentId,
                    'stage_history' => [
                        [null, 'Intake', 'Statement of intent and procurement request encoded.', '2026-01-20 09:15:00', $proponentId],
                        ['Intake', 'Requirements', 'Technical and completeness review started.', '2026-02-03 10:30:00', $projectOfficerId],
                        ['Requirements', 'Management Review', 'Submitted for SOI approval routing.', '2026-02-18 14:00:00', $workgroupHeadId],
                        ['Management Review', 'Implementation & Monitoring', 'Approval completed with rollout conditions.', '2026-03-02 08:45:00', $boardId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'Initial project record submitted by proponent.', '2026-01-20 09:15:00', $proponentId],
                        ['Submitted', 'Requirements Requested', 'Project Officer opened completeness review.', '2026-02-03 10:30:00', $projectOfficerId],
                        ['Requirements Requested', 'For Board Approval', 'Elevated after Workgroup and ManCom endorsements.', '2026-02-25 16:20:00', $mancomId],
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
                            [2, $projectOfficerId, 'approved', 'Completeness check passed for SOI processing.', null, '2026-02-18 14:20:00', '2026-02-20 11:30:00'],
                            [3, $projectOfficerId, 'approved', 'Technical scope is consistent with PMS requirements and implementation timeline.', null, '2026-02-20 11:30:00', '2026-02-22 13:10:00'],
                            [4, $workgroupHeadId, 'approved', 'Workgroup endorses procurement subject to change management plan.', null, '2026-02-22 13:10:00', '2026-02-24 15:45:00'],
                            [5, $mancomId, 'approved', 'Recommended for Board action with quarterly progress reporting.', null, '2026-02-24 15:45:00', '2026-02-25 16:20:00'],
                            [6, $boardId, 'approved_with_conditions', 'Approved subject to pilot acceptance and data privacy compliance sign-off.', 'Submit pilot acceptance report before full rollout.', '2026-02-25 16:20:00', '2026-03-01 17:10:00'],
                            [7, $projectOfficerId, 'approved', 'Agreement and rollout readiness confirmed for implementation.', null, '2026-03-01 17:10:00', '2026-03-02 08:45:00'],
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
                    'process_track' => 'spg_jv',
                    'date_of_application' => '2026-02-12',
                    'target_amount_to_raise' => 185000000,
                    'ndc_participation' => 60000000,
                    'ndc_investment_criteria' => json_encode(['developmental', 'sustainable', 'inclusive']),
                    'project_rationale' => 'Address cold-chain gaps that reduce farm-gate value and increase post-harvest losses in Central Luzon.',
                    'company_background' => 'Luzon Agro-Logistics Consortium is a proposed JV group with cold storage, logistics, and cooperative aggregation experience.',
                    'target_beneficiaries' => 'Farmers, cooperatives, market consolidators, and provincial food logistics stakeholders.',
                    'expected_benefits' => 'Reduced spoilage, improved logistics resilience, additional jobs, and higher market access for high-value crop producers.',
                    'risk_analysis' => 'Main risks are utilization assumptions, power availability, land access, tariff affordability, and JV partner capability.',
                    'financial_metrics' => json_encode([
                        'jobs_generated_direct' => 86,
                        'jobs_generated_indirect' => 240,
                        'retained_jobs' => 120,
                        'projected_revenue' => 38500000,
                        'actual_revenue' => 0,
                        'dividend_remittance' => 0,
                        'gcg_relevance' => true,
                        'gcg_score' => 88,
                        'reportable_to_gcg' => true,
                        'monitoring_frequency' => 'Quarterly',
                        'reporting_period' => 'FY 2026',
                        'monitoring_indicators' => 'Cold storage utilization, post-harvest loss reduction, jobs generated, cooperative participation, revenue, and covenant compliance.',
                        'gcg_metrics' => 'Developmental impact, financial sustainability, and agriculture logistics support indicators.',
                        'social_impact_notes' => 'Supports farmer income stability, market access, and food logistics resilience in Central Luzon.',
                    ]),
                    'issues_problems' => 'Sensitivity analysis on utilization rates is still needed before ManCom action.',
                    'next_steps' => 'Finish recommendation memo and present ManCom options.',
                    'current_stage_id' => $stages['Management Review'],
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
                        [null, 'Intake', 'JV concept note received and encoded.', '2026-02-12 09:00:00', $proponentId],
                        ['Intake', 'Due Diligence', 'Project Officer accepted for preliminary due diligence.', '2026-02-19 13:30:00', $projectOfficerId],
                        ['Due Diligence', 'Management Review', 'Due diligence pack elevated to Workgroup and ManCom SOI route.', '2026-04-28 15:00:00', $workgroupHeadId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'JV proposal submitted by external proponent.', '2026-02-12 09:00:00', $proponentId],
                        ['Submitted', 'Due Diligence Ongoing', 'Completeness check passed.', '2026-02-19 13:30:00', $projectOfficerId],
                        ['Due Diligence Ongoing', 'For Workgroup Review', 'Financial model and site validation package completed.', '2026-04-20 10:45:00', $projectOfficerId],
                        ['For Workgroup Review', 'For ManCom Review', 'Workgroup endorsed for ManCom deliberation.', '2026-04-28 15:00:00', $workgroupHeadId],
                    ],
                    'approval' => [
                        'overall_status' => 'for_approval',
                        'current_step_order' => 5,
                        'started_at' => '2026-04-20 10:45:00',
                        'completed_at' => null,
                        'records' => [
                            [1, $proponentId, 'approved', 'Complete JV proposal and supporting farm-gate demand data submitted.', null, '2026-04-20 10:45:00', '2026-04-20 11:00:00'],
                            [2, $projectOfficerId, 'approved', 'Initial valuation and risk screen are sufficient for committee review.', null, '2026-04-20 11:00:00', '2026-04-23 16:00:00'],
                            [3, $projectOfficerId, 'approved', 'Due diligence report, site validation, and financial model are ready for management review.', null, '2026-04-23 16:00:00', '2026-04-26 14:30:00'],
                            [4, $workgroupHeadId, 'approved', 'Endorsed with request for sensitivity analysis on utilization rates.', null, '2026-04-26 14:30:00', '2026-04-28 15:00:00'],
                            [5, null, 'pending', 'Awaiting ManCom deliberation.', null, '2026-04-28 15:00:00', null],
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
                    'process_track' => 'bdg_investment',
                    'date_of_application' => '2026-03-10',
                    'target_amount_to_raise' => 45000000,
                    'ndc_participation' => 15000000,
                    'ndc_investment_criteria' => json_encode(['pioneering', 'developmental', 'sustainable', 'inclusive']),
                    'project_rationale' => 'Evaluate SVF participation in renewable microgrid operations for underserved island communities.',
                    'company_background' => 'IslaGrid Renewables Inc. is an early-stage microgrid operator pursuing island electrification projects.',
                    'target_beneficiaries' => 'Households, public facilities, small businesses, and LGUs in island communities.',
                    'expected_benefits' => 'Cleaner power, improved energy reliability, local enterprise support, and reduced diesel dependence.',
                    'risk_analysis' => 'Returned for missing DOE endorsement, updated cap table, and revised demand assumptions.',
                    'financial_metrics' => json_encode([
                        'jobs_generated_direct' => 24,
                        'jobs_generated_indirect' => 95,
                        'retained_jobs' => 18,
                        'projected_revenue' => 12400000,
                        'actual_revenue' => 0,
                        'dividend_remittance' => 0,
                        'gcg_relevance' => true,
                        'gcg_score' => 81,
                        'reportable_to_gcg' => true,
                        'monitoring_frequency' => 'Monthly',
                        'reporting_period' => 'FY 2026',
                        'monitoring_indicators' => 'Households served, uptime, tariff sensitivity, permitting completion, jobs generated, renewable generation, and diesel displacement.',
                        'gcg_metrics' => 'Innovation, sustainability, and regional development indicators for SVF monitoring.',
                        'social_impact_notes' => 'Targets reliable renewable power for island households, SMEs, LGUs, and public facilities.',
                    ]),
                    'issues_problems' => 'Completeness deficiencies prevent IC and ManCom review.',
                    'next_steps' => 'Proponent must resubmit DOE, cap table, and demand forecast evidence.',
                    'current_stage_id' => $stages['Intake'],
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
                        [null, 'Intake', 'SVF investment application received.', '2026-03-10 10:10:00', $proponentId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'Application submitted through proponent account.', '2026-03-10 10:10:00', $proponentId],
                        ['Submitted', 'Requirements Requested', 'Project Officer started completeness screening.', '2026-03-12 09:30:00', $projectOfficerId],
                        ['Requirements Requested', 'Returned for Revision', 'Returned for missing DOE endorsement, updated cap table, and interconnection assumptions.', '2026-03-19 16:40:00', $projectOfficerId],
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
                [
                    'project_code' => 'NDC-TRAD-2026-004',
                    'title' => 'Davao Food Terminal Expansion',
                    'description' => 'Rehabilitation, cold-chain capacity enhancement, and logistics integration of the food terminal facility in Davao to support regional distribution for high-value agricultural crops.',
                    'project_type_id' => $types['Infrastructure'],
                    'industry_id' => $industries['Agriculture'],
                    'sector_id' => $sectors['Government'],
                    'investment_type_id' => $investments['Grant'],
                    'funding_source_id' => $fundingSources['Government Budget'],
                    'estimated_cost' => 120000000,
                    'actual_cost' => null,
                    'process_track' => 'spg_traditional',
                    'date_of_application' => '2026-03-15',
                    'target_amount_to_raise' => 120000000,
                    'ndc_participation' => 120000000,
                    'ndc_investment_criteria' => json_encode(['developmental', 'sustainable', 'inclusive']),
                    'project_rationale' => 'Provide post-harvest cooling and consolidation support for vegetable farmers in the Davao region.',
                    'company_background' => 'National Development Company asset development program in partnership with local agricultural cooperatives.',
                    'target_beneficiaries' => 'Smallholder vegetable farmers, aggregators, and consumers in Davao and neighboring cities.',
                    'expected_benefits' => 'Reduce post-harvest spoilage by 25%, increase farmer income stability, and optimize food distribution logistics.',
                    'risk_analysis' => 'Construction delays, contractor capacity, power tariff fluctuations, and cooperative adoption rates.',
                    'financial_metrics' => json_encode([
                        'jobs_generated_direct' => 45,
                        'jobs_generated_indirect' => 150,
                        'retained_jobs' => 20,
                        'projected_revenue' => 18000000,
                        'actual_revenue' => 0,
                        'dividend_remittance' => 0,
                        'gcg_relevance' => true,
                        'gcg_score' => 85,
                        'reportable_to_gcg' => true,
                        'monitoring_frequency' => 'Quarterly',
                        'reporting_period' => 'FY 2026',
                        'monitoring_indicators' => 'Construction progress, equipment installation, jobs generated, cooperative registration, and utility connection milestones.',
                        'gcg_metrics' => 'Infrastructure support and regional development indicators.',
                        'social_impact_notes' => 'Reduces logistics costs and increases food supply stability in Davao Region.',
                    ]),
                    'issues_problems' => 'Awaiting signing of the tripartite agreement between NDC, local government, and cooperative federation.',
                    'next_steps' => 'Obtain legal clearance from OGCC, then route for tripartite agreement signing.',
                    'current_stage_id' => $stages['Agreement & Fund Release'],
                    'status_id' => $statuses['For Agreement Signing'],
                    'proposal_date' => '2026-03-15',
                    'start_date' => null,
                    'target_completion_date' => '2027-06-30',
                    'location_address' => 'Davao City Food Terminal, Barangay Daliao, Toril District, Davao City, Davao del Sur',
                    'location_region_code' => '110000000',
                    'location_region_name' => 'Region XI - Davao Region',
                    'location_province_code' => '112400000',
                    'location_province_name' => 'Davao del Sur',
                    'location_city_code' => '112402000',
                    'location_city_name' => 'Davao City',
                    'location_barangay_code' => '112402030',
                    'location_barangay_name' => 'Daliao',
                    'location_street' => 'Toril District terminal area',
                    'location_lat' => 7.0124,
                    'location_lng' => 125.4952,
                    'project_officer_id' => $projectOfficerId,
                    'workgroup_head_id' => $workgroupHeadId,
                    'proponent_name' => 'Davao Agri-Hub Cooperative Federation',
                    'proponent_contact' => '+63 82 299 0188',
                    'proponent_email' => 'davaoagrihub@coop.test',
                    'created_by' => $proponentId,
                    'stage_history' => [
                        [null, 'Intake', 'LOI and project concept encoded.', '2026-03-15 08:30:00', $proponentId],
                        ['Intake', 'Requirements', 'Completeness screening check started.', '2026-03-25 10:00:00', $projectOfficerId],
                        ['Requirements', 'Due Diligence', 'Preliminary validation and technical design review initiated.', '2026-04-10 14:00:00', $projectOfficerId],
                        ['Due Diligence', 'Management Review', 'Endorsed for ManCom and Board approval.', '2026-05-15 11:30:00', $workgroupHeadId],
                        ['Management Review', 'Board Approval', 'Board approved terminal rehabilitation budget.', '2026-06-02 16:00:00', $boardId],
                        ['Board Approval', 'Agreement & Fund Release', 'Tripartite agreement draft prepared and sent for review.', '2026-06-10 09:15:00', $projectOfficerId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'Initial proposal submitted.', '2026-03-15 08:30:00', $proponentId],
                        ['Submitted', 'Requirements Received', 'Checklist items verified.', '2026-03-25 10:00:00', $projectOfficerId],
                        ['Requirements Received', 'Due Diligence Ongoing', 'Site survey and design reviews completed.', '2026-04-30 16:30:00', $projectOfficerId],
                        ['Due Diligence Ongoing', 'For Workgroup Review', 'Draft Board paper compiled.', '2026-05-15 11:30:00', $workgroupHeadId],
                        ['For Workgroup Review', 'Approved', 'Board approval granted with tripartite signing condition.', '2026-06-02 16:00:00', $boardId],
                        ['Approved', 'For Agreement Signing', 'Tripartite agreement routed to legal and partners.', '2026-06-10 09:15:00', $projectOfficerId],
                    ],
                    'approval' => [
                        'overall_status' => 'approved',
                        'current_step_order' => null,
                        'started_at' => '2026-05-15 11:30:00',
                        'completed_at' => '2026-06-02 16:00:00',
                        'records' => [
                            [1, $proponentId, 'approved', 'Rehabilitation proposal package submitted.', null, '2026-05-15 11:30:00', '2026-05-15 11:45:00'],
                            [2, $projectOfficerId, 'approved', 'Technical specifications and budget estimates verified.', null, '2026-05-15 11:45:00', '2026-05-20 14:00:00'],
                            [3, $projectOfficerId, 'approved', 'Due diligence report and engineering plans endorsed.', null, '2026-05-15 11:45:00', '2026-05-24 16:30:00'],
                            [4, $workgroupHeadId, 'approved', 'Endorsed to ManCom.', null, '2026-05-24 16:30:00', '2026-05-28 11:00:00'],
                            [5, $mancomId, 'approved', 'Recommended for Board action.', null, '2026-05-28 11:00:00', '2026-05-30 10:15:00'],
                            [6, $boardId, 'approved', 'Approved terminal rehabilitation budget.', null, '2026-05-30 10:15:00', '2026-06-02 16:00:00'],
                            [7, $projectOfficerId, 'approved', 'Draft JVA prepared and sent to OGCC.', null, '2026-06-02 16:00:00', '2026-06-10 09:15:00'],
                        ],
                    ],
                    'tasks' => [
                        ['Tripartite agreement drafting and review', 'Draft agreement between NDC, local government, and cooperative federation; submit to OGCC.', 'legal', $projectOfficerId, '2026-06-10', '2026-06-30', 'in_progress', 60, 'high', 32, 20, false],
                        ['Secure OGCC contract review and clearance', 'Liaise with OGCC to finalize legal comments on the tripartite agreement.', 'compliance', $projectOfficerId, '2026-07-01', '2026-07-20', 'pending', 0, 'urgent', 24, null, false],
                        ['Tripartite signing ceremony and notarization', 'Organize execution of agreement and distribute notarized copies.', 'milestone', $workgroupHeadId, '2026-07-21', '2026-07-31', 'pending', 0, 'high', 16, null, true],
                    ],
                ],
                [
                    'project_code' => 'NDC-OWN-2026-005',
                    'title' => 'NDC Building Solarization & Green Certification',
                    'description' => 'Installation of roof-mounted solar photovoltaic systems, smart energy monitoring systems, and green building certification for the NDC Building in Makati.',
                    'project_type_id' => $types['Infrastructure'],
                    'industry_id' => $industries['Energy'],
                    'sector_id' => $sectors['Government'],
                    'investment_type_id' => $investments['Grant'],
                    'funding_source_id' => $fundingSources['Government Budget'],
                    'estimated_cost' => 15000000,
                    'actual_cost' => null,
                    'process_track' => 'spg_ndc_own',
                    'date_of_application' => '2026-04-02',
                    'target_amount_to_raise' => 15000000,
                    'ndc_participation' => 15000000,
                    'ndc_investment_criteria' => json_encode(['developmental', 'sustainable', 'innovative']),
                    'project_rationale' => 'Promote sustainability and reduce operational electricity costs at the NDC headquarters.',
                    'company_background' => 'Internal asset improvement project managed by the SPG technical group.',
                    'target_beneficiaries' => 'NDC administration, building tenants, and environmental sustainability stakeholders.',
                    'expected_benefits' => 'Generate 30% of building energy from solar, reduce greenhouse emissions, and earn EDGE green certification.',
                    'risk_analysis' => 'Roof load-bearing limits, weather disruptions, system integration risks, and certification delays.',
                    'financial_metrics' => json_encode([
                        'jobs_generated_direct' => 15,
                        'jobs_generated_indirect' => 25,
                        'retained_jobs' => 5,
                        'projected_revenue' => 1200000,
                        'actual_revenue' => 0,
                        'dividend_remittance' => 0,
                        'gcg_relevance' => true,
                        'gcg_score' => 90,
                        'reportable_to_gcg' => true,
                        'monitoring_frequency' => 'Quarterly',
                        'reporting_period' => 'FY 2026',
                        'monitoring_indicators' => 'Solar panel output, grid energy reduction, operational expenditure savings, and certification credits.',
                        'gcg_metrics' => 'Green governance and process efficiency indicators.',
                        'social_impact_notes' => 'Models government leadership in green building adoption.',
                    ]),
                    'issues_problems' => 'Structural audit of building roof load-bearing capacity needs to be completed.',
                    'next_steps' => 'Procure third-party structural engineer to finalize roof audit report.',
                    'current_stage_id' => $stages['Due Diligence'],
                    'status_id' => $statuses['Due Diligence Ongoing'],
                    'proposal_date' => '2026-04-02',
                    'start_date' => null,
                    'target_completion_date' => '2026-12-15',
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
                    'proponent_name' => 'NDC General Services Division',
                    'proponent_contact' => '+63 2 8840 4830',
                    'proponent_email' => 'gsd@ndc.gov.ph',
                    'created_by' => $superAdminId,
                    'stage_history' => [
                        [null, 'Intake', 'Solarization concept note registered.', '2026-04-02 09:00:00', $superAdminId],
                        ['Intake', 'Due Diligence', 'Site survey and preliminary design checks started.', '2026-04-20 10:30:00', $projectOfficerId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'Internal proposal registered.', '2026-04-02 09:00:00', $superAdminId],
                        ['Submitted', 'Due Diligence Ongoing', 'Detailed engineering design study initiated.', '2026-04-20 10:30:00', $projectOfficerId],
                    ],
                    'approval' => [
                        'overall_status' => 'for_approval',
                        'current_step_order' => 3,
                        'started_at' => '2026-04-20 10:30:00',
                        'completed_at' => null,
                        'records' => [
                            [1, $superAdminId, 'approved', 'Concept note and initial feasibility check approved.', null, '2026-04-20 10:30:00', '2026-04-20 10:45:00'],
                            [2, $projectOfficerId, 'approved', 'Preliminary energy baseline and structural requirements documented.', null, '2026-04-20 10:45:00', '2026-04-25 15:00:00'],
                            [3, null, 'pending', 'Awaiting structural roof audit completion.', null, '2026-04-25 15:00:00', null],
                        ],
                    ],
                    'tasks' => [
                        ['Perform roof structural load audit', 'Engage structural engineer to verify if roof can carry solar panel arrays safely.', 'due_diligence', $projectOfficerId, '2026-04-26', '2026-06-30', 'in_progress', 45, 'high', 40, 18, false],
                        ['Finalize solar PV design and billing estimates', 'Create detailed array layout, inverter wiring, and utility net-metering interface plans.', 'due_diligence', $projectOfficerId, '2026-05-10', '2026-07-15', 'in_progress', 30, 'normal', 36, 10, false],
                        ['Procurement of solar installation contractor', 'Draft bidding terms and publish procurement package for solar panel system.', 'procurement', $projectOfficerId, '2026-07-16', '2026-08-30', 'pending', 0, 'high', 48, null, false],
                    ],
                ],
                [
                    'project_code' => 'NDC-DIV-2026-006',
                    'title' => 'Divestment of NDC Equity in Batangas Land Company',
                    'description' => 'Valuation, bidding, and share transfer processing for the divestment of NDC\'s legacy minority shareholding in Batangas Land Company Inc. (BLCI).',
                    'project_type_id' => $types['Business Development'],
                    'industry_id' => $industries['Real Estate'],
                    'sector_id' => $sectors['Private'],
                    'investment_type_id' => $investments['Equity'],
                    'funding_source_id' => $fundingSources['Private Investors'],
                    'estimated_cost' => 85000000,
                    'actual_cost' => null,
                    'process_track' => 'divestment',
                    'date_of_application' => '2026-01-15',
                    'target_amount_to_raise' => 85000000,
                    'ndc_participation' => 85000000,
                    'ndc_investment_criteria' => json_encode(['developmental']),
                    'project_rationale' => 'Fulfill government mandate to divest non-performing or legacy minority stakes and redirect capital to strategic investments.',
                    'company_background' => 'Batangas Land Company is a legacy property-holding firm with NDC minority ownership.',
                    'target_beneficiaries' => 'National treasury, buyers, and NDC asset management stakeholders.',
                    'expected_benefits' => 'Release 85M PHP in cash reserves, simplify NDC asset portfolio, and close legacy audit compliance items.',
                    'risk_analysis' => 'Valuation disagreements, legal disputes regarding pre-emptive rights, and low buyer turnout.',
                    'financial_metrics' => json_encode([
                        'jobs_generated_direct' => 0,
                        'jobs_generated_indirect' => 0,
                        'retained_jobs' => 0,
                        'projected_revenue' => 85000000,
                        'actual_revenue' => 0,
                        'dividend_remittance' => 0,
                        'gcg_relevance' => true,
                        'gcg_score' => 87,
                        'reportable_to_gcg' => true,
                        'monitoring_frequency' => 'Quarterly',
                        'reporting_period' => 'FY 2026',
                        'monitoring_indicators' => 'Valuation completion, Board approval, bidding publication, contract execution, and collection completion.',
                        'gcg_metrics' => 'Asset recycling and regulatory compliance indicators.',
                        'social_impact_notes' => 'Supports efficient capital deployment for public-private growth partnerships.',
                    ]),
                    'issues_problems' => 'Currently performing third-party financial asset appraisal to set the minimum bid price.',
                    'next_steps' => 'Receive final asset appraisal report from appraiser and draft divestment recommendation for ManCom.',
                    'current_stage_id' => $stages['Due Diligence'],
                    'status_id' => $statuses['Due Diligence Ongoing'],
                    'proposal_date' => '2026-01-15',
                    'start_date' => null,
                    'target_completion_date' => '2026-11-30',
                    'location_address' => 'BLCI Port Facility, Barangay Calicanto, Batangas City, Batangas',
                    'location_region_code' => '040000000',
                    'location_region_name' => 'Region IV-A - CALABARZON',
                    'location_province_code' => '041000000',
                    'location_province_name' => 'Batangas',
                    'location_city_code' => '041005000',
                    'location_city_name' => 'Batangas City',
                    'location_barangay_code' => '041005008',
                    'location_barangay_name' => 'Calicanto',
                    'location_street' => 'Diversion Road commercial hub',
                    'location_lat' => 13.7654,
                    'location_lng' => 121.0542,
                    'project_officer_id' => $projectOfficerId,
                    'workgroup_head_id' => $workgroupHeadId,
                    'proponent_name' => 'NDC Asset Management Group',
                    'proponent_contact' => '+63 2 8840 4830',
                    'proponent_email' => 'amg@ndc.gov.ph',
                    'created_by' => $superAdminId,
                    'stage_history' => [
                        [null, 'Intake', 'Divestment recommendation memo submitted by Asset Management.', '2026-01-15 14:00:00', $superAdminId],
                        ['Intake', 'Due Diligence', 'Legal and financial due diligence checks launched.', '2026-02-05 09:30:00', $projectOfficerId],
                    ],
                    'status_history' => [
                        [null, 'Submitted', 'AMG divestment proposal registered.', '2026-01-15 14:00:00', $superAdminId],
                        ['Submitted', 'Due Diligence Ongoing', 'Procured third-party appraisal service.', '2026-02-05 09:30:00', $projectOfficerId],
                    ],
                    'approval' => [
                        'overall_status' => 'for_approval',
                        'current_step_order' => 3,
                        'started_at' => '2026-02-05 09:30:00',
                        'completed_at' => null,
                        'records' => [
                            [1, $superAdminId, 'approved', 'Authority to proceed with BLCI divestment plan granted.', null, '2026-02-05 09:30:00', '2026-02-05 09:45:00'],
                            [2, $projectOfficerId, 'approved', 'Legal title and minority stake rights confirmed.', null, '2026-02-05 09:45:00', '2026-02-12 11:00:00'],
                            [3, null, 'pending', 'Awaiting asset appraisal report to establish bid price floor.', null, '2026-02-12 11:00:00', null],
                        ],
                    ],
                    'tasks' => [
                        [
                            '1. Start divestment and due diligence',
                            'Begin divestment proceedings and complete legal and financial due diligence.',
                            'divestment',
                            $projectOfficerId,
                            '2026-02-06',
                            '2026-07-15',
                            'in_progress',
                            80,
                            'urgent',
                            80,
                            64,
                            true,
                            [
                                ['Record ManCom-approved divestment recommendation or external offer', 'divestment', $projectOfficerId, '2026-02-06', '2026-02-12', 'completed', 100, 'high', 8, 8],
                                ['Complete legal due diligence and legal memo', 'divestment', $projectOfficerId, '2026-02-13', '2026-03-10', 'completed', 100, 'high', 24, 22],
                                ['Complete financial due diligence, asset appraisal, and pricing basis', 'divestment', $projectOfficerId, '2026-03-11', '2026-07-15', 'in_progress', 65, 'urgent', 56, 34],
                            ],
                        ],
                        [
                            '2. ManCom approval of divestment terms',
                            'Prepare proposed terms and conditions of share or asset transfer for ManCom approval.',
                            'approval',
                            $workgroupHeadId,
                            '2026-07-16',
                            '2026-08-30',
                            'pending',
                            0,
                            'high',
                            40,
                            null,
                            true,
                            [
                                ['Draft terms and conditions of transfer with Legal and Finance', 'approval', $projectOfficerId, '2026-07-16', '2026-08-05', 'pending', 0, 'high', 18, null],
                                ['Prepare ManCom paper and presentation', 'approval', $workgroupHeadId, '2026-08-06', '2026-08-20', 'pending', 0, 'high', 16, null],
                                ['Record ManCom decision and revisions if any', 'approval', $workgroupHeadId, '2026-08-21', '2026-08-30', 'pending', 0, 'high', 6, null],
                            ],
                        ],
                        [
                            '3. Board approval of divestment',
                            'Secure Board decision on the terms and conditions of divestment.',
                            'approval',
                            $boardId,
                            '2026-09-01',
                            '2026-09-30',
                            'pending',
                            0,
                            'high',
                            32,
                            null,
                            true,
                            [
                                ['Prepare Board paper and Secretary Certificate requirements', 'approval', $workgroupHeadId, '2026-09-01', '2026-09-15', 'pending', 0, 'high', 16, null],
                                ['Present divestment terms to Board of Directors', 'approval', $boardId, '2026-09-16', '2026-09-25', 'pending', 0, 'high', 10, null],
                                ['Record Board decision and required adjustments', 'approval', $boardId, '2026-09-26', '2026-09-30', 'pending', 0, 'high', 6, null],
                            ],
                        ],
                        [
                            '4. Execute divestment transfer and collection',
                            'Complete documentary requirements, payments, receipts, and transfer of shares/assets.',
                            'divestment',
                            $projectOfficerId,
                            '2026-10-01',
                            '2026-11-30',
                            'pending',
                            0,
                            'high',
                            48,
                            null,
                            true,
                            [
                                ['Prepare and sign transfer documents', 'divestment', $projectOfficerId, '2026-10-01', '2026-10-31', 'pending', 0, 'high', 20, null],
                                ['Collect payments and issue receipts', 'divestment', $projectOfficerId, '2026-11-01', '2026-11-20', 'pending', 0, 'high', 16, null],
                                ['Record final transfer of shares/assets and close divestment file', 'divestment', $projectOfficerId, '2026-11-21', '2026-11-30', 'pending', 0, 'high', 12, null],
                            ],
                        ],
                    ],
                ],
            ];

            foreach ($projects as $projectData) {
                $track = $projectData['process_track'] ?? 'bdg_investment';
                $isSvf = str_contains($projectData['project_code'], 'SVF');
                $wfName = match($track) {
                    'bdg_investment' => $isSvf ? 'NDC SVF Investment Approval' : 'NDC BDG Investment Approval',
                    'spg_jv' => 'SPG Joint Venture Project Approval',
                    'spg_traditional' => 'SPG Traditional Equity Funding Approval',
                    'spg_ndc_own' => 'SPG NDC-Owned Project Approval',
                    'divestment' => 'NDC Divestment Approval',
                    default => 'NDC BDG Investment Approval',
                };

                $projectWorkflowId = DB::table('approval_workflows')
                    ->where('name', $wfName)
                    ->value('id');

                $projectSteps = DB::table('approval_steps')
                    ->where('workflow_id', $projectWorkflowId)
                    ->orderBy('step_order')
                    ->get()
                    ->keyBy('step_order');

                $this->seedProject(
                    $projectData,
                    $now,
                    $projectWorkflowId,
                    $projectSteps,
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

        $this->command?->info('Seeded 6 realistic NDC demo projects with tasks, histories, and SOI workflow data.');
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
        DB::table('project_requirements')->where('project_id', $projectId)->delete();
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

        $this->seedRequirements(
            $projectId,
            $data['process_track'] ?? 'bdg_investment',
            (bool) ($data['is_svf'] ?? str_contains($data['project_code'], 'SVF')),
            $approval['overall_status'],
            $now
        );

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

    private function seedRequirements(int $projectId, string $track, bool $isSvf, string $approvalStatus, Carbon $now): void
    {
        // Fetch from default_requirements
        $defaults = DB::table('default_requirements')
            ->where('track', $track)
            ->where(function ($query) use ($isSvf) {
                if (!$isSvf) {
                    $query->where('svf_only', false);
                }
            })
            ->orderBy('sort_order')
            ->get();

        // Get current project's stage sequence order
        $projectStageId = DB::table('projects')->where('id', $projectId)->value('current_stage_id');
        $currentStageOrder = DB::table('project_stages')->where('id', $projectStageId)->value('sequence_order') ?: 1;

        // Map SOI section to stage sequence orders
        $soiSectionOrders = [
            'intake' => 1,
            'requirements' => 2,
            'due_diligence' => 3,
            'management_review' => 4,
            'board_approval' => 5,
            'agreement_fund_release' => 6,
            'implementation_monitoring' => 7,
            'post_investment_strategy' => 8,
            'divestment' => 9,
            'completion' => 10,
        ];

        $completeStatuses = ['approved_with_conditions', 'approved', 'completed'];

        foreach ($defaults as $item) {
            $itemSectionOrder = $soiSectionOrders[$item->soi_section] ?? 1;

            // Determine if the requirement should be received
            $received = false;
            if ($itemSectionOrder < $currentStageOrder) {
                // Previous stage requirements are always received
                $received = true;
            } elseif ($itemSectionOrder == $currentStageOrder) {
                // If it is the current stage, check status or mark some as received/pending
                if (in_array($approvalStatus, $completeStatuses, true)) {
                    $received = true;
                } else {
                    // For active stage, first half of requirements are received
                    $received = ($item->sort_order % 20 === 0);
                }
            }

            DB::table('project_requirements')->insert([
                'project_id' => $projectId,
                'group_name' => $item->group_name,
                'item_name' => $item->item_name,
                'source_document' => $item->source_document,
                'track' => $item->track,
                'owner_type' => $item->owner_type,
                'visibility' => $item->visibility,
                'soi_section' => $item->soi_section,
                'gate_step' => $item->gate_step,
                'is_required' => $item->is_required,
                'is_applicable' => true,
                'svf_only' => $item->svf_only,
                'status' => $received ? 'received' : 'pending',
                'received_at' => $received ? $now->copy()->subDays(max(1, 15 - ($item->sort_order / 10))) : null,
                'remarks' => $received ? 'Seeded as received based on demo project track guidelines.' : null,
                'sort_order' => $item->sort_order,
                'template_file_path' => $item->template_file_path,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
