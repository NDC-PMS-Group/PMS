<!-- src/components/projects/CreateEditProjectDialog.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" :class="{ 'is-dark': isDarkMode }" @mousedown.self="handleClose">
        <div class="modal-panel">
          <!-- Header -->
          <div class="modal-header">
            <div class="header-left">
              <div class="header-icon" :class="isEdit ? 'edit' : 'create'">
                <component :is="isEdit ? EditIcon : PlusCircleIcon" class="h-icon" />
              </div>
              <div>
                <h2 class="modal-title">{{ isEdit ? 'Edit Project' : 'Create New Project' }}</h2>
                <p class="modal-subtitle">{{ isEdit ? `Editing ${props.project?.project_code}` : 'Fill in the details below' }}</p>
              </div>
            </div>
            <button class="close-btn" @click="handleClose"><XIcon class="h-icon" /></button>
          </div>

          <!-- Step Tabs -->
          <div class="step-tabs">
            <button
              v-for="(step, idx) in steps" :key="step.id"
              class="step-tab"
              :class="{ active: activeStep === idx, completed: idx < activeStep, error: stepHasErrors(idx) }"
              @click="activeStep = idx"
            >
              <span class="step-num">
                <CheckIcon v-if="idx < activeStep && !stepHasErrors(idx)" class="step-check" />
                <AlertCircleIcon v-else-if="stepHasErrors(idx)" class="step-check" />
                <span v-else>{{ idx + 1 }}</span>
              </span>
              <span class="step-label">{{ step.label }}</span>
            </button>
          </div>

          <!-- Form Body -->
          <div class="modal-body">

            <!-- ── Step 0: Basic Info ── -->
            <div v-show="activeStep === 0" class="step-content">
              <div class="section-header"><InfoIcon class="section-icon" /><h3>Basic Information</h3></div>
              <div class="form-grid-2">
                <div class="form-group span-2">
                  <label class="form-label required" for="project-title">Project Title</label>
                  <input id="project-title" v-model="form.title" type="text" class="form-input" :class="{ error: errors.title }" placeholder="Enter a descriptive project title..." />
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
                  <label class="form-label" for="project-description">Description</label>
                  <textarea id="project-description" v-model="form.description" class="form-textarea" rows="4" placeholder="Describe the project objectives, scope, and key outcomes..."></textarea>
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
              <div class="toggle-card" @click="form.is_svf = !form.is_svf">
                <div class="toggle-left">
                  <div class="toggle-icon"><StarIcon class="h-icon" /></div>
                  <div>
                    <p class="toggle-title">SVF Project</p>
                    <p class="toggle-desc">Mark as a Special Venture Fund project</p>
                  </div>
                </div>
                <div class="toggle-switch" :class="{ on: form.is_svf }"><div class="toggle-thumb"></div></div>
              </div>
            </div>

            <!-- ── Step 1: Status ── -->
            <div v-show="activeStep === 1" class="step-content">
              <div class="section-header"><ActivityIcon class="section-icon" /><h3>Status & Workflow</h3></div>
              <div class="form-grid-2">
                <div class="form-group">
                  <p class="form-label required">Current Stage</p>
                  <div class="pill-selector">
                    <button v-for="stage in stages" :key="stage.id" type="button"
                      class="pill-option" :class="{ selected: form.current_stage_id === stage.id }"
                      @click="form.current_stage_id = stage.id">
                      {{ stage.name }}
                    </button>
                  </div>
                  <span v-if="errors.current_stage_id" class="form-error">{{ errors.current_stage_id }}</span>
                </div>
                <div class="form-group">
                  <p class="form-label required">Status</p>
                  <div class="pill-selector">
                    <button v-for="status in statuses" :key="status.id" type="button"
                      class="pill-option status-opt" :class="[{ selected: form.status_id === status.id }, statusClass(status.name)]"
                      @click="form.status_id = status.id">
                      <span class="status-dot"></span>{{ status.name }}
                    </button>
                  </div>
                  <span v-if="errors.status_id" class="form-error">{{ errors.status_id }}</span>
                </div>
              </div>
              <!-- Timeline -->
              <div class="section-header" style="margin-top:1.5rem"><CalendarIcon class="section-icon" /><h3>Timeline</h3></div>
              <div class="form-grid-4">
                <div class="form-group"><label class="form-label" for="proposal-date">Proposal Date</label><input id="proposal-date" v-model="form.proposal_date" type="date" class="form-input" /></div>
                <div class="form-group"><label class="form-label" for="project-start-date">Start Date</label><input id="project-start-date" v-model="form.start_date" type="date" class="form-input" /></div>
                <div class="form-group"><label class="form-label" for="target-completion-date">Target Completion</label><input id="target-completion-date" v-model="form.target_completion_date" type="date" class="form-input" /></div>
                <div class="form-group"><label class="form-label" for="actual-completion-date">Actual Completion</label><input id="actual-completion-date" v-model="form.actual_completion_date" type="date" class="form-input" /></div>
              </div>
            </div>

            <!-- ── Step 2: Financial ── -->
            <div v-show="activeStep === 2" class="step-content">
              <div class="section-header"><DollarSignIcon class="section-icon" /><h3>Financial Details</h3></div>
              <div class="form-grid-2">
                <div class="form-group">
                  <label class="form-label" for="estimated-cost">Estimated Cost</label>
                  <div class="input-addon-wrap">
                    <span class="input-addon">{{ form.currency }}</span>
                    <input id="estimated-cost" v-model.number="form.estimated_cost" type="number" step="0.01" min="0" class="form-input addon" placeholder="0.00" />
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label" for="actual-cost">Actual Cost</label>
                  <div class="input-addon-wrap">
                    <span class="input-addon">{{ form.currency }}</span>
                    <input id="actual-cost" v-model.number="form.actual_cost" type="number" step="0.01" min="0" class="form-input addon" placeholder="0.00" />
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
                <div class="cost-row"><span>Estimated</span><span class="cv">{{ fmtCur(form.estimated_cost || 0) }}</span></div>
                <div v-if="form.actual_cost" class="cost-row"><span>Actual</span><span class="cv">{{ fmtCur(form.actual_cost) }}</span></div>
                <div v-if="form.estimated_cost && form.actual_cost" class="cost-row variance">
                  <span>Variance</span>
                  <span class="cv" :class="costVariance >= 0 ? 'pos' : 'neg'">{{ costVariance >= 0 ? '+' : '' }}{{ fmtCur(costVariance) }}</span>
                </div>
              </div>
            </div>

            <!-- ── Step 3: Details ── -->
            <div v-show="activeStep === 3" class="step-content">
              <div class="section-header"><MapPinIcon class="section-icon" /><h3>Location</h3></div>
              <div class="form-grid-2">
                <div class="form-group span-2"><label class="form-label" for="location-address">Address</label><input id="location-address" v-model="form.location_address" type="text" class="form-input" placeholder="Full project address" /></div>
                <div class="form-group"><label class="form-label" for="location-latitude">Latitude</label><input id="location-latitude" v-model.number="form.location_lat" type="number" step="any" class="form-input" placeholder="e.g. 14.5995" /></div>
                <div class="form-group"><label class="form-label" for="location-longitude">Longitude</label><input id="location-longitude" v-model.number="form.location_lng" type="number" step="any" class="form-input" placeholder="e.g. 120.9842" /></div>
              </div>
              <div class="section-header" style="margin-top:1.5rem"><UserIcon class="section-icon" /><h3>Proponent Information</h3></div>
              <div class="form-grid-3">
                <div class="form-group"><label class="form-label" for="proponent-name">Name</label><input id="proponent-name" v-model="form.proponent_name" type="text" class="form-input" placeholder="Full name" /></div>
                <div class="form-group"><label class="form-label" for="proponent-contact">Contact</label><input id="proponent-contact" v-model="form.proponent_contact" type="text" class="form-input" placeholder="+63 XXX XXX XXXX" /></div>
                <div class="form-group"><label class="form-label" for="proponent-email">Email</label><input id="proponent-email" v-model="form.proponent_email" type="email" class="form-input" placeholder="email@example.com" /></div>
              </div>
            </div>

          </div>
          <div v-if="errors._form" class="form-submit-error">{{ errors._form }}</div>

          <!-- Footer -->
          <div class="modal-footer">
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
              {{ loading ? 'Saving...' : (isEdit ? 'Save Changes' : 'Create Project') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from 'vue3-toastify';
import { useProjectStore } from '@/store/projects';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import type { Project, ProjectFormData } from '@/types/project';
import {
  X as XIcon, PlusCircle as PlusCircleIcon, Edit as EditIcon,
  Check as CheckIcon, AlertCircle as AlertCircleIcon,
  Info as InfoIcon, Activity as ActivityIcon, DollarSign as DollarSignIcon,
  Calendar as CalendarIcon, MapPin as MapPinIcon, User as UserIcon, Star as StarIcon,
  ChevronLeft as ChevronLeftIcon, ChevronRight as ChevronRightIcon
} from 'lucide-vue-next';

interface Props { modelValue: boolean; project?: Project | null }
const props = defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [v: boolean];
  saved: [];
  close: [];
}>();

const projectStore = useProjectStore();
const layoutStore = useLayoutStore();
const { projectTypes, industries, sectors, stages, statuses, investmentTypes, fundingSources } = storeToRefs(projectStore);
const isDarkMode = computed(() => {
  const htmlDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  return layoutStore.mode === SITE_MODE.DARK || htmlDark;
});

const loading = ref(false);
const activeStep = ref(0);
const errors = ref<Record<string, string>>({});

const steps = [
  { id: 'basic', label: 'Basic Info' },
  { id: 'status', label: 'Status' },
  { id: 'financial', label: 'Financial' },
  { id: 'details', label: 'Details' },
];

const currencies = [
  { value: 'PHP', symbol: '₱' },
  { value: 'USD', symbol: '$' },
  { value: 'EUR', symbol: '€' },
];

const isEdit = computed(() => !!props.project);
const costVariance = computed(() => (form.value.actual_cost || 0) - (form.value.estimated_cost || 0));

// ── Form State ──
const defaultForm = (): ProjectFormData => ({
  title: '', description: '', project_type_id: 0,
  industry_id: 0, sector_id: 0, currency: 'PHP',
  current_stage_id: 0, status_id: 0, is_svf: false,
});

const form = ref<ProjectFormData>(defaultForm());

watch(() => props.modelValue, (val) => {
  if (val) {
    activeStep.value = 0;
    errors.value = {};
    if (props.project) loadProjectData();
    else form.value = defaultForm();
  }
});

const loadProjectData = () => {
  if (!props.project) return;
  const p = props.project;
  form.value = {
    title: p.title,
    description: p.description || '',
    project_type_id: p.project_type_id,
    industry_id: p.industry_id,
    sector_id: p.sector_id,
    investment_type_id: p.investment_type_id ?? undefined,
    funding_source_id: p.funding_source_id ?? undefined,
    estimated_cost: p.estimated_cost ?? undefined,
    actual_cost: p.actual_cost ?? undefined,
    currency: p.currency || 'PHP',
    current_stage_id: p.current_stage_id,
    status_id: p.status_id,
    proposal_date: p.proposal_date ?? undefined,
    start_date: p.start_date ?? undefined,
    target_completion_date: p.target_completion_date ?? undefined,
    actual_completion_date: p.actual_completion_date ?? undefined,
    location_address: p.location_address ?? undefined,
    location_lat: p.location_lat ?? undefined,
    location_lng: p.location_lng ?? undefined,
    proponent_name: p.proponent_name ?? undefined,
    proponent_contact: p.proponent_contact ?? undefined,
    proponent_email: p.proponent_email ?? undefined,
    is_svf: p.is_svf || false,
  };
};

// ── Validation ──
// Returns errors for a given step (does NOT mutate global errors)
const validateStep = (step: number): Record<string, string> => {
  const e: Record<string, string> = {};
  if (step === 0) {
    if (!form.value.title?.trim()) e.title = 'Project title is required';
    if (!form.value.project_type_id || form.value.project_type_id === 0) e.project_type_id = 'Project type is required';
    if (!form.value.industry_id || form.value.industry_id === 0) e.industry_id = 'Industry is required';
    if (!form.value.sector_id || form.value.sector_id === 0) e.sector_id = 'Sector is required';
  }
  if (step === 1) {
    if (!form.value.current_stage_id || form.value.current_stage_id === 0) e.current_stage_id = 'Please select a stage';
    if (!form.value.status_id || form.value.status_id === 0) e.status_id = 'Please select a status';
  }
  return e;
};

// Which error keys belong to each step
const stepErrorKeys: Record<number, string[]> = {
  0: ['title', 'project_type_id', 'industry_id', 'sector_id'],
  1: ['current_stage_id', 'status_id'],
  2: [],
  3: [],
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
  for (let i = 0; i < steps.length; i++) {
    Object.assign(allErrors, validateStep(i));
  }
  errors.value = allErrors;

  if (Object.keys(errors.value).length > 0) {
    // Jump to the first step that has errors
    for (let i = 0; i < steps.length; i++) {
      if (stepHasErrors(i)) { activeStep.value = i; return; }
    }
    return;
  }

  loading.value = true;
  try {
    if (isEdit.value && props.project) {
      await projectStore.updateProject(props.project.id, form.value);
    } else {
      await projectStore.createProject(form.value);
    }
    emit('saved');
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
      for (let i = 0; i < steps.length; i++) {
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
  emit('update:modelValue', false);
  emit('close');
};

const statusClass = (name: string) => {
  const map: Record<string, string> = { Active: 's-active', 'On Hold': 's-hold', Completed: 's-done', Cancelled: 's-cancelled' };
  return map[name] || '';
};

const fmtCur = (n: number) =>
  new Intl.NumberFormat('en-PH', { style: 'currency', currency: form.value.currency || 'PHP', maximumFractionDigits: 0 }).format(n);
</script>

<style scoped>
/* ─── CSS Variables ─── */
.modal-overlay {
  --m-bg: #ffffff;
  --m-overlay: rgba(15,23,42,0.65);
  --m-border: #e2e8f0;
  --m-subtle: #f8fafc;
  --m-muted: #f1f5f9;
  --m-text: #0f172a;
  --m-text-2: #475569;
  --m-text-3: #94a3b8;
  --m-text-in: #1e293b;
  --m-accent: #2563eb;
  --m-accent-bg: #eff6ff;
  --m-footer: #fafafa;
  --m-input-bg: #ffffff;
  --m-select-bg: #ffffff;
}
:global(.dark) .modal-overlay,
.modal-overlay.is-dark {
  --m-bg: #1e293b;
  --m-overlay: rgba(0,0,0,0.75);
  --m-border: #334155;
  --m-subtle: #253548;
  --m-muted: #293548;
  --m-text: #f1f5f9;
  --m-text-2: #94a3b8;
  --m-text-3: #64748b;
  --m-text-in: #e2e8f0;
  --m-accent: #3b82f6;
  --m-accent-bg: #1e3a5f;
  --m-footer: #1a2c42;
  --m-input-bg: #1e293b;
  --m-select-bg: #253548;
}

/* Overlay */
.modal-overlay {
  position: fixed; inset: 0; z-index: 9999;
  background: var(--m-overlay);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  padding: 1rem; overflow-y: auto;
}

/* Panel */
.modal-panel {
  background: var(--m-bg);
  border-radius: 1rem;
  box-shadow: 0 24px 64px rgba(0,0,0,0.22);
  width: 100%; max-width: 780px;
  max-height: 92vh;
  display: flex; flex-direction: column;
  overflow: hidden;
  position: relative;
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

/* ─── Step Tabs ─── */
.step-tabs { display: flex; padding: 0 1.5rem; border-bottom: 1px solid var(--m-border); overflow-x: auto; flex-shrink: 0; scrollbar-width: none; }
.step-tabs::-webkit-scrollbar { display: none; }
.step-tab { display: flex; align-items: center; gap: 0.5rem; padding: 0.875rem 1rem 0.875rem 0; background: none; border: none; border-bottom: 2.5px solid transparent; margin-bottom: -1px; font-size: 0.8rem; font-weight: 500; color: var(--m-text-3); cursor: pointer; white-space: nowrap; transition: all 0.15s; }
.step-tab:hover { color: var(--m-text-2); }
.step-tab.active { color: var(--m-accent); border-bottom-color: var(--m-accent); }
.step-tab.completed { color: #22c55e; }
.step-tab.error { color: #ef4444; }

.step-num { width: 1.5rem; height: 1.5rem; border-radius: 50%; background: currentColor; display: flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 700; position: relative; }
.step-num > span { color: var(--m-bg); position: relative; z-index: 1; }
.step-tab:not(.active):not(.completed):not(.error) .step-num { background: var(--m-muted); }
.step-tab:not(.active):not(.completed):not(.error) .step-num > span { color: var(--m-text-3); }
.step-tab.active .step-num { background: var(--m-accent); }
.step-tab.completed .step-num { background: #22c55e; }
.step-tab.error .step-num { background: #ef4444; }
.step-check { width: 0.75rem; height: 0.75rem; color: white; }

/* ─── Body ─── */
.modal-body { flex: 1; overflow-y: auto; padding: 1.375rem 1.5rem; overscroll-behavior: contain; }
.step-content { animation: stepIn 0.18s ease; }
@keyframes stepIn { from{opacity:0;transform:translateX(6px)} to{opacity:1;transform:translateX(0)} }

.section-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.125rem; }
.section-header h3 { font-size: 0.9375rem; font-weight: 700; color: var(--m-text); margin: 0; }
.section-icon { width: 1rem; height: 1rem; color: var(--m-accent); flex-shrink: 0; }

.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.span-2 { grid-column: span 2; }
.form-group { display: flex; flex-direction: column; gap: 0.3rem; }

.form-label { font-size: 0.78rem; font-weight: 600; color: var(--m-text-2); }
.form-label.required::after { content: ' *'; color: #ef4444; }
.char-count { font-size: 0.68rem; color: var(--m-text-3); text-align: right; }

.form-input {
  padding: 0.5875rem 0.8125rem; border: 1.5px solid var(--m-border); border-radius: 0.5rem;
  font-size: 0.875rem; color: var(--m-text-in); background: var(--m-input-bg);
  transition: all 0.15s; width: 100%; box-sizing: border-box;
}
.form-input:focus { outline: none; border-color: var(--m-accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-input.error { border-color: #ef4444; }

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

/* Pill Selector */
.pill-selector { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.pill-option { padding: 0.5rem 0.875rem; background: var(--m-subtle); border: 1.5px solid var(--m-border); border-radius: 0.5rem; font-size: 0.8rem; font-weight: 500; color: var(--m-text-2); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.pill-option:hover { border-color: var(--m-accent); color: var(--m-accent); background: var(--m-accent-bg); }
.pill-option.selected { background: var(--m-accent); border-color: var(--m-accent); color: white; }

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

/* ─── Footer ─── */
.modal-footer {
  display: flex; align-items: center; gap: 0.625rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--m-border);
  background: var(--m-footer);
  flex-shrink: 0;
}
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
  .span-2 { grid-column: span 1; }
  .step-label { display: none; }
}
</style>
