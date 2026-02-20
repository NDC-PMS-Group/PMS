import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type {
  Permission,
  Role,
  PermissionFormData,
  RoleFormData,
  AssignPermissionsPayload,
  SyncPermissionsPayload,
  AccessSettingsState
} from '@/types/accessSettings'

export const useAccessSettingsStore = defineStore('accessSettings', {
  // ==================== STATE ====================
  state: (): AccessSettingsState => ({
    permissions: [],
    roles: [],
    loading: false,
    selectedRole: null
  }),

  // ==================== GETTERS ====================
  getters: {
    /**
     * Get permissions grouped by resource
     */
    permissionsByResource: (state) => {
      const grouped: Record<string, Permission[]> = {}
      
      state.permissions.forEach(permission => {
        if (!grouped[permission.resource]) {
          grouped[permission.resource] = []
        }
        grouped[permission.resource].push(permission)
      })
      
      return grouped
    },

    /**
     * Get role by ID
     */
    getRoleById: (state) => (id: number): Role | undefined => {
      return state.roles.find(role => role.id === id)
    },

    /**
     * Get permission by ID
     */
    getPermissionById: (state) => (id: number): Permission | undefined => {
      return state.permissions.find(permission => permission.id === id)
    },

    /**
     * Get non-system roles only
     */
    customRoles: (state) => {
      return state.roles.filter(role => !role.is_system_role)
    },

    /**
     * Get system roles only
     */
    systemRoles: (state) => {
      return state.roles.filter(role => role.is_system_role)
    }
  },

  // ==================== ACTIONS ====================
  actions: {
    // ============================================
    // PERMISSIONS
    // ============================================

    /**
     * Fetch all permissions
     */
    async fetchPermissions(): Promise<void> {
      this.loading = true
      try {
        const response = await axiosInstance.get('/api/access-settings/permissions')
        // Handle both { data: [...] } and direct array responses
        this.permissions = Array.isArray(response.data) ? response.data : (response.data.data || [])
      } catch (error) {
        console.error('Failed to fetch permissions:', error)
        this.permissions = []
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Create a new permission
     */
    async createPermission(data: PermissionFormData): Promise<Permission> {
      this.loading = true
      try {
        const response = await axiosInstance.post('/api/access-settings/permissions', data)
        const permission = response.data.data || response.data
        this.permissions.push(permission)
        return permission
      } catch (error) {
        console.error('Failed to create permission:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Update an existing permission
     */
    async updatePermission(id: number, data: PermissionFormData): Promise<Permission> {
      this.loading = true
      try {
        const response = await axiosInstance.put(`/api/access-settings/permissions/${id}`, data)
        const permission = response.data.data || response.data
        
        // Update in local state
        const index = this.permissions.findIndex(p => p.id === id)
        if (index !== -1) {
          this.permissions[index] = permission
        }
        
        return permission
      } catch (error) {
        console.error('Failed to update permission:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Delete a permission
     */
    async deletePermission(id: number): Promise<void> {
      this.loading = true
      try {
        await axiosInstance.delete(`/api/access-settings/permissions/${id}`)
        
        // Remove from local state
        this.permissions = this.permissions.filter(p => p.id !== id)
      } catch (error) {
        console.error('Failed to delete permission:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    // ============================================
    // ROLES
    // ============================================

    /**
     * Fetch all roles with their permissions
     */
    async fetchRoles(): Promise<void> {
      this.loading = true
      try {
        const response = await axiosInstance.get('/api/access-settings/roles')
        // Handle both { data: [...] } and direct array responses
        this.roles = Array.isArray(response.data) ? response.data : (response.data.data || [])
      } catch (error) {
        console.error('Failed to fetch roles:', error)
        this.roles = []
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Create a new role
     */
    async createRole(data: RoleFormData): Promise<Role> {
      this.loading = true
      try {
        const response = await axiosInstance.post('/api/access-settings/roles', data)
        const role = response.data.data || response.data
        this.roles.push(role)
        return role
      } catch (error) {
        console.error('Failed to create role:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Update an existing role
     */
    async updateRole(id: number, data: RoleFormData): Promise<Role> {
      this.loading = true
      try {
        const response = await axiosInstance.put(`/api/access-settings/roles/${id}`, data)
        const role = response.data.data || response.data
        
        // Update in local state
        const index = this.roles.findIndex(r => r.id === id)
        if (index !== -1) {
          this.roles[index] = role
        }
        
        return role
      } catch (error) {
        console.error('Failed to update role:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Delete a role
     */
    async deleteRole(id: number): Promise<void> {
      this.loading = true
      try {
        await axiosInstance.delete(`/api/access-settings/roles/${id}`)
        
        // Remove from local state
        this.roles = this.roles.filter(r => r.id !== id)
      } catch (error) {
        console.error('Failed to delete role:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    // ============================================
    // ROLE PERMISSIONS
    // ============================================

    /**
     * Assign permissions to a role (add without removing existing)
     */
    async assignPermissions(roleId: number, permissionIds: number[]): Promise<Role> {
      this.loading = true
      try {
        const payload: AssignPermissionsPayload = { permission_ids: permissionIds }
        const response = await axiosInstance.post(
          `/api/access-settings/roles/${roleId}/permissions/assign`,
          payload
        )
        const role = response.data.data || response.data
        
        // Update role in local state
        const index = this.roles.findIndex(r => r.id === roleId)
        if (index !== -1) {
          this.roles[index] = role
        }
        
        return role
      } catch (error) {
        console.error('Failed to assign permissions:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Remove permissions from a role
     */
    async removePermissions(roleId: number, permissionIds: number[]): Promise<Role> {
      this.loading = true
      try {
        const payload: AssignPermissionsPayload = { permission_ids: permissionIds }
        const response = await axiosInstance.post(
          `/api/access-settings/roles/${roleId}/permissions/remove`,
          payload
        )
        const role = response.data.data || response.data
        
        // Update role in local state
        const index = this.roles.findIndex(r => r.id === roleId)
        if (index !== -1) {
          this.roles[index] = role
        }
        
        return role
      } catch (error) {
        console.error('Failed to remove permissions:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Sync permissions to a role (replace all existing permissions)
     */
    async syncPermissions(roleId: number, permissionIds: number[]): Promise<Role> {
      this.loading = true
      try {
        const payload: SyncPermissionsPayload = { permission_ids: permissionIds }
        const response = await axiosInstance.post(
          `/api/access-settings/roles/${roleId}/permissions/sync`,
          payload
        )
        const role = response.data.data || response.data
        
        // Update role in local state
        const index = this.roles.findIndex(r => r.id === roleId)
        if (index !== -1) {
          this.roles[index] = role
        }
        
        return role
      } catch (error) {
        console.error('Failed to sync permissions:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Set selected role for permission assignment
     */
    setSelectedRole(role: Role | null): void {
      this.selectedRole = role
    },

    /**
     * Clear all state
     */
    clearState(): void {
      this.permissions = []
      this.roles = []
      this.selectedRole = null
      this.loading = false
    }
  }
})