export type TaskStatus = "pending" | "in_progress" | "completed" | "cancelled";
export type TaskPriority = "low" | "normal" | "high" | "urgent" | "medium" | "critical";

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
  soi_section?: string | null;
  parent_task_id?: number | null;
  project?: TaskProjectRef | null;
  assigned_to?: TaskUserRef | null;
  assigned_by?: TaskUserRef | null;
  start_date?: string | null;
  due_date?: string | null;
  completion_date?: string | null;
  status: TaskStatus;
  progress_percentage?: number | null;
  priority?: TaskPriority | null;
  priority_profile?: {
    label: string;
    rank: number;
    severity: string;
    description: string;
  };
  estimated_hours?: number | null;
  actual_hours?: number | null;
  is_milestone?: boolean;
  is_overdue?: boolean;
  created_at?: string;
  updated_at?: string;
  status_history?: TaskStatusHistory[];
  hierarchy?: {
    is_parent: boolean;
    is_subtask: boolean;
    level: number;
  };
  parent_task?: TaskItem | null;
  subtasks?: TaskItem[];
}

export interface TaskStatusHistory {
  id: number;
  from_status?: TaskStatus | null;
  to_status: TaskStatus;
  from_progress?: number | null;
  to_progress?: number | null;
  event_type: "created" | "status_changed" | "progress_updated" | string;
  notes?: string | null;
  changed_at?: string | null;
  changed_by?: TaskUserRef | null;
}

export interface TaskFilters {
  project_id?: number;
  assigned_to?: number;
  status?: TaskStatus;
  priority?: TaskPriority;
  soi_section?: string;
  overdue?: boolean;
  process_track?: string;
  my_projects?: boolean;
  sort_by?: string;
  sort_order?: "asc" | "desc";
  per_page?: number;
  page?: number;
  search?: string;
  top_level_only?: boolean;
}

export interface TaskFormData {
  project_id: number;
  title: string;
  description?: string;
  task_type?: string;
  soi_section?: string;
  assigned_to?: number;
  start_date?: string;
  due_date?: string;
  status?: TaskStatus;
  priority?: TaskPriority;
  estimated_hours?: number;
  parent_task_id?: number | null;
  is_milestone?: boolean;
}
