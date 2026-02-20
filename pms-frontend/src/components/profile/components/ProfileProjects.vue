<template>
  <div class="space-y-4">
    <!-- Header / Filter -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ filtered.length }} project{{ filtered.length !== 1 ? 's' : '' }}
      </p>
      <div class="flex items-center gap-2">
        <button
          v-for="s in statuses"
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
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="i in 4" :key="i" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 animate-pulse">
        <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-3" />
        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="filtered.length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 py-16 text-center">
      <FolderOpen :size="40" class="text-gray-300 dark:text-gray-600 mx-auto mb-3" />
      <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No projects found</p>
      <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Try adjusting the filter above</p>
    </div>

    <!-- Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="project in filtered"
        :key="project.id"
        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 transition-all group"
      >
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
              {{ project.title }}
            </h4>
            <p v-if="project.role" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Role: <span class="text-gray-700 dark:text-gray-300 font-medium capitalize">{{ project.role }}</span>
            </p>
          </div>
          <span :class="statusBadge(project.status)" class="text-xs px-2.5 py-1 rounded-full font-medium capitalize shrink-0">
            {{ project.status?.replace(/_/g, ' ') }}
          </span>
        </div>

        <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mt-3">
          <span v-if="project.start_date" class="flex items-center gap-1">
            <CalendarDays :size="12" />
            {{ formatDate(project.start_date) }}
          </span>
          <span v-if="project.end_date" class="flex items-center gap-1">
            <span class="text-gray-300 dark:text-gray-600">→</span>
            {{ formatDate(project.end_date) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { FolderOpen, CalendarDays } from 'lucide-vue-next'
import type { ProfileProject } from '@/store/profile'

const props = defineProps<{
  projects: ProfileProject[]
  loading?: boolean
}>()

const activeStatus = ref('all')

const statuses = [
  { label: 'All', value: 'all' },
  { label: 'Active', value: 'active' },
  { label: 'Completed', value: 'completed' },
  { label: 'On Hold', value: 'on_hold' },
]

const filtered = computed(() => {
  if (activeStatus.value === 'all') return props.projects
  return props.projects.filter(p => p.status?.toLowerCase() === activeStatus.value)
})

function statusBadge(status: string) {
  const s = status?.toLowerCase()
  if (['active', 'ongoing', 'in_progress'].includes(s))
    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  if (s === 'completed')
    return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
  if (s === 'on_hold')
    return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
}

function formatDate(date: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>