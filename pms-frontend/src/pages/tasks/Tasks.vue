<template>
  <div class="tasks-page" :class="{ 'is-dark': isDarkMode }">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-text">
          <h1 class="page-title">{{ isProjectView && currentProject ? currentProject.title : 'Tasks' }}</h1>
          <p class="page-subtitle">
            <span class="stat-pill">{{ stats.total }} total</span>
            <span class="stat-pill pending">{{ stats.pending }} pending</span>
            <span class="stat-pill in-progress">{{ stats.inProgress }} in progress</span>
            <span class="stat-pill completed">{{ stats.completed }} completed</span>
          </p>
        </div>
        <div class="header-actions">
          <button class="btn-create" @click="openCreateModal()">
            <PlusIcon class="btn-icon" /> New Task
          </button>
        </div>
      </div>
      <div class="stats-row">
        <!-- Stat Cards from columns -->
        <div class="stat-card" v-for="col in columns" :key="col.status">
          <div class="stat-icon" :class="getStatIconColor(col.status)">
            <component :is="getStatIcon(col.status)" class="icon" />
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ filteredByStatus(col.status).length }}</span>
            <span class="stat-label">{{ col.label }}</span>
          </div>
        </div>
      </div>
    </div>

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
          <button
            class="flex items-center gap-2 px-3 py-1.5 text-sm font-semibold transition rounded-lg"
            :class="currentView === 'gantt' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
            @click="currentView = 'gantt'"
          >
            <GanttChartIcon class="h-4 w-4" /> Gantt
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

            <div class="relative" v-if="!isProjectView">
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
            @click="openTaskModal(task)"
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
                <div v-if="task.subtasks && task.subtasks.length > 0" class="flex items-center gap-1 text-[11px] font-medium" :class="task.subtasks.filter(s => s.status === 'completed').length === task.subtasks.length ? 'text-emerald-500' : 'text-slate-500 dark:text-slate-400'">
                  <ListTodoIcon class="h-3 w-3" />
                  {{ task.subtasks.filter(s => s.status === 'completed').length }}/{{ task.subtasks.length }}
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
      <FullCalendar 
        :key="tasks.length + '-' + selectedProjectId"
        :options="calendarOptions" 
        :events="calendarEvents" 
      />
    </section>

    <!-- Gantt View -->
    <section v-if="currentView === 'gantt'">
      <GanttView :tasks="tasks" @dateChange="onGanttDateChange" @taskClick="openTaskModal" />
    </section>

    <Teleport to="body">
      <div
        v-if="showTaskModal"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm"
        @mousedown.self="closeModal"
      >
        <div class="w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden rounded-2xl border border-white/40 bg-white/90 shadow-2xl backdrop-blur-3xl dark:border-white/10 dark:bg-slate-900/90">
          <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700 shrink-0">
            <div>
              <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ isEditing ? editingTask?.title : 'Create New Task' }}</h3>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ isEditing ? 'Edit task details and subtasks.' : 'Add a task and assign it to a project.' }}</p>
            </div>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-slate-200/50 text-slate-600 transition hover:text-slate-900 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-300 dark:hover:text-slate-100"
              @click="closeModal"
            >
              <XIcon class="h-4 w-4" />
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
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

            <!-- Assignee Picker -->
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Assignee</label>
                <div class="relative">
                  <select
                    v-model.number="newTask.assigned_to"
                    class="w-full appearance-none rounded-lg border border-slate-300 bg-white/70 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                  >
                    <option :value="0">Unassigned</option>
                    <option v-for="member in projectMembers" :key="member.id" :value="member.id">{{ member.name || member.email }}</option>
                  </select>
                  <ChevronDownIcon class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
              </div>

              <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Start Date</label>
                <input
                  v-model="newTask.start_date"
                  type="date"
                  class="w-full rounded-lg border border-slate-300 bg-white/70 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                />
              </div>
            </div>

            <!-- Subtasks Section -->
            <div v-if="isEditing" class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-700">
              <h4 class="mb-3 text-sm font-bold flex items-center gap-2 text-slate-800 dark:text-slate-100">
                <ListTodoIcon class="h-4 w-4" /> Subtasks
              </h4>
              <ul class="space-y-2 mb-3">
                <li v-for="sub in editingTask?.subtasks" :key="sub.id" class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/50">
                  <div class="flex items-center gap-3">
                    <input type="checkbox" :checked="sub.status === 'completed'" @change="toggleSubtaskStatus(sub)" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600 dark:border-slate-600 dark:bg-slate-700 dark:ring-offset-slate-900" />
                    <span class="text-sm font-medium" :class="sub.status === 'completed' ? 'text-slate-400 line-through dark:text-slate-500' : 'text-slate-700 dark:text-slate-200'">{{ sub.title }}</span>
                  </div>
                  <button @click="deleteTask(sub)" class="text-slate-400 hover:text-red-500 transition p-1 rounded-md hover:bg-red-50 dark:hover:bg-red-900/20"><Trash2Icon class="h-3.5 w-3.5" /></button>
                </li>
                <li v-if="!editingTask?.subtasks?.length" class="text-xs text-slate-500 italic px-1">No subtasks yet.</li>
              </ul>
              <div class="flex items-center gap-2 mt-2 relative">
                <input
                  v-model="newSubtaskTitle"
                  @keyup.enter="createSubtask"
                  class="w-full rounded-lg border border-slate-300 bg-white/70 px-3 py-2 pr-10 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100"
                  placeholder="Add a new subtask..."
                />
                <button @click="createSubtask" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-blue-600 transition"><PlusIcon class="h-4 w-4" /></button>
              </div>
            </div>

          </div>

          <!-- Activity Info Footer -->
          <div v-if="isEditing && editingTask" class="border-t border-slate-200 px-5 py-3 dark:border-slate-700 shrink-0">
            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
              <span v-if="editingTask.assigned_by">
                <UsersIcon class="inline h-3 w-3 mr-1" />Created by <strong>{{ editingTask.assigned_by?.name || 'Unknown' }}</strong>
              </span>
              <span v-if="editingTask.updated_at">
                Last updated: {{ formatDate(editingTask.updated_at) }}
              </span>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700 shrink-0">
            <button
              class="rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-500 hover:text-blue-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
              @click="closeModal"
            >
              Cancel
            </button>
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700" @click="saveTask">
              {{ isEditing ? 'Save Changes' : 'Create Task' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch, markRaw } from "vue";
import { storeToRefs } from "pinia";
import { useRoute } from "vue-router";
import { useTaskStore } from "@/store/tasks";
import { useProjectStore } from "@/store/projects";
import { useLayoutStore } from "@/store/layout";
import { SITE_MODE } from "@/app/const";
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
  ListTodo as ListTodoIcon,
  GanttChart as GanttChartIcon,
  Users as UsersIcon,
  Clock as ClockIcon,
  CheckCircle as CheckCircleIcon,
  AlertTriangle as AlertTriangleIcon,
  Briefcase as BriefcaseIcon,
} from "lucide-vue-next";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import interactionPlugin from "@fullcalendar/interaction";
import GanttView from "@/components/tasks/GanttView.vue";

