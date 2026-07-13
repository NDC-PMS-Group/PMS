import { defineStore } from 'pinia';
import axiosInstance from '@/utils/axiosInstance';
import type { DashboardFilters, DashboardStats } from '@/types/dashboard';

const defaultFilters = (): DashboardFilters => ({
  year: null,
  due_window: '14',
  scope: 'mine',
  sector_id: null,
  stage_id: null,
  origin_track: null,
  lifecycle_phase: null,
  officer_id: null,
});

export const useDashboardStore = defineStore('decision-support-dashboard', {
  state: () => ({
    stats: null as DashboardStats | null,
    filters: defaultFilters(),
    loading: false,
    error: '' as string,
    requestId: 0,
  }),

  getters: {
    isPortfolioMode: (state): boolean => state.stats?.filters.role.mode === 'portfolio',
    hasActiveFilters: (state): boolean => Boolean(
      state.filters.year
      || state.filters.sector_id
      || state.filters.stage_id
      || state.filters.origin_track
      || state.filters.lifecycle_phase
      || state.filters.officer_id
      || state.filters.due_window !== '14'
      || state.filters.scope !== (state.stats?.filters.scopes[0]?.value ?? 'mine')
    ),
  },

  actions: {
    async fetchDashboard(): Promise<void> {
      const requestId = ++this.requestId;
      this.loading = true;
      this.error = '';

      try {
        const params = Object.fromEntries(
          Object.entries(this.filters).filter(([, value]) => value !== null && value !== '')
        );
        const { data } = await axiosInstance.get<DashboardStats>('/api/dashboard/stats', { params });

        if (requestId !== this.requestId) return;
        this.stats = data;
        this.filters = { ...data.filters.applied };
      } catch (error: any) {
        if (requestId !== this.requestId) return;
        this.error = error?.response?.data?.message || 'Dashboard data could not be loaded.';
      } finally {
        if (requestId === this.requestId) this.loading = false;
      }
    },

    async resetFilters(): Promise<void> {
      const defaultScope = this.stats?.filters.scopes[0]?.value ?? 'mine';
      this.filters = { ...defaultFilters(), scope: defaultScope };
      await this.fetchDashboard();
    },
  },
});
