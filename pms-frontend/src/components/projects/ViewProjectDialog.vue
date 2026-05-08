<!-- src/components/projects/ViewProjectDialog.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" :class="{ 'is-dark': isDarkMode }" @mousedown.self="handleClose">
        <div class="modal-panel">
          <!-- Loading -->
          <div v-if="loading" class="loading-state">
            <div class="spinner-lg"></div><span>Loading project...</span>
          </div>
          <div v-else-if="loadError" class="loading-state">
            <AlertCircleIcon class="ep-icon" />
            <span>{{ loadError }}</span>
            <div class="load-actions">
              <button class="retry-btn" @click="loadDialogData">Retry</button>
              <button class="close-btn" @click="handleClose">Close</button>
            </div>
          </div>

          <template v-else-if="project">
            <!-- Hero -->
            <div class="hero" :style="{ background: heroGradient }">
              <div class="hero-top">
                <div class="hero-badges">
                  <span class="h-code">{{ project.project_code }}</span>
                  <span v-if="project.is_svf" class="h-badge svf">SVF</span>
                  <span v-if="project.is_overdue" class="h-badge overdue">Overdue</span>
                  <span v-if="project.is_archived" class="h-badge archived">Archived</span>
                </div>
                <div class="hero-actions">
                  <button v-if="canEditProjectAction" class="h-btn" @click="emit('edit', project)" title="Edit">
                    <EditIcon class="icon" />
                  </button>
                  <button class="h-close" @click="handleClose"><XIcon class="icon" /></button>
                </div>
              </div>
              <h1 class="hero-title">{{ project.title }}</h1>
              <div class="hero-meta">
                <span class="h-pill" v-if="project.current_stage"><LayersIcon class="pi" />{{ project.current_stage.name }}</span>
                <span class="h-pill status-pill" :style="heroStatusStyle" v-if="project.status"><span class="sdot"></span>{{ project.status.name }}</span>
                <span class="h-pill" v-if="project.project_type"><BriefcaseIcon class="pi" />{{ project.project_type.name }}</span>
              </div>
              <div v-if="project.progress_percentage !== undefined" class="hero-prog">
                <div class="hp-track"><div class="hp-fill" :style="{ width: `${project.progress_percentage}%` }"></div></div>
                <span class="hp-label">{{ project.progress_percentage }}% complete</span>
              </div>
            </div>

            <!-- Tabs -->
            <div class="tab-nav">
              <button v-for="tab in tabs" :key="tab.id" class="tab-btn" :class="{ active: activeTab === tab.id }" @click="activeTab = tab.id">
                <component :is="tab.icon" class="ti" />{{ tab.label }}
                <span v-if="tab.count !== undefined" class="tc">{{ tab.count }}</span>
              </button>
            </div>

            <!-- Content -->
            <div class="tab-body">

              <!-- Overview -->
              <div v-show="activeTab === 'overview'" class="tab-pane">
                <div class="info-card">
                  <div class="ic-head"><FileTextIcon class="ci" /><span>Description</span></div>
                  <p class="desc">{{ project.description || 'No description provided.' }}</p>
                </div>
                <div class="two-col">
                  <div class="info-card">
                    <div class="ic-head"><InfoIcon class="ci" /><span>Project Info</span></div>
                    <div class="d-list">
                      <div v-if="project.project_type" class="d-item"><span class="dl">Type</span><span class="dv">{{ project.project_type.name }}</span></div>
                      <div v-if="project.industry" class="d-item"><span class="dl">Industry</span><span class="dv">{{ project.industry.name }}</span></div>
                      <div v-if="project.sector" class="d-item"><span class="dl">Sector</span><span class="dv">{{ project.sector.name }}</span></div>
                      <div v-if="project.investment_type" class="d-item"><span class="dl">Investment</span><span class="dv">{{ project.investment_type.name }}</span></div>
                    </div>
                  </div>
                  <div class="info-card">
                    <div class="ic-head"><CalendarIcon class="ci" /><span>Timeline</span></div>
                    <div class="d-list">
                      <div v-if="project.proposal_date" class="d-item"><span class="dl">Proposed</span><span class="dv">{{ fmtDate(project.proposal_date) }}</span></div>
                      <div v-if="project.start_date" class="d-item"><span class="dl">Started</span><span class="dv">{{ fmtDate(project.start_date) }}</span></div>
                      <div v-if="project.target_completion_date" class="d-item"><span class="dl">Target</span><span class="dv" :class="{ 'ov-text': project.is_overdue }">{{ fmtDate(project.target_completion_date) }}</span></div>
                      <div v-if="project.actual_completion_date" class="d-item"><span class="dl">Completed</span><span class="dv ok-text">{{ fmtDate(project.actual_completion_date) }}</span></div>
                    </div>
                  </div>
                </div>
                <div v-if="project.estimated_cost || project.actual_cost" class="info-card">
                  <div class="ic-head"><CoinsIcon class="ci" /><span>Financial Summary</span></div>
                  <div class="fin-grid">
                    <div class="fin-item"><span class="fl">Estimated</span><span class="fa">{{ fmtPeso(project.estimated_cost || 0) }}</span></div>
                    <div v-if="project.actual_cost" class="fin-item"><span class="fl">Actual</span><span class="fa">{{ fmtPeso(project.actual_cost) }}</span></div>
                    <div v-if="project.funding_source" class="fin-item"><span class="fl">Funding</span><span class="fa sm">{{ project.funding_source.name }}</span></div>
                    <div v-if="project.estimated_cost && project.actual_cost" class="fin-item">
                      <span class="fl">Variance</span>
                      <span class="fa" :class="project.actual_cost > project.estimated_cost ? 'neg' : 'pos'">{{ fmtPeso(project.actual_cost - project.estimated_cost) }}</span>
                    </div>
                  </div>
                </div>
                <div class="two-col">
                  <div v-if="project.location_address" class="info-card">
                    <div class="ic-head"><MapPinIcon class="ci" /><span>Location</span></div>
                    <p class="desc">{{ project.location_address }}</p>
                    <div v-if="hasCoordinates(project)" class="coord-chip">{{ fmtCoord(project.location_lat) }}, {{ fmtCoord(project.location_lng) }}</div>
                  </div>
                  <div v-if="project.proponent_name" class="info-card">
                    <div class="ic-head"><UserIcon class="ci" /><span>Proponent</span></div>
                    <div class="d-list">
                      <div class="d-item"><span class="dl">Name</span><span class="dv">{{ project.proponent_name }}</span></div>
                      <div v-if="project.proponent_contact" class="d-item"><span class="dl">Contact</span><span class="dv">{{ project.proponent_contact }}</span></div>
                      <div v-if="project.proponent_email" class="d-item"><span class="dl">Email</span><a :href="`mailto:${project.proponent_email}`" class="dv link">{{ project.proponent_email }}</a></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Team -->
              <div v-show="activeTab === 'team'" class="tab-pane">
                <div class="pane-head">
                  <h3>Team Members</h3>
                  <button v-if="canManageMembersAction" class="add-btn" @click="openAddMember"><UserPlusIcon class="icon" /> Add Member</button>
                </div>
                <div v-if="activeMembers.length > 0" class="members-list">
                  <div v-for="m in activeMembers" :key="m.id" class="member-card">
                    <div class="m-avatar">
                      <img v-if="m.user?.avatar" :src="m.user.avatar" :alt="m.user?.name || m.user?.full_name" />
                      <span v-else>{{ initials(m.user?.name || m.user?.full_name || '') }}</span>
                    </div>
                    <div class="m-info">
                      <p class="m-name">{{ m.user?.name || m.user?.full_name }}</p>
                      <p class="m-role">{{ m.role?.name || 'Team Member' }}</p>
                      <div class="m-perms">
                        <span class="m-perm" :class="{ on: memberFlag(m, 'can_edit') }">Edit Project + Create/Update Tasks</span>
                        <span class="m-perm" :class="{ on: memberFlag(m, 'can_delete') }">Delete Project/Tasks</span>
                        <span class="m-perm" :class="{ on: memberFlag(m, 'can_manage_members') }">Manage Members</span>
                      </div>
                    </div>
                    <div class="m-actions">
                      <button class="remove-btn" @click="openEditMember(m)">Edit</button>
                      <button class="remove-btn danger" @click="handleRemoveMember(m.id)">Remove</button>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-pane"><UsersIcon class="ep-icon" /><p>No team members added yet</p></div>
              </div>

              <!-- Tasks -->
              <div v-show="activeTab === 'tasks'" class="tab-pane">
                <div class="pane-head">
                  <div>
                    <h3>Task Progress</h3>
                    <p class="pane-sub">Execution tracking linked to this project</p>
                  </div>
                </div>

                <div class="task-summary-grid">
                  <div class="task-stat">
                    <span>Total Tasks</span>
                    <strong>{{ taskStats.total }}</strong>
                  </div>
                  <div class="task-stat">
                    <span>Completed</span>
                    <strong>{{ taskStats.completed }}</strong>
                  </div>
                  <div class="task-stat">
                    <span>In Progress</span>
                    <strong>{{ taskStats.inProgress }}</strong>
                  </div>
                  <div class="task-stat warn">
                    <span>Overdue</span>
                    <strong>{{ taskStats.overdue }}</strong>
                  </div>
                </div>

                <div class="info-card">
                  <div class="ic-head"><ListChecksIcon class="ci" /><span>Overall Execution</span></div>
                  <div class="execution-row">
                    <div class="execution-track">
                      <div class="execution-fill" :style="{ width: `${taskStats.averageProgress}%` }"></div>
                    </div>
                    <strong>{{ taskStats.averageProgress }}%</strong>
                  </div>
                </div>

                <div v-if="topLevelTasks.length" class="task-list">
                  <div v-for="task in topLevelTasks" :key="task.id" class="task-card">
                    <div class="task-main">
                      <div class="task-title-row">
                        <strong>{{ task.title }}</strong>
                        <span class="task-status" :class="task.status">{{ formatTaskStatus(task.status) }}</span>
                      </div>
                      <p v-if="task.description">{{ task.description }}</p>
                      <div class="task-meta">
                        <span>{{ task.assigned_to?.full_name || task.assigned_to?.name || 'Unassigned' }}</span>
                        <span v-if="task.due_date" :class="{ danger: task.is_overdue }">Due {{ fmtDate(task.due_date) }}</span>
                        <span v-if="task.priority">{{ task.priority }}</span>
                      </div>
                      <div v-if="task.subtasks?.length" class="subtask-mini-list">
                        <div v-for="subtask in task.subtasks" :key="subtask.id" class="subtask-mini">
                          <span>{{ subtask.title }}</span>
                          <small>{{ formatTaskStatus(subtask.status) }}</small>
                        </div>
                      </div>
                    </div>
                    <div class="task-progress">
                      <span>{{ task.progress_percentage || 0 }}%</span>
                      <div class="mini-track"><div class="mini-fill" :style="{ width: `${task.progress_percentage || 0}%` }"></div></div>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-pane"><ListChecksIcon class="ep-icon" /><p>No tasks linked to this project yet</p></div>
              </div>

              <!-- Attachments -->
              <div v-show="activeTab === 'attachments'" class="tab-pane">
                <div class="pane-head">
                  <div>
                    <h3>Attachments</h3>
                    <p class="pane-sub">TOR, approvals, evidence, and compliance files</p>
                  </div>
                  <button v-if="canUploadDocumentsAction" class="add-btn" @click="openDocumentPicker">
                    <UploadIcon class="icon" /> Upload File
                  </button>
                </div>

                <input ref="documentFileInput" type="file" class="hidden-file" @change="handleDocumentFileSelect" />

                <div v-if="selectedDocumentFile" class="upload-card">
                  <div class="upload-copy">
                    <strong>{{ selectedDocumentFile.name }}</strong>
                    <span>{{ fmtFileSize(selectedDocumentFile.size) }}</span>
                  </div>
                  <input v-model="documentForm.title" class="member-input" placeholder="Document title" />
                  <input v-model="documentForm.category" class="member-input" placeholder="Category (TOR, SOI, Condition Evidence...)" />
                  <textarea v-model="documentForm.description" class="member-input upload-textarea" placeholder="Short description or condition being satisfied"></textarea>
                  <div class="upload-actions">
                    <button class="remove-btn" @click="clearSelectedDocument">Cancel</button>
                    <button class="add-btn" :disabled="documentUploading" @click="uploadDocument">
                      <span v-if="documentUploading" class="spinner-sm"></span>
                      Save Attachment
                    </button>
                  </div>
                </div>

                <div v-if="projectDocuments.length" class="document-list">
                  <div v-for="doc in projectDocuments" :key="doc.id" class="document-card">
                    <div class="doc-icon"><PaperclipIcon class="icon" /></div>
                    <div class="doc-main">
                      <strong>{{ doc.title }}</strong>
                      <p>{{ doc.description || doc.file_name || 'Project attachment' }}</p>
                      <div class="doc-meta">
                        <span>{{ doc.category || 'General' }}</span>
                        <span>{{ fmtFileSize(doc.file_size || 0) }}</span>
                        <span v-if="doc.uploaded_at">{{ fmtDate(doc.uploaded_at) }}</span>
                        <span v-if="doc.uploaded_by">by {{ doc.uploaded_by.full_name || doc.uploaded_by.name }}</span>
                      </div>
                    </div>
                    <div class="doc-actions">
                      <button class="icon-action" title="Download" @click="downloadDocument(doc)"><DownloadIcon class="icon" /></button>
                      <button v-if="canUploadDocumentsAction" class="icon-action danger" title="Delete" @click="deleteDocument(doc.id)"><TrashIcon class="icon" /></button>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-pane"><PaperclipIcon class="ep-icon" /><p>No attachments uploaded yet</p></div>
              </div>

              <!-- Timeline -->
              <div v-show="activeTab === 'timeline'" class="tab-pane">
                <div v-if="timelineLoading" class="tl-loading"><div class="spinner-sm"></div> Loading history...</div>
                <div v-else-if="timelineData">
                  <div v-if="timelineData.stage_history.length > 0" class="tl-section">
                    <h4 class="tl-title">Stage Changes</h4>
                    <div class="tl-items">
                      <div v-for="h in timelineData.stage_history" :key="h.id" class="tl-item">
                        <div class="tl-dot s-dot"><ArrowRightIcon class="ti-" /></div>
                        <div class="tl-content">
                          <p class="tl-text">Stage changed<span v-if="h.from_stage" class="from"> from {{ h.from_stage.name }}</span> to <strong>{{ h.to_stage?.name }}</strong></p>
                          <p v-if="h.change_reason" class="tl-reason">{{ h.change_reason }}</p>
                          <p class="tl-meta">{{ fmtDate(h.changed_at) }} · {{ h.changed_by_user?.name }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="timelineData.status_history.length > 0" class="tl-section">
                    <h4 class="tl-title">Status Changes</h4>
                    <div class="tl-items">
                      <div v-for="h in timelineData.status_history" :key="h.id" class="tl-item">
                        <div class="tl-dot st-dot"><CheckCircleIcon class="ti-" /></div>
                        <div class="tl-content">
                          <p class="tl-text">Status changed<span v-if="h.from_status" class="from"> from {{ h.from_status.name }}</span> to <strong>{{ h.to_status?.name }}</strong></p>
                          <p v-if="h.change_reason" class="tl-reason">{{ h.change_reason }}</p>
                          <p class="tl-meta">{{ fmtDate(h.changed_at) }} · {{ h.changed_by_user?.name }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="!timelineData.stage_history.length && !timelineData.status_history.length" class="empty-pane"><ClockIcon class="ep-icon" /><p>No history available</p></div>
                </div>
              </div>

              <!-- Approval Flow -->
              <div v-show="activeTab === 'approval'" class="tab-pane">
                 <ProjectApprovalTimeline 
                   :current-approval="timelineData?.current_approval || null"
                   :approval-history="timelineData?.approval_history || []"
                   :loading="timelineLoading"
                   :project-creator-id="projectCreatorId"
                   @open-action="showApprovalModal = true"
                 />
              </div>

            </div>
          </template>
        </div>
      </div>
    </Transition>

    <ApprovalActionModal
       v-model="showApprovalModal"
       :approval-id="timelineData?.current_approval?.id || null"
       :current-step="timelineData?.current_approval?.current_step"
       :resubmission="timelineData?.current_approval?.overall_status === 'returned'"
       @submit="handleApprovalSubmit"
    />

    <Transition name="modal">
      <div v-if="showMemberModal" class="modal-overlay member-overlay" :class="{ 'is-dark': isDarkMode }" @mousedown.self="closeMemberModal">
        <div class="member-modal">
          <div class="member-head">
            <h3>{{ editingMemberId ? 'Edit Project Member' : 'Add Project Member' }}</h3>
            <button class="h-close" @click="closeMemberModal"><XIcon class="icon" /></button>
          </div>

          <div class="member-body">
            <label class="member-label">User</label>
            <select v-model.number="memberForm.user_id" class="member-input" :disabled="!!editingMemberId">
              <option :value="0">Select user</option>
              <option v-for="u in availableUsers" :key="u.id" :value="u.id">
                {{ u.full_name || `${u.first_name} ${u.last_name}` }} ({{ u.email }})
              </option>
            </select>

            <label class="member-label">Role</label>
            <input class="member-input" :value="selectedUserRoleName" readonly />

            <div class="member-perm-grid">
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_view" />
                <span>Can view project and tasks</span>
              </label>
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_edit" />
                <span>Can edit project and create/update tasks</span>
              </label>
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_delete" />
                <span>Can delete tasks and project</span>
              </label>
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_manage_members" />
                <span>Can manage project members</span>
              </label>
            </div>
          </div>

          <div class="member-foot">
            <button class="remove-btn" @click="closeMemberModal">Cancel</button>
            <button class="add-btn" @click="saveMember">{{ editingMemberId ? 'Save Changes' : 'Add Member' }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, markRaw } from 'vue';
import { useProjectStore } from '@/store/projects';
import { useUserStore } from '@/store/user';
import { useAuthStore } from '@/store/auth';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import axiosInstance from '@/utils/axiosInstance';
import type { Project, ProjectMember, ProjectStageHistory, ProjectStatusHistory, ProjectApproval, ApprovalStepRecord, Document as ProjectDocument } from '@/types/project';
import type { User as AppUser } from '@/types/user';
import { toast } from 'vue3-toastify';
import { X as XIcon, Edit as EditIcon, Layers as LayersIcon, Briefcase as BriefcaseIcon, FileText as FileTextIcon, Info as InfoIcon, Calendar as CalendarIcon, Coins as CoinsIcon, MapPin as MapPinIcon, User as UserIcon, Users as UsersIcon, UserPlus as UserPlusIcon, Clock as ClockIcon, CheckCircle as CheckCircleIcon, ArrowRight as ArrowRightIcon, AlertCircle as AlertCircleIcon, ListChecks as ListChecksIcon, Paperclip as PaperclipIcon, Upload as UploadIcon, Download as DownloadIcon, Trash as TrashIcon } from 'lucide-vue-next';
import ProjectApprovalTimeline from './ProjectApprovalTimeline.vue';
import ApprovalActionModal from './ApprovalActionModal.vue';

interface Props { modelValue: boolean; projectId: number | null; initialTab?: string }
const props = defineProps<Props>();
const emit = defineEmits<{ 'update:modelValue': [v: boolean]; edit: [p: Project] }>();

const projectStore = useProjectStore();
const userStore = useUserStore();
const authStore = useAuthStore();
const layoutStore = useLayoutStore();
const isDarkMode = computed(() => {
  const htmlDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  return layoutStore.mode === SITE_MODE.DARK || htmlDark;
});
const loading = ref(false);
const timelineLoading = ref(false);
const loadError = ref('');
let loadRequestId = 0;
const project = ref<Project | null>(null);
const activeTab = ref('overview');
const timelineData = ref<{ stage_history: ProjectStageHistory[]; status_history: ProjectStatusHistory[]; current_approval: ProjectApproval | null; approval_history: ApprovalStepRecord[] } | null>(null);
const users = ref<AppUser[]>([]);
const showMemberModal = ref(false);
const showApprovalModal = ref(false);
const editingMemberId = ref<number | null>(null);
const documentFileInput = ref<HTMLInputElement | null>(null);
const selectedDocumentFile = ref<File | null>(null);
const documentUploading = ref(false);
const documentForm = ref({
  title: '',
  category: 'Project File',
  description: '',
});
const memberForm = ref({
  user_id: 0,
  role_id: 0,
  assignment_type: 'member' as 'member' | 'owner' | 'collaborator' | 'observer',
  can_view: true,
  can_edit: false,
  can_delete: false,
  can_approve: false,
  can_manage_members: false,
});

const tabs = computed(() => [
  { id: 'overview', label: 'Overview', icon: markRaw(InfoIcon) },
  { id: 'team', label: 'Team', icon: markRaw(UsersIcon), count: activeMembers.value.length },
  { id: 'tasks', label: 'Tasks', icon: markRaw(ListChecksIcon), count: projectTasks.value.length },
  { id: 'attachments', label: 'Attachments', icon: markRaw(PaperclipIcon), count: projectDocuments.value.length },
  { id: 'approval', label: 'Approval Flow', icon: markRaw(CheckCircleIcon) },
  { id: 'timeline', label: 'History', icon: markRaw(ClockIcon) },
]);

const activeMembers = computed(() => (project.value?.members || []).filter(m => !m.removed_at));
const projectTasks = computed(() => project.value?.tasks || []);
const topLevelTasks = computed(() => sortProjectTasks(projectTasks.value.filter((task) => !task.parent_task_id)));
const projectDocuments = computed(() => project.value?.documents || []);
const currentUserId = computed(() => authStore.user?.id || 0);
const projectCreatorId = computed(() => {
  if (project.value?.created_by_id) return project.value.created_by_id;
  const createdBy = project.value?.created_by;
  if (typeof createdBy === 'number') return createdBy;
  if (createdBy && typeof createdBy === 'object') return createdBy.id;
  return project.value?.creator?.id || undefined;
});

const currentMember = computed(() =>
  activeMembers.value.find((m) => m.user_id === currentUserId.value)
);

const hasAnyPermission = (permissionNames: string[]) =>
  permissionNames.some((permission) => authStore.permissions.includes(permission));

const canEditProjectAction = computed(() => {
  if (project.value?.approval_lock?.is_locked && !project.value.approval_lock.can_override) {
    return false;
  }

  if (
    hasAnyPermission([
      'projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_edit');
});

const canManageMembersAction = computed(() => {
  if (
    hasAnyPermission([
      'projects.members.manage', 'project_members.manage', 'project_member.manage', 'manage_members'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_manage_members');
});

const canUploadDocumentsAction = computed(() => {
  if (project.value?.approval_lock?.can_override) return true;
  if (projectCreatorId.value === currentUserId.value) return true;
  if (
    hasAnyPermission([
      'documents.create', 'documents.upload', 'projects.update', 'project.update', 'project.edit', 'edit_project'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_edit');
});

const taskStats = computed(() => {
  const tasks = projectTasks.value;
  const total = tasks.length;
  const completed = tasks.filter((task) => task.status === 'completed').length;
  const inProgress = tasks.filter((task) => task.status === 'in_progress').length;
  const overdue = tasks.filter((task) => task.is_overdue).length;
  const averageProgress = total
    ? Math.round(tasks.reduce((sum, task) => sum + Number(task.progress_percentage || 0), 0) / total)
    : 0;

  return { total, completed, inProgress, overdue, averageProgress };
});

const availableUsers = computed(() => {
  if (editingMemberId.value) {
    return users.value;
  }

  const memberUserIds = new Set(activeMembers.value.map((m) => m.user_id));
  return users.value.filter((u) => !memberUserIds.has(u.id));
});

const selectedUserRoleName = computed(() => {
  const selected = users.value.find((u) => u.id === memberForm.value.user_id);
  return selected?.role?.name || 'No default role';
});

const memberFlag = (
  member: ProjectMember | undefined,
  key: 'can_view' | 'can_edit' | 'can_delete' | 'can_approve' | 'can_manage_members'
) => {
  if (!member) return false;
  if (typeof member[key] === 'boolean') return member[key] as boolean;
  return Boolean(member.permissions?.[key]);
};

const heroGradient = computed(() => {
  if (project.value?.is_archived) return 'linear-gradient(135deg,#334155 0%,#0f172a 100%)';
  const m: Record<string,string> = { Active:'linear-gradient(135deg,#0f4c81 0%,#0f172a 100%)', 'On Hold':'linear-gradient(135deg,#78350f 0%,#0f172a 100%)', Completed:'linear-gradient(135deg,#1e3a5f 0%,#0f172a 100%)', Cancelled:'linear-gradient(135deg,#7f1d1d 0%,#0f172a 100%)' };
  return m[project.value?.status?.name || ''] || 'linear-gradient(135deg,#312e81 0%,#0f172a 100%)';
});

const heroStatusStyle = computed(() => {
  const m: Record<string,{bg:string;color:string}> = { Active:{bg:'rgba(34,197,94,0.2)',color:'#86efac'}, 'On Hold':{bg:'rgba(245,158,11,0.2)',color:'#fcd34d'}, Completed:{bg:'rgba(59,130,246,0.2)',color:'#93c5fd'}, Cancelled:{bg:'rgba(239,68,68,0.2)',color:'#fca5a5'} };
  const s = m[project.value?.status?.name || ''] || {bg:'rgba(255,255,255,0.1)',color:'rgba(255,255,255,0.8)'};
  return { background: s.bg, color: s.color };
});

watch([() => props.modelValue, () => props.projectId], async ([isOpen, projectId]) => {
  if (isOpen && projectId) {
    await loadDialogData();
  }
});

watch(activeTab, async (tab) => {
  if (tab === 'team' && users.value.length === 0) {
    await loadUsers();
  }
});

const loadProject = async () => {
  if (!props.projectId) return;
  loading.value = true;
  loadError.value = '';
  try {
    const result = await projectStore.fetchProject(props.projectId);
    if (!result) throw new Error('Project details were not found.');
    project.value = result;
  } catch (error: any) {
    loadError.value = projectErrorMessage(error, 'Failed to load project details.');
    toast.error(loadError.value);
    throw error;
  } finally { loading.value = false; }
};
const loadTimeline = async () => {
  if (!props.projectId) return;
  timelineLoading.value = true;
  try { timelineData.value = await projectStore.fetchTimeline(props.projectId); }
  catch (error: any) {
    toast.error(projectErrorMessage(error, 'Failed to load project history.'));
    throw error;
  } finally { timelineLoading.value = false; }
};

const withTimeout = async <T>(promise: Promise<T>, ms: number, message: string): Promise<T> => {
  let timeoutId: ReturnType<typeof setTimeout> | undefined;
  const timeout = new Promise<never>((_, reject) => {
    timeoutId = setTimeout(() => reject(new Error(message)), ms);
  });

  try {
    return await Promise.race([promise, timeout]);
  } finally {
    if (timeoutId) clearTimeout(timeoutId);
  }
};

const projectErrorMessage = (error: any, fallback: string) =>
  error?.response?.data?.message || error?.message || fallback;

const loadDialogData = async () => {
  if (!props.projectId) return;

  const requestId = ++loadRequestId;
  loading.value = true;
  timelineLoading.value = true;
  loadError.value = '';
  activeTab.value = props.initialTab || 'overview';
  project.value = null;
  timelineData.value = null;

  try {
    const [projectResult, timelineResult] = await Promise.all([
      withTimeout(projectStore.fetchProject(props.projectId), 15000, 'Project details took too long to load.'),
      withTimeout(projectStore.fetchTimeline(props.projectId), 15000, 'Project history took too long to load.'),
    ]);

    if (requestId !== loadRequestId) return;
    if (!projectResult) throw new Error('Project details were not found.');
    project.value = projectResult;
    timelineData.value = timelineResult;
  } catch (error: any) {
    if (requestId !== loadRequestId) return;
    loadError.value = projectErrorMessage(error, 'Failed to load project.');
    toast.error(loadError.value);
  } finally {
    if (requestId === loadRequestId) {
      loading.value = false;
      timelineLoading.value = false;
    }
  }
};

const loadUsers = async () => {
  try {
    await userStore.fetchUsers({ per_page: 200, page: 1, is_active: true });
    users.value = [...userStore.users];
  } catch (error) {
    toast.error('Failed to load users');
  }
};

const openAddMember = async () => {
  if (users.value.length === 0) {
    await loadUsers();
  }
  editingMemberId.value = null;
  memberForm.value = {
    user_id: 0,
    role_id: 0,
    assignment_type: 'member',
    can_view: true,
    can_edit: false,
    can_delete: false,
    can_approve: false,
    can_manage_members: false,
  };
  showMemberModal.value = true;
};

const openEditMember = async (member: ProjectMember) => {
  if (users.value.length === 0) {
    await loadUsers();
  }
  editingMemberId.value = member.id;
  memberForm.value = {
    user_id: member.user_id,
    role_id: member.role_id,
    assignment_type: (member.assignment_type as 'member' | 'owner' | 'collaborator' | 'observer') || 'member',
    can_view: memberFlag(member, 'can_view'),
    can_edit: memberFlag(member, 'can_edit'),
    can_delete: memberFlag(member, 'can_delete'),
    can_approve: memberFlag(member, 'can_approve'),
    can_manage_members: memberFlag(member, 'can_manage_members'),
  };
  showMemberModal.value = true;
};

const closeMemberModal = () => {
  showMemberModal.value = false;
  editingMemberId.value = null;
};

watch(() => memberForm.value.user_id, (userId) => {
  const selected = users.value.find((u) => u.id === userId);
  memberForm.value.role_id = selected?.role?.id || 0;
});

const saveMember = async () => {
  if (!props.projectId) return;
  if (!memberForm.value.user_id || !memberForm.value.role_id) {
    toast.error('User with valid default role is required');
    return;
  }

  try {
    await projectStore.addMember(props.projectId, {
      user_id: memberForm.value.user_id,
      role_id: memberForm.value.role_id,
      assignment_type: memberForm.value.assignment_type,
      can_view: memberForm.value.can_view,
      can_edit: memberForm.value.can_edit,
      can_delete: memberForm.value.can_delete,
      can_approve: memberForm.value.can_approve,
      can_manage_members: memberForm.value.can_manage_members,
    });
    toast.success(editingMemberId.value ? 'Member updated' : 'Member added');
    closeMemberModal();
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to save member');
  }
};

const handleRemoveMember = async (memberId: number) => {
  if (!props.projectId) return;
  const confirmed = window.confirm('Remove this member from the project?');
  if (!confirmed) return;
  try {
    await projectStore.removeMember(props.projectId, memberId);
    toast.success('Member removed');
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to remove member');
  }
};
const handleClose = () => {
  loadRequestId++;
  emit('update:modelValue', false);
  project.value = null;
  timelineData.value = null;
  loadError.value = '';
  loading.value = false;
  timelineLoading.value = false;
  closeMemberModal();
};

const handleApprovalSubmit = async (data: { status: string; comments?: string; conditions?: string }) => {
  if (!timelineData.value?.current_approval?.id) return;
  const aid = timelineData.value.current_approval.id;
  try {
    if (data.status === 'returned') {
      await projectStore.rejectProject(aid, { comments: data.comments || '' });
    } else {
      await projectStore.approveProject(aid, data);
    }
    toast.success(data.status === 'returned' ? 'Project returned for revision' : 'Approval action submitted');
    showApprovalModal.value = false;
    await loadTimeline();
    await loadProject();
  } catch (err: any) {
    // Project store handles basic error, but we can do a fallback
  }
};

const openDocumentPicker = () => {
  documentFileInput.value?.click();
};

const handleDocumentFileSelect = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] || null;
  selectedDocumentFile.value = file;
  if (file) {
    documentForm.value.title = file.name.replace(/\.[^/.]+$/, '');
    documentForm.value.category = documentForm.value.category || 'Project File';
  }
};

const clearSelectedDocument = () => {
  selectedDocumentFile.value = null;
  documentForm.value = {
    title: '',
    category: 'Project File',
    description: '',
  };
  if (documentFileInput.value) {
    documentFileInput.value.value = '';
  }
};

const uploadDocument = async () => {
  if (!props.projectId || !selectedDocumentFile.value) return;
  if (!documentForm.value.title.trim()) {
    toast.error('Document title is required');
    return;
  }

  const payload = new FormData();
  payload.append('project_id', String(props.projectId));
  payload.append('title', documentForm.value.title.trim());
  payload.append('category', documentForm.value.category.trim() || 'Project File');
  payload.append('description', documentForm.value.description.trim());
  payload.append('file', selectedDocumentFile.value);

  documentUploading.value = true;
  try {
    await axiosInstance.post('/api/documents', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    toast.success('Attachment uploaded');
    clearSelectedDocument();
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to upload attachment');
  } finally {
    documentUploading.value = false;
  }
};

const downloadDocument = async (doc: ProjectDocument) => {
  try {
    const response = await axiosInstance.get(`/api/documents/${doc.id}/download`, {
      responseType: 'blob',
    });
    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = doc.file_name || doc.title;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to download attachment');
  }
};

const deleteDocument = async (documentId: number) => {
  const confirmed = window.confirm('Delete this attachment?');
  if (!confirmed) return;

  try {
    await axiosInstance.delete(`/api/documents/${documentId}`);
    toast.success('Attachment deleted');
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to delete attachment');
  }
};

const formatTaskStatus = (status: string) =>
  status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

const priorityRank = (priority?: string | null) => {
  const map: Record<string, number> = { critical: 6, urgent: 5, high: 4, medium: 3, normal: 3, low: 1 };
  return priority ? map[priority] || 2 : 2;
};

const sortProjectTasks = (items: Project['tasks']) =>
  [...(items || [])].sort((a, b) => {
    const priorityDiff = priorityRank(b.priority) - priorityRank(a.priority);
    if (priorityDiff !== 0) return priorityDiff;

    const aDue = a.due_date ? new Date(a.due_date).getTime() : Number.POSITIVE_INFINITY;
    const bDue = b.due_date ? new Date(b.due_date).getTime() : Number.POSITIVE_INFINITY;
    if (aDue !== bDue) return aDue - bDue;

    return a.id - b.id;
  });

const fmtFileSize = (bytes: number) => {
  if (!bytes) return 'Unknown size';
  const units = ['B', 'KB', 'MB', 'GB'];
  let size = bytes;
  let unitIndex = 0;
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }
  return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
};

const initials = (n: string) => n.split(' ').map(x => x[0]).slice(0,2).join('').toUpperCase() || '?';
const fmtPeso = (a: number) => `₱${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(a)}`;
const fmtDate = (d: string) => new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
const fmtCoord = (value: number | string | null | undefined) => {
  const num = Number(value);
  return Number.isFinite(num) ? num.toFixed(4) : '';
};
const hasCoordinates = (p: Project) =>
  Number.isFinite(Number(p.location_lat)) && Number.isFinite(Number(p.location_lng));
</script>

<style scoped>
.modal-overlay {
  --v-bg: #ffffff;
  --v-border: #e2e8f0;
  --v-sub: #f8fafc;
  --v-muted: #f1f5f9;
  --v-text: #0f172a;
  --v-text-2: #475569;
  --v-text-3: #94a3b8;
  --v-accent: #2563eb;
  --v-accent-bg: #eff6ff;
  --v-card: #fafafa;
  --v-avatar-bg: #e0e7ff;
  --v-avatar-c: #4338ca;
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(15,23,42,0.65);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  padding: 1rem; overflow-y: auto;
}
:global(.dark) .modal-overlay,
.modal-overlay.is-dark {
  --v-bg: #1e293b;
  --v-border: #334155;
  --v-sub: #1e293b;
  --v-muted: #293548;
  --v-text: #f1f5f9;
  --v-text-2: #94a3b8;
  --v-text-3: #64748b;
  --v-accent: #3b82f6;
  --v-accent-bg: #1e3a5f;
  --v-card: #253548;
  --v-avatar-bg: #312e81;
  --v-avatar-c: #a5b4fc;
  background: rgba(0,0,0,0.75);
}

.modal-panel {
  background: var(--v-bg);
  border-radius: 1.125rem;
  box-shadow: 0 24px 64px rgba(0,0,0,0.2);
  width: 100%; max-width: 860px; max-height: 92vh;
  display: flex; flex-direction: column; overflow: hidden;
}
:global(.dark) .modal-panel,
.modal-overlay.is-dark .modal-panel { box-shadow: 0 24px 64px rgba(0,0,0,0.5); }

/* Loading */
.loading-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5rem; gap: 1rem; color: var(--v-text-3); font-size: 0.9rem; }
.load-actions { display: flex; gap: 0.75rem; }
.retry-btn, .close-btn { border: 1px solid var(--v-border); border-radius: 0.5rem; padding: 0.55rem 1rem; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.retry-btn { background: var(--v-accent); border-color: var(--v-accent); color: white; }
.retry-btn:hover { filter: brightness(1.05); }
.close-btn { background: var(--v-muted); color: var(--v-text-2); }
.close-btn:hover { color: var(--v-text); }
.spinner-lg { width: 2.75rem; height: 2.75rem; border: 3px solid var(--v-muted); border-top-color: var(--v-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
.spinner-sm { display: inline-block; width: 1rem; height: 1rem; border: 2px solid var(--v-muted); border-top-color: var(--v-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Hero */
.hero { padding: 1.375rem; position: relative; overflow: hidden; flex-shrink: 0; }
.hero::before { content: ''; position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E"); }
.hero-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; position: relative; z-index: 1; }
.hero-badges { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.h-code { font-size: 0.68rem; font-weight: 700; color: rgba(255,255,255,0.45); letter-spacing: 0.1em; text-transform: uppercase; }
.h-badge { font-size: 0.62rem; font-weight: 700; padding: 0.12rem 0.45rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em; }
.h-badge.svf { background: rgba(245,158,11,0.25); color: #fcd34d; }
.h-badge.overdue { background: rgba(239,68,68,0.25); color: #fca5a5; }
.h-badge.archived { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.45); }
.hero-actions { display: flex; align-items: center; gap: 0.375rem; position: relative; z-index: 1; }
.h-btn, .h-close { width: 2.125rem; height: 2.125rem; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); border-radius: 0.5rem; cursor: pointer; color: rgba(255,255,255,0.7); transition: all 0.15s; }
.h-btn:hover { background: rgba(255,255,255,0.16); color: white; }
.h-close:hover { background: rgba(239,68,68,0.3); border-color: rgba(239,68,68,0.4); color: white; }
.icon { width: 1rem; height: 1rem; }
.hero-title { font-size: 1.5rem; font-weight: 800; color: white; margin: 0 0 0.75rem; line-height: 1.25; letter-spacing: -0.02em; position: relative; z-index: 1; }
.hero-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.875rem; position: relative; z-index: 1; }
.h-pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.28rem 0.7rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 999px; font-size: 0.78rem; color: rgba(255,255,255,0.75); font-weight: 500; }
.pi { width: 0.78rem; height: 0.78rem; }
.sdot { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.hero-prog { position: relative; z-index: 1; }
.hp-track { height: 0.35rem; background: rgba(255,255,255,0.1); border-radius: 999px; overflow: hidden; }
.hp-fill { height: 100%; background: linear-gradient(90deg,#60a5fa,#34d399); border-radius: 999px; transition: width 0.5s ease; }
.hp-label { font-size: 0.72rem; color: rgba(255,255,255,0.45); margin-top: 0.3rem; display: block; text-align: right; }

/* Tabs */
.tab-nav { display: flex; border-bottom: 1px solid var(--v-border); padding: 0 1.25rem; overflow-x: auto; scrollbar-width: none; flex-shrink: 0; }
.tab-nav::-webkit-scrollbar { display: none; }
.tab-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.75rem 0.875rem; background: none; border: none; border-bottom: 2.5px solid transparent; margin-bottom: -1px; font-size: 0.8rem; font-weight: 500; color: var(--v-text-3); cursor: pointer; white-space: nowrap; transition: all 0.15s; }
.tab-btn:hover { color: var(--v-text-2); }
.tab-btn.active { color: var(--v-accent); border-bottom-color: var(--v-accent); }
.ti { width: 0.875rem; height: 0.875rem; }
.tc { background: var(--v-muted); color: var(--v-text-3); font-size: 0.68rem; font-weight: 700; padding: 0.08rem 0.38rem; border-radius: 999px; }
.tab-btn.active .tc { background: var(--v-accent-bg); color: var(--v-accent); }

/* Tab body */
.tab-body { flex: 1; overflow-y: auto; padding: 1.25rem; overscroll-behavior: contain; }
.tab-pane { animation: fadeUp 0.18s ease; }
@keyframes fadeUp { from{opacity:0;transform:translateY(5px)} to{opacity:1;transform:translateY(0)} }

/* Info cards */
.info-card { background: var(--v-card); border: 1px solid var(--v-border); border-radius: 0.75rem; padding: 1rem; margin-bottom: 0.875rem; }
.ic-head { display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.7rem; font-size: 0.72rem; font-weight: 700; color: var(--v-text-3); text-transform: uppercase; letter-spacing: 0.06em; }
.ci { width: 0.825rem; height: 0.825rem; color: var(--v-accent); }
.desc { font-size: 0.875rem; color: var(--v-text-2); line-height: 1.65; margin: 0; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem; margin-bottom: 0.875rem; }
.two-col .info-card { margin-bottom: 0; }
.d-list { display: flex; flex-direction: column; gap: 0.45rem; }
.d-item { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; }
.dl { font-size: 0.73rem; color: var(--v-text-3); font-weight: 500; white-space: nowrap; flex-shrink: 0; }
.dv { font-size: 0.8rem; font-weight: 600; color: var(--v-text); text-align: right; }
.dv.link { color: var(--v-accent); text-decoration: none; }
.dv.link:hover { text-decoration: underline; }
.ov-text { color: #dc2626; }
:global(.dark) .ov-text { color: #f87171; }
.ok-text { color: #16a34a; }
:global(.dark) .ok-text { color: #4ade80; }
.fin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.fin-item { display: flex; flex-direction: column; gap: 0.2rem; }
.fl { font-size: 0.73rem; color: var(--v-text-3); font-weight: 500; }
.fa { font-size: 1.0625rem; font-weight: 700; color: var(--v-text); }
.fa.sm { font-size: 0.875rem; color: var(--v-text-2); }
.fa.pos { color: #16a34a; } .fa.neg { color: #dc2626; }
:global(.dark) .fa.pos { color: #4ade80; }
:global(.dark) .fa.neg { color: #f87171; }
.coord-chip { display: inline-block; background: var(--v-muted); padding: 0.2rem 0.55rem; border-radius: 0.375rem; font-size: 0.73rem; font-family: monospace; color: var(--v-text-2); margin-top: 0.5rem; }

/* Team */
.pane-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.pane-head h3 { font-size: 1rem; font-weight: 700; color: var(--v-text); margin: 0; }
.add-btn { display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.875rem; background: var(--v-accent-bg); border: 1px solid rgba(37,99,235,0.25); border-radius: 0.5rem; font-size: 0.78rem; font-weight: 600; color: var(--v-accent); cursor: pointer; transition: all 0.15s; }
.add-btn:hover { background: #dbeafe; }
:global(.dark) .add-btn:hover { background: #1e3a5f; }
.members-list { display: flex; flex-direction: column; gap: 0.5rem; }
.member-card { display: flex; align-items: center; gap: 0.875rem; padding: 0.75rem 1rem; background: var(--v-card); border: 1px solid var(--v-border); border-radius: 0.75rem; }
.m-avatar { width: 2.5rem; height: 2.5rem; border-radius: 50%; background: var(--v-avatar-bg); color: var(--v-avatar-c); font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.m-avatar img { width: 100%; height: 100%; object-fit: cover; }
.m-info { flex: 1; }
.m-name { font-size: 0.875rem; font-weight: 600; color: var(--v-text); margin: 0 0 0.1rem; }
.m-role { font-size: 0.78rem; color: var(--v-text-3); margin: 0; }
.m-perms { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.45rem; }
.m-perm { font-size: 0.64rem; padding: 0.15rem 0.4rem; border-radius: 999px; border: 1px solid var(--v-border); color: var(--v-text-3); background: transparent; }
.m-perm.on { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
:global(.dark) .m-perm.on { color: #86efac; background: #14532d; border-color: #166534; }
.m-actions { display: flex; align-items: center; gap: 0.4rem; }
.remove-btn { padding: 0.35rem 0.7rem; background: transparent; border: 1px solid var(--v-border); border-radius: 0.375rem; font-size: 0.73rem; font-weight: 500; color: var(--v-text-2); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.remove-btn:hover { background: #fee2e2; border-color: #fecaca; color: #dc2626; }
:global(.dark) .remove-btn:hover { background: #450a0a; border-color: #7f1d1d; color: #f87171; }
.remove-btn.danger:hover { background: #fee2e2; border-color: #fecaca; color: #dc2626; }
:global(.dark) .remove-btn.danger:hover { background: #450a0a; border-color: #7f1d1d; color: #f87171; }
.empty-pane { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3.5rem 2rem; text-align: center; color: var(--v-text-3); }
.ep-icon { width: 2.75rem; height: 2.75rem; margin-bottom: 0.875rem; }
.empty-pane p { font-size: 0.875rem; margin: 0; }

/* Tasks and attachments */
.pane-sub { margin: 0.18rem 0 0; font-size: 0.78rem; color: var(--v-text-3); }
.task-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.7rem; margin-bottom: 0.875rem; }
.task-stat { min-width: 0; padding: 0.85rem; border: 1px solid var(--v-border); border-radius: 0.7rem; background: var(--v-card); display: flex; flex-direction: column; gap: 0.25rem; }
.task-stat span { font-size: 0.68rem; font-weight: 700; color: var(--v-text-3); text-transform: uppercase; letter-spacing: 0.05em; }
.task-stat strong { font-size: 1.25rem; line-height: 1; color: var(--v-text); }
.task-stat.warn strong { color: #dc2626; }
:global(.dark) .task-stat.warn strong { color: #f87171; }
.execution-row { display: grid; grid-template-columns: 1fr auto; gap: 0.85rem; align-items: center; }
.execution-track, .mini-track { overflow: hidden; background: var(--v-muted); border-radius: 999px; }
.execution-track { height: 0.7rem; }
.execution-fill, .mini-fill { height: 100%; background: linear-gradient(90deg,#2563eb,#14b8a6); border-radius: inherit; transition: width 0.3s ease; }
.execution-row strong { font-size: 0.9rem; color: var(--v-text); min-width: 3.25rem; text-align: right; }
.task-list, .document-list { display: flex; flex-direction: column; gap: 0.65rem; }
.task-card, .document-card { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.9rem; align-items: stretch; padding: 0.9rem; border: 1px solid var(--v-border); border-radius: 0.75rem; background: var(--v-card); }
.task-main { min-width: 0; }
.task-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.65rem; margin-bottom: 0.35rem; }
.task-title-row strong, .doc-main strong { color: var(--v-text); font-size: 0.9rem; line-height: 1.35; }
.task-main p, .doc-main p { color: var(--v-text-2); font-size: 0.8rem; line-height: 1.55; margin: 0.1rem 0 0.55rem; }
.task-status { flex-shrink: 0; border-radius: 999px; padding: 0.18rem 0.5rem; font-size: 0.64rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; border: 1px solid var(--v-border); color: var(--v-text-3); background: var(--v-muted); }
.task-status.pending { color: #92400e; background: #fef3c7; border-color: #fde68a; }
.task-status.in_progress { color: #1d4ed8; background: #dbeafe; border-color: #bfdbfe; }
.task-status.completed { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
.task-status.cancelled { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
:global(.dark) .task-status.pending { color: #fcd34d; background: #451a03; border-color: #78350f; }
:global(.dark) .task-status.in_progress { color: #93c5fd; background: #172554; border-color: #1d4ed8; }
:global(.dark) .task-status.completed { color: #86efac; background: #14532d; border-color: #166534; }
:global(.dark) .task-status.cancelled { color: #fca5a5; background: #450a0a; border-color: #7f1d1d; }
.task-meta, .doc-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 0.45rem 0.8rem; color: var(--v-text-3); font-size: 0.74rem; }
.task-meta .danger { color: #dc2626; font-weight: 700; }
:global(.dark) .task-meta .danger { color: #f87171; }
.subtask-mini-list { display: grid; gap: 0.35rem; margin-top: 0.75rem; padding-top: 0.65rem; border-top: 1px solid var(--v-border); }
.subtask-mini { display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; padding: 0.42rem 0.55rem; border-radius: 0.5rem; background: var(--v-sub); color: var(--v-text-2); font-size: 0.76rem; }
.subtask-mini small { color: var(--v-text-3); font-weight: 700; white-space: nowrap; }
.task-progress { width: 7.5rem; display: flex; flex-direction: column; align-items: flex-end; justify-content: center; gap: 0.4rem; color: var(--v-text); font-weight: 800; font-size: 0.82rem; }
.mini-track { width: 100%; height: 0.45rem; }
.hidden-file { display: none; }
.upload-card { display: grid; gap: 0.65rem; padding: 0.9rem; margin-bottom: 0.9rem; border: 1px solid rgba(37,99,235,0.28); border-radius: 0.75rem; background: var(--v-accent-bg); }
.upload-copy { display: flex; justify-content: space-between; gap: 0.8rem; color: var(--v-text); font-size: 0.82rem; }
.upload-copy span { color: var(--v-text-3); font-weight: 600; white-space: nowrap; }
.upload-textarea { min-height: 4.75rem; resize: vertical; }
.upload-actions { display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem; }
.add-btn:disabled { cursor: not-allowed; opacity: 0.65; }
.document-card { grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; }
.doc-icon { width: 2.35rem; height: 2.35rem; border-radius: 0.65rem; display: flex; align-items: center; justify-content: center; color: var(--v-accent); background: var(--v-accent-bg); border: 1px solid rgba(37,99,235,0.18); }
.doc-main { min-width: 0; }
.doc-actions { display: flex; align-items: center; gap: 0.35rem; }
.icon-action { width: 2.05rem; height: 2.05rem; border: 1px solid var(--v-border); border-radius: 0.5rem; background: var(--v-sub); color: var(--v-text-2); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; }
.icon-action:hover { color: var(--v-accent); border-color: rgba(37,99,235,0.35); background: var(--v-accent-bg); }
.icon-action.danger:hover { color: #dc2626; border-color: #fecaca; background: #fee2e2; }
:global(.dark) .icon-action.danger:hover { color: #f87171; border-color: #7f1d1d; background: #450a0a; }

/* Member modal */
.member-overlay { z-index: 10010; }
.member-modal { width: 100%; max-width: 560px; border-radius: 0.9rem; background: var(--v-bg); border: 1px solid var(--v-border); box-shadow: 0 18px 42px rgba(0,0,0,0.26); }
.member-head { display: flex; align-items: center; justify-content: space-between; padding: 0.9rem 1rem; border-bottom: 1px solid var(--v-border); }
.member-head h3 { margin: 0; font-size: 1rem; color: var(--v-text); }
.member-body { padding: 1rem; display: flex; flex-direction: column; gap: 0.55rem; }
.member-label { font-size: 0.78rem; font-weight: 600; color: var(--v-text-2); margin-top: 0.15rem; }
.member-input { width: 100%; border-radius: 0.5rem; border: 1px solid var(--v-border); background: var(--v-sub); color: var(--v-text); font-size: 0.82rem; padding: 0.52rem 0.65rem; }
.member-perm-grid { display: grid; gap: 0.45rem; margin-top: 0.45rem; }
.member-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--v-text-2); }
.member-foot { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.85rem 1rem; border-top: 1px solid var(--v-border); }

/* Timeline */
.tl-loading { display: flex; align-items: center; gap: 0.5rem; padding: 2rem; color: var(--v-text-3); font-size: 0.875rem; }
.tl-section { margin-bottom: 1.75rem; }
.tl-title { font-size: 0.72rem; font-weight: 700; color: var(--v-text-3); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 0.875rem; }
.tl-items { display: flex; flex-direction: column; gap: 0.75rem; }
.tl-item { display: flex; gap: 0.875rem; }
.tl-dot { width: 1.875rem; height: 1.875rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 0.1rem; }
.s-dot { background: var(--v-accent-bg); color: var(--v-accent); }
.st-dot { background: #f0fdf4; color: #16a34a; }
:global(.dark) .st-dot { background: #14532d; color: #4ade80; }
.ti- { width: 0.8rem; height: 0.8rem; }
.tl-content { flex: 1; }
.tl-text { font-size: 0.8625rem; color: var(--v-text-2); margin: 0 0 0.2rem; }
.from { color: var(--v-text-3); }
.tl-reason { font-size: 0.8rem; color: var(--v-text-3); margin: 0 0 0.2rem; font-style: italic; }
.tl-meta { font-size: 0.73rem; color: var(--v-text-3); margin: 0; }

/* Modal transition */
.modal-enter-active { animation: ovIn 0.22s ease; }
.modal-leave-active { animation: ovIn 0.18s ease reverse; }
@keyframes ovIn { from{opacity:0} to{opacity:1} }
.modal-enter-active .modal-panel { animation: panIn 0.28s cubic-bezier(0.34,1.4,0.64,1); }
.modal-leave-active .modal-panel { animation: panIn 0.18s ease reverse; }
@keyframes panIn { from{transform:scale(0.93) translateY(18px)} to{transform:scale(1) translateY(0)} }

@media(max-width:640px) {
  .two-col { grid-template-columns: 1fr; }
  .fin-grid { grid-template-columns: 1fr 1fr; }
  .hero-title { font-size: 1.25rem; }
  .task-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .task-card { grid-template-columns: 1fr; }
  .task-progress { width: 100%; align-items: stretch; }
  .document-card { grid-template-columns: auto minmax(0, 1fr); align-items: start; }
  .doc-actions { grid-column: 1 / -1; justify-content: flex-end; }
  .upload-copy { flex-direction: column; gap: 0.2rem; }
}
</style>
