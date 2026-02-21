<template>
  <div
    class="map-stats-bar relative overflow-hidden rounded-xl px-6 py-4
           flex items-center justify-between gap-4 flex-wrap"
    :class="isDark ? 'is-dark' : 'is-light'"
  >
    <!-- Dot grid overlay (matches your theme pattern) -->
    <div class="overlay-dots absolute inset-0 pointer-events-none" />

    <!-- Left: title + subtitle -->
    <div class="relative z-10">
      <h2 class="text-xl font-bold" :class="isDark ? 'text-white' : 'text-gray-800'">
        Project Locations
      </h2>
      <p class="text-xs mt-0.5" :class="isDark ? 'text-white/50' : 'text-gray-500'">
        Showing {{ total }} project{{ total !== 1 ? 's' : '' }} with mapped locations
      </p>
    </div>

    <!-- Right: stat pills -->
    <div class="relative z-10 flex items-center gap-2 flex-wrap">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="stat-chip"
        :class="isDark ? 'chip-dark' : 'chip-light'"
      >
        <span
          class="inline-block w-2 h-2 rounded-full flex-shrink-0"
          :style="{ backgroundColor: stat.color }"
        />
        <span class="font-bold">{{ stat.count }}</span>
        <span class="opacity-70">{{ stat.label }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'

const props = defineProps<{
  total: number
  countByStatus: Record<string, number>
  statuses: { id: number; name: string; color_code: string }[]
}>()

const layoutStore = useLayoutStore()
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK)

const stats = computed(() =>
  props.statuses
    .filter((s) => (props.countByStatus[s.name] ?? 0) > 0)
    .map((s) => ({
      label: s.name,
      count: props.countByStatus[s.name] ?? 0,
      color: s.color_code,
    }))
)
</script>

<style scoped>
.map-stats-bar.is-dark {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f4c81 100%);
}
.map-stats-bar.is-light {
  background: linear-gradient(135deg, #d6ddf5 0%, #2564eb79 60%, #0ea4e9a1 100%);
}
.overlay-dots {
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
.stat-chip {
  @apply inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs;
}
.chip-dark {
  @apply bg-white/10 text-white border border-white/15;
}
.chip-light {
  @apply bg-white/30 text-gray-800 border border-white/40;
}
</style>