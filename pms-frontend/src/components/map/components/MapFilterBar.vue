<template>
  <div class="relative flex-shrink-0">
    <!-- Show/Hide tab anchored at the bottom of the bar -->
    <div class="absolute left-1/2 -translate-x-1/2 bottom-0 translate-y-full z-10">
      <button
        class="flex items-center gap-1.5 px-4 py-1 text-xs font-medium
               bg-white dark:bg-gray-800 border border-t-0 border-gray-200 dark:border-gray-700
               rounded-b-xl text-gray-600 dark:text-gray-400
               hover:text-blue-600 shadow-sm transition-colors"
        @click="mapStore.toggleFilters()"
      >
        <SlidersHorizontal class="h-3 w-3" />
        <span>{{ mapStore.filtersVisible ? 'Hide' : 'Show' }} Filters</span>
        <ChevronUp
          class="h-3 w-3 transition-transform duration-200"
          :class="{ 'rotate-180': !mapStore.filtersVisible }"
        />
      </button>
    </div>

    <!-- Bar body -->
    <Transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-show="mapStore.filtersVisible"
        class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm
               border-b border-gray-200 dark:border-gray-700 shadow-sm"
      >
        <!-- Row 1: status pills + Fit-All + count + reset -->
        <div class="px-4 pt-3 pb-2 flex flex-wrap items-center gap-2">
          <!-- All -->
          <button
            class="filter-pill"
            :class="{ active: !activeStatusId }"
            @click="emit('update:statusId', null)"
          >
            All
            <span class="pill-count">{{ total }}</span>
          </button>

          <!-- Status pills -->
          <button
            v-for="status in statuses"
            :key="status.id"
            class="filter-pill"
            :class="{ active: activeStatusId === status.id }"
            @click="emit('update:statusId', activeStatusId === status.id ? null : status.id)"
          >
            <span
              class="inline-block w-2 h-2 rounded-full flex-shrink-0"
              :style="{ backgroundColor: status.color_code }"
            />
            {{ status.name }}
            <span class="pill-count">{{ countByStatus[status.name] ?? 0 }}</span>
          </button>

          <!-- Divider -->
          <div class="h-5 w-px bg-gray-200 dark:bg-gray-600 mx-1" />

          <!-- Fit all -->
          <button
            class="filter-pill"
            title="Fit all markers in view"
            @click="emit('fit-all')"
          >
            <Maximize2 class="w-3.5 h-3.5" />
            Fit All
          </button>

          <div class="flex-1" />

          <!-- Reset -->
          <button
            v-if="hasActiveFilters"
            class="flex items-center gap-1 px-2.5 py-1.5 text-xs text-red-600
                   border border-red-300 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition"
            @click="onReset"
          >
            <RotateCcw class="h-3 w-3" /> Reset
          </button>
        </div>

        <!-- Row 2: location cascade -->
        <div
          class="px-4 pb-3 flex flex-wrap items-center gap-2
                 border-t border-gray-100 dark:border-gray-800 pt-2"
        >
          <MapPin class="h-3.5 w-3.5 text-blue-500 flex-shrink-0" />
          <span class="text-xs text-gray-500 dark:text-gray-400 font-medium flex-shrink-0">
            Location:
          </span>

          <!-- Region -->
          <select
            :value="mapStore.location.regionCode"
            :class="selectClass"
            :disabled="psgcStore.loadingRegions"
            @change="onRegionChange"
          >
            <option value="">
              {{ psgcStore.loadingRegions ? 'Loading…' : 'All Regions' }}
            </option>
            <option
              v-for="r in psgcStore.regions"
              :key="r.region_code"
              :value="r.region_code"
            >
              {{ r.region_description }}
            </option>
          </select>

          <ChevronDown class="h-3 w-3 text-gray-300 flex-shrink-0" />

          <!-- Province -->
          <select
            :value="mapStore.location.provinceCode"
            :class="!mapStore.location.regionCode ? disabledSelectClass : selectClass"
            :disabled="!mapStore.location.regionCode || psgcStore.loadingProvinces"
            @change="onProvinceChange"
          >
            <option value="">
              {{
                !mapStore.location.regionCode
                  ? 'Select Region first'
                  : psgcStore.loadingProvinces
                    ? 'Loading…'
                    : 'All Provinces'
              }}
            </option>
            <option
              v-for="p in psgcStore.provinces"
              :key="p.province_code"
              :value="p.province_code"
            >
              {{ p.province_description }}
            </option>
          </select>

          <ChevronDown class="h-3 w-3 text-gray-300 flex-shrink-0" />

          <!-- City -->
          <select
            :value="mapStore.location.cityCode"
            :class="!mapStore.location.provinceCode ? disabledSelectClass : selectClass"
            :disabled="!mapStore.location.provinceCode || psgcStore.loadingCities"
            @change="onCityChange"
          >
            <option value="">
              {{
                !mapStore.location.provinceCode
                  ? 'Select Province first'
                  : psgcStore.loadingCities
                    ? 'Loading…'
                    : 'All Cities/Municipalities'
              }}
            </option>
            <option
              v-for="c in psgcStore.cities"
              :key="c.city_municipality_code"
              :value="c.city_municipality_code"
            >
              {{ c.city_municipality_description }}
            </option>
          </select>

          <ChevronDown class="h-3 w-3 text-gray-300 flex-shrink-0" />

          <!-- Barangay -->
          <select
            :value="mapStore.location.barangayCode"
            :class="!mapStore.location.cityCode ? disabledSelectClass : selectClass"
            :disabled="!mapStore.location.cityCode || psgcStore.loadingBarangays"
            @change="onBarangayChange"
          >
            <option value="">
              {{
                !mapStore.location.cityCode
                  ? 'Select City first'
                  : psgcStore.loadingBarangays
                    ? 'Loading…'
                    : 'All Barangays'
              }}
            </option>
            <option
              v-for="b in psgcStore.barangays"
              :key="b.barangay_code"
              :value="b.barangay_code"
            >
              {{ b.barangay_description }}
            </option>
          </select>

          <!-- Breadcrumb chip -->
          <div
            v-if="mapStore.locationBreadcrumb"
            class="ml-2 flex items-center gap-1.5 px-2.5 py-1
                   bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700
                   rounded-full"
          >
            <MapPin class="h-3 w-3 text-blue-500 flex-shrink-0" />
            <span class="text-xs text-blue-700 dark:text-blue-300 font-medium">
              {{ mapStore.locationBreadcrumb }}
            </span>
            <button
              class="text-blue-400 hover:text-blue-600 ml-1"
              @click="onClearLocation"
            >
              <X class="h-3 w-3" />
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  SlidersHorizontal, ChevronUp, ChevronDown,
  MapPin, X, Maximize2, RotateCcw,
} from 'lucide-vue-next'
import { useMapStore } from '@/store/map'
import { usePsgcStore } from '@/store/psgc'

