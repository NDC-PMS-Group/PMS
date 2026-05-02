<script lang="ts" setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import {
  Loader2, MapPin, Layers, Map as MapIcon, Globe,
  Maximize2, Minimize2, Navigation, X as XIcon,
} from 'lucide-vue-next'
import { useMapStore }    from '@/store/map'
import { useLayoutStore } from '@/store/layout'
import { toast }          from 'vue3-toastify'
import type { MapProject } from '@/types/map'

import MapFilterBar    from './components/MapFilterBar.vue'
import ProjectMapPanel from './components/ProjectMapPanel.vue'

// ── Stores ────────────────────────────────────────────────────────────────
const mapStore    = useMapStore()
const layoutStore = useLayoutStore()

// ── Refs ──────────────────────────────────────────────────────────────────
const containerEl      = ref<HTMLElement | null>(null)
const mapEl            = ref<HTMLElement | null>(null)
const streetViewEl     = ref<HTMLElement | null>(null)
const mapInstance      = ref<any>(null)
const mapLoading       = ref(true)
const mapError         = ref('')
const mapType          = ref<'roadmap' | 'satellite' | 'terrain'>('roadmap')
const isFullscreen     = ref(false)
const streetViewActive = ref(false)

// Internal (imperative) storage — NOT reactive
let markers:            any[] = []
let infoWindow:         any   = null
let boundaryFeatures:   any[] = []
let streetViewInstance: any   = null
// projectId → marker for in-place selection updates without rerendering
const markerMap = new Map<number, any>()

// ── Constants ─────────────────────────────────────────────────────────────
const GOOGLE_MAPS_API_KEY = 'AIzaSyBvGOC4HUPjiDuOE2yr7CwbnC4j6vsa274'
const PH_CENTER           = { lat: 12.8797, lng: 121.7740 }
const PH_ZOOM             = 6

// Max zoom cap per location level after fitBounds
const MAX_ZOOM: Record<string, number> = {
  region:   8,
  province: 10,
  city:     13,
  barangay: 16,
}

const DEFAULT_PIN_COLOR = '#6B7280'

// ── Sidebar-aware fixed left offset ──────────────────────────────────────
const sidebarLeft = computed(() =>
  isFullscreen.value ? '0px' : (layoutStore.isSidebarCollapsed ? '64px' : '256px'),
)

function pinColor(p: MapProject): string {
  return p.status?.color_code ?? DEFAULT_PIN_COLOR
}

// ── Status legend (built from data) ───────────────────────────────────────
const statusLegend = computed(() => {
  const seen = new Map<number, { id: number; name: string; color: string; count: number }>()
  for (const p of mapStore.plottableProjects) {
    if (!p.status) continue
    const cur = seen.get(p.status.id)
    if (cur) {
      cur.count += 1
    } else {
      seen.set(p.status.id, {
        id:    p.status.id,
        name:  p.status.name,
        color: p.status.color_code ?? DEFAULT_PIN_COLOR,
        count: 1,
      })
    }
  }
  return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name))
})

