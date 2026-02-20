<template>
  <div class="space-y-4">
    <!-- Toolbar -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ filtered.length }} task{{ filtered.length !== 1 ? 's' : '' }}
      </p>
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-for="s in statusFilters"
          :key="s.value"
          @click="activeStatus = s.value"
          :class="[
            'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
            activeStatus === s.value
              ? 'bg-blue-600 text-white'
              : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600',
          ]"
        >
          {{ s.label }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 animate-pulse">
        <div class="flex items-center gap-4">
          <div class="w-8 h-8 rounded-lg bg-gray-200 dark:bg-gray-700" />
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/3" />
          </div>
          <div class="h-6 w-20 bg-gray-200 dark:bg-gray-700 rounded-full" />
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="filtered.length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 py-16 text-center">
      <ClipboardList :size="40" class="text-gray-300 dark:text-gray-600 mx-auto mb-3" />
      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No tasks found</p>
    </div>

    <!-- Task List -->
    <div v-else class="space-y-3">
      <div
        v-for="task in filtered"
        :key="task.id"
        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:shadow-sm hover:border-blue-200 dark:hover:border-blue-700 transition-all"
      >
        <div class="flex items-start gap-4">
          <!-- Status Icon -->
          <div :class="statusIconBg(task.status)" class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0">
            <component :is="statusIcon(task.status)" :size="15" :class="statusIconColor(task.status)" />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ task.title }}</p>
                <p v-if="task.project" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                  <FolderOpen :size="11" class="inline mr-1" />{{ task.project.title }}
                </p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span v-if="task.priority" :class="priorityBadge(task.priority)" class="text-xs px-2 py-0.5 rounded-full font-medium capitalize">
                  {{ task.priority }}
                </span>
                <span :class="statusBadge(task.status)" class="text-xs px-2.5 py-0.5 rounded-full font-medium capitalize">
                  {{ task.status?.replace(/_/g, ' ') }}
                </span>
              </div>
            </div>

            <!-- Progress & Due Date -->
            <div class="mt-3 flex items-center gap-4">
              <div class="flex-1">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs text-gray-500 dark:text-gray-400">Progress</span>
                  <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ task.progress ?? 0 }}%</span>
                </div>
                <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                  <div
                    :style="{ width: `${task.progress ?? 0}%` }"
                    :class="progressColor(task.progress)"
                    class="h-full rounded-full transition-all duration-500"
                  />
                </div>
              </div>
              <div v-if="task.due_date" class="flex items-center gap-1 text-xs shrink-0" :class="dueDateColor(task.due_date, task.status)">
                <CalendarDays :size="12" />
                {{ formatDate(task.due_date) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ClipboardList, FolderOpen, CalendarDays, CheckCircle2, Clock, CircleDashed, AlertCircle } from 'lucide-vue-next'
import type { ProfileTask } from '@/store/profile'

const props = defineProps<{
  tasks: ProfileTask[]
  loading?: boolean
}>()

const activeStatus = ref('all')

const statusFilters = [
  { label: 'All', value: 'all' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Completed', value: 'completed' },
  { label: 'Pending', value: 'pending' },
]

const filtered = computed(() => {
  if (activeStatus.value === 'all') return props.tasks
  return props.tasks.filter(t => t.status?.toLowerCase() === activeStatus.value)
})

// ── Helpers ──────────────────────────────────────────────────────────────────

function statusIcon(status: string) {
  const s = status?.toLowerCase()
  if (s === 'completed') return CheckCircle2
  if (['in_progress', 'ongoing'].includes(s)) return Clock
  if (s === 'overdue') return AlertCircle
  return CircleDashed
}

function statusIconBg(status: string) {
  const s = status?.toLowerCase()
  if (s === 'completed') return 'bg-green-100 dark:bg-green-900/30'
  if (['in_progress', 'ongoing'].includes(s)) return 'bg-blue-100 dark:bg-blue-900/30'
  if (s === 'overdue') return 'bg-red-100 dark:bg-red-900/30'
  return 'bg-gray-100 dark:bg-gray-700'
}

function statusIconColor(status: string) {
  const s = status?.toLowerCase()
  if (s === 'completed') return 'text-green-600 dark:text-green-400'
  if (['in_progress', 'ongoing'].includes(s)) return 'text-blue-600 dark:text-blue-400'
  if (s === 'overdue') return 'text-red-600 dark:text-red-400'
  return 'text-gray-500 dark:text-gray-400'
}

function statusBadge(status: string) {
  const s = status?.toLowerCase()
  if (s === 'completed') return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  if (['in_progress', 'ongoing'].includes(s)) return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
  if (s === 'overdue') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
}

function priorityBadge(priority: string | null) {
  const p = priority?.toLowerCase()
  if (p === 'high') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  if (p === 'medium') return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
  return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
}

function progressColor(progress: number) {
  if (progress >= 100) return 'bg-green-500'
  if (progress >= 60) return 'bg-blue-500'
  if (progress >= 30) return 'bg-yellow-500'
  return 'bg-red-400'
}

function formatDate(date: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function dueDateColor(dueDate: string, status: string) {
  if (status?.toLowerCase() === 'completed') return 'text-gray-400 dark:text-gray-500'
  const isPast = new Date(dueDate) < new Date()
  return isPast ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'
}
</script>