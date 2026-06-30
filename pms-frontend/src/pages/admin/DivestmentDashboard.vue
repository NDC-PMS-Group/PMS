<template>
  <div class="monitoring-page" :class="{ 'is-dark': isDark }">
    <header class="page-head">
      <div>
        <p class="eyebrow">SOI-03 Portfolio Exit Strategy</p>
        <h1>Divestment Dashboard</h1>
        <p>Manage active exit work: due diligence, ManCom and Board approvals, transfer documents, collections, and closing evidence.</p>
      </div>
      <button class="refresh-btn" :disabled="loading" @click="loadProjects">
        <RefreshCw :class="{ spin: loading }" /> Refresh
      </button>
    </header>

    <section class="summary-grid">
      <article v-for="item in summaryCards" :key="item.label">
        <span class="summary-icon" :class="item.tone"><component :is="item.icon" /></span>
        <div><strong>{{ item.value }}</strong><span>{{ item.label }}</span></div>
      </article>
    </section>

    <section class="toolbar">
      <div class="search-box">
        <Search />
        <input v-model="search" placeholder="Search project, code, or proponent" @keyup.enter="loadProjects" />
      </div>
      <select v-model="stageFilter" @change="loadProjects">
        <option value="">All stages</option>
        <option value="due_diligence">Due Diligence</option>
        <option value="mancom_review">ManCom Review</option>
        <option value="board_review">Board Review</option>
        <option value="transfer">Transfer</option>
      </select>
      <button class="search-btn" @click="loadProjects">Apply</button>
    </section>

    <!-- Loading state -->
    <div v-if="loading && !projects.length" class="state-card">Loading divestment portfolio...</div>
    <!-- Empty state -->
    <div v-else-if="!projects.length" class="state-card">
      <TrendingDown />
      <strong>No divestment projects found</strong>
      <span>Projects entering the divestment pipeline will appear here.</span>
    </div>

    <!-- Project cards -->
    <section v-else class="report-list">
      <article v-for="project in projects" :key="project.id" class="report-row">
        <div class="report-main">
          <div class="report-topline">
            <span class="project-code">{{ project.project_code }}</span>
            <span class="status-badge" :class="statusClass(project)">
              {{ statusLabel(project) }}
            </span>
            <span class="stage-badge" :class="stageClass(project)">
              {{ project.current_stage?.name || 'No stage' }}
            </span>
            <span v-if="isPendingTransfer(project)" class="overdue-badge">Pending Transfer</span>
          </div>
          <h2>{{ project.title }}</h2>
          <p>{{ project.proponent_name || project.proponent_user?.organization_name || 'No proponent recorded' }}</p>
          <div class="report-meta">
            <span><Building2 /> {{ money(project.estimated_cost) }} Investment</span>
            <span><FileCheck /> {{ project.current_stage?.name || 'Not set' }}</span>
            <span><TrendingDown /> {{ exitPhaseLabel(project) }}</span>
          </div>
          <div class="phase-strip">
            <span v-for="phase in exitPhases(project)" :key="phase.key" :class="{ done: phase.progress === 100, active: phase.progress > 0 && phase.progress < 100 }">
              {{ phase.label }} {{ phase.completedChecklist }}/{{ phase.totalChecklist }}
            </span>
          </div>
        </div>

        <div class="metrics">
          <div><strong>{{ money(project.estimated_cost) }}</strong><span>Investment Value</span></div>
          <div><strong>{{ exitProgress(project) }}%</strong><span>Exit Progress</span></div>
          <div><strong>{{ overdueDivestmentTasks(project) }}</strong><span>Overdue Exit Tasks</span></div>
        </div>

        <div class="row-actions">
          <button class="review-btn" @click="openProject(project.id, 'approval')">
            SOI Flow <ArrowRight />
          </button>
          <button class="review-btn secondary" @click="openProject(project.id, 'tasks')">
            Work Plan <ArrowRight />
          </button>
        </div>
      </article>
    </section>

    <!-- Dialogs -->
    <CreateEditProjectDialog v-model="showCreateEditDialog" :project="selectedProject" @saved="onProjectSaved" />
    <ViewProjectDialog
      v-if="showViewDialog && selectedProjectId"
      :key="`${selectedProjectId}-${selectedInitialTab}`"
      :modelValue="true"
      :projectId="selectedProjectId"
      :initialTab="selectedInitialTab"
      @update:modelValue="handleViewDialogVisibility"
      @edit="openEditFromView"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import ViewProjectDialog from '@/components/projects/ViewProjectDialog.vue';
