<template>
  <main class="tasks-workspace">
    <header class="workspace-head">
      <div class="heading"><span>{{ isProjectRoute ? 'Project workflow workspace' : 'Project portfolio' }}</span><h1>{{ pageTitle }}</h1><p>{{ isProjectRoute ? 'Tasks from SOI intake through approvals, implementation, monitoring, and closeout' : 'Authorized project work across every SOI and lifecycle phase' }}</p></div>
      <div class="head-actions">
        <div class="view-switch" aria-label="Task view">
          <button type="button" :aria-pressed="effectiveView === 'list'" :class="{ active: effectiveView === 'list' }" @click="setView('list')"><ListTodo />Checklist</button>
          <button type="button" :aria-pressed="effectiveView === 'board'" :class="{ active: effectiveView === 'board' }" :disabled="!isBoardAvailable" title="Board is available on screens 1280px and wider" @click="setView('board')"><KanbanSquare />Board</button>
        </div>
        <button v-if="permissions.can_create" type="button" class="create" @click="openCreate()"><Plus />New task</button>
      </div>
    </header>

    <TaskKpis :summary="summary" @select="applyKpi" />
    <TaskFiltersPanel :filters="filters" :facets="facets" :open="filtersOpen" :project-locked="isProjectRoute" @patch="patchFilters" @apply="reload" @clear="clearFilters" @toggle="filtersOpen = !filtersOpen" />

    <section v-if="loading" class="loading-state" aria-live="polite"><Loader2 /><span>Loading task workspace...</span><i v-for="index in 6" :key="index"></i></section>
    <section v-else-if="error" class="error-state" role="alert"><AlertCircle /><div><strong>Tasks could not be loaded</strong><span>{{ error }}</span></div><button type="button" @click="reload"><RefreshCw />Try again</button></section>
    <template v-else>
      <div v-if="effectiveView === 'board'" class="board-scroll"><TaskBoard :board="board" :can-create="permissions.can_create" :can-update="permissions.can_update" @open="openTask" @create="openCreate" @move="moveTask" @page="changeLanePage" /></div>
      <TaskChecklist v-else :tasks="tasks" :pagination="pagination" :grouped="isProjectRoute" :can-update="permissions.can_update" :busy-task-id="completingTaskId" @open="openTask" @page="changePage" @completion="toggleCompletion" />
    </template>

    <TaskDrawer v-if="drawerOpen" :task="selectedTask" :initial-status="createStatus" :projects="facets.projects" :assignees="drawerAssignees" :editable="selectedTask ? permissions.can_update : permissions.can_create" :can-delete="permissions.can_delete" :loading="detailLoading" :saving="mutationLoading" :project-id="projectId || undefined" @close="closeDrawer" @save="saveTask" @delete="deleteTask" @toggle-subtask="toggleSubtask" @add-subtask="addSubtask" />
  </main>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useRoute, useRouter } from "vue-router";
import { AlertCircle, KanbanSquare, ListTodo, Loader2, Plus, RefreshCw } from "lucide-vue-next";
import { toast } from "vue3-toastify";
import { useTaskStore } from "@/store/tasks";
import type { TaskFacetOption, TaskFilters, TaskItem, TaskStatus } from "@/types/task";
import TaskBoard from "./components/TaskBoard.vue";
import TaskChecklist from "./components/TaskChecklist.vue";
import TaskDrawer from "./components/TaskDrawer.vue";
import TaskFiltersPanel from "./components/TaskFiltersPanel.vue";
import TaskKpis from "./components/TaskKpis.vue";

type WorkspaceView = "list" | "board";
const store = useTaskStore();
const route = useRoute();
const router = useRouter();
const { tasks, board, pagination, summary, facets, permissions, error, loading, detailLoading, mutationLoading } = storeToRefs(store);
const isProjectRoute = computed(() => route.name === "Project Tasks" && !!route.params.id);
const projectId = computed(() => isProjectRoute.value ? Number(route.params.id) : 0);
const storageKey = computed(() => `pms.tasks.workspace.${projectId.value || "global"}`);
const preferredView = ref<WorkspaceView>("list");
const filtersOpen = ref(false);
const filters = ref<TaskFilters>({ sort_by: "smart_priority", sort_order: "asc", page: 1, per_page: 25, top_level_only: true });
const isBoardAvailable = ref(false);
const drawerOpen = ref(false);
const completingTaskId = ref<number | null>(null);
const selectedTask = ref<TaskItem | null>(null);
const createStatus = ref<TaskStatus>("pending");
let mediaQuery: MediaQueryList | null = null;
let reloadTimer: number | null = null;
let initialized = false;

