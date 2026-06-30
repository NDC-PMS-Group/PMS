<script lang="ts" setup>
import { computed, onActivated, onMounted, ref } from 'vue'
import { toast } from 'vue3-toastify'
import { Key, RefreshCw, Shield, Users, GitMerge } from 'lucide-vue-next'
import { useAccessSettingsStore } from '@/store/accessSettings'
import type { Role } from '@/types/accessSettings'

import RolesTab from './components/RolesTab.vue'
import PermissionsTab from './components/PermissionsTab.vue'
import SoiWorkflowsTab from './components/SoiWorkflowsTab.vue'
import RoleModal from './components/RoleModal.vue'
import PermissionModal from './components/PermissionModal.vue'
import AssignmentModal from './components/AssignmentModal.vue'

const permissionKey = 'access_settings'
const accessStore = useAccessSettingsStore()

const loading = computed(() => accessStore.loading)
const activeTab = ref<'roles' | 'permissions' | 'workflows'>('roles')

const showRoleModal = ref(false)
const showPermissionModal = ref(false)
const showAssignmentModal = ref(false)
const editingRole = ref<Role | undefined>(undefined)

const roles = computed(() => accessStore.roles)
const permissions = computed(() => accessStore.permissions)
const selectedRole = computed(() => accessStore.selectedRole)

const accessSummary = computed(() => [
  {
    label: 'Roles configured',
    value: roles.value.length,
    help: 'System and custom roles',
    icon: Shield,
  },
  {
    label: 'Permission rules',
    value: permissions.value.length,
    help: 'Module action grants',
    icon: Key,
  },
  {
    label: 'Assigned users',
    value: roles.value.reduce((total, role) => total + (role.users_count || 0), 0),
    help: 'Users attached to roles',
    icon: Users,
  },
])

const fetchData = async () => {
  try {
    await Promise.all([
      accessStore.fetchRoles(),
      accessStore.fetchPermissions(),
    ])
  } catch (error: any) {
    toast.error('Failed to fetch access settings')
    console.error(error)
  }
}

const openRoleModal = (role?: Role) => {
  editingRole.value = role
  showRoleModal.value = true
}

const closeRoleModal = () => {
  showRoleModal.value = false
  editingRole.value = undefined
}

const handleRoleSaved = () => {
  closeRoleModal()
  fetchData()
}

const openPermissionModal = () => {
  showPermissionModal.value = true
}

const closePermissionModal = () => {
  showPermissionModal.value = false
}

const handlePermissionsSaved = () => {
  closePermissionModal()
  fetchData()
}

const openAssignmentModal = (role: Role) => {
  accessStore.setSelectedRole(role)
  showAssignmentModal.value = true
}

const closeAssignmentModal = () => {
  showAssignmentModal.value = false
  accessStore.setSelectedRole(null)
}

const handleAssignmentSaved = () => {
  closeAssignmentModal()
  fetchData()
}

onMounted(fetchData)
onActivated(fetchData)
</script>

<template>
  <div class="space-y-6 p-0 md:p-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-wide text-blue-600 dark:text-blue-400">Role-based access control</p>
          <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Access Settings</h1>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400">
            Manage the PMS roles used by project intake, SOI approvals, document submission, work plans,
            reports, maps, and administration. Project visibility still follows creator, proponent, team,
            and current workflow-step rules.
          </p>
        </div>

        <button
          class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-500 hover:text-blue-600 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:border-blue-400 dark:hover:text-blue-300"
          :disabled="loading"
          @click="fetchData"
        >
          <RefreshCw :size="16" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div
          v-for="item in accessSummary"
          :key="item.label"
          class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"
        >
          <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
              <component :is="item.icon" :size="18" />
            </span>
            <div>
              <div class="text-xl font-bold text-slate-950 dark:text-white">{{ item.value }}</div>
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ item.label }}</div>
            </div>
          </div>
          <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ item.help }}</p>
        </div>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div class="border-b border-slate-200 px-5 dark:border-slate-700">
        <nav class="flex gap-6 overflow-x-auto">
          <button
            @click="activeTab = 'roles'"
            :class="[
              'flex items-center gap-2 border-b-2 px-1 py-4 text-sm font-semibold transition-colors',
              activeTab === 'roles'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200',
            ]"
          >
            <Shield :size="18" />
            <span>Roles</span>
          </button>
          <button
            @click="activeTab = 'permissions'"
            :class="[
              'flex items-center gap-2 border-b-2 px-1 py-4 text-sm font-semibold transition-colors',
              activeTab === 'permissions'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200',
            ]"
          >
            <Key :size="18" />
            <span>Permission Modules</span>
          </button>
          <button
            @click="activeTab = 'workflows'"
            :class="[
              'flex items-center gap-2 border-b-2 px-1 py-4 text-sm font-semibold transition-colors',
              activeTab === 'workflows'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200',
            ]"
          >
            <GitMerge :size="18" />
            <span>SOI Workflows</span>
          </button>
        </nav>
      </div>

      <div class="p-5">
        <RolesTab
          v-if="activeTab === 'roles'"
          :roles="roles"
          :loading="loading"
          :permission-key="permissionKey"
          @open-role-modal="openRoleModal"
          @open-assignment-modal="openAssignmentModal"
          @refresh="fetchData"
        />

        <PermissionsTab
          v-if="activeTab === 'permissions'"
          :permissions="permissions"
          :loading="loading"
          :permission-key="permissionKey"
          @open-permission-modal="openPermissionModal"
          @refresh="fetchData"
        />

        <SoiWorkflowsTab
          v-if="activeTab === 'workflows'"
          :roles="roles"
          :permission-key="permissionKey"
          @refresh="fetchData"
        />
      </div>
    </div>

    <RoleModal
      v-if="showRoleModal"
      :role="editingRole"
      @close="closeRoleModal"
      @saved="handleRoleSaved"
    />

    <PermissionModal
      v-if="showPermissionModal"
      @close="closePermissionModal"
      @saved="handlePermissionsSaved"
    />

    <AssignmentModal
      v-if="showAssignmentModal"
      :role="selectedRole"
      :permissions="permissions"
      @close="closeAssignmentModal"
      @saved="handleAssignmentSaved"
    />
  </div>
</template>
