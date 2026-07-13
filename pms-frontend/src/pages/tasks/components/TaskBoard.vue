<template>
  <section class="board" aria-label="Task board">
    <article v-for="lane in lanes" :key="lane.status" class="lane" @dragover.prevent @drop="drop(lane.status)">
      <header><span :class="['dot', lane.status]" aria-hidden="true"></span><h2>{{ lane.label }}</h2><strong>{{ board[lane.status].meta.total }}</strong><button v-if="canCreate" type="button" :title="`Add ${lane.label} task`" :aria-label="`Add ${lane.label} task`" @click="$emit('create', lane.status)"><Plus /></button></header>
      <div class="lane-body">
        <button v-for="task in board[lane.status].data" :key="task.id" type="button" class="task-card" :draggable="canUpdate" @dragstart="canUpdate && (dragged = task)" @dragend="dragged = null" @click="$emit('open', task)">
          <span class="project">{{ task.project?.project_code || 'TASK' }}</span>
          <strong>{{ task.title }}</strong>
          <span v-if="task.description" class="description">{{ task.description }}</span>
          <span class="meta"><span :class="['priority', task.priority || 'none']">{{ label(task.priority) }}</span><span :class="{ overdue: task.is_overdue }"><CalendarDays />{{ date(task.due_date) }}</span></span>
          <span v-if="task.assigned_to" class="assignee"><UserRound />{{ task.assigned_to.name || task.assigned_to.email }}</span>
        </button>
        <div v-if="!board[lane.status].data.length" class="lane-empty"><Inbox />No tasks in this lane</div>
      </div>
      <footer v-if="board[lane.status].meta.last_page > 1">
        <button type="button" :disabled="board[lane.status].meta.current_page <= 1" :aria-label="`Previous ${lane.label} page`" @click="$emit('page', lane.status, board[lane.status].meta.current_page - 1)"><ChevronLeft /></button>
        <span>{{ board[lane.status].meta.current_page }} / {{ board[lane.status].meta.last_page }}</span>
        <button type="button" :disabled="board[lane.status].meta.current_page >= board[lane.status].meta.last_page" :aria-label="`Next ${lane.label} page`" @click="$emit('page', lane.status, board[lane.status].meta.current_page + 1)"><ChevronRight /></button>
      </footer>
    </article>
  </section>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { CalendarDays, ChevronLeft, ChevronRight, Inbox, Plus, UserRound } from "lucide-vue-next";
import type { TaskBoard, TaskItem, TaskStatus } from "@/types/task";
defineProps<{ board: TaskBoard; canCreate: boolean; canUpdate: boolean }>();
const emit = defineEmits<{ open: [task: TaskItem]; create: [status: TaskStatus]; move: [task: TaskItem, status: TaskStatus]; page: [status: TaskStatus, page: number] }>();
const lanes: { status: TaskStatus; label: string }[] = [{ status: "pending", label: "Pending" }, { status: "in_progress", label: "In progress" }, { status: "completed", label: "Completed" }, { status: "cancelled", label: "Cancelled" }];
const dragged = ref<TaskItem | null>(null);
const drop = (status: TaskStatus) => { if (dragged.value && dragged.value.status !== status) emit("move", dragged.value, status); dragged.value = null; };
const label = (value?: string | null) => value ? value.replaceAll("_", " ") : "Unclassified";
const date = (value?: string | null) => value ? new Date(`${value}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) : "No due date";
</script>

<style scoped>
.board { display:grid; grid-template-columns:repeat(4,minmax(270px,1fr)); gap:12px; min-width:1180px; align-items:start; }.lane { display:grid; grid-template-rows:auto minmax(180px,1fr) auto; max-height:calc(100vh - 300px); border:1px solid #dbe3ec; background:#f8fafc; }.lane header { height:44px; display:flex; align-items:center; gap:8px; padding:0 10px; border-bottom:1px solid #dbe3ec; }.lane h2 { flex:1; color:#1e293b; font-size:13px; font-weight:800; }.lane header strong { min-width:24px; padding:2px 6px; border-radius:10px; background:#e2e8f0; color:#475569; font-size:11px; text-align:center; }.lane header button,.lane footer button { width:28px; height:28px; display:grid; place-items:center; color:#475569; }.lane header button:hover,.lane footer button:hover:not(:disabled){background:#e2e8f0}.lane svg { width:15px; }.dot { width:8px; height:8px; border-radius:50%; background:#94a3b8; }.dot.in_progress { background:#2563eb; }.dot.completed { background:#059669; }.dot.cancelled { background:#dc2626; }.lane-body { overflow-y:auto; display:grid; align-content:start; gap:8px; padding:8px; }.task-card { width:100%; display:grid; gap:7px; padding:10px; border:1px solid #dbe3ec; border-radius:6px; background:#fff; text-align:left; box-shadow:0 1px 2px rgb(15 23 42 / .05); }.task-card:hover { border-color:#93c5fd; }.task-card:focus-visible { outline:2px solid #2563eb; outline-offset:1px; }.task-card>.project { color:#2563eb; font-size:10px; font-weight:800; }.task-card>strong { color:#0f172a; font-size:13px; line-height:1.35; }.description { display:-webkit-box; overflow:hidden; color:#64748b; font-size:11px; line-height:1.4; -webkit-box-orient:vertical; -webkit-line-clamp:2; }.meta,.assignee { display:flex; align-items:center; gap:8px; color:#64748b; font-size:10px; }.meta { justify-content:space-between; }.meta span { display:flex; align-items:center; gap:4px; }.meta svg,.assignee svg { width:12px; height:12px; }.meta .overdue { color:#dc2626; font-weight:700; }.priority { text-transform:capitalize; font-weight:800; }.priority.critical,.priority.urgent { color:#dc2626; }.priority.high { color:#d97706; }.priority.low { color:#64748b; }.assignee { overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }.lane-empty { display:grid; justify-items:center; gap:8px; padding:32px 8px; color:#94a3b8; font-size:12px; }.lane-empty svg { width:22px; }.lane footer { display:flex; align-items:center; justify-content:center; gap:9px; height:38px; border-top:1px solid #dbe3ec; color:#64748b; font-size:11px; }.lane footer button:disabled { opacity:.35; }
:global(.dark) .lane { border-color:#334155; background:#111827; }:global(.dark) .lane header,:global(.dark) .lane footer { border-color:#334155; }:global(.dark) .lane h2,:global(.dark) .task-card>strong { color:#f1f5f9; }:global(.dark) .lane header strong { background:#334155;color:#cbd5e1 }:global(.dark) .task-card { border-color:#334155;background:#1e293b }:global(.dark) .task-card:hover { border-color:#3b82f6; }
</style>