const effectiveView = computed<WorkspaceView>(() => preferredView.value === "board" && isBoardAvailable.value ? "board" : "list");
const pageTitle = computed(() => {
  if (!isProjectRoute.value) return "Project Tasks";
  const project = facets.value.projects.find((item) => item.id === projectId.value);
  return project?.label?.replace(/^.*? - /, "") || `Project ${projectId.value} Work Plan`;
});
const drawerAssignees = computed<TaskFacetOption[]>(() => {
  const options = [...facets.value.assignees];
  const current = selectedTask.value?.assigned_to;
  if (current?.id && !options.some((item) => item.id === current.id)) options.push({ id: current.id, label: current.name || current.email || `User ${current.id}`, count: 1 });
  return options;
});

const queryString = (value: unknown) => Array.isArray(value) ? value[0] : typeof value === "string" ? value : undefined;
const queryNumber = (value: unknown) => { const parsed = Number(queryString(value)); return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined; };
const queryBoolean = (value: unknown) => queryString(value) === "true" ? true : undefined;

const restoreState = () => {
  let saved: any = {};
  try { saved = JSON.parse(localStorage.getItem(storageKey.value) || "{}"); } catch { saved = {}; }
  const queryView = queryString(route.query.view);
  preferredView.value = queryView === "board" || queryView === "list" ? queryView : (saved.view === "board" || saved.view === "list" ? saved.view : "list");
  filtersOpen.value = queryBoolean(route.query.filters_open) ?? Boolean(saved.filtersOpen);
  const persisted = saved.filters || {};
  filters.value = {
    sort_by: queryString(route.query.sort_by) || persisted.sort_by || "smart_priority",
    sort_order: queryString(route.query.sort_order) === "desc" ? "desc" : "asc",
    page: queryNumber(route.query.page) || 1,
    per_page: 25,
    top_level_only: true,
    search: queryString(route.query.search) || persisted.search || undefined,
    project_id: projectId.value || queryNumber(route.query.project_id) || persisted.project_id || undefined,
    status: (queryString(route.query.status) || persisted.status) as TaskStatus | undefined,
    priority: (queryString(route.query.priority) || persisted.priority) as any,
    soi_section: queryString(route.query.soi_section) || persisted.soi_section || undefined,
    workstream: queryString(route.query.workstream) || persisted.workstream || undefined,
    assigned_to: queryNumber(route.query.assigned_to) || persisted.assigned_to || undefined,
    overdue: queryBoolean(route.query.overdue) || persisted.overdue || undefined,
    urgent: queryBoolean(route.query.urgent) || persisted.urgent || undefined,
    lane_page_pending: queryNumber(route.query.lane_page_pending) || 1,
    lane_page_in_progress: queryNumber(route.query.lane_page_in_progress) || 1,
    lane_page_completed: queryNumber(route.query.lane_page_completed) || 1,
    lane_page_cancelled: queryNumber(route.query.lane_page_cancelled) || 1,
  };
};

const requestFilters = (): TaskFilters => ({
  ...filters.value,
  project_id: projectId.value || filters.value.project_id,
  my_projects: !projectId.value,
  view: effectiveView.value,
  per_page: effectiveView.value === "list" ? 25 : undefined,
  lane_per_page: effectiveView.value === "board" ? 10 : undefined,
});

const persistState = async () => {
  localStorage.setItem(storageKey.value, JSON.stringify({ view: preferredView.value, filtersOpen: filtersOpen.value, filters: filters.value }));
  const next: Record<string, string> = {};
  const values: Record<string, unknown> = { view: preferredView.value, filters_open: filtersOpen.value || undefined, search: filters.value.search, project_id: projectId.value ? undefined : filters.value.project_id, status: filters.value.status, priority: filters.value.priority, soi_section: filters.value.soi_section, workstream: filters.value.workstream, assigned_to: filters.value.assigned_to, overdue: filters.value.overdue, urgent: filters.value.urgent, sort_by: filters.value.sort_by !== "smart_priority" ? filters.value.sort_by : undefined, sort_order: filters.value.sort_order === "desc" ? "desc" : undefined, page: filters.value.page !== 1 ? filters.value.page : undefined, lane_page_pending: filters.value.lane_page_pending !== 1 ? filters.value.lane_page_pending : undefined, lane_page_in_progress: filters.value.lane_page_in_progress !== 1 ? filters.value.lane_page_in_progress : undefined, lane_page_completed: filters.value.lane_page_completed !== 1 ? filters.value.lane_page_completed : undefined, lane_page_cancelled: filters.value.lane_page_cancelled !== 1 ? filters.value.lane_page_cancelled : undefined, task_id: route.query.task_id };
  Object.entries(values).forEach(([key, value]) => { if (value !== undefined && value !== null && value !== "" && value !== false) next[key] = String(value); });
  await router.replace({ query: next });
};

