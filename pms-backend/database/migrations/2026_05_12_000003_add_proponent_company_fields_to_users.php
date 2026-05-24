<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'organization_name')) {
                $table->string('organization_name')->nullable()->after('address');
            }

            if (!Schema::hasColumn('users', 'organization_type')) {
                $table->string('organization_type', 80)->nullable()->after('organization_name');
            }

            if (!Schema::hasColumn('users', 'organization_registration_no')) {
                $table->string('organization_registration_no')->nullable()->after('organization_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['organization_name', 'organization_type', 'organization_registration_no'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
