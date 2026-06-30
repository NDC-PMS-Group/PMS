<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_requirements')
            ->whereIn('track', ['bdg_investment', 'spg_traditional', 'spg_jv'])
            ->where('group_name', '!=', '1. Intake Pack')
            ->update([
                'is_required' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Historical requirement flags cannot be reconstructed reliably.
    }
};
