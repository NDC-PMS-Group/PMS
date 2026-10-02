<template>
  <main class="dashboard" :class="{ 'is-dark': isDarkMode }" aria-labelledby="dashboard-title">
    <header class="dashboard-head">
      <div>
        <p class="eyebrow">{{ roleLabel }}</p>
        <h1 id="dashboard-title">{{ dashboardTitle }}</h1>
        <p>{{ dashboardSubtitle }}</p>
      </div>
      <div class="freshness">
        <span :class="{ stale: store.error }">{{ updatedLabel }}</span>
        <button type="button" :disabled="store.loading" title="Refresh dashboard" aria-label="Refresh dashboard" @click="store.fetchDashboard">
          <RefreshCw :class="{ spin: store.loading }" aria-hidden="true" />
        </button>
      </div>
    </header>

    <DashboardCommandBar :options="filterOptions" :loading="store.loading" />

    <div v-if="store.error" class="error-banner" role="alert">
      <AlertTriangle aria-hidden="true" />
      <span>{{ store.error }}<template v-if="stats"> Showing the last available dashboard.</template></span>
      <button type="button" @click="store.fetchDashboard">Try again</button>
    </div>

    <template v-if="stats">
      <ProponentDashboard v-if="isProponent" :stats="stats" @open-route="openRoute" @open-project="openProject" />

      <template v-else>
        <DashboardKpiStrip :stats="stats" @open="openMetric" />

        <div class="dashboard-body">
        <section class="primary-grid" aria-label="Priority decisions and portfolio health">
          <DecisionQueue :items="stats.decision_queue" :aging="stats.decision_aging" :loading="store.loading && !stats" @open="openRoute" />
          <PortfolioHealth
            :summary="stats.portfolio_summary"
            :stages="stats.stage_breakdown"
            :sectors="stats.sector_breakdown"
            @open="openRoute"
            @open-legacy="router.push('/projects/legacy')"
          />
        </section>

        <section id="portfolio-analysis" class="analysis-grid" aria-label="Portfolio analysis">
          <PortfolioTrend :points="stats.portfolio_trend" :dark="isDarkMode" />
          <RiskProjects :items="stats.risk_projects" :loading="store.loading && !stats" @open="openRoute" />
        </section>

        <DashboardOperations :stats="stats" @navigate="router.push" @open-project="openProject" />
        </div>
      </template>
    </template>

    <section v-else-if="store.loading" class="dashboard-skeleton" aria-label="Loading dashboard" aria-live="polite">
      <span class="sr-only">Loading dashboard</span>
      <div class="skeleton-kpis"><i v-for="item in 5" :key="item"></i></div>
      <div class="skeleton-panels"><i v-for="item in 4" :key="item"></i></div>
    </section>

    <section v-else class="unavailable-state">
      <LayoutDashboard aria-hidden="true" />
      <h2>Dashboard unavailable</h2>
      <p>We could not load decision-support data for this scope.</p>
      <button type="button" @click="store.fetchDashboard">Reload dashboard</button>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { AlertTriangle, LayoutDashboard, RefreshCw } from 'lucide-vue-next';
import DashboardCommandBar from './DashboardCommandBar.vue';
import DashboardKpiStrip from './DashboardKpiStrip.vue';
import DashboardOperations from './DashboardOperations.vue';
import DecisionQueue from './DecisionQueue.vue';
import PortfolioHealth from './PortfolioHealth.vue';
import PortfolioTrend from './PortfolioTrend.vue';
import ProponentDashboard from './ProponentDashboard.vue';
import RiskProjects from './RiskProjects.vue';
import { useDashboardStore } from '@/store/dashboard';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import type { DashboardFilterPayload, DashboardRoute } from '@/types/dashboard';

const router = useRouter();
const store = useDashboardStore();
const layoutStore = useLayoutStore();
const stats = computed(() => store.stats);
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);

