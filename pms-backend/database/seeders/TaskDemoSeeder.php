<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskDemoSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 2; // sa@gmail.com (superadmin)
        $now = Carbon::now();

        // ==============================
        // PROJECTS
        // ==============================

        $projects = [
            [
                'id' => 900,
                'project_code' => 'NDC-2025-001',
                'title' => 'NDC Website Redesign',
                'description' => 'Complete overhaul of the NDC corporate website with modern design and accessibility compliance.',
                'current_stage_id' => 4, // Implementation
                'status_id' => 6,        // In Progress
                'estimated_cost' => 350000.00,
                'currency' => 'PHP',
                'start_date' => '2025-03-01',
                'target_completion_date' => '2025-09-30',
                'created_by' => $userId,
                'project_officer_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 901,
                'project_code' => 'NDC-2025-002',
                'title' => 'Enterprise Resource Planning System',
                'description' => 'Procurement and deployment of an integrated ERP system for financial management.',
                'current_stage_id' => 1, // Proposal
                'status_id' => 1,        // Pending
                'estimated_cost' => 800000.00,
                'currency' => 'PHP',
                'start_date' => '2025-04-15',
                'target_completion_date' => '2026-03-30',
                'created_by' => $userId,
                'project_officer_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 902,
                'project_code' => 'NDC-2025-003',
                'title' => 'Office Renovation Phase 2',
                'description' => 'Second phase renovation of the 7th floor office space including network infrastructure.',
                'current_stage_id' => 4, // Implementation
                'status_id' => 6,        // In Progress
                'estimated_cost' => 1500000.00,
                'currency' => 'PHP',
                'start_date' => '2025-02-01',
                'target_completion_date' => '2025-07-31',
                'created_by' => $userId,
                'project_officer_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($projects as $project) {
            DB::table('projects')->updateOrInsert(
                ['id' => $project['id']],
                $project
            );
        }

        // Add sa@gmail.com as project member
        foreach ([900, 901, 902] as $pid) {
            DB::table('project_members')->updateOrInsert(
                ['project_id' => $pid, 'user_id' => $userId],
                [
                    'role_id' => 1,
                    'assignment_type' => 'owner',
                    'can_view' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_approve' => true,
                    'can_manage_members' => true,
                    'assigned_by' => $userId,
                    'assigned_at' => $now,
                ]
            );
        }

        // ==============================
        // TASKS for Project: NDC Website Redesign (900)
        // ==============================

        $tasks = [
            // --- Project 900 Tasks ---
            [
                'id' => 9001,
                'project_id' => 900,
                'title' => 'Design wireframes for homepage',
                'description' => 'Create low-fidelity wireframes for the new homepage layout.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-03-05',
                'due_date' => '2025-03-15',
                'status' => 'completed',
                'priority' => 'high',
                'progress_percentage' => 100,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9002,
                'project_id' => 900,
                'title' => 'Develop responsive frontend',
                'description' => 'Implement the design using Vue.js with mobile-first approach.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-03-16',
                'due_date' => '2025-04-30',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'progress_percentage' => 45,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9003,
                'project_id' => 900,
                'title' => 'Backend API integration',
                'description' => 'Connect frontend to the Laravel API endpoints.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-04-01',
                'due_date' => '2025-05-15',
                'status' => 'pending',
                'priority' => 'high',
                'progress_percentage' => 0,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9004,
                'project_id' => 900,
                'title' => 'UAT Sign-off',
                'description' => 'User acceptance testing milestone.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-09-01',
                'due_date' => '2025-09-15',
                'status' => 'pending',
                'priority' => 'normal',
                'progress_percentage' => 0,
                'parent_task_id' => null,
                'is_milestone' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9005,
                'project_id' => 900,
                'title' => 'Content migration',
                'description' => 'Migrate existing content from old WordPress site.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-05-01',
                'due_date' => '2025-06-30',
                'status' => 'pending',
                'priority' => 'normal',
                'progress_percentage' => 0,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // --- Project 901 Tasks ---
            [
                'id' => 9006,
                'project_id' => 901,
                'title' => 'Vendor evaluation and shortlisting',
                'description' => 'Evaluate bids from ERP vendors and prepare shortlist.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-04-15',
                'due_date' => '2025-05-15',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'progress_percentage' => 30,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9007,
                'project_id' => 901,
                'title' => 'Requirements gathering workshop',
                'description' => 'Conduct workshops with department heads to gather requirements.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-04-15',
                'due_date' => '2025-04-30',
                'status' => 'completed',
                'priority' => 'high',
                'progress_percentage' => 100,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9008,
                'project_id' => 901,
                'title' => 'Draft Terms of Reference',
                'description' => 'Prepare the TOR document for procurement.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-05-16',
                'due_date' => '2025-06-15',
                'status' => 'pending',
                'priority' => 'high',
                'progress_percentage' => 0,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // --- Project 902 Tasks ---
            [
                'id' => 9009,
                'project_id' => 902,
                'title' => 'Demolition of existing partitions',
                'description' => 'Remove old cubicle partitions on the 7th floor.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-02-01',
                'due_date' => '2025-02-28',
                'status' => 'completed',
                'priority' => 'high',
                'progress_percentage' => 100,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9010,
                'project_id' => 902,
                'title' => 'Network cabling installation',
                'description' => 'Install Cat6 network cabling and patch panels.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-03-01',
                'due_date' => '2025-04-15',
                'status' => 'in_progress',
                'priority' => 'normal',
                'progress_percentage' => 60,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9011,
                'project_id' => 902,
                'title' => 'Furniture procurement',
                'description' => 'Order and install new ergonomic office furniture.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-04-16',
                'due_date' => '2025-05-31',
                'status' => 'pending',
                'priority' => 'low',
                'progress_percentage' => 0,
                'parent_task_id' => null,
                'is_milestone' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9012,
                'project_id' => 902,
                'title' => 'Final inspection & handover',
                'description' => 'Final inspection milestone before handover.',
                'assigned_to' => $userId,
                'assigned_by' => $userId,
                'start_date' => '2025-07-15',
                'due_date' => '2025-07-31',
                'status' => 'pending',
                'priority' => 'urgent',
                'progress_percentage' => 0,
                'parent_task_id' => null,
                'is_milestone' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($tasks as $task) {
            DB::table('tasks')->updateOrInsert(
                ['id' => $task['id']],
                $task
            );
        }

        // ==============================
        // SUBTASKS
        // ==============================

        $subtasks = [
            // Subtasks for "Develop responsive frontend" (9002)
            ['id' => 9101, 'project_id' => 900, 'title' => 'Setup Vite project scaffolding', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-03-16', 'due_date' => '2025-03-18', 'status' => 'completed', 'priority' => 'normal', 'progress_percentage' => 100, 'parent_task_id' => 9002, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9102, 'project_id' => 900, 'title' => 'Implement navigation component', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-03-19', 'due_date' => '2025-03-25', 'status' => 'completed', 'priority' => 'normal', 'progress_percentage' => 100, 'parent_task_id' => 9002, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9103, 'project_id' => 900, 'title' => 'Build hero section with animations', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-03-26', 'due_date' => '2025-04-05', 'status' => 'in_progress', 'priority' => 'high', 'progress_percentage' => 50, 'parent_task_id' => 9002, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9104, 'project_id' => 900, 'title' => 'Implement footer and contact form', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-04-06', 'due_date' => '2025-04-15', 'status' => 'pending', 'priority' => 'normal', 'progress_percentage' => 0, 'parent_task_id' => 9002, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9105, 'project_id' => 900, 'title' => 'Mobile responsiveness testing', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-04-16', 'due_date' => '2025-04-25', 'status' => 'pending', 'priority' => 'high', 'progress_percentage' => 0, 'parent_task_id' => 9002, 'created_at' => $now, 'updated_at' => $now],

            // Subtasks for "Vendor evaluation" (9006)
            ['id' => 9106, 'project_id' => 901, 'title' => 'Collect vendor proposals', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-04-15', 'due_date' => '2025-04-25', 'status' => 'completed', 'priority' => 'normal', 'progress_percentage' => 100, 'parent_task_id' => 9006, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9107, 'project_id' => 901, 'title' => 'Technical evaluation matrix', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-04-26', 'due_date' => '2025-05-05', 'status' => 'in_progress', 'priority' => 'high', 'progress_percentage' => 40, 'parent_task_id' => 9006, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9108, 'project_id' => 901, 'title' => 'Vendor demo presentations', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-05-06', 'due_date' => '2025-05-15', 'status' => 'pending', 'priority' => 'normal', 'progress_percentage' => 0, 'parent_task_id' => 9006, 'created_at' => $now, 'updated_at' => $now],

            // Subtasks for "Network cabling" (9010)
            ['id' => 9109, 'project_id' => 902, 'title' => 'Survey existing infrastructure', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-03-01', 'due_date' => '2025-03-07', 'status' => 'completed', 'priority' => 'normal', 'progress_percentage' => 100, 'parent_task_id' => 9010, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9110, 'project_id' => 902, 'title' => 'Install cable trays', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-03-08', 'due_date' => '2025-03-20', 'status' => 'completed', 'priority' => 'normal', 'progress_percentage' => 100, 'parent_task_id' => 9010, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9111, 'project_id' => 902, 'title' => 'Pull Cat6 cables and terminate', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-03-21', 'due_date' => '2025-04-05', 'status' => 'in_progress', 'priority' => 'high', 'progress_percentage' => 70, 'parent_task_id' => 9010, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9112, 'project_id' => 902, 'title' => 'Cable testing and certification', 'assigned_to' => $userId, 'assigned_by' => $userId, 'start_date' => '2025-04-06', 'due_date' => '2025-04-15', 'status' => 'pending', 'priority' => 'normal', 'progress_percentage' => 0, 'parent_task_id' => 9010, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($subtasks as $subtask) {
            DB::table('tasks')->updateOrInsert(
                ['id' => $subtask['id']],
                $subtask
            );
        }

        $this->command->info('✅ Seeded 3 projects, 12 tasks, and 12 subtasks for sa@gmail.com');
    }
}
