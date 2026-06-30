<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_invitation_token', 64)->nullable()->unique();
            $table->timestamp('staff_invitation_expires_at')->nullable();
            $table->timestamp('staff_invitation_accepted_at')->nullable();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by_id');
            $table->dropUnique(['staff_invitation_token']);
            $table->dropColumn([
                'staff_invitation_token',
                'staff_invitation_expires_at',
                'staff_invitation_accepted_at',
            ]);
        });
    }
};
