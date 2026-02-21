import { User } from "./user"

export interface ProfileProject {
  id: number
  title: string
  status: string
  role: string | null
  start_date: string | null
  end_date: string | null
}

export interface ProfileTask {
  id: number
  title: string
  status: string
  priority: string | null
  due_date: string | null
  progress: number
  project: { id: number; title: string } | null
}

export interface ProfileActivity {
  id: number
  action: string
  description: string | null
  model_type: string | null
  model_id: number | null
  created_at: string
}

export interface ProfileStats {
  totalProjects: number
  activeProjects: number
  totalTasks: number
  completedTasks: number
  inProgressTasks: number
}

export interface UpdateProfilePayload {
  first_name?: string
  middle_name?: string
  last_name?: string
  suffix?: string
  email?: string
  username?: string
  phone_number?: string
  address?: string
  department?: string
  position?: string
  birth_date?: string
}

export interface ChangePasswordPayload {
  current_password: string
  new_password: string
  new_password_confirmation: string
}

export interface ProfileState {
  profile: User | null
  projects: ProfileProject[]
  tasks: ProfileTask[]
  activity: ProfileActivity[]
  stats: ProfileStats | null

  loading: {
    profile: boolean
    projects: boolean
    tasks: boolean
    activity: boolean
    updating: boolean
    uploadingAvatar: boolean
    changingPassword: boolean
  }

  errors: {
    profile: string | null
    projects: string | null
    tasks: string | null
    activity: string | null
  }

  taskFilter: string
}