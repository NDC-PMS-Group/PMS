<template>
  <main class="dashboard" :class="{ 'is-dark': isDarkMode }" aria-labelledby="dashboard-title">
    <header class="dashboard-head">
      <div>
        <p class="eyebrow">{{ roleModeLabel }}</p>
        <h1 id="dashboard-title">{{ dashboardTitle }}</h1>
        <p>Decisions, delivery risks, workload, and compliance across the active project scope.</p>
      </div>
      <button class="icon-command" type="button" :disabled="store.loading" title="Refresh dashboard" aria-label="Refresh dashboard" @click="store.fetchDashboard">
        <RefreshCw :class="{ spin: store.loading }" aria-hidden="true" />
      </button>
    </header>

    <form class="filter-bar" aria-label="Dashboard filters" @submit.prevent="store.fetchDashboard">
      <div v-if="filterOptions.scopes.length > 1" class="field scope-field">
        <label for="dashboard-scope">Scope</label>
        <select id="dashboard-scope" v-model="store.filters.scope">
          <option v-for="option in filterOptions.scopes" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <div class="field">
        <label for="dashboard-year">Year</label>
        <select id="dashboard-year" v-model="store.filters.year">
          <option :value="null">All years</option>
          <option v-for="year in filterOptions.available_years" :key="year" :value="year">{{ year }}</option>
        </select>
      </div>
      <div class="field">
        <label for="dashboard-sector">Sector</label>
        <select id="dashboard-sector" v-model="store.filters.sector_id">
          <option :value="null">All sectors</option>
          <option v-for="sector in filterOptions.sectors" :key="sector.id" :value="sector.id">{{ sector.name }}</option>
        </select>
      </div>
      <div class="field">
        <label for="dashboard-stage">Stage</label>
        <select id="dashboard-stage" v-model="store.filters.stage_id">
          <option :value="null">All stages</option>
          <option v-for="stage in filterOptions.stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
        </select>
      </div>
      <div class="field">
        <label for="dashboard-origin">Origin route</label>
        <select id="dashboard-origin" v-model="store.filters.origin_track">
          <option :value="null">All routes</option>
          <option v-for="option in filterOptions.origin_tracks" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <div class="field">
        <label for="dashboard-lifecycle">Lifecycle</label>
        <select id="dashboard-lifecycle" v-model="store.filters.lifecycle_phase">
          <option :value="null">All phases</option>
          <option v-for="option in filterOptions.lifecycle_phases" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <div v-if="filterOptions.role.can_view_portfolio" class="field">
        <label for="dashboard-officer">Project officer</label>
        <select id="dashboard-officer" v-model="store.filters.officer_id">
          <option :value="null">All officers</option>
          <option v-for="officer in filterOptions.officers" :key="officer.id" :value="officer.id">{{ officer.name }}</option>
        </select>
      </div>
      <div class="field">
        <label for="dashboard-due">Due window</label>
        <select id="dashboard-due" v-model="store.filters.due_window">
          <option v-for="option in filterOptions.due_windows" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <div class="filter-actions">
        <button class="apply-button" type="submit" :disabled="store.loading">Apply</button>
        <button v-if="store.hasActiveFilters" class="clear-button" type="button" :disabled="store.loading" @click="store.resetFilters">Reset</button>
      </div>
    </form>

    <div v-if="store.error" class="error-banner" role="alert">
      <AlertTriangle aria-hidden="true" />
      <span>{{ store.error }}</span>
      <button type="button" @click="store.fetchDashboard">Try again</button>
    </div>

    <section class="metric-grid" aria-label="Decision support summary">
      <article v-for="metric in summaryMetrics" :key="metric.label" class="metric">
        <span class="metric-icon" :class="metric.tone"><component :is="metric.icon" aria-hidden="true" /></span>
        <span><strong>{{ metric.value }}</strong><small>{{ metric.label }}</small></span>
      </article>
    </section>

    <section class="priority-grid">
      <DecisionQueue :items="stats?.decision_queue ?? []" :loading="store.loading && !stats" @open="openRoute" />
      <RiskProjects :items="stats?.risk_projects ?? []" :loading="store.loading && !stats" @open="openRoute" />
    </section>

    <section class="operations-grid">
      <article class="panel compliance-panel">
        <header class="panel-head">
          <div><p class="kicker">Monitoring</p><h2>Compliance status</h2></div>
          <button class="text-link" type="button" @click="router.push('/admin/post-monitoring')">Open monitoring <ArrowRight aria-hidden="true" /></button>
        </header>
        <div class="compliance-summary">
          <div class="rate-block">
            <strong>{{ formatPercent(compliance.compliance_rate) }}</strong>
            <span>Submitted or accepted in due window</span>
            <div class="progress" role="progressbar" aria-label="Monitoring compliance" :aria-valuenow="compliance.compliance_rate" aria-valuemin="0" aria-valuemax="100">
              <span :style="{ width: `${Math.min(100, compliance.compliance_rate)}%` }"></span>
            </div>
          </div>
          <dl class="compact-stats">
            <div><dt>Active</dt><dd>{{ compliance.active }}</dd></div>
            <div><dt>Due</dt><dd>{{ compliance.due_in_window }}</dd></div>
            <div class="danger"><dt>Overdue</dt><dd>{{ compliance.overdue }}</dd></div>
            <div><dt>No due date</dt><dd>{{ compliance.missing_due_date }}</dd></div>
          </dl>
        </div>
        <ul v-if="compliance.projects.length" class="compact-list" role="list">
          <li v-for="project in compliance.projects.slice(0, 5)" :key="project.project_id">
            <button type="button" @click="openProject(project.project_id, 'monitoring')">
              <span><strong>{{ project.title }}</strong><small>{{ project.project_code }} · {{ statusLabel(project.submission_status) }}</small></span>
              <time :datetime="project.due_date || undefined" :class="{ overdue: project.is_overdue }">{{ formatDate(project.due_date) }}</time>
            </button>
          </li>
        </ul>
        <p v-else class="inline-empty">No monitoring reports match the due window.</p>
      </article>

      <article class="panel workload-panel">
        <header class="panel-head">
          <div><p class="kicker">{{ stats?.workload.mode === 'team' ? 'Team capacity' : 'My capacity' }}</p><h2>Workload</h2></div>
          <button class="text-link" type="button" @click="router.push('/tasks')">Open tasks <ArrowRight aria-hidden="true" /></button>
        </header>
        <dl class="compact-stats workload-totals">
          <div><dt>Projects</dt><dd>{{ workloadTotals.active_projects }}</dd></div>
          <div><dt>Open tasks</dt><dd>{{ workloadTotals.open_tasks }}</dd></div>
          <div class="danger"><dt>Overdue</dt><dd>{{ workloadTotals.overdue_tasks }}</dd></div>
          <div><dt>Unassigned</dt><dd>{{ workloadTotals.unassigned_projects }}</dd></div>
        </dl>
        <ul v-if="stats?.workload.officers.length" class="workload-list" role="list">
          <li v-for="officer in stats.workload.officers.slice(0, 6)" :key="officer.user_id">
            <span class="initials" aria-hidden="true">{{ initials(officer.name) }}</span>
            <span class="officer-copy"><strong>{{ officer.name }}</strong><small>{{ officer.active_projects }} projects · {{ officer.open_tasks }} open tasks</small></span>
            <span class="load-badge" :class="officer.load_level">{{ officer.overdue_tasks }} overdue</span>
          </li>
        </ul>
        <p v-else class="inline-empty">No assigned officer workload in this scope.</p>
      </article>
    </section>

    <section class="context-grid">
      <article class="panel quality-panel">
        <header class="panel-head">
          <div><p class="kicker">Data confidence</p><h2>Portfolio data quality</h2></div>
          <span class="quality-rate">{{ formatPercent(dataQuality.completeness_rate) }}</span>
        </header>
        <p class="panel-note">{{ dataQuality.projects_with_issues }} of {{ dataQuality.total_projects }} projects need core fields completed.</p>
        <ul v-if="dataQuality.records.length" class="quality-list" role="list">
          <li v-for="record in dataQuality.records.slice(0, 6)" :key="record.project_id">
            <button type="button" @click="openProject(record.project_id)">
              <span><strong>{{ record.title }}</strong><small>{{ record.project_code }}</small></span>
              <span class="missing-fields">{{ fieldList(record.missing_fields) }}</span>
            </button>
          </li>
        </ul>
        <p v-else class="inline-empty success">All visible projects have the core decision fields.</p>
      </article>

      <article class="panel pipeline-panel">
        <header class="panel-head"><div><p class="kicker">Portfolio flow</p><h2>Lifecycle pipeline</h2></div></header>
        <ol class="pipeline-list">
          <li v-for="(item, index) in stats?.lifecycle_pipeline ?? []" :key="item.label">
            <span class="step">{{ index + 1 }}</span>
            <span class="pipeline-copy"><span><strong>{{ item.label }}</strong><b>{{ item.count }}</b></span><span class="track"><span :style="{ width: `${pipelineWidth(item.count)}%` }"></span></span></span>
          </li>
        </ol>
      </article>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, markRaw, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { AlertTriangle, ArrowRight, ClipboardCheck, RefreshCw, ShieldAlert, Siren, TableProperties } from 'lucide-vue-next';
