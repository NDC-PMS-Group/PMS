<!-- src/components/admin/accessSettings/components/SoiWorkflowsTab.vue -->
<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { toast } from 'vue3-toastify';
import axiosInstance from '@/utils/axiosInstance';
import { 
  GitMerge, 
  Plus, 
  Trash2, 
  Edit3, 
  Upload, 
  Download, 
  Shield, 
  ChevronRight, 
  ChevronDown, 
  FileText, 
  CheckCircle,
  Clock,
  Menu,
  CheckSquare,
  ChevronUp
} from 'lucide-vue-next';
import type { Role } from '@/types/accessSettings';
import EditStepModal from './EditStepModal.vue';
import EditDefaultRequirementModal from './EditDefaultRequirementModal.vue';
import EditDefaultTaskModal from './EditDefaultTaskModal.vue';

interface Step {
  id?: number;
  step_order: number;
  role_id: number;
  step_name: string;
  soi_section: string | null;
  sla_days: number | null;
  is_required: boolean;
  can_skip: boolean;
  role?: Role;
}

interface Workflow {
  id: number;
  name: string;
  description: string | null;
  project_type_id: number | null;
  is_active: boolean;
  steps: Step[];
}

interface Requirement {
  id?: number;
  track: string;
  group_name: string;
  item_name: string;
  source_document: string | null;
  owner_type: 'proponent' | 'internal';
  visibility: 'proponent_visible' | 'internal_only';
  soi_section: string;
  gate_step: string | null;
  is_required: boolean;
  svf_only: boolean;
  sort_order: number;
  template_file_path?: string | null;
}

interface Task {
  id?: number;
  track: string;
  title: string;
  description: string | null;
  task_type: string | null;
  soi_section: string;
  assigned_role: string;
  days: number;
  priority: string;
  is_milestone: boolean;
  parent_task_title: string | null;
  sort_order: number;
}

interface Props {
  roles: Role[];
  permissionKey: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  refresh: [];
}>();

const loading = ref(false);
const savingSteps = ref(false);
const workflows = ref<Workflow[]>([]);
const allRequirements = ref<Requirement[]>([]);
const allTasks = ref<Task[]>([]);

const selectedTrack = ref<string>('bdg_investment');
const expandedSteps = ref<Record<number, boolean>>({});
const expandedTasks = ref<Record<number, boolean>>({});
const expandedRequirements = ref<Record<number, boolean>>({});

const tracks = [
  { value: 'bdg_investment', label: 'External Investment Proposal (BDG)', group: 'Development Routes' },
  { value: 'bdg_svf', label: 'Small Value Fund Variant (BDG)', group: 'Development Routes' },
  { value: 'spg_jv', label: 'Joint Venture Proposal (SPG)', group: 'Development Routes' },
  { value: 'spg_traditional', label: 'Traditional Equity Funding (SPG)', group: 'Development Routes' },
  { value: 'spg_ndc_own', label: 'NDC-Owned Project (SPG)', group: 'Development Routes' },
  { value: 'implementation_monitoring', label: 'Implementation & Monitoring', group: 'Lifecycle Workflows' },
  { value: 'divestment', label: 'Divestment / Exit', group: 'Lifecycle Workflows' },
];
const trackGroups = ['Development Routes', 'Lifecycle Workflows'];
const templateTrack = computed(() => selectedTrack.value === 'bdg_svf' ? 'bdg_investment' : selectedTrack.value);

const fetchWorkflows = async () => {
  try {
    const res = await axiosInstance.get('/api/access-settings/workflows');
    workflows.value = res.data.data;
  } catch (error) {
    console.error('Error fetching workflows:', error);
    toast.error('Failed to load workflows');
  }
};

const fetchRequirements = async () => {
  try {
    const res = await axiosInstance.get('/api/access-settings/default-requirements');
    allRequirements.value = res.data.data;
  } catch (error) {
    console.error('Error fetching requirements:', error);
    toast.error('Failed to load checklist templates');
  }
};

