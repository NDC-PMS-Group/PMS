<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('investment_type_other')->nullable()->after('investment_type_id');
            $table->string('funding_source_other')->nullable()->after('funding_source_id');
        });

        Schema::table('default_requirements', function (Blueprint $table) {
            $table->foreignId('responsible_role_id')
                ->nullable()
                ->after('owner_type')
                ->constrained('roles')
                ->nullOnDelete();
        });

        Schema::table('project_requirements', function (Blueprint $table) {
            $table->foreignId('responsible_role_id')
                ->nullable()
                ->after('owner_type')
                ->constrained('roles')
                ->nullOnDelete();
        });

        Schema::create('investment_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $criteria = [
            ['pioneering', 'Pioneering'],
            ['developmental', 'Developmental'],
            ['sustainable', 'Sustainable'],
            ['inclusive', 'Inclusive'],
            ['innovative', 'Innovative'],
            ['board_priority', 'Board Priority'],
            ['urgent_special', 'Urgent / Special'],
            ['pgs_commitment', 'Score Card Target'],
            ['others', 'Others'],
        ];
        foreach ($criteria as $index => [$key, $name]) {
            DB::table('investment_criteria')->insert([
                'key' => $key,
                'name' => $name,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            ['Equity', 'Equity investment'],
            ['Convertible Notes', 'Convertible debt instrument'],
            ['SAFE Notes', 'Simple Agreement for Future Equity'],
            ['Bonds', 'Bond investment instrument'],
            ['Others', 'Other investment instrument defined by the project'],
        ] as [$name, $description]) {
            DB::table('investment_types')->updateOrInsert(
                ['name' => $name],
                ['description' => $description, 'created_at' => now()]
            );
        }

        DB::table('funding_sources')->updateOrInsert(
            ['name' => 'Others'],
            ['description' => 'Other funding source defined by the project', 'created_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_criteria');

        Schema::table('project_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_role_id');
        });
        Schema::table('default_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_role_id');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['investment_type_other', 'funding_source_other']);
        });
    }
};
