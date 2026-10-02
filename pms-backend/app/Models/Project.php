<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $with = ['additionalLocations'];

    protected $fillable = [
        'record_type',
        'project_type_other',
        'industry_other',
        'sector_other',
        'operations_start_date',
        'other_financing',
        'narrative_content',
        'project_code',
        'title',
        'description',
        'process_track',
        'origin_track',
        'lifecycle_phase',
        'lifecycle_phase_started_at',
        'implementation_started_by',
        'date_of_application',
        'project_type_id',
        'industry_id',
        'sector_id',
        'investment_type_id',
        'investment_type_other',
        'funding_source_id',
        'funding_source_other',
        'estimated_cost',
        'actual_cost',
        'target_amount_to_raise',
        'ndc_participation',
        'ndc_investment_criteria',
        'ndc_investment_criteria_other',
        'project_rationale',
        'company_background',
        'target_beneficiaries',
        'expected_benefits',
        'risk_analysis',
        'financial_metrics',
        'implementation_milestones',
        'issues_problems',
        'next_steps',
        'post_investment_strategy',
        'monitoring_status',
        'monitoring_submission_status',
        'monitoring_draft_saved_at',
        'monitoring_submitted_at',
        'monitoring_submitted_by',
        'monitoring_reviewed_at',
        'monitoring_reviewed_by',
        'monitoring_review_notes',
        'monitoring_activated_at',
        'monitoring_activated_by',
        'monitoring_due_date',
        'monitoring_instructions',
        'monitoring_proponent_access',
        'monitoring_closed_at',
        'currency',
        'current_stage_id',
        'status_id',
        'proposal_date',
        'start_date',
        'target_completion_date',
        'actual_completion_date',
        'location_address',
        'location_region_code',
        'location_region_name',
        'location_province_code',
        'location_province_name',
        'location_city_code',
        'location_city_name',
        'location_barangay_code',
        'location_barangay_name',
        'location_street',
        'location_lat',
        'location_lng',
        'map_layer',
        'thumbnail_url',
        'logo_url',
        'project_officer_id',
        'workgroup_head_id',
        'proponent_name',
        'proponent_contact',
        'proponent_email',
        'is_svf',
        'is_legacy',
        'is_archived',
        'is_deleted',
        'created_by',
    ];

    protected $casts = [
        'operations_start_date' => 'date',
        'other_financing' => 'array',
        'narrative_content' => 'array',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'target_amount_to_raise' => 'decimal:2',
        'ndc_participation' => 'decimal:2',
        'ndc_investment_criteria' => 'array',
        'financial_metrics' => 'array',
        'implementation_milestones' => 'array',
        'location_lat' => 'decimal:8',
        'location_lng' => 'decimal:8',
        'date_of_application' => 'date',
        'lifecycle_phase_started_at' => 'datetime',
        'proposal_date' => 'date',
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'monitoring_activated_at' => 'datetime',
        'monitoring_draft_saved_at' => 'datetime',
        'monitoring_submitted_at' => 'datetime',
        'monitoring_reviewed_at' => 'datetime',
        'monitoring_due_date' => 'date',
        'monitoring_proponent_access' => 'boolean',
        'monitoring_closed_at' => 'datetime',
        'is_svf' => 'boolean',
        'is_legacy' => 'boolean',
        'is_archived' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function additionalLocations()
    {
        return $this->hasMany(ProjectLocation::class);
    }

    public function scopeWithInvestmentState($query)
    {
        return $query->with('additionalLocations')->withExists([
            'approvals as has_board_approval' => \App\Support\InvestmentLifecycle::boardApproval(...),
            'approvals as investment_not_proceeding' => \App\Support\InvestmentLifecycle::notProceeding(...),
            'fundReleases as has_investment_deployment' => \App\Support\InvestmentLifecycle::deployed(...),
        ]);
    }

    public function getInvestmentStatusAttribute(): ?string
    {
        if ($this->record_type !== 'investment') {
            return null;
        }
        if ($this->attributes['investment_not_proceeding']
            ?? $this->approvals()->where(\App\Support\InvestmentLifecycle::notProceeding(...))->exists()) {
            return 'not_proceeding';
        }
        $boardApproved = $this->attributes['has_board_approval']
            ?? $this->approvals()->where(\App\Support\InvestmentLifecycle::boardApproval(...))->exists();
        $deployed = $this->attributes['has_investment_deployment']
            ?? $this->fundReleases()->where(\App\Support\InvestmentLifecycle::deployed(...))->exists();

        return !$boardApproved ? 'under_evaluation' : ($deployed ? 'portfolio' : 'board_approved');
    }

    public function scopeClassified($query, ?string $type, ?string $status = null)
    {
        if ($type === 'unclassified') {
            $query->whereNull('record_type');
        } elseif (in_array($type, ['project', 'investment'], true)) {
            $query->where('record_type', $type);
        }
        \App\Support\InvestmentLifecycle::filter($query, $status);

        return $query;
    }

    // Relationships
    public function projectType()
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function investmentType()
    {
        return $this->belongsTo(InvestmentType::class);
    }

    public function fundingSource()
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function monitoringSubmittedBy()
    {
        return $this->belongsTo(User::class, 'monitoring_submitted_by');
    }

    public function monitoringReviewedBy()
    {
        return $this->belongsTo(User::class, 'monitoring_reviewed_by');
    }

    public function currentStage()
    {
        return $this->belongsTo(ProjectStage::class, 'current_stage_id');
    }

    public function status()
    {
        return $this->belongsTo(ProjectStatus::class, 'status_id');
    }

    public function projectOfficer()
    {
        return $this->belongsTo(User::class, 'project_officer_id');
    }

    public function workgroupHead()
    {
        return $this->belongsTo(User::class, 'workgroup_head_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function monitoringActivatedBy()
    {
        return $this->belongsTo(User::class, 'monitoring_activated_by');
    }

    public function monitoringReports()
    {
        return $this->hasMany(ProjectMonitoringReport::class);
    }

    public function monitoringCycles()
    {
        return $this->hasMany(ProjectMonitoringCycle::class);
    }

    public function activeMonitoringCycle()
    {
        return $this->hasOne(ProjectMonitoringCycle::class)->where('status', 'open')->latestOfMany();
    }

    public function latestMonitoringReport()
    {
        return $this->hasOne(ProjectMonitoringReport::class)->latestOfMany();
    }

    public function implementationStartedBy()
    {
        return $this->belongsTo(User::class, 'implementation_started_by');
    }

    public function proponentUser()
    {
        return $this->belongsTo(User::class, 'proponent_email', 'email');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function invitations()
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'project_tags');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function agreementForm()
    {
        return $this->hasOne(ProjectAgreementForm::class);
    }

    public function legacyDetail()
    {
        return $this->hasOne(ProjectLegacyDetail::class);
    }

    public function images()
    {
        return $this->hasMany(ProjectImage::class)
            ->active()
            ->orderByDesc('is_thumbnail')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function thumbnailImage()
    {
        return $this->hasOne(ProjectImage::class)
            ->active()
            ->where('is_thumbnail', true);
    }

    public function requirements()
    {
        return $this->hasMany(ProjectRequirement::class)->orderBy('sort_order')->orderBy('id');
    }

    public function fundReleases()
    {
        return $this->hasMany(ProjectFundRelease::class)->latest('release_date')->latest('id');
    }

    public function resources()
    {
        return $this->hasMany(ProjectResource::class);
    }

    public function stageHistory()
    {
        return $this->hasMany(ProjectStageHistory::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(ProjectStatusHistory::class);
    }

    public function approvals()
    {
        return $this->hasMany(ProjectApproval::class);
    }

    public function kpis()
    {
        return $this->hasMany(ProjectKpi::class);
    }

    public function svfApplication()
    {
        return $this->hasOne(SvfApplication::class);
    }

    public function divestmentCase()
    {
        return $this->hasOne(DivestmentCase::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false)
                     ->where('is_archived', false);
    }

    public function scopeVisibleDraftsTo($query, ?User $user)
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($draftQuery) use ($user) {
            $draftQuery
                ->where('created_by', $user->id)
                ->orWhereDoesntHave('status', fn ($statusQuery) => $statusQuery->where('name', 'Draft'));
        });
    }

    public function scopeAccessibleTo($query, ?User $user, array $globalPermissions = ['projects.view'], bool $forceMine = false)
    {
        $query->visibleDraftsTo($user);
        if (! $user) {
            return $query;
        }

        if ($this->isExternalProponent($user)) {
            return $this->scopeMineToUser($query, $user);
        }

        $hasGlobalAccess = (int) $user->default_role_id === 1
            || $user->hasRole('superadmin')
            || collect($globalPermissions)->contains(fn (string $permission) => $user->hasPermissionTo($permission));

        if ($hasGlobalAccess && ! $forceMine) {
            return $query;
        }

        return $this->scopeMineToUser($query, $user);
    }

    private function scopeMineToUser($query, User $user)
    {
        return $query->where(function ($projectQuery) use ($user) {
            $projectQuery->where('created_by', $user->id)
                ->orWhere('proponent_email', $user->email)
                ->orWhere('project_officer_id', $user->id)
                ->orWhere('workgroup_head_id', $user->id)
                ->orWhereHas('members', fn ($memberQuery) => $memberQuery
                    ->where('user_id', $user->id)
                    ->whereNull('removed_at')
                    ->where('can_view', true));
        });
    }

    private function isExternalProponent(User $user): bool
    {
        return (int) $user->default_role_id === 7
            || $user->hasRole('Proponent')
            || strtolower((string) ($user->defaultRole?->name ?? '')) === 'proponent';
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeSvf($query)
    {
        return $query->where('is_svf', true);
    }

    public function scopeByStage($query, $stageId)
    {
        return $query->where('current_stage_id', $stageId);
    }

    public function scopeByStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    // Mutators
    public function setProjectCodeAttribute($value)
    {
        $this->attributes['project_code'] = strtoupper($value);
    }

    // Accessors
    public function getIsOverdueAttribute()
    {
        if (!$this->target_completion_date) {
            return false;
        }
        return now()->gt($this->target_completion_date) && !$this->actual_completion_date;
    }

    public function getProgressPercentageAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }
        $completedTasks = $this->tasks()->where('status', 'completed')->count();
        return round(($completedTasks / $totalTasks) * 100);
    }
}