import CreateEditProjectDialog from '@/components/projects/CreateEditProjectDialog.vue';
import { useRouter } from 'vue-router';
import {
  ArrowRight, CheckCircle2, Clock3, AlertTriangle, RefreshCw,
  Search, TrendingDown, Building2, FileCheck,
} from 'lucide-vue-next';
import axiosInstance from '@/utils/axiosInstance';
import type { Project } from '@/types/project';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import { toast } from 'vue3-toastify';
import { buildSoiTaskSections } from '@/utils/soiWorkflow';

const projects = ref<Project[]>([]);
const loading = ref(false);
const search = ref('');
const stageFilter = ref('');
const router = useRouter();
const layoutStore = useLayoutStore();
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK);

/* ─── Summary card counts ─── */
const counts = computed(() => {
  const list = projects.value;
  return {
    forApproval: list.filter(isForApproval).length,
    dueDiligence: list.filter(isDueDiligence).length,
    boardApproved: list.filter(isBoardApproved).length,
    pendingTransfer: list.filter(isPendingTransfer).length,
  };
});

const summaryCards = computed(() => [
  { label: 'For Approval', value: counts.value.forApproval, icon: Clock3, tone: 'blue' },
  { label: 'In Due Diligence', value: counts.value.dueDiligence, icon: AlertTriangle, tone: 'amber' },
  { label: 'Board Approved', value: counts.value.boardApproved, icon: CheckCircle2, tone: 'green' },
  { label: 'Pending Transfer', value: counts.value.pendingTransfer, icon: TrendingDown, tone: 'red' },
]);

/* ─── Classification helpers ─── */
function isForApproval(project: Project): boolean {
  const stageName = (project.current_stage?.name || '').toLowerCase();
  const statusName = (project.status?.name || '').toLowerCase();
  return stageName.includes('divestment') && statusName.includes('approval');
}

function isDueDiligence(project: Project): boolean {
  const stageName = (project.current_stage?.name || '').toLowerCase();
  const statusName = (project.status?.name || '').toLowerCase();
  return statusName.includes('due_diligence') || stageName.includes('due diligence');
}

function isBoardApproved(project: Project): boolean {
  const statusName = (project.status?.name || '').toLowerCase();
  return statusName === 'approved' || statusName === 'divested';
}

function isPendingTransfer(project: Project): boolean {
  const statusName = (project.status?.name || '').toLowerCase();
  return Boolean(
    project.target_completion_date
    && new Date(project.target_completion_date) < new Date()
    && statusName !== 'divested'
  );
}

/* ─── Data fetching ─── */
async function loadProjects() {
  loading.value = true;
  try {
    const params: Record<string, unknown> = {
      divestment_active: 1,
      with_tasks: 1,
      per_page: 100,
      search: search.value || undefined,
    };
    if (stageFilter.value) {
      params.stage_name = stageFilter.value;
    }
    const response = await axiosInstance.get('/api/projects', { params });
    projects.value = response.data?.data || [];
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to load divestment portfolio.');
  } finally {
    loading.value = false;
  }
}

const showViewDialog = ref(false);
const showCreateEditDialog = ref(false);
const selectedProjectId = ref<number | null>(null);
const selectedInitialTab = ref<'overview' | 'tasks' | 'approval' | 'monitoring'>('overview');
const selectedProject = ref<Project | null>(null);

/* ─── Navigation ─── */
function openProject(id: number, tab: 'approval' | 'tasks') {
  selectedProjectId.value = id;
  selectedInitialTab.value = tab;
  showViewDialog.value = true;
}

function handleViewDialogVisibility(visible: boolean) {
  showViewDialog.value = visible;
  if (!visible) {
    selectedProjectId.value = null;
  }
}

function openEditFromView(projectData: Project) {
  selectedProject.value = projectData;
  showCreateEditDialog.value = true;
}