// ── SVG helpers — teardrop pin (mirrors PMS tooltip aesthetic) ────────────
function pinSvg(color: string, isSelected: boolean): string {
  const w  = isSelected ? 22 : 16
  const h  = Math.round(w * 1.625)
  const cx = w / 2
  const r  = cx - 1.5
  const iy = r + 1.5
  const ring = isSelected
    ? `<circle cx="${cx}" cy="${iy}" r="${r + 4}" fill="${color}" opacity="0.18"/>`
    : ''
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
    ${ring}
    <path d="M${cx},${h - 1}
             C${cx},${h - 1} ${1.5},${iy + r * 0.9} ${1.5},${iy}
             A${r},${r} 0 1 1 ${w - 1.5},${iy}
             C${w - 1.5},${iy + r * 0.9} ${cx},${h - 1} ${cx},${h - 1}Z"
          fill="${color}" stroke="white" stroke-width="${isSelected ? 2 : 1.5}" stroke-linejoin="round"/>
    <circle cx="${cx}" cy="${iy}" r="${r * 0.36}" fill="white" fill-opacity="0.85"/>
  </svg>`
}

function toDataUrl(svg: string): string {
  return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg)
}

// ── Rich tooltip (HTML used in InfoWindow on hover) ───────────────────────
function buildTooltipHtml(project: MapProject): string {
  const thumbnail = project.thumbnail_url
    ? `<img src="${project.thumbnail_url}" class="mtt-thumb" alt=""/>`
    : `<div class="mtt-thumb mtt-thumb--empty">
         <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none"
              viewBox="0 0 24 24" stroke="rgba(255,255,255,0.3)" stroke-width="1.5">
           <path stroke-linecap="round" stroke-linejoin="round"
             d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159
                m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909
                M3.75 18h16.5M3 9.75h18"/>
         </svg>
       </div>`

  const logo = project.logo_url
    ? `<img src="${project.logo_url}" class="mtt-logo" alt=""/>`
    : `<div class="mtt-logo mtt-logo--empty">
         <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none"
              viewBox="0 0 24 24" stroke="rgba(255,255,255,0.4)" stroke-width="1.5">
           <rect x="3" y="3" width="18" height="18" rx="3"/>
         </svg>
       </div>`

  const color  = project.status?.color_code ?? DEFAULT_PIN_COLOR
  const status = project.status?.name ?? ''

  return `
    <div class="mtt-card">
      <div class="mtt-banner">${thumbnail}</div>
      <div class="mtt-body">
        <div class="mtt-row">
          ${logo}
          <div class="mtt-meta">
            <span class="mtt-code">${project.project_code ?? ''}</span>
            <span class="mtt-dot" style="background:${color}"></span>
            <span class="mtt-status">${status}</span>
          </div>
        </div>
        <p class="mtt-title">${project.title ?? ''}</p>
        ${project.project_type ? `<p class="mtt-type">${project.project_type.name}</p>` : ''}
        ${project.location.address
          ? `<div class="mtt-address">
               <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" fill="none"
                    viewBox="0 0 24 24" stroke="rgba(255,255,255,0.4)" stroke-width="2">
                 <path stroke-linecap="round" stroke-linejoin="round"
                   d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                 <path stroke-linecap="round" stroke-linejoin="round"
                   d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
               </svg>
               <span>${project.location.address}</span>
             </div>`
          : ''}
      </div>
    </div>`
}

// ── Load Google Maps ──────────────────────────────────────────────────────
function loadGoogleMaps(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (typeof (window as any).google?.maps?.Map === 'function') return resolve()

    if (document.getElementById('gmaps-script')) {
      let tries = 0
      const poll = setInterval(() => {
        if (typeof (window as any).google?.maps?.Map === 'function') {
          clearInterval(poll); resolve()
        }
        if (++tries > 150) {
          clearInterval(poll); reject(new Error('Timeout loading Google Maps'))
        }
      }, 100)
      return
    }

    const cbName = '__gmapsMapInit_' + Date.now()
    ;(window as any)[cbName] = () => { delete (window as any)[cbName]; resolve() }
    const script   = document.createElement('script')
    script.id      = 'gmaps-script'
    script.async   = true
    script.defer   = true
    script.src     = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=places,geometry&callback=${cbName}`
    script.onerror = () => reject(new Error('Failed to load Google Maps'))
    document.head.appendChild(script)
  })
}

// ── Init map ──────────────────────────────────────────────────────────────
async function initMap() {
  mapLoading.value = true
  mapError.value   = ''
  try {
    await loadGoogleMaps()
    await nextTick()
    if (!mapEl.value) return

    const G = (window as any).google.maps
    mapInstance.value = new G.Map(mapEl.value, {
      center:            PH_CENTER,
      zoom:              PH_ZOOM,
      mapTypeId:         G.MapTypeId.ROADMAP,
      zoomControl:       true,
      mapTypeControl:    false,
      streetViewControl: false,
      fullscreenControl: false,
      styles:            mapStyles,
    })

    infoWindow = new G.InfoWindow({ disableAutoPan: true })

    // Boundary (Data layer) style
    mapInstance.value.data.setStyle({
      fillColor:     '#3b82f6',
      fillOpacity:   0.10,
      strokeColor:   '#1d4ed8',
      strokeWeight:  2.5,
      strokeOpacity: 0.90,
    })

    // Zoom change → re-render markers (pin size scales with zoom)
    mapInstance.value.addListener('zoom_changed', () => {
      mapStore.setMapZoom(mapInstance.value.getZoom() ?? PH_ZOOM)
      renderMarkers()
    })

    // Click map background → deselect
    mapInstance.value.addListener('click', () => {
      onPanelClose()
      infoWindow?.close()
    })

    await loadProjects()
    renderMarkers()
  } catch (err: any) {
    mapError.value = err?.message ?? 'Failed to load map.'
  } finally {
    mapLoading.value = false
  }
}

