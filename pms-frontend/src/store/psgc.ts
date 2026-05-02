import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type {
  PsgcRegion,
  PsgcProvince,
  PsgcCity,
  PsgcBarangay,
} from '@/types/psgc'

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
        const { data } = await axiosInstance.get('/api/v1/public/locations/regions')
        this.regions = Array.isArray(data?.data) ? data.data : []
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
        const { data } = await axiosInstance.get('/api/v1/public/locations/provinces', {
          params: { region_code: regionCode },
        })
        this.provinces            = Array.isArray(data?.data) ? data.data : []
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
        const { data } = await axiosInstance.get('/api/v1/public/locations/cities', {
          params: { province_code: provinceCode },
        })
        this.cities             = Array.isArray(data?.data) ? data.data : []
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
        const { data } = await axiosInstance.get('/api/v1/public/locations/barangays', {
          params: { city_code: cityCode },
        })
        this.barangays          = Array.isArray(data?.data) ? data.data : []
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
