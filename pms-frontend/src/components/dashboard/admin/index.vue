<template>
  <div class="pms-dashboard" :class="{ 'is-dark': isDarkMode }">
    <section class="summary-grid">
      <div v-for="card in cards" :key="card.label" class="metric-card">
        <div class="metric-copy">
          <p class="metric-value">{{ card.value }}</p>
          <p class="metric-label">{{ card.label }}</p>
        </div>
        <div class="metric-icon" :class="card.tone">
          <component :is="card.icon" class="icon" />
        </div>
      </div>
    </section>

    <section class="hero-grid">
      <div class="panel pending-panel">
        <div class="panel-head">
          <div>
            <h2>Pending Actions</h2>
            <p>Approvals waiting for your role</p>
          </div>
          <button class="ghost-btn" @click="loadDashboard" :disabled="loading">
            <RefreshCwIcon class="icon" />
          </button>
        </div>

        <div v-if="loading" class="state-line">
          <span class="spinner"></span>
          Loading dashboard...
        </div>

        <template v-else>
          <div v-if="pendingActions.length === 0" class="empty-state slim">
            <CheckCircleIcon class="empty-icon" />
            <p>No approval actions pending.</p>
          </div>

          <template v-else>
            <button
              v-for="action in pendingActions"
              :key="action.approval_id"
              class="action-row"
              @click="openApproval(action.project_id)"
            >
              <div class="action-main">
                <span class="project-code">{{ action.project_code }}</span>
                <strong>{{ action.title }}</strong>
                <span>{{ action.current_step }} · {{ action.role }}</span>
              </div>
              <div class="action-meta">
                <span>{{ action.status }}</span>
                <ArrowRightIcon class="icon" />
              </div>
            </button>
          </template>

          <div v-if="revisionRequests.length" class="revision-block">
            <div class="queue-title">
              <RotateCcwIcon class="icon" />
              <span>Returned for Revision</span>
            </div>
            <button
              v-for="request in revisionRequests"
              :key="request.approval_id"
              class="action-row revision"
              @click="openApproval(request.project_id)"
            >
              <div class="action-main">
                <span class="project-code">{{ request.project_code }}</span>
                <strong>{{ request.title }}</strong>
                <span>{{ request.status }} · update required</span>
              </div>
              <div class="action-meta">
                <span>Revision</span>
                <ArrowRightIcon class="icon" />
              </div>
            </button>
          </div>
        </template>
      </div>

      <div class="panel chart-panel workflow-card">
        <div class="panel-head">
          <div>
            <h2>Workflow Analytics</h2>
            <p>Current routing health</p>
          </div>
        </div>
        <div class="donut-wrap compact">
          <apexchart v-if="workflowChartSeries.length" height="260" type="donut" :series="workflowChartSeries" :options="workflowChartOptions" />
          <div v-else class="empty-state small"><p>No workflow data yet.</p></div>
        </div>
      </div>
    </section>

    <section class="chart-grid">
      <div class="panel chart-panel wide">
        <div class="panel-head">
          <div>
            <h2>Project Lifecycle Distribution</h2>
            <p>Volume by current project stage</p>
          </div>
        </div>
        <apexchart height="320" type="bar" :series="stageChartSeries" :options="stageChartOptions" />
      </div>

      <div class="panel risk-panel">
        <div class="panel-head">
          <div>
            <h2>Execution Snapshot</h2>
            <p>Counts that need attention</p>
          </div>
        </div>
        <div class="risk-list">
          <div v-for="signal in riskSignals" :key="signal.label" class="risk-item">
            <div class="risk-dot" :class="signal.tone"></div>
            <div>
              <strong>{{ signal.value }}</strong>
              <span>{{ signal.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="dash-grid">
      <div class="panel">
        <div class="panel-head">
          <div>
            <h2>Projects by Stage</h2>
            <p>Lifecycle distribution</p>
          </div>
        </div>
        <div class="bar-list">
          <div v-for="item in stageBreakdown" :key="item.name" class="bar-row">
            <div class="bar-label">
              <span>{{ item.name }}</span>
              <strong>{{ item.count }}</strong>
            </div>
            <div class="bar-track"><div class="bar-fill" :style="{ width: `${item.percent}%`, background: item.color }"></div></div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div>
            <h2>Projects by Status</h2>
            <p>Operational status mix</p>
          </div>
        </div>
        <div class="bar-list">
          <div v-for="item in statusBreakdown" :key="item.name" class="bar-row">
            <div class="bar-label">
              <span>{{ item.name }}</span>
              <strong>{{ item.count }}</strong>
            </div>
            <div class="bar-track"><div class="bar-fill alt" :style="{ width: `${item.percent}%`, background: item.color }"></div></div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, markRaw, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axiosInstance from '@/utils/axiosInstance';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import {
  AlertCircle as AlertCircleIcon,
  ArrowRight as ArrowRightIcon,
  CheckCircle as CheckCircleIcon,
  ClipboardCheck as ClipboardCheckIcon,
  FolderKanban as FolderKanbanIcon,
  RefreshCw as RefreshCwIcon,
  RotateCcw as RotateCcwIcon,
  TimerReset as TimerResetIcon,
} from 'lucide-vue-next';

interface PendingAction {
  approval_id: number;
  project_id: number;
  project_code: string;
  title: string;
  current_step: string;
  role: string;
  status: string;
  overall_status: string;
}

interface DashboardStats {
  total_projects: number;
  my_projects: number;
  pending_approvals: number;
  overdue_tasks: number;
  my_tasks: number;
  completed_this_month: number;
  approved_with_conditions: number;
  revision_requests_count: number;
  active_workflows: number;
  pending_actions: PendingAction[];
  revision_requests: PendingAction[];
  workflow_summary: { overall_status: string; count: number }[];
  projects_by_stage: { count: number; current_stage?: { name: string } }[];
  projects_by_status: { count: number; status?: { name: string } }[];
}

const router = useRouter();
const layoutStore = useLayoutStore();
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);
const loading = ref(false);
const stats = ref<DashboardStats | null>(null);

const cards = computed(() => [
  { label: 'Total Projects', value: stats.value?.total_projects || 0, icon: markRaw(FolderKanbanIcon), tone: 'blue' },
  { label: 'Approval Queue', value: stats.value?.pending_approvals || 0, icon: markRaw(ClipboardCheckIcon), tone: 'amber' },
  { label: 'Revision Requests', value: stats.value?.revision_requests_count || 0, icon: markRaw(RotateCcwIcon), tone: 'orange' },
  { label: 'Active Workflows', value: stats.value?.active_workflows || 0, icon: markRaw(TimerResetIcon), tone: 'violet' },
  { label: 'Overdue Tasks', value: stats.value?.overdue_tasks || 0, icon: markRaw(AlertCircleIcon), tone: 'red' },
  { label: 'With Conditions', value: stats.value?.approved_with_conditions || 0, icon: markRaw(CheckCircleIcon), tone: 'cyan' },
]);

const pendingActions = computed(() => stats.value?.pending_actions || []);
const revisionRequests = computed(() => stats.value?.revision_requests || []);
const workflowSummary = computed(() => stats.value?.workflow_summary || []);

const stageBreakdown = computed(() => toBreakdown(stats.value?.projects_by_stage || [], 'current_stage'));
const statusBreakdown = computed(() => toBreakdown(stats.value?.projects_by_status || [], 'status'));
const chartTextColor = computed(() => isDarkMode.value ? '#cbd5e1' : '#475569');
const chartGridColor = computed(() => isDarkMode.value ? '#334155' : '#e2e8f0');
const chartPalette = ['#2563eb', '#14b8a6', '#f59e0b', '#8b5cf6', '#ef4444', '#22c55e', '#06b6d4', '#64748b'];
const riskSignals = computed(() => [
  { label: 'approval queue items', value: stats.value?.pending_approvals || 0, tone: 'amber' },
  { label: 'returned for revision', value: stats.value?.revision_requests_count || 0, tone: 'orange' },
  { label: 'overdue open tasks', value: stats.value?.overdue_tasks || 0, tone: 'red' },
  { label: 'conditional approvals', value: stats.value?.approved_with_conditions || 0, tone: 'cyan' },
]);

const toBreakdown = (items: any[], relation: string) => {
  const max = Math.max(...items.map((item) => Number(item.count)), 1);
  return items.map((item, index) => ({
    name: item[relation]?.name || 'Unassigned',
    count: Number(item.count),
    percent: Math.max(8, Math.round((Number(item.count) / max) * 100)),
    color: chartPalette[index % chartPalette.length],
  }));
};

const formatStatus = (status: string) =>
  status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

const workflowChartSeries = computed(() => workflowSummary.value.map((item) => Number(item.count)));
const workflowChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, foreColor: chartTextColor.value },
  labels: workflowSummary.value.map((item) => formatStatus(item.overall_status)),
  colors: chartPalette,
  stroke: { width: 0 },
  legend: { position: 'bottom', fontSize: '12px', labels: { colors: chartTextColor.value } },
  dataLabels: { enabled: false },
  plotOptions: {
    pie: {
      donut: {
        size: '70%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Workflows',
            color: chartTextColor.value,
          },
        },
      },
    },
  },
}));

