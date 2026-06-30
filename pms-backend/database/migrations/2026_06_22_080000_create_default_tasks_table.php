<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('track', 80)->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('task_type', 50)->nullable();
            $table->string('soi_section', 80)->index();
            $table->string('assigned_role', 50)->default('Project Officer'); // 'Project Officer', 'Proponent', 'Workgroup Head'
            $table->integer('days')->default(0);
            $table->string('priority', 20)->default('medium');
            $table->boolean('is_milestone')->default(false);
            $table->string('parent_task_title', 255)->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedDefaultTasks();
    }

    public function down(): void
    {
        Schema::dropIfExists('default_tasks');
    }

    private function seedDefaultTasks(): void
    {
        $now = now();
        $records = [];

        // Track data structures matching ProjectController's lifecyclePhasesForTrack
        // Each entry is: [track, title, description, task_type, soi_section, assigned_role, days, priority, is_milestone, parent_task_title, sort_order]

        // 1. SPG NDC Own Track Tasks
        $spgNdcOwnTasks = [
            // Phase 1
            ['spg_ndc_own', '1. Project concept and ManCom instruction', 'Prepare the NDC-owned project concept and secure ManCom approval to proceed.', 'intake', 'intake', 'Project Officer', 150, 'critical', true, null, 10],
            ['spg_ndc_own', 'Prepare project concept from ManCom instruction', null, 'intake', 'intake', 'Project Officer', 90, 'critical', false, '1. Project concept and ManCom instruction', 11],
            ['spg_ndc_own', 'Present project concept to ManCom for decision', null, 'intake', 'intake', 'Workgroup Head', 150, 'urgent', false, '1. Project concept and ManCom instruction', 12],
            ['spg_ndc_own', 'Record ManCom directives and required revisions', null, 'intake', 'intake', 'Project Officer', 150, 'high', false, '1. Project concept and ManCom instruction', 13],

            // Phase 2
            ['spg_ndc_own', '2. Consultancy procurement and study', 'Prepare TOR/MR, procure consultancy services, and complete the required study.', 'due_diligence', 'due_diligence', 'Project Officer', 360, 'high', true, null, 20],
            ['spg_ndc_own', 'Prepare TOR and budget estimates for consultancy services', null, 'due_diligence', 'due_diligence', 'Project Officer', 240, 'high', false, '2. Consultancy procurement and study', 21],
            ['spg_ndc_own', 'Prepare and approve Materials Requisition', null, 'due_diligence', 'due_diligence', 'Project Officer', 247, 'high', false, '2. Consultancy procurement and study', 22],
            ['spg_ndc_own', 'Conduct public bidding for consultancy services', null, 'due_diligence', 'due_diligence', 'Project Officer', 427, 'high', false, '2. Consultancy procurement and study', 23],
            ['spg_ndc_own', 'Complete study from NTP to final report', null, 'due_diligence', 'due_diligence', 'Project Officer', 547, 'high', false, '2. Consultancy procurement and study', 24],

            // Phase 3
            ['spg_ndc_own', '3. Study evaluation and management decision', 'Evaluate the study, prepare recommendations, and present the proposed project to ManCom.', 'approval', 'management_review', 'Workgroup Head', 610, 'high', true, null, 30],
            ['spg_ndc_own', 'Evaluate study and prepare presentation/recommendation', null, 'approval', 'management_review', 'Project Officer', 577, 'high', false, '3. Study evaluation and management decision', 31],
            ['spg_ndc_own', 'Present proposed project for ManCom approval and Board endorsement', null, 'approval', 'management_review', 'Workgroup Head', 607, 'high', false, '3. Study evaluation and management decision', 32],
            ['spg_ndc_own', 'Complete subsequent ManCom presentation if required', null, 'approval', 'management_review', 'Workgroup Head', 610, 'medium', false, '3. Study evaluation and management decision', 33],

            // Phase 4
            ['spg_ndc_own', '4. Board approval', 'Secure Board approval before detailed engineering and construction procurement.', 'approval', 'board_approval', 'Workgroup Head', 640, 'high', true, null, 40],
            ['spg_ndc_own', 'Present proposed project to Board of Directors', null, 'approval', 'board_approval', 'Workgroup Head', 640, 'high', false, '4. Board approval', 41],
            ['spg_ndc_own', 'Record Board approval, resolution, or Secretary Certificate', null, 'approval', 'board_approval', 'Workgroup Head', 640, 'high', false, '4. Board approval', 42],

            // Phase 5
            ['spg_ndc_own', '5. DED and construction procurement', 'Procure DED, conduct construction bidding, and sign construction agreement.', 'fund_release', 'agreement_fund_release', 'Project Officer', 920, 'high', true, null, 50],
            ['spg_ndc_own', 'Prepare TOR and MR for DED public bidding', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 730, 'high', false, '5. DED and construction procurement', 51],
            ['spg_ndc_own', 'Conduct public bidding and complete DED', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 850, 'high', false, '5. DED and construction procurement', 52],
            ['spg_ndc_own', 'Conduct construction bidding and execute construction agreement', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 920, 'high', false, '5. DED and construction procurement', 53],

            // Phase 6
            ['spg_ndc_own', '6. Construction implementation and turn-over', 'Monitor construction implementation and record completion, acceptance, and turn-over.', 'implementation', 'implementation_monitoring', 'Project Officer', 1100, 'medium', false, null, 60],
            ['spg_ndc_own', 'Start construction monitoring after signed agreement and conditions', null, 'implementation', 'implementation_monitoring', 'Project Officer', 950, 'medium', false, '6. Construction implementation and turn-over', 61],
            ['spg_ndc_own', 'Track contractor completion and NDC acceptance', null, 'implementation', 'implementation_monitoring', 'Project Officer', 1080, 'medium', false, '6. Construction implementation and turn-over', 62],
            ['spg_ndc_own', 'Record turn-over and operations/maintenance handoff', null, 'implementation', 'implementation_monitoring', 'Project Officer', 1100, 'medium', false, '6. Construction implementation and turn-over', 63],
        ];

        // 2. SPG JV Track Tasks
        $spgJvTasks = [
            // Phase 1
            ['spg_jv', '1. JV concept and ManCom approval to proceed', 'Conceptualize the JV project and secure ManCom approval to proceed with study and budget allocation.', 'intake', 'intake', 'Project Officer', 150, 'critical', true, null, 10],
            ['spg_jv', 'Prepare JV project concept', null, 'intake', 'intake', 'Project Officer', 90, 'critical', false, '1. JV concept and ManCom approval to proceed', 11],
            ['spg_jv', 'Present JV concept to ManCom for approval to proceed', null, 'intake', 'intake', 'Workgroup Head', 150, 'urgent', false, '1. JV concept and ManCom approval to proceed', 12],
            ['spg_jv', 'Record ManCom revisions, deferral, or approval', null, 'intake', 'intake', 'Project Officer', 150, 'high', false, '1. JV concept and ManCom approval to proceed', 13],

            // Phase 2
            ['spg_jv', '2. Consultancy procurement and study', 'Procure consultancy services, conduct the study, and prepare the study report.', 'due_diligence', 'due_diligence', 'Project Officer', 547, 'high', true, null, 20],
            ['spg_jv', 'Prepare TOR, budget estimates, and Materials Requisition', null, 'due_diligence', 'due_diligence', 'Project Officer', 247, 'high', false, '2. Consultancy procurement and study', 21],
            ['spg_jv', 'Conduct public bidding for consultancy services', null, 'due_diligence', 'due_diligence', 'Project Officer', 427, 'high', false, '2. Consultancy procurement and study', 22],
            ['spg_jv', 'Complete study report from NTP', null, 'due_diligence', 'due_diligence', 'Project Officer', 547, 'high', false, '2. Consultancy procurement and study', 23],

            // Phase 3
            ['spg_jv', '3. ManCom and Board approval of JV project', 'Evaluate the study, present to ManCom, and secure Board approval before NEDA-ICC requirements.', 'approval', 'board_approval', 'Workgroup Head', 640, 'high', true, null, 30],
            ['spg_jv', 'Evaluate study and prepare recommendation', null, 'approval', 'board_approval', 'Project Officer', 577, 'high', false, '3. ManCom and Board approval of JV project', 31],
            ['spg_jv', 'Present JV project to ManCom for decision', null, 'approval', 'board_approval', 'Workgroup Head', 607, 'high', false, '3. ManCom and Board approval of JV project', 32],
            ['spg_jv', 'Present JV project to Board of Directors', null, 'approval', 'board_approval', 'Workgroup Head', 640, 'high', false, '3. ManCom and Board approval of JV project', 33],

            // Phase 4
            ['spg_jv', '4. NEDA-ICC approval and JV-SC composition', 'Obtain required NEDA-ICC documents, submit the JV proposal, and secure Board approval of final JVA terms and JV-SC.', 'approval', 'board_approval', 'Project Officer', 760, 'high', true, null, 40],
            ['spg_jv', 'Obtain NEDA-ICC requirements and endorsements if required', null, 'approval', 'board_approval', 'Project Officer', 690, 'high', false, '4. NEDA-ICC approval and JV-SC composition', 41],
            ['spg_jv', 'Prepare and submit JV proposal to NEDA-ICC', null, 'approval', 'board_approval', 'Project Officer', 720, 'high', false, '4. NEDA-ICC approval and JV-SC composition', 42],
            ['spg_jv', 'Record NEDA-ICC approval and Board approval of JVA terms/JV-SC', null, 'approval', 'board_approval', 'Workgroup Head', 760, 'high', false, '4. NEDA-ICC approval and JV-SC composition', 43],

            // Phase 5
            ['spg_jv', '5. JV partner selection, award, and JVA signing', 'Prepare JV selection documents, conduct selection, secure final Board award, issue NOA, and sign the JVA.', 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1010, 'high', true, null, 50],
            ['spg_jv', 'Prepare JV selection documents', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 820, 'high', false, '5. JV partner selection, award, and JVA signing', 51],
            ['spg_jv', 'Conduct JV partner selection through JV-SC', null, 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1000, 'high', false, '5. JV partner selection, award, and JVA signing', 52],
            ['spg_jv', 'Secure Board approval and issue NOA to winning participant', null, 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1007, 'high', false, '5. JV partner selection, award, and JVA signing', 53],
            ['spg_jv', 'Sign JVA within ten days after NOA', null, 'fund_release', 'agreement_fund_release', 'Workgroup Head', 1010, 'high', false, '5. JV partner selection, award, and JVA signing', 54],
        ];

        // 3. Implementation and Monitoring Tasks
        $monitoringTasks = [
            // Phase 1
            ['implementation_monitoring', '1. Consolidation of milestones and targets', 'Create the project/account summary folder from signed documents, fund release records, covenants, and milestones.', 'monitoring', 'implementation_monitoring', 'Project Officer', 14, 'high', true, null, 10],
            ['implementation_monitoring', 'Attach signed documents and release-of-funds records', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 7, 'high', false, '1. Consolidation of milestones and targets', 11],
            ['implementation_monitoring', 'Prepare project/account summary folder', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 10, 'high', false, '1. Consolidation of milestones and targets', 12],
            ['implementation_monitoring', 'List covenants, drawdowns, dividends/coupons, and implementation milestones', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 14, 'high', false, '1. Consolidation of milestones and targets', 13],

            // Phase 2
            ['implementation_monitoring', '2. Monitoring and management', 'Monitor milestones, financial performance, covenants, correspondence, risks, and issues.', 'monitoring', 'implementation_monitoring', 'Project Officer', 45, 'medium', false, null, 20],
            ['implementation_monitoring', 'Update milestone, covenant, and risk schedule', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 21, 'medium', false, '2. Monitoring and management', 21],
            ['implementation_monitoring', 'Upload correspondence, statement of account, financial statements, and Board papers', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 30, 'medium', false, '2. Monitoring and management', 22],
            ['implementation_monitoring', 'Record updates, issues, next steps, and quarterly management/COA notes', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 45, 'medium', false, '2. Monitoring and management', 23],

            // Phase 3
            ['implementation_monitoring', '3. Adjustment or modification decision', 'Route restructuring, equity changes, divestment, or loan-to-equity conversion issues to ManCom and Board when needed.', 'approval', 'implementation_monitoring', 'Workgroup Head', 75, 'medium', true, null, 30],
            ['implementation_monitoring', 'Prepare background, updates, issues, options, and recommendations', null, 'approval', 'implementation_monitoring', 'Project Officer', 60, 'medium', false, '3. Adjustment or modification decision', 31],
            ['implementation_monitoring', 'Present adjustment or modification issue to ManCom', null, 'approval', 'implementation_monitoring', 'Workgroup Head', 68, 'high', false, '3. Adjustment or modification decision', 32],
            ['implementation_monitoring', 'Record Board decision and update agreement/MOA if required', null, 'approval', 'implementation_monitoring', 'Workgroup Head', 75, 'high', false, '3. Adjustment or modification decision', 33],

            // Phase 4
            ['implementation_monitoring', '4. Post-investment strategy review', 'Review redemption, conversion, restructuring, share transfer, dividend, or exit options.', 'post_investment', 'implementation_monitoring', 'Workgroup Head', 105, 'medium', true, null, 40],
            ['implementation_monitoring', 'Review maturing notes, equity holdings, and restructuring options', null, 'post_investment', 'implementation_monitoring', 'Project Officer', 90, 'medium', false, '4. Post-investment strategy review', 41],
            ['implementation_monitoring', 'Recommend post-investment strategy to Management', null, 'post_investment', 'implementation_monitoring', 'Workgroup Head', 100, 'medium', false, '4. Post-investment strategy review', 42],
            ['implementation_monitoring', 'Attach transfer receipts or term sheets when applicable', null, 'post_investment', 'implementation_monitoring', 'Project Officer', 105, 'medium', false, '4. Post-investment strategy review', 43],
        ];

        // 4. Divestment Tasks
        $divestmentTasks = [
            // Phase 1
            ['divestment', '1. Start divestment and due diligence', 'Begin divestment proceedings and complete legal and financial due diligence.', 'divestment', 'divestment', 'Workgroup Head', 30, 'high', true, null, 10],
            ['divestment', 'Record ManCom-approved divestment recommendation or external offer', null, 'divestment', 'divestment', 'Workgroup Head', 7, 'high', false, '1. Start divestment and due diligence', 11],
            ['divestment', 'Complete legal due diligence and legal memo', null, 'divestment', 'divestment', 'Workgroup Head', 20, 'high', false, '1. Start divestment and due diligence', 12],
            ['divestment', 'Complete financial due diligence, asset appraisal, and pricing basis', null, 'divestment', 'divestment', 'Project Officer', 30, 'high', false, '1. Start divestment and due diligence', 13],

            // Phase 2
            ['divestment', '2. ManCom approval of divestment terms', 'Prepare proposed terms and conditions of share or asset transfer for ManCom approval.', 'approval', 'divestment', 'Workgroup Head', 45, 'high', true, null, 20],
            ['divestment', 'Draft terms and conditions of transfer with Legal and Finance', null, 'approval', 'divestment', 'Workgroup Head', 38, 'high', false, '2. ManCom approval of divestment terms', 21],
            ['divestment', 'Prepare ManCom paper and presentation', null, 'approval', 'divestment', 'Workgroup Head', 42, 'high', false, '2. ManCom approval of divestment terms', 22],
            ['divestment', 'Record ManCom decision and revisions if any', null, 'approval', 'divestment', 'Workgroup Head', 45, 'high', false, '2. ManCom approval of divestment terms', 23],

            // Phase 3
            ['divestment', '3. Board approval of divestment', 'Secure Board decision on the terms and conditions of divestment.', 'approval', 'divestment', 'Workgroup Head', 60, 'high', true, null, 30],
            ['divestment', 'Prepare Board paper and Secretary Certificate requirements', null, 'approval', 'divestment', 'Workgroup Head', 55, 'high', false, '3. Board approval of divestment', 31],
            ['divestment', 'Present divestment terms to Board of Directors', null, 'approval', 'divestment', 'Workgroup Head', 60, 'high', false, '3. Board approval of divestment', 32],
            ['divestment', 'Record Board decision and required adjustments', null, 'approval', 'divestment', 'Workgroup Head', 60, 'high', false, '3. Board approval of divestment', 33],

            // Phase 4
            ['divestment', '4. Execute divestment transfer and collection', 'Complete documentary requirements, payments, receipts, and transfer of shares/assets.', 'divestment', 'divestment', 'Project Officer', 90, 'high', true, null, 40],
            ['divestment', 'Prepare and sign transfer documents', null, 'divestment', 'divestment', 'Project Officer', 75, 'high', false, '4. Execute divestment transfer and collection', 41],
            ['divestment', 'Collect payments and issue receipts', null, 'divestment', 'divestment', 'Project Officer', 82, 'high', false, '4. Execute divestment transfer and collection', 42],
            ['divestment', 'Record final transfer of shares/assets and close divestment file', null, 'divestment', 'divestment', 'Project Officer', 90, 'high', false, '4. Execute divestment transfer and collection', 43],
        ];

        // 5. Fallback/Default Tasks (for bdg_investment and spg_traditional)
        $fallbackTasks = [
            // Phase 1
            ['__TRACK__', '1. Intake and proponent registration', 'Confirm proponent identity, mandate fit, LOI/project concept, and initial NDC criteria.', 'intake', 'intake', 'Project Officer', 10, 'critical', true, null, 10],
            ['__TRACK__', 'Conduct pre-screening / KYC meeting', null, 'intake', 'intake', 'Project Officer', 3, 'critical', false, '1. Intake and proponent registration', 11],
            ['__TRACK__', 'Receive LOI, project concept, pitch deck, and contact details', null, 'intake', 'intake', 'Proponent', 6, 'critical', false, '1. Intake and proponent registration', 12],
            ['__TRACK__', 'Check at least three NDC investment criteria', null, 'intake', 'intake', 'Project Officer', 10, 'urgent', false, '1. Intake and proponent registration', 13],

            // Phase 2
            ['__TRACK__', '2. Requirements and completeness check', 'Issue the response letter/checklist and validate the complete requirements package.', 'requirements', 'requirements', 'Project Officer', 25, 'urgent', true, null, 20],
            ['__TRACK__', 'Send response letter and documentary checklist', null, 'requirements', 'requirements', 'Project Officer', 14, 'urgent', false, '2. Requirements and completeness check', 21],
            ['__TRACK__', 'Receive complete proposal, legal, tax, and financial documents', null, 'requirements', 'requirements', 'Proponent', 21, 'urgent', false, '2. Requirements and completeness check', 22],
            ['__TRACK__', 'Record deferred, waived, or missing requirements with remarks', null, 'requirements', 'requirements', 'Project Officer', 25, 'high', false, '2. Requirements and completeness check', 23],

            // Phase 3
            ['__TRACK__', '3. Due diligence and evaluation', 'Validate submitted documents, financial model, risk items, site evidence, and feasibility assumptions.', 'due_diligence', 'due_diligence', 'Project Officer', 50, 'high', true, null, 30],
            ['__TRACK__', 'Validate documentary requirements and project proposal', null, 'due_diligence', 'due_diligence', 'Project Officer', 32, 'high', false, '3. Due diligence and evaluation', 31],
            ['__TRACK__', 'Prepare financial model and risk evaluation summary', null, 'due_diligence', 'due_diligence', 'Project Officer', 42, 'high', false, '3. Due diligence and evaluation', 32],
            ['__TRACK__', 'Complete due diligence / CBI / third-party review if applicable', null, 'due_diligence', 'due_diligence', 'Project Officer', 50, 'high', false, '3. Due diligence and evaluation', 33],

            // Phase 4
            ['__TRACK__', '4. Management and Board approval', 'Prepare recommendation papers and route the project through IC when applicable, Workgroup, ManCom, and Board.', 'approval', 'management_review', 'Workgroup Head', 80, 'high', true, null, 40],
            ['__TRACK__', 'Prepare AGM / Workgroup recommendation', null, 'approval', 'management_review', 'Workgroup Head', 58, 'high', false, '4. Management and Board approval', 41],
            ['__TRACK__', 'Prepare ManCom decision paper', null, 'approval', 'management_review', 'Workgroup Head', 68, 'high', false, '4. Management and Board approval', 42],
            ['__TRACK__', 'Prepare Board approval package and condition tracker', null, 'approval', 'management_review', 'Workgroup Head', 80, 'high', false, '4. Management and Board approval', 43],

            // Phase 5
            ['__TRACK__', '5. Agreement, fund release, and mobilization', 'Coordinate legal, finance, agreement signing, release evidence, and implementation handoff.', 'fund_release', 'agreement_fund_release', 'Project Officer', 105, 'medium', true, null, 50],
            ['__TRACK__', 'Prepare agreement / contract / JVA documents', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 92, 'medium', false, '5. Agreement, fund release, and mobilization', 51],
            ['__TRACK__', 'Coordinate legal, finance, compliance, and signatures', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 100, 'medium', false, '5. Agreement, fund release, and mobilization', 52],
            ['__TRACK__', 'Attach fund release or receipt evidence', null, 'fund_release', 'agreement_fund_release', 'Project Officer', 105, 'medium', false, '5. Agreement, fund release, and mobilization', 53],

            // Phase 6
            ['__TRACK__', '6. Implementation monitoring and quarterly reporting', 'Maintain the project summary folder, milestones, covenants, issues, jobs generated, and next steps.', 'monitoring', 'implementation_monitoring', 'Project Officer', 135, 'medium', false, null, 60],
            ['__TRACK__', 'Update milestone schedule, covenants, and risks', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 118, 'medium', false, '6. Implementation monitoring and quarterly reporting', 61],
            ['__TRACK__', 'Record jobs generated and financial/reporting indicators', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 126, 'medium', false, '6. Implementation monitoring and quarterly reporting', 62],
            ['__TRACK__', 'Prepare quarterly COA / management update', null, 'monitoring', 'implementation_monitoring', 'Project Officer', 135, 'medium', false, '6. Implementation monitoring and quarterly reporting', 63],
        ];

        // Seed datasets
        $allTasks = array_merge($spgNdcOwnTasks, $spgJvTasks, $monitoringTasks, $divestmentTasks);

        foreach ($allTasks as $task) {
            $records[] = [
                'track' => $task[0],
                'title' => $task[1],
                'description' => $task[2],
                'task_type' => $task[3],
                'soi_section' => $task[4],
                'assigned_role' => $task[5],
                'days' => $task[6],
                'priority' => $task[7],
                'is_milestone' => (bool)$task[8],
                'parent_task_title' => $task[9],
                'sort_order' => $task[10],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Duplicate the fallback tasks for both bdg_investment and spg_traditional tracks
        $targetTracks = ['bdg_investment', 'spg_traditional'];
        foreach ($targetTracks as $track) {
            foreach ($fallbackTasks as $task) {
                $records[] = [
                    'track' => $track,
                    'title' => $task[1],
                    'description' => $task[2],
                    'task_type' => $task[3],
                    'soi_section' => $task[4],
                    'assigned_role' => $task[5],
                    'days' => $task[6],
                    'priority' => $task[7],
                    'is_milestone' => (bool)$task[8],
                    'parent_task_title' => $task[9],
                    'sort_order' => $task[10],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('default_tasks')->insert($records);
    }
};
