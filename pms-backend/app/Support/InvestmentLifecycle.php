<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class InvestmentLifecycle
{
    public const LABELS = [
        'under_evaluation' => 'Investment Under Evaluation',
        'board_approved' => 'Board Approved — Awaiting Deployment',
        'portfolio' => 'Investment Portfolio',
        'not_proceeding' => 'Not Proceeding',
    ];

    public static function boardApproval(Builder $query): void
    {
        $query->whereHas('workflow', fn ($workflow) => $workflow->where('workflow_group', 'origin'))
            ->whereNotIn('overall_status', ['rejected', 'disapproved', 'returned', 'cancelled'])
            ->whereRaw('project_approvals.id = (select max(pa.id) from project_approvals pa inner join approval_workflows aw on aw.id = pa.workflow_id where pa.project_id = project_approvals.project_id and aw.workflow_group = ?)', ['origin'])
            ->whereHas('stepRecords', function (Builder $records) {
                $records->whereIn('status', ['approved', 'approved_with_conditions'])
                    ->whereRaw('approval_step_records.id = (select max(ar.id) from approval_step_records ar where ar.project_approval_id = approval_step_records.project_approval_id and ar.step_id = approval_step_records.step_id)')
                    ->whereHas('step', function (Builder $steps) {
                        $steps->whereHas('role', fn ($roles) => $roles->whereRaw('LOWER(name) = ?', ['board']))
                            ->whereNotExists(function ($later) {
                                $later->selectRaw('1')->from('approval_steps as later')
                                    ->join('roles as board_role', 'board_role.id', '=', 'later.role_id')
                                    ->whereColumn('later.workflow_id', 'approval_steps.workflow_id')
                                    ->whereColumn('later.step_order', '>', 'approval_steps.step_order')
                                    ->whereRaw('LOWER(board_role.name) = ?', ['board']);
                            });
                    });
            });
    }

    public static function deployed(Builder $query): void
    {
        $query->where('status', 'released')->where('amount', '>', 0);
    }

    public static function notProceeding(Builder $query): void
    {
        $query->whereIn('overall_status', ['rejected', 'disapproved', 'cancelled'])
            ->whereHas('workflow', fn ($workflow) => $workflow->where('workflow_group', 'origin'))
            ->whereRaw('project_approvals.id = (select max(pa.id) from project_approvals pa inner join approval_workflows aw on aw.id = pa.workflow_id where pa.project_id = project_approvals.project_id and aw.workflow_group = ?)', ['origin']);
    }

    public static function filter(Builder $query, ?string $status): void
    {
        if (!isset(self::LABELS[$status ?? ''])) {
            return;
        }
        $query->where('record_type', 'investment');
        if ($status === 'not_proceeding') {
            $query->whereHas('approvals', self::notProceeding(...));
        } elseif ($status === 'under_evaluation') {
            $query->whereDoesntHave('approvals', self::notProceeding(...))->whereDoesntHave('approvals', self::boardApproval(...));
        } else {
            $query->whereHas('approvals', self::boardApproval(...));
            $status === 'portfolio'
                ? $query->whereHas('fundReleases', self::deployed(...))
                : $query->whereDoesntHave('fundReleases', self::deployed(...));
        }
    }
}
