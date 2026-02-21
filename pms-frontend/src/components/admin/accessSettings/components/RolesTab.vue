<script lang="ts" setup>
import { usePermission } from '@/composables/usePermission'
import { useAccessSettingsStore } from '@/store/accessSettings'
import { toast } from 'vue3-toastify'
import { Plus, Pencil, Trash2, Key } from 'lucide-vue-next'
import type { Role } from '@/types/accessSettings'

interface Props {
  roles: Role[]
  permissionKey: string
  loading: boolean
}

interface Emits {
  (e: 'open-role-modal', role?: Role): void
  (e: 'open-assignment-modal', role: Role): void
  (e: 'refresh'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { canCreate, canUpdate, canDelete } = usePermission()
const accessStore = useAccessSettingsStore()

const deleteRole = async (roleId: number) => {
  if (!confirm('Are you sure you want to delete this role?')) return

  try {
    await accessStore.deleteRole(roleId)
    toast.success('Role deleted successfully')
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to delete role')
    console.error(error)
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Roles Management</h2>
      <button
        v-if="canCreate(permissionKey)"
        @click="emit('open-role-modal')"
        class="flex items-center space-x-2 px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors"
      >
        <Plus :size="18" />
        <span class="text-sm">Add Role</span>
      </button>
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      <div class="overflow-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-white dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Permissions</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class=" divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 dark:border-blue-400 mb-2"></div>
                  Loading roles...
                </div>
              </td>
            </tr>
            <tr v-else-if="roles.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No roles found
              </td>
            </tr>
            <tr v-else v-for="role in roles" :key="role.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ role.name }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ role.description || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span 
                  v-if="role.is_system_role"
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300"
                >
                  System
                </span>
                <span 
                  v-else
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300"
                >
                  Custom
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300">
                  {{ role.permissions?.length || 0 }} permissions
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button
                  @click="emit('open-assignment-modal', role)"
                  class="text-purple-600 dark:text-purple-400 hover:text-purple-900 dark:hover:text-purple-300"
                  title="Assign Permissions"
                >
                  <Key :size="18" />
                </button>
                <button
                  v-if="canUpdate(permissionKey) && !role.is_system_role"
                  @click="emit('open-role-modal', role)"
                  class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300"
                  title="Edit"
                >
                  <Pencil :size="18" />
                </button>
                <button
                  v-if="canDelete(permissionKey) && !role.is_system_role"
                  @click="deleteRole(role.id)"
                  class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                  title="Delete"
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