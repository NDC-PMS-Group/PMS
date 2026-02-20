<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ activity.length }} event{{ activity.length !== 1 ? 's' : '' }}
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 6" :key="i" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 animate-pulse">
        <div class="flex items-center gap-4">
          <div class="w-9 h-9 rounded-full bg-gray-200 dark:bg-gray-700 shrink-0" />
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-2/5" />
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/5" />
          </div>
          <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded" />
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="activity.length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 py-16 text-center">
      <Activity :size="40" class="text-gray-300 dark:text-gray-600 mx-auto mb-3" />
      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No activity recorded</p>
    </div>

    <!-- Timeline -->
    <div v-else class="relative">
      <!-- Vertical line -->
      <div class="absolute left-[17px] top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700 ml-0.5" />

      <div class="space-y-1">
        <div
          v-for="log in activity"
          :key="log.id"
          class="relative flex items-start gap-4 pl-1 group"
        >
          <!-- Dot -->
          <div
            :class="actionDotClass(log.action)"
            class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 z-10 border-2 border-white dark:border-gray-900 shadow-sm"
          >
            <component :is="actionIcon(log.action)" :size="14" />
          </div>

          <!-- Card -->
          <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-3 hover:shadow-sm transition-shadow">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 capitalize">
                  {{ formatAction(log.action) }}
                </p>
                <p v-if="log.description" class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">
                  {{ log.description }}
                </p>
                <p v-if="log.model_type" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                  <span class="font-medium">{{ extractModel(log.model_type) }}</span>
                  <span v-if="log.model_id"> #{{ log.model_id }}</span>
                </p>
              </div>
              <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0 whitespace-nowrap">
                {{ timeAgo(log.created_at) }}
              </span>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
              {{ formatFullDate(log.created_at) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  Activity, Plus, Pencil, Trash2, LogIn, LogOut,
  Upload, CheckCircle2, RefreshCw, Eye,
} from 'lucide-vue-next'
import type { ProfileActivity } from '@/store/profile'

defineProps<{
  activity: ProfileActivity[]
  loading?: boolean
}>()

function actionIcon(action: string) {
  const a = action?.toLowerCase()
  if (a?.includes('create') || a?.includes('add')) return Plus
  if (a?.includes('update') || a?.includes('edit')) return Pencil
  if (a?.includes('delete') || a?.includes('remove')) return Trash2
  if (a?.includes('login') || a?.includes('sign_in')) return LogIn
  if (a?.includes('logout') || a?.includes('sign_out')) return LogOut
  if (a?.includes('upload')) return Upload
  if (a?.includes('complete') || a?.includes('approve')) return CheckCircle2
  if (a?.includes('view')) return Eye
  return RefreshCw
}

function actionDotClass(action: string) {
  const a = action?.toLowerCase()
  if (a?.includes('create') || a?.includes('add'))
    return 'bg-green-100 text-green-600 dark:bg-green-900/40 dark:text-green-400'
  if (a?.includes('update') || a?.includes('edit'))
    return 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400'
  if (a?.includes('delete') || a?.includes('remove'))
    return 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400'
  if (a?.includes('login'))
    return 'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400'
  return 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
}

function formatAction(action: string) {
  return action?.replace(/_/g, ' ') ?? '—'
}

function extractModel(modelType: string | null) {
  if (!modelType) return ''
  return modelType.split('\\').pop() ?? modelType
}

function formatFullDate(dateStr: string) {
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function timeAgo(dateStr: string) {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  if (days < 7) return `${days}d ago`
  const weeks = Math.floor(days / 7)
  return `${weeks}w ago`
}
</script>