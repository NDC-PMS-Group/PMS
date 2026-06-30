<template>
  <div class="dashboard" :class="{ dark: isDarkMode }">
    <section class="dashboard-head">
      <div>
        <p class="eyebrow">NDC portfolio workspace</p>
        <h1>Project Operations</h1>
        <p>SOI decisions, compliance work, and portfolio movement in one view.</p>
      </div>
      <button class="refresh" :disabled="loading" @click="loadDashboard">
        <RefreshCwIcon :class="{ spin: loading }" />
        Refresh
      </button>
    </section>

    <section class="metrics">
      <article v-for="card in cards" :key="card.label" class="metric">
        <div class="metric-icon" :class="card.tone"><component :is="card.icon" /></div>
        <div><strong>{{ card.value }}</strong><span>{{ card.label }}</span></div>
      </article>
    </section>

    <section class="primary-grid">
      <article class="panel action-panel">
        <header class="panel-head">
          <div>
            <p class="section-kicker">My queue</p>
            <h2>Actions requiring attention</h2>
          </div>
          <span class="queue-count">{{ pendingActions.length + revisionRequests.length }}</span>
        </header>

        <div v-if="loading" class="empty">Loading action queue...</div>
        <div v-else-if="!pendingActions.length && !revisionRequests.length" class="empty">
          <CheckCircleIcon />
          <strong>You are up to date</strong>
          <span>No SOI decision or returned proposal is waiting for you.</span>
        </div>
        <div v-else class="action-list">
          <button v-for="action in pendingActions" :key="`a-${action.approval_id}`" class="action" @click="openApproval(action.project_id)">
            <span class="action-state approval">Decision</span>
            <div class="action-copy">
              <small>{{ action.project_code }} · {{ action.current_step }}</small>
              <strong>{{ action.title }}</strong>
              <span>{{ action.role }} · {{ action.status }}</span>
            </div>
            <ArrowRightIcon />
          </button>
          <button v-for="action in revisionRequests" :key="`r-${action.approval_id}`" class="action" @click="openApproval(action.project_id)">
            <span class="action-state revision">Revision</span>
            <div class="action-copy">
              <small>{{ action.project_code }}</small>
              <strong>{{ action.title }}</strong>
              <span>Returned proposal requires an update</span>
            </div>
            <ArrowRightIcon />
          </button>
        </div>
      </article>

      <article class="panel lifecycle-panel">
        <header class="panel-head">
          <div>
            <p class="section-kicker">SOI lifecycle</p>
            <h2>Portfolio pipeline</h2>
          </div>
        </header>
        <div class="pipeline">
          <div v-for="(item, index) in lifecycle" :key="item.label" class="pipeline-row">
            <span class="step-number">{{ index + 1 }}</span>
            <div class="pipeline-copy">
              <div><span>{{ item.label }}</span><strong>{{ item.count }}</strong></div>
              <div class="track"><div class="fill" :style="{ width: `${pipelineWidth(item.count)}%` }"></div></div>
            </div>
          </div>
        </div>
      </article>
    </section>

    <section class="attention-strip">
      <article v-for="item in attentionCards" :key="item.label" class="attention" :class="item.tone">
        <component :is="item.icon" />
        <div><strong>{{ item.value }}</strong><span>{{ item.label }}</span></div>
      </article>
    </section>

    <section class="analytics-grid-three">
      <article class="panel chart-panel">
        <header class="panel-head">
          <div>
            <p class="section-kicker">Distribution</p>
            <h2>Projects by Stage</h2>
          </div>
        </header>
        <apexchart height="280" type="bar" :series="stageSeries" :options="stageOptions" />
      </article>

      <article class="panel chart-panel">
        <header class="panel-head">
          <div>
            <p class="section-kicker">Sectors</p>
            <h2>Sector Breakdown</h2>
          </div>
        </header>
        <div v-if="!sectorBreakdown.length" class="empty compact">No sector data yet.</div>
        <apexchart v-else height="280" type="donut" :series="sectorSeries" :options="sectorOptions" />
      </article>

      <article class="panel chart-panel">
        <header class="panel-head">
          <div>
            <p class="section-kicker">Capital</p>
            <h2>Investment by Stage</h2>
          </div>
        </header>
        <apexchart height="280" type="bar" :series="investmentSeries" :options="investmentOptions" />
      </article>
    </section>

    <section v-if="monitoring.total_jobs || monitoring.projects_with_indicators" class="panel monitoring-panel">
      <header class="panel-head">
        <div>
          <p class="section-kicker">Active implementation only</p>
          <h2>Monitoring snapshot</h2>
          <span>Figures appear only after NDC opens a monitoring period.</span>
        </div>
      </header>
      <div class="monitoring-grid">
        <div v-for="item in monitoringCards" :key="item.label">
          <strong>{{ item.value }}</strong><span>{{ item.label }}</span>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, markRaw, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  AlertCircle as AlertCircleIcon,
  ArrowRight as ArrowRightIcon,
  CheckCircle as CheckCircleIcon,
  ClipboardCheck as ClipboardCheckIcon,
  Clock3 as ClockIcon,
  FileWarning as FileWarningIcon,
  FolderKanban as FolderIcon,
  RefreshCw as RefreshCwIcon,
  RotateCcw as RotateCcwIcon,
} from 'lucide-vue-next';
import axiosInstance from '@/utils/axiosInstance';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';

