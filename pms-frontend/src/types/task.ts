export type TaskStatus = "pending" | "in_progress" | "completed" | "cancelled";
export type TaskPriority = "low" | "medium" | "high" | "critical";

export interface TaskProjectRef {
  id: number;
  title: string;
  project_code?: string;
}

export interface TaskUserRef {
  id: number;
  name?: string;
  username?: string;
  email?: string;
}

export interface TaskItem {
  id: number;
  title: string;
  description?: string | null;
  task_type?: string | null;
  project?: TaskProjectRef | null;
  assigned_to?: TaskUserRef | null;
  assigned_by?: TaskUserRef | null;
  start_date?: string | null;
  due_date?: string | null;
  completion_date?: string | null;
  status: TaskStatus;
  progress_percentage?: number | null;
  priority?: TaskPriority | null;
  estimated_hours?: number | null;
  actual_hours?: number | null;
  is_milestone?: boolean;
  is_overdue?: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface TaskFilters {
  project_id?: number;
  assigned_to?: number;
  status?: TaskStatus;
  priority?: TaskPriority;
  overdue?: boolean;
  my_projects?: boolean;
  sort_by?: string;
  sort_order?: "asc" | "desc";
  per_page?: number;
  page?: number;
  search?: string;
}

export interface TaskFormData {
  project_id: number;
  title: string;
  description?: string;
  task_type?: string;
  assigned_to?: number;
  start_date?: string;
  due_date?: string;
  status?: TaskStatus;
  priority?: TaskPriority;
  estimated_hours?: number;
  is_milestone?: boolean;
}
