import { defineStore } from 'pinia';
import axiosInstance from '@/utils/axiosInstance';
import type { GeocodeResult, PsgcLocation } from '@/types/location';

const sortLocations = (items: PsgcLocation[], label: (item: PsgcLocation) => string = item => item.name) =>
  [...items].sort((a, b) => label(a).localeCompare(label(b), undefined, { numeric: true, sensitivity: 'base' }));

export const useLocationStore = defineStore('locations', {
  state: () => ({
    regions: [] as PsgcLocation[],
    provinces: [] as PsgcLocation[],
    citiesMunicipalities: [] as PsgcLocation[],
    barangays: [] as PsgcLocation[],
    loading: false,
    geocoding: false,
    error: null as string | null,
  }),

  actions: {
    async fetchRegions() {
      if (this.regions.length) return this.regions;
      this.loading = true;
      try {
        const response = await axiosInstance.get('/api/locations/regions');
        this.regions = sortLocations(
          Array.isArray(response.data) ? response.data : [],
          region => `${region.regionName || region.name} - ${region.name}`,
        );
        return this.regions;
      } finally {
        this.loading = false;
      }
    },

    async fetchProvinces(regionCode: string) {
      this.provinces = [];
      this.citiesMunicipalities = [];
      this.barangays = [];
      if (!regionCode) return [];

      this.loading = true;
      try {
        const response = await axiosInstance.get(`/api/locations/regions/${regionCode}/provinces`);
        this.provinces = sortLocations(Array.isArray(response.data) ? response.data : []);
        return this.provinces;
      } finally {
        this.loading = false;
      }
    },

    async fetchCitiesMunicipalities(params: { regionCode?: string; provinceCode?: string }) {
      this.citiesMunicipalities = [];
      this.barangays = [];
      if (!params.regionCode && !params.provinceCode) return [];

      this.loading = true;
      try {
        const response = await axiosInstance.get('/api/locations/cities-municipalities', {
          params: {
            region_code: params.regionCode,
            province_code: params.provinceCode,
          },
        });
        this.citiesMunicipalities = sortLocations(Array.isArray(response.data) ? response.data : []);
        return this.citiesMunicipalities;
      } finally {
        this.loading = false;
      }
    },

    async fetchBarangays(cityCode: string) {
      this.barangays = [];
      if (!cityCode) return [];

      this.loading = true;
      try {
        const response = await axiosInstance.get('/api/locations/barangays', {
          params: { city_code: cityCode },
        });
        this.barangays = sortLocations(Array.isArray(response.data) ? response.data : []);
        return this.barangays;
      } finally {
        this.loading = false;
      }
    },

    async geocode(address: string): Promise<GeocodeResult | null> {
      const cleaned = address.trim();
      if (!cleaned) return null;

      this.geocoding = true;
      this.error = null;
      try {
        const response = await axiosInstance.post('/api/locations/geocode', { address: cleaned });
        return response.data as GeocodeResult;
      } catch (error: any) {
        this.error = error?.response?.data?.message || 'Unable to geocode this address';
        return null;
      } finally {
        this.geocoding = false;
      }
    },
  },
});