const taskStore = useTaskStore();
const projectStore = useProjectStore();
const layoutStore = useLayoutStore();
const route = useRoute();

const { tasks } = storeToRefs(taskStore);
const { projects } = storeToRefs(projectStore);
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);

const currentView = ref<"board" | "calendar" | "list" | "gantt">("board");
const selectedProjectId = ref(0);
const isProjectView = computed(() => !!route.params.id && route.name === "Project Tasks");
const currentProject = computed(() => {
  if (!isProjectView.value) return null;
  return projects.value.find(p => p.id === Number(route.params.id)) || null;
});
const search = ref("");
const draggingTask = ref<TaskItem | null>(null);
const dropTargetStatus = ref<TaskStatus | null>(null);

const getStatIconColor = (status: TaskStatus) => {
  const map: Record<TaskStatus, string> = {
    pending: 'amber',
    in_progress: 'blue',
    completed: 'green',
    cancelled: 'red'
  };
  return map[status] || 'blue';
};

const getStatIcon = (status: TaskStatus) => {
  const map: Record<TaskStatus, any> = {
    pending: markRaw(ClockIcon),
    in_progress: markRaw(BriefcaseIcon),
    completed: markRaw(CheckCircleIcon),
    cancelled: markRaw(AlertTriangleIcon)
  };
  return map[status] || markRaw(BriefcaseIcon);
};

const showTaskModal = ref(false);
const isEditing = ref(false);
const editingTask = ref<TaskItem | null>(null);
const newSubtaskTitle = ref("");