const fetchTasks = async () => {
  try {
    const res = await axiosInstance.get('/api/access-settings/default-tasks');
    allTasks.value = res.data;
  } catch (error) {
    console.error('Error fetching default tasks:', error);
    toast.error('Failed to load work plan task templates');
  }
};

const loadData = async () => {
  loading.value = true;
  await Promise.all([fetchWorkflows(), fetchRequirements(), fetchTasks()]);
  loading.value = false;
};

onMounted(loadData);

watch(selectedTrack, () => {
  expandedSteps.value = {};
  expandedTasks.value = {};
  expandedRequirements.value = {};
});

// Mapping track values to workflow records in database
const currentWorkflow = computed(() => {
  const map: Record<string, string> = {
    bdg_investment: 'NDC BDG Investment Approval',
    bdg_svf: 'NDC SVF Investment Approval',
    spg_jv: 'SPG Joint Venture Project Approval',
    spg_traditional: 'SPG Traditional Equity Funding Approval',
    spg_ndc_own: 'SPG NDC-Owned Project Approval',
    implementation_monitoring: 'NDC Implementation and Monitoring Workflow',
    divestment: 'NDC Divestment Approval',
  };
  const wName = map[selectedTrack.value];
  return workflows.value.find(w => w.name === wName) || null;
});

const currentRequirements = computed(() => {
  return allRequirements.value.filter(req => req.track === templateTrack.value);
});

// Helper filters for inline checklists
const getRequirementsForSection = (section: string | null) => {
  if (!section) return [];
  return currentRequirements.value.filter(req => req.soi_section === section);
};

const getTasksForSection = (section: string | null) => {
  if (!section) return [];
  return allTasks.value.filter(t => t.track === templateTrack.value && t.soi_section === section);
};

const isStepExpanded = (idx: number) => {
  return expandedSteps.value[idx] !== false; // Default to true (expanded)
};

const toggleStepExpanded = (idx: number) => {
  expandedSteps.value[idx] = !isStepExpanded(idx);
};

const isTasksExpanded = (idx: number) => {
  return expandedTasks.value[idx] === true; // Default to false (collapsed/minimized)
};

const toggleTasksExpanded = (idx: number) => {
  expandedTasks.value[idx] = !isTasksExpanded(idx);
};

const isRequirementsExpanded = (idx: number) => {
  return expandedRequirements.value[idx] === true; // Default to false (collapsed/minimized)
};

const toggleRequirementsExpanded = (idx: number) => {
  expandedRequirements.value[idx] = !isRequirementsExpanded(idx);
};

// Modals State
const showStepModal = ref(false);
const editingStep = ref<Step | null>(null);
const editingStepIndex = ref<number>(-1);

const showReqModal = ref(false);
const editingReq = ref<Requirement | null>(null);

const showTaskModal = ref(false);
const editingTask = ref<Task | null>(null);
const activeTaskSection = ref<string>('intake');
const activeParentTasks = ref<Task[]>([]);

// Drag & Drop sequencing state
const dragIndex = ref<number | null>(null);

const onDragStart = (index: number) => {
  dragIndex.value = index;
};

const onDragOver = (event: DragEvent) => {
  event.preventDefault();
};

const onDrop = (index: number) => {
  if (dragIndex.value === null || !currentWorkflow.value) return;
  const steps = [...currentWorkflow.value.steps];
  const draggedItem = steps[dragIndex.value];
  
  steps.splice(dragIndex.value, 1);
  steps.splice(index, 0, draggedItem);
  
  steps.forEach((s, idx) => {
    s.step_order = idx + 1;
  });
  
  currentWorkflow.value.steps = steps;
  dragIndex.value = null;
};

const openEditStep = (step: Step, index: number) => {
  editingStep.value = { ...step };
  editingStepIndex.value = index;
  showStepModal.value = true;
};

const openAddStep = () => {
  const nextOrder = currentWorkflow.value 
    ? (currentWorkflow.value.steps.reduce((max, s) => Math.max(max, s.step_order), 0) + 1)
    : 1;
  editingStep.value = {
    step_order: nextOrder,
    role_id: props.roles[0]?.id || 0,
    step_name: '',
    soi_section: 'intake',
    sla_days: null,
    is_required: true,
    can_skip: false,
  };
  editingStepIndex.value = -1;
  showStepModal.value = true;
};