import DecisionQueue from './DecisionQueue.vue';
import RiskProjects from './RiskProjects.vue';
import { useDashboardStore } from '@/store/dashboard';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import type { DashboardFilterPayload, DashboardRoute, DataQuality, MonitoringCompliance } from '@/types/dashboard';

const router = useRouter();
const store = useDashboardStore();
const layoutStore = useLayoutStore();
const stats = computed(() => store.stats);
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);

const emptyFilters: DashboardFilterPayload = {
  applied: store.filters,
  available_years: [], due_windows: [
    { value: 'overdue', label: 'Overdue only' }, { value: '7', label: 'Next 7 days' },
    { value: '14', label: 'Next 14 days' }, { value: '30', label: 'Next 30 days' }, { value: 'all', label: 'All dates' },
  ],
  scopes: [{ value: 'mine', label: 'My assignments' }], sectors: [], stages: [], origin_tracks: [], lifecycle_phases: [], officers: [],
  role: { name: 'User', mode: 'officer', can_view_portfolio: false },
};
const filterOptions = computed(() => stats.value?.filters ?? emptyFilters);
const dashboardTitle = computed(() => store.isPortfolioMode ? 'Portfolio Decision Support' : 'My Decision Support');
const roleModeLabel = computed(() => `${filterOptions.value.role.name} · ${store.isPortfolioMode ? 'Portfolio view' : 'Assignment view'}`);
const criticalRisks = computed(() => stats.value?.risk_projects.filter((project) => project.risk_level === 'critical').length ?? 0);
const summaryMetrics = computed(() => [
  { label: 'Decisions waiting', value: stats.value?.decision_queue.length ?? 0, icon: markRaw(ClipboardCheck), tone: 'amber' },
  { label: 'Critical-risk projects', value: criticalRisks.value, icon: markRaw(ShieldAlert), tone: 'red' },
  { label: 'Monitoring overdue', value: stats.value?.monitoring_compliance.overdue ?? 0, icon: markRaw(Siren), tone: 'orange' },
  { label: 'Projects with data gaps', value: stats.value?.data_quality.projects_with_issues ?? 0, icon: markRaw(TableProperties), tone: 'blue' },
]);
const emptyCompliance: MonitoringCompliance = { active: 0, due_in_window: 0, overdue: 0, submitted: 0, accepted: 0, missing_due_date: 0, compliance_rate: 100, projects: [] };
const compliance = computed(() => stats.value?.monitoring_compliance ?? emptyCompliance);
const workloadTotals = computed(() => stats.value?.workload.totals ?? { officers: 0, active_projects: 0, open_tasks: 0, overdue_tasks: 0, unassigned_projects: 0 });
const emptyQuality: DataQuality = { total_projects: 0, complete_projects: 0, projects_with_issues: 0, completeness_rate: 100, records: [] };
const dataQuality = computed(() => stats.value?.data_quality ?? emptyQuality);
const maxPipeline = computed(() => Math.max(1, ...(stats.value?.lifecycle_pipeline.map((item) => item.count) ?? [1])));

