<?php

// database/migrations/2024_01_01_000001_create_pms_tables.php

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
        // ============================================
        // ROLES & PERMISSIONS
        // ============================================
        
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('resource', 50);
            $table->string('action', 50);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['role_id', 'permission_id'], 'role_perms_unique');
        });

        // ============================================
        // USERS
        // ============================================
        
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->foreignId('default_role_id')->nullable()->constrained('roles');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
            
            $table->index('email');
            $table->index('default_role_id');
        });

        // ============================================
        // LOOKUP TABLES
        // ============================================
        
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('investment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('funding_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('project_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->integer('sequence_order');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color_code', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        // ============================================
        // PROJECTS
        // ============================================
        
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 50)->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('project_type_id')->nullable()->constrained();
            $table->foreignId('industry_id')->nullable()->constrained();
            $table->foreignId('sector_id')->nullable()->constrained();
            $table->foreignId('investment_type_id')->nullable()->constrained();
            $table->foreignId('funding_source_id')->nullable()->constrained();
            
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->string('currency', 3)->default('PHP');
            
            $table->foreignId('current_stage_id')->constrained('project_stages');
            $table->foreignId('status_id')->constrained('project_statuses');
            
            $table->date('proposal_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            
            $table->text('location_address')->nullable();
            $table->decimal('location_lat', 10, 8)->nullable();
            $table->decimal('location_lng', 11, 8)->nullable();
            $table->string('map_layer', 100)->nullable();
            
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('logo_url', 500)->nullable();
            
            $table->foreignId('project_officer_id')->nullable()->constrained('users');
            $table->foreignId('workgroup_head_id')->nullable()->constrained('users');
            $table->string('proponent_name', 255)->nullable();
            $table->string('proponent_contact', 255)->nullable();
            $table->string('proponent_email', 255)->nullable();
            
            $table->boolean('is_svf')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_deleted')->default(false);
            
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('project_code');
            $table->index('current_stage_id');
            $table->index('status_id');
            $table->index('project_type_id');
            $table->index('is_archived');
            $table->index('is_deleted');
            $table->index('created_at');
        });

        // ============================================
        // PROJECT MEMBERS & PERMISSIONS
        // ============================================
        
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained();
            
            $table->string('assignment_type', 50)->default('member');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_manage_members')->default(false);
            
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            
            $table->unique(['project_id', 'user_id'], 'project_user_unique');
            $table->index('user_id');
            $table->index('role_id');
        });

        Schema::create('project_member_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_member_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->boolean('granted')->default(true);
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('granted_at')->useCurrent();
            
            $table->unique(['project_member_id', 'permission_id'], 'pm_perms_unique');
        });

        // ============================================
        // PROJECT HISTORY
        // ============================================
        
        Schema::create('project_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_stage_id')->nullable()->constrained('project_stages');
            $table->foreignId('to_stage_id')->constrained('project_stages');
            $table->foreignId('changed_by')->constrained('users');
            $table->text('change_reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            
            $table->index(['project_id', 'changed_at'], 'stage_history_idx');
        });

        Schema::create('project_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_status_id')->nullable()->constrained('project_statuses');
            $table->foreignId('to_status_id')->constrained('project_statuses');
            $table->foreignId('changed_by')->constrained('users');
            $table->text('change_reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            
            $table->index(['project_id', 'changed_at'], 'status_history_idx');
        });

        // ============================================
        // TAGS
        // ============================================
        
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('category', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('project_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['project_id', 'tag_id'], 'project_tags_unique');
        });

        // ============================================
        // TASKS
        // ============================================
        
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('task_type', 50)->nullable();
            
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completion_date')->nullable();
            
            $table->string('status', 50)->default('pending');
            $table->integer('progress_percentage')->default(0);
            $table->string('priority', 20)->nullable();
            
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->nullable();
            
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->onDelete('cascade');
            
            $table->boolean('is_milestone')->default(false);
            $table->boolean('is_deleted')->default(false);
            
            $table->timestamps();
            
            $table->index('project_id');
            $table->index('assigned_to');
            $table->index('status');
            $table->index('due_date');
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('depends_on_task_id')->constrained('tasks')->onDelete('cascade');
            $table->string('dependency_type', 50)->default('finish_to_start');
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['task_id', 'depends_on_task_id'], 'task_deps_unique');
        });

        // ============================================
        // RESOURCES
        // ============================================
        
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('type', 50);
            $table->text('description')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('unit', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('project_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('resource_id')->constrained();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('allocated_amount', 15, 2)->nullable();
            $table->decimal('used_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['project_id', 'resource_id'], 'project_resources_idx');
        });

        Schema::create('task_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('resource_id')->constrained();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ============================================
        // DOCUMENTS
        // ============================================
        
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade');
            
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->nullable();
            $table->string('file_type', 100)->nullable();
            
            $table->string('category', 100)->nullable();
            $table->integer('version')->default(1);
            
            $table->boolean('is_public')->default(false);
            $table->boolean('requires_approval')->default(false);
            
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            
            $table->index('project_id');
            $table->index('category');
            $table->index('uploaded_at');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->integer('version_number');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->nullable();
            $table->text('change_description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['document_id', 'version_number'], 'doc_versions_unique');
        });

        // ============================================
        // APPROVAL WORKFLOWS
        // ============================================
        
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('project_type_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->onDelete('cascade');
            $table->integer('step_order');
            $table->foreignId('role_id')->constrained();
            $table->string('step_name', 100);
            $table->boolean('is_required')->default(true);
            $table->boolean('can_skip')->default(false);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['workflow_id', 'step_order'], 'workflow_steps_idx');
        });

        Schema::create('project_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('workflow_id')->constrained('approval_workflows');
            $table->foreignId('current_step_id')->nullable()->constrained('approval_steps');
            $table->string('overall_status', 50)->default('pending');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            
            $table->index('project_id');
            $table->index('overall_status');
        });

        Schema::create('approval_step_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_approval_id')->constrained()->onDelete('cascade');
            $table->foreignId('step_id')->constrained('approval_steps');
            
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('status', 50)->default('pending');
            $table->text('comments')->nullable();
            $table->text('conditions')->nullable();
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->index(['project_approval_id', 'step_id'], 'approval_steps_idx');
            $table->index('approver_id');
            $table->index('status');
        });

        // ============================================
        // NOTIFICATIONS
        // ============================================
        
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 50);
            $table->string('title', 255);
            $table->text('message');
            
            $table->string('related_entity_type', 50)->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            
            $table->boolean('is_read')->default(false);
            $table->boolean('is_email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            
            $table->index(['user_id', 'is_read'], 'user_notifications_idx');
            $table->index('created_at');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('notification_type', 50);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['user_id', 'notification_type'], 'user_notif_prefs_unique');
        });

        // ============================================
        // AUDIT & LOGS
        // ============================================
        
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 50);
            
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['entity_type', 'entity_id'], 'entity_idx');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('action');
        });

        Schema::create('trash_bin', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->json('entity_data');
            
            $table->foreignId('deleted_by')->constrained('users');
            $table->timestamp('deleted_at')->useCurrent();
            $table->timestamp('can_restore_until')->nullable();
            
            $table->index(['entity_type', 'entity_id'], 'trash_entity_idx');
            $table->index('deleted_at');
        });

        // ============================================
        // KPIs & REPORTS
        // ============================================
        
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->text('calculation_formula')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('target_value', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('project_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('kpi_definition_id')->constrained();
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->text('notes')->nullable();
            
            $table->index(['project_id', 'kpi_definition_id'], 'project_kpis_idx');
            $table->index('recorded_at');
        });

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('report_type', 50)->nullable();
            
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            
            $table->boolean('is_public')->default(false);
            
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('report_type');
        });

        // ============================================
        // SVF INTEGRATION
        // ============================================
        
        Schema::create('svf_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->nullable()->constrained();
            
            $table->string('application_number', 50)->unique();
            $table->string('startup_name', 255);
            $table->text('startup_description')->nullable();
            
            $table->string('founder_name', 255)->nullable();
            $table->string('founder_email', 255)->nullable();
            $table->string('founder_phone', 50)->nullable();
            
            $table->decimal('requested_amount', 15, 2)->nullable();
            $table->decimal('evaluation_score', 5, 2)->nullable();
            
            $table->string('submitted_via', 50)->default('web');
            
            $table->timestamps();
            
            $table->index('project_id');
            $table->index('application_number');
        });

        Schema::create('svf_evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->integer('max_score');
            $table->decimal('weight', 5, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('svf_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('svf_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('criteria_id')->constrained('svf_evaluation_criteria');
            $table->foreignId('evaluator_id')->constrained('users');
            
            $table->integer('score');
            $table->text('comments')->nullable();
            
            $table->timestamp('evaluated_at')->useCurrent();
            
            $table->unique(['svf_application_id', 'criteria_id', 'evaluator_id'], 'svf_eval_unique');
        });

        // ============================================
        // SYSTEM SETTINGS
        // ============================================
        
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->string('data_type', 50)->default('string');
            $table->string('category', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('subject', 255);
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('svf_evaluations');
        Schema::dropIfExists('svf_evaluation_criteria');
        Schema::dropIfExists('svf_applications');
        Schema::dropIfExists('saved_reports');
        Schema::dropIfExists('project_kpis');
        Schema::dropIfExists('kpi_definitions');
        Schema::dropIfExists('trash_bin');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('approval_step_records');
        Schema::dropIfExists('project_approvals');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('task_resources');
        Schema::dropIfExists('project_resources');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('project_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('project_status_history');
        Schema::dropIfExists('project_stage_history');
        Schema::dropIfExists('project_member_permissions');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_statuses');
        Schema::dropIfExists('project_stages');
        Schema::dropIfExists('funding_sources');
        Schema::dropIfExists('investment_types');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('project_types');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};