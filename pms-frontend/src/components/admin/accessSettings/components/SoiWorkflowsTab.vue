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
  ChevronUp,
  GripVertical,
  Indent,
  Outdent,
  X
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
  requires_agreement_form: boolean;
  is_required: boolean;
  can_skip: boolean;
  role?: Role;
}

interface Workflow {
  id: number;
  name: string;
  workflow_key: string;
  workflow_group: 'origin' | 'variant' | 'lifecycle';
  display_name: string | null;
  description: string | null;
  project_type_id: number | null;
  parent_workflow_id: number | null;
  entry_action: 'manual_transition' | 'start_implementation' | 'open_divestment_case' | null;
  audiences: string[] | null;
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
const draggingTaskId = ref<number | null>(null);
const savingTaskOrder = ref(false);

const selectedTrack = ref<string>('bdg_investment');
const expandedSteps = ref<Record<number, boolean>>({});
const expandedTasks = ref<Record<number, boolean>>({});
const expandedRequirements = ref<Record<number, boolean>>({});

const fallbackTracks = [
  { value: 'bdg_investment', label: 'Traditional / External Investment', group: 'Project Categories' },
  { value: 'bdg_svf', label: 'Startup Venture', group: 'Project Categories' },
  { value: 'spg_jv', label: 'Joint Venture', group: 'Project Categories' },
  { value: 'spg_ndc_own', label: 'NDC-Initiated', group: 'Project Categories' },
  { value: 'implementation_monitoring', label: 'Implementation & Monitoring', group: 'Lifecycle Workflows' },
  { value: 'divestment', label: 'Divestment / Exit', group: 'Lifecycle Workflows' },
];
const catalogTracks = ref<typeof fallbackTracks>([]);
const databaseTracks = computed(() => workflows.value
  .filter(workflow => workflow.workflow_key && ['origin', 'variant', 'lifecycle'].includes(workflow.workflow_group))
  .map(workflow => ({
    value: workflow.workflow_key,
    label: workflow.workflow_group === 'variant'
      ? `  ↳ ${workflow.display_name || workflow.name} variant`
      : (workflow.display_name || workflow.name),
    group: workflow.workflow_group === 'lifecycle' ? 'Lifecycle Workflows' : 'Project Categories',
  })));
const tracks = computed(() => databaseTracks.value.length
  ? databaseTracks.value
  : (catalogTracks.value.length ? catalogTracks.value : fallbackTracks));
const trackGroups = ['Project Categories', 'Lifecycle Workflows'];
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

const fetchWorkflowCatalog = async () => {
  try {
    const res = await axiosInstance.get('/api/project-workflow-catalog');
    const data = res.data?.data || res.data;
    const origins = (data?.origins || []).map((origin: any) => ({
      value: origin.workflow_key || origin.key,
      label: origin.label,
      group: 'Project Categories',
    }));
    const lifecycle = (data?.lifecycle_workflows || []).map((workflow: any) => ({
      value: workflow.key,
      label: workflow.label,
      group: 'Lifecycle Workflows',
    }));
    catalogTracks.value = [...origins, ...lifecycle];
  } catch {
    catalogTracks.value = [];
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
  await Promise.all([fetchWorkflowCatalog(), fetchWorkflows(), fetchRequirements(), fetchTasks()]);
  loading.value = false;
};

onMounted(loadData);

watch(selectedTrack, () => {
  expandedSteps.value = {};
  expandedTasks.value = {};
  expandedRequirements.value = {};
});

const currentWorkflow = computed(() => {
  return workflows.value.find(workflow => workflow.workflow_key === selectedTrack.value) || null;
});

const showWorkflowModal = ref(false);
const savingWorkflow = ref(false);
const editingWorkflow = ref<Workflow | null>(null);
const workflowForm = ref({
  display_name: '',
  description: '',
  workflow_group: 'origin' as 'origin' | 'lifecycle',
  entry_action: 'manual_transition' as 'manual_transition' | 'start_implementation' | 'open_divestment_case',
  proponent_access: false,
  is_active: true,
});

const openAddWorkflow = () => {
  editingWorkflow.value = null;
  workflowForm.value = {
    display_name: '',
    description: '',
    workflow_group: 'origin',
    entry_action: 'manual_transition',
    proponent_access: false,
    is_active: true,
  };
  showWorkflowModal.value = true;
};

const openEditWorkflow = () => {
  if (!currentWorkflow.value) return;
  editingWorkflow.value = currentWorkflow.value;
  workflowForm.value = {
    display_name: currentWorkflow.value.display_name || currentWorkflow.value.name,
    description: currentWorkflow.value.description || '',
    workflow_group: currentWorkflow.value.workflow_group === 'lifecycle' ? 'lifecycle' : 'origin',
    entry_action: currentWorkflow.value.entry_action || 'manual_transition',
    proponent_access: Boolean(currentWorkflow.value.audiences?.includes('proponent')),
    is_active: currentWorkflow.value.is_active,
  };
  showWorkflowModal.value = true;
};

const saveWorkflow = async () => {
  if (!workflowForm.value.display_name.trim()) return;
  savingWorkflow.value = true;
  try {
    const payload = {
      display_name: workflowForm.value.display_name.trim(),
      description: workflowForm.value.description.trim() || null,
      workflow_group: workflowForm.value.workflow_group,
      entry_action: workflowForm.value.workflow_group === 'lifecycle'
        ? workflowForm.value.entry_action
        : null,
      audiences: workflowForm.value.workflow_group === 'origin'
        ? ['internal', ...(workflowForm.value.proponent_access ? ['proponent'] : [])]
        : ['internal'],
      is_active: workflowForm.value.is_active,
    };

    const response = editingWorkflow.value
      ? await axiosInstance.put(`/api/access-settings/workflows/${editingWorkflow.value.id}`, payload)
      : await axiosInstance.post('/api/access-settings/workflows', payload);

    selectedTrack.value = response.data.data.workflow_key;
    showWorkflowModal.value = false;
    await Promise.all([fetchWorkflows(), fetchWorkflowCatalog()]);
    toast.success(editingWorkflow.value ? 'Workflow details updated' : 'Draft workflow created. Add its steps, then activate it.');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save workflow');
  } finally {
    savingWorkflow.value = false;
  }
};

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
  return allTasks.value
    .filter(t => t.track === templateTrack.value && t.soi_section === section)
    .sort((a, b) => a.sort_order - b.sort_order || Number(a.id || 0) - Number(b.id || 0));
};

const persistTaskOrder = async (section: string, orderedTasks: Task[]) => {
  if (orderedTasks.some(task => !task.id)) return;

  savingTaskOrder.value = true;
  const normalized = orderedTasks.map((task, index) => ({
    ...task,
    sort_order: (index + 1) * 10,
  }));

  const byId = new Map(normalized.map(task => [task.id, task]));
  allTasks.value = allTasks.value.map(task => byId.get(task.id) || task);

  try {
    const response = await axiosInstance.put('/api/access-settings/default-tasks/reorder', {
      track: templateTrack.value,
      soi_section: section,
      tasks: normalized.map(task => ({
        id: task.id,
        sort_order: task.sort_order,
        parent_task_title: task.parent_task_title,
      })),
    });
    const saved = response.data.tasks as Task[];
    const savedById = new Map(saved.map(task => [task.id, task]));
    allTasks.value = allTasks.value.map(task => savedById.get(task.id) || task);
    toast.success('Work-plan order saved');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save task order');
    await fetchTasks();
  } finally {
    savingTaskOrder.value = false;
  }
};

const startTaskDrag = (task: Task, event: DragEvent) => {
  if (!task.id || savingTaskOrder.value) return;
  draggingTaskId.value = task.id;
  event.dataTransfer?.setData('text/plain', String(task.id));
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
};

const dropTask = async (target: Task) => {
  const sourceId = draggingTaskId.value;
  draggingTaskId.value = null;
  if (!sourceId || sourceId === target.id) return;

  const ordered = getTasksForSection(target.soi_section);
  const sourceIndex = ordered.findIndex(task => task.id === sourceId);
  const targetIndex = ordered.findIndex(task => task.id === target.id);
  if (sourceIndex < 0 || targetIndex < 0) return;

  const [moved] = ordered.splice(sourceIndex, 1);
  ordered.splice(targetIndex, 0, moved);
  await persistTaskOrder(target.soi_section, ordered);
};

const indentTask = async (task: Task) => {
  const ordered = getTasksForSection(task.soi_section);
  const index = ordered.findIndex(item => item.id === task.id);
  if (index <= 0) return;
  if (ordered.some(item => item.parent_task_title === task.title)) {
    toast.error('Outdent this task’s checklist items before indenting the parent.');
    return;
  }

  const previous = ordered[index - 1];
  task.parent_task_title = previous.parent_task_title || previous.title;
  await persistTaskOrder(task.soi_section, ordered);
};

const outdentTask = async (task: Task) => {
  if (!task.parent_task_title) return;
  task.parent_task_title = null;
  await persistTaskOrder(task.soi_section, getTasksForSection(task.soi_section));
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
    requires_agreement_form: false,
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
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
      <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
        <div class="min-w-0">
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Select workflow</label>
          <select
            v-model="selectedTrack"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
          >
            <optgroup v-for="group in trackGroups" :key="group" :label="group">
              <option v-for="t in tracks.filter(track => track.group === group)" :key="t.value" :value="t.value">
                {{ t.label }}
              </option>
            </optgroup>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2 md:flex">
          <button
            type="button"
            :disabled="!currentWorkflow"
            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-blue-300 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
            @click="openEditWorkflow"
          >
            <Edit3 :size="15" /> Edit
          </button>
          <button
            type="button"
            class="inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl bg-blue-600 px-3 text-xs font-bold text-white transition hover:bg-blue-700"
            @click="openAddWorkflow"
          >
            <Plus :size="15" /> Add Workflow
          </button>
        </div>
      </div>

      <div class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-3 text-xs leading-5 text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-400">
        <p>
          Project categories are selected at creation. Lifecycle workflows begin later through an authorized transition.
        </p>
        <p v-if="currentWorkflow" class="inline-flex shrink-0 items-center gap-1.5 font-semibold text-slate-600 dark:text-slate-300">
          <GitMerge :size="13" class="text-blue-600" />
          {{ currentWorkflow.steps.length }} {{ currentWorkflow.steps.length === 1 ? 'step' : 'steps' }} configured
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
          <h3 class="font-bold text-lg text-slate-900 dark:text-white">Workflow approvals, requirements, and work-plan tasks</h3>
          <p class="text-xs text-slate-500">Drag steps to reorder the approval sequence. Required documents and work-plan task templates stay grouped under each SOI phase.</p>
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
                      <span v-if="step.requires_agreement_form" class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
                        <FileText :size="10" /> Draft Agreement Form
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
                      draggable="true"
                      @dragstart="startTaskDrag(task, $event)"
                      @dragend="draggingTaskId = null"
                      @dragover.prevent
                      @drop.prevent="dropTask(task)"
                      class="group flex items-start justify-between gap-3 bg-slate-50/50 p-2.5 rounded-lg border border-slate-100/80 hover:border-slate-200 dark:bg-slate-900/30 dark:border-slate-850 dark:hover:border-slate-800"
                      :class="{
                        'ml-6 border-l-2 border-blue-200 dark:border-blue-900': task.parent_task_title,
                        'opacity-50': draggingTaskId === task.id,
                      }"
                    >
                      <div class="flex min-w-0 items-start gap-2">
                        <GripVertical :size="15" class="mt-0.5 shrink-0 cursor-grab text-slate-400" aria-hidden="true" />
                        <div class="min-w-0">
                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ task.title }}</span>
                        <p v-if="task.description" class="text-[11px] text-slate-500 mt-0.5">{{ task.description }}</p>
                        
                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                          <span class="rounded bg-slate-100 px-1 py-0.2 text-[9px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            Role: {{ task.assigned_role }}
                          </span>
                          <span class="rounded bg-blue-50/60 px-1 py-0.2 text-[9px] font-semibold text-blue-600 dark:bg-blue-950/20 dark:text-blue-400">
                            Day {{ task.days }} from application
                          </span>
                          <span class="rounded bg-slate-100 px-1 py-0.2 text-[9px] font-semibold text-slate-650 dark:bg-slate-800 dark:text-slate-400">
                            Priority: {{ task.priority }}
                          </span>
                          <span v-if="task.is_milestone" class="rounded bg-purple-50 text-purple-650 px-1 py-0.2 text-[9px] font-semibold uppercase">
                            Milestone
                          </span>
                        </div>
                        </div>
                      </div>

                      <div class="flex items-center shrink-0 opacity-60 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                        <button
                          type="button"
                          :disabled="savingTaskOrder"
                          title="Indent under previous task"
                          class="p-1 text-slate-400 hover:text-blue-700 rounded disabled:opacity-40 dark:hover:text-blue-300"
                          @click="indentTask(task)"
                        >
                          <Indent :size="13" />
                        </button>
                        <button
                          type="button"
                          :disabled="savingTaskOrder || !task.parent_task_title"
                          title="Outdent to parent level"
                          class="p-1 text-slate-400 hover:text-blue-700 rounded disabled:opacity-30 dark:hover:text-blue-300"
                          @click="outdentTask(task)"
                        >
                          <Outdent :size="13" />
                        </button>
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
      :track="templateTrack"
      :soiSection="activeTaskSection"
      :parentTasks="activeParentTasks"
      @close="showTaskModal = false"
      @saved="handleTaskSaved"
    />

    <div v-if="showWorkflowModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
      <form
        class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-950"
        @submit.prevent="saveWorkflow"
      >
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
          <div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">
              {{ editingWorkflow ? 'Edit Workflow' : 'Add Workflow' }}
            </h3>
            <p v-if="editingWorkflow" class="mt-0.5 text-xs text-slate-500">
              Key: {{ editingWorkflow.workflow_key }}
            </p>
          </div>
          <button
            type="button"
            aria-label="Close workflow form"
            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-900 dark:hover:text-white"
            @click="showWorkflowModal = false"
          >
            <X :size="18" />
          </button>
        </div>

        <div class="space-y-4 p-6">
          <div>
            <label for="workflow-display-name" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Workflow Name</label>
            <input
              id="workflow-display-name"
              v-model="workflowForm.display_name"
              required
              maxlength="120"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
              placeholder="Enter a clear workflow name"
            />
          </div>

          <div v-if="!editingWorkflow">
            <label for="workflow-group" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Workflow Type</label>
            <select
              id="workflow-group"
              v-model="workflowForm.workflow_group"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
              <option value="origin">Project Category</option>
              <option value="lifecycle">Lifecycle Workflow</option>
            </select>
            <p class="mt-1 text-xs leading-5 text-slate-500">
              Project categories appear during creation. Lifecycle workflows are entered after development approval.
            </p>
          </div>

          <div v-if="workflowForm.workflow_group === 'lifecycle'">
            <label for="workflow-entry-action" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Entry Action</label>
            <select
              id="workflow-entry-action"
              v-model="workflowForm.entry_action"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
              <option value="manual_transition">Manual transition</option>
              <option value="start_implementation">Start Implementation</option>
              <option value="open_divestment_case">Open Exit Case</option>
            </select>
          </div>

          <label v-if="workflowForm.workflow_group === 'origin'" class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
            <input v-model="workflowForm.proponent_access" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
            <span>
              <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Available to proponents</span>
              <span class="mt-0.5 block text-xs text-slate-500">Allow this route to appear in external project applications.</span>
            </span>
          </label>

          <div>
            <label for="workflow-description" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Description</label>
            <textarea
              id="workflow-description"
              v-model="workflowForm.description"
              rows="3"
              maxlength="1000"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
              placeholder="Explain when this workflow should be used"
            ></textarea>
          </div>

          <label v-if="editingWorkflow" class="flex cursor-pointer items-center gap-3">
            <input v-model="workflowForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Workflow is active</span>
          </label>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4 dark:border-slate-800">
          <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 dark:border-slate-800 dark:text-slate-300" @click="showWorkflowModal = false">
            Cancel
          </button>
          <button type="submit" :disabled="savingWorkflow || !workflowForm.display_name.trim()" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
            {{ savingWorkflow ? 'Saving...' : editingWorkflow ? 'Save Changes' : 'Create Workflow' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
