import type { Role } from '@/types/accessSettings'
import type { PaginationMeta } from '@/types/paginationMeta'

export interface User {
  id: number
  username: string
  email: string
  first_name: string
  middle_name: string | null
  last_name: string
  suffix: string | null
  full_name: string
  initials: string
  phone_number: string | null
  address: string | null
  profile_photo_url: string
  employee_id: string | null
  department: string | null
  position: string | null
  date_hired: string | null
  birth_date: string | null
  role: Role | null
  is_active: boolean
  last_login: string | null
  created_at: string
}


export interface UserFormData {
  username: string
  email: string
  password?: string
  password_confirmation?: string
  first_name: string
  middle_name?: string | null
  last_name: string
  suffix?: string | null
  phone_number?: string | null
  address?: string | null
  profile_photo_url?: string | null
  employee_id?: string | null
  department?: string | null
  position?: string | null
  date_hired?: string | null
  birth_date?: string | null
  default_role_id: number
  is_active?: boolean
}

// ==================== FILTERS & PAGINATION ====================

export interface UserFilters {
  search?: string
  role_id?: number | null
  department?: string | null
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

// ==================== STATE ====================

export interface UserState {
  users: User[]
  selectedUser: User | null
  pagination: PaginationMeta | null
  filters: UserFilters
  loading: boolean
  submitting: boolean
}