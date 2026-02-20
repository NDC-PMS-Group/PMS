<template>
  <div class="space-y-4">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-5 md:p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white">Tasks</h1>
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

      <div class="grid grid-cols-2 divide-y divide-white/10 bg-slate-900 text-white/90 md:grid-cols-4 md:divide-x md:divide-y-0">
        <div class="px-4 py-3" v-for="col in columns" :key="col.status">
          <p class="text-xs uppercase tracking-wide text-white/60">{{ col.label }}</p>
          <p class="text-2xl font-bold">{{ filteredByStatus(col.status).length }}</p>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900 md:p-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid w-full gap-3 md:grid-cols-2 lg:max-w-4xl">
          <div class="relative">
            <SearchIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="search"
              class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
              placeholder="Search task title..."
            />
          </div>

          <div class="relative">
            <select
              v-model.number="selectedProjectId"
              class="w-full appearance-none rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
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
    </section>

    <section class="grid gap-4 xl:grid-cols-4 md:grid-cols-2">
      <div
        v-for="col in columns"
        :key="col.status"
        class="min-h-[520px] rounded-2xl border bg-slate-50 transition dark:bg-slate-900/40"
        :class="dropTargetStatus === col.status
          ? 'border-blue-500 ring-2 ring-blue-500/20 dark:border-blue-400 dark:ring-blue-400/20'
          : 'border-slate-200 dark:border-slate-800'"
        @dragover.prevent="onDragOver(col.status)"
        @dragleave="onDragLeave"
        @drop="onDrop(col.status)"
      >
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
          <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ col.label }}</h3>
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ filteredByStatus(col.status).length }}</span>
        </div>

        <div class="space-y-3 p-3">
          <article
            v-for="task in filteredByStatus(col.status)"
            :key="task.id"
            draggable="true"
            class="cursor-grab rounded-xl border border-slate-200 bg-white p-3 transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-700"
            @dragstart="onDragStart(task)"
            @dragend="onDragEnd"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ task.project?.project_code || 'N/A' }}</p>
              <div class="flex items-center gap-1.5">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(task.due_date) }}</p>
                <button
                  class="inline-flex h-6 w-6 items-center justify-center rounded-md text-slate-400 transition hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-300"
                  @click.stop="deleteTask(task)"
                  title="Delete task"
                >
                  <Trash2Icon class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>

            <h4 class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">{{ task.title }}</h4>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ task.project?.title || 'No project linked' }}</p>

            <div class="mt-3 flex items-center justify-between">
              <span :class="priorityBadgeClass(task.priority)">{{ task.priority || 'medium' }}</span>
            </div>
          </article>

          <div
            v-if="filteredByStatus(col.status).length === 0"
            class="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400"
          >
            Drop tasks here
          </div>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm"
        @mousedown.self="showCreateModal = false"
      >
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
          <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <div>
              <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100">Create New Task</h3>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add a task and assign it to a project.</p>
            </div>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-600 transition hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-slate-100"
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
                  class="w-full appearance-none rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
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
                class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                placeholder="Enter task title"
              />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Description</label>
              <textarea
                v-model="newTask.description"
                rows="3"
                class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                placeholder="Optional description"
              ></textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Priority</label>
                <div class="relative">
                  <select
                    v-model="newTask.priority"
                    class="w-full appearance-none rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                  <ChevronDownIcon class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Due Date</label>
                <input
                  v-model="newTask.due_date"
                  type="date"
                  class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
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
import type { TaskItem, TaskStatus } from "@/types/task";
import { toast } from "vue3-toastify";
import {
  ChevronDown as ChevronDownIcon,
  Plus as PlusIcon,
  RefreshCw as RefreshCwIcon,
  Search as SearchIcon,
  Trash2 as Trash2Icon,
  X as XIcon,
} from "lucide-vue-next";

const taskStore = useTaskStore();
const projectStore = useProjectStore();

const { tasks } = storeToRefs(taskStore);
const { projects } = storeToRefs(projectStore);

const selectedProjectId = ref(0);
const search = ref("");
const draggingTask = ref<TaskItem | null>(null);
const dropTargetStatus = ref<TaskStatus | null>(null);
const showCreateModal = ref(false);
const newTask = ref({
  project_id: 0,
  title: "",
  description: "",
  priority: "medium" as const,
  due_date: "",
});

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

const createTask = async () => {
  if (!newTask.value.project_id || !newTask.value.title.trim()) return;
  await taskStore.createTask({
    project_id: newTask.value.project_id,
    title: newTask.value.title.trim(),
    description: newTask.value.description?.trim() || undefined,
    priority: newTask.value.priority,
    due_date: newTask.value.due_date || undefined,
    status: "pending",
  });
  showCreateModal.value = false;
  newTask.value = { project_id: 0, title: "", description: "", priority: "medium", due_date: "" };
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

const priorityBadgeClass = (priority?: string | null) => {
  const base = "rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide";
  if (priority === "critical") return `${base} bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300`;
  if (priority === "high") return `${base} bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300`;
  if (priority === "low") return `${base} bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300`;
  return `${base} bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200`;
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
