import { computed } from 'vue'
import { useAuthStore } from '@/store/auth'

/**
 * Composable for checking user permissions and abilities
 * 
 * @example
 * const { hasPermission, canCreate, canUpdate, canDelete } = usePermission()
 * 
 * if (hasPermission('organization')) {
 *   // User can access organization page
 * }
 * 
 * if (canCreate('organization')) {
 *   // Show create button
 * }
 */
export function usePermission() {
  const authStore = useAuthStore()

  /**
   * Check if user has access to a permission (read/view access)
   */
  const hasPermission = (resource: string): boolean => {
    return authStore.canView(resource)
  }

  /**
   * Check if user can perform a specific action
   */
  const can = (resource: string, action: 'view' | 'create' | 'update' | 'delete'): boolean => {
    return authStore.can(resource, action)
  }

  /**
   * Check if user can create
   */
  const canCreate = (resource: string): boolean => {
    return authStore.canCreate(resource)
  }

  /**
   * Check if user can update
   */
  const canUpdate = (resource: string): boolean => {
    return authStore.canUpdate(resource)
  }

  /**
   * Check if user can delete
   */
  const canDelete = (resource: string): boolean => {
    return authStore.canDelete(resource)
  }

  /**
   * Get all abilities for a resource
   */
  const getAbilities = (resource: string) => {
    return computed(() => ({
      view: authStore.canView(resource),
      create: authStore.canCreate(resource),
      update: authStore.canUpdate(resource),
      delete: authStore.canDelete(resource)
    }))
  }

  /**
   * Check if user has any write access (create, update, or delete)
   */
  const canWrite = (resource: string): boolean => {
    return authStore.canCreate(resource) || 
           authStore.canUpdate(resource) || 
           authStore.canDelete(resource)
  }

  return {
    hasPermission,
    can,
    canCreate,
    canUpdate,
    canDelete,
    getAbilities,
    canWrite,
  }
}