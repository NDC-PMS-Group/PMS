<template>
  <div class="space-y-4">
    <section class="overflow-hidden rounded-2xl border border-white/40 bg-white/40 backdrop-blur-xl shadow-[0_8px_32px_0_rgba(31,38,135,0.07)] dark:border-white/10 dark:bg-slate-900/60 dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.3)]">
      <div class="bg-gradient-to-r from-blue-900/80 via-indigo-900/80 to-slate-900/80 p-5 md:p-6 backdrop-blur-md">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white drop-shadow-md">Tasks</h1>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold text-white">{{ tasks.length }} total</span>
              <span class="rounded-full bg-amber-400/20 px-2.5 py-1 text-xs font-semibold text-amber-200">{{ stats.pending }} pending</span>
              <span class="rounded-full bg-blue-400/20 px-2.5 py-1 text-xs font-semibold text-blue-200">{{ stats.inProgress }} in progress</span>
              <span class="rounded-full bg-emerald-400/20 px-2.5 py-1 text-xs font-semibold text-emerald-200">{{ stats.completed }} completed</span>
            </div>
          </div>
          <button
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-900/30 transition hover:bg-blue-700"
            @click="showCreateModal = true"
          >
            <PlusIcon class="h-4 w-4" /> New Task
          </button>
        </div>
      </div>

      <div class="grid grid-cols-2 divide-y divide-white/10 bg-slate-900/20 text-white/90 md:grid-cols-4 md:divide-x md:divide-y-0 backdrop-blur-md">
        <div class="px-4 py-3" v-for="col in columns" :key="col.status">
          <p class="text-xs uppercase tracking-wide text-white/60">{{ col.label }}</p>
          <p class="text-2xl font-bold">{{ filteredByStatus(col.status).length }}</p>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-white/40 bg-white/40 p-2 shadow-[0_8px_32px_0_rgba(31,38,135,0.07)] backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/60 dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.3)]">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between px-2 py-1">
        <div class="flex items-center gap-1 border-r border-slate-200 dark:border-slate-800 pr-4">
          <button
            class="flex items-center gap-2 px-3 py-1.5 text-sm font-semibold transition rounded-lg"
            :class="currentView === 'board' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
            @click="currentView = 'board'"
          >
            <LayoutIcon class="h-4 w-4" /> Board
          </button>
          <button
            class="flex items-center gap-2 px-3 py-1.5 text-sm font-semibold transition rounded-lg"
            :class="currentView === 'calendar' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
            @click="currentView = 'calendar'"
          >
            <CalendarIcon class="h-4 w-4" /> Calendar
          </button>
        </div>

        <div class="flex-1 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between ml-2">
          <div class="grid w-full gap-3 md:grid-cols-2 lg:max-w-4xl">
            <div class="relative">
              <SearchIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                v-model="search"
                class="w-full rounded-lg border border-white/50 bg-white/50 py-2 pl-9 pr-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-white/10 dark:bg-slate-800/50 dark:text-slate-100"
                placeholder="Search task title..."
              />
            </div>

            <div class="relative">
              <select
                v-model.number="selectedProjectId"
                class="w-full appearance-none rounded-lg border border-white/50 bg-white/50 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-white/10 dark:bg-slate-800/50 dark:text-slate-100"
              >
                <option :value="0">All Projects</option>
                <option v-for="p in projectOptions" :key="p.id" :value="p.id">{{ p.project_code }} - {{ p.title }}</option>
              </select>
              <ChevronDownIcon class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            </div>
          </div>

          <button
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-500 hover:text-blue-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
            @click="reload"
          >
            <RefreshCwIcon class="h-4 w-4" /> Reload
          </button>
        </div>
      </div>
    </section>

    <!-- Kanban Board View -->
    <section v-if="currentView === 'board'" class="flex overflow-x-auto pb-6 gap-6 scrollbar-thin scrollbar-thumb-slate-300 dark:scrollbar-thumb-slate-700">
      <div
        v-for="col in columns"
        :key="col.status"
        class="min-w-[320px] max-w-[320px] flex flex-col h-full rounded-2xl border border-white/40 bg-white/30 backdrop-blur-sm transition dark:border-white/10 dark:bg-slate-900/40"
        :class="dropTargetStatus === col.status
          ? 'border-blue-500 ring-2 ring-blue-500/20 dark:border-blue-400 dark:ring-blue-400/20'
          : 'border-slate-200 dark:border-slate-800'"
        @dragover.prevent="onDragOver(col.status)"
        @dragleave="onDragLeave"
        @drop="onDrop(col.status)"
      >
        <div class="flex items-center justify-between px-4 py-3 sticky top-0 bg-white/50 dark:bg-slate-900/50 backdrop-blur-md rounded-t-2xl border-b border-slate-200 dark:border-slate-800">
          <div class="flex items-center gap-2">
            <span :class="['w-2.5 h-2.5 rounded-full', statusBgClass(col.status)]"></span>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">{{ col.label }}</h3>
            <span class="rounded-md bg-slate-200/50 px-1.5 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">{{ filteredByStatus(col.status).length }}</span>
          </div>
          <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <MoreVerticalIcon class="h-4 w-4" />
          </button>
        </div>

        <div class="flex-1 space-y-3 p-3 min-h-[500px]">
          <article
            v-for="task in filteredByStatus(col.status)"
            :key="task.id"
            draggable="true"
            class="group relative cursor-grab rounded-xl border border-white/60 bg-white/70 p-4 shadow-sm backdrop-blur-md transition hover:-translate-y-1 hover:border-blue-400 hover:shadow-lg active:cursor-grabbing dark:border-white/10 dark:bg-slate-800/70 dark:hover:border-blue-700"
            :class="statusBorderClass(task.status)"
            @dragstart="onDragStart(task)"
            @dragend="onDragEnd"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">{{ task.project?.project_code || 'TASK' }}</span>
              <button
                class="opacity-0 group-hover:opacity-100 inline-flex h-6 w-6 items-center justify-center rounded-md text-slate-400 transition hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-300"
                @click.stop="deleteTask(task)"
              >
                <Trash2Icon class="h-3.5 w-3.5" />
              </button>
            </div>

            <h4 class="mt-2 text-sm font-bold text-slate-900 leading-snug dark:text-slate-100">{{ task.title }}</h4>
            
            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
              <div class="flex items-center gap-3">
                <div v-if="task.due_date" class="flex items-center gap-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                  <CalendarIcon class="h-3 w-3" />
                  {{ formatDate(task.due_date) }}
                </div>
                <div v-if="task.priority" :title="'Priority: ' + task.priority">
                  <FlagIcon :class="['h-3.5 w-3.5', priorityIconColor(task.priority)]" />
                </div>
              </div>
              
              <div class="flex -space-x-2">
                <div class="h-6 w-6 rounded-full border-2 border-white bg-blue-100 flex items-center justify-center text-[10px] font-bold text-blue-600 dark:border-slate-800 dark:bg-blue-900/40">
                  {{ task.assigned_to?.name?.charAt(0) || '?' }}
                </div>
              </div>
            </div>
          </article>

          <button
            class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border border-dashed border-slate-300 text-xs font-semibold text-slate-500 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50/30 transition dark:border-slate-700 dark:text-slate-400 dark:hover:border-blue-700 dark:hover:bg-blue-900/10"
            @click="openCreateModal(col.status)"
          >
            <PlusIcon class="h-3.5 w-3.5" /> Add Task
          </button>
        </div>
      </div>
    </section>

    <!-- Calendar View -->
    <section v-if="currentView === 'calendar'" class="rounded-2xl border border-white/40 bg-white/70 p-6 shadow-xl backdrop-blur-md dark:border-white/10 dark:bg-slate-900/70">
      <FullCalendar :options="calendarOptions" />
    </section>

    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm"
        @mousedown.self="showCreateModal = false"
      >
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/40 bg-white/60 shadow-2xl backdrop-blur-3xl dark:border-white/10 dark:bg-slate-900/60">
          <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
              <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100">Create New Task</h3>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add a task and assign it to a project.</p>
            </div>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-slate-200/50 text-slate-600 transition hover:text-slate-900 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-300 dark:hover:text-slate-100"
              @click="showCreateModal = false"
            >
              <XIcon class="h-4 w-4" />
            </button>
          </div>

          <div class="space-y-4 px-5 py-4">
            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Project</label>
              <div class="relative">
                <select
                  v-model.number="newTask.project_id"
                  class="w-full appearance-none rounded-lg border border-slate-300 bg-white/70 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                >
                  <option :value="0">Select project</option>
                  <option v-for="p in editableProjectOptions" :key="p.id" :value="p.id">{{ p.project_code }} - {{ p.title }}</option>
                </select>
                <ChevronDownIcon class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              </div>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Task Title</label>
              <input
                v-model="newTask.title"
                class="w-full rounded-lg border border-slate-300 bg-white/70 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                placeholder="Enter task title"
              />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Description</label>
              <textarea
                v-model="newTask.description"
                rows="3"
                class="w-full rounded-lg border border-slate-300 bg-white/70 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                placeholder="Optional description"
              ></textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Priority</label>
                <div class="relative">
                  <select
                    v-model="newTask.priority"
                    class="w-full appearance-none rounded-lg border border-slate-300 bg-white/70 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                  >
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                  <ChevronDownIcon class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Status</label>
                <div class="relative">
                  <select
                    v-model="newTask.status"
                    class="w-full appearance-none rounded-lg border border-slate-300 bg-white/70 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                  >
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                  <ChevronDownIcon class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Due Date</label>
                <input
                  v-model="newTask.due_date"
                  type="date"
                  class="w-full rounded-lg border border-slate-300 bg-white/70 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                />
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
            <button
              class="rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-500 hover:text-blue-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
              @click="showCreateModal = false"
            >
              Cancel
            </button>
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700" @click="createTask">
              Create Task
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useTaskStore } from "@/store/tasks";
import { useProjectStore } from "@/store/projects";
import type { TaskItem, TaskStatus, TaskPriority } from "@/types/task";
import { toast } from "vue3-toastify";
import {
  ChevronDown as ChevronDownIcon,
  Plus as PlusIcon,
  RefreshCw as RefreshCwIcon,
  Search as SearchIcon,
  Trash2 as Trash2Icon,
  X as XIcon,
  Calendar as CalendarIcon,
  Flag as FlagIcon,
  MoreVertical as MoreVerticalIcon,
  Layout as LayoutIcon,
} from "lucide-vue-next";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import interactionPlugin from "@fullcalendar/interaction";

