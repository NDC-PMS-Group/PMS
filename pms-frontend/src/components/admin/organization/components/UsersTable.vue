<script lang="ts" setup>
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePermission } from '@/composables/usePermission'
import { useUserStore } from '@/store/user'
import { toast } from 'vue3-toastify'
import {
  Plus, Search, ChevronLeft, ChevronRight,
  UserX, UserCheck, MoreVertical, Eye, Pencil, Trash2,
} from 'lucide-vue-next'
import type { User } from '@/types/user'
import type { Role } from '@/types/accessSettings'
import UserStatusBadge from './UserStatusBadge.vue'
import UserAvatar from '@/app/common/UserAvatar.vue'

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

const router = useRouter()
const { canCreate, canUpdate, canDelete } = usePermission()
const userStore = useUserStore()

// Filters
const search = ref('')
const selectedRoleId = ref<number | null>(null)
const selectedStatus = ref<string>('')

let searchTimeout: ReturnType<typeof setTimeout>
const onSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => emitFilters(), 400)
}
const onFilterChange = () => emitFilters()

const emitFilters = () => {
  const filters: Record<string, any> = {
    search: search.value,
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

const hasActiveFilters = computed(() =>
  search.value || selectedRoleId.value != null || selectedStatus.value !== ''
)

// Teleported dropdown
const openMenuId = ref<number | null>(null)
const menuUser = ref<User | null>(null)
const menuPos = ref({ top: 0, left: 0 })

function openMenu(event: MouseEvent, user: User) {
  if (openMenuId.value === user.id) {
    closeMenu()
    return
  }
  const btn = event.currentTarget as HTMLElement
  const rect = btn.getBoundingClientRect()
  const dropdownW = 176
  const dropdownH = 185
  let left = rect.right - dropdownW
  let top = rect.bottom + 6
  if (left < 8) left = 8
  if (left + dropdownW > window.innerWidth - 8) left = window.innerWidth - dropdownW - 8
  if (top + dropdownH > window.innerHeight - 8) top = rect.top - dropdownH - 6
  menuPos.value = { top, left }
  openMenuId.value = user.id
  menuUser.value = user
}

function closeMenu() {
  openMenuId.value = null
  menuUser.value = null
}

window.addEventListener('scroll', closeMenu, true)
window.addEventListener('resize', closeMenu)
onUnmounted(() => {
  window.removeEventListener('scroll', closeMenu, true)
  window.removeEventListener('resize', closeMenu)
})

function viewProfile(user: User) {
  closeMenu()
  router.push({ name: 'Employee Profile', params: { id: user.id } })
}

const toggleStatus = async (user: User) => {
  closeMenu()
  const action = user.is_active ? 'deactivate' : 'activate'
  if (!confirm(`Are you sure you want to ${action} ${user.full_name}?`)) return
  try {
    await userStore.toggleStatus(user.id, !user.is_active)
    toast.success(`User ${action}d successfully`)
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || `Failed to ${action} user`)
  }
}

const deactivateUser = async (user: User) => {
  closeMenu()
  if (!confirm(`Are you sure you want to deactivate ${user.full_name}? This action cannot be undone.`)) return
  try {
    await userStore.deactivateUser(user.id)
    toast.success('User deactivated successfully')
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to deactivate user')
  }
}

const openEdit = (user: User) => {
  closeMenu()
  emit('open-user-modal', user)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="flex flex-col sm:flex-row gap-3 flex-1">
        <div class="relative flex-1 max-w-sm">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none" />
          <input v-model="search" @input="onSearchInput" type="text" placeholder="Search users..."
            class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors" />
        </div>
        <select v-model="selectedRoleId" @change="onFilterChange"
          class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
          <option :value="null">All Roles</option>
          <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
        <select v-model="selectedStatus" @change="onFilterChange"
          class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <button v-if="hasActiveFilters" @click="resetFilters"
          class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline transition-colors whitespace-nowrap">
          Clear filters
        </button>
      </div>
      <button v-if="canCreate(permissionKey)" @click="emit('open-user-modal')"
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white text-sm rounded-lg hover:bg-blue-700 dark:hover:bg-blue-600 transition-colors whitespace-nowrap">
        <Plus :size="16" /><span>Add User</span>
      </button>
    </div>

    <!-- Table -->
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      <div class="overflow-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-white dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Login</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 dark:border-blue-400 mb-2" />
                  <span class="text-sm">Loading users...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="6" class="px-6 py-10 text-center">
                <div class="flex flex-col items-center text-gray-400 dark:text-gray-500">
                  <Search :size="32" class="mb-2 opacity-50" />
                  <p class="text-sm font-medium">No users found</p>
                  <p v-if="hasActiveFilters" class="text-xs mt-1">Try adjusting your filters</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="user in users" :key="user.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
              <!-- Avatar -->
              <td class="px-6 py-4 whitespace-nowrap">
                <button @click="viewProfile(user)"
                  class="block rounded-full transition-all hover:grayscale hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                  :title="`View ${user.full_name}'s profile`">
                  <UserAvatar :user="user" :size="36" />
                </button>
              </td>
              <!-- Username -->
              <td class="px-6 py-4 whitespace-nowrap">
                <button @click="viewProfile(user)"
                  class="text-sm font-mono text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:underline transition-colors">
                  {{ user.username }}
                </button>
              </td>
              <!-- Role -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="user.role"
                  class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300">
                  {{ user.role.name }}
                </span>
                <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
              </td>
              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <UserStatusBadge :is-active="user.is_active" />
              </td>
              <!-- Last Login -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ user.last_login ?? '—' }}</span>
              </td>
              <!-- Three-dot trigger -->
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <button @click="openMenu($event, user)" :title="`Actions for ${user.full_name}`"
                  :class="[
                    'p-1.5 rounded-md transition-colors focus:outline-none',
                    openMenuId === user.id
                      ? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200'
                      : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700',
                  ]">
                  <MoreVertical :size="18" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && users.length > 0"
        class="px-6 py-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Showing
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ from }}</span>
          to
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ to }}</span>
          of
          <span class="font-medium text-gray-700 dark:text-gray-300">{{ total }}</span>
          results
        </p>
        <div class="flex items-center gap-1">
          <button @click="emit('page-change', currentPage - 1)" :disabled="currentPage <= 1"
            class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
            <ChevronLeft :size="18" />
          </button>
          <span class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300">Page {{ currentPage }} of {{ lastPage }}</span>
          <button @click="emit('page-change', currentPage + 1)" :disabled="currentPage >= lastPage"
            class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
            <ChevronRight :size="18" />
          </button>
        </div>
      </div>
    </div>

    <!-- Teleported dropdown — lives at <body> level, no overflow clipping -->
    <Teleport to="body">
      <!-- Invisible full-screen backdrop: click outside = close -->
      <div v-if="openMenuId !== null" class="fixed inset-0 z-40" @click="closeMenu" />

      <Transition
        enter-active-class="transition ease-out duration-100"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition ease-in duration-75"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95">
        <div v-if="openMenuId !== null && menuUser"
          class="fixed z-50 w-44 rounded-lg bg-white dark:bg-gray-800 shadow-xl ring-1 ring-black/10 dark:ring-white/10 origin-top-right"
          :style="{ top: `${menuPos.top}px`, left: `${menuPos.left}px` }">
          <div class="py-1">

            <button class="menu-item" @click="viewProfile(menuUser)">
              <Eye :size="15" class="shrink-0" /> View Profile
            </button>

            <button v-if="canUpdate(permissionKey) && menuUser.role?.name !== 'superadmin'"
              class="menu-item" @click="openEdit(menuUser)">
              <Pencil :size="15" class="shrink-0" /> Edit User
            </button>

            <div v-if="canUpdate(permissionKey) && menuUser.role?.name !== 'superadmin'"
              class="my-1 border-t border-gray-100 dark:border-gray-700" />

            <button v-if="canUpdate(permissionKey) && menuUser.role?.name !== 'superadmin'"
              :class="menuUser.is_active ? 'menu-item-warning' : 'menu-item-success'"
              @click="toggleStatus(menuUser)">
              <UserX v-if="menuUser.is_active" :size="15" class="shrink-0" />
              <UserCheck v-else :size="15" class="shrink-0" />
              {{ menuUser.is_active ? 'Deactivate' : 'Activate' }}
            </button>

            <button
              v-if="canDelete(permissionKey) && menuUser.is_active && menuUser.role?.name !== 'superadmin'"
              class="menu-item-danger" @click="deactivateUser(menuUser)">
              <Trash2 :size="15" class="shrink-0" /> Remove User
            </button>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<style scoped>
.menu-item {
  @apply w-full flex items-center gap-2.5 px-3 py-2 text-sm
         text-gray-700 dark:text-gray-300
         hover:bg-gray-100 dark:hover:bg-gray-700/60
         transition-colors cursor-pointer text-left;
}
.menu-item-warning {
  @apply w-full flex items-center gap-2.5 px-3 py-2 text-sm
         text-yellow-600 dark:text-yellow-400
         hover:bg-yellow-50 dark:hover:bg-yellow-900/20
         transition-colors cursor-pointer text-left;
}
.menu-item-success {
  @apply w-full flex items-center gap-2.5 px-3 py-2 text-sm
         text-green-600 dark:text-green-400
         hover:bg-green-50 dark:hover:bg-green-900/20
         transition-colors cursor-pointer text-left;
}
.menu-item-danger {
  @apply w-full flex items-center gap-2.5 px-3 py-2 text-sm
         text-red-600 dark:text-red-400
         hover:bg-red-50 dark:hover:bg-red-900/20
         transition-colors cursor-pointer text-left;
}
</style>