const newTask = ref({
  project_id: 0,
  title: "",
  description: "",
  priority: "normal" as TaskPriority,
  due_date: "",
  start_date: "",
  status: "pending" as TaskStatus,
  assigned_to: 0 as number,
});

const projectMembers = ref<{ id: number; name?: string; email?: string }[]>([]);

const calendarOptions = ref({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: "dayGridMonth",
  headerToolbar: {
    left: "prev,next today",
    center: "title",
    right: "dayGridMonth,dayGridWeek",
  },
  events: [] as any[],
  eventClick: (info: any) => {
    openTaskModal(info.event.extendedProps.task);
  },
  height: "auto",
});

const calendarEvents = ref<any[]>([]);

watch(
  tasks,
  (newTasks) => {
    calendarEvents.value = newTasks.map((task) => {
      const startObj = task.start_date || task.due_date;
      const endObj = task.due_date;
      // For FullCalendar allDay events, the end date is exclusive. Appending time ensures inclusive rendering.
      const formattedEnd = endObj && endObj.length === 10 ? `${endObj}T23:59:59` : endObj;

      return {
        id: String(task.id),
        title: task.title,
        start: startObj || undefined,
        end: formattedEnd || undefined,
        color: getStatusColor(task.status),
        extendedProps: { task },
      };
    });
    calendarOptions.value.events = calendarEvents.value;
  },
  { immediate: true, deep: true }
);

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

const stats = computed(() => {
  try {
    const allFiltered = tasks.value.filter(task => {
      const currentProjectId = isProjectView.value ? Number(route.params.id) : selectedProjectId.value;
      if (currentProjectId && task.project?.id !== currentProjectId) return false;
      if (search.value.trim()) return (task.title || "").toLowerCase().includes(search.value.toLowerCase());
      return true;
    });

    return {
      total: allFiltered.length,
      pending: allFiltered.filter((t) => t.status === "pending").length,
      inProgress: allFiltered.filter((t) => t.status === "in_progress").length,
      completed: allFiltered.filter((t) => t.status === "completed").length,
      cancelled: allFiltered.filter((t) => t.status === "cancelled").length,
    };
  } catch (err) {
    console.error("Error computing stats:", err);
    return { total: 0, pending: 0, inProgress: 0, completed: 0, cancelled: 0 };
  }
});

const projectOptions = computed(() => projects.value || []);
const editableProjectOptions = ref<{ id: number; title: string; project_code?: string }[]>([]);

const filteredByStatus = (status: TaskStatus) => {
  try {
    return tasks.value.filter((task) => {
      if (task.status !== status) return false;
      const currentProjectId = isProjectView.value ? Number(route.params.id) : selectedProjectId.value;
      if (currentProjectId && task.project?.id !== currentProjectId) return false;
      if (search.value.trim()) return (task.title || "").toLowerCase().includes(search.value.toLowerCase());
      return true;
    });
  } catch (err) {
    console.error("Error filtering tasks by status:", err);
    return [];
  }
};

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
  await taskStore.fetchTasks({ my_projects: true, top_level_only: true });
};

const onGanttDateChange = async (taskId: number, start: string, end: string) => {
  try {
    await taskStore.updateTask(taskId, { start_date: start, due_date: end } as any);
    toast.success("Task dates updated");
  } catch {
    toast.error("Failed to update task dates");
  }
};

const loadProjectMembers = async (projectId: number) => {
  if (!projectId) { projectMembers.value = []; return; }
  try {
    const { default: axiosInstance } = await import("@/utils/axiosInstance");
    const res = await axiosInstance.get(`/api/projects/${projectId}`);
    const project = res.data?.data || res.data;
    const members = project?.members || project?.team_members || [];
    projectMembers.value = members.map((m: any) => ({
      id: m.user_id || m.id,
      name: m.user?.name || m.name,
      email: m.user?.email || m.email,
    }));
  } catch {
    projectMembers.value = [];
  }
};

const openCreateModal = (status?: TaskStatus) => {
  isEditing.value = false;
  editingTask.value = null;
  newTask.value = { project_id: 0, title: "", description: "", priority: "normal", due_date: "", start_date: "", status: "pending", assigned_to: 0 };
  if (status) newTask.value.status = status;
  if (isProjectView.value) newTask.value.project_id = Number(route.params.id);
  showTaskModal.value = true;
};