interface PendingAction {
  approval_id: number;
  project_id: number;
  project_code: string;
  title: string;
  current_step: string;
  role: string;
  status: string;
}

interface MonitoringSummary {
  total_jobs: number;
  projected_revenue: number;
  actual_revenue: number;
  dividend_remittance: number;
  reportable_projects: number;
  projects_with_indicators: number;
}

interface DashboardStats {
  total_projects: number;
  pending_approvals: number;
  revision_requests_count: number;
  overdue_tasks: number;
  active_workflows: number;
  pending_actions: PendingAction[];
  revision_requests: PendingAction[];
  lifecycle_pipeline?: { label: string; count: number }[];
  attention_summary?: Record<string, number>;
  projects_by_stage: { count: number; current_stage?: { name: string } }[];
  projects_by_status: { count: number; status?: { name: string } }[];
  monitoring_summary?: MonitoringSummary;
}

const router = useRouter();
const layoutStore = useLayoutStore();
const isDarkMode = computed(() =>
  layoutStore.mode === SITE_MODE.DARK ||
  (typeof document !== 'undefined' && document.documentElement.classList.contains('dark'))
);
const loading = ref(false);
const stats = ref<DashboardStats | null>(null);

const cards = computed(() => [
  { label: 'Visible projects', value: stats.value?.total_projects || 0, icon: markRaw(FolderIcon), tone: 'blue' },
  { label: 'My approval queue', value: stats.value?.pending_approvals || 0, icon: markRaw(ClipboardCheckIcon), tone: 'amber' },
  { label: 'Returned for revision', value: stats.value?.revision_requests_count || 0, icon: markRaw(RotateCcwIcon), tone: 'orange' },
  { label: 'Active SOI workflows', value: stats.value?.active_workflows || 0, icon: markRaw(CheckCircleIcon), tone: 'blue' },
]);
const pendingActions = computed(() => stats.value?.pending_actions || []);
const revisionRequests = computed(() => stats.value?.revision_requests || []);
const lifecycle = computed(() => stats.value?.lifecycle_pipeline || []);
const monitoring = computed<MonitoringSummary>(() => stats.value?.monitoring_summary || {
  total_jobs: 0, projected_revenue: 0, actual_revenue: 0, dividend_remittance: 0, reportable_projects: 0, projects_with_indicators: 0,
});
const attention = computed(() => stats.value?.attention_summary || {});
const attentionCards = computed(() => [
  { label: 'Approval decisions', value: attention.value.approval_actions || 0, icon: markRaw(ClipboardCheckIcon), tone: 'amber' },
  { label: 'Returned proposals', value: attention.value.revision_requests || 0, icon: markRaw(RotateCcwIcon), tone: 'orange' },
  { label: 'Overdue requirements', value: attention.value.overdue_requirements || 0, icon: markRaw(FileWarningIcon), tone: 'red' },
  { label: 'Monitoring due in 14 days', value: attention.value.monitoring_due || 0, icon: markRaw(ClockIcon), tone: 'blue' },
  { label: 'Overdue work-plan tasks', value: attention.value.overdue_tasks || 0, icon: markRaw(AlertCircleIcon), tone: 'red' },
]);
const stageBreakdown = computed(() => breakdown(stats.value?.projects_by_stage || [], 'current_stage'));
const statusBreakdown = computed(() => breakdown(stats.value?.projects_by_status || [], 'status'));
const sectorBreakdown = computed(() => breakdown(stats.value?.projects_by_sector || [], 'sector'));

