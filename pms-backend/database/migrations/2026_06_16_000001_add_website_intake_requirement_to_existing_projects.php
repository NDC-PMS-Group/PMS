<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('projects')
            ->whereIn('process_track', ['bdg_investment', 'spg_traditional', 'spg_jv'])
            ->orderBy('id')
            ->select(['id', 'process_track'])
            ->chunkById(100, function ($projects) use ($now) {
                foreach ($projects as $project) {
                    $exists = DB::table('project_requirements')
                        ->where('project_id', $project->id)
                        ->where('group_name', '1. Intake Pack')
                        ->where('item_name', 'Website or product/company page')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('project_requirements')->insert([
                        'project_id' => $project->id,
                        'group_name' => '1. Intake Pack',
                        'item_name' => 'Website or product/company page',
                        'source_document' => 'BDG eligibility checklist',
                        'track' => $project->process_track ?: 'bdg_investment',
                        'is_required' => false,
                        'is_applicable' => true,
                        'svf_only' => false,
                        'status' => 'requested',
                        'sort_order' => 15,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('project_requirements')
            ->where('group_name', '1. Intake Pack')
            ->where('item_name', 'Website or product/company page')
            ->whereNull('document_id')
            ->delete();
    }
};
