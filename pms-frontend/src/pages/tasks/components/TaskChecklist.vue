<template>
  <section class="checklist" aria-label="Implementation task checklist">
    <details v-for="group in groups" :key="group.key" open class="group">
      <summary>
        <ChevronRight aria-hidden="true" />
        <span><small>{{ group.ordinal }}</small><strong>{{ group.label }}</strong></span>
        <span class="progress"><span>{{ group.done }}/{{ group.tasks.length }}</span><span class="track"><i :style="{ width: `${group.percent}%` }"></i></span><b>{{ group.percent }}%</b></span>
      </summary>
      <div class="rows">
        <div v-for="task in group.tasks" :key="task.id" class="row">
          <input
            class="completion-checkbox"
            type="checkbox"
            :checked="task.status === 'completed'"
            :disabled="!canUpdate || busyTaskId === task.id || Boolean(task.subtasks?.length)"
            :title="task.subtasks?.length ? 'Completion is calculated from checklist items' : undefined"
            :aria-label="`${task.status === 'completed' ? 'Reopen' : 'Complete'} ${task.title}`"
            @click.stop
            @change="$emit('completion', task, ($event.target as HTMLInputElement).checked)"
          />
          <button type="button" class="title" @click="$emit('open', task)">
            <strong>{{ task.title }}</strong>
            <small>{{ task.project?.project_code || 'TASK' }}<template v-if="task.assigned_to"> · {{ task.assigned_to.name || task.assigned_to.email }}</template></small>
          </button>
          <span :class="['priority', task.priority || 'none']">{{ task.priority || 'unset' }}</span>
          <span :class="['due', { overdue: task.is_overdue }]"><CalendarDays />{{ date(task.due_date) }}</span>
          <span class="status">{{ label(task.status) }}</span>
          <button type="button" class="open-task" :aria-label="`Open ${task.title}`" @click="$emit('open', task)"><ChevronRight aria-hidden="true" /></button>
        </div>
      </div>
    </details>

    <div v-if="!groups.length" class="empty"><ListChecks /><strong>No implementation tasks found</strong><span>This workspace becomes available after a project starts implementation.</span></div>
    <nav v-if="pagination.last_page > 1" class="pagination" aria-label="Task pages">
      <button type="button" :disabled="pagination.current_page <= 1" @click="$emit('page', pagination.current_page - 1)"><ChevronLeft />Previous</button>
      <span>Page {{ pagination.current_page }} of {{ pagination.last_page }} · {{ pagination.total }} tasks</span>
      <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="$emit('page', pagination.current_page + 1)">Next<ChevronRight /></button>
    </nav>
  </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { CalendarDays, ChevronLeft, ChevronRight, ListChecks } from "lucide-vue-next";
import type { PaginationMeta } from "@/types/paginationMeta";
import type { TaskItem } from "@/types/task";

const props = defineProps<{ tasks: TaskItem[]; pagination: PaginationMeta; grouped: boolean; canUpdate: boolean; busyTaskId?: number | null }>();
defineEmits<{ open: [task: TaskItem]; page: [page: number]; completion: [task: TaskItem, completed: boolean] }>();

const groups = computed(() => {
  const workstreams = props.grouped
    ? [...new Set(props.tasks.map((task) => task.workstream || "General delivery"))]
    : ["all"];

  return workstreams.map((key, index) => {
    const tasks = props.grouped
      ? props.tasks.filter((task) => (task.workstream || "General delivery") === key)
      : props.tasks;
    const done = tasks.filter((task) => task.status === "completed").length;
    return {
      key,
      ordinal: props.grouped ? String(index + 1).padStart(2, "0") : "WORK",
      label: props.grouped ? key : "Implementation task list",
      tasks,
      done,
      percent: tasks.length ? Math.round(done / tasks.length * 100) : 0,
    };
  }).filter((group) => group.tasks.length);
});

const label = (value: string) => value.replaceAll("_", " ");
const date = (value?: string | null) => value ? new Date(`${value}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "No due date";
</script>

<style scoped>
.checklist{border:1px solid #e2e8f0;background:#fff}.group+.group{border-top:1px solid #e2e8f0}.group summary{min-height:52px;display:grid;grid-template-columns:18px minmax(180px,1fr) minmax(190px,320px);align-items:center;gap:8px;padding:8px 12px;cursor:pointer;list-style:none}.group summary::-webkit-details-marker{display:none}.group summary>svg{width:15px;transition:transform .15s}.group[open] summary>svg{transform:rotate(90deg)}.group summary>span:nth-child(2){display:grid}.group summary small{color:#94a3b8;font-size:9px;font-weight:900}.group summary strong{color:#0f172a;font-size:13px}.progress{display:grid;grid-template-columns:auto 1fr 36px;align-items:center;gap:8px;color:#64748b;font-size:11px}.progress b{color:#334155;text-align:right}.track{height:5px;overflow:hidden;background:#e2e8f0}.track i{display:block;height:100%;background:#10b981}.rows{border-top:1px solid #e2e8f0}.row{width:100%;min-height:47px;display:grid;grid-template-columns:20px minmax(220px,1fr) 72px 120px 96px 28px;align-items:center;gap:9px;padding:6px 12px;border-bottom:1px solid #f1f5f9;text-align:left}.row:last-child{border-bottom:0}.row:hover{background:#f8fafc}.completion-checkbox{width:17px;height:17px;accent-color:#059669;cursor:pointer}.completion-checkbox:disabled{cursor:not-allowed;opacity:.45}.title{min-width:0;display:grid;gap:2px;text-align:left}.title:focus-visible,.open-task:focus-visible{outline:2px solid #2563eb;outline-offset:2px}.title strong,.title small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.title strong{color:#1e293b;font-size:12px}.title small{color:#64748b;font-size:10px}.priority,.status{text-transform:capitalize;font-size:10px;font-weight:800}.priority.critical,.priority.urgent{color:#dc2626}.priority.high{color:#d97706}.due{display:flex;align-items:center;gap:5px;color:#64748b;font-size:10px}.due svg{width:12px}.due.overdue{color:#dc2626;font-weight:700}.status{color:#475569}.open-task{width:28px;height:28px;display:grid;place-items:center}.open-task svg{width:14px;color:#94a3b8}.empty{display:grid;justify-items:center;gap:6px;padding:50px 16px;color:#64748b;text-align:center}.empty svg{width:28px}.empty strong{color:#334155}.empty span{font-size:12px}.pagination{display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-top:1px solid #e2e8f0;color:#64748b;font-size:11px}.pagination button{display:flex;align-items:center;gap:5px;min-height:32px;padding:0 9px;border:1px solid #cbd5e1;background:#fff;font-weight:700}.pagination button svg{width:13px}.pagination button:disabled{opacity:.4}
:global(.dark) .checklist{border-color:#334155;background:#0f172a}:global(.dark) .group+.group,:global(.dark) .rows,:global(.dark) .row,:global(.dark) .pagination{border-color:#334155}:global(.dark) .group summary strong,:global(.dark) .title strong,:global(.dark) .empty strong{color:#f8fafc}:global(.dark) .row:hover{background:#1e293b}:global(.dark) .pagination button{border-color:#475569;background:#1e293b;color:#e2e8f0}
@media(max-width:760px){.group summary{grid-template-columns:18px 1fr}.progress{grid-column:2}.row{grid-template-columns:20px minmax(0,1fr) 28px}.priority,.due,.status{display:none}.pagination span{font-size:10px;text-align:center}}
</style>
