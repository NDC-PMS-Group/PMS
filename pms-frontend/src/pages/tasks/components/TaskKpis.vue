<template>
  <section class="kpi-strip" aria-label="Task summary">
    <button v-for="item in items" :key="item.key" type="button" :class="['kpi', item.tone]" @click="$emit('select', item.key)">
      <component :is="item.icon" aria-hidden="true" />
      <span>{{ item.label }}</span>
      <strong>{{ item.value }}</strong>
    </button>
  </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { AlertTriangle, CheckCircle2, CircleDashed, Clock3, ListChecks, Siren } from "lucide-vue-next";
import type { TaskSummary } from "@/types/task";

const props = defineProps<{ summary: TaskSummary }>();
defineEmits<{ select: [key: string] }>();

const items = computed(() => [
  { key: "total", label: "Total", value: props.summary.total, icon: ListChecks, tone: "neutral" },
  { key: "pending", label: "Pending", value: props.summary.pending, icon: CircleDashed, tone: "amber" },
  { key: "in_progress", label: "In progress", value: props.summary.in_progress, icon: Clock3, tone: "blue" },
  { key: "completed", label: "Completed", value: props.summary.completed, icon: CheckCircle2, tone: "green" },
  { key: "overdue", label: "Overdue", value: props.summary.overdue, icon: AlertTriangle, tone: "red" },
  { key: "urgent", label: "Urgent", value: props.summary.urgent, icon: Siren, tone: "violet" },
]);
</script>

<style scoped>
.kpi-strip { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); border:1px solid #e2e8f0; background:#fff; }
.kpi { min-width:0; display:grid; grid-template-columns:18px 1fr auto; align-items:center; gap:8px; padding:10px 12px; border-right:1px solid #e2e8f0; text-align:left; color:#475569; background:transparent; }
.kpi:last-child { border-right:0; }
.kpi:hover { background:#f8fafc; }
.kpi:focus-visible { outline:2px solid #2563eb; outline-offset:-2px; }
.kpi svg { width:16px; height:16px; }
.kpi span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px; font-weight:700; }
.kpi strong { color:#0f172a; font-size:18px; }
.kpi.amber svg { color:#d97706; }.kpi.blue svg { color:#2563eb; }.kpi.green svg { color:#059669; }.kpi.red svg { color:#dc2626; }.kpi.violet svg { color:#7c3aed; }
:global(.dark) .kpi-strip { border-color:#334155; background:#0f172a; }
:global(.dark) .kpi { border-color:#334155; color:#cbd5e1; }
:global(.dark) .kpi:hover { background:#1e293b; }
:global(.dark) .kpi strong { color:#f8fafc; }
@media (max-width:900px) { .kpi-strip { grid-template-columns:repeat(3,minmax(0,1fr)); }.kpi:nth-child(3) { border-right:0; }.kpi:nth-child(-n+3) { border-bottom:1px solid #e2e8f0; } }
@media (max-width:520px) { .kpi { grid-template-columns:16px 1fr; padding:9px; }.kpi strong { grid-column:2; font-size:16px; } }
</style>
