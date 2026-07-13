import type { Role } from '@/types/accessSettings'
import type { PaginationMeta } from '@/types/paginationMeta'

export interface ProponentProfile {
  business_summary?: string | null
  project_experience?: string | null
  previous_projects?: string | null
  major_clients?: string | null
  certifications?: string | null
}

export interface ProponentRegistrationDocument {
  id: number
  document_type: string
  title: string
  file_name: string
  file_size?: number | null
  file_type?: string | null
  review_status?: string | null
  review_remarks?: string | null
  uploaded_at?: string | null
}

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
  organization_name?: string | null
  organization_type?: string | null
  organization_registration_no?: string | null
  proponent_profile?: ProponentProfile | null
  registration_documents?: ProponentRegistrationDocument[]
  profile_photo_url: string
  employee_id: string | null
  department: string | null
  position: string | null
  date_hired: string | null
  birth_date: string | null
  role: Role | null
  is_active: boolean
  staff_invitation_expires_at?: string | null
  staff_invitation_accepted_at?: string | null
  invited_by?: User | null
  last_login: string | null
  created_at: string
  project_memberships?: unknown[]
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
  organization_name?: string | null
  organization_type?: string | null
  organization_registration_no?: string | null
  proponent_profile?: ProponentProfile | null
  profile_photo_url?: string | null
  employee_id?: string | null
  department?: string | null
  position?: string | null
  date_hired?: string | null
  birth_date?: string | null
  default_role_id: number
  is_active?: boolean
}

export type StaffInviteFormData = Pick<
  UserFormData,
  'email' | 'first_name' | 'last_name' | 'default_role_id'
> & Partial<Pick<
  UserFormData,
  'middle_name' | 'suffix' | 'phone_number' | 'employee_id' | 'date_hired'
>> & {
  department: string
  position: string
}

export interface StaffInviteResponse {
  user: User
  invite_url: string
  message: string
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
  usersRequestId: number
  loading: boolean
  submitting: boolean
}
