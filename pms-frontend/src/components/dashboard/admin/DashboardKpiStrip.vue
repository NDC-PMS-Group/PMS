<template>
  <section class="kpi-strip" aria-label="Portfolio summary">
    <button v-for="metric in metrics" :key="metric.key" type="button" class="kpi" :class="metric.tone" @click="$emit('open', metric.key)">
      <span class="kpi-icon"><component :is="metric.icon" aria-hidden="true" /></span>
      <span class="kpi-copy">
        <small>{{ metric.label }}</small>
        <strong>{{ metric.value }}</strong>
        <span>{{ metric.context }}</span>
      </span>
      <ChevronRight aria-hidden="true" />
    </button>
  </section>
</template>

<script setup lang="ts">
import { computed, markRaw } from 'vue';
import { AlertCircle, Banknote, Briefcase, ChevronRight, ClipboardCheck, Landmark } from 'lucide-vue-next';
import type { DashboardStats } from '@/types/dashboard';

const props = defineProps<{ stats: DashboardStats | null }>();
defineEmits<{ open: [key: 'projects' | 'evaluation' | 'portfolio' | 'investment' | 'released' | 'decisions' | 'exceptions'] }>();

const money = (value: number) => new Intl.NumberFormat('en-PH', {
  style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1,
}).format(value || 0);

const metrics = computed(() => {
  const summary = props.stats?.portfolio_summary;
  const critical = props.stats?.risk_projects.filter((project) => project.risk_level === 'critical').length ?? 0;
  const overdue = props.stats?.monitoring_compliance.overdue ?? 0;
  const breached = props.stats?.decision_aging.sla_breached ?? 0;
  return [
    { key: 'projects' as const, label: 'Active NDC Projects', value: summary?.active_ndc_projects ?? 0, context: `${summary?.unclassified_records ?? 0} records need classification`, icon: markRaw(Briefcase), tone: 'neutral' },
    { key: 'evaluation' as const, label: 'Investments Under Evaluation', value: summary?.investments_under_evaluation ?? 0, context: 'Before Board approval', icon: markRaw(ClipboardCheck), tone: 'neutral' },
    { key: 'portfolio' as const, label: 'Investment Portfolio', value: summary?.investment_portfolio ?? 0, context: `${summary?.board_approved_investments ?? 0} approved, awaiting deployment`, icon: markRaw(Briefcase), tone: 'green' },
    { key: 'investment' as const, label: 'Total NDC Investment / Exposure', value: Object.entries(summary?.ndc_investment_by_currency || {}).map(([currency, amount]) => new Intl.NumberFormat('en-PH', { style: 'currency', currency, notation: 'compact' }).format(amount)).join(' · ') || money(0), context: `Planned participation · ${summary?.investment_amount_missing ?? 0} amounts missing`, icon: markRaw(Landmark), tone: 'blue' },
    { key: 'released' as const, label: 'Released funds', value: money(summary?.released_funds ?? 0), context: 'From approved releases', icon: markRaw(Banknote), tone: 'green' },
    { key: 'decisions' as const, label: 'Decisions waiting', value: props.stats?.decision_queue.length ?? 0, context: `${breached} outside SLA`, icon: markRaw(ClipboardCheck), tone: breached ? 'amber' : 'neutral' },
    { key: 'exceptions' as const, label: 'Critical exceptions', value: critical + overdue, context: `${critical} risk · ${overdue} monitoring`, icon: markRaw(AlertCircle), tone: critical + overdue ? 'red' : 'neutral' },
  ];
});
</script>

<style scoped>
.kpi-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-block:1px solid var(--dash-border);background:var(--dash-card)}.kpi{display:grid;grid-template-columns:2.15rem minmax(0,1fr) .8rem;align-items:center;gap:.6rem;min-width:0;border:0;border-right:1px solid var(--dash-border);border-radius:0;background:transparent;color:var(--dash-text);padding:.75rem .8rem;text-align:left;cursor:pointer}.kpi:last-child{border-right:0}.kpi:hover{background:var(--dash-hover)}.kpi:focus-visible{position:relative;z-index:1;outline:3px solid var(--dash-focus);outline-offset:-3px}.kpi-icon{display:grid;place-items:center;width:2.15rem;height:2.15rem;border-radius:.38rem;background:var(--dash-neutral-soft);color:var(--dash-muted)}.kpi-icon svg{width:1rem}.kpi.blue .kpi-icon{background:var(--dash-accent-soft);color:var(--dash-accent)}.kpi.green .kpi-icon{background:var(--dash-success-soft);color:var(--dash-success)}.kpi.amber .kpi-icon{background:var(--dash-warning-soft);color:var(--dash-warning)}.kpi.red .kpi-icon{background:var(--dash-danger-soft);color:var(--dash-danger)}.kpi-copy{min-width:0}.kpi-copy small,.kpi-copy strong,.kpi-copy span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:normal}.kpi-copy small{color:var(--dash-muted);font-size:.63rem;font-weight:800}.kpi-copy strong{margin:.05rem 0;font-size:1.15rem;line-height:1.2}.kpi-copy span{color:var(--dash-muted);font-size:.62rem}.kpi>svg{width:.8rem;color:var(--dash-muted)}@media(max-width:1100px){.kpi-strip{grid-template-columns:repeat(3,minmax(0,1fr))}.kpi:nth-child(3){border-right:0}.kpi:nth-child(-n+3){border-bottom:1px solid var(--dash-border)}}@media(max-width:680px){.kpi-strip{grid-template-columns:1fr 1fr}.kpi:nth-child(3){border-right:1px solid var(--dash-border)}.kpi:nth-child(even){border-right:0}.kpi:nth-child(-n+4){border-bottom:1px solid var(--dash-border)}.kpi:last-child{grid-column:1/-1}.kpi-copy strong{font-size:1rem}}@media(max-width:420px){.kpi-strip{grid-template-columns:1fr}.kpi,.kpi:nth-child(3){border-right:0;border-bottom:1px solid var(--dash-border)}.kpi:last-child{grid-column:auto;border-bottom:0}}
</style>
