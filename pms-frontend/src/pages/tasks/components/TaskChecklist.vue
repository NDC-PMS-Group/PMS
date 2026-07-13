<template>
  <section class="checklist" aria-label="Task checklist">
    <details v-for="group in groups" :key="group.key" open class="group">
      <summary><ChevronRight aria-hidden="true" /><span><small>{{ group.ordinal }}</small><strong>{{ group.label }}</strong></span><span class="progress"><span>{{ group.done }}/{{ group.tasks.length }}</span><span class="track"><i :style="{ width: `${group.percent}%` }"></i></span><b>{{ group.percent }}%</b></span></summary>
      <div class="rows">
        <button v-for="task in group.tasks" :key="task.id" type="button" class="row" @click="$emit('open', task)">
          <span :class="['status-mark', task.status]"><Check v-if="task.status === 'completed'" /></span>
          <span class="title"><strong>{{ task.title }}</strong><small>{{ task.project?.project_code || 'TASK' }}<template v-if="task.assigned_to"> · {{ task.assigned_to.name || task.assigned_to.email }}</template></small></span>
          <span :class="['priority', task.priority || 'none']">{{ task.priority || 'unset' }}</span>
          <span :class="['due', { overdue: task.is_overdue }]"><CalendarDays />{{ date(task.due_date) }}</span>
          <span class="status">{{ label(task.status) }}</span>
          <ChevronRight class="open-icon" aria-hidden="true" />
        </button>
      </div>
    </details>
    <div v-if="!groups.length" class="empty"><ListChecks /><strong>No tasks found</strong><span>Try clearing filters or create the first task for this project.</span></div>
    <nav v-if="pagination.last_page > 1" class="pagination" aria-label="Task pages"><button type="button" :disabled="pagination.current_page <= 1" @click="$emit('page', pagination.current_page - 1)"><ChevronLeft />Previous</button><span>Page {{ pagination.current_page }} of {{ pagination.last_page }} · {{ pagination.total }} tasks</span><button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="$emit('page', pagination.current_page + 1)">Next<ChevronRight /></button></nav>
  </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { CalendarDays, Check, ChevronLeft, ChevronRight, ListChecks } from "lucide-vue-next";
import { SOI_SECTION_ORDER, formatSoiSectionLabel, resolveSoiTaskSection } from "@/utils/soiWorkflow";
import type { PaginationMeta } from "@/types/paginationMeta";
import type { TaskItem } from "@/types/task";
const props = defineProps<{ tasks: TaskItem[]; pagination: PaginationMeta; grouped: boolean }>();
defineEmits<{ open: [task: TaskItem]; page: [page: number] }>();
const groups = computed(() => {
  const source = props.grouped ? SOI_SECTION_ORDER : ["all"];
  return source.map((key, index) => {
    const tasks = props.grouped ? props.tasks.filter((task) => resolveSoiTaskSection(task) === key) : props.tasks;
    const done = tasks.filter((task) => task.status === "completed").length;
    return { key, ordinal: props.grouped ? String(index + 1).padStart(2, "0") : "WORK", label: props.grouped ? formatSoiSectionLabel(key) : "Task list", tasks, done, percent: tasks.length ? Math.round(done / tasks.length * 100) : 0 };
  }).filter((group) => group.tasks.length);
});
const label = (value: string) => value.replaceAll("_", " ");
const date = (value?: string | null) => value ? new Date(`${value}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "No due date";
</script>

<style scoped>
.checklist { border:1px solid #e2e8f0; background:#fff; }.group+.group { border-top:1px solid #e2e8f0; }.group summary { min-height:52px; display:grid; grid-template-columns:18px minmax(180px,1fr) minmax(190px,320px); align-items:center; gap:8px; padding:8px 12px; cursor:pointer; list-style:none; }.group summary::-webkit-details-marker { display:none; }.group summary>svg { width:15px; transition:transform .15s; }.group[open] summary>svg { transform:rotate(90deg); }.group summary>span:nth-child(2) { display:grid; }.group summary small { color:#94a3b8; font-size:9px; font-weight:900; }.group summary strong { color:#0f172a; font-size:13px; }.progress { display:grid; grid-template-columns:auto 1fr 36px; align-items:center; gap:8px; color:#64748b; font-size:11px; }.progress b { color:#334155; text-align:right; }.track { height:5px; overflow:hidden; background:#e2e8f0; }.track i { display:block; height:100%; background:#10b981; }.rows { border-top:1px solid #e2e8f0; }.row { width:100%; min-height:47px; display:grid; grid-template-columns:20px minmax(220px,1fr) 72px 120px 96px 18px; align-items:center; gap:9px; padding:6px 12px; border-bottom:1px solid #f1f5f9; text-align:left; }.row:last-child { border-bottom:0; }.row:hover { background:#f8fafc; }.row:focus-visible { outline:2px solid #2563eb; outline-offset:-2px; }.status-mark { width:16px;height:16px;display:grid;place-items:center;border:1.5px solid #94a3b8;border-radius:3px }.status-mark.completed { border-color:#059669;background:#059669;color:#fff }.status-mark.in_progress { border-color:#2563eb }.status-mark svg { width:11px }.title { min-width:0;display:grid;gap:2px }.title strong { overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1e293b;font-size:12px }.title small { overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;font-size:10px }.priority,.status { text-transform:capitalize;font-size:10px;font-weight:800 }.priority.critical,.priority.urgent { color:#dc2626 }.priority.high { color:#d97706 }.due { display:flex;align-items:center;gap:5px;color:#64748b;font-size:10px }.due svg { width:12px }.due.overdue { color:#dc2626;font-weight:700 }.status { color:#475569 }.open-icon { width:14px;color:#94a3b8 }.empty { display:grid;justify-items:center;gap:6px;padding:50px 16px;color:#64748b;text-align:center }.empty svg { width:28px }.empty strong { color:#334155 }.empty span { font-size:12px }.pagination { display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-top:1px solid #e2e8f0;color:#64748b;font-size:11px }.pagination button { display:flex;align-items:center;gap:5px;min-height:32px;padding:0 9px;border:1px solid #cbd5e1;background:#f8fafc;color:#334155;font-weight:700 }.pagination button:disabled { opacity:.4 }.pagination svg { width:14px }
:global(.dark) .checklist { border-color:#334155;background:#0f172a }:global(.dark) .group+.group,:global(.dark) .rows,:global(.dark) .pagination { border-color:#334155 }:global(.dark) .group summary strong,:global(.dark) .title strong { color:#f1f5f9 }:global(.dark) .row { border-color:#1e293b }:global(.dark) .row:hover { background:#1e293b }:global(.dark) .progress b,:global(.dark) .status { color:#cbd5e1 }:global(.dark) .track { background:#334155 }
@media(max-width:760px){.group summary{grid-template-columns:18px 1fr}.progress{grid-column:2}.row{grid-template-columns:20px minmax(0,1fr) 18px}.priority,.due,.status{display:none}.pagination span{display:none}}
</style>