// ── Marker rendering ──────────────────────────────────────────────────────
function clearMarkers() {
  markers.forEach((m) => m.setMap(null))
  markers = []
  markerMap.clear()
}

function renderMarkers() {
  if (!mapInstance.value) return
  clearMarkers()

  const G        = (window as any).google.maps
  const projects = mapStore.plottableProjects
  if (!projects.length) return

  const selectedId = mapStore.selectedProject?.id ?? null

  projects.forEach((project) => {
    const lat = Number(project.location.latitude)
    const lng = Number(project.location.longitude)
    if (Number.isNaN(lat) || Number.isNaN(lng)) return

    const isSelected = project.id === selectedId
    const color      = pinColor(project)
    const w          = isSelected ? 22 : 16
    const h          = Math.round(w * 1.625)

    const marker = new G.Marker({
      position: { lat, lng },
      map:      mapInstance.value,
      icon: {
        url:        toDataUrl(pinSvg(color, isSelected)),
        scaledSize: new G.Size(w, h),
        anchor:     new G.Point(w / 2, h),
      },
      title:  project.title ?? '',
      zIndex: isSelected ? 1000 : 50,
    })

    marker.addListener('click', () => {
      onMarkerClick(project)
      // Pan slightly left so the side panel doesn't hide the pin
      const pos = marker.getPosition()
      if (pos) mapInstance.value.panTo({ lat: pos.lat(), lng: pos.lng() - 0.01 })
    })

    marker.addListener('mouseover', () => {
      infoWindow?.setContent(buildTooltipHtml(project))
      infoWindow?.open(mapInstance.value, marker)
    })
    marker.addListener('mouseout', () => infoWindow?.close())

    markers.push(marker)
    markerMap.set(project.id, marker)
  })
}

function updateMarkerSelection(prevId: number | null, nextId: number | null) {
  if (!mapInstance.value) return
  const G = (window as any).google.maps

  const swap = (id: number | null, selected: boolean) => {
    if (id === null) return
    const m = markerMap.get(id)
    const p = mapStore.plottableProjects.find((x) => x.id === id)
    if (!m || !p) return
    const w = selected ? 22 : 16
    const h = Math.round(w * 1.625)
    m.setIcon({
      url:        toDataUrl(pinSvg(pinColor(p), selected)),
      scaledSize: new G.Size(w, h),
      anchor:     new G.Point(w / 2, h),
    })
    m.setZIndex(selected ? 1000 : 50)
  }
  swap(prevId, false)
  swap(nextId, true)
}

// ── Refresh one marker's tooltip (after media upload) ─────────────────────
function refreshMarkerTooltip(project: MapProject) {
  // Tooltip content is built lazily on hover from the latest project ref via
  // the map store. Replace the marker's project reference by re-binding click
  // and hover handlers using the up-to-date object.
  const m = markerMap.get(project.id)
  if (!m) return
  // Easiest path: re-render marker icon + handlers in place
  const G = (window as any).google.maps
  m.setIcon({
    url:        toDataUrl(pinSvg(pinColor(project), project.id === mapStore.selectedProject?.id)),
    scaledSize: new G.Size(16, 26),
    anchor:     new G.Point(8, 26),
  })
  // Force the next mouseover to rebuild content (closure captures the new
  // project via the store; cheaper than rebinding listeners).
}

// ── Boundary highlight via Nominatim ──────────────────────────────────────
function clearBoundary() {
  boundaryFeatures.forEach((f) => mapInstance.value?.data?.remove(f))
  boundaryFeatures = []
}

