<template>
  <div class="gantt-container" :class="{ 'is-dark': isDarkMode }">
    <div class="gantt-toolbar">
      <div class="gantt-view-modes">
        <button
          class="mode-btn icon-btn"
          :class="{ disabled: currentMode === 'Day' }"
          @click="zoomIn"
          title="Zoom In"
        >
          <ZoomInIcon class="w-4 h-4" />
        </button>
        <span class="mode-label">{{ currentMode }} View</span>
        <button
          class="mode-btn icon-btn"
          :class="{ disabled: currentMode === 'Month' }"
          @click="zoomOut"
          title="Zoom Out"
        >
          <ZoomOutIcon class="w-4 h-4" />
        </button>
      </div>
      <div class="gantt-legend">
        <span class="legend-item"><span class="legend-dot pending"></span> Pending</span>
        <span class="legend-item"><span class="legend-dot in-progress"></span> In Progress</span>
        <span class="legend-item"><span class="legend-dot completed"></span> Completed</span>
        <span class="legend-item"><span class="legend-dot cancelled"></span> Cancelled</span>
      </div>
    </div>
    <div v-if="ganttTasks.length === 0" class="gantt-empty">
      <p class="text-sm text-slate-500 dark:text-slate-400">No tasks with dates to display on the Gantt chart. Set start and due dates on your tasks to see them here.</p>
    </div>
    <svg ref="ganttSvg" class="gantt-svg"></svg>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, nextTick, computed } from "vue";
import Gantt from "frappe-gantt";
import { ZoomIn as ZoomInIcon, ZoomOut as ZoomOutIcon } from "lucide-vue-next";
import type { TaskItem } from "@/types/task";
import { useLayoutStore } from "@/store/layout";
import { SITE_MODE } from "@/app/const";

const props = defineProps<{
  tasks: TaskItem[];
}>();

const emit = defineEmits<{
  (e: "dateChange", taskId: number, start: string, end: string): void;
  (e: "taskClick", task: TaskItem): void;
}>();

const layoutStore = useLayoutStore();
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);

const ganttSvg = ref<SVGSVGElement | null>(null);
const currentMode = ref("Week");
const viewModes = ["Day", "Week", "Month"];
let ganttInstance: any = null;

const ganttTasks = computed(() =>
  props.tasks
    .filter((t) => t.start_date || t.due_date)
    .map((t) => ({
      id: String(t.id),
      name: t.title,
      start: t.start_date || t.due_date || new Date().toISOString().split("T")[0],
      end: t.due_date || t.start_date || new Date().toISOString().split("T")[0],
      progress: t.progress_percentage || 0,
      custom_class: `bar-${t.status}`,
      _task: t,
    }))
);

const renderGantt = () => {
  if (!ganttSvg.value || ganttTasks.value.length === 0) return;

  // Clear previous
  ganttSvg.value.innerHTML = "";

  ganttInstance = new Gantt(ganttSvg.value, ganttTasks.value, {
    view_mode: currentMode.value,
    date_format: "YYYY-MM-DD",
    custom_popup_html: (task: any) => {
      const original = props.tasks.find((t) => String(t.id) === task.id);
      const status = original?.status || "pending";
      const priority = original?.priority || "—";
      const assignee = original?.assigned_to?.name || "Unassigned";
      return `
        <div class="gantt-popup">
          <h5 class="gantt-popup-title">${task.name}</h5>
          <p class="gantt-popup-info">Status: <strong>${status}</strong></p>
          <p class="gantt-popup-info">Priority: <strong>${priority}</strong></p>
          <p class="gantt-popup-info">Assignee: <strong>${assignee}</strong></p>
          <p class="gantt-popup-info">${task.start} → ${task.end}</p>
          <p class="gantt-popup-info">Progress: ${task.progress}%</p>
        </div>
      `;
    },
    on_click: (task: any) => {
      const original = props.tasks.find((t) => String(t.id) === task.id);
      if (original) emit("taskClick", original);
    },
    on_date_change: (task: any, start: Date, end: Date) => {
      const startStr = start.toISOString().split("T")[0];
      const endStr = end.toISOString().split("T")[0];
      emit("dateChange", Number(task.id), startStr, endStr);
    },
  });
};

const changeViewMode = (mode: string) => {
  currentMode.value = mode;
  if (ganttInstance) {
    ganttInstance.change_view_mode(mode);
  }
};

const zoomIn = () => {
  const currentIndex = viewModes.indexOf(currentMode.value);
  if (currentIndex > 0) {
    changeViewMode(viewModes[currentIndex - 1]);
  }
};

const zoomOut = () => {
  const currentIndex = viewModes.indexOf(currentMode.value);
  if (currentIndex < viewModes.length - 1) {
    changeViewMode(viewModes[currentIndex + 1]);
  }
};

watch(
  () => props.tasks,
  async () => {
    await nextTick();
    renderGantt();
  },
  { deep: true }
);

onMounted(async () => {
  await nextTick();
  renderGantt();
});
</script>

