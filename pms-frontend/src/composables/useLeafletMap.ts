import { ref, onUnmounted } from 'vue'
import L from 'leaflet'
import type { MapProject } from '@/types/map'

// Fix Leaflet default icon paths broken by Vite bundling
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

delete (L.Icon.Default.prototype as any)._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

// Build an SVG circle marker colored by project status
const buildMarkerIcon = (color: string, isSelected: boolean): L.DivIcon => {
  const size = isSelected ? 18 : 13
  const ring = isSelected ? `<circle cx="18" cy="18" r="17" fill="none" stroke="${color}" stroke-width="2.5" opacity="0.4"/>` : ''
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36">
      ${ring}
      <circle cx="18" cy="18" r="${size}" fill="${color}" stroke="#ffffff" stroke-width="2.5"/>
    </svg>`
  return L.divIcon({
    html: svg,
    className: '',
    iconSize: [36, 36],
    iconAnchor: [18, 18],
    popupAnchor: [0, -20],
  })
}

export function useLeafletMap(
  mapContainerId: string,
  onMarkerClick: (project: MapProject) => void
) {
  const mapInstance = ref<L.Map | null>(null)
  const markersLayer = ref<L.LayerGroup | null>(null)
  const isMapReady = ref(false)

  const initMap = () => {
    const el = document.getElementById(mapContainerId)
    if (!el) return

    const map = L.map(el, {
      center: [12.8797, 121.774], // Philippines center
      zoom: 6,
      scrollWheelZoom: 'center',
      zoomControl: false,
    })

    // Custom zoom control position
    L.control.zoom({ position: 'bottomright' }).addTo(map)

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map)

    markersLayer.value = L.layerGroup().addTo(map)
    mapInstance.value = map
    isMapReady.value = true
  }

  const renderMarkers = (projects: MapProject[], selectedId: number | null = null) => {
    if (!markersLayer.value) return
    markersLayer.value.clearLayers()

    projects.forEach((project) => {
      const { latitude, longitude } = project.location
      if (latitude === null || longitude === null) return

      const color = project.status?.color_code ?? '#6B7280'
      const isSelected = project.id === selectedId
      const icon = buildMarkerIcon(color, isSelected)

      const marker = L.marker([latitude, longitude], { icon })
      marker.on('click', () => onMarkerClick(project))

      // Lightweight tooltip on hover
      marker.bindTooltip(project.title, {
        direction: 'top',
        offset: [0, -10],
        className: 'map-tooltip',
      })

      markersLayer.value!.addLayer(marker)
    })
  }

  const flyToProject = (project: MapProject) => {
    const { latitude, longitude } = project.location
    if (!mapInstance.value || latitude === null || longitude === null) return
    mapInstance.value.flyTo([latitude, longitude], 14, { animate: true, duration: 0.8 })
  }

  const fitAllMarkers = (projects: MapProject[]) => {
    if (!mapInstance.value || projects.length === 0) return
    const coords = projects
      .filter((p) => p.location.latitude !== null && p.location.longitude !== null)
      .map((p) => [p.location.latitude!, p.location.longitude!] as [number, number])
    if (coords.length === 0) return
    mapInstance.value.fitBounds(L.latLngBounds(coords), { padding: [40, 40], maxZoom: 13 })
  }

  onUnmounted(() => {
    mapInstance.value?.remove()
    mapInstance.value = null
  })

  return {
    mapInstance,
    isMapReady,
    initMap,
    renderMarkers,
    flyToProject,
    fitAllMarkers,
  }
}