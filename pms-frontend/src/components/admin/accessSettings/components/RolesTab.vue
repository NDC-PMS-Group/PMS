<script lang="ts" setup>
import { computed } from 'vue'
import { usePermission } from '@/composables/usePermission'
import { useAccessSettingsStore } from '@/store/accessSettings'
import { toast } from 'vue3-toastify'
import { Plus, Pencil, Trash2, Key, ShieldCheck } from 'lucide-vue-next'
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

const roleOrder = [
  'superadmin',
  'Project Officer',
  'Workgroup Head',
  'Investment Committee',
  'Legal and Finance',
  'ManCom',
  'Board',
  'Proponent',
  'Supervisor',
  'Staff',
]

const roleMeta: Record<string, { purpose: string; workflow: string }> = {
  superadmin: {
    purpose: 'Can administer the full PMS: users, projects, workflow, reports, maps, system settings, and access control.',
    workflow: 'Override/admin access across the system.',
  },
  'project officer': {
    purpose: 'Handles intake, completeness checks, due diligence, project updates, and assigned work-plan items.',
    workflow: 'Receives Project Officer steps in the SOI flow.',
  },
  'workgroup head': {
    purpose: 'Reviews workgroup recommendations and supervises project actions before ManCom or Board routing.',
    workflow: 'Receives Workgroup Head approval steps.',
  },
  'investment committee': {
    purpose: 'Reviews investment evaluation outputs, recommendations, risks, and decision materials.',
    workflow: 'Receives IC evaluation and endorsement steps.',
  },
  'legal and finance': {
    purpose: 'Reviews legal, finance, compliance, fund-release, agreement, and monitoring requirements.',
    workflow: 'Receives legal/finance review steps and document checks.',
  },
  mancom: {
    purpose: 'Reviews management committee decision packages and approval recommendations.',
    workflow: 'Receives ManCom approval steps.',
  },
  board: {
    purpose: 'Reviews Board approval packages, conditions, and final decision records.',
    workflow: 'Receives Board approval steps.',
  },
  proponent: {
    purpose: 'External company/proponent access for proposal submission, requirements, files, and returned revisions.',
    workflow: 'Acts only on owned/submitted projects and requested revisions.',
  },
  supervisor: {
    purpose: 'Monitors assigned project execution and can update operational tasks.',
    workflow: 'Works within assigned projects and task scopes.',
  },
  staff: {
    purpose: 'Works on assigned tasks and views allowed project records.',
    workflow: 'Works within assigned project membership.',
  },
}

const sortedRoles = computed(() => {
  return [...props.roles].sort((a, b) => roleRank(a) - roleRank(b) || a.name.localeCompare(b.name))
})

const roleRank = (role: Role) => {
  const index = roleOrder.findIndex((name) => name.toLowerCase() === role.name.toLowerCase())
  return index === -1 ? roleOrder.length + role.id : index
}

const rolePurpose = (role: Role) => {
  return roleMeta[role.name.toLowerCase()]?.purpose || role.description || 'Custom role. Permission assignment controls access.'
}

const roleWorkflow = (role: Role) => {
  return roleMeta[role.name.toLowerCase()]?.workflow || 'Uses project membership, ownership, and assigned permission rules.'
}

const roleAccessLevel = (role: Role) => {
  const count = role.permissions?.length || 0
  if (role.name.toLowerCase() === 'superadmin') return 'Full'
  if (count >= 16) return 'Broad'
  if (count >= 8) return 'Operational'
  if (count > 0) return 'Limited'
  return 'Unconfigured'
}

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
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Roles Management</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Assign module permissions to the roles used by the PMS workflow.</p>
      </div>
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
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Purpose in PMS</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Workflow Use</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Access</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Users</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class=" divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 dark:border-blue-400 mb-2"></div>
                  Loading roles...
                </div>
              </td>
            </tr>
            <tr v-else-if="roles.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                No roles found
              </td>
            </tr>
            <tr v-else v-for="role in sortedRoles" :key="role.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <ShieldCheck v-if="role.name.toLowerCase() === 'superadmin'" :size="16" class="text-amber-500" />
                  <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ role.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ role.permissions?.length || 0 }} permissions</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 min-w-[280px]">
                <div class="text-sm text-gray-700 dark:text-gray-300">{{ rolePurpose(role) }}</div>
              </td>
              <td class="px-6 py-4 min-w-[220px]">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ roleWorkflow(role) }}</div>
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
                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300">
                  {{ roleAccessLevel(role) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                  {{ role.users_count || 0 }} users
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