const maxPipeline = computed(() => Math.max(...lifecycle.value.map((item) => item.count), 1));
const pipelineWidth = (count: number) => count ? Math.max(8, Math.round((count / maxPipeline.value) * 100)) : 0;
const chartText = computed(() => isDarkMode.value ? '#cbd5e1' : '#475569');
const chartGrid = computed(() => isDarkMode.value ? '#334155' : '#e2e8f0');

const stageSeries = computed(() => [{ name: 'Projects', data: stageBreakdown.value.map((item) => item.count) }]);
const stageOptions = computed(() => ({
  chart: { toolbar: { show: false }, foreColor: chartText.value },
  colors: ['#2563eb'],
  grid: { borderColor: chartGrid.value, strokeDashArray: 4 },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '52%' } },
  dataLabels: { enabled: false },
  xaxis: { categories: stageBreakdown.value.map((item) => item.name), labels: { rotate: -25 } },
  tooltip: { theme: isDarkMode.value ? 'dark' : 'light' },
}));

const sectorSeries = computed(() => sectorBreakdown.value.map((item) => item.count));
const sectorOptions = computed(() => ({
  chart: { foreColor: chartText.value },
  labels: sectorBreakdown.value.map((item) => item.name),
  legend: { position: 'bottom' },
  colors: ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'],
  tooltip: { theme: isDarkMode.value ? 'dark' : 'light' },
  dataLabels: { enabled: false },
}));

const investmentSeries = computed(() => [{ name: 'Investment', data: stageBreakdown.value.map((item) => item.total_investment) }]);
const investmentOptions = computed(() => ({
  chart: { toolbar: { show: false }, foreColor: chartText.value },
  colors: ['#10b981'],
  grid: { borderColor: chartGrid.value, strokeDashArray: 4 },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '52%' } },
  dataLabels: { enabled: false },
  xaxis: { categories: stageBreakdown.value.map((item) => item.name), labels: { rotate: -25 } },
  yaxis: {
    labels: {
      formatter: (val: number) => `₱${compact(val)}`
    }
  },
  tooltip: {
    theme: isDarkMode.value ? 'dark' : 'light',
    y: {
      formatter: (val: number) => `₱${new Intl.NumberFormat('en-PH').format(val)}`
    }
  }
}));

const monitoringCards = computed(() => [
  { label: 'Jobs generated / retained', value: compact(monitoring.value.total_jobs) },
  { label: 'Projected revenue', value: money(monitoring.value.projected_revenue) },
  { label: 'Actual revenue', value: money(monitoring.value.actual_revenue) },
  { label: 'Dividend / remittance', value: money(monitoring.value.dividend_remittance) },
  { label: 'GCG reportable projects', value: monitoring.value.reportable_projects },
]);

