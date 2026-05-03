<script lang="ts" setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ExternalLink, MapPin, Loader2 } from 'lucide-vue-next'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'
import axiosInstance from '@/utils/axiosInstance'
import { parseMapProjectList } from '@/types/map'
import type { MapProject } from '@/types/map'

// ── Stores / Router ─────────────────────────────────────────────────────
const router      = useRouter()
const layoutStore = useLayoutStore()
const isDark      = computed(() => layoutStore.mode === SITE_MODE.DARK)

// ── State ───────────────────────────────────────────────────────────────
interface RegionRow { region: string; count: number }

const mapEl       = ref<HTMLElement | null>(null)
const projects    = ref<MapProject[]>([])
const regionStats = ref<RegionRow[]>([])
const loading     = ref(true)
const error       = ref('')

let mapInstance: any = null
let markers: any[]   = []

// ── Constants ───────────────────────────────────────────────────────────
const GOOGLE_MAPS_API_KEY = 'AIzaSyBvGOC4HUPjiDuOE2yr7CwbnC4j6vsa274'
const PH_CENTER           = { lat: 12.8797, lng: 121.7740 }
const PH_ZOOM             = 5
const DEFAULT_PIN_COLOR   = '#3B82F6'

// ── Display helpers ─────────────────────────────────────────────────────
const totalMapped = computed(() => projects.value.length)
const totalRegion = computed(() => regionStats.value.reduce((s, r) => s + r.count, 0))

// Strip leading "REGION X — " / "(...)" wrappers so the bar chart isn't
// dominated by the boilerplate prefixes the PSGC dataset uses.
function shortenRegionName(raw: string): string {
  const paren = raw.match(/\(([^)]+)\)/)
  if (paren) return titleCase(paren[1].trim())
  const prefix = raw.match(/^region\s+[\w\d-]+\s*[-–]\s*(.+)/i)
  if (prefix) return titleCase(prefix[1].trim())
  return titleCase(raw)
}
function titleCase(s: string): string {
  return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase())
}

// ── ApexCharts — horizontal bar of region counts ────────────────────────
const chartSeries = computed(() => [{
  name: 'Projects',
  data: regionStats.value.map((r) => r.count),
}])

const chartOptions = computed<any>(() => ({
  chart: {
    type:       'bar',
    toolbar:    { show: false },
    foreColor:  isDark.value ? '#cbd5e1' : '#475569',
    fontFamily: 'inherit',
  },
  plotOptions: {
    bar: {
      horizontal:   true,
      borderRadius: 4,
      barHeight:    '70%',
      distributed:  true,
    },
  },
  colors: [
    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
    '#06B6D4', '#EC4899', '#F97316', '#84CC16', '#6366F1',
    '#14B8A6', '#A855F7', '#EAB308', '#F43F5E', '#22C55E',
    '#0EA5E9', '#D946EF',
  ],
  dataLabels: {
    enabled:  true,
    style:    { colors: ['#fff'], fontWeight: 600, fontSize: '11px' },
    offsetX:  0,
  },
  xaxis: {
    categories: regionStats.value.map((r) => shortenRegionName(r.region)),
    labels:     { style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: { style: { fontSize: '11px' } },
  },
  grid: {
    borderColor: isDark.value ? '#334155' : '#e2e8f0',
    strokeDashArray: 3,
  },
  legend: { show: false },
  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
    y: { formatter: (v: number) => `${v} project${v !== 1 ? 's' : ''}` },
  },
}))

