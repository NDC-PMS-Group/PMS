<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Draft', 'color_code' => '#94A3B8', 'is_active' => true],
            ['name' => 'Submitted', 'color_code' => '#6366F1', 'is_active' => true],
            ['name' => 'Pre-screening / KYC', 'color_code' => '#8B5CF6', 'is_active' => true],
            ['name' => 'LOI Received', 'color_code' => '#6366F1', 'is_active' => true],
            ['name' => 'Requirements Requested', 'color_code' => '#F59E0B', 'is_active' => true],
            ['name' => 'Requirements Received', 'color_code' => '#06B6D4', 'is_active' => true],
            ['name' => 'Returned for Revision', 'color_code' => '#F97316', 'is_active' => true],
            ['name' => 'Due Diligence Ongoing', 'color_code' => '#EAB308', 'is_active' => true],
            ['name' => 'For IC Evaluation', 'color_code' => '#7C3AED', 'is_active' => true],
            ['name' => 'For Workgroup Review', 'color_code' => '#38BDF8', 'is_active' => true],
            ['name' => 'For ManCom Review', 'color_code' => '#0EA5E9', 'is_active' => true],
            ['name' => 'For Board Approval', 'color_code' => '#2563EB', 'is_active' => true],
            ['name' => 'For NEDA-ICC Review', 'color_code' => '#7C3AED', 'is_active' => true],
            ['name' => 'For JV Selection', 'color_code' => '#9333EA', 'is_active' => true],
            ['name' => 'Approved', 'color_code' => '#32CD32', 'is_active' => true],
            ['name' => 'Approved with Conditions', 'color_code' => '#9ACD32', 'is_active' => true],
            ['name' => 'For Agreement Signing', 'color_code' => '#14B8A6', 'is_active' => true],
            ['name' => 'For Fund Release', 'color_code' => '#0D9488', 'is_active' => true],
            ['name' => 'Milestones Setup', 'color_code' => '#06B6D4', 'is_active' => true],
            ['name' => 'Implementation Ongoing', 'color_code' => '#14B8A6', 'is_active' => true],
            ['name' => 'Monitoring Ongoing', 'color_code' => '#0F766E', 'is_active' => true],
            ['name' => 'For Monitoring Update', 'color_code' => '#0891B2', 'is_active' => true],
            ['name' => 'Post-Investment Review', 'color_code' => '#64748B', 'is_active' => true],
            ['name' => 'For Divestment', 'color_code' => '#475569', 'is_active' => true],
            ['name' => 'For Divestment Approval', 'color_code' => '#334155', 'is_active' => true],
            ['name' => 'On Hold', 'color_code' => '#FF8C00', 'is_active' => true],
            ['name' => 'Delayed', 'color_code' => '#DC143C', 'is_active' => true],
            ['name' => 'Construction Ongoing', 'color_code' => '#EA580C', 'is_active' => true],
            ['name' => 'Completed', 'color_code' => '#228B22', 'is_active' => true],
            ['name' => 'Archived', 'color_code' => '#475569', 'is_active' => true],
            ['name' => 'Divested', 'color_code' => '#64748B', 'is_active' => true],
            ['name' => 'Cancelled', 'color_code' => '#696969', 'is_active' => true],
            ['name' => 'Rejected', 'color_code' => '#8B0000', 'is_active' => true],
        ];

        foreach ($statuses as $status) {
            DB::table('project_statuses')->updateOrInsert(
                ['name' => $status['name']],
                array_merge($status, ['created_at' => now()])
            );
        }

        $activeNames = array_column($statuses, 'name');

        DB::table('project_statuses')
            ->whereNotIn('name', $activeNames)
            ->update(['is_active' => false]);

        $legacyMap = [
            'Pending' => 'LOI Received',
            'Initial Completeness Check' => 'Requirements Requested',
            'For Evaluation' => 'Due Diligence Ongoing',
            'Evaluation Ongoing' => 'Due Diligence Ongoing',
            'For Approval' => 'For ManCom Review',
            'For AGM Review' => 'For Workgroup Review',
            'In Progress' => 'Implementation Ongoing',
        ];

        foreach ($legacyMap as $legacyName => $replacementName) {
            $legacy = DB::table('project_statuses')->where('name', $legacyName)->first();
            $replacement = DB::table('project_statuses')->where('name', $replacementName)->first();

            if (!$legacy || !$replacement) {
                continue;
            }

            DB::table('projects')
                ->where('status_id', $legacy->id)
                ->update(['status_id' => $replacement->id]);

            DB::table('project_status_history')
                ->where('to_status_id', $legacy->id)
                ->update(['to_status_id' => $replacement->id]);

            DB::table('project_status_history')
                ->where('from_status_id', $legacy->id)
                ->update(['from_status_id' => $replacement->id]);
        }
    }
}
