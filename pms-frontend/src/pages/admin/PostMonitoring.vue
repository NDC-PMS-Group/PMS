<template>
  <div class="monitoring-page" :class="{ 'is-dark': isDark }">
    <header class="page-head">
      <div>
        <p class="eyebrow">SOI-02 Portfolio Compliance</p>
        <h1>Implementation Monitoring</h1>
        <p>Track implementation milestones, review proponent submissions, and monitor accepted post-investment outcomes.</p>
      </div>
      <button class="refresh-btn" :disabled="loading" @click="loadReports">
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
        <input v-model="search" placeholder="Search project, code, or proponent" @keyup.enter="loadReports" />
      </div>
      <select v-model="status" @change="loadReports">
        <option value="">All report states</option>
        <option value="submitted">Submitted for review</option>
        <option value="returned">Returned</option>
        <option value="draft">Draft in progress</option>
        <option value="accepted">Accepted</option>
      </select>
      <label class="overdue-toggle">
        <input v-model="overdueOnly" type="checkbox" @change="loadReports" />
        Overdue only
      </label>
      <button class="search-btn" @click="loadReports">Apply</button>
    </section>

    <div v-if="loading && !reports.length" class="state-card">Loading implementation monitoring portfolio...</div>
    <div v-else-if="!reports.length" class="state-card">
      <ClipboardCheck />
      <strong>No monitoring reports found</strong>
      <span>Open a monitoring period from an approved project to begin.</span>
    </div>

    <section v-else class="report-list">
      <article v-for="project in reports" :key="project.id" class="report-row">
        <div class="report-main">
          <div class="report-topline">
            <span class="project-code">{{ project.project_code }}</span>
            <span class="status-badge" :class="project.monitoring_submission_status">
              {{ statusLabel(project.monitoring_submission_status) }}
            </span>
            <span v-if="isOverdue(project)" class="overdue-badge">Overdue</span>
          </div>
          <h2>{{ project.title }}</h2>
          <p>{{ project.proponent_name || project.proponent_user?.organization_name || 'No proponent recorded' }}</p>
          <div class="report-meta">
            <span><CalendarClock /> Due {{ formatDate(project.monitoring_due_date) }}</span>
            <span v-if="project.monitoring_submitted_at"><Send /> Submitted {{ formatDate(project.monitoring_submitted_at) }}</span>
          </div>
        </div>

        <div class="metrics">
          <div><strong>{{ totalJobs(project) }}</strong><span>Jobs</span></div>
          <div><strong>{{ money(project.financial_metrics?.actual_revenue) }}</strong><span>Actual revenue</span></div>
          <div><strong>{{ money(project.financial_metrics?.dividend_remittance) }}</strong><span>Remittance</span></div>
        </div>

        <button class="review-btn" @click="openProject(project.id)">
          {{ project.monitoring_submission_status === 'submitted' ? 'Review Report' : 'View Details' }}
          <ArrowRight />
        </button>
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
  ArrowRight, CalendarClock, CheckCircle2, ClipboardCheck, Clock3,
  AlertTriangle, RefreshCw, RotateCcw, Search, Send,
} from 'lucide-vue-next';
import axiosInstance from '@/utils/axiosInstance';
import type { Project } from '@/types/project';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import { toast } from 'vue3-toastify';

const reports = ref<Project[]>([]);
const loading = ref(false);
const search = ref('');
const status = ref('');
const overdueOnly = ref(false);
const router = useRouter();
const layoutStore = useLayoutStore();
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK);

const counts = computed(() => ({
  submitted: reports.value.filter((item) => item.monitoring_submission_status === 'submitted').length,
  returned: reports.value.filter((item) => item.monitoring_submission_status === 'returned').length,
  accepted: reports.value.filter((item) => item.monitoring_submission_status === 'accepted').length,
  overdue: reports.value.filter(isOverdue).length,
}));
const summaryCards = computed(() => [
  { label: 'Awaiting review', value: counts.value.submitted, icon: Clock3, tone: 'blue' },
  { label: 'Returned', value: counts.value.returned, icon: RotateCcw, tone: 'red' },
  { label: 'Accepted', value: counts.value.accepted, icon: CheckCircle2, tone: 'green' },
  { label: 'Overdue', value: counts.value.overdue, icon: AlertTriangle, tone: 'amber' },
]);

