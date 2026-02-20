<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePermission } from '@/composables/usePermission'
import { useActivityLogsStore } from '@/store/activityLogs'
import { storeToRefs } from 'pinia'
import { toast } from 'vue3-toastify'
import { Settings, Download } from 'lucide-vue-next'

// Components
import StatisticsCards from './components/StatisticsCards.vue'
import FiltersSection from './components/FiltersSection.vue'
import LogsTable from './components/LogsTable.vue'
import SettingsModal from './components/SettingsModal.vue'
import DetailsModal from './components/DetailsModal.vue'

// Store
const activityLogsStore = useActivityLogsStore()
const { 
  logs, 
  statistics, 
  settings, 
  loading, 
  currentPage, 
  totalPages,
  filters,
  hasActiveFilters,
  activeFiltersCount,
  activeFiltersList
} = storeToRefs(activityLogsStore)

// Permission checking
const { canUpdate } = usePermission()
const permissionKey = 'activity_logs'

// Modals
const showSettingsModal = ref(false)
const showDetailsModal = ref(false)
const selectedLog = ref<any>(null)

// Action types for filters
const actionTypes = [
  { value: 'all', label: 'All Actions' },
  { value: 'login', label: 'Login' },
  { value: 'logout', label: 'Logout' },
  { value: 'create', label: 'Create' },
  { value: 'update', label: 'Update' },
  { value: 'delete', label: 'Delete' },
]

// Apply filters
const applyFilters = async () => {
  try {
    await activityLogsStore.applyFilters()
  } catch (error) {
    toast.error('Failed to apply filters')
  }
}

// Reset filters
const resetFilters = async () => {
  activityLogsStore.resetFilters()
  await applyFilters()
}

// Remove single filter
const removeFilter = async (key: string) => {
  activityLogsStore.removeFilter(key as any)
  await applyFilters()
}

// View log details
const viewDetails = (log: any) => {
  selectedLog.value = log
  showDetailsModal.value = true
}

// Open settings modal
const openSettingsModal = () => {
  showSettingsModal.value = true
}

// Close settings modal
const closeSettingsModal = () => {
  showSettingsModal.value = false
}

// Handle settings saved
const handleSettingsSaved = () => {
  closeSettingsModal()
}

// Close details modal
const closeDetailsModal = () => {
  showDetailsModal.value = false
  selectedLog.value = null
}

// Export logs
const exportLogs = async () => {
  try {
    const data = await activityLogsStore.exportLogs()
    
    if (data.length === 0) {
      toast.error('No data to export')
      return
    }
    
    const headers = Object.keys(data[0])
    const csv = [
      headers.join(','),
      ...data.map((row: any) => headers.map(h => `"${row[h] ?? ''}"`).join(','))
    ].join('\n')
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `activity-logs-${new Date().toISOString().split('T')[0]}.csv`
    a.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    toast.error('Failed to export logs')
  }
}

// Initialize
onMounted(async () => {
  try {
    await activityLogsStore.initialize()
  } catch (error) {
    console.error('Failed to initialize:', error)
  }
})
</script>

<template>
  <div class="p-0 md:p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between"">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Activity Logs</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Track user activities and system events</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="exportLogs"
          class="text-sm px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 transition-colors"
        >
          <Download :size="16" />
          Export
        </button>
        <button
          v-if="canUpdate(permissionKey)"
          @click="openSettingsModal"
          class="text-sm px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-600 flex items-center gap-2 transition-colors"
        >
          <Settings :size="16" />
          Settings
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <StatisticsCards :statistics="statistics" />

    <!-- Filters Section -->
    <FiltersSection
      :filters="filters"
      :action-types="actionTypes"
      :has-active-filters="hasActiveFilters"
      :active-filters-count="activeFiltersCount"
      :active-filters-list="activeFiltersList"
      @apply-filters="applyFilters"
      @reset-filters="resetFilters"
      @remove-filter="removeFilter"
    />

    <!-- Logs Table -->
    <LogsTable
      :logs="logs"
      :loading="loading"
      :current-page="currentPage"
      :total-pages="totalPages"
      @view-details="viewDetails"
      @previous-page="activityLogsStore.previousPage()"
      @next-page="activityLogsStore.nextPage()"
    />

    <!-- Modals -->
    <SettingsModal
      v-if="showSettingsModal"
      :settings="settings"
      :permission-key="permissionKey"
      @close="closeSettingsModal"
      @saved="handleSettingsSaved"
    />

    <DetailsModal
      v-if="showDetailsModal && selectedLog"
      :log="selectedLog"
      @close="closeDetailsModal"
    />
  </div>
</template>