<!-- src/components/projects/CreateEditProjectDialog.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" @click.self="handleClose">
        <div class="modal-panel" role="dialog" aria-modal="true">
          <!-- Header -->
          <div class="modal-header">
            <div class="header-left">
              <div class="header-icon" :class="isEdit ? 'edit' : 'create'">
                <component :is="isEdit ? EditIcon : PlusCircleIcon" class="h-icon" />
              </div>
              <div>
                <h2 class="modal-title">{{ isEdit ? 'Edit Project' : 'Create New Project' }}</h2>
                <p class="modal-subtitle">{{ isEdit ? `Editing: ${props.project?.project_code}` : 'Fill in the details to create a new project' }}</p>
              </div>
            </div>
            <button class="close-btn" @click="handleClose">
              <XIcon class="h-icon" />
            </button>
          </div>

          <!-- Step Tabs -->
          <div class="step-tabs">
            <button
              v-for="(step, idx) in steps"
              :key="step.id"
              class="step-tab"
              :class="{ active: activeStep === idx, completed: idx < activeStep, error: stepHasError(idx) }"
              @click="activeStep = idx"
            >
              <span class="step-num">
                <CheckIcon v-if="idx < activeStep && !stepHasError(idx)" class="step-check" />
                <AlertCircleIcon v-else-if="stepHasError(idx)" class="step-check" />
                <span v-else>{{ idx + 1 }}</span>
              </span>
              <span class="step-label">{{ step.label }}</span>
            </button>
          </div>

          <!-- Form Body -->
          <div class="modal-body">
            <form @submit.prevent="handleSubmit">

              <!-- Step 1: Basic Info -->
              <div v-show="activeStep === 0" class="step-content">
                <div class="section-header">
                  <InfoIcon class="section-icon" />
                  <h3>Basic Information</h3>
                </div>

                <div class="form-grid-2">
                  <div class="form-group span-2">
                    <label class="form-label required" for="project-detail-title">Project Title</label>
                    <input
                      id="project-detail-title"
                      v-model="form.title"
                      type="text"
                      class="form-input"
                      :class="{ error: errors.title }"
                      placeholder="Enter a descriptive project title..."
                    />
                    <span v-if="errors.title" class="form-error">{{ errors.title }}</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label required" for="project-detail-type">Project Type</label>
                    <div class="select-wrapper">
                      <select id="project-detail-type" v-model="form.project_type_id" class="form-select" :class="{ error: errors.project_type_id }">
                        <option :value="0">Select type</option>
                        <option v-for="t in projectTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                      </select>
                    </div>
                    <span v-if="errors.project_type_id" class="form-error">{{ errors.project_type_id }}</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="project-detail-investment-type">Investment Type</label>
                    <div class="select-wrapper">
                      <select id="project-detail-investment-type" v-model="form.investment_type_id" class="form-select">
                        <option :value="undefined">Select type</option>
                        <option v-for="t in investmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group span-2">
                    <label class="form-label" for="project-detail-description">Description</label>
                    <textarea
                      id="project-detail-description"
                      v-model="form.description"
                      class="form-textarea"
                      rows="4"
                      placeholder="Describe the project objectives, scope, and key outcomes..."
                    ></textarea>
                    <span class="char-count">{{ form.description?.length || 0 }} characters</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label required" for="project-detail-industry">Industry</label>
                    <div class="select-wrapper">
                      <select id="project-detail-industry" v-model="form.industry_id" class="form-select" :class="{ error: errors.industry_id }">
                        <option :value="0">Select industry</option>
                        <option v-for="i in industries" :key="i.id" :value="i.id">{{ i.name }}</option>
                      </select>
                    </div>
                    <span v-if="errors.industry_id" class="form-error">{{ errors.industry_id }}</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label required" for="project-detail-sector">Sector</label>
                    <div class="select-wrapper">
                      <select id="project-detail-sector" v-model="form.sector_id" class="form-select" :class="{ error: errors.sector_id }">
                        <option :value="0">Select sector</option>
                        <option v-for="s in sectors" :key="s.id" :value="s.id">{{ s.name }}</option>
                      </select>
                    </div>
                    <span v-if="errors.sector_id" class="form-error">{{ errors.sector_id }}</span>
                  </div>
                </div>

                <!-- SVF Toggle -->
                <div class="toggle-card" @click="form.is_svf = !form.is_svf">
                  <div class="toggle-left">
                    <div class="toggle-icon">
                      <StarIcon class="h-icon" />
                    </div>
                    <div>
                      <p class="toggle-title">SVF Project</p>
                      <p class="toggle-desc">Mark this as a Special Venture Fund project</p>
                    </div>
                  </div>
                  <div class="toggle-switch" :class="{ on: form.is_svf }">
                    <div class="toggle-thumb"></div>
                  </div>
                </div>
              </div>

              <!-- Step 2: Status & Stage -->
              <div v-show="activeStep === 1" class="step-content">
                <div class="section-header">
                  <ActivityIcon class="section-icon" />
                  <h3>Status & Workflow</h3>
                </div>

                <div class="form-grid-2">
                  <div class="form-group">
                    <p class="form-label required">Current Stage</p>
                    <div class="stage-selector">
                      <button
                        v-for="stage in stages"
                        :key="stage.id"
                        type="button"
                        class="stage-option"
                        :class="{ selected: form.current_stage_id === stage.id }"
                        @click="form.current_stage_id = stage.id"
                      >
                        {{ stage.name }}
                      </button>
                    </div>
                    <span v-if="errors.current_stage_id" class="form-error">{{ errors.current_stage_id }}</span>
                  </div>

                  <div class="form-group">
                    <p class="form-label required">Status</p>
                    <div class="status-selector">
                      <button
                        v-for="status in statuses"
                        :key="status.id"
                        type="button"
                        class="status-option"
                        :class="[{ selected: form.status_id === status.id }, getStatusClass(status.name)]"
                        @click="form.status_id = status.id"
                      >
                        <span class="status-dot"></span>
                        {{ status.name }}
                      </button>
                    </div>
                    <span v-if="errors.status_id" class="form-error">{{ errors.status_id }}</span>
                  </div>
                </div>

                <!-- Timeline Section -->
                <div class="section-header" style="margin-top: 1.5rem;">
                  <CalendarIcon class="section-icon" />
                  <h3>Timeline</h3>
                </div>

                <div class="form-grid-4">
                  <div class="form-group">
                    <label class="form-label" for="project-detail-proposal-date">Proposal Date</label>
                    <input id="project-detail-proposal-date" v-model="form.proposal_date" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="project-detail-start-date">Start Date</label>
                    <input id="project-detail-start-date" v-model="form.start_date" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="project-detail-target-date">Target Completion</label>
                    <input id="project-detail-target-date" v-model="form.target_completion_date" type="date" class="form-input" />
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="project-detail-actual-date">Actual Completion</label>
                    <input id="project-detail-actual-date" v-model="form.actual_completion_date" type="date" class="form-input" />
                  </div>
                </div>
              </div>

              <!-- Step 3: Financial -->
              <div v-show="activeStep === 2" class="step-content">
                <div class="section-header">
                  <DollarSignIcon class="section-icon" />
                  <h3>Financial Details</h3>
                </div>

                <div class="form-grid-2">
                  <div class="form-group">
                    <label class="form-label" for="project-detail-estimated-cost">Estimated Cost</label>
                    <div class="input-addon-wrapper">
                      <span class="input-addon">{{ form.currency }}</span>
                      <input
                        id="project-detail-estimated-cost"
                        v-model.number="form.estimated_cost"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-input with-addon"
                        placeholder="0.00"
                      />
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="project-detail-actual-cost">Actual Cost</label>
                    <div class="input-addon-wrapper">
                      <span class="input-addon">{{ form.currency }}</span>
                      <input
                        id="project-detail-actual-cost"
                        v-model.number="form.actual_cost"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-input with-addon"
                        placeholder="0.00"
                      />
                    </div>
                  </div>

                  <div class="form-group">
                    <p class="form-label">Currency</p>
                    <div class="currency-selector">
                      <button
                        v-for="cur in currencies"
                        :key="cur.value"
                        type="button"
                        class="currency-option"
                        :class="{ selected: form.currency === cur.value }"
                        @click="form.currency = cur.value"
                      >
                        <span class="currency-symbol">{{ cur.symbol }}</span>
                        {{ cur.value }}
                      </button>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="project-detail-funding-source">Funding Source</label>
                    <div class="select-wrapper">
                      <select id="project-detail-funding-source" v-model="form.funding_source_id" class="form-select">
                        <option :value="undefined">Select source</option>
                        <option v-for="f in fundingSources" :key="f.id" :value="f.id">{{ f.name }}</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- Cost Summary -->
                <div v-if="form.estimated_cost || form.actual_cost" class="cost-summary">
                  <div class="cost-row">
                    <span>Estimated</span>
                    <span class="cost-val">{{ formatCurrency(form.estimated_cost || 0, form.currency) }}</span>
                  </div>
                  <div v-if="form.actual_cost" class="cost-row">
                    <span>Actual</span>
                    <span class="cost-val">{{ formatCurrency(form.actual_cost, form.currency) }}</span>
                  </div>
                  <div v-if="form.estimated_cost && form.actual_cost" class="cost-row variance">
                    <span>Variance</span>
                    <span class="cost-val" :class="variance >= 0 ? 'positive' : 'negative'">
                      {{ variance >= 0 ? '+' : '' }}{{ formatCurrency(variance, form.currency) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Step 4: Details -->
              <div v-show="activeStep === 3" class="step-content">
                <!-- Location -->
                <div class="section-header">
                  <MapPinIcon class="section-icon" />
                  <h3>Location</h3>
                </div>

                <div class="form-grid-2">
                  <div class="form-group span-2">
                    <label class="form-label" for="project-detail-address">Address</label>
                    <input id="project-detail-address" v-model="form.location_address" type="text" class="form-input" placeholder="Full project location address" />
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="project-detail-latitude">Latitude</label>
                    <input id="project-detail-latitude" v-model.number="form.location_lat" type="number" step="any" class="form-input" placeholder="e.g. 14.5995" />
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="project-detail-longitude">Longitude</label>
                    <input id="project-detail-longitude" v-model.number="form.location_lng" type="number" step="any" class="form-input" placeholder="e.g. 120.9842" />
                  </div>
                </div>

                <!-- Proponent -->
                <div class="section-header" style="margin-top: 1.5rem;">
                  <UserIcon class="section-icon" />
                  <h3>Proponent Information</h3>
                </div>

                <div class="form-grid-3">
                  <div class="form-group">
                    <label class="form-label" for="project-detail-proponent-name">Name</label>
                    <input id="project-detail-proponent-name" v-model="form.proponent_name" type="text" class="form-input" placeholder="Proponent full name" />
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="project-detail-proponent-contact">Contact</label>
                    <input id="project-detail-proponent-contact" v-model="form.proponent_contact" type="text" class="form-input" placeholder="+63 XXX XXX XXXX" />
                  </div>
                  <div class="form-group">
                    <label class="form-label" for="project-detail-proponent-email">Email</label>
                    <input id="project-detail-proponent-email" v-model="form.proponent_email" type="email" class="form-input" placeholder="email@example.com" />
                  </div>
                </div>
              </div>
            </form>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <button v-if="activeStep > 0" class="btn-back" @click="activeStep--">
              <ChevronLeftIcon class="h-icon" /> Back
            </button>
            <div class="footer-right">
              <button class="btn-cancel" @click="handleClose">Cancel</button>
              <button
                v-if="activeStep < steps.length - 1"
                class="btn-next"
                @click="nextStep"
              >
                Next <ChevronRightIcon class="h-icon" />
              </button>
              <button
                v-else
                class="btn-submit"
                @click="handleSubmit"
                :disabled="loading"
              >
                <span v-if="loading" class="spinner-sm"></span>
                {{ loading ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Project' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useProjectStore } from '@/store/projects';
import type { Project, ProjectFormData } from '@/types/project';
import {
  X as XIcon,
  Plus as PlusIcon,
  PlusCircle as PlusCircleIcon,
  Edit as EditIcon,
  Check as CheckIcon,
  AlertCircle as AlertCircleIcon,
  Info as InfoIcon,
  Activity as ActivityIcon,
  DollarSign as DollarSignIcon,
  Calendar as CalendarIcon,
  MapPin as MapPinIcon,
  User as UserIcon,
  Star as StarIcon,
  ChevronLeft as ChevronLeftIcon,
  ChevronRight as ChevronRightIcon
} from 'lucide-vue-next';

interface Props {
  modelValue: boolean;
  project?: Project | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  saved: [];
  close: [];
}>();

const projectStore = useProjectStore();
const { projectTypes, industries, sectors, stages, statuses, investmentTypes, fundingSources } = storeToRefs(projectStore);

const loading = ref(false);
const errors = ref<Record<string, string>>({});
const activeStep = ref(0);

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

const form = ref<ProjectFormData>({
  title: '',
  description: '',
  project_type_id: 0,
  industry_id: 0,
  sector_id: 0,
  currency: 'PHP',
  current_stage_id: 0,
  status_id: 0,
  is_svf: false,
});

const variance = computed(() =>
  (form.value.actual_cost || 0) - (form.value.estimated_cost || 0)
);

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    activeStep.value = 0;
    errors.value = {};
    if (props.project) loadProjectData();
    else resetForm();
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
    investment_type_id: p.investment_type_id || undefined,
    funding_source_id: p.funding_source_id || undefined,
    estimated_cost: p.estimated_cost || undefined,
    actual_cost: p.actual_cost || undefined,
    currency: p.currency || 'PHP',
    current_stage_id: p.current_stage_id,
    status_id: p.status_id,
    proposal_date: p.proposal_date || undefined,
    start_date: p.start_date || undefined,
    target_completion_date: p.target_completion_date || undefined,
    actual_completion_date: p.actual_completion_date || undefined,
    location_address: p.location_address || undefined,
    location_lat: p.location_lat || undefined,
    location_lng: p.location_lng || undefined,
    project_officer_id: p.project_officer_id || undefined,
    workgroup_head_id: p.workgroup_head_id || undefined,
    proponent_name: p.proponent_name || undefined,
    proponent_contact: p.proponent_contact || undefined,
    proponent_email: p.proponent_email || undefined,
    is_svf: p.is_svf || false,
  };
};

const resetForm = () => {
  form.value = {
    title: '',
    description: '',
    project_type_id: 0,
    industry_id: 0,
    sector_id: 0,
    currency: 'PHP',
    current_stage_id: 0,
    status_id: 0,
    is_svf: false,
  };
  errors.value = {};
};

const stepErrors: Record<number, string[]> = {
  0: ['title', 'project_type_id', 'industry_id', 'sector_id'],
  1: ['current_stage_id', 'status_id'],
  2: [],
  3: [],
};

const stepHasError = (idx: number) =>
  stepErrors[idx]?.some(key => !!errors.value[key]) || false;

const validateStep = (step: number): boolean => {
  const stepErr: Record<string, string> = {};
  if (step === 0) {
    if (!form.value.title?.trim()) stepErr.title = 'Project title is required';
    if (!form.value.project_type_id || form.value.project_type_id === 0) stepErr.project_type_id = 'Required';
    if (!form.value.industry_id || form.value.industry_id === 0) stepErr.industry_id = 'Required';
    if (!form.value.sector_id || form.value.sector_id === 0) stepErr.sector_id = 'Required';
  }
  if (step === 1) {
    if (!form.value.current_stage_id || form.value.current_stage_id === 0) stepErr.current_stage_id = 'Select a stage';
    if (!form.value.status_id || form.value.status_id === 0) stepErr.status_id = 'Select a status';
  }
  // Merge into errors
  Object.assign(errors.value, stepErr);
  return Object.keys(stepErr).length === 0;
};

const nextStep = () => {
  if (validateStep(activeStep.value)) {
    activeStep.value++;
  }
};

const validate = (): boolean => {
  errors.value = {};
  let valid = true;
  for (let i = 0; i < steps.length; i++) {
    if (!validateStep(i)) valid = false;
  }
  return valid;
};

const handleSubmit = async () => {
  if (!validate()) {
    // Jump to first error step
    for (let i = 0; i < steps.length; i++) {
      if (stepHasError(i)) { activeStep.value = i; break; }
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
    if (err.response?.data?.errors) {
      Object.assign(errors.value, err.response.data.errors);
    }
  } finally {
    loading.value = false;
  }
};

const handleClose = () => {
  emit('update:modelValue', false);
  emit('close');
};

const getStatusClass = (name: string) => {
  const map: Record<string, string> = {
    'Active': 'active', 'On Hold': 'hold', 'Completed': 'done', 'Cancelled': 'cancelled',
  };
  return map[name] || '';
};

const formatCurrency = (amount: number, currency = 'PHP') =>
  new Intl.NumberFormat('en-PH', { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount);
</script>

<style scoped>
/* Modal Overlay */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  overflow-y: auto;
}

.modal-panel {
  background: white;
  border-radius: 1rem;
  box-shadow: 0 24px 64px rgba(0,0,0,0.18);
  width: 100%;
  max-width: 780px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Header */
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid #f1f5f9;
}
.header-left { display: flex; align-items: center; gap: 1rem; }

.header-icon {
  width: 3rem; height: 3rem;
  border-radius: 0.75rem;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.header-icon.create { background: #eff6ff; color: #2563eb; }
.header-icon.edit { background: #f0fdf4; color: #16a34a; }

.modal-title { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0 0 0.125rem; }
.modal-subtitle { font-size: 0.8rem; color: #94a3b8; margin: 0; }

.close-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  border: none; background: #f1f5f9; border-radius: 0.5rem;
  cursor: pointer; color: #475569; transition: all 0.15s;
  flex-shrink: 0;
}
.close-btn:hover { background: #fee2e2; color: #dc2626; }

/* Step Tabs */
.step-tabs {
  display: flex;
  padding: 0 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  gap: 0;
}
.step-tab {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1.25rem 0.875rem 0;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  color: #94a3b8;
  font-size: 0.8125rem;
  font-weight: 500;
  transition: all 0.15s;
  margin-bottom: -1px;
}
.step-tab:hover { color: #475569; }
.step-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
.step-tab.completed { color: #22c55e; }
.step-tab.error { color: #ef4444; }

.step-num {
  width: 1.5rem; height: 1.5rem;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.15;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.7rem; font-weight: 700;
  color: inherit;
  position: relative;
}
.step-tab.active .step-num { opacity: 1; background: #2563eb; color: white; }
.step-tab.completed .step-num { opacity: 1; background: #22c55e; color: white; }
.step-tab.error .step-num { opacity: 1; background: #ef4444; color: white; }
.step-check { width: 0.75rem; height: 0.75rem; color: white; }

/* Body */
.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
  overscroll-behavior: contain;
}

/* Step Content */
.step-content { animation: stepIn 0.2s ease; }
@keyframes stepIn {
  from { opacity: 0; transform: translateX(8px); }
  to { opacity: 1; transform: translateX(0); }
}

.section-header {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin-bottom: 1.25rem;
}
.section-header h3 { font-size: 0.9375rem; font-weight: 700; color: #0f172a; margin: 0; }
.section-icon { width: 1rem; height: 1rem; color: #2563eb; }

/* Form Layout */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.span-2 { grid-column: span 2; }

.form-group { display: flex; flex-direction: column; gap: 0.375rem; }
.form-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
}
.form-label.required::after { content: ' *'; color: #ef4444; }

.form-input {
  padding: 0.625rem 0.875rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  color: #0f172a;
  background: white;
  transition: all 0.15s;
  width: 100%;
  box-sizing: border-box;
}
.form-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-input.error { border-color: #ef4444; }

.form-textarea {
  padding: 0.625rem 0.875rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  color: #0f172a;
  resize: vertical;
  font-family: inherit;
  transition: all 0.15s;
  width: 100%;
  box-sizing: border-box;
}
.form-textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.char-count { font-size: 0.7rem; color: #94a3b8; text-align: right; }

.select-wrapper { position: relative; }
.form-select {
  appearance: none;
  padding: 0.625rem 2.5rem 0.625rem 0.875rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  color: #0f172a;
  background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 0.875rem center;
  cursor: pointer;
  transition: all 0.15s;
  width: 100%;
}
.form-select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-select.error { border-color: #ef4444; }

.form-error { font-size: 0.75rem; color: #ef4444; font-weight: 500; }

/* SVF Toggle */
.toggle-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.75rem;
  cursor: pointer;
  transition: all 0.15s;
  margin-top: 1rem;
}
.toggle-card:hover { border-color: #2563eb; background: #eff6ff; }
.toggle-left { display: flex; align-items: center; gap: 0.875rem; }
.toggle-icon { width: 2.25rem; height: 2.25rem; background: #fffbeb; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; color: #f59e0b; }
.toggle-title { font-size: 0.875rem; font-weight: 600; color: #0f172a; margin: 0 0 0.125rem; }
.toggle-desc { font-size: 0.75rem; color: #64748b; margin: 0; }

.toggle-switch {
  width: 2.75rem; height: 1.5rem;
  background: #e2e8f0;
  border-radius: 999px;
  position: relative;
  transition: background 0.2s;
  flex-shrink: 0;
}
.toggle-switch.on { background: #2563eb; }
.toggle-thumb {
  position: absolute;
  top: 0.25rem; left: 0.25rem;
  width: 1rem; height: 1rem;
  background: white;
  border-radius: 50%;
  transition: transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-switch.on .toggle-thumb { transform: translateX(1.25rem); }

/* Stage Selector */
.stage-selector, .status-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.stage-option {
  padding: 0.5rem 1rem;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
.stage-option:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
.stage-option.selected { background: #2563eb; border-color: #2563eb; color: white; }

.status-option {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
.status-option .status-dot {
  width: 0.5rem; height: 0.5rem; border-radius: 50%; background: currentColor;
}
.status-option:hover { border-color: #64748b; }
.status-option.selected.active { background: #dcfce7; border-color: #22c55e; color: #15803d; }
.status-option.selected.hold { background: #fef3c7; border-color: #f59e0b; color: #b45309; }
.status-option.selected.done { background: #dbeafe; border-color: #3b82f6; color: #1d4ed8; }
.status-option.selected.cancelled { background: #fee2e2; border-color: #ef4444; color: #b91c1c; }
.status-option.selected:not(.active):not(.hold):not(.done):not(.cancelled) { background: #eff6ff; border-color: #2563eb; color: #2563eb; }

/* Input with addon */
.input-addon-wrapper { display: flex; align-items: center; }
.input-addon {
  padding: 0.625rem 0.75rem;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-right: none;
  border-radius: 0.5rem 0 0 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
  white-space: nowrap;
}
.form-input.with-addon { border-radius: 0 0.5rem 0.5rem 0; }

/* Currency Selector */
.currency-selector { display: flex; gap: 0.5rem; }
.currency-option {
  flex: 1;
  padding: 0.5rem;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
  display: flex; flex-direction: column; align-items: center; gap: 0.125rem;
}
.currency-symbol { font-size: 1rem; font-weight: 700; }
.currency-option.selected { background: #2563eb; border-color: #2563eb; color: white; }
.currency-option:hover:not(.selected) { border-color: #2563eb; color: #2563eb; background: #eff6ff; }

/* Cost Summary */
.cost-summary {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  padding: 1rem;
  margin-top: 1rem;
}
.cost-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.375rem 0;
  font-size: 0.875rem;
  color: #64748b;
}
.cost-row + .cost-row { border-top: 1px solid #e2e8f0; }
.cost-row.variance { font-weight: 700; }
.cost-val { font-weight: 600; color: #0f172a; }
.cost-val.positive { color: #16a34a; }
.cost-val.negative { color: #dc2626; }

/* h-icon size */
.h-icon { width: 1.125rem; height: 1.125rem; }

/* Footer */
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-top: 1px solid #f1f5f9;
  background: #fafafa;
}
.footer-right { display: flex; align-items: center; gap: 0.625rem; margin-left: auto; }

.btn-back {
  display: flex; align-items: center; gap: 0.375rem;
  padding: 0.625rem 1.125rem;
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-back:hover { border-color: #94a3b8; color: #0f172a; }

.btn-cancel {
  padding: 0.625rem 1.125rem;
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-cancel:hover { border-color: #94a3b8; }

.btn-next {
  display: flex; align-items: center; gap: 0.375rem;
  padding: 0.625rem 1.375rem;
  background: #0f172a;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: white;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-next:hover { background: #1e293b; }

.btn-submit {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.625rem 1.375rem;
  background: #2563eb;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: white;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-submit:hover:not(:disabled) { background: #1d4ed8; }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

/* Dark mode overrides */
:global(.dark) .modal-overlay { background: rgba(0, 0, 0, 0.75); }
:global(.dark) .modal-panel { background: #1e293b; box-shadow: 0 24px 64px rgba(0, 0, 0, 0.5); }
:global(.dark) .modal-header,
:global(.dark) .step-tabs,
:global(.dark) .modal-footer { border-color: #334155; }
:global(.dark) .modal-footer { background: #1a2c42; }
:global(.dark) .modal-title { color: #f1f5f9; }
:global(.dark) .modal-subtitle,
:global(.dark) .step-tab,
:global(.dark) .char-count,
:global(.dark) .form-error,
:global(.dark) .toggle-desc,
:global(.dark) .form-label,
:global(.dark) .btn-cancel,
:global(.dark) .btn-back { color: #94a3b8; }
:global(.dark) .close-btn { background: #293548; color: #94a3b8; }
:global(.dark) .step-tab:hover { color: #cbd5e1; }
:global(.dark) .section-header h3 { color: #f1f5f9; }
:global(.dark) .form-input,
:global(.dark) .form-textarea,
:global(.dark) .form-select {
  color: #e2e8f0;
  background-color: #1e293b;
  border-color: #334155;
}
:global(.dark) .form-select {
  background-color: #253548;
}
:global(.dark) .toggle-card,
:global(.dark) .stage-option,
:global(.dark) .status-option,
:global(.dark) .currency-option,
:global(.dark) .cost-summary,
:global(.dark) .input-addon {
  background: #253548;
  border-color: #334155;
  color: #94a3b8;
}
:global(.dark) .toggle-title,
:global(.dark) .cost-val { color: #f1f5f9; }
:global(.dark) .toggle-icon { background: #2d1f08; color: #fbbf24; }
:global(.dark) .toggle-switch { background: #334155; }
:global(.dark) .toggle-thumb,
:global(.dark) .btn-cancel,
:global(.dark) .btn-back { background: #1e293b; border-color: #334155; }
:global(.dark) .stage-option:hover,
:global(.dark) .status-option:hover,
:global(.dark) .currency-option:hover:not(.selected),
:global(.dark) .toggle-card:hover { background: #1e3a5f; border-color: #3b82f6; color: #60a5fa; }
:global(.dark) .cost-row,
:global(.dark) .cost-row + .cost-row { border-color: #334155; color: #94a3b8; }
:global(.dark) .btn-next { background: #64748b; }
:global(.dark) .btn-next:hover { background: #94a3b8; }

.spinner-sm {
  width: 1rem; height: 1rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal transition */
.modal-enter-active { animation: modalIn 0.25s ease; }
.modal-leave-active { animation: modalIn 0.2s ease reverse; }
@keyframes modalIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.modal-enter-active .modal-panel { animation: panelIn 0.25s cubic-bezier(0.34,1.56,0.64,1); }
.modal-leave-active .modal-panel { animation: panelIn 0.2s ease reverse; }
@keyframes panelIn {
  from { transform: scale(0.95) translateY(16px); }
  to { transform: scale(1) translateY(0); }
}

@media (max-width: 640px) {
  .form-grid-2 { grid-template-columns: 1fr; }
  .form-grid-3 { grid-template-columns: 1fr 1fr; }
  .form-grid-4 { grid-template-columns: 1fr 1fr; }
  .span-2 { grid-column: span 1; }
  .step-tabs { overflow-x: auto; }
  .step-label { display: none; }
}
</style>