async function loadReports() {
  loading.value = true;
  try {
    const response = await axiosInstance.get('/api/post-monitoring', {
      params: {
        per_page: 100,
        search: search.value || undefined,
        submission_status: status.value || undefined,
        overdue: overdueOnly.value ? 1 : undefined,
      },
    });
    reports.value = response.data?.data || [];
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to load post-monitoring reports.');
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
function openProject(id: number) {
  selectedProjectId.value = id;
  selectedInitialTab.value = 'monitoring';
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
  await loadReports();
};
function statusLabel(value?: string) {
  return ({
    draft: 'Draft in progress',
    submitted: 'Submitted',
    returned: 'Returned',
    accepted: 'Accepted',
  } as Record<string, string>)[value || ''] || 'Not requested';
}
function formatDate(value?: string | null) {
  if (!value) return 'Not set';
  return new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}
function isOverdue(project: Project) {
  return Boolean(
    project.monitoring_due_date
    && new Date(project.monitoring_due_date) < new Date()
    && project.monitoring_submission_status !== 'accepted'
  );
}
function totalJobs(project: Project) {
  const metrics = project.financial_metrics || {};
  return Number(metrics.jobs_generated_direct || 0)
    + Number(metrics.jobs_generated_indirect || 0)
    + Number(metrics.retained_jobs || 0);
}
function money(value?: number | null) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(Number(value || 0));
}

onMounted(loadReports);
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
.toolbar { display:grid; grid-template-columns:minmax(16rem,1fr) 13rem auto auto; gap:.65rem; padding:.8rem; margin-bottom:1rem; border:1px solid var(--border); background:var(--card); border-radius:.5rem; }
.search-box { display:flex; align-items:center; gap:.55rem; } .search-box svg { width:1rem; color:var(--muted); }
.search-box input,.toolbar select { width:100%; min-height:2.45rem; border:1px solid var(--border); border-radius:.4rem; background:var(--bg); color:var(--text); padding:0 .7rem; }
.search-box input { border:0; background:transparent; padding:0; outline:0; }
.overdue-toggle { display:flex; align-items:center; gap:.45rem; color:var(--muted); font-size:.8rem; font-weight:700; white-space:nowrap; }
.search-btn,.review-btn { background:#2563eb; border-color:#2563eb; color:#fff; }
.report-list { display:grid; gap:.65rem; }
.report-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; gap:1.2rem; align-items:center; padding:1rem; border:1px solid var(--border); border-radius:.5rem; background:var(--card); }
.report-topline,.report-meta { display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; }
.project-code { color:#2563eb; font-size:.72rem; font-weight:900; letter-spacing:.05em; }
.status-badge,.overdue-badge { padding:.18rem .5rem; border-radius:999px; font-size:.65rem; font-weight:900; text-transform:uppercase; }
.status-badge.submitted{background:#dbeafe;color:#1d4ed8}.status-badge.returned{background:#fee2e2;color:#b91c1c}.status-badge.draft{background:#fef3c7;color:#92400e}.status-badge.accepted{background:#dcfce7;color:#166534}.overdue-badge{background:#fee2e2;color:#b91c1c}
.report-main h2 { margin:.35rem 0 .2rem; font-size:1rem; } .report-main>p { margin:0; color:var(--muted); font-size:.8rem; }
.report-meta { margin-top:.55rem; color:var(--muted); font-size:.72rem; } .report-meta span { display:flex; align-items:center; gap:.3rem; } .report-meta svg { width:.85rem; }
.metrics { display:grid; grid-template-columns:repeat(3,8rem); } .metrics div { display:grid; padding:0 .75rem; border-left:1px solid var(--border); }
.metrics strong { font-size:.9rem; } .metrics span { color:var(--muted); font-size:.68rem; }
.state-card { min-height:14rem; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.45rem; border:1px dashed var(--border); border-radius:.5rem; background:var(--card); color:var(--muted); }
.state-card svg { width:2rem; } .state-card strong { color:var(--text); }
@media(max-width:1000px){.summary-grid{grid-template-columns:repeat(2,1fr)}.toolbar{grid-template-columns:1fr 1fr}.report-row{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(3,1fr)}.review-btn{justify-self:start}}
@media(max-width:640px){.monitoring-page{padding:1rem}.page-head{flex-direction:column}.summary-grid,.toolbar{grid-template-columns:1fr}.metrics{grid-template-columns:1fr}.metrics div{border-left:0;border-top:1px solid var(--border);padding:.55rem 0}}
</style>
