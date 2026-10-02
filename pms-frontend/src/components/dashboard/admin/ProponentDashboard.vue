<template>
  <div class="proponent-dashboard">
    <section class="summary-strip" aria-label="My project obligations">
      <div><span class="icon blue"><Briefcase aria-hidden="true" /></span><span><small>Linked projects</small><strong>{{ stats.portfolio_summary.active_projects }}</strong></span></div>
      <div><span class="icon green"><Activity aria-hidden="true" /></span><span><small>Active monitoring</small><strong>{{ stats.monitoring_compliance.active }}</strong></span></div>
      <div><span class="icon amber"><CalendarClock aria-hidden="true" /></span><span><small>Due in window</small><strong>{{ stats.monitoring_compliance.due_in_window }}</strong></span></div>
      <div><span class="icon red"><Undo2 aria-hidden="true" /></span><span><small>Returned actions</small><strong>{{ stats.revision_requests_count }}</strong></span></div>
    </section>

    <section class="proponent-grid">
      <DecisionQueue :items="stats.decision_queue" :aging="stats.decision_aging" :loading="false" @open="$emit('open-route', $event)" />

      <section class="monitoring-panel" aria-labelledby="my-monitoring-heading">
        <header>
          <div><p>Monitoring</p><h2 id="my-monitoring-heading">My compliance schedule</h2></div>
          <strong>{{ percent(stats.monitoring_compliance.compliance_rate) }}</strong>
        </header>
        <div class="compliance-line"><span :style="{ width: `${Math.min(100, stats.monitoring_compliance.compliance_rate)}%` }"></span></div>
        <ul v-if="stats.monitoring_compliance.projects.length">
          <li v-for="project in stats.monitoring_compliance.projects" :key="project.project_id">
            <button type="button" @click="$emit('open-project', project.project_id, project.is_legacy, 'monitoring')">
              <span><strong>{{ project.title }}</strong><small>{{ project.project_code }} · {{ label(project.submission_status) }}</small></span>
              <span class="due" :class="{ overdue: project.is_overdue }"><time :datetime="project.due_date || undefined">{{ date(project.due_date) }}</time><ChevronRight aria-hidden="true" /></span>
            </button>
          </li>
        </ul>
        <div v-else class="empty-state">
          <CheckCircle2 aria-hidden="true" />
          <strong>No monitoring reports are due</strong>
          <span>Your linked project obligations will appear here.</span>
        </div>
      </section>
    </section>
  </div>
</template>

<script setup lang="ts">
import { Activity, Briefcase, CalendarClock, CheckCircle2, ChevronRight, Undo2 } from 'lucide-vue-next';
import DecisionQueue from './DecisionQueue.vue';
import type { DashboardRoute, DashboardStats } from '@/types/dashboard';

defineProps<{ stats: DashboardStats }>();
defineEmits<{
  'open-route': [route: DashboardRoute];
  'open-project': [projectId: number, isLegacy: boolean, tab: string];
}>();

const percent = (value: number) => `${Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 1 })}%`;
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const date = (value: string | null) => value ? new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(`${value}T00:00:00+08:00`)) : 'No due date';
</script>

<style scoped>
.proponent-dashboard{display:flex;flex-direction:column;gap:.85rem;padding:.85rem}.summary-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid var(--dash-border);border-radius:.4rem;background:var(--dash-card);overflow:hidden}.summary-strip>div{display:flex;align-items:center;gap:.55rem;border-right:1px solid var(--dash-border);padding:.7rem}.summary-strip>div:last-child{border-right:0}.summary-strip small,.summary-strip strong{display:block}.summary-strip small{color:var(--dash-muted);font-size:.62rem;font-weight:800}.summary-strip strong{font-size:1rem}.icon{display:grid;width:2rem;height:2rem;flex:none;place-items:center;border-radius:.35rem;background:var(--dash-neutral-soft);color:var(--dash-muted)}.icon svg{width:.95rem}.icon.blue{background:var(--dash-accent-soft);color:var(--dash-accent)}.icon.green{background:var(--dash-success-soft);color:var(--dash-success)}.icon.amber{background:var(--dash-warning-soft);color:var(--dash-warning)}.icon.red{background:var(--dash-danger-soft);color:var(--dash-danger)}.proponent-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(19rem,.8fr);gap:.85rem;align-items:stretch}.monitoring-panel{min-width:0;border:1px solid var(--dash-border);border-radius:.45rem;background:var(--dash-card);padding:1rem}.monitoring-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem}.monitoring-panel header p{margin:0;color:var(--dash-accent);font-size:.65rem;font-weight:800;text-transform:uppercase}.monitoring-panel h2{margin:.12rem 0 0;font-size:1.05rem}.monitoring-panel>header>strong{color:var(--dash-success);font-size:1.1rem}.compliance-line{height:.35rem;margin:.65rem 0;border-radius:99px;background:var(--dash-neutral-soft);overflow:hidden}.compliance-line span{display:block;height:100%;background:var(--dash-success)}.monitoring-panel ul{max-height:30rem;min-height:0;margin:0;padding:0 .2rem 0 0;list-style:none;overflow:auto;overscroll-behavior:contain}.monitoring-panel li+li{border-top:1px solid var(--dash-border)}.monitoring-panel li button{display:flex;width:100%;min-width:0;align-items:center;justify-content:space-between;gap:.65rem;border:0;background:transparent;color:var(--dash-text);padding:.7rem .1rem;text-align:left;cursor:pointer}.monitoring-panel li button>span:first-child{min-width:0}.monitoring-panel li strong,.monitoring-panel li small{display:block}.monitoring-panel li strong{font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.monitoring-panel li small{color:var(--dash-muted);font-size:.64rem}.due{display:flex;flex:none;align-items:center;gap:.25rem;color:var(--dash-muted);font-size:.65rem}.due.overdue{color:var(--dash-danger);font-weight:800}.due svg{width:.8rem}.empty-state{display:flex;min-height:14rem;align-items:center;justify-content:center;flex-direction:column;gap:.25rem;color:var(--dash-muted);text-align:center}.empty-state svg{width:1.5rem;color:var(--dash-success)}.empty-state strong{color:var(--dash-text);font-size:.76rem}.empty-state span{font-size:.66rem}.monitoring-panel button:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}@media(max-width:900px){.proponent-grid{grid-template-columns:1fr}}@media(max-width:680px){.proponent-dashboard{padding:.65rem}.monitoring-panel ul{max-height:24rem}.summary-strip{grid-template-columns:repeat(2,minmax(0,1fr))}.summary-strip>div:nth-child(2){border-right:0}.summary-strip>div:nth-child(-n+2){border-bottom:1px solid var(--dash-border)}}@media(max-width:420px){.summary-strip{grid-template-columns:1fr}.summary-strip>div{border-right:0;border-bottom:1px solid var(--dash-border)}.summary-strip>div:last-child{border-bottom:0}}
</style>