const handleSaveStep = (stepData: Step) => {
  if (!currentWorkflow.value) return;
  
  const stepsCopy = [...currentWorkflow.value.steps];
  
  if (editingStepIndex.value > -1) {
    stepsCopy[editingStepIndex.value] = stepData;
  } else {
    stepsCopy.push(stepData);
  }
  
  stepsCopy.sort((a, b) => a.step_order - b.step_order);
  currentWorkflow.value.steps = stepsCopy;
  showStepModal.value = false;
};

const handleDeleteStep = (index: number) => {
  if (!currentWorkflow.value) return;
  if (confirm('Are you sure you want to remove this approval step? You must save changes to apply.')) {
    const stepsCopy = [...currentWorkflow.value.steps];
    stepsCopy.splice(index, 1);
    stepsCopy.forEach((s, idx) => s.step_order = idx + 1);
    currentWorkflow.value.steps = stepsCopy;
  }
};

const handleSaveAllSteps = async () => {
  if (!currentWorkflow.value) return;
  savingSteps.value = true;
  try {
    const res = await axiosInstance.put(
      `/api/access-settings/workflows/${currentWorkflow.value.id}/steps`, 
      { steps: currentWorkflow.value.steps }
    );
    
    currentWorkflow.value.steps = res.data.data.steps;
    toast.success('Approval step sequence saved successfully!');
    fetchWorkflows();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save workflow steps');
  } finally {
    savingSteps.value = false;
  }
};

// Requirements Management
const openAddRequirement = (step: Step, stepIdx: number) => {
  const section = step.soi_section || 'intake';
  const existing = getRequirementsForSection(section);
  editingReq.value = {
    track: templateTrack.value,
    group_name: `${stepIdx + 1}. ${step.step_name}`,
    item_name: '',
    source_document: '',
    owner_type: 'proponent',
    visibility: 'proponent_visible',
    soi_section: section,
    gate_step: null,
    is_required: true,
    svf_only: false,
    sort_order: (existing.length + 1) * 10,
  };
  showReqModal.value = true;
};

const openEditRequirement = (req: Requirement) => {
  editingReq.value = { ...req };
  showReqModal.value = true;
};

const handleDeleteRequirement = async (reqId: number) => {
  if (confirm('Are you sure you want to permanently delete this required checklist document?')) {
    try {
      await axiosInstance.delete(`/api/access-settings/default-requirements/${reqId}`);
      toast.success('Required document template deleted');
      fetchRequirements();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to delete requirement');
    }
  }
};

const handleReqSaved = () => {
  showReqModal.value = false;
  fetchRequirements();
};

// Default Tasks Management
const openAddTask = (step: Step) => {
  const section = step.soi_section || 'intake';
  const existing = getTasksForSection(section);
  activeTaskSection.value = section;
  activeParentTasks.value = existing.filter(t => !t.parent_task_title);
  
  editingTask.value = {
    track: templateTrack.value,
    title: '',
    description: '',
    task_type: null,
    soi_section: section,
    assigned_role: 'Project Officer',
    days: 10,
    priority: 'medium',
    is_milestone: false,
    parent_task_title: null,
    sort_order: (existing.length + 1) * 10,
  };
  showTaskModal.value = true;
};

const openEditTask = (task: Task) => {
  const existing = getTasksForSection(task.soi_section);
  activeTaskSection.value = task.soi_section;
  activeParentTasks.value = existing.filter(t => !t.parent_task_title);
  
  editingTask.value = { ...task };
  showTaskModal.value = true;
};

const handleDeleteTask = async (taskId: number) => {
  if (confirm('Are you sure you want to permanently delete this default task?')) {
    try {
      await axiosInstance.delete(`/api/access-settings/default-tasks/${taskId}`);
      toast.success('Default task deleted successfully');
      fetchTasks();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to delete default task');
    }
  }
};

const handleTaskSaved = () => {
  showTaskModal.value = false;
  fetchTasks();
};

