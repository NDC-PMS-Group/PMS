<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The UAT catalog contains a test entry whose key, name and description are all "sample".
        // Keep historical project selections intact while removing this placeholder from new choices.
        DB::table('investment_criteria')->where('key', 'sample')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['sample'])
            ->whereRaw('LOWER(TRIM(description)) = ?', ['sample'])
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Do not reactivate test criteria during a schema rollback. Administrators can review the entry.
    }
};