defineProps<{
  statuses:       { id: number; name: string; color_code: string }[]
  countByStatus:  Record<string, number>
  activeStatusId: number | null
  total:          number
}>()

const emit = defineEmits<{
  'update:statusId': [id: number | null]
  'fit-all': []
}>()

const mapStore  = useMapStore()
const psgcStore = usePsgcStore()

// Bootstrap regions on mount
psgcStore.fetchRegions()

const hasActiveFilters = computed(
  () => !!mapStore.hasActiveFilters || mapStore.hasLocationFilter,
)

// ── Cascade handlers ─────────────────────────────────────────────────────
async function onRegionChange(e: Event) {
  const sel  = e.target as HTMLSelectElement
  const code = sel.value
  const name = sel.options[sel.selectedIndex]?.text ?? ''
  psgcStore.clearProvinceDown()
  if (!code) { mapStore.clearLocation(); return }
  mapStore.setRegion(code, name)
  await psgcStore.fetchProvinces(code)
}

async function onProvinceChange(e: Event) {
  const sel  = e.target as HTMLSelectElement
  const code = sel.value
  const name = sel.options[sel.selectedIndex]?.text ?? ''
  psgcStore.clearCityDown()
  if (!code) {
    mapStore.setRegion(mapStore.location.regionCode, mapStore.location.regionName)
    return
  }
  mapStore.setProvince(code, name)
  await psgcStore.fetchCities(code)
}

async function onCityChange(e: Event) {
  const sel  = e.target as HTMLSelectElement
  const code = sel.value
  const name = sel.options[sel.selectedIndex]?.text ?? ''
  psgcStore.clearBarangays()
  if (!code) {
    mapStore.setProvince(mapStore.location.provinceCode, mapStore.location.provinceName)
    return
  }
  mapStore.setCity(code, name)
  await psgcStore.fetchBarangays(code)
}

function onBarangayChange(e: Event) {
  const sel  = e.target as HTMLSelectElement
  const code = sel.value
  const name = sel.options[sel.selectedIndex]?.text ?? ''
  if (!code) {
    mapStore.setCity(mapStore.location.cityCode, mapStore.location.cityName)
    return
  }
  mapStore.setBarangay(code, name)
}

function onClearLocation() {
  mapStore.clearLocation()
  psgcStore.clearProvinceDown()
}

function onReset() {
  emit('update:statusId', null)
  onClearLocation()
}

const selectClass         = 'text-xs border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition min-w-[130px]'
const disabledSelectClass = `${selectClass} opacity-50 cursor-not-allowed`
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
