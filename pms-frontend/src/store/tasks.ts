import { defineStore } from "pinia";
import axiosInstance from "@/utils/axiosInstance";
import type { TaskFilters, TaskFormData, TaskItem, TaskStatus } from "@/types/task";
import type { PaginationMeta } from "@/types/paginationMeta";

const TASK_ENDPOINTS = ["/api/v1/admin/tasks", "/api/v1/tasks", "/api/tasks"];

const isFallbackCandidateError = (error: any) => {
  const status = error?.response?.status;
  return status === 404 || status === 405;
};

const requestWithFallback = async <T>(
  requestFn: (basePath: string) => Promise<T>,
  basePaths: string[]
): Promise<T> => {
  let lastError: any = null;

  for (const basePath of basePaths) {
    try {
      return await requestFn(basePath);
    } catch (error: any) {
      lastError = error;
      if (!isFallbackCandidateError(error)) throw error;
    }
  }

  throw lastError;
};

const parsePagination = (source: any): PaginationMeta | null => {
  if (!source || typeof source !== "object") return null;

  const currentPage = source.current_page ?? source.currentPage;
  const lastPage = source.last_page ?? source.lastPage;
  const perPage = source.per_page ?? source.perPage;
  const total = source.total;

  if (
    typeof currentPage !== "number" ||
    typeof lastPage !== "number" ||
    typeof perPage !== "number" ||
    typeof total !== "number"
  ) {
    return null;
  }

  return {
    current_page: currentPage,
    last_page: lastPage,
    per_page: perPage,
    total,
    from: source.from ?? null,
    to: source.to ?? null,
  };
};

const parseTaskListResponse = (responseData: any): { tasks: TaskItem[]; pagination: PaginationMeta | null } => {
  const payload = responseData?.data ?? responseData;
  const list =
    (Array.isArray(payload?.data) && payload.data) ||
    (Array.isArray(payload?.tasks) && payload.tasks) ||
    (Array.isArray(payload) && payload) ||
    [];

  const pagination =
    parsePagination(payload?.meta?.pagination) ||
    parsePagination(payload?.meta) ||
    parsePagination(payload?.pagination) ||
    parsePagination(payload) ||
    parsePagination(responseData?.meta?.pagination) ||
    parsePagination(responseData?.meta) ||
    parsePagination(responseData?.pagination) ||
    null;

  return {
    tasks: list as TaskItem[],
    pagination,
  };
};

const parseTaskItemResponse = (responseData: any): TaskItem | null => {
  const payload = responseData?.data ?? responseData;
  if (payload && typeof payload === "object" && !Array.isArray(payload) && typeof payload.id === "number") {
    return payload as TaskItem;
  }
  if (responseData?.task && typeof responseData.task === "object") {
    return responseData.task as TaskItem;
  }
  return null;
};

interface TaskState {
  tasks: TaskItem[];
  currentTask: TaskItem | null;
  pagination: PaginationMeta | null;
  filters: TaskFilters;
  loading: boolean;
  error: string | null;
}

export const useTaskStore = defineStore("task", {
  state: (): TaskState => ({
    tasks: [],
    currentTask: null,
    pagination: null,
    filters: {
      sort_by: "due_date",
      sort_order: "asc",
      per_page: 200,
      page: 1,
    },
    loading: false,
    error: null,
  }),

  getters: {
    tasksByStatus: (state) => (status: TaskStatus) =>
      state.tasks.filter((t) => t.status === status),
  },

  actions: {
    getApiErrorMessage(error: any, fallback: string): string {
      return error?.response?.data?.error || error?.response?.data?.message || error?.message || fallback;
    },

    async fetchTasks(filters?: Partial<TaskFilters>) {
      this.loading = true;
      this.error = null;

      try {
        if (filters) this.filters = { ...this.filters, ...filters };

        const params = new URLSearchParams();
        Object.entries(this.filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null && value !== "") {
            params.append(key, String(value));
          }
        });

        const query = params.toString();
        const response = await requestWithFallback(
          (basePath) => axiosInstance.get(query ? `${basePath}?${query}` : basePath),
          TASK_ENDPOINTS
        );

        const parsed = parseTaskListResponse(response.data);
        this.tasks = parsed.tasks;
        this.pagination = parsed.pagination;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, "Failed to fetch tasks");
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createTask(payload: TaskFormData) {
      this.loading = true;
      this.error = null;

      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.post(basePath, payload),
          TASK_ENDPOINTS
        );
        const created = parseTaskItemResponse(response.data);
        if (created) this.tasks.unshift(created);
        return created;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, "Failed to create task");
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateTask(taskId: number, payload: Partial<TaskItem>) {
      this.loading = true;
      this.error = null;

      try {
        const response = await requestWithFallback(
          (basePath) => axiosInstance.put(`${basePath}/${taskId}`, payload),
          TASK_ENDPOINTS
        );
        const updated = parseTaskItemResponse(response.data);
        if (updated) {
          const idx = this.tasks.findIndex((t) => t.id === taskId);
          if (idx !== -1) this.tasks[idx] = updated;
        }
        return updated;
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, "Failed to update task");
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async moveTaskStatus(task: TaskItem, status: TaskStatus) {
      const originalStatus = task.status;
      task.status = status;

      try {
        await this.updateTask(task.id, { status });
      } catch (error) {
        task.status = originalStatus;
        throw error;
      }
    },

    async deleteTask(taskId: number) {
      this.loading = true;
      this.error = null;

      try {
        await requestWithFallback(
          (basePath) => axiosInstance.delete(`${basePath}/${taskId}`),
          TASK_ENDPOINTS
        );
        this.tasks = this.tasks.filter((task) => task.id !== taskId);
      } catch (error: any) {
        this.error = this.getApiErrorMessage(error, "Failed to delete task");
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});