const openRoute = (route: DashboardRoute) => router.push({ path: route.path, query: route.query });
const openProject = (projectId: number, tab = 'overview') => router.push({ path: '/projects', query: { project_id: projectId, tab } });
const pipelineWidth = (count: number) => count ? Math.max(5, Math.round((count / maxPipeline.value) * 100)) : 0;
const formatPercent = (value: number) => `${Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 1 })}%`;
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(`${value}T00:00:00+08:00`)) : 'No due date';
const statusLabel = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const fieldList = (fields: string[]) => fields.slice(0, 3).map(statusLabel).join(', ') + (fields.length > 3 ? ` +${fields.length - 3}` : '');
const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

onMounted(() => store.fetchDashboard());
</script>

<style scoped>
.dashboard{--dash-bg:#f5f7fa;--dash-card:#fff;--dash-soft:#f8fafc;--dash-border:#d8e0ea;--dash-text:#172033;--dash-muted:#607086;--dash-accent:#1d4ed8;--dash-accent-soft:#dbeafe;--dash-focus:#60a5fa;--dash-success:#15803d;--dash-warning:#a16207;--dash-warning-soft:#fef3c7;--dash-danger:#b91c1c;--dash-danger-soft:#fee2e2;--dash-info:#0369a1;--dash-info-soft:#e0f2fe;--dash-neutral-soft:#e8edf3;display:flex;min-width:0;flex-direction:column;gap:1rem;padding:1.25rem;background:var(--dash-bg);color:var(--dash-text);border:1px solid var(--dash-border);border-radius:.5rem}.dashboard.is-dark{--dash-bg:#0b1220;--dash-card:#121c2d;--dash-soft:#0e1828;--dash-border:#314159;--dash-text:#f3f6fa;--dash-muted:#a6b4c7;--dash-accent:#7db2ff;--dash-accent-soft:#172f55;--dash-focus:#93c5fd;--dash-success:#6ee7a0;--dash-warning:#facc6b;--dash-warning-soft:#3c3017;--dash-danger:#fda4a4;--dash-danger-soft:#441f25;--dash-info:#7dd3fc;--dash-info-soft:#123448;--dash-neutral-soft:#253247}.dashboard-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.dashboard-head h1{margin:.1rem 0;font-size:1.55rem;letter-spacing:0}.dashboard-head p:last-child{margin:0;color:var(--dash-muted);font-size:.86rem}.eyebrow,.kicker{margin:0;color:var(--dash-accent);font-size:.7rem;font-weight:800;text-transform:uppercase}.icon-command{display:grid;place-items:center;flex:none;width:2.5rem;height:2.5rem;border:1px solid var(--dash-border);border-radius:.4rem;background:var(--dash-card);color:var(--dash-text);cursor:pointer}.icon-command svg{width:1.05rem}.icon-command:focus-visible,.apply-button:focus-visible,.clear-button:focus-visible,.text-link:focus-visible,.compact-list button:focus-visible,.quality-list button:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}.filter-bar{display:grid;grid-template-columns:repeat(5,minmax(7rem,1fr)) auto;align-items:end;gap:.65rem;padding:.8rem;border:1px solid var(--dash-border);border-radius:.5rem;background:var(--dash-card)}.field{min-width:0}.field label{display:block;margin-bottom:.25rem;color:var(--dash-muted);font-size:.68rem;font-weight:700}.field select{width:100%;min-width:0;height:2.25rem;border:1px solid var(--dash-border);border-radius:.35rem;background:var(--dash-soft);color:var(--dash-text);padding:0 .55rem;font:inherit;font-size:.78rem}.filter-actions{display:flex;gap:.4rem}.apply-button,.clear-button{height:2.25rem;border-radius:.35rem;padding:0 .8rem;font-size:.75rem;font-weight:800;cursor:pointer}.apply-button{border:1px solid var(--dash-accent);background:var(--dash-accent);color:#fff}.is-dark .apply-button{color:#08101e}.clear-button{border:1px solid var(--dash-border);background:var(--dash-soft);color:var(--dash-text)}button:disabled{cursor:not-allowed;opacity:.55}.error-banner{display:flex;align-items:center;gap:.6rem;border:1px solid var(--dash-danger);border-radius:.4rem;background:var(--dash-danger-soft);color:var(--dash-danger);padding:.7rem .8rem}.error-banner svg{width:1rem;flex:none}.error-banner span{flex:1;font-size:.8rem}.error-banner button{border:0;background:transparent;color:inherit;font-weight:800;text-decoration:underline;cursor:pointer}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem}.metric{display:flex;min-width:0;align-items:center;gap:.7rem;border:1px solid var(--dash-border);border-radius:.5rem;background:var(--dash-card);padding:.85rem}.metric-icon{display:grid;place-items:center;width:2.25rem;height:2.25rem;flex:none;border-radius:.4rem}.metric-icon svg{width:1.05rem}.metric-icon.amber{background:var(--dash-warning-soft);color:var(--dash-warning)}.metric-icon.red{background:var(--dash-danger-soft);color:var(--dash-danger)}.metric-icon.orange{background:#ffedd5;color:#c2410c}.is-dark .metric-icon.orange{background:#40291a;color:#fdba74}.metric-icon.blue{background:var(--dash-info-soft);color:var(--dash-info)}.metric strong,.metric small{display:block}.metric strong{font-size:1.25rem}.metric small{color:var(--dash-muted);font-size:.7rem;font-weight:700;overflow-wrap:anywhere}.priority-grid,.operations-grid,.context-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1rem;align-items:start}.panel{min-width:0;border:1px solid var(--dash-border);background:var(--dash-card);border-radius:.5rem;padding:1rem}.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.8rem}.panel-head h2{margin:.12rem 0 0;font-size:1.05rem;letter-spacing:0}.text-link{display:flex;align-items:center;gap:.3rem;border:0;background:transparent;color:var(--dash-accent);padding:.2rem;font-size:.72rem;font-weight:800;cursor:pointer}.text-link svg{width:.85rem}.compliance-summary{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:.8rem;align-items:stretch}.rate-block{border:1px solid var(--dash-border);border-radius:.4rem;background:var(--dash-soft);padding:.8rem}.rate-block>strong,.rate-block>span{display:block}.rate-block>strong{font-size:1.55rem}.rate-block>span{color:var(--dash-muted);font-size:.7rem}.progress{height:.4rem;margin-top:.65rem;border-radius:99px;background:var(--dash-neutral-soft);overflow:clip}.progress span{display:block;height:100%;border-radius:inherit;background:var(--dash-success)}.compact-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem;margin:0}.compact-stats div{display:flex;min-width:0;flex-direction:column-reverse;justify-content:center;border:1px solid var(--dash-border);border-radius:.35rem;background:var(--dash-soft);padding:.5rem}.compact-stats dt{color:var(--dash-muted);font-size:.65rem}.compact-stats dd{margin:0;font-size:1rem;font-weight:800}.compact-stats .danger dd{color:var(--dash-danger)}.compact-list,.workload-list,.quality-list{margin:.75rem 0 0;padding:0;list-style:none}.compact-list li+li,.workload-list li+li,.quality-list li+li{border-top:1px solid var(--dash-border)}.compact-list button,.quality-list button{display:flex;width:100%;min-width:0;align-items:center;justify-content:space-between;gap:.65rem;border:0;background:transparent;color:var(--dash-text);padding:.6rem .2rem;text-align:left;cursor:pointer}.compact-list button>span,.quality-list button>span:first-child{min-width:0}.compact-list strong,.compact-list small,.quality-list strong,.quality-list small{display:block}.compact-list strong,.quality-list strong{font-size:.76rem;overflow-wrap:anywhere}.compact-list small,.quality-list small{color:var(--dash-muted);font-size:.64rem}.compact-list time{flex:none;color:var(--dash-muted);font-size:.68rem}.compact-list time.overdue{color:var(--dash-danger);font-weight:800}.workload-totals{grid-template-columns:repeat(4,minmax(0,1fr))}.workload-list li{display:grid;grid-template-columns:2rem minmax(0,1fr) auto;align-items:center;gap:.55rem;padding:.55rem .1rem}.initials{display:grid;place-items:center;width:2rem;height:2rem;border-radius:50%;background:var(--dash-accent-soft);color:var(--dash-accent);font-size:.65rem;font-weight:800}.officer-copy{min-width:0}.officer-copy strong,.officer-copy small{display:block}.officer-copy strong{font-size:.76rem;overflow-wrap:anywhere}.officer-copy small{color:var(--dash-muted);font-size:.64rem}.load-badge{border-radius:.25rem;background:var(--dash-neutral-soft);color:var(--dash-muted);padding:.2rem .35rem;font-size:.62rem;font-weight:800}.load-badge.moderate{background:var(--dash-warning-soft);color:var(--dash-warning)}.load-badge.high{background:var(--dash-danger-soft);color:var(--dash-danger)}.quality-rate{color:var(--dash-success);font-size:1.1rem;font-weight:800}.panel-note,.inline-empty{margin:.1rem 0;color:var(--dash-muted);font-size:.72rem}.inline-empty{padding:1.5rem .5rem;text-align:center}.inline-empty.success{color:var(--dash-success)}.missing-fields{max-width:48%;color:var(--dash-danger);font-size:.65rem;text-align:right;overflow-wrap:anywhere}.pipeline-list{display:flex;flex-direction:column;gap:.6rem;margin:0;padding:0;list-style:none}.pipeline-list li{display:grid;grid-template-columns:1.6rem minmax(0,1fr);align-items:center;gap:.55rem}.step{display:grid;place-items:center;width:1.6rem;height:1.6rem;border:1px solid var(--dash-border);border-radius:50%;background:var(--dash-soft);font-size:.65rem;font-weight:800}.pipeline-copy{min-width:0}.pipeline-copy>span:first-child{display:flex;justify-content:space-between;gap:.5rem;font-size:.72rem}.pipeline-copy b{font-size:.72rem}.track{display:block;height:.35rem;margin-top:.25rem;border-radius:99px;background:var(--dash-neutral-soft);overflow:clip}.track span{display:block;height:100%;border-radius:inherit;background:var(--dash-accent)}.spin{animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:1100px){.filter-bar{grid-template-columns:repeat(3,minmax(0,1fr))}.filter-actions{grid-column:auto}.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.operations-grid,.context-grid{grid-template-columns:1fr}}@media(max-width:760px){.dashboard{padding:.8rem}.dashboard-head h1{font-size:1.3rem}.filter-bar{grid-template-columns:repeat(2,minmax(0,1fr))}.filter-actions{grid-column:1/-1}.apply-button,.clear-button{flex:1}.priority-grid{grid-template-columns:1fr}.compliance-summary{grid-template-columns:1fr}}@media(max-width:480px){.dashboard{padding:.65rem;border-inline:0}.dashboard-head p:last-child{font-size:.76rem}.filter-bar,.metric-grid{grid-template-columns:1fr}.filter-actions{grid-column:auto}.metric{padding:.7rem}.panel{padding:.75rem}.panel-head{align-items:flex-start;flex-direction:column;gap:.35rem}.text-link{padding:0}.workload-totals{grid-template-columns:repeat(2,minmax(0,1fr))}.workload-list li{grid-template-columns:2rem minmax(0,1fr)}.load-badge{grid-column:2;justify-self:start}.compact-list button,.quality-list button{align-items:flex-start;flex-direction:column}.missing-fields{max-width:none;text-align:left}}
</style>