// ── Google Maps loader (shared cached script) ───────────────────────────
function loadGoogleMaps(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (typeof (window as any).google?.maps?.Map === 'function') return resolve()
    if (document.getElementById('gmaps-script')) {
      let tries = 0
      const poll = setInterval(() => {
        if (typeof (window as any).google?.maps?.Map === 'function') {
          clearInterval(poll); resolve()
        }
        if (++tries > 150) { clearInterval(poll); reject(new Error('Timeout loading Google Maps')) }
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

function pinSvg(color: string, w = 12, h = 19): string {
  const cx = w / 2
  const r  = cx - 1
  const iy = r + 1
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
    <path d="M${cx},${h - 1}
             C${cx},${h - 1} 1,${iy + r * 0.9} 1,${iy}
             A${r},${r} 0 1 1 ${w - 1},${iy}
             C${w - 1},${iy + r * 0.9} ${cx},${h - 1} ${cx},${h - 1}Z"
          fill="${color}" stroke="white" stroke-width="1" stroke-linejoin="round"/>
  </svg>`
}
const toDataUrl = (svg: string) =>
  'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg)

// ── Map init ────────────────────────────────────────────────────────────
async function initMap() {
  await loadGoogleMaps()
  await nextTick()
  if (!mapEl.value) return

  const G = (window as any).google.maps
  mapInstance = new G.Map(mapEl.value, {
    center:            PH_CENTER,
    zoom:              PH_ZOOM,
    disableDefaultUI:  true,
    gestureHandling:   'none',           // read-only — clicks bubble to overlay
    keyboardShortcuts: false,
    clickableIcons:    false,
    backgroundColor:   isDark.value ? '#0f172a' : '#f8fafc',
    styles:            isDark.value ? darkMapStyles : lightMapStyles,
  })

  renderMarkers()
}

function clearMarkers() {
  markers.forEach((m) => m.setMap(null))
  markers = []
}

function renderMarkers() {
  if (!mapInstance) return
  clearMarkers()
  const G = (window as any).google.maps

  projects.value.forEach((p) => {
    const lat = Number(p.location.latitude)
    const lng = Number(p.location.longitude)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return
    const color = p.status?.color_code ?? DEFAULT_PIN_COLOR
    const marker = new G.Marker({
      position:  { lat, lng },
      map:       mapInstance,
      icon: {
        url:        toDataUrl(pinSvg(color)),
        scaledSize: new G.Size(12, 19),
        anchor:     new G.Point(6, 19),
      },
      clickable: false,
      title:     p.title ?? '',
    })
    markers.push(marker)
  })
}

// Re-style the map when dark mode toggles.
watch(isDark, () => {
  if (mapInstance) {
    mapInstance.setOptions({
      styles:          isDark.value ? darkMapStyles : lightMapStyles,
      backgroundColor: isDark.value ? '#0f172a' : '#f8fafc',
    })
  }
})

// ── Data fetch ──────────────────────────────────────────────────────────
async function fetchData() {
  loading.value = true
  error.value   = ''
  try {
    const [mapRes, statsRes] = await Promise.all([
      axiosInstance.get('/api/projects/map').catch((e) => { console.warn('[Dashboard] map fetch failed', e); return null }),
      axiosInstance.get('/api/projects/stats/by-region').catch((e) => { console.warn('[Dashboard] region stats fetch failed', e); return null }),
    ])
    projects.value    = mapRes ? parseMapProjectList(mapRes.data) : []
    regionStats.value = statsRes?.data?.data ?? []
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to load dashboard data'
  } finally {
    loading.value = false
  }
}

// ── Lifecycle ───────────────────────────────────────────────────────────
onMounted(async () => {
  await fetchData()
  await initMap()
})

onUnmounted(() => {
  clearMarkers()
  mapInstance = null
})

function goToFullMap() {
  router.push('/project-map')
}

// ── Map styles (subtle / clean) ─────────────────────────────────────────
const lightMapStyles = [
  { featureType: 'poi',                     elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'transit',                 elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'water',                                                     stylers: [{ color: '#cfe3f5' }] },
  { featureType: 'landscape',                                                 stylers: [{ color: '#f5f5f0' }] },
  { featureType: 'road',                    elementType: 'geometry',        stylers: [{ visibility: 'off' }] },
  { featureType: 'road',                    elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'administrative.country',  elementType: 'geometry.stroke', stylers: [{ color: '#94a3b8' }, { weight: 1 }] },
  { featureType: 'administrative.province', elementType: 'geometry.stroke', stylers: [{ color: '#cbd5e1' }, { weight: 0.6 }] },
]
const darkMapStyles = [
  { elementType: 'geometry',           stylers: [{ color: '#1e293b' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#0f172a' }] },
  { elementType: 'labels.text.fill',   stylers: [{ color: '#94a3b8' }] },
  { featureType: 'water',              stylers: [{ color: '#0c4a6e' }] },
  { featureType: 'poi',                elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'transit',            elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'road',               elementType: 'geometry',        stylers: [{ visibility: 'off' }] },
  { featureType: 'road',               elementType: 'labels',          stylers: [{ visibility: 'off' }] },
  { featureType: 'administrative.country',  elementType: 'geometry.stroke', stylers: [{ color: '#475569' }, { weight: 1 }] },
  { featureType: 'administrative.province', elementType: 'geometry.stroke', stylers: [{ color: '#334155' }, { weight: 0.6 }] },
]
</script>

<template>
  <TCard
    title="Projects Nationwide"
    class="col-span-12"
  >
    <template #titleAction>
      <button
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold
               text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20
               rounded-lg transition-colors"
        @click="goToFullMap"
      >
        View Full Map
        <ExternalLink class="h-3.5 w-3.5" />
      </button>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- ── Left: mini map ──────────────────────────────────────── -->
      <div
        class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700"
        style="min-height: 360px;"
      >
        <div ref="mapEl" class="absolute inset-0 cursor-pointer" @click="goToFullMap" />

        <!-- Click overlay — gestureHandling: 'none' lets the click bubble -->
        <div
          class="absolute inset-0 z-10 cursor-pointer"
          title="Open the full project map"
          @click="goToFullMap"
        />

        <!-- Loading -->
        <div
          v-if="loading"
          class="absolute inset-0 z-20 flex items-center justify-center bg-white/70 dark:bg-gray-900/70 backdrop-blur-sm"
        >
          <Loader2 class="h-6 w-6 animate-spin text-blue-500" />
        </div>

        <!-- Empty -->
        <div
          v-else-if="!loading && totalMapped === 0"
          class="absolute inset-0 z-20 flex flex-col items-center justify-center text-center px-4"
        >
          <MapPin class="h-8 w-8 text-gray-300 dark:text-gray-600 mb-2" />
          <p class="text-xs text-gray-500 dark:text-gray-400">
            No projects with map locations yet
          </p>
        </div>

        <!-- Mapped count badge -->
        <div
          v-if="!loading && totalMapped > 0"
          class="absolute bottom-3 left-3 z-20 flex items-center gap-1.5 px-2.5 py-1
                 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm rounded-full shadow
                 border border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400"
        >
          <span class="font-semibold text-gray-900 dark:text-gray-100">{{ totalMapped }}</span>
          plotted
        </div>
      </div>

      <!-- ── Right: bar chart ─────────────────────────────────────── -->
      <div
        class="relative rounded-xl border border-gray-200 dark:border-gray-700 px-2 py-3"
        style="min-height: 360px;"
      >
        <div class="flex items-baseline justify-between px-3 mb-1">
          <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
            Projects per Region
          </p>
          <p class="text-xs text-gray-400">
            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ totalRegion }}</span>
            total
          </p>
        </div>

        <!-- Loading -->
        <div
          v-if="loading"
          class="absolute inset-0 flex items-center justify-center"
        >
          <Loader2 class="h-6 w-6 animate-spin text-blue-500" />
        </div>

        <!-- Empty -->
        <div
          v-else-if="regionStats.length === 0"
          class="flex flex-col items-center justify-center text-center px-4 h-[300px]"
        >
          <MapPin class="h-8 w-8 text-gray-300 dark:text-gray-600 mb-2" />
          <p class="text-xs text-gray-500 dark:text-gray-400">
            No regional data yet
          </p>
          <p class="text-xs text-gray-400 mt-1">
            Region counts populate after projects are saved with a structured address.
          </p>
        </div>

        <!-- Chart -->
        <apexchart
          v-else
          :height="Math.max(300, regionStats.length * 28)"
          :series="chartSeries"
          :options="chartOptions"
        />
      </div>
    </div>
  </TCard>
</template>