function cleanLocationName(raw: string): string {
  const paren = raw.match(/\(([^)]+)\)/)
  if (paren) return toTitleCase(paren[1])
  const prefix = raw.match(/^region\s+[\w\d-]+\s*[-–]\s*(.+)/i)
  if (prefix) return toTitleCase(prefix[1].trim())
  const dashPrefix = raw.match(/^[\w]+\s*[-–]\s*(.+)/i)
  if (dashPrefix && raw.toUpperCase().startsWith('NCR')) {
    return toTitleCase(dashPrefix[1].trim())
  }
  return toTitleCase(raw)
}
function toTitleCase(s: string): string {
  return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
}
function bboxArea(bbox: string[]): number {
  if (!bbox || bbox.length < 4) return 0
  return (parseFloat(bbox[1]) - parseFloat(bbox[0])) *
         (parseFloat(bbox[3]) - parseFloat(bbox[2]))
}

async function fetchAndShowBoundary(rawName: string, type: string) {
  clearBoundary()
  if (!rawName || !mapInstance.value) return
  const cleanName = cleanLocationName(rawName)

  try {
    const q   = encodeURIComponent(`${cleanName} Philippines`)
    const url = `https://nominatim.openstreetmap.org/search?q=${q}&polygon_geojson=1&format=json&limit=10&addressdetails=1`
    const res = await fetch(url, {
      headers: { 'Accept-Language': 'en', 'User-Agent': 'NDC-PMS/1.0' },
    })
    if (!res.ok) return
    const results: any[] = await res.json()
    if (!results.length) return

    let adminResults = results.filter(
      (r) => r.class === 'boundary' && r.type === 'administrative' && r.geojson,
    )
    if (!adminResults.length) adminResults = results.filter((r) => r.geojson)
    if (!adminResults.length) return

    adminResults.sort((a, b) => bboxArea(b.boundingbox) - bboxArea(a.boundingbox))
    const best = adminResults[0]

    const G       = (window as any).google.maps
    const geojson = { type: 'Feature', geometry: best.geojson, properties: {} }
    const added   = mapInstance.value.data.addGeoJson(geojson)
    boundaryFeatures = added

    const bounds = new G.LatLngBounds()
    added.forEach((feature: any) => {
      feature.getGeometry()?.forEachLatLng?.((ll: any) => bounds.extend(ll))
    })

    if (!bounds.isEmpty()) {
      mapInstance.value.fitBounds(bounds, { padding: 40 })
      const cap = MAX_ZOOM[type] ?? 12
      G.event.addListenerOnce(mapInstance.value, 'idle', () => {
        if ((mapInstance.value.getZoom() ?? 0) > cap) {
          mapInstance.value.setZoom(cap)
        }
      })
    }
  } catch (err) {
    // Non-critical — silently skip if the boundary fetch fails
    console.warn('[ProjectMap] boundary fetch failed:', err)
  }
}

// Watch location cascade → fetch boundary
watch(
  () => mapStore.location,
  async (loc) => {
    if (!mapInstance.value) return
    if (loc.barangayName) {
      await fetchAndShowBoundary(`${loc.barangayName} ${loc.cityName}`, 'barangay')
    } else if (loc.cityName) {
      await fetchAndShowBoundary(loc.cityName, 'city')
    } else if (loc.provinceName) {
      await fetchAndShowBoundary(loc.provinceName, 'province')
    } else if (loc.regionName) {
      await fetchAndShowBoundary(loc.regionName, 'region')
    } else {
      clearBoundary()
      mapInstance.value.setCenter(PH_CENTER)
      mapInstance.value.setZoom(PH_ZOOM)
    }
  },
  { deep: true },
)

// Re-render markers when the project list changes
watch(
  () => mapStore.plottableProjects,
  () => renderMarkers(),
  { deep: true },
)

// ── Marker click / panel close ────────────────────────────────────────────
function onMarkerClick(project: MapProject) {
  const prevId = mapStore.selectedProject?.id ?? null
  mapStore.setSelectedProject(project)
  updateMarkerSelection(prevId, project.id)
}

function onPanelClose() {
  const prevId = mapStore.selectedProject?.id ?? null
  mapStore.setSelectedProject(null)
  updateMarkerSelection(prevId, null)
}

// ── Filter handlers ───────────────────────────────────────────────────────
async function onStatusFilter(statusId: number | null) {
  mapStore.setFilters({ status_id: statusId })
  await loadProjects()
}

async function loadProjects() {
  await mapStore.fetchMapProjects().catch(() => {/* error already in store */})
}