const taskStore = useTaskStore();
const projectStore = useProjectStore();

const { tasks } = storeToRefs(taskStore);
const { projects } = storeToRefs(projectStore);

const currentView = ref<"board" | "calendar" | "list">("board");
const selectedProjectId = ref(0);
const search = ref("");
const draggingTask = ref<TaskItem | null>(null);
const dropTargetStatus = ref<TaskStatus | null>(null);
const showCreateModal = ref(false);
const newTask = ref({
  project_id: 0,
  title: "",
  description: "",
  priority: "normal" as TaskPriority,
  due_date: "",
  status: "pending" as TaskStatus,
});

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: "dayGridMonth",
  headerToolbar: {
    left: "prev,next today",
    center: "title",
    right: "dayGridMonth,dayGridWeek",
  },
  events: tasks.value.map((task) => ({
    id: String(task.id),
    title: task.title,
    start: task.due_date || task.start_date || undefined,
    color: getStatusColor(task.status),
    extendedProps: { task },
  })),
  eventClick: (info: any) => {
    // Handle task click
    console.log("Task clicked:", info.event.extendedProps.task);
  },
  height: "auto",
}));

const getStatusColor = (status: TaskStatus) => {
  if (status === "completed") return "#10b981";
  if (status === "in_progress") return "#3b82f6";
  if (status === "cancelled") return "#ef4444";
  return "#94a3b8";
};