const stageChartSeries = computed(() => [
  {
    name: 'Projects',
    data: stageBreakdown.value.map((item) => item.count),
  },
]);

const stageChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, foreColor: chartTextColor.value },
  colors: ['#2563eb'],
  grid: { borderColor: chartGridColor.value, strokeDashArray: 4 },
  plotOptions: {
    bar: { borderRadius: 6, columnWidth: '48%' },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: stageBreakdown.value.map((item) => item.name),
    labels: { rotate: -25, style: { colors: chartTextColor.value } },
  },
  yaxis: { labels: { style: { colors: chartTextColor.value } } },
  legend: { labels: { colors: chartTextColor.value } },
  tooltip: { theme: isDarkMode.value ? 'dark' : 'light' },
}));

const loadDashboard = async () => {
  loading.value = true;
  try {
    const response = await axiosInstance.get('/api/dashboard/stats');
    stats.value = response.data;
  } finally {
    loading.value = false;
  }
};

const openApproval = (projectId: number) => {
  router.push({ path: '/projects', query: { project_id: projectId, tab: 'approval' } });
};

onMounted(loadDashboard);
</script>

<style scoped>
.pms-dashboard { --d-card:#fff; --d-card-2:#f8fafc; --d-border:#e2e8f0; --d-text:#0f172a; --d-sub:#64748b; --d-muted:#f8fafc; --d-soft:#f1f5f9; display:flex; flex-direction:column; gap:1rem; }
.pms-dashboard.is-dark { --d-card:#172033; --d-card-2:#101827; --d-border:#2b3950; --d-text:#f8fafc; --d-sub:#94a3b8; --d-muted:#101827; --d-soft:#0f172a; }
.summary-grid { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:0.875rem; }
.metric-card,.panel { background:linear-gradient(180deg,var(--d-card),var(--d-card-2)); border:1px solid var(--d-border); border-radius:0.7rem; box-shadow:0 10px 28px rgba(15,23,42,0.04); }
.metric-card { display:flex; align-items:center; justify-content:space-between; gap:0.75rem; padding:1rem; min-height:5rem; }
.metric-copy { min-width:0; }
.metric-icon { width:2.25rem; height:2.25rem; display:flex; align-items:center; justify-content:center; border-radius:0.55rem; }
.metric-icon.blue{background:rgba(37,99,235,0.12);color:#60a5fa}.metric-icon.amber{background:rgba(245,158,11,0.14);color:#f59e0b}.metric-icon.orange{background:rgba(249,115,22,0.14);color:#fb923c}.metric-icon.violet{background:rgba(139,92,246,0.14);color:#a78bfa}.metric-icon.red{background:rgba(239,68,68,0.14);color:#f87171}.metric-icon.cyan{background:rgba(6,182,212,0.14);color:#22d3ee}
.metric-value { margin:0; color:var(--d-text); font-size:1.45rem; font-weight:800; line-height:1; }
.metric-label { margin:0.35rem 0 0; color:var(--d-sub); font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; }
.hero-grid { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(21rem,0.8fr); gap:1rem; align-items:stretch; }
.chart-grid { display:grid; grid-template-columns:minmax(0,1.45fr) minmax(21rem,0.75fr); gap:1rem; }
.dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.panel { padding:1rem; min-height:15rem; }
.chart-panel { overflow:hidden; }
.panel-head { display:flex; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
.panel-head h2 { margin:0; color:var(--d-text); font-size:1rem; font-weight:800; }
.panel-head p { margin:0.2rem 0 0; color:var(--d-sub); font-size:0.8rem; }
.ghost-btn { width:2rem; height:2rem; display:flex; align-items:center; justify-content:center; border:1px solid var(--d-border); border-radius:0.5rem; background:var(--d-muted); color:var(--d-sub); cursor:pointer; }
.action-row { width:100%; display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:0.9rem; border:1px solid var(--d-border); border-radius:0.55rem; background:rgba(15,23,42,0.18); text-align:left; cursor:pointer; margin-bottom:0.65rem; transition:0.15s ease; }
.action-row:hover { border-color:#3b82f6; background:rgba(59,130,246,0.08); transform:translateY(-1px); }
.action-row.revision:hover { border-color:#f97316; background:rgba(249,115,22,0.08); }
.action-main { display:flex; flex-direction:column; gap:0.2rem; min-width:0; }
.action-main strong { color:var(--d-text); font-size:0.9rem; }
.action-main span:not(.project-code),.action-meta { color:var(--d-sub); font-size:0.78rem; }
.project-code { color:#3b82f6; font-size:0.72rem; font-weight:800; letter-spacing:0.05em; }
.action-meta { display:flex; align-items:center; gap:0.4rem; white-space:nowrap; }
.revision-block { margin-top:0.95rem; padding-top:0.95rem; border-top:1px solid var(--d-border); }
.queue-title { display:flex; align-items:center; gap:0.45rem; color:var(--d-sub); font-size:0.76rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.65rem; }
.workflow-row,.bar-label { display:flex; align-items:center; justify-content:space-between; gap:1rem; color:var(--d-text); font-size:0.85rem; }
.workflow-row { padding:0.7rem 0; border-bottom:1px solid var(--d-border); }
.donut-wrap { min-height:17rem; }
.donut-wrap.compact { display:flex; align-items:center; justify-content:center; min-height:16rem; }
.risk-panel { min-height:0; }
.risk-list { display:grid; gap:0.75rem; }
.risk-item { display:flex; align-items:center; gap:0.75rem; padding:0.85rem; border:1px solid var(--d-border); border-radius:0.55rem; background:rgba(15,23,42,0.16); }
.risk-item strong { display:block; color:var(--d-text); font-size:1.15rem; line-height:1; }
.risk-item span { display:block; margin-top:0.25rem; color:var(--d-sub); font-size:0.78rem; }
.risk-dot { width:0.65rem; height:2.25rem; border-radius:999px; background:#64748b; }
.risk-dot.amber{background:#f59e0b}.risk-dot.orange{background:#f97316}.risk-dot.red{background:#ef4444}.risk-dot.cyan{background:#06b6d4}.risk-dot.violet{background:#8b5cf6}
.bar-list { display:flex; flex-direction:column; gap:0.85rem; }
.bar-track { height:0.45rem; background:var(--d-muted); border-radius:999px; overflow:hidden; }
.bar-fill { height:100%; background:#3b82f6; border-radius:999px; }
.bar-fill.alt { background:#14b8a6; }
.state-line,.empty-state { display:flex; align-items:center; justify-content:center; gap:0.6rem; color:var(--d-sub); min-height:9rem; }
.empty-state { flex-direction:column; text-align:center; }
.empty-state.slim { min-height:6.5rem; }
.empty-state.small { min-height:13rem; }
.empty-icon { width:2rem; height:2rem; color:#22c55e; }
.spinner { width:1rem; height:1rem; border:2px solid var(--d-border); border-top-color:#3b82f6; border-radius:50%; animation:spin 0.8s linear infinite; }
.icon { width:1rem; height:1rem; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width:1200px){.summary-grid{grid-template-columns:repeat(3,1fr)}.hero-grid,.chart-grid,.dash-grid{grid-template-columns:1fr}}
@media (max-width:640px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
</style>