<style scoped>
.gantt-container {
  width: 100%;
  overflow-x: auto;
  border-radius: 1rem;
  --gantt-bg: rgba(255, 255, 255, 0.85);
  --gantt-border: rgba(226, 232, 240, 0.6);
  --gantt-grid-header: #f8fafc;
  --gantt-grid-header-stroke: #e2e8f0;
  --gantt-grid-row: #ffffff;
  --gantt-grid-row-alt: #f8fafc;
  --gantt-grid-line: #e2e8f0;
  --gantt-text-upper: #64748b;
  --gantt-text-lower: #334155;
  --gantt-bar-label: #ffffff;
  --gantt-bar-label-big: #334155;
  --gantt-popup-bg: rgba(255, 255, 255, 0.95);
  --gantt-popup-border: #e2e8f0;
  --gantt-popup-text: #334155;
  --gantt-popup-title: #0f172a;
  --gantt-today: #dbeafe;

  border: 1px solid var(--gantt-border);
  background: var(--gantt-bg);
  backdrop-filter: blur(16px);
  box-shadow: 0 4px 24px rgba(0,0,0,0.06);
}

:global(.dark) .gantt-container, .gantt-container.is-dark {
  --gantt-bg: rgba(15, 23, 42, 0.85);
  --gantt-border: rgba(255, 255, 255, 0.08);
  --gantt-grid-header: #0f172a;
  --gantt-grid-header-stroke: #1e293b;
  --gantt-grid-row: #0f172a;
  --gantt-grid-row-alt: #111827;
  --gantt-grid-line: #1e293b;
  --gantt-text-upper: #64748b;
  --gantt-text-lower: #94a3b8;
  --gantt-bar-label: #ffffff;
  --gantt-bar-label-big: #cbd5e1;
  --gantt-popup-bg: rgba(15, 23, 42, 0.95);
  --gantt-popup-border: rgba(255,255,255,0.1);
  --gantt-popup-text: #e2e8f0;
  --gantt-popup-title: #f1f5f9;
  --gantt-today: #1e3a5f;
  box-shadow: 0 4px 24px rgba(0,0,0,0.3);
}

.gantt-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.875rem 1.5rem;
  border-bottom: 1px solid rgba(226, 232, 240, 0.5);
  flex-wrap: wrap;
  gap: 0.75rem;
}
:global(.dark) .gantt-toolbar, .is-dark .gantt-toolbar {
  border-bottom-color: rgba(255, 255, 255, 0.08);
}

.gantt-view-modes {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(241, 245, 249, 0.9);
  border-radius: 0.5rem;
  padding: 0.25rem 0.5rem;
}
:global(.dark) .gantt-view-modes, .is-dark .gantt-view-modes {
  background: rgba(30, 41, 59, 0.7);
}

.mode-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
  min-width: 4rem;
  text-align: center;
}
:global(.dark) .mode-label, .is-dark .mode-label {
  color: #e2e8f0;
}