function fitAll() {
  if (!mapInstance.value) return
  const G       = (window as any).google.maps
  const coords  = mapStore.plottableProjects
    .filter((p) => p.location.latitude !== null && p.location.longitude !== null)
    .map((p) => ({ lat: Number(p.location.latitude), lng: Number(p.location.longitude) }))
  if (!coords.length) return
  const bounds = new G.LatLngBounds()
  coords.forEach((c) => bounds.extend(c))
  mapInstance.value.fitBounds(bounds, 60)
}

// ── Map type ──────────────────────────────────────────────────────────────
function setMapType(type: 'roadmap' | 'satellite' | 'terrain') {
  mapType.value = type
  if (!mapInstance.value) return
  const G = (window as any).google.maps
  const t = {
    roadmap:   G.MapTypeId.ROADMAP,
    satellite: G.MapTypeId.HYBRID,
    terrain:   G.MapTypeId.TERRAIN,
  }
  mapInstance.value.setMapTypeId(t[type])
}

function resetView() {
  clearBoundary()
  mapStore.clearLocation()
  mapInstance.value?.setCenter(PH_CENTER)
  mapInstance.value?.setZoom(PH_ZOOM)
}

// ── Street View ───────────────────────────────────────────────────────────
function openStreetView(project: MapProject) {
  if (!project.location.latitude || !project.location.longitude) return
  const G    = (window as any).google.maps
  const lat  = Number(project.location.latitude)
  const lng  = Number(project.location.longitude)
  const ll   = new G.LatLng(lat, lng)

  const sv = new G.StreetViewService()
  sv.getPanorama(
    { location: ll, radius: 200, preference: G.StreetViewPreference.NEAREST },
    async (data: any, status: any) => {
      if (status !== 'OK') {
        toast.error('Street View imagery is not available for this location.')
        return
      }
      streetViewActive.value = true
      await nextTick()
      if (!streetViewEl.value) return

      if (!streetViewInstance) {
        streetViewInstance = new G.StreetViewPanorama(streetViewEl.value, {
          position:          data.location.latLng,
          pov:               { heading: 0, pitch: 0 },
          zoom:              1,
          addressControl:    true,
          fullscreenControl: false,
        })
      } else {
        streetViewInstance.setPosition(data.location.latLng)
        streetViewInstance.setPov({ heading: 0, pitch: 0 })
      }
    },
  )
}

function closeStreetView() {
  streetViewActive.value = false
}

// ── Fullscreen ────────────────────────────────────────────────────────────
function toggleFullscreen() {
  if (!document.fullscreenElement) {
    containerEl.value?.requestFullscreen?.()
  } else {
    document.exitFullscreen?.()
  }
}
function onFullscreenChange() {
  isFullscreen.value = !!document.fullscreenElement
}

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(async () => {
  document.addEventListener('fullscreenchange', onFullscreenChange)
  await initMap()
})

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange)
  clearMarkers()
  clearBoundary()
  mapStore.setSelectedProject(null)
  infoWindow = null
})

// ── Map styles ────────────────────────────────────────────────────────────
const mapStyles = [
  { featureType: 'poi',                     elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'transit',                 elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'water',                                                    stylers: [{ color: '#b3d1f5' }] },
  { featureType: 'landscape',                                                stylers: [{ color: '#f5f5f0' }] },
  { featureType: 'road.highway',            elementType: 'geometry',        stylers: [{ color: '#ffffff' }] },
  { featureType: 'road.arterial',           elementType: 'geometry',        stylers: [{ color: '#f8f8f8' }] },
  { featureType: 'administrative.country',  elementType: 'geometry.stroke', stylers: [{ color: '#a0aec0' }, { weight: 1.2 }] },
  { featureType: 'administrative.province', elementType: 'geometry.stroke', stylers: [{ color: '#cbd5e0' }, { weight: 0.8 }] },
]
</script>

