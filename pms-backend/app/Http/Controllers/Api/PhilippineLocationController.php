<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhilippineRegion;
use App\Models\PhilippineProvince;
use App\Models\PhilippineCity;
use App\Models\PhilippineBarangay;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhilippineLocationController extends Controller
{
    use ApiResponse; 

    /**
     * GET /api/v1/public/locations/regions
     * Returns all Philippine regions ordered alphabetically.
     */
    public function regions(): JsonResponse
    {
        $regions = PhilippineRegion::orderBy('region_description')
            ->get(['id', 'psgc_code', 'region_code', 'region_description']);

        return $this->success($regions);
    }

    /**
     * GET /api/v1/public/locations/provinces?region_code=xxx
     * Returns provinces belonging to the given region_code.
     */
    public function provinces(Request $request): JsonResponse
    {
        $request->validate(['region_code' => 'required|string']);

        $provinces = PhilippineProvince::where('region_code', $request->region_code)
            ->orderBy('province_description')
            ->get(['id', 'psgc_code', 'province_code', 'province_description', 'region_code']);

        return $this->success($provinces);
    }

    /**
     * GET /api/v1/public/locations/cities?province_code=xxx
     * Returns cities/municipalities belonging to the given province_code.
     */
    public function cities(Request $request): JsonResponse
    {
        $request->validate(['province_code' => 'required|string']);

        $cities = PhilippineCity::where('province_code', $request->province_code)
            ->orderBy('city_municipality_description')
            ->get([
                'id', 'psgc_code', 'city_municipality_code',
                'city_municipality_description', 'province_code', 'region_description',
            ]);

        return $this->success($cities);
    }

    /**
     * GET /api/v1/public/locations/barangays?city_code=xxx
     * Returns barangays belonging to the given city_municipality_code.
     */
    public function barangays(Request $request): JsonResponse
    {
        $request->validate(['city_code' => 'required|string']);

        $barangays = PhilippineBarangay::where('city_municipality_code', $request->city_code)
            ->orderBy('barangay_description')
            ->get([
                'id', 'barangay_code', 'barangay_description',
                'city_municipality_code', 'province_code', 'region_code',
            ]);

        return $this->success($barangays);
    }
}