const openTaskModal = (task: TaskItem) => {
  isEditing.value = true;
  editingTask.value = task;
  newTask.value = {
    project_id: task.project?.id || 0,
    title: task.title,
    description: task.description || "",
    priority: task.priority || "normal",
    due_date: task.due_date ? task.due_date.split('T')[0] : "",
    start_date: task.start_date ? task.start_date.split('T')[0] : "",
    status: task.status,
    assigned_to: task.assigned_to?.id || 0,
  };
  showTaskModal.value = true;
  loadProjectMembers(task.project?.id || 0);
};

const closeModal = () => {
  showTaskModal.value = false;
  isEditing.value = false;
  editingTask.value = null;
  newSubtaskTitle.value = "";
};

const saveTask = async () => {
  if (!newTask.value.title.trim()) return;
  if (isEditing.value && editingTask.value) {
    const updatePayload: any = {
      title: newTask.value.title,
      description: newTask.value.description || null,
      due_date: newTask.value.due_date || null,
      start_date: newTask.value.start_date || null,
      priority: newTask.value.priority,
      status: newTask.value.status,
    };
    if (newTask.value.assigned_to) updatePayload.assigned_to = newTask.value.assigned_to;
    await taskStore.updateTask(editingTask.value.id, updatePayload);
    toast.success("Task updated");
  } else {
    if (!newTask.value.project_id) return;
    await taskStore.createTask({
      project_id: newTask.value.project_id,
      title: newTask.value.title.trim(),
      description: newTask.value.description?.trim() || undefined,
      priority: newTask.value.priority,
      due_date: newTask.value.due_date || undefined,
      start_date: newTask.value.start_date || undefined,
      status: newTask.value.status,
      assigned_to: newTask.value.assigned_to || undefined,
    });
    toast.success("Task created");
  }
  closeModal();
  await reload();
};

const createSubtask = async () => {
  if (!newSubtaskTitle.value.trim() || !editingTask.value) return;
  
  const subtask = await taskStore.createTask({
    project_id: editingTask.value.project?.id || 0,
    parent_task_id: editingTask.value.id,
    title: newSubtaskTitle.value.trim(),
    status: "pending",
  });
  
  if (subtask) {
    if (!editingTask.value.subtasks) editingTask.value.subtasks = [];
    editingTask.value.subtasks.push(subtask);
    toast.success("Subtask added");
    newSubtaskTitle.value = "";
  }
};

