<template>
  <div class="flex items-center gap-2 flex-wrap">
    <!-- Status filter pills -->
    <button
      class="filter-pill"
      :class="{ active: !activeStatusId }"
      @click="emit('update:statusId', null)"
    >
      All
      <span class="pill-count">{{ total }}</span>
    </button>

    <button
      v-for="status in statuses"
      :key="status.id"
      class="filter-pill"
      :class="{ active: activeStatusId === status.id }"
      @click="emit('update:statusId', activeStatusId === status.id ? null : status.id)"
    >
      <!-- Status color dot -->
      <span
        class="inline-block w-2 h-2 rounded-full flex-shrink-0"
        :style="{ backgroundColor: status.color_code }"
      />
      {{ status.name }}
      <span class="pill-count">{{ countByStatus[status.name] ?? 0 }}</span>
    </button>

    <!-- Divider -->
    <div class="h-5 w-px bg-gray-200 dark:bg-gray-600 mx-1" />

    <!-- Fit all button -->
    <button
      class="filter-pill"
      title="Fit all markers in view"
      @click="emit('fit-all')"
    >
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
      </svg>
      Fit All
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  statuses: { id: number; name: string; color_code: string }[]
  countByStatus: Record<string, number>
  activeStatusId: number | null
  total: number
}>()

const emit = defineEmits<{
  'update:statusId': [id: number | null]
  'fit-all': []
}>()
</script>

<style scoped>
.filter-pill {
  @apply inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
         border border-gray-200 dark:border-gray-600
         bg-white dark:bg-gray-800
         text-gray-600 dark:text-gray-300
         hover:border-blue-400 dark:hover:border-blue-500
         hover:text-blue-600 dark:hover:text-blue-400
         transition-all duration-150 cursor-pointer;
}
.filter-pill.active {
  @apply bg-blue-600 border-blue-600 text-white dark:bg-blue-500 dark:border-blue-500;
}
.pill-count {
  @apply bg-black/10 dark:bg-white/15 text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none;
}
.filter-pill.active .pill-count {
  @apply bg-white/20;
}
</style>