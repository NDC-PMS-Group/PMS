<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    private const PSGC_BASE_URL = 'https://psgc.gitlab.io/api';

    public function regions()
    {
        return response()->json($this->psgc('/regions/'));
    }

    public function provinces(string $regionCode)
    {
        return response()->json($this->psgc("/regions/{$regionCode}/provinces/"));
    }

    public function citiesMunicipalities(Request $request)
    {
        $provinceCode = $request->query('province_code');
        $regionCode = $request->query('region_code');

        if ($provinceCode) {
            return response()->json($this->psgc("/provinces/{$provinceCode}/cities-municipalities/"));
        }

        if ($regionCode) {
            return response()->json($this->psgc("/regions/{$regionCode}/cities-municipalities/"));
        }

        return response()->json(['message' => 'province_code or region_code is required.'], 422);
    }

    public function barangays(Request $request)
    {
        $cityCode = $request->query('city_code');
        $municipalityCode = $request->query('municipality_code', $cityCode);

        if (!$municipalityCode) {
            return response()->json(['message' => 'city_code is required.'], 422);
        }

        return response()->json($this->psgc("/cities-municipalities/{$municipalityCode}/barangays/"));
    }

    public function geocode(Request $request)
    {
        $payload = $request->validate([
            'address' => 'required|string|max:500',
        ]);

        $address = trim($payload['address']);
        $cacheKey = 'geocode:' . sha1(mb_strtolower($address));

        $result = Cache::remember($cacheKey, now()->addDays(30), function () use ($address) {
            $response = Http::withHeaders([
                'User-Agent' => config('app.name', 'NDC PMS') . '/1.0',
            ])
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'ph',
                    'q' => $address,
                ]);

            if (!$response->ok()) {
                return null;
            }

            $first = collect($response->json())->first();
            if (!$first || !isset($first['lat'], $first['lon'])) {
                return null;
            }

            return [
                'latitude' => (float) $first['lat'],
                'longitude' => (float) $first['lon'],
                'display_name' => $first['display_name'] ?? $address,
            ];
        });

        if (!$result) {
            return response()->json(['message' => 'No coordinates found for this address.'], 404);
        }

        return response()->json($result);
    }

    private function psgc(string $path): array
    {
        return Cache::remember('psgc:' . $path, now()->addDays(7), function () use ($path) {
            $response = Http::timeout(10)->get(self::PSGC_BASE_URL . $path);

            if (!$response->ok()) {
                return [];
            }

            return collect($response->json())
                ->sortBy('name')
                ->values()
                ->all();
        });
    }
}