const toggleSubtaskStatus = async (sub: TaskItem) => {
  const newStatus = sub.status === 'completed' ? 'pending' : 'completed';
  const updated = await taskStore.updateTask(sub.id, { status: newStatus });
  if (updated && editingTask.value?.subtasks) {
    const idx = editingTask.value.subtasks.findIndex(s => s.id === sub.id);
    if (idx !== -1) editingTask.value.subtasks[idx] = updated;
  }
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
    taskStore.fetchTasks({ per_page: 200, page: 1, my_projects: true, top_level_only: true }),
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

watch(
  () => route.fullPath,
  async (newPath) => {
    console.log("Tasks route changed to:", newPath);
    try {
      const projectId = route.params.id ? Number(route.params.id) : 0;
      selectedProjectId.value = projectId;
      console.log("Selected project ID set to:", projectId);

      if (projectId) {
        await taskStore.fetchTasks({ project_id: projectId, top_level_only: true, my_projects: undefined });
      } else {
        // Clear all filters when going back to "All Tasks"
        taskStore.resetFilters();
        await taskStore.fetchTasks({ my_projects: true, top_level_only: true });
      }
      console.log("Tasks fetched successfully, count:", tasks.value.length);
    } catch (err) {
      console.error("Failed to fetch tasks on route change:", err);
    }
  },
  { immediate: true }
);
</script>

<style scoped>
.tasks-page {
  --c-bg: #f8fafc;
  --c-card: rgba(255, 255, 255, 0.7);
  --glass-blur: blur(20px);
  --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
  --header-bg: linear-gradient(135deg, #ffffff 0%, #f1f5f9 60%, #e2e8f0 100%);
  --header-text: #0f172a;
  --header-text-muted: #64748b;
  --header-border: rgba(15,23,42,0.1);
  --header-hover: rgba(15,23,42,0.04);
  --header-pill-bg: rgba(15,23,42,0.06);
  --header-pill-border: rgba(15,23,42,0.1);
  --header-pill-text: #475569;
}

:global(.dark) .tasks-page, .tasks-page.is-dark {
  --c-bg: #0f172a;
  --c-card: rgba(30, 41, 59, 0.6);
  --glass-blur: blur(20px);
  --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
  --header-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f4c81 100%);
  --header-text: #ffffff;
  --header-text-muted: rgba(255,255,255,0.5);
  --header-border: rgba(255,255,255,0.1);
  --header-hover: rgba(255,255,255,0.05);
  --header-pill-bg: rgba(255,255,255,0.12);
  --header-pill-border: rgba(255,255,255,0.2);
  --header-pill-text: rgba(255,255,255,0.85);
}

/* ─── Header ─── */
.page-header {
  background: var(--header-bg);
  padding: 2rem 2rem 0;
  color: var(--header-text);
  position: relative;
  overflow: hidden;
  border-radius: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}
.page-header::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%2364748b' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
:global(.dark) .page-header::before, .tasks-page.is-dark .page-header::before {
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
.header-content { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; position: relative; z-index: 1; }
.page-title { font-size: 2rem; font-weight: 800; letter-spacing: -0.025em; margin: 0 0 0.5rem; }
.page-subtitle { display: flex; align-items: center; gap: 0.5rem; margin: 0; flex-wrap: wrap; }
.stat-pill { font-size: 0.73rem; font-weight: 600; background: var(--header-pill-bg); border: 1px solid var(--header-pill-border); padding: 0.2rem 0.6rem; border-radius: 999px; color: var(--header-pill-text); }
.stat-pill.completed { background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.3); color: #15803d; }
:global(.dark) .stat-pill.completed, .tasks-page.is-dark .stat-pill.completed { background: rgba(34,197,94,0.2); border-color: rgba(34,197,94,0.4); color: #86efac; }
.stat-pill.in-progress { background: rgba(59,130,246,0.15); border-color: rgba(59,130,246,0.3); color: #2563eb; }
:global(.dark) .stat-pill.in-progress, .tasks-page.is-dark .stat-pill.in-progress { background: rgba(59,130,246,0.2); border-color: rgba(59,130,246,0.4); color: #93c5fd; }
.stat-pill.pending { background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.3); color: #d97706; }
:global(.dark) .stat-pill.pending, .tasks-page.is-dark .stat-pill.pending { background: rgba(245,158,11,0.2); border-color: rgba(245,158,11,0.4); color: #fcd34d; }
.header-actions { display: flex; gap: 0.75rem; flex-shrink: 0; }
.btn-create { display: flex; align-items: center; gap: 0.5rem; padding: 0.575rem 1.1rem; background: #2563eb; border: none; color: white; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(37,99,235,0.35); }
.btn-create:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,0.45); }
.btn-icon { width: 1.1rem; height: 1.1rem; flex-shrink: 0; }

.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid var(--header-border); position: relative; z-index: 1; }
.stat-card { display: flex; align-items: center; gap: 0.875rem; padding: 1.125rem 1.5rem; border-right: 1px solid var(--header-border); transition: background 0.15s; }
.stat-card:last-child { border-right: none; }
.stat-card:hover { background: var(--header-hover); }
.stat-icon { width: 2.375rem; height: 2.375rem; border-radius: 0.625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon .icon { width: 1.125rem; height: 1.125rem; }
.stat-icon.blue { background: rgba(59,130,246,0.15); color: #2563eb; }
:global(.dark) .stat-icon.blue, .tasks-page.is-dark .stat-icon.blue { background: rgba(59,130,246,0.2); color: #93c5fd; }
.stat-icon.green { background: rgba(34,197,94,0.15); color: #16a34a; }
:global(.dark) .stat-icon.green, .tasks-page.is-dark .stat-icon.green { background: rgba(34,197,94,0.2); color: #86efac; }
.stat-icon.red { background: rgba(239,68,68,0.15); color: #dc2626; }
:global(.dark) .stat-icon.red, .tasks-page.is-dark .stat-icon.red { background: rgba(239,68,68,0.2); color: #fca5a5; }
.stat-icon.amber { background: rgba(245,158,11,0.15); color: #d97706; }
:global(.dark) .stat-icon.amber, .tasks-page.is-dark .stat-icon.amber { background: rgba(245,158,11,0.2); color: #fcd34d; }
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.375rem; font-weight: 700; color: var(--header-text); line-height: 1; }
.stat-label { font-size: 0.68rem; color: var(--header-text-muted); margin-top: 0.25rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }
</style>
