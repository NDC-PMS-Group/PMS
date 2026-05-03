<script lang="ts" setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { MapPin } from 'lucide-vue-next'
import { useProjectStore } from '@/store/projects'
import { usePsgcStore } from '@/store/psgc'
import type { Project, ProjectFormData, ProjectAddressFormData } from '@/types/project'

// ── Props ───────────────────────────────────────────────────────────────
const props = defineProps<{
  currentStep: number
  isEditMode:  boolean
}>()

// ── Stores ──────────────────────────────────────────────────────────────
const projectStore = useProjectStore()
const psgcStore    = usePsgcStore()
const {
  projectTypes, industries, sectors, stages, statuses,
  investmentTypes, fundingSources,
} = storeToRefs(projectStore)

// ── Form state ──────────────────────────────────────────────────────────
const form = ref<ProjectFormData>({
  title: '',
  description: '',
  project_type_id: 0,
  industry_id: 0,
  sector_id: 0,
  investment_type_id: undefined,
  funding_source_id: undefined,
  estimated_cost: undefined,
  actual_cost: undefined,
  currency: 'PHP',
  current_stage_id: 0,
  status_id: 0,
  proposal_date: undefined,
  start_date: undefined,
  target_completion_date: undefined,
  actual_completion_date: undefined,
  proponent_name: undefined,
  proponent_contact: undefined,
  proponent_email: undefined,
  is_svf: false,
})

const address = ref({
  house_number: '',
  floor: '',
  street: '',
  barangay: '',
  city_municipality: '',
  province: '',
  region: '',
  country: 'Philippines',
  zip_code: '',
  latitude:  null as number | null,
  longitude: null as number | null,
})

// PSGC cascade refs (we keep codes locally; names are mirrored into `address`)
const selectedRegionCode   = ref('')
const selectedProvinceCode = ref('')
const selectedCityCode     = ref('')
const selectedBarangayCode = ref('')

// Suppress cascade-clear watchers while we hydrate from existing data.
const hydrating = ref(false)

// ── Errors ──────────────────────────────────────────────────────────────
const errors = ref<Record<string, string>>({})

// ── Currencies ──────────────────────────────────────────────────────────
const currencies = [
  { value: 'PHP', symbol: '₱' },
  { value: 'USD', symbol: '$' },
  { value: 'EUR', symbol: '€' },
]

// ── Step error keys ─────────────────────────────────────────────────────
const stepErrorKeys: Record<number, string[]> = {
  0: ['title', 'project_type_id', 'industry_id', 'sector_id'],
  1: ['current_stage_id', 'status_id'],
  2: [],
  3: [
    'address.region', 'address.province', 'address.city_municipality',
    'address.barangay', 'address.latitude', 'address.longitude',
  ],
  4: ['proponent_email'],
}

// ── Validation ──────────────────────────────────────────────────────────
function validateStep(step: number): boolean {
  // Clear this step's errors before re-evaluating.
  stepErrorKeys[step]?.forEach((k) => delete errors.value[k])

  if (step === 0) {
    if (!form.value.title?.trim()) errors.value['title'] = 'Project title is required'
    if (!form.value.project_type_id) errors.value['project_type_id'] = 'Project type is required'
    if (!form.value.industry_id) errors.value['industry_id'] = 'Industry is required'
    if (!form.value.sector_id) errors.value['sector_id'] = 'Sector is required'
  }

  if (step === 1) {
    if (!form.value.current_stage_id) errors.value['current_stage_id'] = 'Stage is required'
    if (!form.value.status_id) errors.value['status_id'] = 'Status is required'
  }

  if (step === 3) {
    if (!address.value.region) errors.value['address.region'] = 'Region is required'
    if (!address.value.province) errors.value['address.province'] = 'Province is required'
    if (!address.value.city_municipality) errors.value['address.city_municipality'] = 'City / Municipality is required'
    if (!address.value.barangay) errors.value['address.barangay'] = 'Barangay is required'
    if (address.value.latitude === null || address.value.latitude === undefined)
      errors.value['address.latitude'] = 'Latitude is required (drop a pin on the map)'
    if (address.value.longitude === null || address.value.longitude === undefined)
      errors.value['address.longitude'] = 'Longitude is required (drop a pin on the map)'
  }

  if (step === 4) {
    if (form.value.proponent_email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.value.proponent_email)) {
      errors.value['proponent_email'] = 'Email format is invalid'
    }
  }

  return stepErrorKeys[step]?.every((k) => !errors.value[k]) ?? true
}