const columns: { status: TaskStatus; label: string }[] = [
  { status: "pending", label: "Pending" },
  { status: "in_progress", label: "In Progress" },
  { status: "completed", label: "Completed" },
  { status: "cancelled", label: "Cancelled" },
];

const stats = computed(() => ({
  pending: tasks.value.filter((t) => t.status === "pending").length,
  inProgress: tasks.value.filter((t) => t.status === "in_progress").length,
  completed: tasks.value.filter((t) => t.status === "completed").length,
  cancelled: tasks.value.filter((t) => t.status === "cancelled").length,
}));

const projectOptions = computed(() => projects.value || []);
const editableProjectOptions = ref<{ id: number; title: string; project_code?: string }[]>([]);

const filteredByStatus = (status: TaskStatus) =>
  tasks.value.filter((task) => {
    if (task.status !== status) return false;
    if (selectedProjectId.value && task.project?.id !== selectedProjectId.value) return false;
    if (search.value.trim()) return (task.title || "").toLowerCase().includes(search.value.toLowerCase());
    return true;
  });

const onDragStart = (task: TaskItem) => {
  draggingTask.value = task;
};

const onDragEnd = () => {
  dropTargetStatus.value = null;
};

const onDragOver = (status: TaskStatus) => {
  dropTargetStatus.value = status;
};