const emptyFilters: DashboardFilterPayload = {
  applied: store.filters,
  available_years: [],
  due_windows: [
    { value: 'overdue', label: 'Overdue only' },
    { value: '7', label: 'Next 7 days' },
    { value: '14', label: 'Next 14 days' },
    { value: '30', label: 'Next 30 days' },
    { value: 'all', label: 'All dates' },
  ],
  scopes: [{ value: 'mine', label: 'My assignments' }],
  sectors: [], stages: [], origin_tracks: [], lifecycle_phases: [], officers: [],
  record_sources: [
    { value: 'all', label: 'All records' },
    { value: 'pms', label: 'PMS projects' },
    { value: 'legacy', label: 'Legacy projects' },
  ],
  role: { name: 'User', mode: 'officer', can_view_portfolio: false, default_scope: 'mine' },
};

const filterOptions = computed(() => stats.value?.filters ?? emptyFilters);
const isProponent = computed(() => filterOptions.value.role.name.toLowerCase().includes('proponent'));
const roleLabel = computed(() => `${filterOptions.value.role.name} · ${store.isPortfolioMode ? 'Portfolio' : 'My assignments'}`);
const dashboardTitle = computed(() => isProponent.value ? 'My monitoring workspace' : store.isPortfolioMode ? 'Portfolio command center' : 'My work dashboard');
const dashboardSubtitle = computed(() => isProponent.value
  ? 'Linked projects, monitoring obligations, due dates, and returned actions.'
  : store.isPortfolioMode
    ? 'Decisions, delivery health, investment, and operational exceptions in one view.'
    : 'Your decisions, assigned projects, due work, and delivery risks.');
const updatedLabel = computed(() => {
  if (!stats.value?.generated_at) return store.loading ? 'Updating...' : 'Not updated';
  const value = new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(stats.value.generated_at));
  return store.loading ? 'Updating...' : `Updated ${value}`;
});

const openRoute = (route: DashboardRoute) => router.push({ path: route.path, query: route.query });
const openProject = (projectId: number, isLegacy: boolean, tab = 'overview') => router.push({
  path: isLegacy ? '/projects/legacy' : '/projects',
  query: { project_id: projectId, ...(isLegacy ? {} : { tab }) },
});

const scrollTo = (selector: string) => {
  document.querySelector(selector)?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
};

const openMetric = (key: 'projects' | 'evaluation' | 'portfolio' | 'investment' | 'released' | 'decisions' | 'exceptions') => {
  if (key === 'decisions') return scrollTo('#decision-queue-heading');
  if (key === 'exceptions') return scrollTo('#risk-projects-heading');
  if (['projects', 'evaluation', 'portfolio', 'investment'].includes(key)) return router.push({ path: '/projects', query: { include_legacy: '1', record_type: key === 'projects' ? 'project' : 'investment', investment_status: key === 'evaluation' ? 'under_evaluation' : key === 'portfolio' ? 'portfolio' : undefined } });
  return router.push({ path: '/admin/reports', query: { is_legacy: store.filters.record_source === 'all' ? undefined : store.filters.record_source === 'legacy' ? 1 : 0 } });
};

onMounted(() => store.fetchDashboard());
</script>

