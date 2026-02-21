import { defineStore } from 'pinia';
import type { MapProject, MapFilters, MapState } from '@/types/map';
import { parseMapProjectList } from '@/types/map';
import axiosInstance from '@/utils/axiosInstance';

// Mirrors the fallback pattern from projectStore.ts
const MAP_ENDPOINTS = ['/api/projects/map'];

const isFallbackCandidateError = (error: any): boolean => {
  const status = error?.response?.status;
  return status === 404 || status === 405;
};

const requestWithFallback = async <T>(
  requestFn: (basePath: string) => Promise<T>,
  basePaths: string[]
): Promise<T> => {
  let lastError: any = null;

  for (const basePath of basePaths) {
    try {
      return await requestFn(basePath);
    } catch (error: any) {
      lastError = error;
      if (!isFallbackCandidateError(error)) {
        throw error;
      }
    }
  }

  throw lastError;
};

export const useMapStore = defineStore('map', {
  state: (): MapState => ({
    mapProjects: [],
    selectedProject: null,
    filters: {
      status_id: null,
      project_type_id: null,
      stage_id: null,
      bounds: null,
    },
    loading: false,
    error: null,
  }),

  getters: {
    // All projects that have valid coordinates (safety net on top of API filter)
    plottableProjects: (state): MapProject[] =>
      state.mapProjects.filter(
        (p) => p.location.latitude !== null && p.location.longitude !== null
      ),

    // Count per status — used for the map legend / filter bar
    projectCountByStatus: (state): Record<string, number> => {
      return state.mapProjects.reduce<Record<string, number>>((acc, p) => {
        const key = p.status?.name ?? 'Unknown';
        acc[key] = (acc[key] ?? 0) + 1;
        return acc;
      }, {});
    },

    // All unique statuses present in the current map data
    // Useful for building a dynamic filter/legend from real data
    activeStatuses: (state): { id: number; name: string; color_code: string }[] => {
      const seen = new Map<number, { id: number; name: string; color_code: string }>();
      for (const p of state.mapProjects) {
        if (p.status && !seen.has(p.status.id)) {
          seen.set(p.status.id, p.status);
        }
      }
      return Array.from(seen.values());
    },

    totalProjects: (state): number => state.mapProjects.length,

    hasActiveFilters: (state): boolean =>
      state.filters.status_id !== null ||
      state.filters.project_type_id !== null ||
      state.filters.stage_id !== null,
  },

  actions: {
    getApiErrorMessage(error: any, fallback: string): string {
      return (
        error?.response?.data?.error ||
        error?.response?.data?.message ||
        error?.message ||
        fallback
      );
    },

    async fetchMapProjects(filters?: Partial<MapFilters>) {
      this.loading = true;
      this.error = null;

      try {
        if (filters) {
          this.filters = { ...this.filters, ...filters };
        }

        const params = new URLSearchParams();
        Object.entries(this.filters).forEach(([key, value]) => {
          if (value !== null && value !== undefined && value !== '') {
            params.append(key, String(value));
          }
        });

        const query = params.toString();

        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(query ? `${basePath}?${query}` : basePath),
          MAP_ENDPOINTS
        );

        this.mapProjects = parseMapProjectList(response.data);
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, 'Failed to fetch map projects');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    setSelectedProject(project: MapProject | null) {
      this.selectedProject = project;
    },

    setFilters(filters: Partial<MapFilters>) {
      this.filters = { ...this.filters, ...filters };
    },

    resetFilters() {
      this.filters = {
        status_id: null,
        project_type_id: null,
        stage_id: null,
        bounds: null,
      };
    },

    clearError() {
      this.error = null;
    },
  },
});