function stepHasErrors(step: number): boolean {
  return stepErrorKeys[step]?.some((k) => !!errors.value[k]) ?? false
}

function applyServerErrors(serverErrors: Record<string, string[] | string>) {
  const aliases: Record<string, string> = {
    stage_id: 'current_stage_id',
    current_status_id: 'status_id',
    type_id: 'project_type_id',
    project_name: 'title',
  }
  Object.entries(serverErrors).forEach(([k, v]) => {
    const key = aliases[k] || k
    const message = Array.isArray(v) ? v[0] : v
    errors.value[key] = message
  })
}

// ── Payload ─────────────────────────────────────────────────────────────
function getPayload(): ProjectFormData {
  // Build the structured-address sub-object.
  const addr: ProjectAddressFormData = {
    house_number:      address.value.house_number || undefined,
    floor:             address.value.floor || undefined,
    street:            address.value.street || undefined,
    barangay:          address.value.barangay,
    city_municipality: address.value.city_municipality,
    province:          address.value.province,
    region:            address.value.region,
    country:           address.value.country || undefined,
    zip_code:          address.value.zip_code || undefined,
    latitude:          Number(address.value.latitude),
    longitude:         Number(address.value.longitude),
  }

  return {
    ...form.value,
    address: addr,
  }
}

// ── Hydrate from existing project (edit mode) ───────────────────────────
// The API (ProjectResource) returns nested relations (project_type, status,
// proponent, location, address) instead of flat FK columns, so we read with
// fallbacks: try the *_id column first, then the relation's id.
async function loadFromProject(project: Project) {
  hydrating.value = true
  try {
    const p = project as any

    const pickId = (idField: any, relation: any): number | undefined => {
      if (idField !== undefined && idField !== null) return Number(idField)
      if (relation?.id !== undefined && relation?.id !== null) return Number(relation.id)
      return undefined
    }

    // Proponent comes back as a nested object on the resource; the legacy
    // fields are also accepted in case some endpoint exposes them flat.
    const proponentObj = p.proponent ?? {}

    form.value = {
      title:        project.title || '',
      description:  project.description || '',
      project_type_id:    pickId(p.project_type_id,    project.project_type)    ?? 0,
      industry_id:        pickId(p.industry_id,        project.industry)        ?? 0,
      sector_id:          pickId(p.sector_id,          project.sector)          ?? 0,
      investment_type_id: pickId(p.investment_type_id, project.investment_type) ?? undefined,
      funding_source_id:  pickId(p.funding_source_id,  project.funding_source)  ?? undefined,
      estimated_cost:     project.estimated_cost ?? undefined,
      actual_cost:        project.actual_cost    ?? undefined,
      currency:           project.currency || 'PHP',
      current_stage_id:   pickId(p.current_stage_id, project.current_stage) ?? 0,
      status_id:          pickId(p.status_id,        project.status)        ?? 0,
      proposal_date:           project.proposal_date           ?? undefined,
      start_date:              project.start_date              ?? undefined,
      target_completion_date:  project.target_completion_date  ?? undefined,
      actual_completion_date:  project.actual_completion_date  ?? undefined,
      project_officer_id:      pickId(p.project_officer_id, project.project_officer) ?? undefined,
      workgroup_head_id:       pickId(p.workgroup_head_id,  project.workgroup_head)  ?? undefined,
      proponent_name:    p.proponent_name    ?? proponentObj?.name    ?? undefined,
      proponent_contact: p.proponent_contact ?? proponentObj?.contact ?? undefined,
      proponent_email:   p.proponent_email   ?? proponentObj?.email   ?? undefined,
      is_svf:            project.is_svf || false,
    }

    if (project.address) {
      address.value = {
        house_number:      project.address.house_number      ?? '',
        floor:             project.address.floor             ?? '',
        street:            project.address.street            ?? '',
        barangay:          project.address.barangay          ?? '',
        city_municipality: project.address.city_municipality ?? '',
        province:          project.address.province          ?? '',
        region:            project.address.region            ?? '',
        country:           project.address.country           ?? 'Philippines',
        zip_code:          project.address.zip_code          ?? '',
        latitude:          project.address.latitude  !== null && project.address.latitude  !== undefined ? Number(project.address.latitude)  : null,
        longitude:         project.address.longitude !== null && project.address.longitude !== undefined ? Number(project.address.longitude) : null,
      }

      // Resolve cascade codes by matching descriptions back to PSGC entries.
      if (!psgcStore.regions.length) await psgcStore.fetchRegions()
      const regionMatch = psgcStore.regions.find(
        (r) => r.region_description.toLowerCase() === address.value.region.toLowerCase(),
      )
      if (regionMatch) {
        selectedRegionCode.value = regionMatch.region_code
        await psgcStore.fetchProvinces(regionMatch.region_code)

        const provinceMatch = psgcStore.provinces.find(
          (pr) => pr.province_description.toLowerCase() === address.value.province.toLowerCase(),
        )
        if (provinceMatch) {
          selectedProvinceCode.value = provinceMatch.province_code
          await psgcStore.fetchCities(provinceMatch.province_code)

          const cityMatch = psgcStore.cities.find(
            (c) => c.city_municipality_description.toLowerCase() === address.value.city_municipality.toLowerCase(),
          )
          if (cityMatch) {
            selectedCityCode.value = cityMatch.city_municipality_code
            await psgcStore.fetchBarangays(cityMatch.city_municipality_code)

            const barangayMatch = psgcStore.barangays.find(
              (b) => b.barangay_description.toLowerCase() === address.value.barangay.toLowerCase(),
            )
            if (barangayMatch) selectedBarangayCode.value = barangayMatch.barangay_code
          }
        }
      }
    } else {
      // Older project (predates project_addresses): pull legacy lat/lng from
      // ProjectResource's nested `location` object, OR from flat columns if
      // some endpoint exposes them that way. The cascade stays empty.
      const loc = p.location
      const lat = loc?.latitude  ?? p.location_lat
      const lng = loc?.longitude ?? p.location_lng
      if (lat !== null && lat !== undefined && lng !== null && lng !== undefined) {
        address.value.latitude  = Number(lat)
        address.value.longitude = Number(lng)
      }
    }
  } finally {
    // Allow a tick for watchers to settle without firing cascade-clears.
    await nextTick()
    hydrating.value = false
  }
}

