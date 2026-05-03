// ── PSGC (Philippine Standard Geographic Code) types ──────────────────────
// Mirrors the response shape of /api/v1/public/locations/* endpoints
// Backed by app/Models/Philippine{Region,Province,City,Barangay}.php

export interface PsgcRegion {
  id: number
  psgc_code: string
  region_code: string
  region_description: string
}

export interface PsgcProvince {
  id: number
  psgc_code: string
  province_code: string
  province_description: string
  region_code: string
}

export interface PsgcCity {
  id: number
  psgc_code: string
  city_municipality_code: string
  city_municipality_description: string
  province_code: string
  region_description: string
}

export interface PsgcBarangay {
  id: number
  barangay_code: string
  barangay_description: string
  city_municipality_code: string
  province_code: string
  region_code: string
}

// ── Map-side location filter (selected region/province/city/barangay) ──────
export interface LocationFilter {
  regionCode:    string
  regionName:    string
  provinceCode:  string
  provinceName:  string
  cityCode:      string
  cityName:      string
  barangayCode:  string
  barangayName:  string
}

export const emptyLocation = (): LocationFilter => ({
  regionCode:   '',
  regionName:   '',
  provinceCode: '',
  provinceName: '',
  cityCode:     '',
  cityName:     '',
  barangayCode: '',
  barangayName: '',
})
