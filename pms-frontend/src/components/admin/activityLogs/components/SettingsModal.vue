<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useActivityLogsStore } from '@/store/activityLogs'
import { toast } from 'vue3-toastify'
import { X, Save } from 'lucide-vue-next'
import type { ActivityLogSettings } from '@/types/activityLogs'

interface Props {
  settings: ActivityLogSettings | null
  permissionKey: string
}

interface Emits {
  (e: 'close'): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const activityLogsStore = useActivityLogsStore()

// Form state
const form = ref<Pick<ActivityLogSettings, 'retention_months' | 'max_id' | 'auto_cleanup_enabled'>>({
  retention_months: 3,
  max_id: 1000000,
  auto_cleanup_enabled: true,
})

const loading = computed(() => activityLogsStore.loading)

// Initialize form with settings data
watch(() => props.settings, (settings) => {
  if (settings) {
    form.value = {
      retention_months: settings.retention_months,
      max_id: settings.max_id,
      auto_cleanup_enabled: settings.auto_cleanup_enabled,
    }
  }
}, { immediate: true })

const closeModal = () => {
  emit('close')
}

const handleSubmit = async () => {
  try {
    await activityLogsStore.updateSettings(form.value)
    toast.success('Settings updated successfully')
    emit('saved')
  } catch (error) {
    toast.error('Failed to update settings')
  }
}
</script>

<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70 flex items-center justify-center z-50 p-4 !mt-0">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-hidden border border-gray-200 dark:border-gray-700">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
        <div>
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            Activity Log Settings
          </h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Configure log retention and cleanup settings
          </p>
        </div>
        <button
          @click="closeModal"
          class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
          :disabled="loading"
        >
          <X :size="20" class="text-gray-500 dark:text-gray-400" />
        </button>
      </div>
      
      <!-- Loading Overlay -->
      <div v-if="loading" class="absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 dark:bg-opacity-75 flex items-center justify-center z-10 rounded-lg">
        <div class="flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">Saving settings...</span>
        </div>
      </div>
      
      <!-- Form Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] modal-scroll">
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label for="retention-months" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Retention Period (Months) <span class="text-red-500">*</span>
            </label>
            <input
              id="retention-months"
              v-model.number="form.retention_months"
              type="number"
              min="1"
              max="60"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 focus:border-gray-500 dark:focus:border-gray-400 focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-400/50 outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              required
            />
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Logs older than this will be automatically deleted
            </p>
          </div>

          <!-- Commented out sections kept as in original -->
          <!-- <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Maximum ID (ID Reuse Limit)
            </label>
            <input
              v-model.number="form.max_id"
              type="number"
              min="100000"
              max="10000000"
              step="100000"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 focus:border-gray-500 dark:focus:border-gray-400 focus:ring-2 focus:ring-gray-200 dark:focus:ring-gray-400/50 outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
            />
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              IDs will be reused after reaching this number
            </p>
          </div>

          <div class="flex items-center">
            <input
              v-model="form.auto_cleanup_enabled"
              type="checkbox"
              class="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500"
            />
            <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">
              Enable automatic cleanup
            </label>
          </div>

          <div v-if="canDelete(permissionKey)" class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              @click="triggerCleanup"
              class="w-full px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 flex items-center justify-center gap-2 transition-colors"
            >
              <Trash2 :size="16" />
              Clean Up Old Logs Now
            </button>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 text-center">
              Last cleanup: {{ settings?.last_cleanup_at ? formatDate(settings.last_cleanup_at) : 'Never' }}
            </p>
          </div> -->
        </form>
      </div>
      
      <!-- Footer -->
      <div class="flex justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
        <button
          @click="closeModal"
          class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm font-medium disabled:opacity-50"
          :disabled="loading"
        >
          Cancel
        </button>
        <button
          @click="handleSubmit"
          class="px-4 py-2.5 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors flex items-center gap-2 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="loading"
        >
          <svg v-if="loading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <Save v-else :size="16" />
          <span>Save Settings</span>
        </button>
      </div>
      
    </div>
  </div>
</template>