const onProjectSaved = async (savedProject: Project | null) => {
  if (savedProject?.id) {
    selectedInitialTab.value = 'overview';
    selectedProjectId.value = savedProject.id;
    showViewDialog.value = true;
  }
  await loadProjects();
};

function exitPhases(project: Project) {
  const tasks = (project.tasks || []).filter((task: any) =>
    task.soi_section === 'divestment' || String(task.title || '').toLowerCase().includes('divest')
  );
  return buildSoiTaskSections(tasks as any[], 'divestment');
}

function exitProgress(project: Project) {
  const phases = exitPhases(project);
  const total = phases.reduce((sum, phase) => sum + phase.totalChecklist, 0);
  const completed = phases.reduce((sum, phase) => sum + phase.completedChecklist, 0);
  return total ? Math.round((completed / total) * 100) : Number(project.progress_percentage || 0);
}

function overdueDivestmentTasks(project: Project) {
  return (project.tasks || []).filter((task: any) =>
    (task.soi_section === 'divestment' || String(task.title || '').toLowerCase().includes('divest'))
    && task.is_overdue
    && task.status !== 'completed'
  ).length;
}

function exitPhaseLabel(project: Project) {
  const phases = exitPhases(project);
  const active = phases.find((phase) => phase.progress > 0 && phase.progress < 100)
    || phases.find((phase) => phase.progress < 100)
    || phases[phases.length - 1];
  return active?.label || 'Exit readiness';
}

/* ─── Display helpers ─── */
function statusLabel(project: Project): string {
  const name = (project.status?.name || '').toLowerCase();
  const labels: Record<string, string> = {
    approved: 'Approved',
    divested: 'Divested',
    pending: 'Pending',
    active: 'Active',
    for_approval: 'For Approval',
    due_diligence: 'Due Diligence',
  };
  return labels[name] || project.status?.name || 'Unknown';
}

function statusClass(project: Project): string {
  const name = (project.status?.name || '').toLowerCase();
  if (name === 'approved' || name === 'divested') return 'accepted';
  if (name.includes('approval') || name === 'pending') return 'submitted';
  if (name.includes('due_diligence')) return 'draft';
  return 'returned';
}

function stageClass(project: Project): string {
  const name = (project.current_stage?.name || '').toLowerCase();
  if (name.includes('board')) return 'accepted';
  if (name.includes('mancom')) return 'submitted';
  if (name.includes('due diligence')) return 'draft';
  if (name.includes('transfer')) return 'returned';
  return 'draft';
}

function formatTrack(track?: string | null): string {
  if (!track) return 'Not set';
  return track.charAt(0).toUpperCase() + track.slice(1).replace(/_/g, ' ');
}

function money(value?: number | null): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(Number(value || 0));
}

onMounted(loadProjects);
</script>