const reload = async () => { await persistState(); await store.fetchTasks(requestFilters()); };
const scheduleReload = (searchChanged = false) => { if (reloadTimer) window.clearTimeout(reloadTimer); reloadTimer = window.setTimeout(reload, searchChanged ? 350 : 0); };
const resetPages = () => { filters.value.page = 1; filters.value.lane_page_pending = 1; filters.value.lane_page_in_progress = 1; filters.value.lane_page_completed = 1; filters.value.lane_page_cancelled = 1; };
const patchFilters = (patch: Partial<TaskFilters>) => { const searchChanged = Object.hasOwn(patch, "search"); filters.value = { ...filters.value, ...patch }; resetPages(); scheduleReload(searchChanged); };
const clearFilters = () => { filters.value = { sort_by: "smart_priority", sort_order: "asc", page: 1, per_page: 25, top_level_only: true, project_id: projectId.value || undefined, lane_page_pending: 1, lane_page_in_progress: 1, lane_page_completed: 1, lane_page_cancelled: 1 }; reload(); };
const setView = (view: WorkspaceView) => { if (view === "board" && !isBoardAvailable.value) return; preferredView.value = view; resetPages(); reload(); };
const changePage = (page: number) => { filters.value.page = page; reload(); };
const changeLanePage = (status: TaskStatus, page: number) => { (filters.value as any)[`lane_page_${status}`] = page; reload(); };
const applyKpi = (key: string) => { if (key === "total") patchFilters({ status: undefined, overdue: undefined, urgent: undefined }); else if (key === "overdue") patchFilters({ overdue: true, urgent: undefined, status: undefined }); else if (key === "urgent") patchFilters({ urgent: true, status: undefined, overdue: undefined }); else patchFilters({ status: key as TaskStatus, overdue: undefined, urgent: undefined }); };

const openTask = async (task: TaskItem) => {
  selectedTask.value = task;
  drawerOpen.value = true;
  await router.replace({ query: { ...route.query, task_id: String(task.id) } });
  try { selectedTask.value = await store.fetchTask(task.id); } catch { toast.error(store.error || "Unable to open task."); }
};
const openTaskById = async (taskId: number) => { drawerOpen.value = true; selectedTask.value = null; try { selectedTask.value = await store.fetchTask(taskId); } catch { drawerOpen.value = false; } };
const openCreate = (status: TaskStatus = "pending") => { createStatus.value = status; selectedTask.value = null; drawerOpen.value = true; };
const closeDrawer = async () => { drawerOpen.value = false; selectedTask.value = null; if (route.query.task_id) { const query = { ...route.query }; delete query.task_id; await router.replace({ query }); } };
const saveTask = async (payload: any) => { try { if (selectedTask.value) await store.updateTask(selectedTask.value.id, payload); else await store.createTask(payload); toast.success(selectedTask.value ? "Task updated" : "Task created"); await closeDrawer(); await reload(); } catch (error: any) { toast.error(store.errorMessage(error, "Task could not be saved.")); } };
const deleteTask = async (task: TaskItem) => { if (!window.confirm(`Delete "${task.title}"?`)) return; try { await store.deleteTask(task.id); toast.success("Task deleted"); await closeDrawer(); await reload(); } catch (error: any) { toast.error(store.errorMessage(error, "Task could not be deleted.")); } };
const moveTask = async (task: TaskItem, status: TaskStatus) => { if (!permissions.value.can_update) return; try { await store.moveTaskStatus(task, status); toast.success(`Moved to ${status.replaceAll("_", " ")}`); await reload(); } catch (error: any) { toast.error(store.errorMessage(error, "Task could not be moved.")); await reload(); } };
const toggleCompletion = async (task: TaskItem, completed: boolean) => { if (!permissions.value.can_update) return; completingTaskId.value = task.id; try { await store.updateCompletion(task, completed); await reload(); } catch (error: any) { toast.error(store.errorMessage(error, "Task completion could not be updated.")); } finally { completingTaskId.value = null; } };
const toggleSubtask = async (subtask: TaskItem) => { try { await store.updateCompletion(subtask, subtask.status !== "completed"); if (selectedTask.value) selectedTask.value = await store.fetchTask(selectedTask.value.id); } catch (error: any) { toast.error(store.errorMessage(error, "Checklist item could not be updated.")); } };
const addSubtask = async (title: string) => { if (!selectedTask.value) return; try { await store.createTask({ project_id: selectedTask.value.project_id || selectedTask.value.project?.id || 0, parent_task_id: selectedTask.value.id, title, status: "pending", soi_section: selectedTask.value.soi_section || undefined, workstream: selectedTask.value.workstream || undefined }); selectedTask.value = await store.fetchTask(selectedTask.value.id); } catch (error: any) { toast.error(store.errorMessage(error, "Checklist item could not be added.")); } };

