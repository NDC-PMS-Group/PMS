<!-- src/components/projects/CreateEditProjectDialog.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="project-form-overlay" :class="{ 'is-dark': isDarkMode }" @mousedown.self="handleClose">
        <div class="modal-panel">
          <!-- Header -->
          <div class="modal-header">
            <div class="header-left">
              <div class="header-icon" :class="isEdit ? 'edit' : 'create'">
                <component :is="isEdit ? EditIcon : PlusCircleIcon" class="h-icon" />
              </div>
              <div>
                <h2 class="modal-title">{{ modalTitle }}</h2>
                <p class="modal-subtitle">{{ modalSubtitle }}</p>
              </div>
            </div>
            <div class="header-step-indicator">
              <span class="step-counter">Step {{ activeStep + 1 }} of {{ steps.length }}</span>
              <span class="step-counter-label">{{ steps[activeStep]?.label }}</span>
            </div>
            <button class="close-btn" @click="handleClose"><XIcon class="h-icon" /></button>
          </div>

          <!-- Horizontal Stepper -->
          <div class="stepper-bar">
            <div class="stepper-track">
              <template v-for="(step, idx) in steps" :key="step.id">
                <button
                  class="stepper-node"
                  :class="{ active: activeStep === idx, completed: idx < activeStep, error: stepHasErrors(idx) }"
                  @click="activeStep = idx"
                >
                  <span class="stepper-circle">
                    <CheckIcon v-if="idx < activeStep && !stepHasErrors(idx)" class="step-check" />
                    <AlertCircleIcon v-else-if="stepHasErrors(idx)" class="step-check" />
                    <span v-else>{{ idx + 1 }}</span>
                  </span>
                  <span class="stepper-label">{{ step.label }}</span>
                </button>
                <div v-if="idx < steps.length - 1" class="stepper-connector" :class="{ filled: idx < activeStep }"></div>
              </template>
            </div>
          </div>

          <!-- Form Body -->
          <div class="modal-body" ref="formBodyRef" @scroll="handleBodyScroll">

            <!-- ── Step 0: Basic Info ── -->
            <div v-show="activeStep === 0" class="step-content">
              <div class="section-header"><InfoIcon class="section-icon" /><h3>LOI / Proposal Intake</h3></div>
              <div class="helper-panel compact">
                Start with the minimum SOI information. The system will create the checklist, SOI route, and project work plan automatically.
              </div>
              <div class="form-grid-2">
                <div class="form-group span-2">
                  <label class="form-label required" for="project-title">Project / LOI Title</label>
                  <input id="project-title" v-model="form.title" type="text" class="form-input" :class="{ error: errors.title }" placeholder="Project name stated in the LOI or concept note" />
                  <span v-if="errors.title" class="form-error">{{ errors.title }}</span>
                </div>
                <div class="form-group">
                  <label class="form-label required" for="project-type">Project Type</label>
                  <select id="project-type" v-model="form.project_type_id" class="form-select" :class="{ error: errors.project_type_id }">
                    <option :value="0">Select type</option>
                    <option v-for="t in projectTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                  <span v-if="errors.project_type_id" class="form-error">{{ errors.project_type_id }}</span>
                </div>
                <div class="form-group">
                  <label class="form-label" for="investment-type">Investment Type</label>
                  <select id="investment-type" v-model="form.investment_type_id" class="form-select">
                    <option :value="undefined">Select type</option>
                    <option v-for="t in investmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
                <div class="form-group span-2">
                  <label class="form-label required" for="process-track">{{ isProponentAccount ? 'Proposal Type' : 'NDC Process Track' }}</label>
                  <select id="process-track" v-model="form.process_track" class="form-select">
                    <option v-for="track in visibleProcessTracks" :key="track.value" :value="track.value">{{ track.label }}</option>
                  </select>
                  <span class="field-hint">The SOI route and reviewer queue are created automatically after the proposal is saved.</span>
                </div>
                <div class="form-group span-2">
                  <label class="form-label" for="project-description">Project Concept Summary</label>
                  <textarea id="project-description" v-model="form.description" class="form-textarea" rows="4" placeholder="Description, location/market context, reason for the project, and proposed NDC participation"></textarea>
                  <span v-if="errors.description" class="form-error">{{ errors.description }}</span>
                  <span class="char-count">{{ form.description?.length || 0 }} characters</span>
                </div>
                <div class="form-group">
                  <label class="form-label required" for="project-industry">Industry</label>
                  <select id="project-industry" v-model="form.industry_id" class="form-select" :class="{ error: errors.industry_id }">
                    <option :value="0">Select industry</option>
                    <option v-for="i in industries" :key="i.id" :value="i.id">{{ i.name }}</option>
                  </select>
                  <span v-if="errors.industry_id" class="form-error">{{ errors.industry_id }}</span>
                </div>
                <div class="form-group">
                  <label class="form-label required" for="project-sector">Sector</label>
                  <select id="project-sector" v-model="form.sector_id" class="form-select" :class="{ error: errors.sector_id }">
                    <option :value="0">Select sector</option>
                    <option v-for="s in sectors" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                  <span v-if="errors.sector_id" class="form-error">{{ errors.sector_id }}</span>
                </div>
              </div>
              <!-- SVF Toggle -->
              <div v-if="!isProponentAccount" class="toggle-card" @click="form.is_svf = !form.is_svf">
                <div class="toggle-left">
                  <div class="toggle-icon"><StarIcon class="h-icon" /></div>
                  <div>
                    <p class="toggle-title">SVF Project</p>
                    <p class="toggle-desc">Adds the Investment Committee step before ManCom</p>
                  </div>
                </div>
                <div class="toggle-switch" :class="{ on: form.is_svf }"><div class="toggle-thumb"></div></div>
              </div>

              <div v-if="!isProponentAccount" class="criteria-panel">
                <div class="criteria-head">
                  <strong>NDC investment criteria</strong>
                  <span>{{ selectedCriteriaCount }}/{{ investmentCriteria.length }} selected</span>
                </div>
                <div class="criteria-grid">
                  <label v-for="criterion in investmentCriteria" :key="criterion.value" class="criteria-option">
                    <input type="checkbox" :value="criterion.value" v-model="form.ndc_investment_criteria" />
                    <span>{{ criterion.label }}</span>
                  </label>
                </div>
                <span v-if="errors.ndc_investment_criteria" class="form-error">{{ errors.ndc_investment_criteria }}</span>
              </div>
            </div>

            <!-- ── Step 1: Financial ── -->
            <div v-show="activeStep === 1" class="step-content">
              <div class="section-header"><CoinsIcon class="section-icon" /><h3>Financial Details</h3></div>
              <div class="form-grid-2">
                <div class="form-group">
                  <label class="form-label" for="estimated-cost">Estimated Cost</label>
                  <div class="input-addon-wrap">
                    <span class="input-addon">{{ form.currency }}</span>
                    <input id="estimated-cost" v-model.number="form.estimated_cost" type="number" step="0.01" min="0" class="form-input addon" placeholder="0.00" />
                  </div>
                </div>
                <div v-if="isEdit && !isProponentAccount" class="form-group">
                  <label class="form-label" for="actual-cost">Actual Cost</label>
                  <div class="input-addon-wrap">
                    <span class="input-addon">{{ form.currency }}</span>
                    <input id="actual-cost" v-model.number="form.actual_cost" type="number" step="0.01" min="0" class="form-input addon" placeholder="0.00" />
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label" for="target-raise">Target Amount to Raise</label>
                  <div class="input-addon-wrap">
                    <span class="input-addon">{{ form.currency }}</span>
                    <input id="target-raise" v-model.number="form.target_amount_to_raise" type="number" step="0.01" min="0" class="form-input addon" placeholder="0.00" />
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label" for="ndc-participation">Proposed NDC Participation</label>
                  <div class="input-addon-wrap">
                    <span class="input-addon">{{ form.currency }}</span>
                    <input id="ndc-participation" v-model.number="form.ndc_participation" type="number" step="0.01" min="0" class="form-input addon" placeholder="0.00" />
                  </div>
                </div>
                <div class="form-group">
                  <p class="form-label">Currency</p>
                  <div class="currency-selector">
                    <button v-for="c in currencies" :key="c.value" type="button" class="currency-btn" :class="{ selected: form.currency === c.value }" @click="form.currency = c.value">
                      <span class="cur-sym">{{ c.symbol }}</span>{{ c.value }}
                    </button>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label" for="funding-source">Funding Source</label>
                  <select id="funding-source" v-model="form.funding_source_id" class="form-select">
                    <option :value="undefined">Select source</option>
                    <option v-for="f in fundingSources" :key="f.id" :value="f.id">{{ f.name }}</option>
                  </select>
                </div>
              </div>
              <!-- Cost Summary -->
              <div v-if="form.estimated_cost || form.actual_cost" class="cost-summary">
                <div class="cost-row"><span>Estimated</span><span class="cv">{{ fmtPeso(form.estimated_cost || 0) }}</span></div>
                <div v-if="form.actual_cost" class="cost-row"><span>Actual</span><span class="cv">{{ fmtPeso(form.actual_cost) }}</span></div>
                <div v-if="form.estimated_cost && form.actual_cost" class="cost-row variance">
                  <span>Variance</span>
                  <span class="cv" :class="costVariance >= 0 ? 'pos' : 'neg'">{{ costVariance >= 0 ? '+' : '' }}{{ fmtPeso(costVariance) }}</span>
                </div>
              </div>

            </div>

            <!-- ── Step 2: Details ── -->
            <div v-show="activeStep === 2" class="step-content">
              <div class="section-header"><FileTextIcon class="section-icon" /><h3>Optional SOI Details</h3></div>
              <div class="helper-panel compact">
                These fields help NDC evaluate the proposal, but the official supporting documents are uploaded after the draft is created.
              </div>
              <div class="form-grid-2">
                <div class="form-group span-2"><label class="form-label" for="project-rationale">Rationale</label><textarea id="project-rationale" v-model="form.project_rationale" class="form-textarea" rows="3" placeholder="Why the project is needed and how it aligns with NDC mandate"></textarea></div>
                <div class="form-group span-2"><label class="form-label" for="company-background">Company / Proponent Background</label><textarea id="company-background" v-model="form.company_background" class="form-textarea" rows="3" placeholder="Optional summary. You may leave this blank and provide the company profile or pitch deck in Requirements."></textarea></div>
                <div class="form-group"><label class="form-label" for="target-beneficiaries">Target Beneficiaries</label><textarea id="target-beneficiaries" v-model="form.target_beneficiaries" class="form-textarea" rows="3"></textarea></div>
                <div class="form-group"><label class="form-label" for="expected-benefits">Social / Economic Benefits</label><textarea id="expected-benefits" v-model="form.expected_benefits" class="form-textarea" rows="3"></textarea></div>
                <div class="form-group span-2"><label class="form-label" for="risk-analysis">Risk Analysis</label><textarea id="risk-analysis" v-model="form.risk_analysis" class="form-textarea" rows="3" placeholder="Key risks, mitigations, constraints, and open issues"></textarea></div>
              </div>

              <details class="optional-dates">
                <summary><CalendarIcon class="section-icon" /> Optional dates</summary>
                <div class="form-grid-4">
                  <div class="form-group"><label class="form-label" for="application-date">Application Date</label><input id="application-date" v-model="form.date_of_application" type="date" class="form-input" /></div>
                  <div class="form-group"><label class="form-label" for="proposal-date">LOI / Proposal Date</label><input id="proposal-date" v-model="form.proposal_date" type="date" class="form-input" /></div>
                  <div class="form-group"><label class="form-label" for="project-start-date">Expected Start</label><input id="project-start-date" v-model="form.start_date" type="date" class="form-input" /></div>
                  <div class="form-group"><label class="form-label" for="target-completion-date">Target Completion</label><input id="target-completion-date" v-model="form.target_completion_date" type="date" class="form-input" /></div>
                </div>
              </details>

              <div class="section-header"><MapPinIcon class="section-icon" /><h3>Location</h3></div>
              <div class="form-grid-2">
                <div class="form-group">
                  <label class="form-label" for="location-region">Region</label>
                  <select id="location-region" v-model="form.location_region_code" class="form-select">
                    <option :value="undefined">Select region</option>
                    <option v-for="region in locationRegions" :key="region.code" :value="region.code">{{ region.regionName || region.name }} - {{ region.name }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="location-province">Province</label>
                  <select id="location-province" v-model="form.location_province_code" class="form-select" :disabled="!locationProvinces.length">
                    <option :value="undefined">{{ locationProvinces.length ? 'Select province' : 'No province for this region' }}</option>
                    <option v-for="province in locationProvinces" :key="province.code" :value="province.code">{{ province.name }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="location-city">City / Municipality</label>
                  <select id="location-city" v-model="form.location_city_code" class="form-select" :disabled="!locationCities.length">
                    <option :value="undefined">Select city or municipality</option>
                    <option v-for="city in locationCities" :key="city.code" :value="city.code">{{ city.name }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="location-barangay">Barangay</label>
                  <select id="location-barangay" v-model="form.location_barangay_code" class="form-select" :disabled="!locationBarangays.length">
                    <option :value="undefined">Select barangay</option>
                    <option v-for="barangay in locationBarangays" :key="barangay.code" :value="barangay.code">{{ barangay.name }}</option>
                  </select>
                </div>
                <div class="form-group span-2"><label class="form-label" for="location-street">Street / Specific Address</label><input id="location-street" v-model="form.location_street" type="text" class="form-input" placeholder="Building, street, lot, or landmark" /></div>
                <div class="form-group span-2">
                  <label class="form-label" for="location-address">Generated Address</label>
                  <div class="input-action-wrap">
                    <input id="location-address" v-model="form.location_address" type="text" class="form-input action-input" placeholder="Full project address" />
                    <button type="button" class="btn-inline" @click="geocodeAddress" :disabled="locationStore.geocoding || !form.location_address">
                      <LocateFixedIcon class="h-icon" /> {{ locationStore.geocoding ? 'Locating...' : 'Find on Map' }}
                    </button>
                  </div>
                </div>
                <div class="form-group"><label class="form-label" for="location-latitude">Latitude</label><input id="location-latitude" v-model.number="form.location_lat" type="number" step="any" class="form-input" placeholder="e.g. 14.5995" /></div>
                <div class="form-group"><label class="form-label" for="location-longitude">Longitude</label><input id="location-longitude" v-model.number="form.location_lng" type="number" step="any" class="form-input" placeholder="e.g. 120.9842" /></div>
                <div class="location-picker-card span-2">
                  <div class="picker-head">
                    <div>
                      <strong>Address Tagging</strong>
                      <span>{{ locationStatusText }}</span>
                    </div>
                    <button type="button" class="btn-map-tool" @click="centerMapOnCoordinates" :disabled="!hasCoordinates">
                      Center pin
                    </button>
                  </div>
                  <div class="map-search-row">
                    <input v-model="mapSearchQuery" type="text" class="form-input map-search-input" placeholder="Search landmark, city, or full address" @keyup.enter="searchMapLocation" />
                    <button type="button" class="btn-map-tool primary" @click="searchMapLocation" :disabled="locationStore.geocoding || !mapSearchQuery.trim()">
                      {{ locationStore.geocoding ? 'Searching...' : 'Search' }}
                    </button>
                    <button type="button" class="btn-map-tool" @click="useCurrentBrowserLocation" :disabled="usingBrowserLocation">
                      {{ usingBrowserLocation ? 'Locating...' : 'Use my location' }}
                    </button>
                  </div>
                  <div ref="locationMapEl" class="location-map"></div>
                  <div class="map-help-row">
                    <span>Best practice: choose the address from the dropdowns, search if needed, then click or drag the pin to the exact project site.</span>
                  </div>
                </div>
                <div v-if="form.location_lat && form.location_lng" class="map-preview span-2">
                  <MapPinIcon class="h-icon" />
                  <span>{{ Number(form.location_lat).toFixed(6) }}, {{ Number(form.location_lng).toFixed(6) }}</span>
                  <a :href="mapPreviewUrl" target="_blank" rel="noreferrer">Open map</a>
                </div>
              </div>
              <div class="section-header proponent-header" style="margin-top:1.5rem">
                <div class="section-title-line">
                  <UserIcon class="section-icon" />
                  <h3>Company / Proponent</h3>
                </div>
                <span v-if="isProponentAccount" class="account-pill">From registered account</span>
              </div>
              <div v-if="isProponentAccount" class="helper-panel compact">
                Your company profile will be attached to this proposal. Update your account profile if these details need to change.
              </div>
              <div class="form-grid-3">
                <div class="form-group">
                  <label class="form-label" for="proponent-name">Company / Organization</label>
                  <input id="proponent-name" v-model="form.proponent_name" type="text" class="form-input" :readonly="isProponentAccount" placeholder="Company or proponent name" />
                </div>
                <div class="form-group">
                  <label class="form-label" for="proponent-contact">Contact Number</label>
                  <input id="proponent-contact" v-model="form.proponent_contact" type="text" class="form-input" :readonly="isProponentAccount" placeholder="+63 XXX XXX XXXX" />
                </div>
                <div class="form-group">
                  <label class="form-label" for="proponent-email">Email</label>
                  <input id="proponent-email" v-model="form.proponent_email" type="email" class="form-input" :readonly="isProponentAccount" placeholder="email@example.com" />
                </div>
              </div>
              <div v-if="!isProponentAccount" class="proponent-history-panel">
                <div class="history-head">
                  <div>
                    <strong>Previous proponent projects</strong>
                    <span>Check past or existing records before accepting a new proposal.</span>
                  </div>
                  <button type="button" class="btn-history" :disabled="!canCheckProponentHistory || proponentHistoryLoading" @click="checkProponentHistory">
                    <HistoryIcon class="h-icon" />
                    {{ proponentHistoryLoading ? 'Checking...' : 'Check History' }}
                  </button>
                </div>
                <div v-if="proponentHistoryChecked" class="history-list">
                  <div v-if="!proponentHistory.length" class="history-empty">No previous projects found for this proponent.</div>
                  <div v-for="item in proponentHistory" :key="item.id" class="history-item">
                    <div>
                      <strong>{{ item.project_code }} · {{ item.title }}</strong>
                      <span>{{ item.current_stage?.name || 'No stage' }} / {{ item.status?.name || 'No status' }}</span>
                    </div>
                    <div class="history-meta">
                      <span>{{ item.project_type?.name || 'Uncategorized' }}</span>
                      <span>{{ item.estimated_cost ? fmtPeso(item.estimated_cost) : 'No amount' }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
          <div v-if="errors._form" class="form-submit-error">{{ errors._form }}</div>

          <!-- Floating Scroll Affordance Hint -->
          <div v-show="showScrollIndicator" class="scroll-more-hint" @click="scrollToBottom">
            <span>Scroll down for more fields</span>
            <ChevronDownIcon class="hint-icon" />
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <div class="footer-progress">
              <div class="footer-progress-bar" :style="{ width: `${((activeStep + 1) / steps.length) * 100}%` }"></div>
            </div>
            <div class="footer-actions">
              <button v-if="activeStep > 0" class="btn-back" type="button" @click="activeStep--">
                <ChevronLeftIcon class="h-icon" /> Back
              </button>
              <div class="footer-spacer"></div>
              <button class="btn-cancel" type="button" @click="handleClose">Cancel</button>
              <button v-if="activeStep < steps.length - 1" class="btn-next" type="button" @click="goNext">
                Next <ChevronRightIcon class="h-icon" />
              </button>
              <button v-else class="btn-submit" type="button" @click="handleSubmit" :disabled="loading">
                <span v-if="loading" class="spinner-sm"></span>
                {{ loading ? 'Saving...' : submitButtonLabel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from 'vue3-toastify';
import L from 'leaflet';
import { useProjectStore } from '@/store/projects';
import { useLocationStore } from '@/store/locations';
import { useLayoutStore } from '@/store/layout';
import { useAuthStore } from '@/store/auth';
import { SITE_MODE } from '@/app/const';
import type { Project, ProjectFinancialMetrics, ProjectFormData } from '@/types/project';
import {
  X as XIcon, PlusCircle as PlusCircleIcon, Edit as EditIcon,
  Check as CheckIcon, AlertCircle as AlertCircleIcon,
  Info as InfoIcon, Activity as ActivityIcon, Coins as CoinsIcon,
  Calendar as CalendarIcon, MapPin as MapPinIcon, User as UserIcon, Star as StarIcon, FileText as FileTextIcon,
  ChevronLeft as ChevronLeftIcon, ChevronRight as ChevronRightIcon, LocateFixed as LocateFixedIcon,
  ClipboardList as ClipboardListIcon, CheckCircle as CheckCircleIcon, ListChecks as ListChecksIcon,
  History as HistoryIcon, ChevronDown as ChevronDownIcon
} from 'lucide-vue-next';

interface Props { modelValue: boolean; project?: Project | null }
const props = defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [v: boolean];
  saved: [project: Project | null];
  close: [];
}>();

const projectStore = useProjectStore();
const locationStore = useLocationStore();
const layoutStore = useLayoutStore();
const authStore = useAuthStore();
const { projectTypes, industries, sectors, stages, statuses, investmentTypes, fundingSources } = storeToRefs(projectStore);
const {
  regions: locationRegions,
  provinces: locationProvinces,
  citiesMunicipalities: locationCities,
  barangays: locationBarangays,
} = storeToRefs(locationStore);
const isDarkMode = computed(() => {
  const htmlDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  return layoutStore.mode === SITE_MODE.DARK || htmlDark;
});

const loading = ref(false);
const activeStep = ref(0);
const errors = ref<Record<string, string>>({});

const formBodyRef = ref<HTMLElement | null>(null);
const showScrollIndicator = ref(false);

const checkScroll = () => {
  const el = formBodyRef.value;
  if (!el) {
    showScrollIndicator.value = false;
    return;
  }
  const isScrollable = el.scrollHeight > el.clientHeight;
  const isAtBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 40;
  showScrollIndicator.value = isScrollable && !isAtBottom;
};

const handleBodyScroll = () => {
  checkScroll();
};

const scrollToBottom = () => {
  const el = formBodyRef.value;
  if (el) {
    el.scrollTo({
      top: el.scrollHeight,
      behavior: 'smooth'
    });
  }
};

watch(() => props.modelValue, (val) => {
  if (val) {
    nextTick(() => {
      setTimeout(checkScroll, 350);
    });
  } else {
    showScrollIndicator.value = false;
  }
});

watch(activeStep, () => {
  nextTick(() => {
    setTimeout(checkScroll, 350);
  });
});


const locationMapEl = ref<HTMLElement | null>(null);
const mapSearchQuery = ref('');
const coordinateSourceMessage = ref('');
const usingBrowserLocation = ref(false);
let locationMap: L.Map | null = null;
let locationMarker: L.Marker | null = null;

const allProcessTracks = [
  { value: 'bdg_investment', label: 'External Investment Proposal (BDG)', audience: 'all' },
  { value: 'spg_jv', label: 'Joint Venture Proposal (SPG)', audience: 'all' },
  { value: 'spg_traditional', label: 'Traditional Equity Funding (SPG)', audience: 'all' },
  { value: 'spg_ndc_own', label: 'NDC-Owned Project (SPG)', audience: 'all' },
  { value: 'implementation_monitoring', label: 'Approved Project for Monitoring', audience: 'all' },
  { value: 'divestment', label: 'Post-Investment / Divestment', audience: 'all' },
];

const investmentCriteria = [
  { value: 'pioneering', label: 'Pioneering' },
  { value: 'developmental', label: 'Developmental' },
  { value: 'sustainable', label: 'Sustainable' },
  { value: 'inclusive', label: 'Inclusive' },
  { value: 'innovative', label: 'Innovative' },
  { value: 'board_priority', label: 'Board Priority' },
  { value: 'urgent_special', label: 'Urgent / Special' },
  { value: 'pgs_commitment', label: 'PGS Commitment' },
];

const defaultSoiFlow = [
  { title: 'Intake', copy: 'LOI, project concept, and proponent details are recorded' },
  { title: 'Requirements', copy: 'NDC sends checklist and checks complete documents' },
  { title: 'Due Diligence', copy: 'AO validates proposal, financials, risks, and site evidence' },
  { title: 'Management Review', copy: 'Workgroup, IC when SVF, and ManCom review the recommendation' },
  { title: 'Board Approval', copy: 'Board approves, rejects, or sets conditions' },
  { title: 'Fund Release', copy: 'Agreement, legal/finance checks, signatures, and release evidence' },
  { title: 'Monitoring', copy: 'Summary sheet, milestones, issues, jobs, and quarterly reports' },
];

const soiFlows: Record<string, typeof defaultSoiFlow> = {
  bdg_investment: defaultSoiFlow,
  spg_traditional: [
    { title: 'LOI / Concept', copy: 'AO receives the LOI, project concept, and pitch deck' },
    { title: 'Initial Review', copy: 'AO validates mandate fit, viability, and required response' },
    { title: 'Requirements', copy: 'SPG receives complete proposal and documentary checklist' },
    { title: 'Triangulation', copy: 'AO validates documents, feasibility, financial model, and site basis' },
    { title: 'ManCom', copy: 'AGM/AO presents recommendation for management decision' },
    { title: 'Board', copy: 'Board approves, sets conditions, defers, or rejects the proposal' },
    { title: 'Agreement & Funds', copy: 'Legal/Finance prepares agreement, signatures, and fund release' },
  ],
  spg_ndc_own: [
    { title: 'Project Concept', copy: 'AO/AGM prepares the concept based on ManCom or Board instruction' },
    { title: 'ManCom Go Signal', copy: 'Project concept is presented to ManCom for approval to proceed' },
    { title: 'Study Procurement', copy: 'TOR, MR, bidding, consultancy agreement, and study are completed' },
    { title: 'ManCom Decision', copy: 'Study results and recommendation are presented to ManCom' },
    { title: 'Board Approval', copy: 'Project is presented to the Board for approval' },
    { title: 'DED / Construction', copy: 'DED, construction bidding, award, agreement, and implementation follow' },
    { title: 'Turn-over', copy: 'Completed project is accepted and turned over to NDC operations' },
  ],
  spg_jv: [
    { title: 'JV Concept', copy: 'AO/AGM prepares the JV concept and secures ManCom go signal' },
    { title: 'Study', copy: 'Consultancy procurement and project study are completed' },
    { title: 'ManCom / Board', copy: 'JV project is presented for ManCom decision and Board approval' },
    { title: 'NEDA-ICC', copy: 'Required NEDA-ICC documents, proposal, and approval are coordinated' },
    { title: 'JV-SC', copy: 'Board approves final JVA terms and JV Selection Committee composition' },
    { title: 'Selection', copy: 'JV partner selection, recommendation, and award are processed' },
    { title: 'JVA Signing', copy: 'NOA is issued and the Joint Venture Agreement is signed' },
  ],
  implementation_monitoring: [
    { title: 'Summary Folder', copy: 'Signed documents, release records, covenants, and milestones are consolidated' },
    { title: 'Milestones', copy: 'AGM/AO sets milestone targets, drawdowns, dividends, and reporting schedule' },
    { title: 'Monitoring', copy: 'AO/AGM tracks implementation, issues, financials, and Board papers' },
    { title: 'Adjustments', copy: 'Restructuring or equity changes are routed to ManCom and Board if needed' },
    { title: 'Post-Investment', copy: 'Redemption, conversion, restructuring, and exit options are reviewed' },
  ],
  divestment: [
    { title: 'Due Diligence', copy: 'Legal and financial due diligence validates transfer terms and pricing' },
    { title: 'ManCom', copy: 'Proposed divestment terms are presented for ManCom approval' },
    { title: 'Board', copy: 'Board approves the terms and conditions of divestment' },
    { title: 'Transfer', copy: 'Documents, payment, receipts, and share/asset transfer are completed' },
  ],
};

const generatedRecords = [
  {
    title: 'SOI checklist',
    copy: 'Official BDG/SPG requirements grouped by phase',
    icon: ClipboardListIcon,
  },
  {
    title: 'Approval queue',
    copy: 'Role-based action for the next reviewer',
    icon: CheckCircleIcon,
  },
  {
    title: 'Project work plan',
    copy: 'SOI tasks and subtasks under the project',
    icon: ListChecksIcon,
  },
];

const steps = computed(() => [
  { id: 'basic', label: 'Intake' },
  { id: 'financial', label: 'Funding' },
  { id: 'details', label: 'SOI Details' },
]);

const currencies = [
  { value: 'PHP', symbol: '₱' },
  { value: 'USD', symbol: '$' },
  { value: 'EUR', symbol: '€' },
];

const isEdit = computed(() => !!props.project);
const costVariance = computed(() => (form.value.actual_cost || 0) - (form.value.estimated_cost || 0));
const selectedCriteriaCount = computed(() => new Set(form.value.ndc_investment_criteria || []).size);
const isProponentAccount = computed(() => {
  const roleName = authStore.user?.role?.name?.toLowerCase();
  const roleId = Number((authStore.user as any)?.default_role_id ?? authStore.user?.role?.id);
  return roleName === 'proponent' || roleId === 7;
});
const visibleProcessTracks = computed(() =>
  allProcessTracks.filter((track) => track.audience === 'all' || !isProponentAccount.value)
);
const workflowStartForTrack = (track?: string) => {
  switch (track) {
    case 'implementation_monitoring':
      return { stage: 'Implementation & Monitoring', status: 'Monitoring Ongoing' };
    case 'divestment':
      return { stage: 'Divestment', status: 'For Divestment' };
    case 'spg_ndc_own':
      return { stage: 'Intake', status: 'LOI Received' };
    default:
      return { stage: 'Intake', status: 'Draft' };
  }
};

const stageIdByName = (name: string) =>
  stages.value.find((stage) => stage.name === name)?.id || stages.value[0]?.id || 1;

const statusIdByName = (name: string) =>
  statuses.value.find((status) => status.name === name)?.id || statuses.value[0]?.id || 1;

const defaultStageId = computed(() => stages.value.find((stage) => stage.name === 'Intake')?.id || stages.value[0]?.id || 1);
const defaultStatusId = computed(() =>
  statuses.value.find((status) => status.name === 'Draft')?.id ||
  statuses.value.find((status) => status.name === 'LOI Received')?.id ||
  statuses.value.find((status) => status.name === 'Submitted')?.id ||
  statuses.value[0]?.id ||
  1
);
const selectedProcessTrackLabel = computed(() =>
  allProcessTracks.find((track) => track.value === form.value.process_track)?.label || 'NDC SOI'
);
const selectedRouteName = computed(() => {
  if (form.value.is_svf) return 'SVF Investment Committee route';

  switch (form.value.process_track) {
    case 'spg_traditional':
      return 'SPG traditional equity route';
    case 'spg_ndc_own':
      return 'SPG NDC-owned project route';
    case 'spg_jv':
      return 'SPG joint venture route';
    case 'implementation_monitoring':
      return 'Implementation monitoring route';
    case 'divestment':
      return 'Divestment route';
    default:
      return 'BDG investment route';
  }
});
const modalTitle = computed(() => {
  if (isEdit.value) return 'Edit Project';
  return isProponentAccount.value ? 'New Proposal Draft' : 'Create New Project';
});
const modalSubtitle = computed(() => {
  if (isEdit.value) return `Editing ${props.project?.project_code}`;
  return isProponentAccount.value
    ? 'Save the proposal first, then upload and submit the complete file package'
    : 'Create an internal NDC project record';
});
const submitButtonLabel = computed(() => {
  if (isEdit.value) return 'Save Changes';
  if (['bdg_investment', 'spg_traditional', 'spg_jv'].includes(form.value.process_track || '') || form.value.is_svf) {
    return 'Create Draft';
  }
  return 'Create Project';
});
const initialStageName = computed(() => stages.value.find((stage) => stage.id === form.value.current_stage_id)?.name || 'Intake');
const initialStatusName = computed(() => statuses.value.find((status) => status.id === form.value.status_id)?.name || 'LOI Received');
const isFiniteCoordinate = (value: number | string | null | undefined) => {
  if (value === null || value === undefined || value === '') return false;
  return Number.isFinite(Number(value));
};
const hasCoordinates = computed(() =>
  isFiniteCoordinate(form.value.location_lat) && isFiniteCoordinate(form.value.location_lng)
);
const mapPreviewUrl = computed(() => {
  const lat = form.value.location_lat;
  const lng = form.value.location_lng;
  return lat && lng ? `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=15/${lat}/${lng}` : '#';
});
const locationStatusText = computed(() => {
  if (coordinateSourceMessage.value) return coordinateSourceMessage.value;
  if (hasCoordinates.value) return 'Coordinates are set. Drag the pin or click the map to refine the exact project site.';
  return 'No pin set yet. Search the address or click the map to set coordinates.';
});

const proponentHistory = ref<Project[]>([]);
const proponentHistoryLoading = ref(false);
const proponentHistoryChecked = ref(false);
const canCheckProponentHistory = computed(() =>
  Boolean(form.value.proponent_name?.trim() || form.value.proponent_email?.trim())
);

const locationMarkerIcon = L.divIcon({
  className: 'project-location-pin',
  html: '<span></span>',
  iconSize: [28, 28],
  iconAnchor: [14, 28],
});

const defaultMapCenter: [number, number] = [12.8797, 121.774];

const currentCoordinates = (): [number, number] | null => {
  if (!hasCoordinates.value) return null;
  return [Number(form.value.location_lat), Number(form.value.location_lng)];
};

const setCoordinatesFromMap = (lat: number, lng: number, source = 'Pin set from map') => {
  form.value.location_lat = Number(lat.toFixed(6));
  form.value.location_lng = Number(lng.toFixed(6));
  coordinateSourceMessage.value = source;
  updateLocationMarker(false);
};

const updateLocationMarker = (pan = false) => {
  if (!locationMap) return;
  const coords = currentCoordinates();
  if (!coords) {
    locationMarker?.remove();
    locationMarker = null;
    return;
  }

  if (!locationMarker) {
    locationMarker = L.marker(coords, {
      icon: locationMarkerIcon,
      draggable: true,
      autoPan: true,
    }).addTo(locationMap);
    locationMarker.on('dragend', () => {
      const position = locationMarker?.getLatLng();
      if (position) setCoordinatesFromMap(position.lat, position.lng, 'Pin adjusted. These coordinates will be saved with the project.');
    });
  } else {
    locationMarker.setLatLng(coords);
  }

  if (pan) locationMap.setView(coords, Math.max(locationMap.getZoom(), 14));
};

const initLocationMap = async () => {
  if (activeStep.value !== 2 || !props.modelValue) return;
  await nextTick();
  if (!locationMapEl.value) return;

  const coords = currentCoordinates();
  if (!locationMap) {
    locationMap = L.map(locationMapEl.value, {
      center: coords || defaultMapCenter,
      zoom: coords ? 14 : 6,
      zoomControl: false,
      scrollWheelZoom: 'center',
    });
    L.control.zoom({ position: 'bottomright' }).addTo(locationMap);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19,
    }).addTo(locationMap);
    locationMap.on('click', (event: L.LeafletMouseEvent) => {
      setCoordinatesFromMap(event.latlng.lat, event.latlng.lng, 'Pin set from the map. These coordinates will be saved with the project.');
    });
  }

  setTimeout(() => {
    locationMap?.invalidateSize();
    updateLocationMarker(Boolean(coords));
  }, 80);
};

const destroyLocationMap = () => {
  locationMarker = null;
  locationMap?.remove();
  locationMap = null;
};

const centerMapOnCoordinates = async () => {
  await initLocationMap();
  updateLocationMarker(true);
};

// ── Form State ──
const defaultFinancialMetrics = (): ProjectFinancialMetrics => ({
  jobs_generated_direct: null,
  jobs_generated_indirect: null,
  retained_jobs: null,
  projected_revenue: null,
  actual_revenue: null,
  dividend_remittance: null,
  gcg_relevance: false,
  gcg_score: null,
  reportable_to_gcg: false,
  is_reportable: false,
  monitoring_frequency: null,
  reporting_period: '',
  monitoring_indicators: '',
  gcg_metrics: '',
  social_impact_notes: '',
});

const normalizeFinancialMetrics = (value?: ProjectFinancialMetrics | null): ProjectFinancialMetrics => ({
  ...defaultFinancialMetrics(),
  ...(value || {}),
});

const defaultForm = (): ProjectFormData => ({
  title: '', description: '', process_track: 'bdg_investment', project_type_id: 0,
  industry_id: 0, sector_id: 0, currency: 'PHP',
  current_stage_id: defaultStageId.value, status_id: defaultStatusId.value, is_svf: false,
  ndc_investment_criteria: ['developmental', 'sustainable', 'inclusive'],
});

const form = ref<ProjectFormData>(defaultForm());
watch(form, () => {
  nextTick(checkScroll);
}, { deep: true });
const soiFlow = computed(() => soiFlows[form.value.process_track || 'bdg_investment'] || defaultSoiFlow);

watch(() => props.modelValue, (val) => {
  if (val) {
    activeStep.value = 0;
    errors.value = {};
    if (props.project) loadProjectData();
    else form.value = defaultForm();
    syncInitialWorkflowFields();
    fillProponentFromAccount();
    mapSearchQuery.value = form.value.location_address || '';
    coordinateSourceMessage.value = hasCoordinates.value
      ? 'Coordinates are set. Drag the pin or click the map to refine the exact project site.'
      : '';
    proponentHistory.value = [];
    proponentHistoryChecked.value = false;
  } else {
    destroyLocationMap();
  }
});

const loadProjectData = () => {
  if (!props.project) return;
  const p = props.project;
  form.value = {
    title: p.title,
    description: p.description || '',
    process_track: p.process_track || 'bdg_investment',
    date_of_application: p.date_of_application ?? undefined,
    project_type_id: p.project_type_id,
    industry_id: p.industry_id,
    sector_id: p.sector_id,
    investment_type_id: p.investment_type_id ?? undefined,
    funding_source_id: p.funding_source_id ?? undefined,
    estimated_cost: p.estimated_cost ?? undefined,
    actual_cost: p.actual_cost ?? undefined,
    target_amount_to_raise: p.target_amount_to_raise ?? undefined,
    ndc_participation: p.ndc_participation ?? undefined,
    ndc_investment_criteria: p.ndc_investment_criteria || [],
    project_rationale: p.project_rationale ?? undefined,
    company_background: p.company_background ?? undefined,
    target_beneficiaries: p.target_beneficiaries ?? undefined,
    expected_benefits: p.expected_benefits ?? undefined,
    risk_analysis: p.risk_analysis ?? undefined,
    financial_metrics: normalizeFinancialMetrics(p.financial_metrics),
    issues_problems: p.issues_problems ?? undefined,
    next_steps: p.next_steps ?? undefined,
    post_investment_strategy: p.post_investment_strategy ?? undefined,
    currency: p.currency || 'PHP',
    current_stage_id: p.current_stage_id,
    status_id: p.status_id,
    proposal_date: p.proposal_date ?? undefined,
    start_date: p.start_date ?? undefined,
    target_completion_date: p.target_completion_date ?? undefined,
    actual_completion_date: p.actual_completion_date ?? undefined,
    location_address: p.location_address ?? undefined,
    location_region_code: p.location_region_code ?? undefined,
    location_region_name: p.location_region_name ?? undefined,
    location_province_code: p.location_province_code ?? undefined,
    location_province_name: p.location_province_name ?? undefined,
    location_city_code: p.location_city_code ?? undefined,
    location_city_name: p.location_city_name ?? undefined,
    location_barangay_code: p.location_barangay_code ?? undefined,
    location_barangay_name: p.location_barangay_name ?? undefined,
    location_street: p.location_street ?? undefined,
    location_lat: p.location_lat ?? undefined,
    location_lng: p.location_lng ?? undefined,
    proponent_name: p.proponent_name ?? undefined,
    proponent_contact: p.proponent_contact ?? undefined,
    proponent_email: p.proponent_email ?? undefined,
    is_svf: p.is_svf || false,
  };
};

const fillProponentFromAccount = () => {
  const user = authStore.user;
  if (!user || !isProponentAccount.value) return;

  form.value.proponent_name = user.organization_name || user.full_name;
  form.value.proponent_email = user.email;
  form.value.proponent_contact = user.phone_number || undefined;

  if (!visibleProcessTracks.value.some((track) => track.value === form.value.process_track)) {
    form.value.process_track = 'bdg_investment';
  }
};

watch(() => props.modelValue, async (val) => {
  if (val) {
    await locationStore.fetchRegions();
    if (form.value.location_region_code) {
      await hydrateLocationOptions();
    }
  }
});

watch(activeStep, (step) => {
  if (step === 2) {
    initLocationMap();
  }
});

watch(() => [form.value.location_lat, form.value.location_lng], () => {
  updateLocationMarker();
});

watch([stages, statuses], () => {
  if (props.modelValue && !isEdit.value) {
    syncInitialWorkflowFields();
  }
});

watch(() => form.value.process_track, () => {
  if (props.modelValue && !isEdit.value) {
    syncInitialWorkflowFields();
  }
});

const syncInitialWorkflowFields = () => {
  if (!isEdit.value) {
    const start = workflowStartForTrack(form.value.process_track);
    form.value.current_stage_id = stageIdByName(start.stage);
    form.value.status_id = statusIdByName(start.status);
  }
};

watch(() => form.value.location_region_code, async (code, oldCode) => {
  const selected = locationRegions.value.find(r => r.code === code);
  form.value.location_region_name = selected ? `${selected.regionName || selected.name} - ${selected.name}` : undefined;
  if (oldCode && code !== oldCode) {
    form.value.location_province_code = undefined;
    form.value.location_province_name = undefined;
    form.value.location_city_code = undefined;
    form.value.location_city_name = undefined;
    form.value.location_barangay_code = undefined;
    form.value.location_barangay_name = undefined;
  }
  if (!code) return;
  const provinces = await locationStore.fetchProvinces(code);
  if (!provinces.length) {
    await locationStore.fetchCitiesMunicipalities({ regionCode: code });
  }
  syncAddressFromParts();
});

watch(() => form.value.location_province_code, async (code, oldCode) => {
  const selected = locationProvinces.value.find(p => p.code === code);
  form.value.location_province_name = selected?.name;
  if (oldCode && code !== oldCode) {
    form.value.location_city_code = undefined;
    form.value.location_city_name = undefined;
    form.value.location_barangay_code = undefined;
    form.value.location_barangay_name = undefined;
  }
  if (code) {
    await locationStore.fetchCitiesMunicipalities({ provinceCode: code });
  }
  syncAddressFromParts();
});

watch(() => form.value.location_city_code, async (code, oldCode) => {
  const selected = locationCities.value.find(c => c.code === code);
  form.value.location_city_name = selected?.name;
  if (oldCode && code !== oldCode) {
    form.value.location_barangay_code = undefined;
    form.value.location_barangay_name = undefined;
  }
  if (code) {
    await locationStore.fetchBarangays(code);
  }
  syncAddressFromParts();
});

watch(() => form.value.location_barangay_code, () => {
  const selected = locationBarangays.value.find(b => b.code === form.value.location_barangay_code);
  form.value.location_barangay_name = selected?.name;
  syncAddressFromParts();
});

watch(() => form.value.location_street, () => syncAddressFromParts());

watch(() => form.value.location_address, (address, previousAddress) => {
  if (!mapSearchQuery.value.trim() || mapSearchQuery.value === previousAddress) {
    mapSearchQuery.value = address || '';
  }
});

watch(() => [form.value.proponent_name, form.value.proponent_email], () => {
  proponentHistory.value = [];
  proponentHistoryChecked.value = false;
});

const hydrateLocationOptions = async () => {
  const regionCode = form.value.location_region_code;
  if (!regionCode) return;
  const provinces = await locationStore.fetchProvinces(regionCode);
  if (form.value.location_province_code) {
    await locationStore.fetchCitiesMunicipalities({ provinceCode: form.value.location_province_code });
  } else if (!provinces.length) {
    await locationStore.fetchCitiesMunicipalities({ regionCode });
  }
  if (form.value.location_city_code) {
    await locationStore.fetchBarangays(form.value.location_city_code);
  }
};

const syncAddressFromParts = () => {
  const parts = [
    form.value.location_street,
    form.value.location_barangay_name,
    form.value.location_city_name,
    form.value.location_province_name,
    form.value.location_region_name,
    'Philippines',
  ].filter(Boolean);

  if (parts.length > 1) {
    form.value.location_address = parts.join(', ');
  }
};

const locationQueryCandidates = (primary?: string) => {
  const candidates = [
    primary,
    form.value.location_address,
    [
      form.value.location_street,
      form.value.location_barangay_name,
      form.value.location_city_name,
      form.value.location_province_name,
      'Philippines',
    ].filter(Boolean).join(', '),
    [
      form.value.location_barangay_name,
      form.value.location_city_name,
      form.value.location_province_name,
      'Philippines',
    ].filter(Boolean).join(', '),
    [
      form.value.location_city_name,
      form.value.location_province_name,
      'Philippines',
    ].filter(Boolean).join(', '),
    [
      form.value.location_province_name,
      'Philippines',
    ].filter(Boolean).join(', '),
  ].filter((value): value is string => Boolean(value?.trim()));

  return [...new Set(candidates.map(value => value.trim()))];
};

const applyGeocodeResult = async (result: { latitude: number; longitude: number; display_name?: string }, source: string) => {
  form.value.location_lat = Number(Number(result.latitude).toFixed(6));
  form.value.location_lng = Number(Number(result.longitude).toFixed(6));
  coordinateSourceMessage.value = source;
  await centerMapOnCoordinates();
};

const findCoordinatesForCandidates = async (candidates: string[]) => {
  for (const candidate of candidates) {
    const result = await locationStore.geocode(candidate);
    if (result) return { result, candidate };
  }
  return null;
};

const geocodeAddress = async () => {
  syncAddressFromParts();
  const found = await findCoordinatesForCandidates(locationQueryCandidates(form.value.location_address));
  if (!found) {
    coordinateSourceMessage.value = 'Address lookup did not find coordinates. Please click the project site on the map.';
    toast.error(locationStore.error || 'Unable to geocode this address. You can still click the map to set the pin.');
    return;
  }
  await applyGeocodeResult(found.result, `Pin placed from address lookup: ${found.candidate}`);
  mapSearchQuery.value = found.candidate;
  toast.success('Coordinates updated from address lookup');
};

const searchMapLocation = async () => {
  const query = mapSearchQuery.value.trim();
  if (!query) return;

  const found = await findCoordinatesForCandidates(locationQueryCandidates(query));
  if (!found) {
    coordinateSourceMessage.value = 'Search did not find coordinates. Click the map to set the exact project site.';
    toast.error(locationStore.error || 'No map result found. Try a nearby landmark, city, or click the map.');
    return;
  }

  await applyGeocodeResult(found.result, `Pin placed from map search: ${found.candidate}`);
  toast.success('Map pin updated');
};

const useCurrentBrowserLocation = async () => {
  if (!navigator.geolocation) {
    toast.error('Current location is not available in this browser.');
    return;
  }

  usingBrowserLocation.value = true;
  navigator.geolocation.getCurrentPosition(
    async (position) => {
      usingBrowserLocation.value = false;
      await initLocationMap();
      setCoordinatesFromMap(
        position.coords.latitude,
        position.coords.longitude,
        'Pin set from your browser location. Drag it if the project site is nearby but not exact.',
      );
      await centerMapOnCoordinates();
    },
    () => {
      usingBrowserLocation.value = false;
      toast.error('Unable to access current location. Search the address or click the map instead.');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
  );
};

const checkProponentHistory = async () => {
  if (!canCheckProponentHistory.value) return;

  proponentHistoryLoading.value = true;
  try {
    proponentHistory.value = await projectStore.fetchProponentHistory({
      proponent_name: form.value.proponent_name,
      proponent_email: form.value.proponent_email,
      exclude_project_id: props.project?.id,
    });
    proponentHistoryChecked.value = true;
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to check proponent history');
  } finally {
    proponentHistoryLoading.value = false;
  }
};

// ── Validation ──
// Returns errors for a given step (does NOT mutate global errors)
const validateStep = (step: number): Record<string, string> => {
  const e: Record<string, string> = {};
  if (step === 0) {
    if (!form.value.title?.trim()) e.title = 'Project title is required';
    if (!form.value.description?.trim()) e.description = 'Project concept summary is required';
    if (!form.value.project_type_id || form.value.project_type_id === 0) e.project_type_id = 'Project type is required';
    if (!form.value.industry_id || form.value.industry_id === 0) e.industry_id = 'Industry is required';
    if (!form.value.sector_id || form.value.sector_id === 0) e.sector_id = 'Sector is required';
    if (['bdg_investment', 'spg_traditional', 'spg_jv'].includes(form.value.process_track || '') && selectedCriteriaCount.value < 3) {
      e.ndc_investment_criteria = 'Select at least three NDC investment criteria.';
    }
  }
  return e;
};

// Which error keys belong to each step
const stepErrorKeys: Record<number, string[]> = {
  0: ['title', 'description', 'project_type_id', 'industry_id', 'sector_id', 'ndc_investment_criteria'],
  1: [],
  2: [],
};

const stepHasErrors = (idx: number) =>
  stepErrorKeys[idx]?.some(k => !!errors.value[k]) ?? false;

const normalizeServerErrorKey = (key: string): string => {
  const aliases: Record<string, string> = {
    stage_id: 'current_stage_id',
    current_status_id: 'status_id',
    type_id: 'project_type_id',
    project_name: 'title',
  };
  return aliases[key] || key;
};

const goNext = () => {
  const stepErrs = validateStep(activeStep.value);
  // Clear previous errors for this step, then assign new ones
  stepErrorKeys[activeStep.value].forEach(k => delete errors.value[k]);
  Object.assign(errors.value, stepErrs);
  if (Object.keys(stepErrs).length === 0) {
    activeStep.value++;
  }
};

const handleSubmit = async () => {
  // Validate all steps
  errors.value = {};
  const allErrors: Record<string, string> = {};
  for (let i = 0; i < steps.value.length; i++) {
    Object.assign(allErrors, validateStep(i));
  }
  errors.value = allErrors;

  if (Object.keys(errors.value).length > 0) {
    // Jump to the first step that has errors
    for (let i = 0; i < steps.value.length; i++) {
      if (stepHasErrors(i)) { activeStep.value = i; return; }
    }
    return;
  }

  loading.value = true;
  try {
    let savedProject: Project | null = null;
    if (isEdit.value && props.project) {
      savedProject = await projectStore.updateProject(props.project.id, form.value);
    } else {
      savedProject = await projectStore.createProject(form.value);
    }
    emit('saved', savedProject);
    handleClose();
  } catch (err: any) {
    // Server validation errors
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors;
      let firstServerError = '';
      // Normalise: server may return arrays
      Object.entries(serverErrors).forEach(([k, v]) => {
        const normalizedKey = normalizeServerErrorKey(k);
        const message = Array.isArray(v) ? (v as string[])[0] : (v as string);
        errors.value[normalizedKey] = message;
        if (!firstServerError && message) firstServerError = message;
      });
      if (firstServerError) {
        errors.value._form = firstServerError;
        toast.error(firstServerError);
      }
      // Jump to first offending step
      for (let i = 0; i < steps.value.length; i++) {
        if (stepHasErrors(i)) { activeStep.value = i; break; }
      }
    } else {
      const message =
        err?.response?.data?.error ||
        err?.response?.data?.message ||
        err?.message ||
        'Failed to save project';
      errors.value._form = message;
      toast.error(message);
    }
  } finally {
    loading.value = false;
  }
};

const handleClose = () => {
  destroyLocationMap();
  emit('update:modelValue', false);
  emit('close');
};

onBeforeUnmount(() => {
  destroyLocationMap();
});

const statusClass = (name: string) => {
  const map: Record<string, string> = { Active: 's-active', 'On Hold': 's-hold', Completed: 's-done', Cancelled: 's-cancelled' };
  return map[name] || '';
};

const fmtPeso = (n: number) =>
  `₱${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(n)}`;
</script>

<style scoped>
/* ─── CSS Variables ─── */
.project-form-overlay {
  --m-bg: #ffffff;
  --m-overlay: rgba(15, 23, 42, 0.72);
  --m-border: #cbd5e1;
  --m-subtle: #f8fafc;
  --m-muted: #e2e8f0;
  --m-text: #0f172a;
  --m-text-2: #334155;
  --m-text-3: #64748b;
  --m-text-in: #1e293b;
  --m-accent: #2563eb;
  --m-accent-bg: #eff6ff;
  --m-footer: #f8fafc;
  --m-input-bg: #ffffff;
  --m-select-bg: #ffffff;
}
:global(.dark) .project-form-overlay,
.project-form-overlay.is-dark {
  --m-bg: #111827;
  --m-overlay: rgba(2, 6, 23, 0.88);
  --m-border: rgba(255, 255, 255, 0.12);
  --m-subtle: rgba(17, 24, 39, 0.55);
  --m-muted: rgba(30, 41, 59, 0.55);
  --m-text: #f1f5f9;
  --m-text-2: #94a3b8;
  --m-text-3: #64748b;
  --m-text-in: #e2e8f0;
  --m-accent: #3b82f6;
  --m-accent-bg: rgba(15, 23, 42, 0.72);
  --m-footer: rgba(15, 23, 42, 0.72);
  --m-input-bg: rgba(15, 23, 42, 0.72);
  --m-select-bg: rgba(15, 23, 42, 0.72);
}

/* Overlay */
.project-form-overlay {
  position: fixed; inset: 0; z-index: 9999;
  background: var(--m-overlay);
  backdrop-filter: blur(3px);
  display: flex; align-items: center; justify-content: center;
  padding: 1rem; overflow-y: auto;
}

/* Panel */
.modal-panel {
  background: var(--m-bg);
  border: 1px solid rgba(148, 163, 184, 0.45);
  border-radius: 1rem;
  box-shadow: 0 24px 64px rgba(0,0,0,0.22);
  width: 100%; max-width: 920px;
  max-height: 92vh;
  display: flex; flex-direction: column;
  overflow: hidden;
  position: relative;
}
:global(.dark) .modal-panel {
  background: rgba(15, 23, 42, 0.92);
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: 0 24px 64px rgba(0,0,0,0.6);
}
.project-form-overlay.is-dark .modal-panel {
  background: rgba(15, 23, 42, 0.92);
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: 0 24px 64px rgba(0,0,0,0.6);
}

/* ─── Header ─── */
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.375rem 1.5rem 1rem;
  border-bottom: 1px solid var(--m-border);
  flex-shrink: 0;
}
.header-left { display: flex; align-items: center; gap: 0.875rem; }
.header-icon { width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.header-icon.create { background: #eff6ff; color: #2563eb; }
.header-icon.edit { background: #f0fdf4; color: #16a34a; }
:global(.dark) .header-icon.create { background: #1e3a5f; color: #60a5fa; }
:global(.dark) .header-icon.edit { background: #14532d; color: #4ade80; }
.modal-title { font-size: 1.1875rem; font-weight: 700; color: var(--m-text); margin: 0 0 0.125rem; }
.modal-subtitle { font-size: 0.78rem; color: var(--m-text-3); margin: 0; }
.close-btn { width: 2.25rem; height: 2.25rem; display: flex; align-items: center; justify-content: center; border: none; background: var(--m-muted); border-radius: 0.5rem; cursor: pointer; color: var(--m-text-2); transition: all 0.15s; flex-shrink: 0; }
.close-btn:hover { background: #fee2e2; color: #dc2626; }
.h-icon { width: 1.0625rem; height: 1.0625rem; }

/* ─── Header Step Indicator ─── */
.header-step-indicator { display: flex; flex-direction: column; align-items: flex-end; gap: 0.15rem; }
.step-counter { font-size: 0.72rem; font-weight: 800; color: var(--m-accent); text-transform: uppercase; letter-spacing: 0.06em; }
.step-counter-label { font-size: 0.78rem; font-weight: 600; color: var(--m-text-3); }

/* ─── Horizontal Stepper ─── */
.stepper-bar { padding: 1.125rem 1.5rem 0.875rem; border-bottom: 1px solid var(--m-border); flex-shrink: 0; overflow-x: auto; scrollbar-width: none; }
.stepper-bar::-webkit-scrollbar { display: none; }
.stepper-track { display: flex; align-items: flex-start; justify-content: center; gap: 0; }
.stepper-node { display: flex; flex-direction: column; align-items: center; gap: 0.45rem; background: none; border: none; cursor: pointer; transition: all 0.15s; padding: 0; min-width: 5rem; }
.stepper-circle { width: 2.25rem; height: 2.25rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; font-weight: 700; transition: all 0.25s; border: 2.5px solid var(--m-muted); background: var(--m-bg); color: var(--m-text-3); position: relative; z-index: 1; }
.stepper-circle > span { position: relative; z-index: 1; }
.stepper-node.active .stepper-circle { border-color: var(--m-accent); background: var(--m-accent); color: white; box-shadow: 0 0 0 4px rgba(37,99,235,0.15); }
.stepper-node.completed .stepper-circle { border-color: #22c55e; background: #22c55e; color: white; }
.stepper-node.error .stepper-circle { border-color: #ef4444; background: #ef4444; color: white; }
.stepper-node:hover:not(.active):not(.completed):not(.error) .stepper-circle { border-color: var(--m-text-3); }
.step-check { width: 0.85rem; height: 0.85rem; color: white; }
.stepper-label { font-size: 0.72rem; font-weight: 600; color: var(--m-text-3); white-space: nowrap; transition: color 0.15s; }
.stepper-node.active .stepper-label { color: var(--m-accent); font-weight: 700; }
.stepper-node.completed .stepper-label { color: #22c55e; }
.stepper-node.error .stepper-label { color: #ef4444; }
.stepper-connector { flex: 1; height: 2.5px; background: var(--m-muted); margin-top: 1.0625rem; min-width: 2rem; transition: background 0.3s; }
.stepper-connector.filled { background: #22c55e; }

/* ─── Body ─── */
.modal-body { flex: 1; overflow-y: auto; padding: 1.375rem 1.5rem; overscroll-behavior: contain; }
.step-content { animation: stepIn 0.18s ease; }
@keyframes stepIn { from{opacity:0;transform:translateX(6px)} to{opacity:1;transform:translateX(0)} }

.section-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.125rem; }
.section-header h3 { font-size: 0.9375rem; font-weight: 700; color: var(--m-text); margin: 0; }
.section-icon { width: 1rem; height: 1rem; color: var(--m-accent); flex-shrink: 0; }
.proponent-header { justify-content: space-between; gap: 0.75rem; }
.section-title-line { display: flex; align-items: center; gap: 0.5rem; min-width: 0; }
.account-pill { flex-shrink: 0; border: 1px solid rgba(37,99,235,0.25); border-radius: 999px; background: rgba(37,99,235,0.1); color: var(--m-accent); padding: 0.25rem 0.55rem; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
.helper-panel { border: 1px solid var(--m-border); border-radius: 0.75rem; background: var(--m-subtle); color: var(--m-text-2); font-size: 0.8rem; line-height: 1.45; padding: 0.8rem 0.9rem; margin: -0.25rem 0 1rem; }
.helper-panel.compact { padding: 0.7rem 0.85rem; }

.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.span-2 { grid-column: span 2; }
.form-group { display: flex; flex-direction: column; gap: 0.3rem; }

.form-label { font-size: 0.78rem; font-weight: 600; color: var(--m-text-2); }
.form-label.required::after { content: ' *'; color: #ef4444; }
.field-hint { color: var(--m-text-3); font-size: 0.72rem; line-height: 1.4; }
.char-count { font-size: 0.68rem; color: var(--m-text-3); text-align: right; }

.form-input {
  padding: 0.5875rem 0.8125rem; border: 1.5px solid var(--m-border); border-radius: 0.5rem;
  font-size: 0.875rem; color: var(--m-text-in); background: var(--m-input-bg);
  transition: all 0.15s; width: 100%; box-sizing: border-box;
}
.form-input:focus { outline: none; border-color: var(--m-accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-input.error { border-color: #ef4444; }
.form-input[readonly] { background: var(--m-subtle); color: var(--m-text-2); cursor: default; }
.form-input::placeholder,
.form-textarea::placeholder { color: #94a3b8; opacity: 1; }

.form-textarea { padding: 0.5875rem 0.8125rem; border: 1.5px solid var(--m-border); border-radius: 0.5rem; font-size: 0.875rem; color: var(--m-text-in); background: var(--m-input-bg); resize: vertical; font-family: inherit; transition: all 0.15s; width: 100%; box-sizing: border-box; }
.form-textarea:focus { outline: none; border-color: var(--m-accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.form-select {
  appearance: none; padding: 0.5875rem 2.25rem 0.5875rem 0.8125rem;
  border: 1.5px solid var(--m-border); border-radius: 0.5rem;
  font-size: 0.875rem; color: var(--m-text-in); background: var(--m-select-bg);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 0.75rem center;
  cursor: pointer; transition: all 0.15s; width: 100%;
}
.form-select:focus { outline: none; border-color: var(--m-accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-select.error { border-color: #ef4444; }
.form-error { font-size: 0.73rem; color: #ef4444; font-weight: 500; }
.form-submit-error {
  margin: 0 1.5rem 0.75rem;
  padding: 0.625rem 0.75rem;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 500;
}
:global(.dark) .form-submit-error {
  border-color: #7f1d1d;
  background: #450a0a;
  color: #fca5a5;
}

.optional-dates {
  margin: 1rem 0 1.25rem;
  border: 1px solid var(--m-border);
  border-radius: 0.75rem;
  background: var(--m-subtle);
  overflow: hidden;
}
.optional-dates summary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.8rem 0.9rem;
  color: var(--m-text);
  font-size: 0.82rem;
  font-weight: 800;
  cursor: pointer;
}
.optional-dates .form-grid-4 {
  padding: 0 0.9rem 0.9rem;
}

/* Toggle Card */
.toggle-card { display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.125rem; background: var(--m-subtle); border: 1.5px solid var(--m-border); border-radius: 0.75rem; cursor: pointer; transition: all 0.15s; margin-top: 1rem; }
.toggle-card:hover { border-color: var(--m-accent); background: var(--m-accent-bg); }
.toggle-left { display: flex; align-items: center; gap: 0.75rem; }
.toggle-icon { width: 2.125rem; height: 2.125rem; background: #fffbeb; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; color: #f59e0b; }
:global(.dark) .toggle-icon { background: #2d1f08; color: #fbbf24; }
.toggle-title { font-size: 0.875rem; font-weight: 600; color: var(--m-text); margin: 0 0 0.125rem; }
.toggle-desc { font-size: 0.73rem; color: var(--m-text-3); margin: 0; }
.toggle-switch { width: 2.75rem; height: 1.5rem; background: var(--m-border); border-radius: 999px; position: relative; transition: background 0.2s; flex-shrink: 0; }
.toggle-switch.on { background: var(--m-accent); }
.toggle-thumb { position: absolute; top: 0.25rem; left: 0.25rem; width: 1rem; height: 1rem; background: white; border-radius: 50%; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.toggle-switch.on .toggle-thumb { transform: translateX(1.25rem); }
.criteria-panel { margin-top: 1rem; padding: 1rem; border: 1.5px solid var(--m-border); border-radius: .75rem; background: var(--m-subtle); }
.criteria-head { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; color: var(--m-text); font-size: .875rem; }
.criteria-head span { color: var(--m-text-3); font-weight: 700; }
.criteria-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .5rem; }
.criteria-option { display: flex; align-items: center; gap: .45rem; padding: .65rem .7rem; border: 1px solid var(--m-border); border-radius: .55rem; color: var(--m-text); background: var(--m-bg); font-size: .78rem; font-weight: 700; }
.criteria-option input { accent-color: var(--m-accent); }

.pill-selector { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.pill-option { padding: 0.5rem 0.875rem; background: var(--m-subtle); border: 1.5px solid var(--m-border); border-radius: 0.5rem; font-size: 0.8rem; font-weight: 500; color: var(--m-text-2); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.pill-selector:not(.readonly) .pill-option:hover { border-color: var(--m-accent); color: var(--m-accent); background: var(--m-accent-bg); }
.pill-option.selected { background: var(--m-accent); border-color: var(--m-accent); color: white; }
.pill-selector.readonly .pill-option { cursor: default; opacity: 0.6; }
.pill-selector.readonly .pill-option.selected { opacity: 1; }

.workflow-notice { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--m-accent-bg); border: 1px solid var(--m-accent); border-radius: 0.5rem; color: var(--m-accent); font-size: 0.8rem; margin-bottom: 0.5rem; }
.wn-icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }

.flow-grid { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); gap: 1rem; }
.flow-card { border: 1.5px solid var(--m-border); border-radius: 0.85rem; background: var(--m-subtle); padding: 1rem; min-width: 0; }
.flow-card.primary { background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(20,184,166,0.11)); border-color: rgba(37,99,235,0.32); }
.flow-card-head { display: flex; flex-direction: column; gap: .2rem; margin-bottom: .85rem; }
.flow-card-head strong { color: var(--m-text); font-size: .98rem; line-height: 1.35; }
.flow-kicker { color: var(--m-text-3); font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.flow-facts { display: grid; gap: .55rem; }
.flow-facts div { display: flex; justify-content: space-between; gap: .8rem; align-items: center; padding: .55rem .65rem; border: 1px solid var(--m-border); border-radius: .6rem; background: var(--m-input-bg); }
.flow-facts span { color: var(--m-text-3); font-size: .73rem; font-weight: 700; }
.flow-facts strong { color: var(--m-text); font-size: .8rem; text-align: right; }
.generated-list { display: grid; gap: .55rem; }
.generated-item { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .65rem; align-items: center; padding: .62rem .65rem; border: 1px solid var(--m-border); border-radius: .65rem; background: var(--m-input-bg); }
.generated-icon { width: 1.1rem; height: 1.1rem; color: var(--m-accent); }
.generated-item strong { display: block; color: var(--m-text); font-size: .8rem; line-height: 1.2; }
.generated-item span { display: block; color: var(--m-text-3); font-size: .72rem; line-height: 1.35; margin-top: .1rem; }
.flow-lane { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .6rem; margin: 1rem 0 1.35rem; }
.flow-node { position: relative; min-width: 0; padding: .8rem .7rem; border: 1px solid var(--m-border); border-radius: .75rem; background: var(--m-input-bg); }
.flow-node.active { border-color: rgba(37,99,235,0.5); box-shadow: inset 0 0 0 1px rgba(37,99,235,0.18); }
.node-dot { width: 1.45rem; height: 1.45rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--m-muted); color: var(--m-text-2); font-size: .68rem; font-weight: 900; margin-bottom: .55rem; }
.flow-node.active .node-dot { background: var(--m-accent); color: white; }
.flow-node strong { display: block; color: var(--m-text); font-size: .76rem; line-height: 1.25; margin-bottom: .25rem; }
.flow-node span { color: var(--m-text-3); font-size: .68rem; line-height: 1.35; display: block; }
.section-header.compact { margin-top: .25rem; margin-bottom: .85rem; }

.status-opt { display: flex; align-items: center; gap: 0.4rem; }
.status-dot { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.s-active.selected { background: #dcfce7; border-color: #22c55e; color: #15803d; }
.s-hold.selected { background: #fef3c7; border-color: #f59e0b; color: #b45309; }
.s-done.selected { background: #dbeafe; border-color: #3b82f6; color: #1d4ed8; }
.s-cancelled.selected { background: #fee2e2; border-color: #ef4444; color: #b91c1c; }
:global(.dark) .s-active.selected { background: #14532d; border-color: #22c55e; color: #86efac; }
:global(.dark) .s-hold.selected { background: #422006; border-color: #f59e0b; color: #fcd34d; }
:global(.dark) .s-done.selected { background: #1e3a5f; border-color: #3b82f6; color: #93c5fd; }
:global(.dark) .s-cancelled.selected { background: #450a0a; border-color: #ef4444; color: #fca5a5; }

/* Input Addon */
.input-addon-wrap { display: flex; align-items: stretch; }
.input-addon { padding: 0 0.75rem; background: var(--m-muted); border: 1.5px solid var(--m-border); border-right: none; border-radius: 0.5rem 0 0 0.5rem; font-size: 0.78rem; font-weight: 700; color: var(--m-text-3); display: flex; align-items: center; white-space: nowrap; }
.form-input.addon { border-radius: 0 0.5rem 0.5rem 0; flex: 1; }
.input-action-wrap { display: flex; align-items: stretch; gap: 0.5rem; }
.action-input { flex: 1; min-width: 0; }
.btn-inline { display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem; padding: 0 0.85rem; min-height: 2.625rem; border: 1.5px solid var(--m-accent); border-radius: 0.5rem; background: var(--m-accent); color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: opacity 0.15s; }
.btn-inline:disabled { opacity: 0.55; cursor: not-allowed; }
.location-picker-card { border: 1px solid var(--m-border); border-radius: 0.75rem; background: var(--m-subtle); overflow: hidden; }
.picker-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.8rem 0.9rem; border-bottom: 1px solid var(--m-border); }
.picker-head strong { display: block; color: var(--m-text); font-size: 0.84rem; margin-bottom: 0.15rem; }
.picker-head span { display: block; color: var(--m-text-3); font-size: 0.72rem; line-height: 1.35; }
.btn-map-tool { flex-shrink: 0; border: 1px solid var(--m-border); border-radius: 0.5rem; background: var(--m-input-bg); color: var(--m-text-2); font-size: 0.74rem; font-weight: 800; padding: 0.45rem 0.7rem; cursor: pointer; }
.btn-map-tool:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-map-tool:not(:disabled):hover { border-color: var(--m-accent); color: var(--m-accent); }
.btn-map-tool.primary { background: var(--m-accent); border-color: var(--m-accent); color: #fff; }
.btn-map-tool.primary:not(:disabled):hover { color: #fff; opacity: 0.9; }
.map-search-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 0.55rem;
  padding: 0.75rem 0.9rem;
  border-bottom: 1px solid var(--m-border);
  background: var(--m-input-bg);
}
.map-search-input { min-width: 0; }
.location-map { height: 17rem; width: 100%; background: #dbeafe; }
:global(.dark) .location-map, .project-form-overlay.is-dark .location-map { background: #0f172a; }
.map-help-row {
  padding: 0.65rem 0.9rem;
  border-top: 1px solid var(--m-border);
  background: var(--m-subtle);
  color: var(--m-text-3);
  font-size: 0.72rem;
  line-height: 1.45;
}
:deep(.project-location-pin) { background: transparent; border: none; }
:deep(.project-location-pin span) {
  display: block;
  position: relative;
  width: 1.45rem;
  height: 1.45rem;
  border-radius: 50% 50% 50% 0;
  background: var(--m-accent);
  border: 3px solid #fff;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.32);
  transform: rotate(-45deg);
}
:deep(.project-location-pin span::after) {
  content: '';
  position: absolute;
  inset: 0.32rem;
  border-radius: 50%;
  background: #fff;
}
.map-preview { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 0.875rem; border: 1px solid var(--m-border); border-radius: 0.5rem; background: var(--m-subtle); color: var(--m-text-2); font-size: 0.8rem; }
.map-preview a { margin-left: auto; color: var(--m-accent); font-weight: 700; text-decoration: none; }

/* Currency */
.currency-selector { display: flex; gap: 0.5rem; }
.currency-btn { flex: 1; padding: 0.5rem 0.25rem; background: var(--m-subtle); border: 1.5px solid var(--m-border); border-radius: 0.5rem; font-size: 0.78rem; font-weight: 500; color: var(--m-text-2); cursor: pointer; transition: all 0.15s; display: flex; flex-direction: column; align-items: center; gap: 0.125rem; }
.cur-sym { font-size: 1rem; font-weight: 700; }
.currency-btn.selected { background: var(--m-accent); border-color: var(--m-accent); color: white; }
.currency-btn:hover:not(.selected) { border-color: var(--m-accent); color: var(--m-accent); background: var(--m-accent-bg); }

/* Cost Summary */
.cost-summary { background: var(--m-subtle); border: 1px solid var(--m-border); border-radius: 0.75rem; padding: 0.875rem 1rem; margin-top: 1rem; }
.cost-row { display: flex; justify-content: space-between; align-items: center; padding: 0.3rem 0; font-size: 0.875rem; color: var(--m-text-2); }
.cost-row + .cost-row { border-top: 1px solid var(--m-border); }
.cost-row.variance { font-weight: 700; }
.cv { font-weight: 600; color: var(--m-text); }
.cv.pos { color: #16a34a; }
.cv.neg { color: #dc2626; }
.proponent-history-panel {
  margin-top: 1rem;
  border: 1px solid var(--m-border);
  border-radius: 0.8rem;
  background: var(--m-subtle);
  overflow: hidden;
}
.history-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 0.95rem;
  border-bottom: 1px solid var(--m-border);
}
.history-head strong { display: block; color: var(--m-text); font-size: 0.85rem; }
.history-head span { display: block; color: var(--m-text-3); font-size: 0.72rem; line-height: 1.35; margin-top: 0.1rem; }
.btn-history {
  flex-shrink: 0;
  min-height: 2.2rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  border: 1px solid rgba(37,99,235,0.28);
  border-radius: 0.55rem;
  background: var(--m-accent-bg);
  color: var(--m-accent);
  font-size: 0.76rem;
  font-weight: 800;
  padding: 0 0.75rem;
  cursor: pointer;
}
.btn-history:disabled { opacity: 0.55; cursor: not-allowed; }
.history-list { display: grid; gap: 0.5rem; padding: 0.85rem 0.95rem; }
.history-empty {
  border: 1px dashed var(--m-border);
  border-radius: 0.65rem;
  padding: 0.85rem;
  color: var(--m-text-3);
  font-size: 0.78rem;
  text-align: center;
}
.history-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.8rem;
  align-items: center;
  border: 1px solid var(--m-border);
  border-radius: 0.65rem;
  background: var(--m-input-bg);
  padding: 0.72rem 0.78rem;
}
.history-item strong { display: block; color: var(--m-text); font-size: 0.8rem; line-height: 1.3; }
.history-item span { display: block; color: var(--m-text-3); font-size: 0.72rem; line-height: 1.35; margin-top: 0.15rem; }
.history-meta { text-align: right; }
.history-meta span { color: var(--m-text-2); font-weight: 700; }

/* ─── Footer ─── */
.modal-footer {
  border-top: 1px solid var(--m-border);
  background: var(--m-footer);
  flex-shrink: 0;
}
.footer-progress { height: 3px; background: var(--m-muted); position: relative; }
.footer-progress-bar { height: 100%; background: linear-gradient(90deg, var(--m-accent), #06b6d4); border-radius: 0 3px 3px 0; transition: width 0.35s ease; }
.footer-actions { display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem 1.5rem; }
.footer-spacer { flex: 1; }

.btn-back { display: flex; align-items: center; gap: 0.375rem; padding: 0.5875rem 1rem; background: var(--m-bg); border: 1.5px solid var(--m-border); border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--m-text-2); cursor: pointer; transition: all 0.15s; }
.btn-back:hover { border-color: var(--m-text-3); }
.btn-cancel { padding: 0.5875rem 1rem; background: var(--m-bg); border: 1.5px solid var(--m-border); border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--m-text-2); cursor: pointer; transition: all 0.15s; }
.btn-cancel:hover { border-color: var(--m-text-3); }
.btn-next { display: flex; align-items: center; gap: 0.375rem; padding: 0.5875rem 1.25rem; background: #0f172a; border: none; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; color: white; cursor: pointer; transition: all 0.15s; }
.btn-next:hover { background: #1e293b; }
:global(.dark) .btn-next { background: var(--m-text-3); }
:global(.dark) .btn-next:hover { background: var(--m-text-2); }
.btn-submit { display: flex; align-items: center; gap: 0.5rem; padding: 0.5875rem 1.375rem; background: var(--m-accent); border: none; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; color: white; cursor: pointer; transition: all 0.15s; }
.btn-submit:hover:not(:disabled) { opacity: 0.88; }
.btn-submit:disabled { opacity: 0.55; cursor: not-allowed; }

.spinner-sm { width: 1rem; height: 1rem; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Transition ─── */
.modal-enter-active { animation: ovIn 0.22s ease; }
.modal-leave-active { animation: ovIn 0.18s ease reverse; }
@keyframes ovIn { from{opacity:0} to{opacity:1} }
.modal-enter-active .modal-panel { animation: panIn 0.26s cubic-bezier(0.34,1.5,0.64,1); }
.modal-leave-active .modal-panel { animation: panIn 0.18s ease reverse; }
@keyframes panIn { from{transform:scale(0.94) translateY(14px)} to{transform:scale(1) translateY(0)} }

@media (max-width: 640px) {
  .form-grid-2 { grid-template-columns: 1fr; }
  .form-grid-3 { grid-template-columns: 1fr 1fr; }
  .form-grid-4 { grid-template-columns: 1fr 1fr; }
  .flow-grid { grid-template-columns: 1fr; }
  .flow-lane { grid-template-columns: 1fr; }
  .span-2 { grid-column: span 1; }
  .criteria-grid { grid-template-columns: 1fr; }
  .input-action-wrap { flex-direction: column; }
  .btn-inline { min-height: 2.5rem; }
  .picker-head { align-items: flex-start; flex-direction: column; }
  .btn-map-tool { width: 100%; }
  .location-map { height: 14rem; }
  .history-head { align-items: stretch; flex-direction: column; }
  .btn-history { width: 100%; }
  .history-item { grid-template-columns: 1fr; }
  .history-meta { text-align: left; }
  .stepper-label { display: none; }
  .stepper-connector { min-width: 1rem; }
}

/* Floating Scroll Hint style */
.scroll-more-hint {
  position: absolute;
  bottom: 80px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--m-accent, #2563eb);
  color: white;
  padding: 0.5rem 1.125rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
  cursor: pointer;
  z-index: 50;
  border: 1px solid rgba(255, 255, 255, 0.15);
  animation: fadeIn 0.22s ease-out;
  user-select: none;
}
.scroll-more-hint:hover {
  background: #1d4ed8;
}
.hint-icon {
  width: 0.9rem;
  height: 0.9rem;
  animation: bounce 1.2s infinite;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translate(-50%, 6px); }
  to { opacity: 1; transform: translate(-50%, 0); }
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(4px); }
}
</style>
