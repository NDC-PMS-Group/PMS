<!-- src/components/projects/ProjectApprovalTimeline.vue -->
<template>
  <div class="approval-timeline" :class="{ 'dark-mode': darkMode }">

    <div v-if="loading" class="spinner-container">
      <div class="spinner-sm"></div>
      <span>Loading SOI workflow...</span>
    </div>

    <div v-else-if="hasUnifiedContent">
      <div v-if="currentApproval" class="approval-header">
        <div class="ah-left">
          <h3 class="ah-title">SOI Flow and Work Plan</h3>
          <span class="ah-badge" :class="statusClass(currentApproval.overall_status)">
            {{ formatStatus(currentApproval.overall_status) }}
          </span>
        </div>
        <button v-if="canApprove" class="btn-action" @click="emit('open-action')">
          <Edit3Icon class="btn-icon" /> {{ actionButtonLabel }}
        </button>
      </div>

      <div v-if="!canApprove && noActionMessage" class="action-note">
        <InfoIcon class="note-icon" />
        <span>{{ noActionMessage }}</span>
      </div>

      <div class="soi-sections">
        <section
          v-for="section in groupedSections"
          :key="section.key"
          class="soi-section"
          :class="{ active: section.isActive }"
        >
          <div class="section-head">
            <div>
              <p class="section-kicker">{{ section.ordinal }}</p>
              <h4>{{ section.label }}</h4>
            </div>
            <div class="section-summary">
              <span v-if="section.steps.length">{{ section.steps.length }} step{{ section.steps.length === 1 ? '' : 's' }}</span>
              <span v-if="section.missingReqsCount" class="missing-badge-timeline">
                ⚠️ {{ section.missingReqsCount }} missing doc{{ section.missingReqsCount === 1 ? '' : 's' }}
              </span>
              <span>{{ section.completedChecklist }}/{{ section.totalChecklist }} checklist</span>
            </div>
          </div>

          <div v-if="section.steps.length" class="workflow-steps">
            <div
              v-for="(step, index) in section.steps"
              :key="step.id"
              class="wf-step"
              :class="getStepState(step.id)"
            >
              <div class="wfs-indicator">
                <div v-if="index < section.steps.length - 1" class="wfs-line"></div>
                <div class="wfs-dot">
                  <CheckIcon v-if="getStepState(step.id) === 'completed'" class="dot-icon" />
                  <div v-else-if="getStepState(step.id) === 'current'" class="dot-pulse"></div>
                  <span v-else class="dot-num">{{ step.step_order }}</span>
                </div>
              </div>

              <div class="wfs-content">
                <h5 class="wfc-title">{{ step.step_name }}</h5>
                <div v-if="step.role" class="wfc-role">
                  <UsersIcon class="r-icon" />{{ step.role.name }}
                </div>

                <div v-if="getStepRecord(step.id)" class="wfc-record">
                  <div class="rec-head">
                    <span class="rec-user">{{ getStepRecord(step.id)?.approver?.name || getStepRecord(step.id)?.approver?.full_name || 'System' }}</span>
                    <span class="rec-date">{{ fmtDateTime(getStepRecord(step.id)?.reviewed_at || getStepRecord(step.id)?.submitted_at) }}</span>
                  </div>
                  <div class="rec-status" :class="statusClass(getStepRecord(step.id)?.status || '')">
                    {{ formatStatus(getStepRecord(step.id)?.status || '') }}
                  </div>
                  <p v-if="getStepRecord(step.id)?.conditions" class="rec-cond"><strong>Conditions:</strong> {{ getStepRecord(step.id)?.conditions }}</p>
                  <p v-if="getStepRecord(step.id)?.comments" class="rec-comm">"{{ getStepRecord(step.id)?.comments }}"</p>
                  <div v-if="getStepState(step.id) === 'current'" class="current-action-card">
                    <span>{{ currentStepMessage }}</span>
                    <button v-if="canApprove" class="btn-action inline" @click="emit('open-action')">
                      <Edit3Icon class="btn-icon" /> {{ actionButtonLabel }}
                    </button>
                  </div>
                </div>

                <div v-else-if="getStepState(step.id) === 'current'" class="wfc-pending">
                  <span>{{ currentStepMessage }}</span>
                  <button v-if="canApprove" class="btn-action inline" @click="emit('open-action')">
                    <Edit3Icon class="btn-icon" /> {{ actionButtonLabel }}
                  </button>
                </div>

                <!-- Collapsible Step Checklists -->
                <div class="step-checklists-container" style="margin-top: 0.75rem;">
                  <!-- Requirements Checklist -->
                  <div class="step-checklist-group">
                    <div 
                      class="step-checklist-header" 
                      @click="toggleRequirementsExpanded(step.id)"
                    >
                      <span class="sch-title">
                        <component :is="isRequirementsExpanded(step.id) ? ChevronUpIcon : ChevronDownIcon" class="sch-chevron" />
                        <FileTextIcon class="sch-icon" />
                        Required Documents ({{ getRequirementsForStep(step, section).length }})
                      </span>
                    </div>
                    
                    <div v-show="isRequirementsExpanded(step.id)" class="step-checklist-body">
                      <div v-if="getRequirementsForStep(step, section).length" class="timeline-requirements-list" style="margin-top: 0.5rem;">
                        <div
                          v-for="req in getRequirementsForStep(step, section)"
                          :key="req.id"
                          class="timeline-requirement-card inline-card"
                          :class="{ 'is-missing': isRequirementMissing(req) }"
                        >
                          <div class="tr-main">
                            <span class="tr-name">{{ req.item_name }}</span>
                            <div class="tr-meta">
                              <span class="tr-owner" :class="req.owner_type">
                                {{ req.owner_type === 'internal' ? 'Internal NDC' : 'Proponent' }}
                              </span>
                              <span v-if="req.is_required" class="tr-badge required">Required</span>
                              <span v-else class="tr-badge optional">Optional</span>
                              <span v-if="isRequirementMissing(req)" class="tr-badge missing-flag-badge">⚠️ Missing</span>
                            </div>
                          </div>

                          <div class="tr-action-area">
                            <span class="req-status-badge" :class="req.status">
                              {{ formatRequirementStatus(req.status) }}
                            </span>
                            <button
                              v-if="req.template_file_path"
                              class="btn-download-template"
                              title="Download document template"
                              @click="downloadTemplate(req.template_file_path)"
                            >
                              <DownloadIcon class="action-icon" /> Template
                            </button>
                            <button
                              v-if="req.document && !req.document.is_deleted"
                              class="btn-view-doc"
                              @click="emit('view-document', req.document)"
                            >
                              View File
                            </button>
                          </div>
                        </div>
                      </div>
                      <p v-else class="empty-checklist" style="margin-top: 0.5rem; padding-left: 1.5rem;">No required documents for this step.</p>
                    </div>
                  </div>

                  <!-- Work Plan Tasks Checklist -->
                  <div class="step-checklist-group" style="margin-top: 0.5rem;">
                    <div 
                      class="step-checklist-header" 
                      @click="toggleTasksExpanded(step.id)"
                    >
                      <span class="sch-title">
                        <component :is="isTasksExpanded(step.id) ? ChevronUpIcon : ChevronDownIcon" class="sch-chevron" />
                        <ListChecksIcon class="sch-icon" />
                        Work-plan tasks ({{ getTasksForStep(step, section).length }})
                      </span>
                    </div>

                    <div v-show="isTasksExpanded(step.id)" class="step-checklist-body">
                      <div v-if="getTasksForStep(step, section).length" class="section-task-list" style="margin-top: 0.5rem;">
                        <article v-for="task in getTasksForStep(step, section)" :key="task.id" class="section-task inline-task">
                          <div class="task-row">
                            <div class="task-main">
                              <div class="task-title-line">
                                <strong :style="task.status === 'completed' ? 'text-decoration: line-through; opacity: 0.6;' : ''">{{ task.title }}</strong>
                                <span class="task-status" :class="task.status">{{ formatTaskStatus(task.status) }}</span>
                              </div>
                              <p v-if="!milestoneOnly && task.description">{{ task.description }}</p>
                              <div v-if="!milestoneOnly" class="task-meta">
                                <span>{{ task.assigned_to?.full_name || task.assigned_to?.name || 'Unassigned' }}</span>
                                <span v-if="task.due_date" :class="{ danger: task.is_overdue }">Due {{ fmtDate(task.due_date) }}</span>
                                <span v-if="task.priority">{{ task.priority }}</span>
                              </div>
                            </div>

                            <div class="task-progress">
                              <span>{{ task.progress_percentage || 0 }}%</span>
                              <div class="mini-track">
                                <div class="mini-fill" :style="{ width: `${task.progress_percentage || 0}%` }"></div>
                              </div>
                              <div v-if="!task.subtasks?.length" class="task-checkbox-wrap">
                                <input
                                  type="checkbox"
                                  :checked="task.status === 'completed'"
                                  :disabled="!canUpdateTasks || isTaskUpdating(task.id)"
                                  @change="emit('set-task-status', task, task.status === 'completed' ? 'in_progress' : 'completed')"
                                  class="task-checkbox"
                                  :class="{ 'cursor-pointer': canUpdateTasks }"
                                />
                              </div>
                            </div>
                          </div>

                          <div v-if="task.subtasks?.length" class="subtask-list">
                            <div v-for="subtask in task.subtasks" :key="subtask.id" class="subtask-row">
                              <div class="subtask-check" :class="{ done: subtask.status === 'completed' }">
                                <CheckIcon v-if="subtask.status === 'completed'" class="subtask-check-icon" />
                              </div>
                              <div class="subtask-copy">
                                <span :style="subtask.status === 'completed' ? 'text-decoration: line-through; opacity: 0.6;' : ''">{{ subtask.title }}</span>
                                <small>
                                  {{ formatTaskStatus(subtask.status) }}
                                  <template v-if="!milestoneOnly && subtask.assigned_to"> · {{ subtask.assigned_to.full_name || subtask.assigned_to.name }}</template>
                                  <template v-if="!milestoneOnly && subtask.due_date"> · Due {{ fmtDate(subtask.due_date) }}</template>
                                </small>
                              </div>
                              <div class="subtask-actions">
                                <input
                                  type="checkbox"
                                  :checked="subtask.status === 'completed'"
                                  :disabled="!canUpdateTasks || isTaskUpdating(subtask.id)"
                                  @change="emit('set-task-status', subtask, subtask.status === 'completed' ? 'in_progress' : 'completed', task)"
                                  class="subtask-checkbox"
                                  :class="{ 'cursor-pointer': canUpdateTasks }"
                                />
                              </div>
                            </div>
                          </div>
                        </article>
                      </div>
                      <p v-else class="empty-checklist" style="margin-top: 0.5rem; padding-left: 1.5rem;">{{ emptyChecklistMessage }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="section.requirements.length || section.tasks.length" class="section-checklists-container">
            <div class="step-checklist-group">
              <div class="step-checklist-header static">
                <span class="sch-title">
                  <FileTextIcon class="sch-icon" />
                  Required Documents ({{ section.requirements.length }})
                </span>
              </div>

              <div class="step-checklist-body">
                <div v-if="section.requirements.length" class="timeline-requirements-list">
                  <div
                    v-for="req in section.requirements"
                    :key="req.id"
                    class="timeline-requirement-card inline-card"
                    :class="{ 'is-missing': isRequirementMissing(req) }"
                  >
                    <div class="tr-main">
                      <span class="tr-name">{{ req.item_name }}</span>
                      <div class="tr-meta">
                        <span class="tr-owner" :class="req.owner_type">
                          {{ req.owner_type === 'internal' ? 'Internal NDC' : 'Proponent' }}
                        </span>
                        <span v-if="req.is_required" class="tr-badge required">Required</span>
                        <span v-else class="tr-badge optional">Optional</span>
                        <span v-if="isRequirementMissing(req)" class="tr-badge missing-flag-badge">⚠️ Missing</span>
                      </div>
                    </div>

                    <div class="tr-action-area">
                      <span class="req-status-badge" :class="req.status">
                        {{ formatRequirementStatus(req.status) }}
                      </span>
                      <button
                        v-if="req.template_file_path"
                        class="btn-download-template"
                        title="Download document template"
                        @click="downloadTemplate(req.template_file_path)"
                      >
                        <DownloadIcon class="action-icon" /> Template
                      </button>
                      <button
                        v-if="req.document && !req.document.is_deleted"
                        class="btn-view-doc"
                        @click="emit('view-document', req.document)"
                      >
                        View File
                      </button>
                    </div>
                  </div>
                </div>
                <p v-else class="empty-checklist">No required documents for this section.</p>
              </div>
            </div>

            <div class="step-checklist-group">
              <div class="step-checklist-header static">
                <span class="sch-title">
                  <ListChecksIcon class="sch-icon" />
                  Work-plan tasks ({{ section.tasks.length }})
                </span>
              </div>

              <div class="step-checklist-body">
                <div v-if="section.tasks.length" class="section-task-list">
                  <article v-for="task in section.tasks" :key="task.id" class="section-task inline-task">
                    <div class="task-row">
                      <div class="task-main">
                        <div class="task-title-line">
                          <strong :style="task.status === 'completed' ? 'text-decoration: line-through; opacity: 0.6;' : ''">{{ task.title }}</strong>
                          <span class="task-status" :class="task.status">{{ formatTaskStatus(task.status) }}</span>
                        </div>
                        <p v-if="!milestoneOnly && task.description">{{ task.description }}</p>
                        <div v-if="!milestoneOnly" class="task-meta">
                          <span>{{ task.assigned_to?.full_name || task.assigned_to?.name || 'Unassigned' }}</span>
                          <span v-if="task.due_date" :class="{ danger: task.is_overdue }">Due {{ fmtDate(task.due_date) }}</span>
                          <span v-if="task.priority">{{ task.priority }}</span>
                        </div>
                      </div>

                      <div class="task-progress">
                        <span>{{ task.progress_percentage || 0 }}%</span>
                        <div class="mini-track">
                          <div class="mini-fill" :style="{ width: `${task.progress_percentage || 0}%` }"></div>
                        </div>
                        <div v-if="!task.subtasks?.length" class="task-checkbox-wrap">
                          <input
                            type="checkbox"
                            :checked="task.status === 'completed'"
                            :disabled="!canUpdateTasks || isTaskUpdating(task.id)"
                            @change="emit('set-task-status', task, task.status === 'completed' ? 'in_progress' : 'completed')"
                            class="task-checkbox"
                            :class="{ 'cursor-pointer': canUpdateTasks }"
                          />
                        </div>
                      </div>
                    </div>

                    <div v-if="task.subtasks?.length" class="subtask-list">
                      <div v-for="subtask in task.subtasks" :key="subtask.id" class="subtask-row">
                        <div class="subtask-check" :class="{ done: subtask.status === 'completed' }">
                          <CheckIcon v-if="subtask.status === 'completed'" class="subtask-check-icon" />
                        </div>
                        <div class="subtask-copy">
                          <span :style="subtask.status === 'completed' ? 'text-decoration: line-through; opacity: 0.6;' : ''">{{ subtask.title }}</span>
                          <small>
                            {{ formatTaskStatus(subtask.status) }}
                            <template v-if="!milestoneOnly && subtask.assigned_to"> · {{ subtask.assigned_to.full_name || subtask.assigned_to.name }}</template>
                            <template v-if="!milestoneOnly && subtask.due_date"> · Due {{ fmtDate(subtask.due_date) }}</template>
                          </small>
                        </div>
                        <div class="subtask-actions">
                          <input
                            type="checkbox"
                            :checked="subtask.status === 'completed'"
                            :disabled="!canUpdateTasks || isTaskUpdating(subtask.id)"
                            @change="emit('set-task-status', subtask, subtask.status === 'completed' ? 'in_progress' : 'completed', task)"
                            class="subtask-checkbox"
                            :class="{ 'cursor-pointer': canUpdateTasks }"
                          />
                        </div>
                      </div>
                    </div>
                  </article>
                </div>
                <p v-else class="empty-checklist">{{ emptyChecklistMessage }}</p>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div v-else class="empty-state">
      <div class="empty-icon"><ActivityIcon /></div>
      <p>No active SOI workflow or work plan for this project.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import { useAuthStore } from '@/store/auth';
import type { ProjectApproval, ApprovalStepRecord, Task as ProjectTask, ApprovalStep, ProjectRequirement, Document as ProjectDocument } from '@/types/project';
import {
  Check as CheckIcon,
  Users as UsersIcon,
  Edit3 as Edit3Icon,
  Activity as ActivityIcon,
  Info as InfoIcon,
  ListChecks as ListChecksIcon,
  FileText as FileTextIcon,
  Download as DownloadIcon,
  ChevronDown as ChevronDownIcon,
  ChevronUp as ChevronUpIcon,
} from 'lucide-vue-next';
import {
  SOI_SECTION_LABELS,
  SOI_SECTION_ORDER,
  SOI_TRACK_PHASE_DEFINITIONS,
  getTaskChecklistItems,
  normalizeSoiSection,
  resolveSoiTaskGroupKey,
  resolveSoiTaskSection,
} from '@/utils/soiWorkflow';

type TaskStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled';

interface Props {
  currentApproval: ProjectApproval | null;
  approvalHistory: ApprovalStepRecord[];
  workPlanTasks?: ProjectTask[];
  requirements?: ProjectRequirement[];
  canUpdateTasks?: boolean;
  milestoneOnly?: boolean;
  emptyChecklistMessage?: string;
  darkMode?: boolean;
  updatingTaskIds?: Set<number>;
  loading?: boolean;
  projectCreatorId?: number;
  processTrack?: string | null;
  activeTab?: string;
}

const props = withDefaults(defineProps<Props>(), {
  workPlanTasks: () => [],
  requirements: () => [],
  canUpdateTasks: false,
  milestoneOnly: false,
  emptyChecklistMessage: 'No automated checklist items for this section yet.',
  darkMode: false,
  updatingTaskIds: () => new Set<number>(),
  loading: false,
  processTrack: null,
  activeTab: 'overview',
});

const emit = defineEmits<{
  'open-action': [];
  'set-task-status': [task: ProjectTask, status: TaskStatus, parentTask?: ProjectTask];
  'view-document': [document: ProjectDocument];
}>();

const completedRequirementStatuses = ['received', 'approved', 'approved_with_conditions', 'waived'];

function isRequirementMissing(req: ProjectRequirement): boolean {
  return req.is_required && !completedRequirementStatuses.includes(req.status || '');
}

function deriveRequirementGroupKey(req: ProjectRequirement): string {
  const track = String(props.processTrack || '').toLowerCase();
  const section = String(req.soi_section || '').toLowerCase();
  const gate = String(req.gate_step || '').toLowerCase();

  if (track === 'spg_jv') {
    if (section === 'intake') return 'spg_jv_concept';
    if (section === 'due_diligence') return 'spg_jv_study';
    if (gate === 'spg_jv_mancom_project_decision' || gate === 'spg_jv_board_project_approval') return 'spg_jv_mancom_board';
    if (gate === 'spg_jv_neda_icc' || gate === 'spg_jv_jva_terms_jvsc') return 'spg_jv_neda_jvsc';
    if (gate === 'spg_jv_selection_award' || gate === 'spg_jv_final_award' || gate === 'spg_jv_jva_signing' || section === 'agreement_fund_release') return 'spg_jv_selection_signing';
    return section;
  }

  if (track === 'spg_ndc_own') {
    if (section === 'intake') return 'spg_own_concept';
    if (section === 'due_diligence') return 'spg_own_study';
    if (section === 'management_review') return 'spg_own_mancom';
    if (section === 'board_approval') return 'spg_own_board';
    if (gate === 'spg_ndc_own_ded_construction' || section === 'agreement_fund_release') return 'spg_own_ded';
    if (gate === 'spg_ndc_own_turnover' || section === 'implementation_monitoring') return 'spg_own_turnover';
    return section;
  }

  return section;
}

const formatRequirementStatus = (status: string) => {
  if (!status) return 'Pending Request';
  return status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const hasUnifiedContent = computed(() =>
  Boolean(props.currentApproval) || groupedSections.value.length > 0
);

function deriveStepSection(step: ApprovalStep): string {
  return normalizeSection(step.soi_section, step.step_name);
}

function deriveTaskSection(task: ProjectTask): string {
  return resolveSoiTaskSection(task);
}

function deriveStepGroupKey(step: ApprovalStep): string {
  const phase = trackPhaseDefinition.value?.find((item) => item.stepOrders.includes(step.step_order));
  return phase?.key || deriveStepSection(step);
}

function deriveTaskGroupKey(task: ProjectTask): string {
  return resolveSoiTaskGroupKey(task, props.processTrack);
}

function checklistItemsForTask(task: ProjectTask): ProjectTask[] {
  return getTaskChecklistItems(task) as ProjectTask[];
}

function normalizeSection(value?: string | null, fallback?: string | null): string {
  return normalizeSoiSection(value, fallback);
}


const authStore = useAuthStore();
const currentUserRole = computed(() => authStore.userRole);
const currentUserId = computed(() => authStore.user?.id);
const isSuperAdmin = computed(() => {
  const roleName = currentUserRole.value?.toLowerCase();
  const roleId = Number(authStore.user?.role?.id);
  return roleName === 'superadmin' || roleId === 1;
});
const milestoneOnly = computed(() => props.milestoneOnly);

const terminalStatuses = ['approved', 'approved_with_conditions', 'completed', 'rejected'];
const sectionOrder = SOI_SECTION_ORDER;
const sectionLabels: Record<string, string> = SOI_SECTION_LABELS;
type TimelinePhaseDefinition = {
  key: string;
  label: string;
  stepOrders: number[];
  taskPrefixes: string[];
};
const trackPhaseDefinitions: Record<string, TimelinePhaseDefinition[]> = SOI_TRACK_PHASE_DEFINITIONS;

const steps = computed(() => {
  console.log("props.currentApproval:", JSON.stringify(props.currentApproval));
  if (!props.currentApproval?.workflow?.steps) {
    console.log("workflow or steps is missing on currentApproval");
    return [];
  }
  return [...props.currentApproval.workflow.steps].sort((a, b) => a.step_order - b.step_order);
});

const isProponentStep = computed(() => {
  const step = props.currentApproval?.current_step;
  if (!step) return false;

  const roleName = step.role?.name?.toLowerCase() || '';
  const stepName = step.step_name?.toLowerCase() || '';

  return roleName === 'proponent' || stepName.includes('proponent submission');
});

const canApprove = computed(() => {
  if (!props.currentApproval || !props.currentApproval.current_step) return false;
  if (terminalStatuses.includes(props.currentApproval.overall_status)) return false;

  if (props.currentApproval.overall_status === 'returned') {
    if (isProponentStep.value) {
      return isSuperAdmin.value || props.projectCreatorId === currentUserId.value;
    }
    return isSuperAdmin.value
      || props.currentApproval.current_step.role?.name?.toLowerCase() === currentUserRole.value?.toLowerCase();
  }

  if (isSuperAdmin.value) return true;

  if (isProponentStep.value) {
    return props.projectCreatorId === currentUserId.value;
  }

  return props.currentApproval.current_step.role?.name?.toLowerCase() === currentUserRole.value?.toLowerCase();
});

const actionButtonLabel = computed(() => {
  if (props.currentApproval?.overall_status !== 'returned') {
    return 'Take Action';
  }

  return isProponentStep.value ? 'Resubmit' : 'Resolve Return';
});

const currentStepMessage = computed(() => {
  if (!props.currentApproval) return '';
  if (props.currentApproval.overall_status === 'returned') {
    if (canApprove.value) {
      return isProponentStep.value
        ? 'Returned for revision. Update the project details or files, then resubmit from this step.'
        : 'Returned for revision. Review the comments, update this stage, then send it forward again.';
    }
    return `Returned to ${assignedRoleLabel.value}. Waiting for the assigned reviewer to resolve this SOI step.`;
  }
  if (canApprove.value) {
    return 'This is the current SOI step assigned to you.';
  }
  return 'Waiting for the assigned reviewer to complete this SOI step.';
});

const roleAccountHints: Record<string, string> = {
  'project officer': 'admin@ndc.gov.ph or pdo@ndc.gov.ph',
  'account officer': 'admin@ndc.gov.ph or pdo@ndc.gov.ph',
  'workgroup head': 'wgh@ndc.gov.ph',
  mancom: 'mancom@ndc.gov.ph',
  board: 'board@ndc.gov.ph',
  'legal and finance': 'legalfinance@ndc.gov.ph',
  'investment committee': 'ic@ndc.gov.ph',
  proponent: 'the project proponent account',
};

const assignedRoleLabel = computed(() =>
  props.currentApproval?.current_step?.role?.name || 'assigned reviewer'
);

const assignedRoleHint = computed(() => {
  const key = assignedRoleLabel.value.toLowerCase();
  return roleAccountHints[key] || '';
});

const noActionMessage = computed(() => {
  if (!props.currentApproval) return '';
  
  const isProponent = props.milestoneOnly || currentUserRole.value?.toLowerCase() === 'proponent' || Number(authStore.user?.role?.id) === 7;
  
  if (props.currentApproval.overall_status === 'returned') {
    if (canApprove.value) return '';
    if (isProponent) {
      return `Returned for revision and assigned to ${assignedRoleLabel.value}.`;
    }
    const hint = assignedRoleHint.value
      ? ` Log in as ${assignedRoleHint.value} or Super Admin to act.`
      : ' Log in as the assigned role or Super Admin to act.';
    return `Returned for revision and assigned to ${assignedRoleLabel.value}.${hint}`;
  }
  if (terminalStatuses.includes(props.currentApproval.overall_status) || !props.currentApproval.current_step) {
    return 'This SOI workflow has no pending step to act on.';
  }
  if (isSuperAdmin.value) return '';
  if (isProponent) {
    return `Assigned to ${assignedRoleLabel.value}.`;
  }
  const hint = assignedRoleHint.value
    ? ` Log in as ${assignedRoleHint.value} or Super Admin to act.`
    : ' Log in as the assigned role or Super Admin to act.';
  return `Assigned to ${assignedRoleLabel.value}.${hint}`;
});

const currentStepSection = computed(() =>
  props.currentApproval?.current_step
    ? deriveStepSection(props.currentApproval.current_step)
    : null
);
const currentStepGroupKey = computed(() =>
  props.currentApproval?.current_step
    ? deriveStepGroupKey(props.currentApproval.current_step)
    : null
);
const trackPhaseDefinition = computed(() => {
  const track = String(props.processTrack || '').toLowerCase();
  return trackPhaseDefinitions[track] || null;
});

const sortedTasks = computed(() =>
  [...props.workPlanTasks].sort((a, b) => {
    const aDue = a.due_date ? new Date(a.due_date).getTime() : Number.POSITIVE_INFINITY;
    const bDue = b.due_date ? new Date(b.due_date).getTime() : Number.POSITIVE_INFINITY;
    if (aDue !== bDue) return aDue - bDue;
    return a.id - b.id;
  })
);

const groupedSections = computed(() => {
  if (trackPhaseDefinition.value) {
    return trackPhaseDefinition.value
      .map((phase, index) => {
        const sectionSteps = steps.value.filter((step) => phase.stepOrders.includes(step.step_order));
        const sectionTasks = sortedTasks.value.filter((task) => deriveTaskGroupKey(task) === phase.key);
        const checklistItems = sectionTasks.flatMap((task) => checklistItemsForTask(task));
        const sectionReqs = (props.requirements || []).filter((req) => deriveRequirementGroupKey(req) === phase.key);

        return {
          key: phase.key,
          label: phase.label,
          ordinal: `Phase ${index + 1}`,
          steps: sectionSteps,
          tasks: sectionTasks,
          requirements: sectionReqs,
          totalChecklist: checklistItems.length,
          completedChecklist: checklistItems.filter((task) => task.status === 'completed').length,
          missingReqsCount: sectionReqs.filter(isRequirementMissing).length,
          isActive: currentStepGroupKey.value === phase.key,
        };
      })
      .filter((section) => section.steps.length || section.tasks.length || section.requirements.length);
  }

  const keys = new Set<string>();
  steps.value.forEach((step) => keys.add(deriveStepSection(step)));
  sortedTasks.value.forEach((task) => keys.add(deriveTaskSection(task)));
  (props.requirements || []).forEach((req) => keys.add(deriveRequirementGroupKey(req)));

  const orderedKeys = [
    ...sectionOrder.filter((key) => keys.has(key)),
    ...Array.from(keys).filter((key) => !sectionOrder.includes(key)),
  ];

  return orderedKeys.map((key, index) => {
    const sectionSteps = steps.value.filter((step) => deriveStepSection(step) === key);
    const sectionTasks = sortedTasks.value.filter((task) => deriveTaskSection(task) === key);
    const checklistItems = sectionTasks.flatMap((task) => checklistItemsForTask(task));
    const sectionReqs = (props.requirements || []).filter((req) => deriveRequirementGroupKey(req) === key);

    return {
      key,
      label: sectionLabels[key] || formatStatus(key),
      ordinal: `Section ${index + 1}`,
      steps: sectionSteps,
      tasks: sectionTasks,
      requirements: sectionReqs,
      totalChecklist: checklistItems.length,
      completedChecklist: checklistItems.filter((task) => task.status === 'completed').length,
      missingReqsCount: sectionReqs.filter(isRequirementMissing).length,
      isActive: currentStepSection.value === key,
    };
  });
});




const getStepRecord = (stepId: number) => {
  return props.approvalHistory.find((record) => record.step_id === stepId);
};

const getStepState = (stepId: number) => {
  if (props.currentApproval?.current_step_id === stepId) return 'current';
  const record = getStepRecord(stepId);
  if (record && record.status !== 'returned') return 'completed';
  if (record && record.status === 'returned') return 'returned';
  return 'pending';
};

const isTaskUpdating = (taskId: number) => props.updatingTaskIds.has(taskId);

const formatStatus = (status: string) => {
  return status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const formatTaskStatus = (status: string) => formatStatus(status || 'pending');

const statusClass = (status: string) => {
  if (status.includes('approved')) return 's-approved';
  if (status.includes('returned') || status.includes('rejected')) return 's-returned';
  if (status.includes('evaluation') || status.includes('approval') || status.includes('pending')) return 's-pending';
  if (status === 'completed') return 's-completed';
  return 's-default';
};

const fmtDateTime = (date?: string | null) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const fmtDate = (date?: string | null) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
const downloadTemplate = (filePath: string) => {
  const token = localStorage.getItem('token') || sessionStorage.getItem('token');
  const tokenQuery = token ? `&token=${token}` : '';
  window.open(`/api/lookup/templates/download?file=${encodeURIComponent(filePath)}${tokenQuery}`, '_blank');
};

const expandedTasks = ref<Record<number, boolean>>({});
const expandedRequirements = ref<Record<number, boolean>>({});

const isTasksExpanded = (stepId: number) => {
  if (expandedTasks.value[stepId] !== undefined) {
    return expandedTasks.value[stepId];
  }
  return getStepState(stepId) === 'current';
};

const toggleTasksExpanded = (stepId: number) => {
  expandedTasks.value[stepId] = !isTasksExpanded(stepId);
};

const isRequirementsExpanded = (stepId: number) => {
  if (expandedRequirements.value[stepId] !== undefined) {
    return expandedRequirements.value[stepId];
  }
  return getStepState(stepId) === 'current';
};

const toggleRequirementsExpanded = (stepId: number) => {
  expandedRequirements.value[stepId] = !isRequirementsExpanded(stepId);
};

function getGateStepsForStep(step: ApprovalStep): string[] {
  const stepName = String(step.step_name || '').toLowerCase();
  const roleName = String(step.role?.name || '').toLowerCase();
  const text = `${stepName} ${roleName}`;
  const track = String(props.processTrack || '').toLowerCase();

  if (track === 'spg_jv') {
    if (text.includes('mancom jv project decision')) return ['spg_jv_mancom_project_decision'];
    if (text.includes('board approval of jv project')) return ['spg_jv_board_project_approval'];
    if (text.includes('neda-icc') || text.includes('neda icc')) return ['spg_jv_neda_icc'];
    if (text.includes('jva terms') || text.includes('jv-sc') || text.includes('jv sc')) return ['spg_jv_jva_terms_jvsc'];
    if (text.includes('jv partner selection')) return ['spg_jv_selection_award'];
    if (text.includes('final board approval')) return ['spg_jv_final_award'];
    if (text.includes('signing of jva')) return ['spg_jv_jva_signing'];
    return [];
  }

  if (track === 'spg_ndc_own') {
    if (text.includes('mancom project decision')) return ['spg_ndc_own_mancom_project_decision'];
    if (stepName.trim() === 'board approval') return ['spg_ndc_own_board_approval'];
    if (text.includes('ded') || text.includes('construction procurement') || text.includes('construction agreement')) return ['spg_ndc_own_ded_construction'];
    if (text.includes('construction implementation') || text.includes('turn-over') || text.includes('turnover')) return ['spg_ndc_own_turnover'];
    return [];
  }

  const gates: string[] = [];
  if (text.includes('mancom') || text.includes('management committee')) {
    gates.push('mancom');
  }
  if (text.includes('board')) {
    gates.push('board');
  }
  if (text.includes('legal') || text.includes('finance') || text.includes('agreement') || text.includes('fund release') || text.includes('signing')) {
    gates.push('fund_release');
  }
  if (text.includes('neda') || text.includes('icc') || text.includes('selection') || text.includes('award') || text.includes('partner selection')) {
    gates.push('jv');
  }
  if (text.includes('monitor') || text.includes('milestone') || text.includes('adjustment')) {
    gates.push('monitoring');
  }
  if (text.includes('divest')) {
    gates.push('divestment');
  }
  return Array.from(new Set(gates));
}

function getStepTaskKeywords(step: ApprovalStep): string[] {
  const stepName = String(step.step_name || '').toLowerCase();
  const keywords: string[] = [];
  if (stepName.includes('mancom') || stepName.includes('management')) {
    keywords.push('mancom', 'management');
  }
  if (stepName.includes('board')) {
    keywords.push('board');
  }
  if (stepName.includes('neda') || stepName.includes('icc')) {
    keywords.push('neda', 'icc');
  }
  if (stepName.includes('selection') || stepName.includes('award') || stepName.includes('partner')) {
    keywords.push('selection', 'award', 'partner');
  }
  if (stepName.includes('signing') || stepName.includes('agreement') || stepName.includes('jva')) {
    keywords.push('signing', 'agreement', 'jva');
  }
  if (stepName.includes('due diligence') || stepName.includes('study') || stepName.includes('procurement') || stepName.includes('consultancy')) {
    keywords.push('diligence', 'study', 'procurement', 'consultancy');
  }
  if (stepName.includes('intake') || stepName.includes('submission') || stepName.includes('prescreening') || stepName.includes('screening') || stepName.includes('kyc') || stepName.includes('loi')) {
    keywords.push('intake', 'submission', 'prescreening', 'screening', 'kyc', 'loi', 'concept');
  }
  return keywords;
}

function getRequirementsForStep(step: ApprovalStep, section: any) {
  if (!props.requirements?.length) return [];
  
  const sectionSteps = section.steps || [];
  const stepIndex = sectionSteps.findIndex((s: ApprovalStep) => s.id === step.id);
  if (stepIndex === -1) return [];

  const allSecReqs = (props.requirements || []).filter((req) => deriveRequirementGroupKey(req) === section.key);
  const stepGates = getGateStepsForStep(step);
  const matchedReqs = allSecReqs.filter((req) => req.gate_step && stepGates.includes(req.gate_step));

  if (stepIndex === 0) {
    const unmappedReqs = allSecReqs.filter((req) => {
      if (!req.gate_step) return true;
      const matchesAnyStep = sectionSteps.some((s: ApprovalStep) => getGateStepsForStep(s).includes(req.gate_step!));
      return !matchesAnyStep;
    });
    return [...matchedReqs, ...unmappedReqs];
  }

  return matchedReqs;
}

function getTasksForStep(step: ApprovalStep, section: any) {
  if (!props.workPlanTasks?.length) return [];

  const sectionSteps = section.steps || [];
  const stepIndex = sectionSteps.findIndex((s: ApprovalStep) => s.id === step.id);
  if (stepIndex === -1) return [];

  const allSecTasks = sortedTasks.value.filter((task) => deriveTaskGroupKey(task) === section.key);

  const taskToStepMap = allSecTasks.map((task, taskIdx) => {
    const title = String(task.title || '').toLowerCase();
    const desc = String(task.description || '').toLowerCase();
    const text = `${title} ${desc}`;

    let bestStepIdx = -1;
    let highestScore = 0;

    sectionSteps.forEach((s: ApprovalStep, sIdx: number) => {
      const keywords = getStepTaskKeywords(s);
      let score = 0;
      keywords.forEach((keyword) => {
        if (text.includes(keyword)) {
          score++;
        }
      });
      if (score > highestScore) {
        highestScore = score;
        bestStepIdx = sIdx;
      }
    });

    if (bestStepIdx !== -1) {
      return { task, stepIdx: bestStepIdx };
    }

    const match = title.match(/^(\d+)\./);
    if (match) {
      const num = parseInt(match[1], 10);
      const stepByOrderIdx = sectionSteps.findIndex((s: ApprovalStep) => s.step_order === num);
      if (stepByOrderIdx !== -1) {
        return { task, stepIdx: stepByOrderIdx };
      }
    }

    const fallbackStepIdx = Math.min(taskIdx, sectionSteps.length - 1);
    return { task, stepIdx: fallbackStepIdx };
  });

  return taskToStepMap.filter((item) => item.stepIdx === stepIndex).map((item) => item.task);
}
</script>

<style scoped>
.approval-timeline {
  --at-bg: var(--v-card, #fafafa);
  --at-border: var(--v-border, #e2e8f0);
  --at-text: var(--v-text, #0f172a);
  --at-text-2: var(--v-text-2, #475569);
  --at-text-3: var(--v-text-3, #94a3b8);
  --at-accent: var(--v-accent, #2563eb);
  --at-accent-bg: var(--v-accent-bg, #eff6ff);
  background: var(--at-bg);
  border: 1px solid var(--at-border);
  border-radius: 0.75rem;
  color: var(--at-text);
  padding: 1.25rem;
}

:global(.dark) .approval-timeline,
:global(.modal-overlay.is-dark) .approval-timeline,
.approval-timeline.dark-mode,
.is-dark .approval-timeline {
  --at-bg: var(--v-card, #111c2e);
  --at-border: var(--v-border, #2b3b52);
  --at-text: var(--v-text, #f8fafc);
  --at-text-2: var(--v-text-2, #cbd5e1);
  --at-text-3: var(--v-text-3, #94a3b8);
  --at-accent: var(--v-accent, #3b82f6);
  --at-accent-bg: var(--v-accent-bg, #172b4a);
}

.spinner-container { display: flex; align-items: center; gap: 0.75rem; color: var(--at-text-3); font-size: 0.875rem; padding: 2rem; justify-content: center; }
.spinner-sm { width: 1.25rem; height: 1.25rem; border: 2px solid var(--at-border); border-top-color: var(--at-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.approval-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--at-border); gap: 1rem; }
.action-note { display: inline-flex; align-items: center; gap: 0.4rem; margin: 0 0 1rem; color: var(--at-text-3); font-size: 0.78rem; }
.note-icon { width: 0.9rem; height: 0.9rem; flex-shrink: 0; }
.ah-left { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.ah-title { font-size: 1rem; font-weight: 700; color: var(--at-text); margin: 0; }
.ah-badge { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; }

.btn-action { display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.875rem; background: var(--at-accent); border: none; border-radius: 0.5rem; font-size: 0.78rem; font-weight: 600; color: white; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.btn-action:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
.btn-icon { width: 0.875rem; height: 0.875rem; }

.soi-sections { display: grid; gap: 1rem; }
.soi-section { border: 1px solid var(--at-border); border-radius: 0.7rem; background: rgba(255,255,255,0.55); color: var(--at-text); overflow: hidden; }
:global(.dark) .soi-section { background: rgba(15,23,42,0.35); }
:global(.modal-overlay.is-dark) .soi-section { background: #101a2b; }
.approval-timeline.dark-mode .soi-section { background: #101a2b !important; border-color: #29384e; }
.soi-section.active { border-color: rgba(37,99,235,0.5); box-shadow: 0 0 0 3px rgba(37,99,235,0.08); }
.section-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.95rem 1rem; border-bottom: 1px solid var(--at-border); background: rgba(248,250,252,0.75); color: var(--at-text); }
:global(.dark) .section-head { background: rgba(30,41,59,0.55); }
:global(.modal-overlay.is-dark) .section-head { background: #152238; }
.approval-timeline.dark-mode .section-head { background: #152238 !important; border-color: #29384e; }
.section-kicker { margin: 0 0 0.15rem; color: var(--at-text-3); font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.section-head h4 { margin: 0; color: var(--at-text); font-size: 0.95rem; font-weight: 700; }
.section-summary { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; justify-content: flex-end; color: var(--at-text-2); font-size: 0.72rem; font-weight: 600; }
.section-summary span { border: 1px solid var(--at-border); border-radius: 999px; padding: 0.18rem 0.45rem; background: var(--at-bg); }

.workflow-steps { display: flex; flex-direction: column; padding: 1rem 1rem 0.2rem; }
.wf-step { display: flex; gap: 1rem; position: relative; padding-bottom: 1.15rem; }
.wf-step:last-child { padding-bottom: 0.7rem; }
.wfs-indicator { display: flex; flex-direction: column; align-items: center; width: 1.5rem; flex-shrink: 0; position: relative; }
.wfs-line { position: absolute; top: 1.5rem; bottom: -0.2rem; width: 2px; background: var(--at-border); z-index: 0; }
.wf-step.completed .wfs-line { background: #22c55e; }
:global(.dark) .wf-step.completed .wfs-line { background: #166534; }

.wfs-dot { width: 1.5rem; height: 1.5rem; border-radius: 50%; background: var(--at-bg); border: 2px solid var(--at-border); display: flex; align-items: center; justify-content: center; z-index: 1; position: relative; }
.wf-step.completed .wfs-dot { background: #22c55e; border-color: #22c55e; color: white; }
:global(.dark) .wf-step.completed .wfs-dot { background: #166534; border-color: #166534; color: #86efac; }
.wf-step.current .wfs-dot { border-color: var(--at-accent); background: var(--at-accent-bg); }
.wf-step.returned .wfs-dot { border-color: #ef4444; background: #fef2f2; color: #ef4444; }
:global(.dark) .wf-step.returned .wfs-dot { border-color: #ef4444; background: #450a0a; }

.dot-icon { width: 0.875rem; height: 0.875rem; }
.dot-num { font-size: 0.7rem; font-weight: 700; color: var(--at-text-3); }
.wf-step.returned .dot-num { color: #ef4444; }
.dot-pulse { width: 0.5rem; height: 0.5rem; background: var(--at-accent); border-radius: 50%; animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(37,99,235,0.4); } 70% { box-shadow: 0 0 0 6px rgba(37,99,235,0); } 100% { box-shadow: 0 0 0 0 rgba(37,99,235,0); } }

.wfs-content { flex: 1; min-width: 0; }
.wfc-title { margin: 0 0 0.2rem; font-size: 0.9rem; font-weight: 600; color: var(--at-text); }
.wfc-role { display: flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; color: var(--at-text-3); margin-bottom: 0.5rem; }
.r-icon { width: 0.75rem; height: 0.75rem; }

.wfc-record { background: rgba(0,0,0,0.02); border: 1px solid var(--at-border); border-radius: 0.5rem; padding: 0.75rem; margin-top: 0.25rem; }
:global(.dark) .wfc-record { background: rgba(0,0,0,0.15); }
:global(.modal-overlay.is-dark) .wfc-record { background: #101a2b; }
.approval-timeline.dark-mode .wfc-record { background: #101a2b; }
.rec-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.4rem; }
.rec-user { font-size: 0.75rem; font-weight: 600; color: var(--at-text-2); }
.rec-date { font-size: 0.68rem; color: var(--at-text-3); white-space: nowrap; }
.rec-status { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 0.25rem; margin-bottom: 0.4rem; }
.rec-cond { font-size: 0.8rem; color: #b45309; margin: 0 0 0.25rem; background: #fffbeb; padding: 0.4rem; border-radius: 0.25rem; }
:global(.dark) .rec-cond { color: #fcd34d; background: #78350f; }
.rec-comm { font-size: 0.8rem; color: var(--at-text-2); margin: 0; font-style: italic; }

.wfc-pending, .current-action-card { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; font-size: 0.8rem; color: var(--at-accent); font-weight: 500; margin-top: 0.25rem; }
.current-action-card { border-top: 1px solid var(--at-border); padding-top: 0.65rem; color: var(--at-text-2); }
.btn-action.inline { padding: 0.38rem 0.7rem; font-size: 0.72rem; }

.section-workplan { border-top: 1px solid var(--at-border); padding: 0.9rem 1rem 1rem; background: rgba(248,250,252,0.45); }
:global(.dark) .section-workplan { background: rgba(2,6,23,0.18); }
:global(.modal-overlay.is-dark) .section-workplan { background: #0c1423; }
.approval-timeline.dark-mode .section-workplan { background: #0c1423 !important; border-color: #29384e; }
.workplan-head { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.65rem; color: var(--at-text-2); font-size: 0.78rem; font-weight: 700; }
.workplan-icon { width: 0.9rem; height: 0.9rem; }
.section-task-list { display: grid; gap: 0.65rem; }
.section-task { border: 1px solid var(--at-border); border-radius: 0.55rem; background: var(--at-bg); padding: 0.75rem; }
:global(.modal-overlay.is-dark) .section-task { background: #172238; }
.approval-timeline.dark-mode .section-task { background: #172238 !important; border-color: #29384e; }
.task-row { display: grid; grid-template-columns: minmax(0, 1fr) 9rem; gap: 0.85rem; align-items: start; }
.task-main { min-width: 0; }
.task-title-line { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.2rem; }
.task-title-line strong { color: var(--at-text); font-size: 0.86rem; }
.task-main p { margin: 0.15rem 0 0; color: var(--at-text-2); font-size: 0.75rem; line-height: 1.45; }
.task-meta { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; color: var(--at-text-3); font-size: 0.7rem; margin-top: 0.45rem; }
.task-meta .danger { color: #dc2626; font-weight: 700; }
.task-status { border-radius: 999px; padding: 0.12rem 0.42rem; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
.task-status.completed { background: #dcfce7; color: #166534; }
.task-status.in_progress { background: #dbeafe; color: #1e40af; }
.task-status.pending { background: #f1f5f9; color: #475569; }
.task-status.cancelled { background: #fee2e2; color: #991b1b; }
:global(.modal-overlay.is-dark) .task-status.completed { background: #12351f; color: #bbf7d0; }
:global(.modal-overlay.is-dark) .task-status.in_progress { background: #172b4a; color: #bfdbfe; }
:global(.modal-overlay.is-dark) .task-status.pending { background: #253244; color: #cbd5e1; }
:global(.modal-overlay.is-dark) .task-status.cancelled { background: #4a1515; color: #fecaca; }
.task-progress { display: grid; gap: 0.35rem; justify-items: end; color: var(--at-text-2); font-size: 0.75rem; font-weight: 700; }
.mini-track { width: 100%; height: 0.35rem; border-radius: 999px; background: var(--at-border); overflow: hidden; }
.mini-fill { height: 100%; border-radius: inherit; background: var(--at-accent); }
.task-actions, .subtask-actions { display: flex; justify-content: flex-end; gap: 0.35rem; }
.task-action-btn { border: 1px solid var(--at-border); border-radius: 0.4rem; background: var(--at-bg); color: var(--at-text-2); padding: 0.28rem 0.5rem; font-size: 0.68rem; font-weight: 700; cursor: pointer; }
.task-action-btn.done { border-color: rgba(34,197,94,0.35); background: #dcfce7; color: #166534; }
:global(.modal-overlay.is-dark) .task-action-btn { background: #111c2e; color: #cbd5e1; }
:global(.modal-overlay.is-dark) .task-action-btn.done { border-color: #255f3b; background: #12351f; color: #bbf7d0; }
.task-action-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.subtask-list { display: grid; gap: 0.45rem; margin-top: 0.65rem; padding-top: 0.65rem; border-top: 1px solid var(--at-border); }
.subtask-row { display: grid; grid-template-columns: 1.1rem minmax(0, 1fr) auto; gap: 0.55rem; align-items: center; }
.subtask-check { width: 1rem; height: 1rem; border-radius: 50%; border: 1px solid var(--at-border); display: flex; align-items: center; justify-content: center; color: white; }
.subtask-check.done { background: #22c55e; border-color: #22c55e; }
:global(.modal-overlay.is-dark) .subtask-check.done { background: #2563eb; border-color: #60a5fa; }
.subtask-check-icon { width: 0.7rem; height: 0.7rem; }
.subtask-copy { display: grid; gap: 0.1rem; min-width: 0; }
.subtask-copy span { color: var(--at-text); font-size: 0.78rem; font-weight: 600; }
.subtask-copy small { color: var(--at-text-3); font-size: 0.68rem; }
.empty-checklist { margin: 0; color: var(--at-text-3); font-size: 0.76rem; }

.empty-state { display: flex; flex-direction: column; align-items: center; padding: 2rem 1rem; color: var(--at-text-3); text-align: center; }
.empty-icon { width: 2.5rem; height: 2.5rem; opacity: 0.5; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; }
.empty-icon svg { width: 100%; height: 100%; }

.s-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
:global(.dark) .s-approved { background: #14532d; color: #86efac; border-color: #166534; }
.s-returned { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
:global(.dark) .s-returned { background: #450a0a; color: #fca5a5; border-color: #7f1d1d; }
.s-pending { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
:global(.dark) .s-pending { background: #422006; color: #fef08a; border-color: #713f12; }
.s-completed { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
:global(.dark) .s-completed { background: #1e3a8a; color: #bfdbfe; border-color: #1e3a8a; }
.s-default { background: var(--at-border); color: var(--at-text-2); }

@media (max-width: 720px) {
  .approval-header,
  .section-head,
  .rec-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .section-summary {
    justify-content: flex-start;
  }

  .task-row {
    grid-template-columns: 1fr;
  }

  .task-progress {
    justify-items: stretch;
  }

  .subtask-row {
    grid-template-columns: 1.1rem minmax(0, 1fr);
  }

  .subtask-actions {
    grid-column: 2;
    justify-content: flex-start;
  }
}

.missing-badge-timeline {
  border: 1px solid #fecaca !important;
  border-radius: 999px;
  padding: 0.18rem 0.45rem;
  background: #fef2f2 !important;
  color: #b91c1c !important;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
}
:global(.dark) .missing-badge-timeline {
  background: #450a0a !important;
  color: #fca5a5 !important;
  border-color: #7f1d1d !important;
}

.section-requirements {
  border-top: 1px solid var(--at-border);
  padding: 0.9rem 1rem 1rem;
  background: rgba(248, 250, 252, 0.25);
}
:global(.dark) .section-requirements {
  background: rgba(2, 6, 23, 0.1);
}
:global(.modal-overlay.is-dark) .section-requirements {
  background: #0c1423;
}
.approval-timeline.dark-mode .section-requirements {
  background: #0c1423 !important;
  border-color: #29384e;
}
.requirements-head {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 0.65rem;
  color: var(--at-text-2);
  font-size: 0.78rem;
  font-weight: 700;
}
.requirements-icon {
  width: 0.9rem;
  height: 0.9rem;
}
.timeline-requirements-list {
  display: grid;
  gap: 0.5rem;
}
.timeline-requirement-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.65rem 0.8rem;
  background: var(--at-bg);
  border: 1px solid var(--at-border);
  border-radius: 0.5rem;
  transition: all 0.2s ease;
}
.timeline-requirement-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
}
:global(.dark) .timeline-requirement-card:hover {
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
.timeline-requirement-card.is-missing {
  border-color: rgba(239, 68, 68, 0.4);
  background: rgba(254, 242, 242, 0.4);
}
:global(.dark) .timeline-requirement-card.is-missing {
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(69, 10, 10, 0.2);
}
.timeline-requirement-card.is-missing:hover {
  border-color: rgba(239, 68, 68, 0.6);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);
}
.tr-main {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}
.tr-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--at-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.tr-meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.tr-owner {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  text-transform: uppercase;
}
.tr-owner.internal {
  background: #eff6ff;
  color: #1e40af;
}
:global(.dark) .tr-owner.internal {
  background: #1e3a8a;
  color: #bfdbfe;
}
.tr-owner.proponent {
  background: #f5f3ff;
  color: #5b21b6;
}
:global(.dark) .tr-owner.proponent {
  background: #3b0764;
  color: #ddd6fe;
}
.tr-badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.1rem 0.3rem;
  border-radius: 4px;
  text-transform: uppercase;
}
.tr-badge.required {
  background: #fee2e2;
  color: #991b1b;
}
:global(.dark) .tr-badge.required {
  background: #450a0a;
  color: #fca5a5;
}
.tr-badge.optional {
  background: #f1f5f9;
  color: #475569;
}
:global(.dark) .tr-badge.optional {
  background: #1e293b;
  color: #cbd5e1;
}
.tr-badge.missing-flag-badge {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
  animation: pulse-border-timeline 2s infinite;
}
:global(.dark) .tr-badge.missing-flag-badge {
  background: #78350f;
  color: #fcd34d;
  border-color: #713f12;
}
@keyframes pulse-border-timeline {
  0% { border-color: rgba(253, 230, 138, 0.4); }
  50% { border-color: rgba(239, 68, 68, 0.6); }
  100% { border-color: rgba(253, 230, 138, 0.4); }
}
.tr-action-area {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.req-status-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  text-transform: uppercase;
}
.req-status-badge.pending {
  background: #f1f5f9;
  color: #475569;
}
:global(.dark) .req-status-badge.pending {
  background: #1e293b;
  color: #cbd5e1;
}
.req-status-badge.requested {
  background: #fef3c7;
  color: #92400e;
}
:global(.dark) .req-status-badge.requested {
  background: #78350f;
  color: #fcd34d;
}
.req-status-badge.received {
  background: #dcfce7;
  color: #166534;
}
:global(.dark) .req-status-badge.received {
  background: #14532d;
  color: #86efac;
}
.req-status-badge.approved {
  background: #dcfce7;
  color: #166534;
}
:global(.dark) .req-status-badge.approved {
  background: #14532d;
  color: #86efac;
}
.req-status-badge.approved_with_conditions {
  background: #dbeafe;
  color: #1e40af;
}
:global(.dark) .req-status-badge.approved_with_conditions {
  background: #1e3a8a;
  color: #bfdbfe;
}
.req-status-badge.waived {
  background: #f3e8ff;
  color: #6b21a8;
}
:global(.dark) .req-status-badge.waived {
  background: #581c87;
  color: #e9d5ff;
}
.btn-view-doc {
  background: transparent;
  border: 1px solid var(--at-border);
  color: var(--at-text-2);
  padding: 0.25rem 0.65rem;
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-view-doc:hover {
  background: var(--at-border);
  color: var(--at-text);
}
.btn-download-template {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: rgba(37, 99, 235, 0.08);
  border: 1px solid rgba(37, 99, 235, 0.2);
  color: var(--at-accent);
  padding: 0.25rem 0.65rem;
  font-size: 0.72rem;
  font-weight: 700;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-download-template:hover {
  background: var(--at-accent);
  color: white;
  border-color: var(--at-accent);
  transform: translateY(-1px);
}
.action-icon {
  width: 0.75rem;
  height: 0.75rem;
}

.step-checklists-container {
  border-top: 1px dashed var(--at-border);
  padding-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.section-checklists-container {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  padding: 1rem;
}
.step-checklist-group {
  border: 1px solid var(--at-border);
  border-radius: 0.5rem;
  background: rgba(0, 0, 0, 0.015);
  overflow: hidden;
}
:global(.dark) .step-checklist-group {
  background: rgba(255, 255, 255, 0.015);
}
.step-checklist-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  user-select: none;
  background: rgba(0, 0, 0, 0.025);
  transition: background 0.15s ease;
}
:global(.dark) .step-checklist-header {
  background: rgba(255, 255, 255, 0.035);
}
.step-checklist-header:hover {
  background: rgba(0, 0, 0, 0.045);
}
:global(.dark) .step-checklist-header:hover {
  background: rgba(255, 255, 255, 0.065);
}
.step-checklist-header.static {
  cursor: default;
}
.step-checklist-header.static:hover {
  background: rgba(0, 0, 0, 0.025);
}
:global(.dark) .step-checklist-header.static:hover {
  background: rgba(255, 255, 255, 0.035);
}
.sch-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--at-text-2);
}
.sch-chevron {
  width: 0.875rem;
  height: 0.875rem;
  color: var(--at-text-3);
  transition: transform 0.2s ease;
}
.sch-icon {
  width: 0.875rem;
  height: 0.875rem;
  color: var(--at-accent);
}
.step-checklist-body {
  padding: 0 0.75rem 0.75rem;
}
.timeline-requirement-card.inline-card {
  padding: 0.5rem 0.65rem;
  margin-top: 0.35rem;
  margin-bottom: 0;
  background: var(--at-bg);
}
.section-task.inline-task {
  padding: 0.6rem 0.75rem;
  margin-top: 0.35rem;
  margin-bottom: 0;
  background: var(--at-bg);
}
.empty-checklist {
  font-size: 0.75rem;
  color: var(--at-text-3);
  font-style: italic;
}
</style>
