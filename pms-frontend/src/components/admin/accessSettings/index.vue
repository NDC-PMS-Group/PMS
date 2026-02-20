<script lang="ts" setup>
import { ref, onMounted, computed, onActivated } from 'vue'
import { useAccessSettingsStore } from '@/store/accessSettings'
import { toast } from 'vue3-toastify'
import { Shield, Key } from 'lucide-vue-next'
import type { Role } from '@/types/accessSettings'

// Components
import RolesTab from './components/RolesTab.vue'
import PermissionsTab from './components/PermissionsTab.vue'
import RoleModal from './components/RoleModal.vue'
import PermissionModal from './components/PermissionModal.vue'
import AssignmentModal from './components/AssignmentModal.vue'

const permissionKey = 'access_settings'
const accessStore = useAccessSettingsStore()

const loading = computed(() => accessStore.loading)

// State
const activeTab = ref<'roles' | 'permissions'>('roles')

// Modal states
const showRoleModal = ref(false)
const showPermissionModal = ref(false)
const showAssignmentModal = ref(false)

// Role state
const editingRole = ref<Role | undefined>(undefined)

// Computed
const roles = computed(() => accessStore.roles)
const permissions = computed(() => accessStore.permissions)
const selectedRole = computed(() => accessStore.selectedRole)

// Fetch Data
const fetchData = async () => {
  try {
    await Promise.all([
      accessStore.fetchRoles(),
      accessStore.fetchPermissions()
    ])
  } catch (error: any) {
    toast.error('Failed to fetch data')
    console.error(error)
  }
}

// Role Modal Handlers
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

// Permission Modal Handlers
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

// Assignment Modal Handlers
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

// Lifecycle
onMounted(() => {
  fetchData()
})

onActivated(() => {
  fetchData()
})
</script>

<template>
  <div class="space-y-6 p-0 md:p-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Access Settings</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage roles, permissions, and access control</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="flex space-x-8">
        <button
          @click="activeTab = 'roles'"
          :class="[
            'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
            activeTab === 'roles'
              ? 'border-blue-500 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
          ]"
        >
          <div class="flex items-center space-x-2">
            <Shield :size="18" />
            <span>Roles</span>
          </div>
        </button>
        <button
          @click="activeTab = 'permissions'"
          :class="[
            'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
            activeTab === 'permissions'
              ? 'border-blue-500 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'
          ]"
        >
          <div class="flex items-center space-x-2">
            <Key :size="18" />
            <span>Permissions</span>
          </div>
        </button>
      </nav>
    </div>

    <!-- Roles Tab -->
    <RolesTab
      v-if="activeTab === 'roles'"
      :roles="roles"
      :loading="loading"
      :permission-key="permissionKey"
      @open-role-modal="openRoleModal"
      @open-assignment-modal="openAssignmentModal"
      @refresh="fetchData"
    />

    <!-- Permissions Tab -->
    <PermissionsTab
      v-if="activeTab === 'permissions'"
      :permissions="permissions"
      :loading="loading"
      :permission-key="permissionKey"
      @open-permission-modal="openPermissionModal"
      @refresh="fetchData"
    />

    <!-- Modals -->
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