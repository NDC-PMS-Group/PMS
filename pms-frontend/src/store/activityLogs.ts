// src/store/activityLogs.ts

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axiosInstance from '@/utils/axiosInstance'
import type {
  ActivityLog,
  ActivityLogStatistics,
  ActivityLogSettings,
  ActivityLogFilters
} from '@/types/activityLogs'

export const useActivityLogsStore = defineStore('activityLogs', () => {
  // ==================== STATE ====================
  const logs = ref<ActivityLog[]>([])
  const statistics = ref<ActivityLogStatistics | null>(null)
  const settings = ref<ActivityLogSettings | null>(null)
  const loading = ref(false)
  const currentPage = ref(1)
  const totalPages = ref(1)
  const perPage = ref(50)
  const totalLogs = ref(0)
  
  const filters = ref<ActivityLogFilters>({
    search: '',
    action_type: 'all',
    start_date: '',
    end_date: '',
  })

  // ==================== COMPUTED ====================
  
  /**
   * Check if any filters are active
   */
  const hasActiveFilters = computed(() => {
    return (
      filters.value.search !== '' ||
      filters.value.action_type !== 'all' ||
      filters.value.start_date !== '' ||
      filters.value.end_date !== ''
    )
  })

  /**
   * Count of active filters
   */
  const activeFiltersCount = computed(() => {
    let count = 0
    if (filters.value.search) count++
    if (filters.value.action_type !== 'all') count++
    if (filters.value.start_date) count++
    if (filters.value.end_date) count++
    return count
  })

  /**
   * List of active filters with labels
   */
  const activeFiltersList = computed(() => {
    const list: Array<{ key: string; label: string; value: string }> = []
    
    if (filters.value.search) {
      list.push({
        key: 'search',
        label: 'Search',
        value: filters.value.search
      })
    }
    
    if (filters.value.action_type !== 'all') {
      list.push({
        key: 'action_type',
        label: 'Action Type',
        value: filters.value.action_type.charAt(0).toUpperCase() + filters.value.action_type.slice(1)
      })
    }
    
    if (filters.value.start_date) {
      list.push({
        key: 'start_date',
        label: 'Start Date',
        value: new Date(filters.value.start_date).toLocaleDateString()
      })
    }
    
    if (filters.value.end_date) {
      list.push({
        key: 'end_date',
        label: 'End Date',
        value: new Date(filters.value.end_date).toLocaleDateString()
      })
    }
    
    return list
  })

  // ==================== ACTIONS ====================

  /**
   * Fetch activity logs with pagination and filters
   */
  const fetchLogs = async (): Promise<void> => {
    loading.value = true
    try {
      const params = {
        page: currentPage.value,
        per_page: perPage.value,
        search: filters.value.search || undefined,
        action_type: filters.value.action_type !== 'all' ? filters.value.action_type : undefined,
        start_date: filters.value.start_date || undefined,
        end_date: filters.value.end_date || undefined,
      }
      
      const response = await axiosInstance.get('/api/activity-logs', { params })
      
      logs.value = response.data.data || []
      totalPages.value = response.data.last_page || 1
      currentPage.value = response.data.current_page || 1
      totalLogs.value = response.data.total || 0
      perPage.value = response.data.per_page || 50
    } catch (error) {
      console.error('Failed to fetch logs:', error)
      logs.value = []
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch activity log statistics
   */
  const fetchStatistics = async (): Promise<void> => {
    try {
      const params = {
        start_date: filters.value.start_date || undefined,
        end_date: filters.value.end_date || undefined,
      }
      
      const response = await axiosInstance.get('/api/activity-logs/statistics', { params })
      statistics.value = response.data
    } catch (error) {
      console.error('Failed to fetch statistics:', error)
      statistics.value = null
      throw error
    }
  }

  /**
   * Fetch activity log settings
   */
  const fetchSettings = async (): Promise<void> => {
    try {
      const response = await axiosInstance.get('/api/activity-logs/settings')
      settings.value = response.data
    } catch (error) {
      console.error('Failed to fetch settings:', error)
      settings.value = null
      throw error
    }
  }

  /**
   * Update activity log settings
   */
  const updateSettings = async (newSettings: Partial<ActivityLogSettings>): Promise<void> => {
    loading.value = true
    try {
      const response = await axiosInstance.put('/api/activity-logs/settings', newSettings)
      settings.value = response.data
    } catch (error) {
      console.error('Failed to update settings:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * Trigger manual cleanup of old logs
   */
  const triggerCleanup = async (): Promise<{ message: string; deleted_count: number }> => {
    loading.value = true
    try {
      const response = await axiosInstance.post('/api/activity-logs/settings/cleanup')
      
      // Refresh logs and statistics after cleanup
      await Promise.all([fetchLogs(), fetchStatistics()])
      
      return response.data
    } catch (error) {
      console.error('Failed to cleanup logs:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * Export activity logs data
   */
  const exportLogs = async (): Promise<any[]> => {
    loading.value = true
    try {
      const params = {
        search: filters.value.search || undefined,
        action_type: filters.value.action_type !== 'all' ? filters.value.action_type : undefined,
        start_date: filters.value.start_date || undefined,
        end_date: filters.value.end_date || undefined,
      }
      
      const response = await axiosInstance.get('/api/activity-logs/export/data', { params })
      return response.data.data || []
    } catch (error) {
      console.error('Failed to export logs:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * Set a specific filter value
   */
  const setFilter = (key: keyof ActivityLogFilters, value: string): void => {
    filters.value[key] = value
  }

  /**
   * Remove a specific filter
   */
  const removeFilter = (key: keyof ActivityLogFilters): void => {
    if (key === 'action_type') {
      filters.value[key] = 'all'
    } else {
      filters.value[key] = ''
    }
  }

  /**
   * Reset all filters to default
   */
  const resetFilters = (): void => {
    filters.value = {
      search: '',
      action_type: 'all',
      start_date: '',
      end_date: '',
    }
  }

  /**
   * Apply current filters and refresh data
   */
  const applyFilters = async (): Promise<void> => {
    currentPage.value = 1
    await Promise.all([fetchLogs(), fetchStatistics()])
  }

  /**
   * Navigate to a specific page
   */
  const goToPage = async (page: number): Promise<void> => {
    if (page < 1 || page > totalPages.value) return
    currentPage.value = page
    await fetchLogs()
  }

  /**
   * Navigate to next page
   */
  const nextPage = async (): Promise<void> => {
    if (currentPage.value < totalPages.value) {
      currentPage.value++
      await fetchLogs()
    }
  }

  /**
   * Navigate to previous page
   */
  const previousPage = async (): Promise<void> => {
    if (currentPage.value > 1) {
      currentPage.value--
      await fetchLogs()
    }
  }

  /**
   * Initialize store - fetch all initial data
   */
  const initialize = async (): Promise<void> => {
    loading.value = true
    try {
      await Promise.all([
        fetchLogs(),
        fetchStatistics(),
        fetchSettings()
      ])
    } catch (error) {
      console.error('Failed to initialize activity logs store:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * Clear all state
   */
  const clearState = (): void => {
    logs.value = []
    statistics.value = null
    settings.value = null
    currentPage.value = 1
    totalPages.value = 1
    totalLogs.value = 0
    perPage.value = 50
    filters.value = {
      search: '',
      action_type: 'all',
      start_date: '',
      end_date: '',
    }
    loading.value = false
  }

  return {
    // State
    logs,
    statistics,
    settings,
    loading,
    currentPage,
    totalPages,
    perPage,
    totalLogs,
    filters,
    
    // Computed
    hasActiveFilters,
    activeFiltersCount,
    activeFiltersList,
    
    // Actions
    fetchLogs,
    fetchStatistics,
    fetchSettings,
    updateSettings,
    triggerCleanup,
    exportLogs,
    setFilter,
    removeFilter,
    resetFilters,
    applyFilters,
    goToPage,
    nextPage,
    previousPage,
    initialize,
    clearState,
  }
})