const onDragLeave = () => {
  dropTargetStatus.value = null;
};

const onDrop = async (status: TaskStatus) => {
  if (!draggingTask.value || draggingTask.value.status === status) {
    dropTargetStatus.value = null;
    return;
  }
  await taskStore.moveTaskStatus(draggingTask.value, status);
  draggingTask.value = null;
  dropTargetStatus.value = null;
};

const reload = async () => {
  await taskStore.fetchTasks({ my_projects: true });
};

const openCreateModal = (status?: TaskStatus) => {
  if (status) newTask.value.status = status;
  showCreateModal.value = true;
};

const createTask = async () => {
  if (!newTask.value.project_id || !newTask.value.title.trim()) return;
  await taskStore.createTask({
    project_id: newTask.value.project_id,
    title: newTask.value.title.trim(),
    description: newTask.value.description?.trim() || undefined,
    priority: newTask.value.priority,
    due_date: newTask.value.due_date || undefined,
    status: newTask.value.status,
  });
  showCreateModal.value = false;
  newTask.value = { project_id: 0, title: "", description: "", priority: "normal", due_date: "", status: "pending" };
  await reload();
};

const deleteTask = async (task: TaskItem) => {
  const confirmed = window.confirm(`Delete task \"${task.title}\"?`);
  if (!confirmed) return;

  try {
    await taskStore.deleteTask(task.id);
    toast.success("Task deleted");
  } catch (error: any) {
    const message = error?.response?.data?.message || taskStore.error || "Failed to delete task";
    toast.error(message);
  }
};

const formatDate = (value?: string | null) => {
  if (!value) return "No due date";
  return new Date(value).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
};



const priorityIconColor = (priority?: string | null) => {
  if (priority === "critical" || priority === "urgent") return "text-red-600";
  if (priority === "high") return "text-amber-500";
  if (priority === "medium" || priority === "normal") return "text-blue-500";
  if (priority === "low") return "text-emerald-500";
  return "text-slate-400";
};

const statusBorderClass = (status: TaskStatus) => {
  if (status === "pending") return "border-l-4 border-l-slate-400";
  if (status === "in_progress") return "border-l-4 border-l-blue-500";
  if (status === "completed") return "border-l-4 border-l-emerald-500";
  if (status === "cancelled") return "border-l-4 border-l-red-500";
  return "";
};

const statusBgClass = (status: TaskStatus) => {
  if (status === "pending") return "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300";
  if (status === "in_progress") return "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300";
  if (status === "completed") return "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300";
  if (status === "cancelled") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
  return "";
};

onMounted(async () => {
  await Promise.all([
    projectStore.fetchProjects({ per_page: 200, page: 1, is_archived: false, my_projects: true }),
    taskStore.fetchTasks({ per_page: 200, page: 1, my_projects: true }),
  ]);

  await projectStore.fetchProjects({
    per_page: 200,
    page: 1,
    is_archived: false,
    editable_projects: true,
  });

  editableProjectOptions.value = (projectStore.projects || []).map((p) => ({
    id: p.id,
    title: p.title,
    project_code: p.project_code,
  }));

  // Restore visible project list for filter dropdown (my projects).
  await projectStore.fetchProjects({ per_page: 200, page: 1, is_archived: false, my_projects: true });
});
</script>
