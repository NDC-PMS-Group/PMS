<script setup lang="ts">
import { 
  Eye,
  LogIn,
  LogOut,
  Plus,
  Edit,
  Trash2,
  Monitor,
  Smartphone,
  Tablet,
  ChevronLeft,
  ChevronRight, 
} from 'lucide-vue-next'
import type { ActivityLog } from '@/types/activityLogs'

interface Props {
  logs: ActivityLog[]
  loading: boolean
  currentPage: number
  totalPages: number
}

interface Emits {
  (e: 'view-details', log: ActivityLog): void
  (e: 'previous-page'): void
  (e: 'next-page'): void
}

defineProps<Props>()
const emit = defineEmits<Emits>()

// Get action badge color
const getActionBadgeColor = (actionType: string) => {
  const colors: Record<string, string> = {
    login: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    login_failed: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    logout: 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300',
    create: 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    update: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    delete: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    register: 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
  }
  return colors[actionType] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'
}

// Get action icon
const getActionIcon = (actionType: string) => {
  const icons: Record<string, any> = {
    login: LogIn,
    logout: LogOut,
    create: Plus,
    update: Edit,
    delete: Trash2,
  }
  return icons[actionType] || Eye
}

// Get device icon
const getDeviceIcon = (deviceType?: string) => {
  if (!deviceType) return Monitor
  const type = deviceType.toLowerCase()
  if (type.includes('mobile')) return Smartphone
  if (type.includes('tablet')) return Tablet
  return Monitor
}

// Format date
const formatDate = (date: string) => {
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden transition-colors">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Date/Time</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">User</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Action</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Description</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Device</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">IP Address</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
              <div class="flex flex-col items-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 dark:border-blue-400 mb-2"></div>
                Loading logs...
              </div>
            </td>
          </tr>
          <tr v-else-if="logs.length === 0">
            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
              No activity logs found
            </td>
          </tr>
          <tr v-else v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
              {{ formatDate(log.created_at) }}
            </td>
            <td class="px-4 py-3 text-sm">
              <div class="font-medium text-gray-900 dark:text-gray-100">
                {{ log.employee?.first_name }} {{ log.employee?.last_name }}
              </div>
              <div class="text-gray-500 dark:text-gray-400">{{ log.email }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="getActionBadgeColor(log.action_type)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors capitalize">
                <component :is="getActionIcon(log.action_type)" :size="14" />
                {{ log.action_type?.replace('_', ' ') || 'Unknown' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
              {{ log.description }}
            </td>
            <td class="px-4 py-3 text-sm">
              <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                <component :is="getDeviceIcon(log.device_type)" :size="16" />
                <span>{{ log.device_type || 'Unknown' }}</span>
              </div>
              <div v-if="log.browser" class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                {{ log.browser }}{{ log.browser_version ? ' ' + log.browser_version : '' }}
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 font-mono">
              {{ log.ip_address }}
            </td>
            <td class="px-4 py-3 text-center">
              <button
                @click="emit('view-details', log)"
                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                title="View Details"
              >
                <Eye :size="18" />
              </button>
            </td>
          </tr>
        </tbody>


      </table>
    </div>

    <!-- Pagination -->
    <div
      v-if="!loading && logs.length > 0"
      class="px-6 py-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
    >
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Page
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ currentPage }}</span>
        of
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ totalPages }}</span>
      </p>

      <div class="flex items-center space-x-1">
        <button
          @click="emit('previous-page')"
          :disabled="currentPage <= 1"
          class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronLeft :size="18" />
        </button>

        <span class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300">
          {{ currentPage }} / {{ totalPages }}
        </span>

        <button
          @click="emit('next-page')"
          :disabled="currentPage >= totalPages"
          class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronRight :size="18" />
        </button>
      </div>
    </div>
  </div>
</template>