const downloadTemplate = (filePath: string) => {
  const token = localStorage.getItem('auth_token');
  const tokenQuery = token ? `&token=${token}` : '';
  window.open(`/api/lookup/templates/download?file=${encodeURIComponent(filePath)}${tokenQuery}`, '_blank');
};

const sectionLabels: Record<string, string> = {
  intake: 'Intake',
  requirements: 'Requirements Check',
  due_diligence: 'Due Diligence',
  management_review: 'Management Review',
  board_approval: 'Board Approval',
  agreement_fund_release: 'Agreement & Fund Release',
  implementation_monitoring: 'Implementation & Monitoring',
  post_investment_strategy: 'Post-Investment Strategy',
  divestment: 'Divestment',
  completion: 'Completion',
};
</script>

<template>
  <div class="space-y-6">
    <!-- Selection Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between rounded-xl bg-slate-50 p-4 border border-slate-200 dark:bg-slate-800/40 dark:border-slate-700">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Select SOI Track</label>
        <select
          v-model="selectedTrack"
          class="min-w-[280px] rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
        >
          <optgroup v-for="group in trackGroups" :key="group" :label="group">
            <option v-for="t in tracks.filter(track => track.group === group)" :key="t.value" :value="t.value">
              {{ t.label }}
            </option>
          </optgroup>
        </select>
      </div>
      
      <div v-if="currentWorkflow" class="text-right">
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-100 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-900">
          <GitMerge :size="12" /> Database Workflow Mapped
        </span>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
          ID: {{ currentWorkflow.id }} · {{ currentWorkflow.steps.length }} active stages
        </p>
      </div>
    </div>

    <!-- Main Content Timeline -->
    <div v-if="loading" class="flex flex-col items-center justify-center p-12 text-slate-500">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
      <p class="text-sm font-semibold">Loading track details...</p>
    </div>

    <div v-else class="max-w-4xl mx-auto space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
        <div>
          <h3 class="font-bold text-lg text-slate-900 dark:text-white">SOI Routing Flow & Checklists</h3>
          <p class="text-xs text-slate-500">Drag step cards to reorder sequence. Add requirements and work-plan tasks directly under each phase.</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="openAddStep"
            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            <Plus :size="14" /> Add Step
          </button>
          <button
            @click="handleSaveAllSteps"
            :disabled="savingSteps"
            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-700 transition disabled:opacity-50"
          >
            <Clock :size="14" v-if="savingSteps" class="animate-spin" />
            Save Sequence
          </button>
        </div>
      </div>

      <div v-if="!currentWorkflow?.steps?.length" class="text-center p-12 text-slate-400 bg-white border border-slate-200 rounded-2xl dark:border-slate-800 dark:bg-slate-900">
        No steps found for this workflow. Add a step to begin.
      </div>

      <div v-else class="relative pl-8 border-l-2 border-slate-200 dark:border-slate-800 space-y-8 py-2 ml-4">
        <div 
          v-for="(step, idx) in currentWorkflow.steps" 
          :key="idx" 
          class="relative group"
          draggable="true"
          @dragstart="onDragStart(idx)"
          @dragover="onDragOver"
          @drop="onDrop(idx)"
        >
          <!-- Bullet node -->
          <span 
            class="absolute -left-[43px] top-4 flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ring-4 ring-white dark:ring-slate-950 border bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-950 dark:text-blue-400 dark:border-blue-900"
          >
            {{ idx + 1 }}
          </span>
          
          <div class="space-y-4">
            <!-- Step Card -->
            <div 
              class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all dark:border-slate-800 dark:bg-slate-900"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex items-center gap-3">
                  <Menu :size="18" class="text-slate-400 drag-handle shrink-0 cursor-move" />
                  <div class="min-w-0">
                    <h4 class="text-base font-bold text-slate-900 dark:text-white truncate">
                      {{ step.step_name }}
                    </h4>
                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                      <span class="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                        <Shield :size="10" /> {{ roles.find(r => r.id === step.role_id)?.name || 'Role ID ' + step.role_id }}
                      </span>
                      <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Phase: {{ sectionLabels[step.soi_section || ''] || step.soi_section || 'N/A' }}
                      </span>
                      <span v-if="!step.is_required" class="inline-flex items-center rounded bg-yellow-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-yellow-600 border border-yellow-100 dark:bg-yellow-950/20 dark:text-yellow-400">
                        Optional
                      </span>
                      <span v-if="step.sla_days" class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ step.sla_days }} day SLA
                      </span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <button
                    @click="toggleStepExpanded(idx)"
                    class="p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 rounded-lg dark:hover:bg-slate-800 dark:hover:text-slate-200"
                  >
                    <component :is="isStepExpanded(idx) ? ChevronUp : ChevronDown" :size="16" />
                  </button>
                  <button
                    @click="openEditStep(step, idx)"
                    class="p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 rounded-lg dark:hover:bg-slate-800 dark:hover:text-white"
                  >
                    <Edit3 :size="16" />
                  </button>
                  <button
                    @click="handleDeleteStep(idx)"
                    class="p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700 rounded-lg dark:hover:bg-red-950/45 dark:hover:text-red-400"
                  >
                    <Trash2 :size="16" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Inline Checklists (Collapsible step child components) -->
            <div 
              v-show="isStepExpanded(idx)"
              class="ml-6 pl-6 border-l border-slate-200 dark:border-slate-800 space-y-4 pb-2"
            >
              <!-- 1. Work Plan Checklist -->
              <div class="space-y-3">
                <div 
                  @click="toggleTasksExpanded(idx)"
                  class="flex items-center justify-between cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40 p-2 rounded-xl transition select-none"
                >
                  <h5 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-450 flex items-center gap-1.5">
                    <component :is="isTasksExpanded(idx) ? ChevronUp : ChevronDown" :size="14" class="text-slate-400" />
                    <CheckSquare :size="14" /> Work-plan tasks
                    <span class="ml-1 text-[10px] lowercase font-medium text-slate-400">
                      ({{ getTasksForSection(step.soi_section).length }} tasks)
                    </span>
                  </h5>
                  <button
                    @click.stop="openAddTask(step)"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                  >
                    <Plus :size="12" /> Add Task
                  </button>
                </div>

                <div v-show="isTasksExpanded(idx)" class="space-y-2 pl-5 transition-all">
                  <div v-if="!getTasksForSection(step.soi_section).length" class="text-xs text-slate-400 italic pl-1">
                    No tasks configured for this phase.
                  </div>

                  <div v-else class="space-y-2">
                    <div 
                      v-for="task in getTasksForSection(step.soi_section)" 
                      :key="task.id" 
                      class="group flex items-start justify-between gap-3 bg-slate-50/50 p-2.5 rounded-lg border border-slate-100/80 hover:border-slate-200 dark:bg-slate-900/30 dark:border-slate-850 dark:hover:border-slate-800"
                      :class="{ 'ml-6 border-l-2 border-blue-200 dark:border-blue-900': task.parent_task_title }"
                    >
                      <div class="min-w-0">
                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ task.title }}</span>
                        <p v-if="task.description" class="text-[11px] text-slate-500 mt-0.5">{{ task.description }}</p>
                        
                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                          <span class="rounded bg-slate-100 px-1 py-0.2 text-[9px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            Role: {{ task.assigned_role }}
                          </span>
                          <span class="rounded bg-blue-50/60 px-1 py-0.2 text-[9px] font-semibold text-blue-600 dark:bg-blue-950/20 dark:text-blue-400">
                            Days: {{ task.days }}
                          </span>
                          <span class="rounded bg-slate-100 px-1 py-0.2 text-[9px] font-semibold text-slate-650 dark:bg-slate-800 dark:text-slate-400">
                            Priority: {{ task.priority }}
                          </span>
                          <span v-if="task.is_milestone" class="rounded bg-purple-50 text-purple-650 px-1 py-0.2 text-[9px] font-semibold uppercase">
                            Milestone
                          </span>
                        </div>
                      </div>

                      <div class="flex items-center shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          @click="openEditTask(task)"
                          class="p-1 text-slate-400 hover:text-slate-700 rounded dark:hover:text-white"
                        >
                          <Edit3 :size="12" />
                        </button>
                        <button
                          v-if="task.id"
                          @click="handleDeleteTask(task.id)"
                          class="p-1 text-red-400 hover:text-red-600 rounded"
                        >
                          <Trash2 :size="12" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 2. Required Files Checklist -->
              <div class="space-y-3">
                <div 
                  @click="toggleRequirementsExpanded(idx)"
                  class="flex items-center justify-between cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40 p-2 rounded-xl transition select-none"
                >
                  <h5 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-450 flex items-center gap-1.5">
                    <component :is="isRequirementsExpanded(idx) ? ChevronUp : ChevronDown" :size="14" class="text-slate-400" />
                    <FileText :size="14" /> Required documents
                    <span class="ml-1 text-[10px] lowercase font-medium text-slate-400">
                      ({{ getRequirementsForSection(step.soi_section).length }} files)
                    </span>
                  </h5>
                  <button
                    @click.stop="openAddRequirement(step, idx)"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                  >
                    <Plus :size="12" /> Add File
                  </button>
                </div>

                <div v-show="isRequirementsExpanded(idx)" class="space-y-2 pl-5 transition-all">
                  <div v-if="!getRequirementsForSection(step.soi_section).length" class="text-xs text-slate-400 italic pl-1">
                    No required documents configured for this phase.
                  </div>

                  <div v-else class="space-y-2">
                    <div 
                      v-for="req in getRequirementsForSection(step.soi_section)" 
                      :key="req.id" 
                      class="group flex items-start justify-between gap-3 bg-slate-50/50 p-2.5 rounded-lg border border-slate-100/80 hover:border-slate-200 dark:bg-slate-900/30 dark:border-slate-850 dark:hover:border-slate-800"
                    >
                      <div class="min-w-0">
                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ req.item_name }}</span>
                        <p v-if="req.group_name" class="text-[10px] text-slate-450 dark:text-slate-550 uppercase tracking-wide mt-0.5">{{ req.group_name }}</p>
                        
                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                          <span 
                            class="rounded px-1.5 py-0.2 text-[9px] font-semibold uppercase tracking-wider"
                            :class="req.owner_type === 'internal' ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/30 dark:text-blue-400' : 'bg-purple-50 text-purple-600 dark:bg-purple-950/30 dark:text-purple-400'"
                          >
                            {{ req.owner_type === 'internal' ? 'NDC Action' : 'Proponent File' }}
                          </span>
                          <span v-if="req.is_required" class="rounded bg-red-50 px-1.5 py-0.2 text-[9px] font-semibold text-red-650 dark:bg-red-950/20 dark:text-red-400">
                            Mandatory
                          </span>
                          <button 
                            v-if="req.template_file_path"
                            @click="downloadTemplate(req.template_file_path)"
                            class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 hover:underline dark:text-blue-400"
                          >
                            <Download :size="10" /> Template
                          </button>
                        </div>
                      </div>

                      <div class="flex items-center shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          @click="openEditRequirement(req)"
                          class="p-1 text-slate-400 hover:text-slate-700 rounded dark:hover:text-white"
                        >
                          <Edit3 :size="12" />
                        </button>
                        <button
                          v-if="req.id"
                          @click="handleDeleteRequirement(req.id)"
                          class="p-1 text-red-400 hover:text-red-600 rounded"
                        >
                          <Trash2 :size="12" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <EditStepModal
      v-if="showStepModal"
      :step="editingStep"
      :roles="roles"
      @close="showStepModal = false"
      @save="handleSaveStep"
    />

    <EditDefaultRequirementModal
      v-if="showReqModal"
      :requirement="editingReq"
      :track="selectedTrack"
      @close="showReqModal = false"
      @saved="handleReqSaved"
    />

    <EditDefaultTaskModal
      v-if="showTaskModal"
      :task="editingTask"
      :track="selectedTrack"
      :soiSection="activeTaskSection"
      :parentTasks="activeParentTasks"
      @close="showTaskModal = false"
      @saved="handleTaskSaved"
    />
  </div>
</template>
