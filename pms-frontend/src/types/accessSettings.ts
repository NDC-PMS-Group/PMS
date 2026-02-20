export interface Permission {
  id: number
  name: string
  resource: string
  action: string
  description: string | null
  created_at?: string
}

export interface Role {
  id: number
  name: string
  description: string | null
  is_system_role: boolean
  permissions?: Permission[]
  created_at?: string
}

export interface PermissionFormData {
  resource: string
  action: string
  description?: string
}

export interface RoleFormData {
  name: string
  description?: string
  is_system_role?: boolean
}

export interface AssignPermissionsPayload {
  permission_ids: number[]
}

export interface SyncPermissionsPayload {
  permission_ids: number[]
}

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface AccessSettingsState {
  permissions: Permission[]
  roles: Role[]
  loading: boolean
  selectedRole: Role | null
}