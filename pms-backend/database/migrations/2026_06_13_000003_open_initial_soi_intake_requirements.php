<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_requirements')
            ->where('group_name', '1. Intake Pack')
            ->whereIn('item_name', [
                'Non-Disclosure Agreement and Data Privacy Consent',
                'Secretary Certificate or authority to submit',
                'Website or product/company page',
            ])
            ->where('status', 'pending')
            ->update([
                'status' => 'requested',
                'is_required' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('project_requirements')
            ->where('group_name', '1. Intake Pack')
            ->whereIn('item_name', [
                'Non-Disclosure Agreement and Data Privacy Consent',
                'Secretary Certificate or authority to submit',
                'Website or product/company page',
            ])
            ->where('status', 'requested')
            ->where('is_required', false)
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
    }
};
