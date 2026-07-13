import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type {
  CloseDivestmentCasePayload,
  CreateDivestmentCasePayload,
  DivestmentCase,
  DivestmentCaseFilters,
  DivestmentPhase,
  DivestmentState,
} from '@/types/divestment'

export const useDivestmentStore = defineStore('divestment', {
  state: (): DivestmentState => ({
    cases: [],
    selectedCase: null,
    pagination: null,
    loading: false,
    submitting: false,
  }),

  getters: {
    activeCases: (state) => state.cases.filter(item => item.status === 'active'),
    closedCases: (state) => state.cases.filter(item => item.status === 'closed'),
  },

  actions: {
    upsert(item: DivestmentCase) {
      const index = this.cases.findIndex(existing => existing.id === item.id)
      if (index === -1) this.cases.unshift(item)
      else this.cases[index] = item
      if (this.selectedCase?.id === item.id) this.selectedCase = item
    },

    async fetchCases(filters: DivestmentCaseFilters = {}) {
      this.loading = true
      try {
        const response = await axiosInstance.get('/api/divestment-cases', { params: filters })
        this.cases = response.data.data || []
        this.pagination = response.data.meta || null
      } finally {
        this.loading = false
      }
    },

    async createCase(payload: CreateDivestmentCasePayload) {
      this.submitting = true
      try {
        const response = await axiosInstance.post('/api/divestment-cases', payload)
        const item = response.data.data as DivestmentCase
        this.upsert(item)
        return item
      } finally {
        this.submitting = false
      }
    },

    async updateCase(id: number, payload: Partial<CloseDivestmentCasePayload>) {
      this.submitting = true
      try {
        const response = await axiosInstance.patch(`/api/divestment-cases/${id}`, payload)
        const item = response.data.data as DivestmentCase
        this.upsert(item)
        return item
      } finally {
        this.submitting = false
      }
    },

    async transition(id: number, toPhase: DivestmentPhase, notes: string) {
      this.submitting = true
      try {
        const response = await axiosInstance.post(`/api/divestment-cases/${id}/transition`, {
          to_phase: toPhase,
          notes,
        })
        const item = response.data.data as DivestmentCase
        this.upsert(item)
        return item
      } finally {
        this.submitting = false
      }
    },

    async closeCase(id: number, payload: CloseDivestmentCasePayload) {
      this.submitting = true
      try {
        const response = await axiosInstance.post(`/api/divestment-cases/${id}/close`, payload)
        const item = response.data.data as DivestmentCase
        this.upsert(item)
        return item
      } finally {
        this.submitting = false
      }
    },
  },
})