watch(filtersOpen, () => { if (initialized) persistState(); });
watch(() => route.params.id, async () => { if (!initialized) return; restoreState(); await reload(); });
onMounted(async () => {
  mediaQuery = window.matchMedia("(min-width: 1280px)");
  const updateBoardAvailability = () => { const wasAvailable = isBoardAvailable.value; isBoardAvailable.value = Boolean(mediaQuery?.matches); if (initialized && wasAvailable !== isBoardAvailable.value) reload(); };
  updateBoardAvailability();
  mediaQuery.addEventListener("change", updateBoardAvailability);
  (mediaQuery as any)._listener = updateBoardAvailability;
  restoreState();
  initialized = true;
  await reload();
  const taskId = queryNumber(route.query.task_id);
  if (taskId) await openTaskById(taskId);
});
onBeforeUnmount(() => { if (reloadTimer) window.clearTimeout(reloadTimer); const listener = (mediaQuery as any)?._listener; if (listener) mediaQuery?.removeEventListener("change", listener); });
</script>

<style scoped>
.tasks-workspace { width:100%;display:grid;gap:12px;padding:16px;color:#0f172a }.workspace-head { display:flex;align-items:flex-end;justify-content:space-between;gap:16px }.heading>span { color:#2563eb;font-size:10px;font-weight:900;text-transform:uppercase }.heading h1 { color:#0f172a;font-size:22px;line-height:1.2;font-weight:850 }.heading p { margin-top:3px;color:#64748b;font-size:12px }.head-actions { display:flex;align-items:center;gap:8px }.view-switch { display:flex;border:1px solid #cbd5e1;background:#fff;padding:3px }.view-switch button,.create { min-height:34px;display:flex;align-items:center;gap:7px;padding:0 10px;color:#475569;font-size:12px;font-weight:800 }.view-switch button.active { background:#e8f0fe;color:#1d4ed8 }.view-switch button:disabled { opacity:.4;cursor:not-allowed }.view-switch svg,.create svg { width:15px }.create { border:1px solid #1d4ed8;background:#2563eb;color:#fff }.board-scroll { max-width:100%;overflow-x:auto;padding-bottom:4px }.loading-state { min-height:330px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;align-content:start;padding:16px;border:1px solid #e2e8f0;background:#fff;color:#64748b;font-size:12px }.loading-state>svg { width:18px;animation:spin 1s linear infinite }.loading-state>span { grid-column:span 2 }.loading-state i { height:84px;background:#f1f5f9;animation:pulse 1.3s ease-in-out infinite }.error-state { min-height:180px;display:flex;align-items:center;justify-content:center;gap:12px;padding:20px;border:1px solid #fecaca;background:#fff7f7;color:#b91c1c }.error-state>svg { width:24px }.error-state div { display:grid;gap:2px }.error-state span { font-size:12px }.error-state button { display:flex;align-items:center;gap:6px;min-height:34px;padding:0 10px;border:1px solid #fca5a5;background:#fff;font-size:12px;font-weight:800 }.error-state button svg { width:14px }@keyframes spin{to{transform:rotate(360deg)}}@keyframes pulse{50%{opacity:.45}}
:global(.dark) .tasks-workspace { color:#f8fafc }:global(.dark) .heading h1 { color:#f8fafc }:global(.dark) .view-switch { border-color:#475569;background:#0f172a }:global(.dark) .view-switch button { color:#cbd5e1 }:global(.dark) .view-switch button.active { background:#1e3a5f;color:#93c5fd }:global(.dark) .loading-state { border-color:#334155;background:#0f172a }:global(.dark) .loading-state i { background:#1e293b }
@media(max-width:700px){.tasks-workspace{padding:10px;gap:10px}.workspace-head{align-items:flex-start}.heading h1{font-size:18px}.heading p{display:none}.head-actions{align-items:stretch}.view-switch button{font-size:0;padding:0;width:34px;justify-content:center}.create{font-size:0;width:36px;padding:0;justify-content:center}.loading-state{grid-template-columns:1fr}.loading-state>span{grid-column:auto}.error-state{align-items:flex-start;flex-wrap:wrap}}
</style>
