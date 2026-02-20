import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axiosInstance from '@/utils/axiosInstance'
import type { GroupedSettings } from '@/types/systemSettings'

export const useSystemSettingsStore = defineStore('systemSettings', () => {
  // State
  const settings = ref<GroupedSettings>({})
  const publicSettings = ref<Record<string, any>>({})
  const loading = ref(false)

  // Computed
  const sessionTimeoutMinutes = computed(() => {
    return publicSettings.value.session_timeout_minutes || 30
  })

  const sessionTimeoutEnabled = computed(() => {
    return publicSettings.value.session_timeout_enabled !== false
  })

  const securitySettings = computed(() => {
    return settings.value.security || []
  })

  // Actions
  const fetchSettings = async (group?: string) => {
    loading.value = true
    try {
      const params = group ? { group } : {}
      const response = await axiosInstance.get('/api/v1/admin/settings', { params })
      
      if (group) {
        settings.value[group] = response.data.settings
      } else {
        settings.value = response.data
      }
    } catch (error) {
      console.error('Failed to fetch settings:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const fetchPublicSettings = async () => {
    try {
      const response = await axiosInstance.get('/api/v1/settings/public')
      publicSettings.value = response.data
    } catch (error) {
      console.error('Failed to fetch public settings:', error)
      throw error
    }
  }

  const getSetting = async (key: string) => {
    try {
      const response = await axiosInstance.get(`/api/v1/admin/settings/${key}`)
      return response.data
    } catch (error) {
      console.error(`Failed to fetch setting ${key}:`, error)
      throw error
    }
  }

  const updateSettings = async (settingsToUpdate: Array<{ key: string; value: any }>) => {
    loading.value = true
    try {
      const response = await axiosInstance.put('/api/v1/admin/settings', {
        settings: settingsToUpdate
      })
      
      // Refresh settings after update
      await fetchSettings()
      await fetchPublicSettings()
      
      return response.data
    } catch (error) {
      console.error('Failed to update settings:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const updateSetting = async (key: string, value: any) => {
    loading.value = true
    try {
      const response = await axiosInstance.put(`/api/v1/admin/settings/${key}`, { value })
      
      // Refresh settings after update
      await fetchSettings()
      await fetchPublicSettings()
      
      return response.data
    } catch (error) {
      console.error(`Failed to update setting ${key}:`, error)
      throw error
    } finally {
      loading.value = false
    }
  }

  const initialize = async () => {
    await Promise.all([
      fetchSettings(),
      fetchPublicSettings()
    ])
  }

  return {
    // State
    settings,
    publicSettings,
    loading,
    
    // Computed
    sessionTimeoutMinutes,
    sessionTimeoutEnabled,
    securitySettings,
    
    // Actions
    fetchSettings,
    fetchPublicSettings,
    getSetting,
    updateSettings,
    updateSetting,
    initialize,
  }
})