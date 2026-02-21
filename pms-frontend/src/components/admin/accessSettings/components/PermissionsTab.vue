<script lang="ts" setup>
import { computed } from 'vue'
import { usePermission } from '@/composables/usePermission'
import { useAccessSettingsStore } from '@/store/accessSettings'
import { toast } from 'vue3-toastify'
import { Plus, Trash2, Check } from 'lucide-vue-next'
import type { Permission } from '@/types/accessSettings'

interface Props {
  permissions: Permission[]
  permissionKey: string
  loading: boolean
}

interface Emits {
  (e: 'open-permission-modal'): void
  (e: 'refresh'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { canCreate, canDelete } = usePermission()
const accessStore = useAccessSettingsStore()

// Group permissions by resource for display
const groupedPermissionsForDisplay = computed(() => {
  const groups: Record<string, {
    resource: string
    description: string | null
    actions: {
      view?: Permission
      create?: Permission
      update?: Permission
      delete?: Permission
    }
  }> = {}

  props.permissions.forEach(permission => {
    if (!groups[permission.resource]) {
      groups[permission.resource] = {
        resource: permission.resource,
        description: permission.description,
        actions: {}
      }
    }
    groups[permission.resource].actions[permission.action as 'view' | 'create' | 'update' | 'delete'] = permission
  })

  return Object.values(groups)
})

const deletePermissionGroup = async (resource: string) => {
  if (!confirm(`Are you sure you want to delete all permissions for "${resource}"?`)) return

  try {
    // Find all permissions for this resource
    const permissionsToDelete = props.permissions.filter(p => p.resource === resource)
    
    // Delete all permissions
    const promises = permissionsToDelete.map(p => accessStore.deletePermission(p.id))
    await Promise.all(promises)
    
    toast.success(`All permissions for "${resource}" deleted successfully`)
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to delete permissions')
    console.error(error)
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Permissions Management</h2>
      <button
        v-if="canCreate(permissionKey)"
        @click="emit('open-permission-modal')"
        class="flex items-center space-x-2 px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors"
      >
        <Plus :size="18" />
        <span class="text-sm">Add Permission</span>
      </button>
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      <div class="overflow-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-white dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Resource</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Manage</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 dark:border-blue-400 mb-2"></div>
                  Loading permissions...
                </div>
              </td>
            </tr>
            <tr v-else-if="groupedPermissionsForDisplay.length === 0">
              <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No permissions found
              </td>
            </tr>
            <tr v-else v-for="group in groupedPermissionsForDisplay" :key="group.resource" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ group.resource }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ group.description || '-' }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-2">
                  <span 
                    v-if="group.actions.view"
                    class="px-2 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300"
                  >
                    <Check :size="12" class="mr-1" />
                    view
                  </span>
                  <span 
                    v-if="group.actions.create"
                    class="px-2 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300"
                  >
                    <Check :size="12" class="mr-1" />
                    create
                  </span>
                  <span 
                    v-if="group.actions.update"
                    class="px-2 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300"
                  >
                    <Check :size="12" class="mr-1" />
                    update
                  </span>
                  <span 
                    v-if="group.actions.delete"
                    class="px-2 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300"
                  >
                    <Check :size="12" class="mr-1" />
                    delete
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  v-if="canDelete(permissionKey)"
                  @click="deletePermissionGroup(group.resource)"
                  class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                  title="Delete all permissions for this resource"
                >
                  <Trash2 :size="18" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>  
    </div>
  </div>
</template>