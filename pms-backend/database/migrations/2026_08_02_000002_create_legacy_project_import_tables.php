<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('status_summary')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->index('imported_by');
            $table->index('created_at');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('is_svf');
            $table->index('is_legacy');
        });

        Schema::create('project_legacy_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_import_batch_id')->nullable()->constrained('legacy_import_batches')->nullOnDelete();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_file')->nullable();
            $table->string('source_status_raw')->nullable();
            $table->string('source_cost_raw')->nullable();
            $table->string('source_fund_released_raw')->nullable();
            $table->string('source_partner_raw')->nullable();
            $table->text('source_remarks')->nullable();
            $table->string('row_fingerprint', 64)->nullable();
            $table->json('parse_warnings')->nullable();
            $table->string('detail_status', 30)->default('needs_details');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('project_id');
            $table->unique('row_fingerprint');
            $table->index('legacy_import_batch_id');
            $table->index('source_status_raw');
            $table->index('detail_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_legacy_details');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['is_legacy']);
            $table->dropColumn('is_legacy');
        });

        Schema::dropIfExists('legacy_import_batches');
    }
};