// ── PSGC cascade — write display names into `address`, fetch downstream ─
watch(selectedRegionCode, async (code) => {
  const region = psgcStore.regions.find((r) => r.region_code === code)
  address.value.region = region?.region_description ?? ''

  if (!hydrating.value) {
    selectedProvinceCode.value = ''
    selectedCityCode.value     = ''
    selectedBarangayCode.value = ''
    address.value.province          = ''
    address.value.city_municipality = ''
    address.value.barangay          = ''
    if (code) await psgcStore.fetchProvinces(code)
  }
})

watch(selectedProvinceCode, async (code) => {
  const province = psgcStore.provinces.find((p) => p.province_code === code)
  if (province) address.value.province = province.province_description

  if (!hydrating.value) {
    selectedCityCode.value     = ''
    selectedBarangayCode.value = ''
    address.value.city_municipality = ''
    address.value.barangay          = ''
    if (code) await psgcStore.fetchCities(code)
  }
})

watch(selectedCityCode, async (code) => {
  const city = psgcStore.cities.find((c) => c.city_municipality_code === code)
  if (city) address.value.city_municipality = city.city_municipality_description

  if (!hydrating.value) {
    selectedBarangayCode.value = ''
    address.value.barangay = ''
    if (code) await psgcStore.fetchBarangays(code)
  }
})

watch(selectedBarangayCode, (code) => {
  const barangay = psgcStore.barangays.find((b) => b.barangay_code === code)
  if (barangay) address.value.barangay = barangay.barangay_description
})

onMounted(() => {
  psgcStore.fetchRegions()
})

// ── Composed search query (auto-fills the Maps search input) ────────────
const locationSearchQuery = ref('')

