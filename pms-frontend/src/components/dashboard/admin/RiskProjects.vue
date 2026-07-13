<template>
  <section class="panel risk-panel" aria-labelledby="risk-projects-heading">
    <header class="panel-head">
      <div>
        <p class="kicker">Portfolio risk</p>
        <h2 id="risk-projects-heading">Projects needing intervention</h2>
      </div>
      <span class="count danger" :aria-label="`${items.length} risk projects`">{{ items.length }}</span>
    </header>

    <div v-if="loading" class="list-state" aria-live="polite">Evaluating project risks...</div>
    <div v-else-if="!items.length" class="list-state empty-state">
      <ShieldCheck aria-hidden="true" />
      <strong>No material risks found</strong>
      <span>No overdue delivery, compliance, or cost risks match this view.</span>
    </div>
    <ul v-else class="risk-list" role="list">
      <li v-for="project in items" :key="project.project_id">
        <button type="button" class="risk-row" @click="$emit('open', project.route)">
          <span class="risk-score" :class="project.risk_level">
            <strong>{{ project.risk_score }}</strong>
            <small>{{ project.risk_level }}</small>
          </span>
          <span class="risk-copy">
            <span class="risk-meta">{{ project.project_code }} · {{ project.stage }}</span>
            <strong>{{ project.title }}</strong>
            <span class="reason-list">
              <span v-for="reason in project.reasons.slice(0, 2)" :key="reason.code">{{ reason.label }}</span>
            </span>
            <small>Owner: {{ project.officer }}</small>
          </span>
          <ChevronRight aria-hidden="true" />
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { ChevronRight, ShieldCheck } from 'lucide-vue-next';
import type { DashboardRoute, RiskProject } from '@/types/dashboard';

defineProps<{ items: RiskProject[]; loading: boolean }>();
defineEmits<{ open: [route: DashboardRoute] }>();
</script>

<style scoped>
.panel{min-width:0;border:1px solid var(--dash-border);background:var(--dash-card);border-radius:.5rem;padding:1rem}.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.75rem}.kicker{margin:0 0 .15rem;color:var(--dash-danger);font-size:.7rem;font-weight:800;text-transform:uppercase}.panel-head h2{margin:0;font-size:1.05rem;letter-spacing:0}.count{display:grid;place-items:center;min-width:2rem;height:2rem;padding:0 .45rem;border-radius:50%;font-weight:800}.count.danger{background:var(--dash-danger-soft);color:var(--dash-danger)}.risk-list{display:flex;flex-direction:column;gap:.5rem;margin:0;padding:0;list-style:none}.risk-row{width:100%;min-width:0;display:grid;grid-template-columns:3.1rem minmax(0,1fr) 1rem;align-items:center;gap:.7rem;border:1px solid var(--dash-border);border-radius:.4rem;background:var(--dash-soft);color:var(--dash-text);padding:.7rem;text-align:left;cursor:pointer}.risk-row:hover{border-color:var(--dash-danger)}.risk-row:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}.risk-row>svg{width:1rem;color:var(--dash-muted)}.risk-score{display:flex;width:3.1rem;height:3.1rem;flex-direction:column;align-items:center;justify-content:center;border-radius:.4rem}.risk-score strong{font-size:1rem}.risk-score small{font-size:.6rem;text-transform:uppercase}.risk-score.watch{background:var(--dash-neutral-soft);color:var(--dash-muted)}.risk-score.high{background:var(--dash-warning-soft);color:var(--dash-warning)}.risk-score.critical{background:var(--dash-danger-soft);color:var(--dash-danger)}.risk-copy{min-width:0}.risk-copy>strong,.risk-copy>small,.risk-meta{display:block}.risk-copy>strong{margin:.15rem 0;font-size:.86rem;overflow-wrap:anywhere}.risk-copy>small,.risk-meta{color:var(--dash-muted);font-size:.68rem}.reason-list{display:flex;flex-wrap:wrap;gap:.25rem;margin:.3rem 0}.reason-list span{border:1px solid var(--dash-border);border-radius:.25rem;padding:.15rem .3rem;font-size:.63rem}.list-state{min-height:15rem;display:flex;align-items:center;justify-content:center;color:var(--dash-muted);text-align:center}.empty-state{flex-direction:column;gap:.3rem}.empty-state svg{width:1.75rem;color:var(--dash-success)}.empty-state span{max-width:28rem;font-size:.78rem}@media(max-width:520px){.panel{padding:.75rem}.risk-row{grid-template-columns:2.65rem minmax(0,1fr);padding:.65rem}.risk-row>svg{display:none}.risk-score{width:2.65rem;height:2.65rem}}
</style>
