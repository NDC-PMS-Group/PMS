<template>
  <section class="operations" aria-labelledby="operations-heading">
    <header class="section-head">
      <div><p class="kicker">Operations</p><h2 id="operations-heading">Delivery controls</h2></div>
      <span>{{ stats.portfolio_summary.unassigned_projects }} projects unassigned</span>
    </header>

    <ol class="lifecycle-flow" aria-label="Project lifecycle distribution">
      <li v-for="(item, index) in stats.lifecycle_pipeline" :key="item.label">
        <span>{{ index + 1 }}</span>
        <div><strong>{{ item.count }}</strong><small>{{ item.label }}</small></div>
      </li>
    </ol>

    <div class="operations-grid">
      <article class="operation-panel monitoring-panel">
        <header><div><p class="kicker">Monitoring</p><h3>Compliance</h3></div><button type="button" @click="$emit('navigate', '/admin/post-monitoring')">Open <ArrowUpRight aria-hidden="true" /></button></header>
        <div class="rate-row">
          <strong>{{ percent(stats.monitoring_compliance.compliance_rate) }}</strong>
          <span>submitted or accepted</span>
          <div class="progress" role="progressbar" aria-label="Monitoring compliance" :aria-valuenow="stats.monitoring_compliance.compliance_rate" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${Math.min(100, stats.monitoring_compliance.compliance_rate)}%` }"></span></div>
        </div>
        <dl class="mini-stats">
          <div><dt>Active</dt><dd>{{ stats.monitoring_compliance.active }}</dd></div>
          <div><dt>Due</dt><dd>{{ stats.monitoring_compliance.due_in_window }}</dd></div>
          <div class="danger"><dt>Overdue</dt><dd>{{ stats.monitoring_compliance.overdue }}</dd></div>
          <div><dt>No due date</dt><dd>{{ stats.monitoring_compliance.missing_due_date }}</dd></div>
        </dl>
        <ul v-if="stats.monitoring_compliance.projects.length" class="action-list">
          <li v-for="project in stats.monitoring_compliance.projects.slice(0, 3)" :key="project.project_id">
            <button type="button" @click="$emit('open-project', project.project_id, project.is_legacy, 'monitoring')">
              <span><strong>{{ project.title }}</strong><small>{{ project.project_code }} · {{ label(project.submission_status) }}</small></span>
              <time :datetime="project.due_date || undefined" :class="{ overdue: project.is_overdue }">{{ date(project.due_date) }}</time>
            </button>
          </li>
        </ul>
        <p v-else class="empty-inline">No monitoring items in this window.</p>
      </article>

      <article class="operation-panel quality-panel">
        <header><div><p class="kicker">Data confidence</p><h3>Core-field quality</h3></div><strong class="quality-rate">{{ percent(stats.data_quality.completeness_rate) }}</strong></header>
        <p class="panel-note">{{ stats.data_quality.projects_with_issues }} of {{ stats.data_quality.total_projects }} projects need essential details.</p>
        <ul v-if="stats.data_quality.records.length" class="action-list quality-list">
          <li v-for="record in stats.data_quality.records.slice(0, 4)" :key="record.project_id">
            <button type="button" @click="$emit('open-project', record.project_id, record.is_legacy, 'overview')">
              <span><strong>{{ record.title }}</strong><small>{{ record.project_code }}</small></span>
              <span class="missing">{{ fields(record.missing_fields) }}</span>
            </button>
          </li>
        </ul>
        <p v-else class="empty-inline success">Core decision fields are complete.</p>
      </article>

      <article class="operation-panel legacy-panel">
        <header><div><p class="kicker">Migration</p><h3>Legacy readiness</h3></div><button type="button" @click="$emit('navigate', '/projects/legacy')">Workspace <ArrowUpRight aria-hidden="true" /></button></header>
        <div class="legacy-total"><strong>{{ stats.legacy_portfolio.active_records }}</strong><span>active legacy records</span></div>
        <dl class="legacy-states">
          <div><dt>Needs details</dt><dd>{{ stats.legacy_portfolio.needs_details }}</dd></div>
          <div><dt>In progress</dt><dd>{{ stats.legacy_portfolio.in_progress }}</dd></div>
          <div><dt>Monitoring</dt><dd>{{ stats.legacy_portfolio.monitoring_active }}</dd></div>
        </dl>
        <p class="panel-note">Legacy records contribute to portfolio reporting but remain managed in their migration workspace.</p>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ArrowUpRight } from 'lucide-vue-next';
import type { DashboardStats } from '@/types/dashboard';

defineProps<{ stats: DashboardStats }>();
defineEmits<{
  navigate: [path: string];
  'open-project': [projectId: number, isLegacy: boolean, tab: string];
}>();

const percent = (value: number) => `${Number(value || 0).toLocaleString('en-PH', { maximumFractionDigits: 1 })}%`;
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const fields = (values: string[]) => values.slice(0, 2).map(label).join(', ') + (values.length > 2 ? ` +${values.length - 2}` : '');
const date = (value: string | null) => value ? new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }).format(new Date(`${value}T00:00:00+08:00`)) : 'No date';
</script>

<style scoped>
.operations{min-width:0;border-top:1px solid var(--dash-border);padding-top:.9rem}.section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:.65rem}.section-head h2{margin:.1rem 0 0;font-size:1.1rem}.section-head>span{color:var(--dash-muted);font-size:.66rem}.kicker{margin:0;color:var(--dash-accent);font-size:.65rem;font-weight:800;text-transform:uppercase}.lifecycle-flow{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));margin:0 0 .75rem;padding:0;border:1px solid var(--dash-border);border-radius:.4rem;background:var(--dash-card);list-style:none;overflow:hidden}.lifecycle-flow li{position:relative;display:flex;align-items:center;gap:.45rem;min-width:0;border-right:1px solid var(--dash-border);padding:.55rem}.lifecycle-flow li:last-child{border-right:0}.lifecycle-flow li>span{display:grid;width:1.45rem;height:1.45rem;flex:none;place-items:center;border-radius:50%;background:var(--dash-accent-soft);color:var(--dash-accent);font-size:.6rem;font-weight:800}.lifecycle-flow strong,.lifecycle-flow small{display:block}.lifecycle-flow strong{font-size:.82rem}.lifecycle-flow small{color:var(--dash-muted);font-size:.58rem;line-height:1.2;overflow-wrap:anywhere}.operations-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.operation-panel{min-width:0;border:1px solid var(--dash-border);border-radius:.45rem;background:var(--dash-card);padding:.8rem}.operation-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;margin-bottom:.6rem}.operation-panel h3{margin:.1rem 0 0;font-size:.88rem}.operation-panel header button{display:inline-flex;align-items:center;gap:.2rem;border:0;background:transparent;color:var(--dash-accent);padding:.1rem;font:inherit;font-size:.62rem;font-weight:800;cursor:pointer}.operation-panel header button svg{width:.7rem}.rate-row{display:grid;grid-template-columns:auto 1fr;align-items:end;gap:.1rem .45rem}.rate-row>strong{font-size:1.35rem}.rate-row>span{padding-bottom:.18rem;color:var(--dash-muted);font-size:.62rem}.progress{grid-column:1/-1;height:.32rem;border-radius:99px;background:var(--dash-neutral-soft);overflow:hidden}.progress span{display:block;height:100%;background:var(--dash-success)}.mini-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.3rem;margin:.65rem 0}.mini-stats div{display:flex;min-width:0;flex-direction:column-reverse;border-left:2px solid var(--dash-border);padding-left:.35rem}.mini-stats dt{color:var(--dash-muted);font-size:.55rem;overflow-wrap:anywhere}.mini-stats dd{margin:0;font-size:.82rem;font-weight:800}.mini-stats .danger{border-color:var(--dash-danger)}.mini-stats .danger dd{color:var(--dash-danger)}.action-list{max-height:16rem;min-height:0;margin:.2rem 0 0;padding:0 .2rem 0 0;list-style:none;overflow:auto;overscroll-behavior:contain}.action-list li+li{border-top:1px solid var(--dash-border)}.action-list button{display:flex;width:100%;min-width:0;align-items:center;justify-content:space-between;gap:.5rem;border:0;background:transparent;color:var(--dash-text);padding:.46rem .1rem;text-align:left;cursor:pointer}.action-list button>span:first-child{min-width:0}.action-list strong,.action-list small{display:block}.action-list strong{font-size:.66rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.action-list small{color:var(--dash-muted);font-size:.56rem}.action-list time{flex:none;color:var(--dash-muted);font-size:.58rem}.action-list time.overdue{color:var(--dash-danger);font-weight:800}.quality-rate{color:var(--dash-success);font-size:.95rem}.panel-note,.empty-inline{margin:.35rem 0;color:var(--dash-muted);font-size:.61rem;line-height:1.4}.empty-inline{padding:.75rem 0;text-align:center}.empty-inline.success{color:var(--dash-success)}.missing{max-width:48%;color:var(--dash-danger);font-size:.55rem;text-align:right;overflow-wrap:anywhere}.legacy-total{display:flex;align-items:baseline;gap:.4rem;border-bottom:1px solid var(--dash-border);padding-bottom:.5rem}.legacy-total strong{font-size:1.4rem}.legacy-total span{color:var(--dash-muted);font-size:.62rem}.legacy-states{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.35rem;margin:.55rem 0}.legacy-states div{min-width:0}.legacy-states dt{color:var(--dash-muted);font-size:.55rem}.legacy-states dd{margin:.1rem 0 0;font-size:.8rem;font-weight:800}.operation-panel button:focus-visible,.action-list button:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}@media(max-width:1180px){.operations-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.lifecycle-flow{grid-template-columns:repeat(4,minmax(0,1fr))}.lifecycle-flow li:nth-child(4){border-right:0}.lifecycle-flow li:nth-child(-n+4){border-bottom:1px solid var(--dash-border)}}@media(max-width:680px){.operations-grid{grid-template-columns:1fr}.action-list{max-height:20rem}.lifecycle-flow{display:flex;overflow-x:auto}.lifecycle-flow li{min-width:8rem;border-bottom:0!important}.section-head{align-items:flex-start;flex-direction:column;gap:.2rem}}
</style>
