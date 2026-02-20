<script setup lang="ts">
import type { ActivityLogStatistics } from '@/types/activityLogs'

interface Props {
  statistics: ActivityLogStatistics | null
}

defineProps<Props>()

const stats = [
  {
    key: 'total_activities',
    label: 'Total Activities',
    getValue: (s: ActivityLogStatistics) => s.total_activities,
    valueClass: 'text-slate-700 dark:text-slate-300',
    iconClass:  'text-slate-400 dark:text-slate-500',
    barClass:   'bg-slate-400 dark:bg-slate-500',
    iconPath: 'M3 12h4l3-9 4 18 3-9h4',
  },
  {
    key: 'total_logins',
    label: 'User Logins',
    getValue: (s: ActivityLogStatistics) => s.total_logins,
    valueClass: 'text-emerald-600 dark:text-emerald-400',
    iconClass:  'text-emerald-500 dark:text-emerald-500',
    barClass:   'bg-emerald-500 dark:bg-emerald-400',
    iconPath: 'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3',
  },
  {
    key: 'creates_updates',
    label: 'Creates + Updates',
    getValue: (s: ActivityLogStatistics) => s.total_creates + s.total_updates,
    valueClass: 'text-sky-600 dark:text-sky-400',
    iconClass:  'text-sky-500 dark:text-sky-500',
    barClass:   'bg-sky-500 dark:bg-sky-400',
    iconPath: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z',
  },
  {
    key: 'unique_users',
    label: 'Unique Users',
    getValue: (s: ActivityLogStatistics) => s.unique_users,
    valueClass: 'text-violet-600 dark:text-violet-400',
    iconClass:  'text-violet-500 dark:text-violet-500',
    barClass:   'bg-violet-500 dark:bg-violet-400',
    iconPath: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
  },
]
</script>

<template>
  <div v-if="statistics" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
    <div
      v-for="stat in stats"
      :key="stat.key"
      class="group relative flex flex-col gap-3 rounded-xl border border-slate-200 dark:border-white/[0.06] bg-white dark:bg-[#131c2e] px-4 pt-4 pb-3 overflow-hidden transition-all duration-200 hover:-translate-y-px hover:shadow-md hover:border-slate-300 dark:hover:border-white/[0.11] dark:hover:bg-[#16203a] cursor-default"
    >
      <!-- Header -->
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-medium tracking-widest uppercase text-slate-400 dark:text-slate-500">
          {{ stat.label }}
        </span>
        <span :class="['w-4 h-4 opacity-60 group-hover:opacity-100 transition-opacity', stat.iconClass]">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full">
            <path :d="stat.iconPath" />
          </svg>
        </span>
      </div>

      <!-- Value -->
      <div :class="['font-mono text-[28px] font-medium leading-none tracking-tight', stat.valueClass]">
        {{ stat.getValue(statistics)?.toLocaleString() ?? '—' }}
      </div>

      <!-- Bar -->
      <div class="h-0.5 rounded-full bg-slate-100 dark:bg-white/5 overflow-hidden">
        <div :class="['h-full w-[55%] rounded-full opacity-70', stat.barClass]" />
      </div>
    </div>
  </div>
</template>