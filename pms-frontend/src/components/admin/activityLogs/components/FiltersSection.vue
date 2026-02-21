<script setup lang="ts">
import { Search, Filter, X } from 'lucide-vue-next'
import type { ActivityLogFilters, ActionType, ActiveFilter } from '@/types/activityLogs'

interface Props {
  filters: ActivityLogFilters
  actionTypes: ActionType[]
  hasActiveFilters: boolean
  activeFiltersCount: number
  activeFiltersList: ActiveFilter[]
}

interface Emits {
  (e: 'apply-filters'): void
  (e: 'reset-filters'): void
  (e: 'remove-filter', key: string): void
}

defineProps<Props>()
const emit = defineEmits<Emits>()
</script>

<template>
  <div class="bg-white dark:bg-[#131c2e] rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4 transition-colors">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- Search -->
      <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Search User</label>
        <div class="relative">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Email or name..."
            class="text-sm w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-[#131c2e] text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 transition-colors"
          />
        </div>
      </div>

      <!-- Action Type -->
      <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Action Type</label>
        <select
          v-model="filters.action_type"
          class="text-sm w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-[#131c2e] text-gray-900 dark:text-white transition-colors"
        >
          <option v-for="type in actionTypes" :key="type.value" :value="type.value">
            {{ type.label }}
          </option>
        </select>
      </div>

      <!-- Start Date -->
      <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
        <input
          v-model="filters.start_date"
          type="date"
          class="text-sm w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-[#131c2e] text-gray-900 dark:text-white transition-colors"
        />
      </div>

      <!-- End Date -->
      <div>
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
        <input
          v-model="filters.end_date"
          type="date"
          class="text-sm w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 bg-white dark:bg-[#131c2e] text-gray-900 dark:text-white transition-colors"
        />
      </div>
    </div>

    <!-- Active Filters Display -->
    <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
      <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Active Filters:</span>
      <div class="text-sm flex flex-wrap gap-2">
        <div
          v-for="filter in activeFiltersList"
          :key="filter.key"
          class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-sm transition-colors"
        >
          <span class="font-medium">{{ filter.label }}:</span>
          <span>{{ filter.value }}</span>
          <button
            @click="emit('remove-filter', filter.key)"
            class="ml-1 hover:bg-blue-100 dark:hover:bg-blue-800/50 rounded-full p-0.5 transition-colors"
            title="Remove filter"
          >
            <X :size="14" />
          </button>
        </div>
      </div>
      <button
        @click="emit('reset-filters')"
        class="ml-auto text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium transition-colors"
      >
        Clear All Filters
      </button>
    </div>

    <!-- Filter Actions -->
    <div class="flex items-center justify-between">
      <div v-if="hasActiveFilters" class="text-sm text-gray-600 dark:text-gray-400">
        {{ activeFiltersCount }} filter{{ activeFiltersCount !== 1 ? 's' : '' }} active
      </div>
      <div class="flex items-center gap-2 ml-auto">
        <button
          v-if="hasActiveFilters"
          @click="emit('reset-filters')"
          class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors"
        >
          Clear Filters
        </button>
        <button
          @click="emit('apply-filters')"
          class="text-sm px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-600 flex items-center gap-2 transition-colors"
        >
          <Filter :size="16" />
          Apply Filters
        </button>
      </div>
    </div>
  </div>
</template>