const composedAddress = computed(() => {
  const parts = [
    [address.value.house_number, address.value.street].filter(Boolean).join(' '),
    address.value.barangay,
    address.value.city_municipality,
    address.value.province,
    address.value.region,
  ].filter(Boolean)
  let out = parts.join(', ')
  if (address.value.zip_code) out += ' ' + address.value.zip_code
  return out
})

watch(composedAddress, (val) => {
  if (val) locationSearchQuery.value = val
})

// ── Google Maps ─────────────────────────────────────────────────────────
const GOOGLE_MAPS_API_KEY = 'AIzaSyBvGOC4HUPjiDuOE2yr7CwbnC4j6vsa274'
const mapRef          = ref<HTMLElement | null>(null)
const mapInitialized  = ref(false)
let googleMap:    any = null
let mapMarker:    any = null
let mapSearchBox: any = null

const loadGoogleMapsScript = (): Promise<void> =>
  new Promise((resolve, reject) => {
    const w = window as any
    if (w.google?.maps?.Map) { resolve(); return }

    const existing = document.getElementById('gmaps-script') as HTMLScriptElement | null
    if (existing) {
      let tries = 0
      const poll = setInterval(() => {
        if (w.google?.maps?.Map) { clearInterval(poll); resolve() }
        if (++tries > 150) { clearInterval(poll); reject(new Error('Timeout loading Google Maps')) }
      }, 100)
      return
    }

    const callbackName = '__gmapsInit_' + Date.now()
    ;(w as any)[callbackName] = () => { delete (w as any)[callbackName]; resolve() }
    const script = document.createElement('script')
    script.id      = 'gmaps-script'
    script.async   = true
    script.defer   = true
    script.src     = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&libraries=places&callback=${callbackName}`
    script.onerror = () => reject(new Error('Failed to load Google Maps'))
    document.head.appendChild(script)
  })

async function initMap() {
  if (!mapRef.value) return
  try {
    await loadGoogleMapsScript()
    const g = (window as any).google

    const defaultLat = address.value.latitude  ?? 14.5995
    const defaultLng = address.value.longitude ?? 120.9842
    const center = { lat: Number(defaultLat), lng: Number(defaultLng) }

    if (!mapInitialized.value) {
      googleMap = new g.maps.Map(mapRef.value, {
        zoom: 15,
        center,
        mapTypeControl:    false,
        streetViewControl: false,
        fullscreenControl: true,
        zoomControl:       true,
        styles: [{ featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }],
      })

      mapMarker = new g.maps.Marker({
        position: center,
        map:      googleMap,
        draggable: true,
        title:     'Project Location',
        animation: g.maps.Animation.DROP,
      })

      mapMarker.addListener('dragend', (event: any) => {
        address.value.latitude  = event.latLng.lat()
        address.value.longitude = event.latLng.lng()
      })

      googleMap.addListener('click', (event: any) => {
        mapMarker.setPosition(event.latLng)
        address.value.latitude  = event.latLng.lat()
        address.value.longitude = event.latLng.lng()
      })

      const searchInput = document.getElementById('projectLocationSearch') as HTMLInputElement | null
      if (searchInput) {
        mapSearchBox = new g.maps.places.SearchBox(searchInput)
        googleMap.addListener('bounds_changed', () => {
          mapSearchBox?.setBounds(googleMap.getBounds())
        })
        mapSearchBox.addListener('places_changed', () => {
          const places = mapSearchBox?.getPlaces()
          if (!places?.length) return
          const place = places[0]
          if (!place.geometry?.location) return
          const loc = place.geometry.location
          googleMap.setCenter(loc)
          mapMarker.setPosition(loc)
          address.value.latitude  = loc.lat()
          address.value.longitude = loc.lng()
        })
      }

      mapInitialized.value = true
    } else {
      g.maps.event.trigger(googleMap, 'resize')
      const lat = address.value.latitude  ?? 14.5995
      const lng = address.value.longitude ?? 120.9842
      googleMap.setCenter({ lat: Number(lat), lng: Number(lng) })
      mapMarker?.setPosition({ lat: Number(lat), lng: Number(lng) })
    }
  } catch (err) {
    console.error('Google Maps failed to load:', err)
  }
}

// Initialize the map only when the Location step becomes active.
watch(
  () => props.currentStep,
  async (step) => {
    if (step === 3) {
      await nextTick()
      await initMap()
    }
  },
)

// ── Expose ──────────────────────────────────────────────────────────────
defineExpose({
  validateStep,
  stepHasErrors,
  applyServerErrors,
  getPayload,
  loadFromProject,
})
</script>

<template>
  <div>

    <!-- ── Step 0: Basic Info ───────────────────────────────────────── -->
    <div v-show="currentStep === 0" class="p-6 space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="md:col-span-2">
          <label class="field-label required">Project Title</label>
          <input
            v-model="form.title"
            type="text"
            class="field-input"
            :class="{ 'has-error': errors.title }"
            placeholder="Enter a descriptive project title…"
          />
          <p v-if="errors.title" class="field-error">{{ errors.title }}</p>
        </div>

        <div>
          <label class="field-label required">Project Type</label>
          <select
            v-model="form.project_type_id"
            class="field-input"
            :class="{ 'has-error': errors.project_type_id }"
          >
            <option :value="0">Select type</option>
            <option v-for="t in projectTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <p v-if="errors.project_type_id" class="field-error">{{ errors.project_type_id }}</p>
        </div>

        <div>
          <label class="field-label">Investment Type</label>
          <select v-model="form.investment_type_id" class="field-input">
            <option :value="undefined">Select type</option>
            <option v-for="t in investmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="field-label">Description</label>
          <textarea
            v-model="form.description"
            class="field-input"
            rows="4"
            placeholder="Describe the project objectives, scope, and key outcomes…"
          />
        </div>

        <div>
          <label class="field-label required">Industry</label>
          <select
            v-model="form.industry_id"
            class="field-input"
            :class="{ 'has-error': errors.industry_id }"
          >
            <option :value="0">Select industry</option>
            <option v-for="i in industries" :key="i.id" :value="i.id">{{ i.name }}</option>
          </select>
          <p v-if="errors.industry_id" class="field-error">{{ errors.industry_id }}</p>
        </div>

        <div>
          <label class="field-label required">Sector</label>
          <select
            v-model="form.sector_id"
            class="field-input"
            :class="{ 'has-error': errors.sector_id }"
          >
            <option :value="0">Select sector</option>
            <option v-for="s in sectors" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <p v-if="errors.sector_id" class="field-error">{{ errors.sector_id }}</p>
        </div>

        <div>
          <label class="field-label">Funding Source</label>
          <select v-model="form.funding_source_id" class="field-input">
            <option :value="undefined">Select source</option>
            <option v-for="f in fundingSources" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ── Step 1: Status ───────────────────────────────────────────── -->
    <div v-show="currentStep === 1" class="p-6 space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
          <label class="field-label required">Current Stage</label>
          <select
            v-model="form.current_stage_id"
            class="field-input"
            :class="{ 'has-error': errors.current_stage_id }"
          >
            <option :value="0">Select stage</option>
            <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <p v-if="errors.current_stage_id" class="field-error">{{ errors.current_stage_id }}</p>
        </div>

        <div>
          <label class="field-label required">Status</label>
          <select
            v-model="form.status_id"
            class="field-input"
            :class="{ 'has-error': errors.status_id }"
          >
            <option :value="0">Select status</option>
            <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <p v-if="errors.status_id" class="field-error">{{ errors.status_id }}</p>
        </div>

        <div class="md:col-span-2">
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input v-model="form.is_svf" type="checkbox" class="rounded border-gray-300" />
            <span class="text-sm text-gray-700 dark:text-gray-200">Mark as SVF (Startup Venture Fund) project</span>
          </label>
        </div>

        <div>
          <label class="field-label">Proposal Date</label>
          <input v-model="form.proposal_date" type="date" class="field-input" />
        </div>

        <div>
          <label class="field-label">Start Date</label>
          <input v-model="form.start_date" type="date" class="field-input" />
        </div>

        <div>
          <label class="field-label">Target Completion</label>
          <input v-model="form.target_completion_date" type="date" class="field-input" />
        </div>

        <div>
          <label class="field-label">Actual Completion</label>
          <input v-model="form.actual_completion_date" type="date" class="field-input" />
        </div>
      </div>
    </div>

    <!-- ── Step 2: Financial ────────────────────────────────────────── -->
    <div v-show="currentStep === 2" class="p-6 space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
          <label class="field-label">Currency</label>
          <select v-model="form.currency" class="field-input">
            <option v-for="c in currencies" :key="c.value" :value="c.value">
              {{ c.value }} ({{ c.symbol }})
            </option>
          </select>
        </div>
        <div>
          <label class="field-label">Estimated Cost</label>
          <input v-model.number="form.estimated_cost" type="number" step="0.01" min="0" class="field-input" placeholder="0.00" />
        </div>
        <div>
          <label class="field-label">Actual Cost</label>
          <input v-model.number="form.actual_cost" type="number" step="0.01" min="0" class="field-input" placeholder="0.00" />
        </div>
      </div>
    </div>

    <!-- ── Step 3: Location (AMS-style) ─────────────────────────────── -->
    <div v-show="currentStep === 3" class="p-6 space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <div>
          <label class="field-label">House / Building Number</label>
          <input v-model="address.house_number" type="text" class="field-input" placeholder="e.g. 116" />
        </div>
        <div>
          <label class="field-label">Floor</label>
          <input v-model="address.floor" type="text" class="field-input" placeholder="e.g. 3rd Floor" />
        </div>
        <div>
          <label class="field-label">Street</label>
          <input v-model="address.street" type="text" class="field-input" placeholder="e.g. Tordesillas Street" />
        </div>
        <div>
          <label class="field-label">ZIP Code</label>
          <input v-model="address.zip_code" type="text" class="field-input" placeholder="e.g. 1227" />
        </div>

        <!-- Region -->
        <div>
          <label class="field-label required">Region</label>
          <select
            v-model="selectedRegionCode"
            class="field-input"
            :class="{ 'has-error': errors['address.region'] }"
            :disabled="psgcStore.loadingRegions"
          >
            <option value="">
              {{ psgcStore.loadingRegions ? 'Loading regions…' : 'Select Region' }}
            </option>
            <option v-for="r in psgcStore.regions" :key="r.region_code" :value="r.region_code">
              {{ r.region_description }}
            </option>
          </select>
          <p v-if="errors['address.region']" class="field-error">{{ errors['address.region'] }}</p>
        </div>

        <!-- Province -->
        <div>
          <label class="field-label required">Province</label>
          <select
            v-model="selectedProvinceCode"
            class="field-input"
            :class="{ 'has-error': errors['address.province'] }"
            :disabled="psgcStore.loadingProvinces || !selectedRegionCode"
          >
            <option value="">
              {{
                psgcStore.loadingProvinces
                  ? 'Loading provinces…'
                  : !selectedRegionCode
                    ? 'Select a region first'
                    : 'Select Province'
              }}
            </option>
            <option v-for="p in psgcStore.provinces" :key="p.province_code" :value="p.province_code">
              {{ p.province_description }}
            </option>
          </select>
          <p v-if="errors['address.province']" class="field-error">{{ errors['address.province'] }}</p>
        </div>

        <!-- City / Municipality -->
        <div>
          <label class="field-label required">City / Municipality</label>
          <select
            v-model="selectedCityCode"
            class="field-input"
            :class="{ 'has-error': errors['address.city_municipality'] }"
            :disabled="psgcStore.loadingCities || !selectedProvinceCode"
          >
            <option value="">
              {{
                psgcStore.loadingCities
                  ? 'Loading cities…'
                  : !selectedProvinceCode
                    ? 'Select a province first'
                    : 'Select City / Municipality'
              }}
            </option>
            <option v-for="c in psgcStore.cities" :key="c.city_municipality_code" :value="c.city_municipality_code">
              {{ c.city_municipality_description }}
            </option>
          </select>
          <p v-if="errors['address.city_municipality']" class="field-error">{{ errors['address.city_municipality'] }}</p>
        </div>

        <!-- Barangay -->
        <div>
          <label class="field-label required">Barangay</label>
          <select
            v-model="selectedBarangayCode"
            class="field-input"
            :class="{ 'has-error': errors['address.barangay'] }"
            :disabled="psgcStore.loadingBarangays || !selectedCityCode"
          >
            <option value="">
              {{
                psgcStore.loadingBarangays
                  ? 'Loading barangays…'
                  : !selectedCityCode
                    ? 'Select a city first'
                    : 'Select Barangay'
              }}
            </option>
            <option v-for="b in psgcStore.barangays" :key="b.barangay_code" :value="b.barangay_code">
              {{ b.barangay_description }}
            </option>
          </select>
          <p v-if="errors['address.barangay']" class="field-error">{{ errors['address.barangay'] }}</p>
        </div>

        <!-- Map -->
        <div class="md:col-span-2">
          <label class="field-label flex items-center gap-1.5 required">
            <MapPin class="h-3.5 w-3.5 text-blue-500" />
            Project Location (Map)
          </label>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            Search for the project location, drag the pin, or click on the map to set coordinates.
          </p>

          <div class="rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60">
            <input
              id="projectLocationSearch"
              v-model="locationSearchQuery"
              type="text"
              placeholder="Search location…"
              class="block w-full px-3 py-2 text-sm border-0 border-b border-gray-300 dark:border-gray-600
                     bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                     placeholder-gray-400 outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
              autocomplete="off"
            />

            <div ref="mapRef" style="height: 320px; width: 100%;" />

            <div class="flex items-center gap-6 px-4 py-2 text-sm text-gray-600 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700">
              <span class="flex items-center gap-1">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-400">Lat</span>
                <strong class="text-gray-800 dark:text-gray-200 font-mono">
                  {{ address.latitude !== null ? Number(address.latitude).toFixed(6) : '—' }}
                </strong>
              </span>
              <span class="flex items-center gap-1">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-400">Lng</span>
                <strong class="text-gray-800 dark:text-gray-200 font-mono">
                  {{ address.longitude !== null ? Number(address.longitude).toFixed(6) : '—' }}
                </strong>
              </span>
              <span v-if="address.latitude !== null" class="ml-auto flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                <MapPin class="h-3 w-3" /> Location set
              </span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-3">
            <div>
              <label class="field-label">Latitude (manual override)</label>
              <input
                v-model.number="address.latitude"
                type="number"
                step="any"
                placeholder="e.g. 14.5995"
                class="field-input font-mono"
                :class="{ 'has-error': errors['address.latitude'] }"
                @change="() => {
                  if (googleMap && mapMarker && address.latitude !== null && address.longitude !== null) {
                    const pos = { lat: Number(address.latitude), lng: Number(address.longitude) }
                    googleMap.setCenter(pos)
                    mapMarker.setPosition(pos)
                  }
                }"
              />
              <p v-if="errors['address.latitude']" class="field-error">{{ errors['address.latitude'] }}</p>
            </div>
            <div>
              <label class="field-label">Longitude (manual override)</label>
              <input
                v-model.number="address.longitude"
                type="number"
                step="any"
                placeholder="e.g. 120.9842"
                class="field-input font-mono"
                :class="{ 'has-error': errors['address.longitude'] }"
                @change="() => {
                  if (googleMap && mapMarker && address.latitude !== null && address.longitude !== null) {
                    const pos = { lat: Number(address.latitude), lng: Number(address.longitude) }
                    googleMap.setCenter(pos)
                    mapMarker.setPosition(pos)
                  }
                }"
              />
              <p v-if="errors['address.longitude']" class="field-error">{{ errors['address.longitude'] }}</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Step 4: Proponent ────────────────────────────────────────── -->
    <div v-show="currentStep === 4" class="p-6 space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
          <label class="field-label">Name</label>
          <input v-model="form.proponent_name" type="text" class="field-input" placeholder="Full name" />
        </div>
        <div>
          <label class="field-label">Contact</label>
          <input v-model="form.proponent_contact" type="text" class="field-input" placeholder="+63 XXX XXX XXXX" />
        </div>
        <div>
          <label class="field-label">Email</label>
          <input
            v-model="form.proponent_email"
            type="email"
            class="field-input"
            :class="{ 'has-error': errors.proponent_email }"
            placeholder="email@example.com"
          />
          <p v-if="errors.proponent_email" class="field-error">{{ errors.proponent_email }}</p>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.field-label {
  @apply block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1;
}
.field-label.required::after {
  content: ' *';
  color: #ef4444;
}
.field-input {
  @apply w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg
         bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400
         focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
         transition;
}
.field-input.has-error {
  @apply border-red-400 focus:ring-red-500;
}
.field-error {
  @apply mt-1 text-xs text-red-600 dark:text-red-400;
}
</style>
