<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_steps') && !Schema::hasColumn('approval_steps', 'requires_agreement_form')) {
            Schema::table('approval_steps', function (Blueprint $table) {
                $table->boolean('requires_agreement_form')->default(false)->after('sla_days');
            });
        }

        $legacyId = DB::table('roles')->where('name', 'Legal and Finance')->value('id');
        $legalId = $this->ensureLegalRole($legacyId ? (int) $legacyId : null);
        $financeId = $this->ensureRole('Finance', 'Reviews financial due diligence, fund release readiness, collections, receipts, and remittance evidence');
        $legalUserId = $this->ensureDemoUser('legal', 'legal@ndc.gov.ph', 'Legal', 'Reviewer', $legalId);
        $this->ensureDemoUser('finance', 'finance@ndc.gov.ph', 'Finance', 'Reviewer', $financeId);

        if ($legacyId) {
            $this->copyPermissions((int) $legacyId, $legalId);
            $this->copyPermissions((int) $legacyId, $financeId);
            $this->migrateLegacyRoleReferences((int) $legacyId, $legalId, $financeId, $legalUserId);
        }

        if (!Schema::hasTable('approval_steps')) {
            return;
        }

        DB::table('approval_steps')
            ->where(function ($query) {
                $query->whereRaw('LOWER(step_name) LIKE ?', ['%legal%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%agreement%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%jva%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%contract%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%signing%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%transfer document%']);
            })
            ->update([
                'role_id' => $legalId,
                'requires_agreement_form' => true,
            ]);

        DB::table('approval_steps')
            ->where(function ($query) {
                $query->whereRaw('LOWER(step_name) LIKE ?', ['%finance%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%financial%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%fund release%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%collection%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%receipt%'])
                    ->orWhereRaw('LOWER(step_name) LIKE ?', ['%valuation%']);
            })
            ->whereRaw('LOWER(step_name) NOT LIKE ?', ['%agreement%'])
            ->whereRaw('LOWER(step_name) NOT LIKE ?', ['%jva%'])
            ->whereRaw('LOWER(step_name) NOT LIKE ?', ['%contract%'])
            ->whereRaw('LOWER(step_name) NOT LIKE ?', ['%signing%'])
            ->update([
                'role_id' => $financeId,
                'requires_agreement_form' => false,
            ]);

        $this->splitAgreementAndFundReleaseStep('NDC BDG Investment Approval', 'Agreement Signing and Fund Release Readiness', 'Legal Agreement Drafting and Signing Readiness', 'Finance Fund Release Readiness', $legalId, $financeId);
        $this->splitAgreementAndFundReleaseStep('NDC SVF Investment Approval', 'Agreement Signing and Fund Release Readiness', 'Legal Agreement Drafting and Signing Readiness', 'Finance Fund Release Readiness', $legalId, $financeId);
        $this->splitAgreementAndFundReleaseStep('SPG Traditional Equity Funding Approval', 'Agreement Signing and Fund Release', 'Legal Agreement Drafting and Signing', 'Finance Fund Release Readiness', $legalId, $financeId);
        $this->splitAgreementAndFundReleaseStep('SPG NDC-Owned Project Approval', 'DED / Construction Procurement and Agreement', 'DED / Construction Agreement Review', 'Finance Construction Procurement and Fund Readiness', $legalId, $financeId);
        $this->splitAgreementAndFundReleaseStep('NDC Divestment Approval', 'Divestment Legal and Financial Due Diligence', 'Divestment Legal Due Diligence', 'Divestment Financial Due Diligence and Valuation Basis', $legalId, $financeId);
        $this->splitAgreementAndFundReleaseStep('NDC Divestment Approval', 'Execute Divestment Procedure and Transfer', 'Execute Divestment Transfer Documents', 'Collect Payments and Issue Receipts', $legalId, $financeId);

        if ($legacyId && (int) $legacyId !== $legalId) {
            $this->deleteLegacyRole((int) $legacyId);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_steps') && Schema::hasColumn('approval_steps', 'requires_agreement_form')) {
            Schema::table('approval_steps', function (Blueprint $table) {
                $table->dropColumn('requires_agreement_form');
            });
        }
    }

    private function ensureRole(string $name, string $description): int
    {
        $existing = DB::table('roles')->where('name', $name)->first();
        if ($existing) {
            DB::table('roles')->where('id', $existing->id)->update([
                'description' => $description,
                'is_system_role' => true,
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'description' => $description,
            'is_system_role' => true,
            'created_at' => now(),
        ]);
    }

    private function ensureLegalRole(?int $legacyId): int
    {
        $description = 'Reviews legal due diligence, agreement drafting, contract terms, and signing readiness';
        $existing = DB::table('roles')->where('name', 'Legal')->first();

        if ($existing) {
            DB::table('roles')->where('id', $existing->id)->update([
                'description' => $description,
                'is_system_role' => true,
            ]);

            return (int) $existing->id;
        }

        if ($legacyId) {
            DB::table('roles')->where('id', $legacyId)->update([
                'name' => 'Legal',
                'description' => $description,
                'is_system_role' => true,
            ]);

            return $legacyId;
        }

        return $this->ensureRole('Legal', $description);
    }

    private function copyPermissions(int $fromId, int $toId): void
    {
        if (!$fromId || !$toId || !Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionIds = DB::table('role_permissions')->where('role_id', $fromId)->pluck('permission_id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $toId, 'permission_id' => $permissionId],
                ['created_at' => now()]
            );
        }
    }

    private function ensureDemoUser(string $username, string $email, string $firstName, string $lastName, int $roleId): ?int
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $existing = DB::table('users')->where('email', $email)->orWhere('username', $username)->first();
        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update([
                'username' => $username,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'default_role_id' => $roleId,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('users')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password_hash' => Hash::make(env('SEED_DEFAULT_PASSWORD', 'Password123!')),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'default_role_id' => $roleId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function migrateLegacyRoleReferences(int $legacyId, int $legalId, int $financeId, ?int $legalUserId): void
    {
        if (Schema::hasTable('users')) {
            $legacyUser = DB::table('users')
                ->where(function ($query) {
                    $query->where('username', 'legalfinance')
                        ->orWhere('email', 'legalfinance@ndc.gov.ph');
                })
                ->first();

            if ($legacyUser && $legalUserId) {
                $this->migrateLegacyUserReferences((int) $legacyUser->id, $legalUserId);
            }

            if ($legacyUser) {
                DB::table('users')->where('id', $legacyUser->id)->delete();
            }

            DB::table('users')
                ->where('default_role_id', $legacyId)
                ->update(['default_role_id' => $legalId]);
        }

        $this->migrateRoleColumnByText('approval_steps', 'role_id', $legacyId, $legalId, $financeId, ['step_name', 'soi_section']);
        $this->migrateRoleColumnByText('project_members', 'role_id', $legacyId, $legalId, $financeId, ['assignment_type', 'notes']);
        $this->migrateRoleColumnByText('project_invitations', 'role_id', $legacyId, $legalId, $financeId, ['assignment_type', 'message']);
        $this->migrateRoleColumnByText('default_requirements', 'responsible_role_id', $legacyId, $legalId, $financeId, ['group_name', 'item_name', 'gate_step', 'soi_section']);
        $this->migrateRoleColumnByText('project_requirements', 'responsible_role_id', $legacyId, $legalId, $financeId, ['group_name', 'item_name', 'gate_step', 'soi_section']);
    }

    private function migrateLegacyUserReferences(int $legacyUserId, int $legalUserId): void
    {
        $references = [
            'projects' => ['project_officer_id', 'workgroup_head_id', 'created_by', 'monitoring_activated_by', 'monitoring_submitted_by', 'monitoring_reviewed_by', 'implementation_started_by'],
            'project_members' => ['user_id', 'assigned_by'],
            'project_stage_history' => ['changed_by'],
            'project_status_history' => ['changed_by'],
            'tasks' => ['assigned_to', 'assigned_by'],
            'documents' => ['uploaded_by', 'submitted_by', 'update_requested_by'],
            'document_versions' => ['created_by'],
            'approval_step_records' => ['approver_id'],
            'notifications' => ['user_id'],
            'audit_logs' => ['user_id'],
            'project_images' => ['uploaded_by'],
            'project_requirements' => ['received_by'],
            'project_fund_releases' => ['prepared_by', 'reviewed_by', 'released_by'],
            'project_agreement_forms' => ['prepared_by', 'submitted_by', 'returned_by'],
            'project_monitoring_reports' => ['created_by', 'submitted_by', 'reviewed_by'],
            'project_monitoring_cycles' => ['opened_by', 'closed_by'],
            'divestment_cases' => ['created_by', 'closed_by'],
            'divestment_case_transitions' => ['transitioned_by'],
            'project_invitations' => ['invited_by_id'],
            'notification_template_versions' => ['created_by', 'published_by'],
        ];

        foreach ($references as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->where($column, $legacyUserId)
                    ->update([$column => $legalUserId]);
            }
        }
    }

    private function migrateRoleColumnByText(
        string $table,
        string $column,
        int $legacyId,
        int $legalId,
        int $financeId,
        array $textColumns
    ): void {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $existingTextColumns = array_values(array_filter(
            $textColumns,
            fn (string $textColumn) => Schema::hasColumn($table, $textColumn)
        ));

        if (!empty($existingTextColumns)) {
            DB::table($table)
                ->where($column, $legacyId)
                ->where(function ($query) use ($existingTextColumns) {
                    foreach ($existingTextColumns as $textColumn) {
                        $columnName = '`' . str_replace('`', '``', $textColumn) . '`';
                        $query->orWhereRaw('LOWER(COALESCE(' . $columnName . ', \'\')) LIKE ?', ['%finance%'])
                            ->orWhereRaw('LOWER(COALESCE(' . $columnName . ', \'\')) LIKE ?', ['%financial%'])
                            ->orWhereRaw('LOWER(COALESCE(' . $columnName . ', \'\')) LIKE ?', ['%fund release%'])
                            ->orWhereRaw('LOWER(COALESCE(' . $columnName . ', \'\')) LIKE ?', ['%collection%'])
                            ->orWhereRaw('LOWER(COALESCE(' . $columnName . ', \'\')) LIKE ?', ['%receipt%'])
                            ->orWhereRaw('LOWER(COALESCE(' . $columnName . ', \'\')) LIKE ?', ['%valuation%'])
                            ->orWhereRaw('LOWER(COALESCE(' . $columnName . ', \'\')) LIKE ?', ['%remittance%']);
                    }
                })
                ->update([$column => $financeId]);
        }

        DB::table($table)
            ->where($column, $legacyId)
            ->update([$column => $legalId]);
    }

    private function deleteLegacyRole(int $legacyId): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('role_id', $legacyId)->delete();
        }

        DB::table('roles')->where('id', $legacyId)->delete();
    }

    private function splitAgreementAndFundReleaseStep(
        string $workflowName,
        string $oldStepName,
        string $legalStepName,
        string $financeStepName,
        int $legalId,
        int $financeId
    ): void {
        $workflowId = DB::table('approval_workflows')->where('name', $workflowName)->value('id');
        if (!$workflowId) {
            return;
        }

        $step = DB::table('approval_steps')
            ->where('workflow_id', $workflowId)
            ->where(function ($query) use ($oldStepName, $legalStepName) {
                $query->where('step_name', $oldStepName)->orWhere('step_name', $legalStepName);
            })
            ->orderBy('step_order')
            ->first();

        if (!$step) {
            return;
        }

        DB::table('approval_steps')->where('id', $step->id)->update([
            'role_id' => $legalId,
            'step_name' => $legalStepName,
            'soi_section' => $step->soi_section ?: 'agreement_fund_release',
            'requires_agreement_form' => true,
        ]);

        $financeExists = DB::table('approval_steps')
            ->where('workflow_id', $workflowId)
            ->where('step_name', $financeStepName)
            ->exists();

        if ($financeExists) {
            DB::table('approval_steps')
                ->where('workflow_id', $workflowId)
                ->where('step_name', $financeStepName)
                ->update([
                    'role_id' => $financeId,
                    'soi_section' => $step->soi_section ?: 'agreement_fund_release',
                    'requires_agreement_form' => false,
                ]);
            return;
        }

        DB::table('approval_steps')
            ->where('workflow_id', $workflowId)
            ->where('step_order', '>', $step->step_order)
            ->increment('step_order');

        DB::table('approval_steps')->insert([
            'workflow_id' => $workflowId,
            'step_order' => ((int) $step->step_order) + 1,
            'role_id' => $financeId,
            'step_name' => $financeStepName,
            'soi_section' => $step->soi_section ?: 'agreement_fund_release',
            'sla_days' => null,
            'requires_agreement_form' => false,
            'is_required' => true,
            'can_skip' => false,
            'created_at' => now(),
        ]);
    }
};
