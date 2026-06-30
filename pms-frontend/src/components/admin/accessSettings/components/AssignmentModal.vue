<script lang="ts" setup>
import { ref, computed, watch } from 'vue'
import { useAccessSettingsStore } from '@/store/accessSettings'
import { toast } from 'vue3-toastify'
import { Lock, Save, X } from 'lucide-vue-next'
import type { Role, Permission } from '@/types/accessSettings'

interface Props {
  role: Role | null
  permissions: Permission[]
}

interface Emits {
  (e: 'close'): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const accessStore = useAccessSettingsStore()

// State
const selectedPermissionIds = ref<Set<number>>(new Set())

const loading = computed(() => accessStore.loading)
const isSuperAdmin = computed(() => props.role?.name.toLowerCase() === 'superadmin')

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
    description: 'Project proposals, SOI flow, requirements, files, teams, and lifecycle records.',
  },
  project_map: {
    label: 'Project Map',
    description: 'Map pins, coordinates, and project image thumbnails.',
  },
  tasks: {
    label: 'Tasks',
    description: 'Work plans, subtasks, urgency, status history, and progress updates.',
  },
  profile: {
    label: 'Own Profile',
    description: 'Own profile, company/proponent details, previous projects, avatar, and password.',
  },
  employee_profile: {
    label: 'User Profile View',
    description: 'Admin view of users or proponents for account and proposal review.',
  },
  organization: {
    label: 'Organization & Accounts',
    description: 'Users, account approval, departments, roles, and proponent confirmation.',
  },
  users: {
    label: 'Users',
    description: 'User records used for team assignment, task assignment, and routing.',
  },
  admin_tools: {
    label: 'Admin Tools Menu',
    description: 'Administrative navigation group in the sidebar.',
  },
  access_settings: {
    label: 'Access Settings',
    description: 'RBAC roles, permission modules, and permission assignment.',
  },
  system_settings: {
    label: 'System Settings',
    description: 'Application-level settings and configuration controls.',
  },
  activity_logs: {
    label: 'Activity Logs',
    description: 'Audit trail and system activity history.',
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

const actionOrder = ['view', 'create', 'update', 'delete'] as const
const actionLabels: Record<string, string> = {
  view: 'View',
  create: 'Create',
  update: 'Update',
  delete: 'Delete',
}

// Group permissions by resource for assignment modal
const groupedPermissions = computed(() => {
  const groups: Record<string, { resource: string; permissions: Permission[] }> = {}
  
  props.permissions.forEach(permission => {
    if (!groups[permission.resource]) {
      groups[permission.resource] = {
        resource: permission.resource,
        permissions: [],
      }
    }
    groups[permission.resource].permissions.push(permission)
  })
  
  return Object.values(groups)
    .map((group) => ({
      ...group,
      permissions: [...group.permissions].sort((a, b) => actionOrder.indexOf(a.action as any) - actionOrder.indexOf(b.action as any)),
    }))
    .sort((a, b) => moduleRank(a.resource) - moduleRank(b.resource) || moduleLabel(a.resource).localeCompare(moduleLabel(b.resource)))
})

const moduleRank = (resource: string) => {
  const index = moduleOrder.indexOf(resource)
  return index === -1 ? moduleOrder.length : index
}

const moduleLabel = (resource: string) => moduleMeta[resource]?.label || titleize(resource)
const moduleDescription = (resource: string) => moduleMeta[resource]?.description || 'Custom permission module.'
const titleize = (value: string) => value.split('_').map(part => part.charAt(0).toUpperCase() + part.slice(1)).join(' ')

// Initialize selected permissions when role changes
watch(() => props.role, (role) => {
  if (role) {
    selectedPermissionIds.value = new Set(
      role.permissions?.map(p => p.id) || []
    )
  } else {
    selectedPermissionIds.value = new Set()
  }
}, { immediate: true })

const togglePermission = (permissionId: number) => {
  if (selectedPermissionIds.value.has(permissionId)) {
    selectedPermissionIds.value.delete(permissionId)
  } else {
    selectedPermissionIds.value.add(permissionId)
  }
}

const closeModal = () => {
  emit('close')
}

const handleSubmit = async () => {
  if (!props.role) return

  try {
    const permissionIds = Array.from(selectedPermissionIds.value)
    await accessStore.syncPermissions(props.role.id, permissionIds)
    toast.success('Permissions updated successfully')
    emit('saved')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to update permissions')
    console.error(error)
  }
}
</script>

<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70 flex items-center justify-center z-50 p-4 !mt-0">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden border border-gray-200 dark:border-gray-700">
      
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
        <div>
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            Assign Permissions
          </h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Manage permissions for <span class="font-medium text-blue-600 dark:text-blue-400">{{ role?.name }}</span>
          </p>
          <p v-if="isSuperAdmin" class="mt-2 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
            <Lock :size="12" />
            Super Admin should keep full system access.
          </p>
        </div>
        <button
          @click="closeModal"
          class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
          :disabled="loading"
        >
          <X :size="20" class="text-gray-500 dark:text-gray-400" />
        </button>
      </div>
      
      <!-- Loading Overlay -->
      <div v-if="loading" class="absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 dark:bg-opacity-75 flex items-center justify-center z-10 rounded-lg">
        <div class="flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">Updating permissions...</span>
        </div>
      </div>
      
      <!-- Form Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-220px)] modal-scroll">
        <div class="space-y-4">
          <div
            v-for="group in groupedPermissions"
            :key="group.resource"
            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-700/50"
          >
            <div class="mb-3">
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center">
                <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                {{ moduleLabel(group.resource) }}
                <span class="ml-2 rounded bg-white px-1.5 py-0.5 font-mono text-[10px] text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ group.resource }}</span>
              </h4>
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ moduleDescription(group.resource) }}</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div 
                v-for="permission in group.permissions"
                :key="permission.id" 
                class="flex items-start space-x-3 p-2 rounded hover:bg-white dark:hover:bg-gray-700 transition-colors"
              >
                <input
                  type="checkbox"
                  :id="`perm-${permission.id}`"
                  :checked="selectedPermissionIds.has(permission.id)"
                  @change="togglePermission(permission.id)"
                  class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 mt-0.5"
                />
                <label :for="`perm-${permission.id}`" class="text-sm text-gray-700 dark:text-gray-300 flex-1 cursor-pointer">
                  <span class="font-medium">{{ actionLabels[permission.action] || permission.action }}</span>
                  <span v-if="permission.description" class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ permission.description }}
                  </span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="flex justify-between items-center gap-3 p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          <span class="font-medium text-gray-900 dark:text-white">{{ selectedPermissionIds.size }}</span> 
          permission(s) selected
        </p>
        
        <div class="flex gap-3">
          <button
            @click="closeModal"
            class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm font-medium disabled:opacity-50"
            :disabled="loading"
          >
            Cancel
          </button>
          <button
            @click="handleSubmit"
            class="px-4 py-2.5 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors flex items-center gap-2 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loading"
          >
            <svg v-if="loading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <Save v-else :size="16" />
            <span>Save Permissions</span>
          </button>
        </div>
      </div>
      
    </div>
  </div>
</template>
