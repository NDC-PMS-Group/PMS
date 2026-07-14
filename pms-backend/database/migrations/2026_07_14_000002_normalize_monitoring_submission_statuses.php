<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')
            ->where('monitoring_submission_status', 'approved')
            ->update(['monitoring_submission_status' => 'accepted']);

        DB::table('projects')
            ->where('monitoring_status', 'active')
            ->where(function ($query) {
                $query->whereNull('monitoring_submission_status')
                    ->orWhere('monitoring_submission_status', 'not_requested');
            })
            ->update(['monitoring_submission_status' => 'draft']);
    }

    public function down(): void
    {
        // Status normalization is intentionally not reversed because "accepted" is canonical.
    }
};