.mode-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.35rem 0.5rem;
  border: none;
  background: transparent;
  border-radius: 0.375rem;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
}
:global(.dark) .mode-btn, .is-dark .mode-btn {
  color: #94a3b8;
}
.mode-btn:hover:not(.disabled) {
  background: rgba(59, 130, 246, 0.12);
  color: #3b82f6;
}
:global(.dark) .mode-btn:hover:not(.disabled), .is-dark .mode-btn:hover:not(.disabled) {
  background: rgba(59, 130, 246, 0.2);
  color: #60a5fa;
}
.mode-btn.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.gantt-legend {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
  letter-spacing: 0.01em;
}
:global(.dark) .legend-item, .is-dark .legend-item {
  color: #94a3b8;
}
.legend-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  box-shadow: 0 0 0 2px rgba(255,255,255,0.8);
}
:global(.dark) .legend-dot, .is-dark .legend-dot {
  box-shadow: 0 0 0 2px rgba(30,41,59,0.8);
}
.legend-dot.pending { background: #94a3b8; }
.legend-dot.in-progress { background: #3b82f6; }
.legend-dot.completed { background: #10b981; }
.legend-dot.cancelled { background: #ef4444; }

.gantt-empty {
  padding: 4rem 2rem;
  text-align: center;
}

.gantt-svg {
  min-width: 100%;
  min-height: 350px;
}

/* ============================================
   FRAPPE-GANTT INTERNAL OVERRIDES — LIGHT MODE
   ============================================ */

/* Grid background and rows */
:global(.gantt .grid-background) {
  fill: var(--gantt-grid-row) !important;
}
:global(.gantt .grid-header) {
  fill: var(--gantt-grid-header);
  stroke: var(--gantt-grid-header-stroke);
  stroke-width: 1;
}
:global(.gantt .grid-row) {
  fill: var(--gantt-grid-row);
}
:global(.gantt .grid-row:nth-child(even)) {
  fill: var(--gantt-grid-row-alt);
}
:global(.gantt .row-line) {
  stroke: var(--gantt-grid-line);
}
:global(.gantt .tick) {
  stroke: var(--gantt-grid-line);
  stroke-width: 0.2;
}
:global(.gantt .tick.thick) {
  stroke-width: 0.4;
}

/* Today highlight */
:global(.gantt .today-highlight) {
  fill: var(--gantt-today);
  opacity: 0.5;
}

/* Text labels */
:global(.gantt .upper-text) {
  fill: var(--gantt-text-upper);
  font-size: 11px;
  font-weight: 600;
}
:global(.gantt .lower-text) {
  fill: var(--gantt-text-lower);
  font-size: 11px;
  font-weight: 500;
}

/* Bars — smoother */
:global(.gantt .bar) {
  rx: 4;
  ry: 4;
  stroke-width: 0;
  transition: fill 0.2s ease, stroke-width 0.2s ease;
}
:global(.gantt .bar-progress) {
  rx: 4;
  ry: 4;
}
:global(.gantt .bar-label) {
  fill: #fff;
  font-size: 11px;
  font-weight: 600;
}
:global(.gantt .bar-label.big) {
  fill: #334155;
  font-weight: 600;
}

/* Handles */
:global(.gantt .handle) {
  fill: #94a3b8;
  cursor: ew-resize;
  opacity: 0;
  transition: opacity 0.2s ease;
}
:global(.gantt .bar-wrapper:hover .handle) {
  visibility: visible;
  opacity: 0.8;
}

/* Popup (tooltip) */
:global(.gantt-container .popup-wrapper) {
  background: var(--gantt-popup-bg) !important;
  backdrop-filter: blur(16px);
  border: 1px solid var(--gantt-popup-border);
  border-radius: 0.75rem !important;
  box-shadow: 0 12px 40px rgba(0,0,0,0.12);
  color: var(--gantt-popup-text) !important;
  overflow: hidden;
}
:global(.gantt-container .popup-wrapper .title) {
  border-bottom: 2px solid #3b82f6 !important;
  padding: 12px 14px !important;
  font-weight: 700;
  font-size: 13px;
  color: var(--gantt-popup-title) !important;
}
:global(.gantt-container .popup-wrapper .subtitle) {
  padding: 10px 14px !important;
  color: #475569 !important;
  font-size: 12px;
}
:global(.gantt-container .popup-wrapper .pointer) {
  border-top-color: var(--gantt-popup-bg) !important;
}

/* Custom popup */
:global(.gantt-popup) {
  padding: 0.75rem 1rem;
  min-width: 220px;
}
:global(.gantt-popup-title) {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--gantt-popup-title);
  margin: 0 0 0.5rem;
}
:global(.gantt-popup-info) {
  font-size: 0.75rem;
  color: var(--gantt-popup-text);
  margin: 0.2rem 0;
  line-height: 1.5;
}

/* ============================================
   BAR STATUS COLORS (both modes)
   ============================================ */

/* Pending — Slate */
:global(.bar-pending .bar) { fill: #cbd5e1; }
:global(.bar-pending .bar-progress) { fill: #94a3b8; }
:global(.dark .bar-pending .bar), .is-dark :global(.bar-pending .bar) { fill: #334155; }
:global(.dark .bar-pending .bar-progress), .is-dark :global(.bar-pending .bar-progress) { fill: #64748b; }

/* In Progress — Blue */
:global(.bar-in_progress .bar) { fill: #93c5fd; }
:global(.bar-in_progress .bar-progress) { fill: #3b82f6; }
:global(.dark .bar-in_progress .bar), .is-dark :global(.bar-in_progress .bar) { fill: #1e3a5f; }
:global(.dark .bar-in_progress .bar-progress), .is-dark :global(.bar-in_progress .bar-progress) { fill: #3b82f6; }

/* Completed — Green */
:global(.bar-completed .bar) { fill: #6ee7b7; }
:global(.bar-completed .bar-progress) { fill: #10b981; }
:global(.dark .bar-completed .bar), .is-dark :global(.bar-completed .bar) { fill: #064e3b; }
:global(.dark .bar-completed .bar-progress), .is-dark :global(.bar-completed .bar-progress) { fill: #10b981; }

/* Cancelled — Red */
:global(.bar-cancelled .bar) { fill: #fca5a5; }
:global(.bar-cancelled .bar-progress) { fill: #ef4444; }
:global(.dark .bar-cancelled .bar), .is-dark :global(.bar-cancelled .bar) { fill: #450a0a; }
:global(.dark .bar-cancelled .bar-progress), .is-dark :global(.bar-cancelled .bar-progress) { fill: #dc2626; }

/* Hover states for dark mode bars */
:global(.dark .gantt .bar-wrapper:hover .bar), .is-dark :global(.gantt .bar-wrapper:hover .bar) {
  filter: brightness(1.3);
}
:global(.dark .gantt .bar-wrapper:hover .bar-progress), .is-dark :global(.gantt .bar-wrapper:hover .bar-progress) {
  filter: brightness(1.2);
}
</style>