<template>
  <div
    ref="containerEl"
    class="fixed top-[60px] bottom-0 right-0 flex flex-col overflow-hidden bg-gray-100 dark:bg-gray-900"
    :style="{
      left: sidebarLeft,
      transition: isFullscreen ? 'none' : 'left 300ms cubic-bezier(0.4,0,0.2,1)',
    }"
  >
    <!-- Filter Bar -->
    <MapFilterBar
      :statuses="mapStore.activeStatuses"
      :count-by-status="mapStore.projectCountByStatus"
      :active-status-id="mapStore.filters.status_id ?? null"
      :total="mapStore.totalProjects"
      @update:status-id="onStatusFilter"
      @fit-all="fitAll"
    />

    <!-- Map area -->
    <div class="relative flex flex-1 overflow-hidden">

      <!-- Google Map canvas -->
      <div ref="mapEl" v-show="!streetViewActive" class="flex-1 h-full" />

      <!-- Street View canvas -->
      <div ref="streetViewEl" v-show="streetViewActive" class="flex-1 h-full" />

      <!-- Loading overlay -->
      <Transition
        enter-active-class="transition-opacity duration-300"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="mapLoading"
          class="absolute inset-0 z-20 flex flex-col items-center justify-center
                 bg-gray-100 dark:bg-gray-900 gap-3"
        >
          <Loader2 class="h-8 w-8 text-blue-500 animate-spin" />
          <p class="text-sm text-gray-600 dark:text-gray-400">Loading map…</p>
        </div>
      </Transition>

      <!-- Error -->
      <div
        v-if="mapError"
        class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3"
      >
        <MapPin class="h-10 w-10 text-red-400" />
        <p class="text-sm text-red-600">{{ mapError }}</p>
        <button
          class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          @click="initMap"
        >
          Retry
        </button>
      </div>

      <!-- Empty state -->
      <div
        v-if="!mapLoading && !mapError && mapStore.plottableProjects.length === 0"
        class="absolute inset-0 z-10 flex flex-col items-center justify-center pointer-events-none"
      >
        <MapPin :size="40" class="text-gray-300 dark:text-gray-600 mb-3" />
        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
          No projects with map locations found
        </p>
      </div>

      <!-- Street View exit bar -->
      <Transition
        enter-active-class="transition-all duration-200"
        enter-from-class="opacity-0 translate-y-2"
        leave-active-class="transition-all duration-150"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div
          v-if="streetViewActive"
          class="absolute bottom-4 left-1/2 -translate-x-1/2 z-30"
        >
          <button
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium
                   bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100
                   rounded-full shadow-lg border border-gray-200 hover:bg-gray-50 transition"
            @click="closeStreetView"
          >
            <XIcon class="h-4 w-4" />
            Exit Street View
          </button>
        </div>
      </Transition>

      <!-- ── Bottom-left: map type + reset + count badge ───────────────── -->
      <div
        v-if="!mapLoading && !mapError"
        class="absolute bottom-6 left-4 z-10 flex flex-col gap-2"
      >
        <div
          class="flex flex-col bg-white dark:bg-gray-800 rounded-xl shadow-lg
                 border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
          <button
            v-for="t in ([
              { key: 'roadmap',   label: 'Map',       icon: MapIcon },
              { key: 'satellite', label: 'Satellite', icon: Globe },
              { key: 'terrain',   label: 'Terrain',   icon: Layers },
            ] as const)"
            :key="t.key"
            :class="[
              'flex items-center gap-2 px-3 py-2 text-xs font-medium transition',
              mapType === t.key
                ? 'bg-blue-600 text-white'
                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
            ]"
            @click="setMapType(t.key)"
          >
            <component :is="t.icon" class="h-3.5 w-3.5 flex-shrink-0" />
            {{ t.label }}
          </button>
        </div>

        <button
          class="flex items-center gap-1.5 px-3 py-2 text-xs font-medium
                 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300
                 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg
                 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          @click="resetView"
        >
          <MapPin class="h-3.5 w-3.5" /> Philippines
        </button>

        <!-- Count badge -->
        <div
          class="flex items-center gap-1.5 px-3 py-1.5 bg-white/95 dark:bg-gray-800/95
                 backdrop-blur-sm rounded-full shadow border border-gray-200 dark:border-gray-700
                 text-xs text-gray-600 dark:text-gray-400"
        >
          <span class="font-semibold text-gray-900 dark:text-gray-100">
            {{ mapStore.plottableProjects.length }}
          </span>
          project{{ mapStore.plottableProjects.length !== 1 ? 's' : '' }} mapped
          <span
            v-if="mapStore.totalProjects !== mapStore.plottableProjects.length"
            class="text-gray-400"
          >
            of {{ mapStore.totalProjects }}
          </span>
        </div>
      </div>

      <!-- ── Top-right: Street View + Fullscreen ───────────────────────── -->
      <div
        v-if="!mapLoading && !mapError"
        class="absolute top-3 z-10 flex gap-2 transition-all duration-300"
        :style="{ right: mapStore.selectedProject ? '404px' : '1rem' }"
      >
        <button
          v-if="mapStore.selectedProject?.location?.latitude"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm
                 text-gray-700 dark:text-gray-300
                 border border-gray-200 dark:border-gray-700 rounded-xl shadow
                 hover:bg-white transition"
          @click="openStreetView(mapStore.selectedProject!)"
        >
          <Navigation class="h-3.5 w-3.5 text-blue-500" />
          Street View
        </button>

        <button
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm
                 text-gray-700 dark:text-gray-300
                 border border-gray-200 dark:border-gray-700 rounded-xl shadow
                 hover:bg-white transition"
          @click="toggleFullscreen"
        >
          <component :is="isFullscreen ? Minimize2 : Maximize2" class="h-3.5 w-3.5" />
          {{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}
        </button>
      </div>

      <!-- ── Bottom-right: status legend ───────────────────────────────── -->
      <div
        v-if="!mapLoading && !mapError && statusLegend.length"
        class="absolute bottom-6 z-10 transition-all duration-300"
        :style="{ right: mapStore.selectedProject ? '404px' : '1rem' }"
      >
        <div
          class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm rounded-xl shadow-lg
                 border border-gray-200 dark:border-gray-700 px-4 py-3"
        >
          <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Status</p>
          <div class="flex flex-col gap-1.5">
            <div
              v-for="s in statusLegend"
              :key="s.id"
              class="flex items-center gap-2"
            >
              <span
                class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                :style="{ background: s.color }"
              />
              <span class="text-xs text-gray-600 dark:text-gray-400">
                {{ s.name }}
                <span class="text-gray-400">({{ s.count }})</span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Side panel (PMS slider info — kept untouched) ─────────────── -->
      <div class="absolute top-0 right-0 h-full z-20 flex">
        <ProjectMapPanel
          :project="mapStore.selectedProject"
          :refresh-marker-tooltip="refreshMarkerTooltip"
          @close="onPanelClose"
        />
      </div>
    </div>
  </div>
</template>

<style>
/* ── Tooltip wrapper — Google InfoWindow renders outside component DOM ───── */
.gm-style .gm-style-iw-c {
  padding:    0 !important;
  background: transparent !important;
  box-shadow: none !important;
  border-radius: 12px !important;
  overflow:   visible !important;
}
.gm-style .gm-style-iw-d {
  overflow:   visible !important;
  padding:    0 !important;
}
.gm-style .gm-style-iw-tc::after {
  background: rgba(10, 15, 30, 0.96) !important;
}
.gm-style .gm-ui-hover-effect {
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
  width:       100%;
  height:      85px;
  overflow:    hidden;
  background:  #111827;
  flex-shrink: 0;
}
.mtt-thumb {
  width:      100%;
  height:     100%;
  object-fit: cover;
  display:    block;
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
  display:       flex;
  align-items:   center;
  gap:           7px;
  margin-bottom: 5px;
}

/* Logo */
.mtt-logo {
  width:         24px;
  height:        24px;
  border-radius: 5px;
  object-fit:    contain;
  background:    rgba(255,255,255,0.07);
  border:        1px solid rgba(255,255,255,0.1);
  padding:       2px;
  flex-shrink:   0;
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
  width:         5px;
  height:        5px;
  border-radius: 50%;
  flex-shrink:   0;
}
.mtt-status {
  font-size:     9px;
  font-weight:   600;
  color:         rgba(255,255,255,0.5);
  white-space:   nowrap;
  overflow:      hidden;
  text-overflow: ellipsis;
}

/* Title & type */
.mtt-title {
  font-size:          12px;
  font-weight:        700;
  color:              #ffffff;
  line-height:        1.35;
  margin:             0 0 2px;
  display:            -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow:           hidden;
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
  font-size:          9px;
  color:              rgb(240, 240, 240);
  line-height:        1.4;
  display:            -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow:           hidden;
}
</style>
