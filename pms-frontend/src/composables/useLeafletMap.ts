import { ref, onUnmounted } from 'vue'
import L from 'leaflet'
import type { MapProject } from '@/types/map'

import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon   from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

delete (L.Icon.Default.prototype as any)._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl:       markerIcon,
  shadowUrl:     markerShadow,
})

const buildPinIcon = (color: string, isSelected: boolean): L.DivIcon => {
  const w  = isSelected ? 28 : 22   // overall width
  const h  = isSelected ? 38 : 30   // overall height
  const r  = w / 2                  // circle radius = half width
  const cx = w / 2
  const cy = r

  // Pulse ring for selected state
  const ring = isSelected
    ? `<circle cx="${cx}" cy="${cy}" r="${r + 5}" fill="${color}" opacity="0.18"/>`
    : ''

  // Teardrop path: circle at top + triangular tip pointing down
  // We draw it as a combined path
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">
      ${ring}
      <path
        d="M${cx},${h}
           C${cx},${h} 0,${cy + r * 0.8} 0,${cy}
           A${r},${r} 0 1,1 ${w},${cy}
           C${w},${cy + r * 0.8} ${cx},${h} ${cx},${h}Z"
        fill="${color}"
        stroke="#ffffff"
        stroke-width="${isSelected ? 2 : 1.5}"
      />
      <circle cx="${cx}" cy="${cy}" r="${r * 0.38}" fill="#ffffff" opacity="0.85"/>
    </svg>`

  return L.divIcon({
    html:       svg,
    className:  '',
    iconSize:   [w, h],
    iconAnchor: [w / 2, h],    // tip of the pin = anchor
    popupAnchor:[0, -(h + 4)],
  })
}

// ── Rich tooltip ───────────────────────────────────────────────────────────────

const buildTooltipHtml = (project: MapProject): string => {
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

  const color  = project.status?.color_code ?? '#6B7280'
  const status = project.status?.name ?? ''

  return `
    <div class="mtt-card">
      <div class="mtt-banner">${thumbnail}</div>
      <div class="mtt-body">
        <div class="mtt-row">
          ${logo}
          <div class="mtt-meta">
            <span class="mtt-code">${project.project_code}</span>
            <span class="mtt-dot" style="background:${color}"></span>
            <span class="mtt-status">${status}</span>
          </div>
        </div>
        <p class="mtt-title">${project.title}</p>
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

// ── Composable ─────────────────────────────────────────────────────────────────

export function useLeafletMap(
  mapContainerId: string,
  onMarkerClick: (project: MapProject) => void
) {
  const mapInstance  = ref<L.Map | null>(null)
  const layerGroup   = ref<L.LayerGroup | null>(null)
  const isMapReady   = ref(false)

  // projectId → marker for in-place icon updates (avoids clearing all markers)
  const markerMap = new Map<number, L.Marker>()

  // ── Init ───────────────────────────────────────────────────────────────────

  const initMap = () => {
    const el = document.getElementById(mapContainerId)
    if (!el) return

    const map = L.map(el, {
      center:          [12.8797, 121.774],
      zoom:            6,
      scrollWheelZoom: 'center',
      zoomControl:     false,
    })

    L.control.zoom({ position: 'bottomright' }).addTo(map)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
      maxZoom:     19,
    }).addTo(map)

    const group = L.layerGroup().addTo(map)
    layerGroup.value  = group
    mapInstance.value = map
    isMapReady.value  = true
  }

  // ── Full render — only when project LIST changes ───────────────────────────

  const renderMarkers = (projects: MapProject[], selectedId: number | null = null) => {
    if (!layerGroup.value || !mapInstance.value) return

    // Snapshot current view BEFORE clearing
    const center = mapInstance.value.getCenter()
    const zoom   = mapInstance.value.getZoom()

    layerGroup.value.clearLayers()
    markerMap.clear()

    projects.forEach((project) => {
      const { latitude, longitude } = project.location
      if (latitude === null || longitude === null) return

      const color      = project.status?.color_code ?? '#6B7280'
      const isSelected = project.id === selectedId
      const icon       = buildPinIcon(color, isSelected)
      const marker     = L.marker([latitude, longitude], { icon, zIndexOffset: isSelected ? 1000 : 0 })

      marker.on('click', () => onMarkerClick(project))

      marker.bindTooltip(buildTooltipHtml(project), {
        direction: 'top',
        offset:    [0, -4],
        className: 'mtt-wrapper',
        opacity:   1,
      })

      layerGroup.value!.addLayer(marker)
      markerMap.set(project.id, marker)
    })

    // Restore view after re-adding markers. This forces Leaflet to recalculate
    // the pixel origin from scratch, resyncing tile layer and marker transforms
    // to the same baseline. Fixes drift after panel open/close → filter.
    mapInstance.value.setView(center, zoom, { animate: false })
  }

  // ── Refresh one marker's tooltip in-place after media upload ─────────────
  // Rebuilds the tooltip HTML for a single marker using updated project data.
  // No clearLayers, no re-render — just that one marker's tooltip.

  const refreshMarkerTooltip = (project: MapProject) => {
    const marker = markerMap.get(project.id)
    if (!marker) return
    marker.unbindTooltip()
    marker.bindTooltip(buildTooltipHtml(project), {
      direction: 'top',
      offset:    [0, -4],
      className: 'mtt-wrapper',
      opacity:   1,
    })
  }

  // ── In-place selection update — does NOT clear or re-render any markers ────

  const updateMarkerSelection = (
    prevId: number | null,
    nextId: number | null,
    projects: MapProject[]
  ) => {
    const find = (id: number | null) =>
      id !== null ? (projects.find((p) => p.id === id) ?? null) : null

    // Restore previous pin
    if (prevId !== null) {
      const p = find(prevId)
      const m = markerMap.get(prevId)
      if (p && m) {
        m.setIcon(buildPinIcon(p.status?.color_code ?? '#6B7280', false))
        m.setZIndexOffset(0)
      }
    }

    // Highlight new pin
    if (nextId !== null) {
      const p = find(nextId)
      const m = markerMap.get(nextId)
      if (p && m) {
        m.setIcon(buildPinIcon(p.status?.color_code ?? '#6B7280', true))
        m.setZIndexOffset(1000)
      }
    }
  }

  // ── invalidateSize — fixes marker position drift when panel opens/closes ────
  // Uses setView instead of invalidateSize so the pixel origin is fully
  // recalculated, keeping tile and marker transforms on the same baseline.

  const invalidateSize = () => {
    setTimeout(() => {
      if (!mapInstance.value) return
      const center = mapInstance.value.getCenter()
      const zoom   = mapInstance.value.getZoom()
      mapInstance.value.setView(center, zoom, { animate: false })
    }, 320)
  }

  // ── flyTo ──────────────────────────────────────────────────────────────────

  const flyToProject = (project: MapProject) => {
    const { latitude, longitude } = project.location
    if (!mapInstance.value || latitude === null || longitude === null) return
    mapInstance.value.flyTo([latitude, longitude], 14, { animate: true, duration: 0.8 })
  }

  // ── fitBounds ─────────────────────────────────────────────────────────────

  const fitAllMarkers = (projects: MapProject[]) => {
    if (!mapInstance.value || projects.length === 0) return
    const coords = projects
      .filter((p) => p.location.latitude !== null && p.location.longitude !== null)
      .map((p) => [p.location.latitude!, p.location.longitude!] as [number, number])
    if (coords.length === 0) return
    mapInstance.value.fitBounds(L.latLngBounds(coords), { padding: [60, 60], maxZoom: 13 })
  }

  // ── Cleanup ────────────────────────────────────────────────────────────────

  onUnmounted(() => {
    mapInstance.value?.remove()
    mapInstance.value = null
    markerMap.clear()
  })

  return {
    mapInstance,
    isMapReady,
    initMap,
    renderMarkers,
    refreshMarkerTooltip,
    updateMarkerSelection,
    invalidateSize,
    flyToProject,
    fitAllMarkers,
  }
}