<style scoped>
.dashboard{--dash-bg:#f4f7fb;--dash-card:#fff;--dash-input:#fff;--dash-soft:#f7f9fc;--dash-hover:#f1f5f9;--dash-border:#d9e2ec;--dash-text:#172033;--dash-muted:#64748b;--dash-accent:#1d4ed8;--dash-accent-strong:#2563eb;--dash-accent-soft:#e5efff;--dash-focus:#60a5fa;--dash-success:#15803d;--dash-success-soft:#dcfce7;--dash-warning:#a16207;--dash-warning-soft:#fef3c7;--dash-danger:#b91c1c;--dash-danger-soft:#fee2e2;--dash-info:#0369a1;--dash-info-soft:#e0f2fe;--dash-neutral-soft:#e9eef5;display:flex;min-width:0;flex-direction:column;background:var(--dash-bg);color:var(--dash-text);color-scheme:light;border:1px solid var(--dash-border);border-radius:.5rem;overflow:clip}.dashboard.is-dark{--dash-bg:#0b1220;--dash-card:#111b2b;--dash-input:#0e1828;--dash-soft:#0e1828;--dash-hover:#182438;--dash-border:#314159;--dash-text:#f3f6fa;--dash-muted:#a8b5c7;--dash-accent:#86b7ff;--dash-accent-strong:#3b82f6;--dash-accent-soft:#17335b;--dash-focus:#93c5fd;--dash-success:#74e6a0;--dash-success-soft:#163b2b;--dash-warning:#facc6b;--dash-warning-soft:#3d3016;--dash-danger:#fda4a4;--dash-danger-soft:#451f27;--dash-info:#7dd3fc;--dash-info-soft:#123448;--dash-neutral-soft:#263449;color-scheme:dark}.dashboard :deep(h1),.dashboard :deep(h2),.dashboard :deep(h3){color:var(--dash-text)}.dashboard-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.1rem .8rem;background:var(--dash-card)}.dashboard-head h1{margin:.08rem 0 .12rem;font-size:1.45rem;letter-spacing:0}.dashboard-head p:last-child{margin:0;color:var(--dash-muted);font-size:.76rem}.eyebrow{margin:0;color:var(--dash-accent);font-size:.65rem;font-weight:800;text-transform:uppercase}.freshness{display:flex;align-items:center;gap:.55rem;color:var(--dash-muted);font-size:.63rem;white-space:nowrap}.freshness span.stale{color:var(--dash-warning)}.freshness button{display:grid;width:2.25rem;height:2.25rem;place-items:center;border:1px solid var(--dash-border);border-radius:.35rem;background:var(--dash-card);color:var(--dash-text);cursor:pointer}.freshness button svg{width:.95rem}.freshness button:focus-visible,.error-banner button:focus-visible,.unavailable-state button:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}.error-banner{display:flex;align-items:center;gap:.55rem;border-bottom:1px solid var(--dash-danger);background:var(--dash-danger-soft);color:var(--dash-danger);padding:.55rem .85rem;font-size:.7rem}.error-banner svg{width:.9rem;flex:none}.error-banner span{flex:1}.error-banner button{border:0;background:transparent;color:inherit;font:inherit;font-weight:800;text-decoration:underline;cursor:pointer}.dashboard-body{display:flex;min-width:0;flex-direction:column;gap:.85rem;padding:.85rem}.primary-grid,.analysis-grid{display:grid;grid-template-columns:minmax(0,7fr) minmax(19rem,5fr);gap:.85rem}.primary-grid{align-items:stretch}.analysis-grid{align-items:start}.dashboard-skeleton{padding:.85rem}.skeleton-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:1px;background:var(--dash-border)}.skeleton-kpis i,.skeleton-panels i{display:block;background:linear-gradient(90deg,var(--dash-soft),var(--dash-card),var(--dash-soft));background-size:200% 100%;animation:shimmer 1.4s infinite}.skeleton-kpis i{height:5rem}.skeleton-panels{display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem;margin-top:.85rem}.skeleton-panels i{height:18rem;border:1px solid var(--dash-border);border-radius:.45rem}.unavailable-state{display:flex;min-height:24rem;align-items:center;justify-content:center;flex-direction:column;gap:.35rem;padding:2rem;text-align:center}.unavailable-state svg{width:2rem;color:var(--dash-muted)}.unavailable-state h2{margin:.2rem 0 0;font-size:1rem}.unavailable-state p{margin:0;color:var(--dash-muted);font-size:.72rem}.unavailable-state button{margin-top:.5rem;border:1px solid var(--dash-accent-strong);border-radius:.35rem;background:var(--dash-accent-strong);color:#fff;padding:.5rem .8rem;font:inherit;font-size:.7rem;font-weight:800;cursor:pointer}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}.spin{animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@keyframes shimmer{from{background-position:200% 0}to{background-position:-200% 0}}@media(prefers-reduced-motion:reduce){.spin,.skeleton-kpis i,.skeleton-panels i{animation:none}}@media(max-width:1050px){.primary-grid,.analysis-grid{grid-template-columns:1fr}.skeleton-kpis{grid-template-columns:repeat(3,1fr)}}@media(max-width:680px){.dashboard{border-inline:0;border-radius:0}.dashboard-head{padding:.8rem}.dashboard-head h1{font-size:1.22rem}.dashboard-head p:last-child{max-width:25rem}.freshness>span{display:none}.dashboard-body{padding:.65rem;gap:.65rem}.skeleton-panels{grid-template-columns:1fr}.skeleton-kpis{grid-template-columns:1fr 1fr}}@media(max-width:420px){.dashboard-head p:last-child{font-size:.68rem}.skeleton-kpis{grid-template-columns:1fr}}
</style>
