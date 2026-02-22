<template>
  <div class="flex flex-col gap-4">

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

      <!-- Status indicator -->
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
    <div
      class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm"
      style="height: 600px;"
    >
      <!-- Leaflet map -->
      <div
        id="project-map"
        class="absolute inset-0 w-full h-full"
        :style="{ filter: isDark ? 'brightness(0.85) saturate(0.9)' : 'none' }"
      />

      <!-- Ctrl+scroll hint -->
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

      <!-- Empty state -->
      <div
        v-if="!mapStore.loading && mapStore.plottableProjects.length === 0"
        class="absolute inset-0 flex flex-col items-center justify-center z-[300]
               bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm"
      >
        <MapPin :size="40" class="text-gray-300 dark:text-gray-600 mb-3" />
        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
          No projects with map locations found
        </p>
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
      :refresh-marker-tooltip="refreshMarkerTooltip"
      @close="onPanelClose"
    />

  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { AlertTriangle, MapPin } from 'lucide-vue-next'
import { useMapStore }    from '@/store/map'
import { useLeafletMap }  from '@/composables/useLeafletMap'
import { useScrollHint }  from '@/composables/useScrollHint'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE }      from '@/app/const'
import type { MapProject } from '@/types/map'

import MapStatsBar     from './components/MapStatsBar.vue'
import MapFilterBar    from './components/MapFilterBar.vue'
import ProjectMapPanel from './components/ProjectMapPanel.vue'

// ── Stores ────────────────────────────────────────────────────────────────────

const mapStore    = useMapStore()
const layoutStore = useLayoutStore()
const isDark      = computed(() => layoutStore.mode === SITE_MODE.DARK)

// ── Composables ───────────────────────────────────────────────────────────────

const {
  isMapReady,
  initMap,
  renderMarkers,
  refreshMarkerTooltip,
  updateMarkerSelection,
  invalidateSize,
  flyToProject,
  fitAllMarkers,
} = useLeafletMap('project-map', onMarkerClick)

const { showScrollHint, attachScrollHint } = useScrollHint('project-map')

// ── Handlers ──────────────────────────────────────────────────────────────────

function onMarkerClick(project: MapProject) {
  const prev = mapStore.selectedProject
  mapStore.setSelectedProject(project)

  // Swap only the two affected marker icons — no clearLayers()
  updateMarkerSelection(prev?.id ?? null, project.id, mapStore.plottableProjects)

  // invalidateSize first so flyTo position is accurate after panel opens
  invalidateSize()
  flyToProject(project)
}

function onPanelClose() {
  const prev = mapStore.selectedProject
  mapStore.setSelectedProject(null)
  updateMarkerSelection(prev?.id ?? null, null, mapStore.plottableProjects)
  invalidateSize()
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

// ── Watchers ──────────────────────────────────────────────────────────────────

// Only re-render all markers when the PROJECT LIST changes.
// Selection changes are handled imperatively in onMarkerClick / onPanelClose.
watch(
  [() => mapStore.plottableProjects, isMapReady],
  ([projects, ready]) => {
    if (!ready) return
    renderMarkers(projects, mapStore.selectedProject?.id ?? null)
  },
  { immediate: true }
)

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  initMap()
  attachScrollHint()
  await loadProjects()
})
</script>

<style>
/* ── Tooltip wrapper — unscoped: Leaflet renders outside component DOM ───────── */
.mtt-wrapper.leaflet-tooltip {
  background:    transparent !important;
  border:        none !important;
  box-shadow:    none !important;
  padding:       0 !important;
  margin-bottom: 8px !important;
}
.mtt-wrapper.leaflet-tooltip::before {
  display: none !important;
}

/* Card */
.mtt-card {
  width:         210px;
  background:    rgba(10, 15, 30, 0.96);
  border:        1px solid rgba(255,255,255,0.1);
  border-radius: 12px;
  overflow:      hidden;
  box-shadow:    0 8px 32px rgba(0,0,0,0.55);
}

/* Banner / thumbnail */
.mtt-banner {
  width:      100%;
  height:     85px;
  overflow:   hidden;
  background: #111827;
  flex-shrink: 0;
}
.mtt-thumb {
  width:       100%;
  height:      100%;
  object-fit:  cover;
  display:     block;
}
.mtt-thumb--empty {
  display:         flex;
  align-items:     center;
  justify-content: center;
  background:      linear-gradient(135deg, #1a1f2e, #0d1117);
}

/* Body */
.mtt-body {
  padding: 9px 11px 10px;
}
.mtt-row {
  display:     flex;
  align-items: center;
  gap:         7px;
  margin-bottom: 5px;
}

/* Logo */
.mtt-logo {
  width:        24px;
  height:       24px;
  border-radius: 5px;
  object-fit:   contain;
  background:   rgba(255,255,255,0.07);
  border:       1px solid rgba(255,255,255,0.1);
  padding:      2px;
  flex-shrink:  0;
}
.mtt-logo--empty {
  width:           24px;
  height:          24px;
  border-radius:   5px;
  display:         flex;
  align-items:     center;
  justify-content: center;
  background:      rgba(255,255,255,0.05);
  border:          1px solid rgba(255,255,255,0.08);
  flex-shrink:     0;
}

/* Meta row */
.mtt-meta {
  display:     flex;
  align-items: center;
  gap:         4px;
  flex:        1;
  min-width:   0;
  overflow:    hidden;
}
.mtt-code {
  font-size:      9px;
  font-weight:    700;
  color:          rgba(255,255,255,0.4);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space:    nowrap;
}
.mtt-dot {
  width:        5px;
  height:       5px;
  border-radius: 50%;
  flex-shrink:  0;
}
.mtt-status {
  font-size:    9px;
  font-weight:  600;
  color:        rgba(255,255,255,0.5);
  white-space:  nowrap;
  overflow:     hidden;
  text-overflow:ellipsis;
}

/* Title & type */
.mtt-title {
  font-size:   12px;
  font-weight: 700;
  color:       #ffffff;
  line-height: 1.35;
  margin:      0 0 2px;
  display:         -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow:    hidden;
}
.mtt-type {
  font-size:     10px;
  color:         rgba(255,255,255,0.38);
  margin:        0 0 4px;
  white-space:   nowrap;
  overflow:      hidden;
  text-overflow: ellipsis;
}
.mtt-address {
  display:     flex;
  align-items: flex-start;
  gap:         4px;
  margin-top:  4px;
  padding-top: 4px;
  border-top:  1px solid rgba(255,255,255,0.07);
}
.mtt-address svg {
  flex-shrink: 0;
  margin-top:  1px;
}
.mtt-address span {
  font-size:   9px;
  color:       rgb(240, 240, 240);
  line-height: 1.4;
  display:         -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow:    hidden;
}

/* ── Leaflet zoom control dark mode ─────────────────────────────────────────── */
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
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from,  .fade-leave-to      { opacity: 0; }
</style>