<style scoped>
.monitoring-page { --bg:#f8fafc; --card:#fff; --border:#dbe3ee; --text:#0f172a; --muted:#64748b; min-height:100%; padding:2rem; background:var(--bg); color:var(--text); }
.monitoring-page.is-dark { --bg:#0f172a; --card:#162238; --border:#2b3a52; --text:#f1f5f9; --muted:#94a3b8; }
.page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
.eyebrow { margin:0 0 .25rem; color:#2563eb; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
h1 { margin:0; font-size:1.7rem; letter-spacing:0; } .page-head p:last-child { margin:.35rem 0 0; color:var(--muted); }
.refresh-btn,.search-btn,.review-btn { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; border:1px solid var(--border); border-radius:.45rem; background:var(--card); color:var(--text); min-height:2.5rem; padding:0 .85rem; font-weight:700; cursor:pointer; }
.refresh-btn svg,.review-btn svg { width:1rem; } .spin { animation:spin 1s linear infinite; } @keyframes spin { to { transform:rotate(360deg); } }
.summary-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem; margin-bottom:1rem; }
.summary-grid article { display:flex; align-items:center; gap:.75rem; padding:1rem; border:1px solid var(--border); background:var(--card); border-radius:.5rem; }
.summary-grid article div { display:grid; } .summary-grid strong { font-size:1.35rem; } .summary-grid article span:last-child { color:var(--muted); font-size:.76rem; }
.summary-icon { width:2.25rem; height:2.25rem; display:grid; place-items:center; border-radius:.45rem; } .summary-icon svg { width:1.1rem; }
.summary-icon.blue{background:#dbeafe;color:#2563eb}.summary-icon.red{background:#fee2e2;color:#dc2626}.summary-icon.green{background:#dcfce7;color:#16a34a}.summary-icon.amber{background:#fef3c7;color:#d97706}
.toolbar { display:grid; grid-template-columns:minmax(16rem,1fr) 13rem auto; gap:.65rem; padding:.8rem; margin-bottom:1rem; border:1px solid var(--border); background:var(--card); border-radius:.5rem; }
.search-box { display:flex; align-items:center; gap:.55rem; } .search-box svg { width:1rem; color:var(--muted); }
.search-box input,.toolbar select { width:100%; min-height:2.45rem; border:1px solid var(--border); border-radius:.4rem; background:var(--bg); color:var(--text); padding:0 .7rem; }
.search-box input { border:0; background:transparent; padding:0; outline:0; }
.search-btn,.review-btn { background:#2563eb; border-color:#2563eb; color:#fff; }
.review-btn.secondary { background:var(--card); color:var(--text); border-color:var(--border); }
.row-actions { display:grid; gap:.45rem; }
.report-list { display:grid; gap:.65rem; }
.report-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; gap:1.2rem; align-items:center; padding:1rem; border:1px solid var(--border); border-radius:.5rem; background:var(--card); }
.report-topline,.report-meta { display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; }
.project-code { color:#2563eb; font-size:.72rem; font-weight:900; letter-spacing:.05em; }
.status-badge,.overdue-badge,.stage-badge { padding:.18rem .5rem; border-radius:999px; font-size:.65rem; font-weight:900; text-transform:uppercase; }
.status-badge.submitted{background:#dbeafe;color:#1d4ed8}.status-badge.returned{background:#fee2e2;color:#b91c1c}.status-badge.draft{background:#fef3c7;color:#92400e}.status-badge.accepted{background:#dcfce7;color:#166534}
.stage-badge.submitted{background:#dbeafe;color:#1d4ed8}.stage-badge.returned{background:#fee2e2;color:#b91c1c}.stage-badge.draft{background:#fef3c7;color:#92400e}.stage-badge.accepted{background:#dcfce7;color:#166534}
.overdue-badge{background:#fee2e2;color:#b91c1c}
.report-main h2 { margin:.35rem 0 .2rem; font-size:1rem; } .report-main>p { margin:0; color:var(--muted); font-size:.8rem; }
.report-meta { margin-top:.55rem; color:var(--muted); font-size:.72rem; } .report-meta span { display:flex; align-items:center; gap:.3rem; } .report-meta svg { width:.85rem; }
.phase-strip { display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.65rem; }
.phase-strip span { border:1px solid var(--border); border-radius:999px; padding:.2rem .5rem; color:var(--muted); font-size:.65rem; font-weight:900; }
.phase-strip span.active { border-color:#93c5fd; background:#dbeafe; color:#1d4ed8; }
.phase-strip span.done { border-color:#bbf7d0; background:#dcfce7; color:#166534; }
.metrics { display:grid; grid-template-columns:repeat(3,8rem); } .metrics div { display:grid; padding:0 .75rem; border-left:1px solid var(--border); }
.metrics strong { font-size:.9rem; } .metrics span { color:var(--muted); font-size:.68rem; }
.state-card { min-height:14rem; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.45rem; border:1px dashed var(--border); border-radius:.5rem; background:var(--card); color:var(--muted); }
.state-card svg { width:2rem; } .state-card strong { color:var(--text); }
@media(max-width:1000px){.summary-grid{grid-template-columns:repeat(2,1fr)}.toolbar{grid-template-columns:1fr 1fr}.report-row{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(3,1fr)}.review-btn{justify-self:start}}
@media(max-width:640px){.monitoring-page{padding:1rem}.page-head{flex-direction:column}.summary-grid,.toolbar{grid-template-columns:1fr}.metrics{grid-template-columns:1fr}.metrics div{border-left:0;border-top:1px solid var(--border);padding:.55rem 0}}
</style>
