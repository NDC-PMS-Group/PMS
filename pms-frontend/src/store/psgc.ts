import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type {
  PsgcRegion,
  PsgcProvince,
  PsgcCity,
  PsgcBarangay,
} from '@/types/psgc'

const rowsFrom = (data: any): any[] => {
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  return []
}

const sortByLabel = <T>(items: T[], label: (item: T) => string): T[] =>
  [...items].sort((a, b) =>
    label(a).localeCompare(label(b), undefined, { numeric: true, sensitivity: 'base' }),
  )

const regionLabel = (raw: any): string => {
  if (raw.region_description) return raw.region_description
  if (raw.regionName && raw.name && raw.regionName !== raw.name) {
    return `${raw.regionName} - ${raw.name}`
  }
  return raw.name ?? ''
}

const toRegion = (raw: any): PsgcRegion => ({
  id: Number(raw.id ?? 0),
  psgc_code: raw.psgc_code ?? raw.code ?? '',
  region_code: raw.region_code ?? raw.regionCode ?? raw.code ?? '',
  region_description: regionLabel(raw),
})

const toProvince = (raw: any): PsgcProvince => ({
  id: Number(raw.id ?? 0),
  psgc_code: raw.psgc_code ?? raw.code ?? '',
  province_code: raw.province_code ?? raw.provinceCode ?? raw.code ?? '',
  province_description: raw.province_description ?? raw.name ?? '',
  region_code: raw.region_code ?? raw.regionCode ?? '',
})

const toCity = (raw: any): PsgcCity => ({
  id: Number(raw.id ?? 0),
  psgc_code: raw.psgc_code ?? raw.code ?? '',
  city_municipality_code: raw.city_municipality_code ?? raw.cityCode ?? raw.municipalityCode ?? raw.code ?? '',
  city_municipality_description: raw.city_municipality_description ?? raw.name ?? '',
  province_code: raw.province_code ?? raw.provinceCode ?? '',
  region_description: raw.region_description ?? raw.regionName ?? '',
})

const toBarangay = (raw: any, cityCode = ''): PsgcBarangay => ({
  id: Number(raw.id ?? 0),
  barangay_code: raw.barangay_code ?? raw.code ?? '',
  barangay_description: raw.barangay_description ?? raw.name ?? '',
  city_municipality_code: raw.city_municipality_code ?? raw.cityCode ?? raw.municipalityCode ?? cityCode,
  province_code: raw.province_code ?? raw.provinceCode ?? '',
  region_code: raw.region_code ?? raw.regionCode ?? '',
})

interface PsgcState {
  regions:   PsgcRegion[]
  provinces: PsgcProvince[]
  cities:    PsgcCity[]
  barangays: PsgcBarangay[]

  loadingRegions:   boolean
  loadingProvinces: boolean
  loadingCities:    boolean
  loadingBarangays: boolean

  // Cache keys — avoid re-fetching the same parent
  cachedProvinceRegion: string
  cachedCityProvince:   string
  cachedBarangayCity:   string
}

export const usePsgcStore = defineStore('psgc', {
  state: (): PsgcState => ({
    regions:   [],
    provinces: [],
    cities:    [],
    barangays: [],

    loadingRegions:   false,
    loadingProvinces: false,
    loadingCities:    false,
    loadingBarangays: false,

    cachedProvinceRegion: '',
    cachedCityProvince:   '',
    cachedBarangayCity:   '',
  }),

  actions: {
    // ── Regions (loaded once) ───────────────────────────────────────────
    async fetchRegions(): Promise<void> {
      if (this.regions.length) return // already loaded
      this.loadingRegions = true
      try {
        const { data } = await axiosInstance.get('/api/locations/regions')
        this.regions = sortByLabel(rowsFrom(data).map(toRegion), item => item.region_description)
      } catch {
        this.regions = []
      } finally {
        this.loadingRegions = false
      }
    },

    // ── Provinces (cached per region) ───────────────────────────────────
    async fetchProvinces(regionCode: string): Promise<void> {
      if (this.cachedProvinceRegion === regionCode && this.provinces.length) return
      this.loadingProvinces   = true
      this.provinces          = []
      this.cities             = []
      this.barangays          = []
      this.cachedCityProvince = ''
      this.cachedBarangayCity = ''
      try {
        const { data } = await axiosInstance.get(`/api/locations/regions/${regionCode}/provinces`)
        this.provinces            = sortByLabel(rowsFrom(data).map(toProvince), item => item.province_description)
        this.cachedProvinceRegion = regionCode
      } catch {
        this.provinces = []
      } finally {
        this.loadingProvinces = false
      }
    },

    // ── Cities (cached per province) ────────────────────────────────────
    async fetchCities(provinceCode: string): Promise<void> {
      if (this.cachedCityProvince === provinceCode && this.cities.length) return
      this.loadingCities      = true
      this.cities             = []
      this.barangays          = []
      this.cachedBarangayCity = ''
      try {
        const { data } = await axiosInstance.get('/api/locations/cities-municipalities', {
          params: { province_code: provinceCode },
        })
        this.cities             = sortByLabel(rowsFrom(data).map(toCity), item => item.city_municipality_description)
        this.cachedCityProvince = provinceCode
      } catch {
        this.cities = []
      } finally {
        this.loadingCities = false
      }
    },

    // ── Barangays (cached per city) ─────────────────────────────────────
    async fetchBarangays(cityCode: string): Promise<void> {
      if (this.cachedBarangayCity === cityCode && this.barangays.length) return
      this.loadingBarangays = true
      this.barangays        = []
      try {
        const { data } = await axiosInstance.get('/api/locations/barangays', {
          params: { city_code: cityCode },
        })
        this.barangays          = sortByLabel(
          rowsFrom(data).map(item => toBarangay(item, cityCode)),
          item => item.barangay_description,
        )
        this.cachedBarangayCity = cityCode
      } catch {
        this.barangays = []
      } finally {
        this.loadingBarangays = false
      }
    },

    // ── Clear downstream when parent selection changes ──────────────────
    clearProvinceDown(): void {
      this.provinces            = []
      this.cities               = []
      this.barangays            = []
      this.cachedProvinceRegion = ''
      this.cachedCityProvince   = ''
      this.cachedBarangayCity   = ''
    },

    clearCityDown(): void {
      this.cities             = []
      this.barangays          = []
      this.cachedCityProvince = ''
      this.cachedBarangayCity = ''
    },

    clearBarangays(): void {
      this.barangays          = []
      this.cachedBarangayCity = ''
    },
  },
})
