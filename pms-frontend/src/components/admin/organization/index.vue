<script lang="ts" setup>
import { ref, computed, onMounted, onActivated } from 'vue'
import { useUserStore } from '@/store/user'
import { useAccessSettingsStore } from '@/store/accessSettings'
import { toast } from 'vue3-toastify'
import { Users } from 'lucide-vue-next'
import type { User } from '@/types/user'

// Components
import UsersTable from './components/UsersTable.vue'
import UserModal from './components/UserModal.vue'

const permissionKey = 'users'

const userStore = useUserStore()
const accessStore = useAccessSettingsStore()

// Modal state
const showUserModal = ref(false)
const editingUser = ref<User | undefined>(undefined)

// Computed from store
const users      = computed(() => userStore.users)
const roles      = computed(() => accessStore.roles)
const loading    = computed(() => userStore.loading)
const pagination = computed(() => userStore.pagination)

// Fetch initial data
const fetchData = async () => {
  try {
    await Promise.all([
      userStore.fetchUsers(),
      accessStore.fetchRoles(),
    ])
  } catch (error: any) {
    toast.error('Failed to load users')
    console.error(error)
  }
}

// Modal handlers
const openUserModal = (user?: User) => {
  editingUser.value = user
  showUserModal.value = true
}

const closeUserModal = () => {
  showUserModal.value = false
  editingUser.value = undefined
}

const handleUserSaved = () => {
  closeUserModal()
  fetchData()
}

// Filter handler from table
const handleFilterChange = async (filters: Record<string, any>) => {
  try {
    await userStore.setFilters(filters)
  } catch (error: any) {
    toast.error('Failed to apply filters')
    console.error(error)
  }
}

// Pagination handler from table
const handlePageChange = async (page: number) => {
  try {
    await userStore.goToPage(page)
  } catch (error: any) {
    toast.error('Failed to load page')
    console.error(error)
  }
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
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Organization</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Manage users, roles, and account status
        </p>
      </div>
      <div class="flex items-center space-x-2 text-gray-400 dark:text-gray-500">
        <Users :size="20" />
        <span class="text-sm font-medium">
          {{ pagination?.total ?? 0 }} user{{ (pagination?.total ?? 0) !== 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <!-- Users Table -->
    <UsersTable
      :users="users"
      :roles="roles"
      :loading="loading"
      :permission-key="permissionKey"
      :current-page="pagination?.current_page ?? 1"
      :last-page="pagination?.last_page ?? 1"
      :total="pagination?.total ?? 0"
      :per-page="pagination?.per_page ?? 15"
      :from="pagination?.from ?? null"
      :to="pagination?.to ?? null"
      @open-user-modal="openUserModal"
      @refresh="fetchData"
      @page-change="handlePageChange"
      @filter-change="handleFilterChange"
    />

    <!-- User Modal -->
    <UserModal
      v-if="showUserModal"
      :user="editingUser"
      :roles="roles"
      @close="closeUserModal"
      @saved="handleUserSaved"
    />

  </div>
</template>