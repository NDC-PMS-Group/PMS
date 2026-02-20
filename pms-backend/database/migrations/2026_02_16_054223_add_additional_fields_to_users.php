<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Name fields
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('suffix', 10)->nullable()->after('last_name'); // Jr., Sr., III, etc.
            
            // Profile
            $table->string('profile_photo_url')->nullable()->after('email');
            
            // Contact information
            $table->string('phone_number', 20)->nullable()->after('email');
            $table->text('address')->nullable()->after('phone_number');
            
            // Employment information
            $table->string('employee_id', 50)->nullable()->unique()->after('id');
            $table->string('department')->nullable()->after('employee_id');
            $table->string('position')->nullable()->after('department');
            $table->date('date_hired')->nullable()->after('position');
            
            // Personal information
            $table->date('birth_date')->nullable()->after('last_name');
            
            // Add indexes for better query performance
            $table->index('employee_id');
            $table->index('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['department']);
            
            $table->dropColumn([
                'middle_name',
                'suffix',
                'profile_photo_url',
                'phone_number',
                'address',
                'employee_id',
                'department',
                'position',
                'date_hired',
                'birth_date',
            ]);
        });
    }
};
