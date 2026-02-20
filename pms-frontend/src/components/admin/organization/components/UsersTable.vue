<script lang="ts" setup>
import { ref, computed } from 'vue'
import { usePermission } from '@/composables/usePermission'
import { useUserStore } from '@/store/user'
import { toast } from 'vue3-toastify'
import {
  Plus, Pencil, Trash2, Search,
  ChevronLeft, ChevronRight, UserX, UserCheck
} from 'lucide-vue-next'
import type { User } from '@/types/user'
import type { Role } from '@/types/accessSettings'
import UserStatusBadge from './UserStatusBadge.vue'

interface Props {
  users: User[]
  roles: Role[]
  loading: boolean
  permissionKey: string
  currentPage: number
  lastPage: number
  total: number
  perPage: number
  from: number | null
  to: number | null
}

interface Emits {
  (e: 'open-user-modal', user?: User): void
  (e: 'refresh'): void
  (e: 'page-change', page: number): void
  (e: 'filter-change', filters: Record<string, any>): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { canCreate, canUpdate, canDelete } = usePermission()
const userStore = useUserStore()

// Local filter state
const search = ref('')
const selectedRoleId = ref<number | null>(null)
const selectedStatus = ref<string>('')

// Search/filter handler
let searchTimeout: ReturnType<typeof setTimeout>
const onSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    emitFilters()
  }, 400)
}

const onFilterChange = () => {
  emitFilters()
}

// Always emit ALL filter values — including empty string for search
// so the store properly clears old values when fields are emptied
const emitFilters = () => {
  const filters: Record<string, any> = {
    search:  search.value,
    role_id: selectedRoleId.value ?? null,
  }

  if (selectedStatus.value !== '') {
    filters.is_active = selectedStatus.value === 'active'
  } else {
    filters.is_active = undefined
  }

  emit('filter-change', filters)
}

const resetFilters = () => {
  search.value = ''
  selectedRoleId.value = null
  selectedStatus.value = ''
  emitFilters()
}

const hasActiveFilters = computed(() => {
  return search.value || selectedRoleId.value != null || selectedStatus.value !== ''
})

// Toggle status
const toggleStatus = async (user: User) => {
  const action = user.is_active ? 'deactivate' : 'activate'
  if (!confirm(`Are you sure you want to ${action} ${user.full_name}?`)) return

  try {
    await userStore.toggleStatus(user.id, !user.is_active)
    toast.success(`User ${action}d successfully`)
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || `Failed to ${action} user`)
    console.error(error)
  }
}

// Deactivate (destroy)
const deactivateUser = async (user: User) => {
  if (!confirm(`Are you sure you want to deactivate ${user.full_name}? This action cannot be undone.`)) return

  try {
    await userStore.deactivateUser(user.id)
    toast.success('User deactivated successfully')
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to deactivate user')
    console.error(error)
  }
}
</script>

<template>
  <div class="space-y-4">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="flex flex-col sm:flex-row gap-3 flex-1">

        <!-- Search -->
        <div class="relative flex-1 max-w-sm">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none" />
          <input
            v-model="search"
            @input="onSearchInput"
            type="text"
            placeholder="Search users..."
            class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent transition-colors"
          />
        </div>

        <!-- Role Filter -->
        <select
          v-model="selectedRoleId"
          @change="onFilterChange"
          class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent transition-colors"
        >
          <option :value="null">All Roles</option>
          <option v-for="role in roles" :key="role.id" :value="role.id">
            {{ role.name }}
          </option>
        </select>

        <!-- Status Filter -->
        <select
          v-model="selectedStatus"
          @change="onFilterChange"
          class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent transition-colors"
        >
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>

        <!-- Reset Filters -->
        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline transition-colors whitespace-nowrap"
        >
          Clear filters
        </button>
      </div>

      <!-- Add User Button -->
      <button
        v-if="canCreate(permissionKey)"
        @click="emit('open-user-modal')"
        class="flex items-center space-x-2 px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white text-sm rounded-lg hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors whitespace-nowrap"
      >
        <Plus :size="16" />
        <span>Add User</span>
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="overflow-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Login</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">

            <!-- Loading State -->
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 dark:border-blue-400 mb-2"></div>
                  <span class="text-sm">Loading users...</span>
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="users.length === 0">
              <td colspan="6" class="px-6 py-10 text-center">
                <div class="flex flex-col items-center text-gray-400 dark:text-gray-500">
                  <Search :size="32" class="mb-2 opacity-50" />
                  <p class="text-sm font-medium">No users found</p>
                  <p v-if="hasActiveFilters" class="text-xs mt-1">Try adjusting your filters</p>
                </div>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr
              v-else
              v-for="user in users"
              :key="user.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-3">
                  <img
                    :src="user.profile_photo"
                    :alt="user.initials"
                    class="h-8 w-8 rounded-full object-cover bg-gray-200 dark:bg-gray-700 flex-shrink-0"
                  />
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.full_name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm text-gray-700 dark:text-gray-300 font-mono">{{ user.username }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  v-if="user.role"
                  class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300"
                >
                  {{ user.role.name }}
                </span>
                <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UserStatusBadge :is-active="user.is_active" />
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ user.last_login ?? '—' }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button
                  v-if="canUpdate(permissionKey) && user.role?.name !== 'superadmin'"
                  @click="toggleStatus(user)"
                  :title="user.is_active ? 'Deactivate' : 'Activate'"
                  :class="[
                    'transition-colors',
                    user.is_active
                      ? 'text-yellow-500 dark:text-yellow-400 hover:text-yellow-700 dark:hover:text-yellow-300'
                      : 'text-green-500 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300'
                  ]"
                >
                  <UserX v-if="user.is_active" :size="18" />
                  <UserCheck v-else :size="18" />
                </button>
                <button
                  v-if="canUpdate(permissionKey) && user.role?.name !== 'superadmin'"
                  @click="emit('open-user-modal', user)"
                  title="Edit"
                  class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 transition-colors"
                >
                  <Pencil :size="18" />
                </button>
                <button
                  v-if="canDelete(permissionKey) && user.is_active && user.role?.name !== 'superadmin'"
                  @click="deactivateUser(user)"
                  title="Deactivate"
                  class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 transition-colors"
                >
                  <Trash2 :size="18" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="!loading && users.length > 0"
        class="px-6 py-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-700"
      >
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Showing
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ from }}</span>
          to
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ to }}</span>
          of
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ total }}</span>
          results
        </p>
        <div class="flex items-center space-x-1">
          <button
            @click="emit('page-change', currentPage - 1)"
            :disabled="currentPage <= 1"
            class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <ChevronLeft :size="18" />
          </button>
          <span class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300">
            Page {{ currentPage }} of {{ lastPage }}
          </span>
          <button
            @click="emit('page-change', currentPage + 1)"
            :disabled="currentPage >= lastPage"
            class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <ChevronRight :size="18" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>