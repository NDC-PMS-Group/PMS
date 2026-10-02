<template>
  <section class="panel health-panel" aria-labelledby="portfolio-health-heading">
    <header class="panel-head">
      <div>
        <p class="kicker">Portfolio health</p>
        <h2 id="portfolio-health-heading">Where investment is concentrated</h2>
      </div>
      <div class="view-switch" aria-label="Breakdown view">
        <button type="button" :class="{ active: view === 'stage' }" :aria-pressed="view === 'stage'" @click="view = 'stage'">Stage</button>
        <button type="button" :class="{ active: view === 'sector' }" :aria-pressed="view === 'sector'" @click="view = 'sector'">Sector</button>
      </div>
    </header>

    <div class="source-line" aria-label="Record source breakdown">
      <span><i class="pms-dot"></i>PMS <strong>{{ summary.pms_projects }}</strong></span>
      <span><i class="legacy-dot"></i>Legacy <strong>{{ summary.legacy_projects }}</strong></span>
      <button v-if="summary.legacy_projects" type="button" @click="$emit('open-legacy')">Review migration <ArrowUpRight aria-hidden="true" /></button>
    </div>

    <ol v-if="breakdown.length" class="ranked-list">
      <li v-for="item in breakdown.slice(0, 7)" :key="`${view}-${item.id ?? 'none'}`">
        <button type="button" @click="$emit('open', item.route)">
          <span class="rank-copy">
            <strong>{{ item.label }}</strong>
            <small>{{ item.count }} projects · {{ money(item.investment) }}</small>
          </span>
          <span class="rank-value">{{ item.percentage }}%</span>
          <span class="rank-track" aria-hidden="true"><span :style="{ width: `${item.percentage}%` }"></span></span>
        </button>
      </li>
    </ol>
    <div v-else class="empty-state">
      <BarChart3 aria-hidden="true" />
      <strong>No portfolio distribution yet</strong>
      <span>Stage and sector comparisons will appear when projects match this scope.</span>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { ArrowUpRight, BarChart3 } from 'lucide-vue-next';
import type { DashboardBreakdown, DashboardRoute, PortfolioSummary } from '@/types/dashboard';

const props = defineProps<{
  summary: PortfolioSummary;
  stages: DashboardBreakdown[];
  sectors: DashboardBreakdown[];
}>();
defineEmits<{ open: [route: DashboardRoute]; 'open-legacy': [] }>();

const view = ref<'stage' | 'sector'>('stage');
const breakdown = computed(() => view.value === 'stage' ? props.stages : props.sectors);
const money = (value: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1 }).format(value || 0);
</script>

<style scoped>
.panel{min-width:0;border:1px solid var(--dash-border);border-radius:.45rem;background:var(--dash-card);padding:.9rem}.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:.65rem}.kicker{margin:0 0 .12rem;color:var(--dash-accent);font-size:.65rem;font-weight:800;text-transform:uppercase}.panel-head h2{margin:0;font-size:1rem}.view-switch{display:flex;padding:.15rem;border:1px solid var(--dash-border);border-radius:.35rem;background:var(--dash-soft)}.view-switch button{height:1.65rem;border:0;border-radius:.25rem;background:transparent;color:var(--dash-muted);padding:0 .5rem;font:inherit;font-size:.65rem;font-weight:800;cursor:pointer}.view-switch button.active{background:var(--dash-card);color:var(--dash-accent);box-shadow:0 1px 2px rgb(15 23 42 / .12)}.source-line{display:flex;align-items:center;gap:.8rem;border-block:1px solid var(--dash-border);padding:.5rem 0;color:var(--dash-muted);font-size:.66rem}.source-line span{display:inline-flex;align-items:center;gap:.28rem}.source-line i{width:.45rem;height:.45rem;border-radius:50%}.pms-dot{background:var(--dash-accent-strong)}.legacy-dot{background:var(--dash-warning)}.source-line button{display:inline-flex;align-items:center;gap:.18rem;margin-left:auto;border:0;background:transparent;color:var(--dash-accent);padding:.15rem;font:inherit;font-size:.64rem;font-weight:800;cursor:pointer}.source-line button svg{width:.72rem}.ranked-list{display:flex;flex-direction:column;gap:.15rem;margin:.55rem 0 0;padding:0;list-style:none}.ranked-list button{display:grid;width:100%;grid-template-columns:minmax(0,1fr) auto;gap:.12rem .6rem;border:0;border-radius:.3rem;background:transparent;color:var(--dash-text);padding:.42rem;text-align:left;cursor:pointer}.ranked-list button:hover{background:var(--dash-hover)}.ranked-list button:focus-visible,.view-switch button:focus-visible,.source-line button:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}.rank-copy{min-width:0}.rank-copy strong,.rank-copy small{display:block}.rank-copy strong{font-size:.73rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rank-copy small{color:var(--dash-muted);font-size:.61rem}.rank-value{align-self:center;color:var(--dash-muted);font-size:.68rem;font-weight:800}.rank-track{grid-column:1/-1;height:.28rem;border-radius:99px;background:var(--dash-neutral-soft);overflow:hidden}.rank-track span{display:block;height:100%;border-radius:inherit;background:var(--dash-accent-strong)}.empty-state{display:flex;min-height:14rem;align-items:center;justify-content:center;flex-direction:column;gap:.25rem;color:var(--dash-muted);text-align:center}.empty-state svg{width:1.6rem}.empty-state strong{color:var(--dash-text);font-size:.8rem}.empty-state span{max-width:20rem;font-size:.68rem}@media(max-width:460px){.panel-head{align-items:flex-start;flex-direction:column}.source-line{flex-wrap:wrap}.source-line button{width:100%;margin-left:0}}
</style>
