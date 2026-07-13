<template>
  <section class="panel decision-panel" aria-labelledby="decision-queue-heading">
    <header class="panel-head">
      <div>
        <p class="kicker">Priority queue</p>
        <h2 id="decision-queue-heading">Decisions requiring attention</h2>
      </div>
      <span class="count" :aria-label="`${items.length} decisions`">{{ items.length }}</span>
    </header>

    <div v-if="loading" class="list-state" aria-live="polite">Loading decision queue...</div>
    <div v-else-if="!items.length" class="list-state empty-state">
      <CheckCircle2 aria-hidden="true" />
      <strong>No decisions are waiting</strong>
      <span>The current filters have no approval, revision, or monitoring reviews.</span>
    </div>
    <ul v-else class="decision-list" role="list">
      <li v-for="item in items" :key="`${item.type}-${item.project_id}-${item.approval_id ?? 0}`">
        <button type="button" class="queue-row" @click="$emit('open', item.route)">
          <span class="type-icon" :class="item.type" aria-hidden="true">
            <FileCheck2 v-if="item.type === 'approval'" />
            <RefreshCcw v-else-if="item.type === 'revision'" />
            <ClipboardCheck v-else />
          </span>
          <span class="row-copy">
            <span class="row-meta">
              <span class="priority" :class="item.priority">{{ priorityLabel(item.priority) }}</span>
              <span>{{ item.project_code || 'No code' }}</span>
              <span>{{ item.current_step }}</span>
            </span>
            <strong>{{ item.title }}</strong>
            <small>{{ item.stage }} · {{ item.action_label }}</small>
          </span>
          <ChevronRight aria-hidden="true" />
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { ChevronRight, CheckCircle2, ClipboardCheck, FileCheck2, RefreshCcw } from 'lucide-vue-next';
import type { DashboardRoute, DecisionQueueItem, Priority } from '@/types/dashboard';

defineProps<{ items: DecisionQueueItem[]; loading: boolean }>();
defineEmits<{ open: [route: DashboardRoute] }>();

const priorityLabel = (priority: Priority) => priority === 'normal' ? 'Standard' : priority;
</script>

<style scoped>
.panel{min-width:0;border:1px solid var(--dash-border);background:var(--dash-card);border-radius:.5rem;padding:1rem}.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.75rem}.kicker{margin:0 0 .15rem;color:var(--dash-accent);font-size:.7rem;font-weight:800;text-transform:uppercase}.panel-head h2{margin:0;font-size:1.05rem;letter-spacing:0}.count{display:grid;place-items:center;min-width:2rem;height:2rem;padding:0 .45rem;border-radius:50%;background:var(--dash-accent-soft);color:var(--dash-accent);font-weight:800}.decision-list{display:flex;flex-direction:column;gap:.5rem;margin:0;padding:0;list-style:none}.queue-row{width:100%;min-width:0;display:grid;grid-template-columns:2.25rem minmax(0,1fr) 1rem;align-items:center;gap:.7rem;border:1px solid var(--dash-border);border-radius:.4rem;background:var(--dash-soft);color:var(--dash-text);padding:.7rem;text-align:left;cursor:pointer}.queue-row:hover{border-color:var(--dash-accent)}.queue-row:focus-visible{outline:3px solid var(--dash-focus);outline-offset:2px}.queue-row>svg{width:1rem;color:var(--dash-muted)}.type-icon{display:grid;place-items:center;width:2.25rem;height:2.25rem;border-radius:.4rem}.type-icon svg{width:1.1rem}.type-icon.approval{background:var(--dash-warning-soft);color:var(--dash-warning)}.type-icon.revision{background:var(--dash-danger-soft);color:var(--dash-danger)}.type-icon.monitoring{background:var(--dash-info-soft);color:var(--dash-info)}.row-copy{min-width:0}.row-copy strong,.row-copy small{display:block}.row-copy strong{margin:.22rem 0;font-size:.86rem;overflow-wrap:anywhere}.row-copy small{color:var(--dash-muted);font-size:.72rem}.row-meta{display:flex;align-items:center;gap:.4rem;min-width:0;color:var(--dash-muted);font-size:.68rem}.row-meta>span:not(.priority){overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.priority{flex:none;border-radius:.25rem;padding:.15rem .3rem;font-weight:800;text-transform:capitalize}.priority.normal{background:var(--dash-neutral-soft);color:var(--dash-muted)}.priority.high{background:var(--dash-warning-soft);color:var(--dash-warning)}.priority.critical{background:var(--dash-danger-soft);color:var(--dash-danger)}.list-state{min-height:15rem;display:flex;align-items:center;justify-content:center;color:var(--dash-muted);text-align:center}.empty-state{flex-direction:column;gap:.3rem}.empty-state svg{width:1.75rem;color:var(--dash-success)}.empty-state span{max-width:28rem;font-size:.78rem}@media(max-width:520px){.panel{padding:.75rem}.queue-row{grid-template-columns:2rem minmax(0,1fr);padding:.65rem}.queue-row>svg{display:none}.type-icon{width:2rem;height:2rem}.row-meta{flex-wrap:wrap}.row-meta>span:not(.priority){white-space:normal}}
</style>
