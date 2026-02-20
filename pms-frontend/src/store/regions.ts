import { defineStore } from 'pinia'
import axiosInstance from "@/utils/axiosInstance";
import { toast } from "vue3-toastify";
import type { Region, CreateRegionPayload, UpdateRegionPayload } from '@/types/region'

export const useRegionStore = defineStore('regions', {
  state: () => ({
    regions: [] as Region[],
    currentRegion: null as Region | null,
    loading: false,
    error: null as string | null
  }),

  getters: {
    getRegionById: (state) => (id: number) => {
      return state.regions.find(region => region.id === id)
    },
    
    regionsCount: (state) => state.regions.length,
    
    // Get regions formatted for dropdown
    regionsForDropdown: (state) => {
      return state.regions.map(region => ({
        id: region.id,
        name: `${region.region} - ${region.office_name}`,
        region: region.region,
        office_name: region.office_name
      }))
    },
    
    // Get total divisions across all regions
    totalDivisions: (state) => {
      return state.regions.reduce((sum, region) => sum + (region.divisions_count || 0), 0)
    }
  },

  actions: {
    /**
     * Fetch all regions
     */
    async fetchRegions() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosInstance.get('/api/regions')

        this.regions = Array.isArray(response.data) ? response.data : []
        return this.regions
      } catch (error: any) {
        this.error = error.response?.data?.message || 
                   error.response?.data?.error || 
                   'Failed to fetch regions'
        console.error('Error fetching regions:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Fetch a single region by ID
     */
    async fetchRegion(id: number) {
      this.loading = true
      this.error = null
      try {
        const response = await axiosInstance.get(`/api/regions/${id}`)
        
        // Laravel returns the region directly
        this.currentRegion = response.data
        
        // Also update in regions array if exists
        const index = this.regions.findIndex(r => r.id === id)
        if (index !== -1) {
          this.regions[index] = response.data
        }
        
        return this.currentRegion
      } catch (error: any) {
        this.error = error.response?.data?.message || 
                   error.response?.data?.error || 
                   'Failed to fetch region'
        console.error('Error fetching region:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Create a new region
     */
    async createRegion(payload: CreateRegionPayload) {
      this.loading = true
      this.error = null
      try {
        const response = await axiosInstance.post('/api/regions', payload)
      
        const newRegion = response.data.data
        
        this.regions.unshift(newRegion)
        
        toast.success(response.data.message || 'Region created successfully')
        return newRegion
      } catch (error: any) {
        // Handle validation errors from Laravel
        if (error.response?.status === 422) {
          this.error = error.response.data.message || 'Validation failed'
          const errors = error.response.data.errors
          if (errors) {
            // Show first validation error
            const firstError = Object.values(errors)[0] as string[]
            if (firstError && firstError.length > 0) {
              this.error = firstError[0]
            }
          }
        } else {
          this.error = error.response?.data?.message || 'Failed to create region'
        }
        
        toast.error(this.error || 'An error occurred')
        console.error('Error creating region:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Update an existing region
     */
    async updateRegion(id: number, payload: UpdateRegionPayload) {
      this.loading = true
      this.error = null
      try {
        const response = await axiosInstance.put(`/api/regions/${id}`, payload)
        
        // Laravel returns { message: string, data: Region }
        const updatedRegion = response.data.data
        
        // Update in the state
        const index = this.regions.findIndex(r => r.id === id)
        if (index !== -1) {
          this.regions[index] = updatedRegion
        }
        
        // Also update currentRegion if it's the one being edited
        if (this.currentRegion && this.currentRegion.id === id) {
          this.currentRegion = updatedRegion
        }
        
        toast.success(response.data.message || 'Region updated successfully')
        return updatedRegion
      } catch (error: any) {
        // Handle validation errors from Laravel
        if (error.response?.status === 422) {
          this.error = error.response.data.message || 'Validation failed'
          const errors = error.response.data.errors
          if (errors) {
            // Show first validation error
            const firstError = Object.values(errors)[0] as string[]
            if (firstError && firstError.length > 0) {
              this.error = firstError[0]
            }
          }
        } else {
          this.error = error.response?.data?.message || 'Failed to update region'
        }
        
        toast.error(this.error || 'An error occurred')
        console.error('Error updating region:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Delete a region
     */
    async deleteRegion(id: number) {
      this.loading = true
      this.error = null
      try {
        const response = await axiosInstance.delete(`/api/regions/${id}`)
        
        // Remove from state
        this.regions = this.regions.filter(r => r.id !== id)
        
        // Clear currentRegion if it's the one being deleted
        if (this.currentRegion && this.currentRegion.id === id) {
          this.currentRegion = null
        }
        
        toast.success(response.data.message || 'Region deleted successfully')
        return true
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to delete region'
        toast.error(this.error || 'An error occurred')
        console.error('Error deleting region:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Search regions by name or code
     */
    searchRegions(query: string): Region[] {
      if (!query.trim()) return this.regions
      
      const lowerQuery = query.toLowerCase()
      return this.regions.filter(region => 
        region.office_name.toLowerCase().includes(lowerQuery) ||
        region.region.toLowerCase().includes(lowerQuery) ||
        region.invoicing_acc_officer?.toLowerCase().includes(lowerQuery) ||
        region.receiving_acc_officer?.toLowerCase().includes(lowerQuery)
      )
    },

    /**
     * Clear current region
     */
    clearCurrentRegion() {
      this.currentRegion = null
    },

    /**
     * Clear error state
     */
    clearError() {
      this.error = null
    },

    /**
     * Reset store to initial state
     */
    $reset() {
      this.regions = []
      this.currentRegion = null
      this.loading = false
      this.error = null
    }
  }
})