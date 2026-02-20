<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70 flex items-center justify-center z-50 p-4 !mt-0">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden border border-gray-200 dark:border-gray-700">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
        <div>
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            Activity Details
          </h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Complete information about this activity
          </p>
        </div>
        <button
          @click="closeModal"
          class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
        >
          <X :size="20" class="text-gray-500 dark:text-gray-400" />
        </button>
      </div>
      
      <!-- Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] modal-scroll">
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Date/Time</div>
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatDate(log.created_at) }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Action Type</div>
              <span :class="getActionBadgeColor(log.action_type)" class="text-sm inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors">
                {{ log.action_type }}
              </span>
            </div>
          </div>

          <div>
            <div class="text-xs text-gray-600 dark:text-gray-400">User</div>
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
              {{ log.employee?.first_name }} {{ log.employee?.last_name }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ log.email }}</div>
          </div>

          <div>
            <div class="text-xs text-gray-600 dark:text-gray-400">Description</div>
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ log.description }}</div>
          </div>

          <div v-if="log.model_type">
            <div class="text-xs text-gray-600 dark:text-gray-400">Model</div>
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
              {{ log.model_type.split('\\').pop() }} (ID: {{ log.model_id }})
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-xs text-gray-600 dark:text-gray-400">IP Address</div>
              <div class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ log.ip_address }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-600 dark:text-gray-400">Device Type</div>
              <div class="text-sm flex items-center gap-2 text-gray-900 dark:text-gray-100">
                <component :is="getDeviceIcon(log.device_type)" :size="16" />
                {{ log.device_type || 'Unknown' }}
              </div>
            </div>
          </div>

          <div v-if="log.browser">
            <div class="text-xs text-gray-600 dark:text-gray-400">Browser</div>
            <div class="text-sm text-gray-900 dark:text-gray-100">{{ log.browser }}{{ log.browser_version ? ' ' + log.browser_version : '' }}</div>
          </div>

          <div v-if="log.platform">
            <div class="text-xs text-gray-600 dark:text-gray-400">Platform</div>
            <div class="text-sm text-gray-900 dark:text-gray-100">{{ log.platform }}{{ log.platform_version ? ' ' + log.platform_version : '' }}</div>
          </div>

          <div>
            <div class="text-xs text-gray-600 dark:text-gray-400">User Agent</div>
            <div class="text-sm text-gray-500 dark:text-gray-100 break-all">{{ log.user_agent }}</div>
          </div>

          <!-- Changes (Old vs New Values) -->
          <div v-if="hasChanges" class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Changes</div>
            
            <div class="space-y-3">
              <div v-for="field in changedFields" :key="field" class="border-l-2 border-blue-500 pl-3">
                <div class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase mb-1">
                  {{ String(field).replace(/_/g, ' ') }}
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                  <!-- Old Value -->
                  <div v-if="oldValues && oldValues[field] !== undefined">
                    <div class="text-xs text-gray-500 dark:text-gray-500 mb-1">Old Value</div>
                    <div class="text-sm bg-red-50 dark:bg-red-900/20 text-red-900 dark:text-red-300 px-2 py-1 rounded border border-red-200 dark:border-red-800 break-words">
                      {{ formatValue(oldValues[field]) }}
                    </div>
                  </div>
                  
                  <!-- New Value -->
                  <div :class="oldValues && oldValues[field] !== undefined ? '' : 'col-span-2'">
                    <div class="text-xs text-gray-500 dark:text-gray-500 mb-1">
                      {{ oldValues && oldValues[field] !== undefined ? 'New Value' : 'Value' }}
                    </div>
                    <div class="text-sm bg-green-50 dark:bg-green-900/20 text-green-900 dark:text-green-300 px-2 py-1 rounded border border-green-200 dark:border-green-800 break-words">
                      {{ formatValue(newValues?.[field]) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>


        </div>
      </div>
      
      <!-- Footer -->
      <div class="flex justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
        <button
          @click="closeModal"
          class="px-4 py-2.5 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-800 transition-colors text-sm font-medium"
        >
          Close
        </button>
      </div>
      
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { X, Monitor, Smartphone, Tablet } from 'lucide-vue-next'
import type { ActivityLog } from '@/types/activityLogs'

interface Props {
  log: ActivityLog
}

interface Emits {
  (e: 'close'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const closeModal = () => {
  emit('close')
}

const getActionBadgeColor = (actionType: string) => {
  const colors: Record<string, string> = {
    login: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    logout: 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300',
    create: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    update: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    delete: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
  }
  return colors[actionType] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'
}

const formatValue = (value: any): string => {
  if (value === null || value === undefined) return 'N/A'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'object') return JSON.stringify(value, null, 2)
  return String(value)
}

const getDeviceIcon = (deviceType?: string) => {
  if (!deviceType) return Monitor
  const type = deviceType.toLowerCase()
  if (type.includes('mobile')) return Smartphone
  if (type.includes('tablet')) return Tablet
  return Monitor
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Parse JSON strings into objects
const parseChanges = (changeString: string | object | null | undefined) => {
  if (!changeString) return null
  
  // If already an object, return it
  if (typeof changeString === 'object') return changeString
  
  // If it's a string, try to parse it
  try {
    return JSON.parse(changeString)
  } catch (e) {
    console.error('Failed to parse changes:', e)
    return null
  }
}

const hasChanges = computed(() => {
  return props.log.changes && 
    (props.log.changes.old || props.log.changes.new)
})

const oldValues = computed(() => {
  if (!props.log.changes?.old) return null
  return parseChanges(props.log.changes.old)
})

const newValues = computed(() => {
  if (!props.log.changes?.new) return null
  return parseChanges(props.log.changes.new)
})

const changedFields = computed(() => {
  if (!hasChanges.value) return []
  
  const fields = new Set([
    ...Object.keys(oldValues.value || {}),
    ...Object.keys(newValues.value || {})
  ])
  
  return Array.from(fields)
})
</script>