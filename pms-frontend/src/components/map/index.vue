<template>
  <div class="flex flex-col gap-4 h-full">

    <!-- Stats Header Bar -->
    <MapStatsBar
      :total="mapStore.totalProjects"
      :count-by-status="mapStore.projectCountByStatus"
      :statuses="mapStore.activeStatuses"
    />

    <!-- Filter Bar -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <MapFilterBar
        :statuses="mapStore.activeStatuses"
        :count-by-status="mapStore.projectCountByStatus"
        :active-status-id="mapStore.filters.status_id ?? null"
        :total="mapStore.totalProjects"
        @update:status-id="onStatusFilter"
        @fit-all="fitAll"
      />

      <!-- Right: loading indicator -->
      <div class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
        <template v-if="mapStore.loading">
          <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          Loading...
        </template>
        <template v-else-if="mapStore.error">
          <AlertTriangle :size="14" class="text-red-400" />
          <span class="text-red-400">{{ mapStore.error }}</span>
          <button class="underline hover:text-red-500" @click="loadProjects">Retry</button>
        </template>
        <template v-else>
          <MapPin :size="13" />
          {{ mapStore.plottableProjects.length }} plotted
        </template>
      </div>
    </div>

    <!-- Map Container -->
    <div class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm" style="height: 600px;">

      <!-- Leaflet map -->
      <div
        id="project-map"
        class="absolute inset-0 w-full h-full"
        :style="{ filter: isDark ? 'brightness(0.85) saturate(0.9)' : 'none' }"
      />

      <!-- Ctrl+scroll hint overlay -->
      <Transition name="fade">
        <div
          v-if="showScrollHint"
          class="absolute inset-0 flex items-center justify-center z-[300] pointer-events-none"
        >
          <div class="bg-black/70 backdrop-blur-sm text-white text-sm font-medium
                      px-5 py-3 rounded-xl border border-white/10">
            Use Ctrl + Scroll to zoom the map
          </div>
        </div>
      </Transition>

      <!-- Empty state overlay -->
      <div
        v-if="!mapStore.loading && mapStore.plottableProjects.length === 0"
        class="absolute inset-0 flex flex-col items-center justify-center z-[300]
               bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm"
      >
        <MapPin :size="40" class="text-gray-300 dark:text-gray-600 mb-3" />
        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No projects with map locations found</p>
        <button
          v-if="mapStore.hasActiveFilters"
          class="mt-3 text-xs text-blue-500 hover:underline"
          @click="clearFilters"
        >
          Clear filters
        </button>
      </div>
    </div>

    <!-- Side Panel -->
    <ProjectMapPanel
      :project="mapStore.selectedProject"
      @close="mapStore.setSelectedProject(null)"
    />

  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { AlertTriangle, MapPin } from 'lucide-vue-next'
import { useMapStore } from '@/store/map'
import { useLeafletMap } from '@/composables/useLeafletMap'
import { useScrollHint } from '@/composables/useScrollHint'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'
import type { MapProject } from '@/types/map'

import MapStatsBar from './components/MapStatsBar.vue'
import MapFilterBar from './components/MapFilterBar.vue'
import ProjectMapPanel from './components/ProjectMapPanel.vue'

// ── Stores ────────────────────────────────────────────────────────────────────
const mapStore = useMapStore()
const layoutStore = useLayoutStore()
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK)

// ── Leaflet ───────────────────────────────────────────────────────────────────
const { isMapReady, initMap, renderMarkers, flyToProject, fitAllMarkers } =
  useLeafletMap('project-map', onMarkerClick)

// ── Scroll hint ───────────────────────────────────────────────────────────────
const { showScrollHint, attachScrollHint } = useScrollHint('project-map')

// ── Handlers ──────────────────────────────────────────────────────────────────
function onMarkerClick(project: MapProject) {
  mapStore.setSelectedProject(project)
  flyToProject(project)
}

async function onStatusFilter(statusId: number | null) {
  mapStore.setFilters({ status_id: statusId })
  await mapStore.fetchMapProjects()
}

function fitAll() {
  fitAllMarkers(mapStore.plottableProjects)
}

function clearFilters() {
  mapStore.resetFilters()
  loadProjects()
}

async function loadProjects() {
  await mapStore.fetchMapProjects()
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(async () => {
  // Init Leaflet then load data
  initMap()
  attachScrollHint()
  await loadProjects()
})

// Re-render markers whenever project list or selectedProject changes
watch(
  [() => mapStore.plottableProjects, () => mapStore.selectedProject, isMapReady],
  ([projects, selected, ready]) => {
    if (!ready) return
    renderMarkers(projects, selected?.id ?? null)
  },
  { immediate: true }
)
</script>

<style>
/* Leaflet tooltip override — cannot be scoped since Leaflet renders outside component DOM */
.map-tooltip {
  background: rgba(15, 23, 42, 0.9) !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 6px !important;
  color: #f1f5f9 !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  padding: 4px 10px !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
  white-space: nowrap !important;
}
.map-tooltip::before {
  display: none !important;
}

/* Leaflet zoom control */
.leaflet-control-zoom a {
  background-color: white !important;
  color: #374151 !important;
  border-color: #e5e7eb !important;
}
.dark .leaflet-control-zoom a {
  background-color: #1f2937 !important;
  color: #d1d5db !important;
  border-color: #374151 !important;
}
</style>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>