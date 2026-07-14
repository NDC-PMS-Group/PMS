import { defineStore } from "pinia";
import axiosInstance from "@/utils/axiosInstance";
import type { PaginationMeta } from "@/types/paginationMeta";
import type {
  TaskBoard,
  TaskFacets,
  TaskFilters,
  TaskFormData,
  TaskItem,
  TaskLane,
  TaskStatus,
  TaskSummary,
  TaskWorkspacePermissions,
} from "@/types/task";

const STATUSES: TaskStatus[] = ["pending", "in_progress", "completed", "cancelled"];
const emptyMeta = (): PaginationMeta => ({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: null, to: null });
const emptyLane = (): TaskLane => ({ data: [], meta: emptyMeta() });
const emptyBoard = (): TaskBoard => ({
  pending: emptyLane(),
  in_progress: emptyLane(),
  completed: emptyLane(),
  cancelled: emptyLane(),
});
const emptySummary = (): TaskSummary => ({ total: 0, pending: 0, in_progress: 0, completed: 0, cancelled: 0, overdue: 0, urgent: 0 });
const emptyFacets = (): TaskFacets => ({ statuses: [], priorities: [], soi_sections: [], projects: [], assignees: [] });
const defaultPermissions = (): TaskWorkspacePermissions => ({ can_view: true, can_create: false, can_update: false, can_delete: false });

const parseMeta = (source: any): PaginationMeta => ({
  current_page: Number(source?.current_page ?? 1),
  last_page: Number(source?.last_page ?? 1),
  per_page: Number(source?.per_page ?? 25),
  total: Number(source?.total ?? 0),
  from: source?.from ?? null,
  to: source?.to ?? null,
});

const replaceTask = (items: TaskItem[], task: TaskItem): TaskItem[] => items.map((item) => {
  if (item.id === task.id) return task;
  return item.subtasks?.length ? { ...item, subtasks: replaceTask(item.subtasks, task) } : item;
});

const removeTask = (items: TaskItem[], taskId: number): TaskItem[] => items
  .filter((item) => item.id !== taskId)
  .map((item) => item.subtasks?.length ? { ...item, subtasks: removeTask(item.subtasks, taskId) } : item);

interface TaskState {
  tasks: TaskItem[];
  board: TaskBoard;
  currentTask: TaskItem | null;
  pagination: PaginationMeta;
  summary: TaskSummary;
  facets: TaskFacets;
  permissions: TaskWorkspacePermissions;
  filters: TaskFilters;
  loading: boolean;
  detailLoading: boolean;
  mutationLoading: boolean;
  error: string | null;
}

export const useTaskStore = defineStore("task", {
  state: (): TaskState => ({
    tasks: [],
    board: emptyBoard(),
    currentTask: null,
    pagination: emptyMeta(),
    summary: emptySummary(),
    facets: emptyFacets(),
    permissions: defaultPermissions(),
    filters: { sort_by: "smart_priority", sort_order: "asc", per_page: 25, page: 1, top_level_only: true },
    loading: false,
    detailLoading: false,
    mutationLoading: false,
    error: null,
  }),

  getters: {
    tasksByStatus: (state) => (status: TaskStatus) => state.board[status].data,
  },

  actions: {
    errorMessage(error: any, fallback: string): string {
      return error?.response?.data?.message || error?.response?.data?.error || error?.message || fallback;
    },

    async fetchTasks(filters: TaskFilters) {
      this.loading = true;
      this.error = null;
      this.filters = { ...filters };
      try {
        const { data } = await axiosInstance.get("/api/tasks", { params: filters });
        this.summary = { ...emptySummary(), ...(data?.summary || {}) };
        this.facets = { ...emptyFacets(), ...(data?.facets || {}) };
        this.permissions = { ...defaultPermissions(), ...(data?.permissions || {}) };

        if (data?.board) {
          const board = emptyBoard();
          STATUSES.forEach((status) => {
            board[status] = {
              data: Array.isArray(data.board[status]?.data) ? data.board[status].data : [],
              meta: parseMeta(data.board[status]?.meta),
            };
          });
          this.board = board;
          this.tasks = STATUSES.flatMap((status) => board[status].data);
          this.pagination = emptyMeta();
        } else {
          this.tasks = Array.isArray(data?.data) ? data.data : [];
          this.pagination = parseMeta(data?.meta);
          this.board = emptyBoard();
        }
      } catch (error: any) {
        this.error = this.errorMessage(error, "Unable to load tasks.");
      } finally {
        this.loading = false;
      }
    },

    async fetchTask(taskId: number) {
      this.detailLoading = true;
      try {
        const { data } = await axiosInstance.get(`/api/tasks/${taskId}`);
        this.currentTask = data?.data ?? data;
        return this.currentTask;
      } catch (error: any) {
        this.error = this.errorMessage(error, "Unable to load task details.");
        throw error;
      } finally {
        this.detailLoading = false;
      }
    },

    async createTask(payload: TaskFormData) {
      this.mutationLoading = true;
      try {
        const { data } = await axiosInstance.post("/api/tasks", payload);
        return (data?.data ?? data) as TaskItem;
      } finally {
        this.mutationLoading = false;
      }
    },

    async updateTask(taskId: number, payload: Partial<TaskItem>) {
      this.mutationLoading = true;
      try {
        const { data } = await axiosInstance.put(`/api/tasks/${taskId}`, payload);
        const updated = (data?.data ?? data) as TaskItem;
        this.tasks = replaceTask(this.tasks, updated);
        STATUSES.forEach((status) => {
          this.board[status].data = this.board[status].data.filter((task) => task.id !== taskId);
        });
        if (!updated.parent_task_id) this.board[updated.status].data = [updated, ...this.board[updated.status].data];
        if (this.currentTask?.id === taskId) this.currentTask = updated;
        return updated;
      } finally {
        this.mutationLoading = false;
      }
    },

    async moveTaskStatus(task: TaskItem, status: TaskStatus) {
      return this.updateTask(task.id, { status });
    },

    async updateCompletion(task: TaskItem, completed: boolean) {
      this.mutationLoading = true;
      try {
        const { data } = await axiosInstance.patch(`/api/tasks/${task.id}/completion`, { completed });
        const updated = (data?.data ?? data) as TaskItem;
        this.tasks = replaceTask(this.tasks, updated);
        STATUSES.forEach((status) => {
          this.board[status].data = this.board[status].data.filter((item) => item.id !== task.id);
        });
        if (!updated.parent_task_id) this.board[updated.status].data = [updated, ...this.board[updated.status].data];
        if (this.currentTask?.id === task.id) this.currentTask = updated;
        return updated;
      } finally {
        this.mutationLoading = false;
      }
    },

    async deleteTask(taskId: number) {
      this.mutationLoading = true;
      try {
        await axiosInstance.delete(`/api/tasks/${taskId}`);
        this.tasks = removeTask(this.tasks, taskId);
        STATUSES.forEach((status) => {
          this.board[status].data = removeTask(this.board[status].data, taskId);
        });
        if (this.currentTask?.id === taskId) this.currentTask = null;
      } finally {
        this.mutationLoading = false;
      }
    },
  },
});
