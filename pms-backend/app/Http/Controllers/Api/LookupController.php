<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\ProjectTypeResource;
use App\Http\Resources\IndustryResource;
use App\Http\Resources\SectorResource;
use App\Http\Resources\InvestmentTypeResource;
use App\Http\Resources\FundingSourceResource;
use App\Http\Resources\ProjectStageResource;
use App\Http\Resources\ProjectStatusResource;
use App\Http\Resources\TagResource;
use App\Models\Role;
use App\Models\Permission;
use App\Models\ProjectType;
use App\Models\Industry;
use App\Models\Sector;
use App\Models\InvestmentType;
use App\Models\InvestmentCriterion;
use App\Models\FundingSource;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\Tag;

class LookupController extends Controller
{
    public function roles()
    {
        return RoleResource::collection(
            Role::query()
                ->where('name', '!=', 'Legal and Finance')
                ->orderBy('name')
                ->get()
        );
    }

    public function permissions()
    {
        return PermissionResource::collection(Permission::all());
    }

    public function projectTypes()
    {
        return ProjectTypeResource::collection(ProjectType::all());
    }

    public function industries()
    {
        return IndustryResource::collection(Industry::all());
    }

    public function sectors()
    {
        return SectorResource::collection(Sector::all());
    }

    public function investmentTypes()
    {
        $order = ['Equity', 'Convertible Notes', 'SAFE Notes', 'Bonds', 'Others'];

        return InvestmentTypeResource::collection(
            InvestmentType::query()
                ->whereIn('name', $order)
                ->get()
                ->sortBy(fn ($type) => array_search($type->name, $order, true))
                ->values()
        );
    }

    public function investmentCriteria()
    {
        return response()->json([
            'data' => InvestmentCriterion::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'description']),
        ]);
    }

    public function fundingSources()
    {
        $order = ['NDC Internal', 'Government Budget', 'Private Investors', 'International Grants', 'Bank Loans', 'Others'];

        return FundingSourceResource::collection(
            FundingSource::query()
                ->whereIn('name', $order)
                ->get()
                ->sortBy(fn ($source) => array_search($source->name, $order, true))
                ->values()
        );
    }

    public function projectStages()
    {
        return ProjectStageResource::collection(ProjectStage::active()->ordered()->get());
    }

    public function projectStatuses()
    {
        return ProjectStatusResource::collection(ProjectStatus::active()->get());
    }

    public function tags()
    {
        return TagResource::collection(Tag::all());
    }
}