function breakdown(items: any[], relation: string) {
  const max = Math.max(...items.map((item) => Number(item.count)), 1);
  return items.map((item) => ({
    name: item[relation]?.name || 'Unassigned',
    count: Number(item.count),
    percent: Math.max(6, Math.round((Number(item.count) / max) * 100)),
    total_investment: Number(item.total_investment || 0)
  }));
}
const compact = (value: number) => new Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 }).format(value || 0);
const money = (value: number) => `₱${compact(value || 0)}`;
const openApproval = (projectId: number) => router.push({ path: '/projects', query: { project_id: projectId, tab: 'approval' } });
const loadDashboard = async () => {
  loading.value = true;
  try {
    stats.value = (await axiosInstance.get('/api/dashboard/stats')).data;
  } finally {
    loading.value = false;
  }
};
onMounted(loadDashboard);
</script>

<style scoped>
.dashboard{--card:#fff;--soft:#f7f9fc;--border:#dce5ef;--text:#0f172a;--sub:#64748b;display:flex;flex-direction:column;gap:1rem;color:var(--text);padding:1rem;border:1px solid var(--border);border-radius:1rem;background:linear-gradient(180deg,rgba(248,250,252,.96),rgba(241,245,249,.98));box-shadow:0 18px 42px rgba(15,23,42,.06)}
.dashboard.dark,
:global(.dark) .dashboard{--card:#121c2d;--soft:#0b1220;--border:#26344a;--text:#f8fafc;--sub:#94a3b8;background-color:#0b1220!important;background-image:linear-gradient(180deg,#0b1220 0%,#0f172a 100%)!important;border:1px solid #26344a!important;outline:0!important;box-shadow:0 26px 64px rgba(2,6,23,.46)!important}
.dashboard-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}.dashboard-head h1{margin:.1rem 0;font-size:1.75rem}.dashboard-head>div>p:last-child{margin:0;color:var(--sub)}.eyebrow,.section-kicker{margin:0;color:#2563eb;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.refresh{display:flex;align-items:center;gap:.45rem;border:1px solid var(--border);background:var(--card);color:var(--text);padding:.65rem .8rem;border-radius:.5rem}.refresh svg{width:1rem}.spin{animation:spin .8s linear infinite}
.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric,.panel,.attention{border:1px solid var(--border);background:var(--card);border-radius:.65rem}.metric{display:flex;align-items:center;gap:.75rem;padding:1rem}.metric-icon{display:grid;place-items:center;width:2.4rem;height:2.4rem;border-radius:.55rem}.metric-icon svg{width:1.15rem}.metric-icon.blue{background:#dbeafe;color:#2563eb}.metric-icon.amber{background:#fef3c7;color:#d97706}.metric-icon.orange{background:#ffedd5;color:#ea580c}.metric-icon.green{background:#dbeafe;color:#2563eb}.metric strong{display:block;font-size:1.35rem;color:var(--text)}.metric span{display:block;color:var(--sub);font-size:.72rem;font-weight:700}
.primary-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(18rem,.8fr);gap:1rem;align-items:start}.panel{padding:1rem}.panel-head{display:flex;justify-content:space-between;gap:1rem;margin-bottom:.9rem;align-items:flex-start}.panel-head h2{margin:.15rem 0 0;font-size:1.05rem}.panel-head span{color:var(--sub);font-size:.78rem}.queue-count{display:grid;place-items:center;min-width:2rem;height:2rem;border-radius:50%;background:#dbeafe;color:#2563eb!important;font-weight:800}
.action-list{display:flex;flex-direction:column;gap:.55rem}.action{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.75rem;text-align:left;border:1px solid var(--border);background:var(--soft);color:var(--text);padding:.8rem;border-radius:.5rem;cursor:pointer}.action:hover{border-color:#60a5fa}.action>svg{width:1rem;color:var(--sub)}.action-state{padding:.25rem .42rem;border-radius:.35rem;font-size:.64rem;font-weight:800;text-transform:uppercase}.action-state.approval{background:#fef3c7;color:#92400e}.action-state.revision{background:#ffedd5;color:#9a3412}.action-copy{min-width:0}.action-copy small,.action-copy span{display:block;color:var(--sub);font-size:.72rem}.action-copy strong{display:block;margin:.16rem 0;font-size:.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.empty{min-height:10rem;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:var(--sub);gap:.3rem}.empty svg{width:1.8rem;color:#16a34a}.empty.compact{min-height:6rem}.pipeline{display:flex;flex-direction:column;gap:.7rem}.pipeline-row{display:flex;align-items:center;gap:.65rem}.step-number{display:grid;place-items:center;width:1.65rem;height:1.65rem;border-radius:50%;background:var(--soft);border:1px solid var(--border);font-size:.7rem;font-weight:800}.pipeline-copy{flex:1}.pipeline-copy>div:first-child,.status-row>div:first-child{display:flex;justify-content:space-between;gap:.5rem;font-size:.78rem}.track{height:.38rem;margin-top:.3rem;background:var(--soft);border-radius:99px;overflow:hidden}.fill{height:100%;background:#2563eb;border-radius:99px}.fill.teal{background:#14b8a6}
.dashboard.dark .pipeline-copy > div:first-child span,
.dashboard.dark .pipeline-copy > div:first-child strong,
.dashboard.dark .status-row > div:first-child span,
.dashboard.dark .status-row > div:first-child strong,
.dashboard.dark .metric strong,
.dashboard.dark .step-number,
.dashboard.dark .monitoring-grid strong {
  color: var(--text);
}
.dashboard.dark .pipeline-copy > div:first-child span,
.dashboard.dark .status-row > div:first-child span,
.dashboard.dark .monitoring-grid span {
  color: var(--sub);
}
.dashboard.dark .metric-icon.green {
  background: #dbeafe;
  color: #2563eb;
}
.dashboard.dark .dashboard-head,
.dashboard.dark .metrics,
.dashboard.dark .primary-grid,
.dashboard.dark .attention-strip,
.dashboard.dark .analytics-grid-three,
.dashboard.dark .monitoring-panel {
  background: transparent !important;
}
.dashboard.dark .metric,
.dashboard.dark .panel,
.dashboard.dark .attention {
  background: var(--card) !important;
  border-color: var(--border);
}
.dashboard.dark .action,
.dashboard.dark .track,
.dashboard.dark .step-number,
.dashboard.dark .monitoring-grid > div {
  background: var(--soft) !important;
}
.attention-strip{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem}.attention{display:flex;gap:.6rem;align-items:center;padding:.8rem;min-height:4rem}.attention svg{width:1.05rem;flex:none}.attention strong{display:block}.attention span{display:block;color:var(--sub);font-size:.67rem}.attention.amber svg{color:#d97706}.attention.orange svg{color:#ea580c}.attention.red svg{color:#dc2626}.attention.blue svg{color:#2563eb}
.analytics-grid-three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;align-items:start}.chart-panel{overflow:hidden}.status-list{display:flex;flex-direction:column;gap:.8rem}.status-row{font-size:.8rem}.monitoring-panel{padding:1rem}.monitoring-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem}.monitoring-grid>div{padding:.8rem;border:1px solid var(--border);background:var(--soft);border-radius:.5rem}.monitoring-grid strong{display:block}.monitoring-grid span{display:block;color:var(--sub);font-size:.7rem;margin-top:.2rem}
@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:1100px){.metrics{grid-template-columns:repeat(2,1fr)}.primary-grid,.analytics-grid-three{grid-template-columns:1fr}.attention-strip{grid-template-columns:repeat(3,1fr)}.monitoring-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:640px){.dashboard-head{flex-direction:column}.metrics,.attention-strip,.monitoring-grid{grid-template-columns:1fr}}
</style>
