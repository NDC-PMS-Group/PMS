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

const moduleOrder = [
  'dashboard',
  'projects',
  'project_map',
  'tasks',
  'profile',
  'employee_profile',
  'organization',
  'users',
  'admin_tools',
  'access_settings',
  'system_settings',
  'activity_logs',
  'documents',
  'reports',
]

const moduleMeta: Record<string, { label: string; description: string }> = {
  dashboard: {
    label: 'Dashboard',
    description: 'Operational dashboard, pending actions, analytics, and monitoring overview.',
  },
  projects: {
    label: 'Projects',
    description: 'Project proposals, SOI flow, requirements, files, teams, tasks, and lifecycle records.',
  },
  project_map: {
    label: 'Project Map',
    description: 'Map view, project location pins, coordinates, and project image thumbnails.',
  },
  tasks: {
    label: 'Tasks',
    description: 'Work-plan tasks, subtasks, urgency, status history, and progress updates.',
  },
  profile: {
    label: 'Own Profile',
    description: 'User profile, proponent/company details, previous projects, avatar, and password.',
  },
  employee_profile: {
    label: 'User Profile View',
    description: 'Admin view of another user or proponent profile for evaluation and account review.',
  },
  organization: {
    label: 'Organization & Accounts',
    description: 'Users, account approval, departments, role assignment, and proponent confirmation.',
  },
  users: {
    label: 'Users',
    description: 'User records used for team assignment, task assignment, and approval routing.',
  },
  admin_tools: {
    label: 'Admin Tools Menu',
    description: 'Allows the administrative navigation group to appear in the sidebar.',
  },
  access_settings: {
    label: 'Access Settings',
    description: 'Role-based access control, permission modules, and permission assignment.',
  },
  system_settings: {
    label: 'System Settings',
    description: 'Application-level settings and configuration controls.',
  },
  activity_logs: {
    label: 'Activity Logs',
    description: 'Audit trail of logins, profile updates, project changes, and system activity.',
  },
  documents: {
    label: 'Documents & Files',
    description: 'Project attachments, draft upload, submit, request update, and download actions.',
  },
  reports: {
    label: 'Reports',
    description: 'Project exports, filters, financial data, GCG indicators, and reportable projects.',
  },
}

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

  return Object.values(groups).sort((a, b) => moduleRank(a.resource) - moduleRank(b.resource) || moduleLabel(a.resource).localeCompare(moduleLabel(b.resource)))
})

const moduleRank = (resource: string) => {
  const index = moduleOrder.indexOf(resource)
  return index === -1 ? moduleOrder.length : index
}

const moduleLabel = (resource: string) => {
  return moduleMeta[resource]?.label || titleize(resource)
}

const moduleDescription = (resource: string, fallback?: string | null) => {
  return moduleMeta[resource]?.description || fallback || 'Custom permission module.'
}

const titleize = (value: string) => value.split('_').map(part => part.charAt(0).toUpperCase() + part.slice(1)).join(' ')

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
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Permission Modules</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Current PMS modules and the available actions for each role.</p>
      </div>
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
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Module</th>
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
            <tr v-else v-for="group in groupedPermissionsForDisplay" :key="group.resource" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ moduleLabel(group.resource) }}</div>
                <div class="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400">{{ group.resource }}</div>
              </td>
              <td class="px-6 py-4 min-w-[320px]">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ moduleDescription(group.resource, group.description) }}</div>
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
