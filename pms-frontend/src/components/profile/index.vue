<template>
  <div class="min-h-screen">
    <!-- ── Loading State ─────────────────────────────────────────────────── -->
    <div v-if="profileStore.loading.profile" class="flex items-center justify-center min-h-[60vh]">
      <div class="flex flex-col items-center gap-3">
        <Loader2 :size="32" class="animate-spin text-blue-500" />
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading profile...</p>
      </div>
    </div>

    <!-- ── Error State ────────────────────────────────────────────────────── -->
    <div v-else-if="profileStore.errors.profile" class="flex items-center justify-center min-h-[60vh]">
      <div class="text-center">
        <AlertCircle :size="40" class="text-red-400 mx-auto mb-3" />
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ profileStore.errors.profile }}</p>
        <button @click="loadData" class="mt-4 text-sm text-blue-600 hover:underline">Try again</button>
      </div>
    </div>

    <!-- ── Profile Layout ─────────────────────────────────────────────────── -->
    <div v-else-if="profile" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="flex flex-col lg:flex-row gap-6">

        <!-- ── LEFT SIDEBAR ─────────────────────────────────────────────── -->
        <aside class="w-full lg:w-72 shrink-0 space-y-4">

          <!-- Profile Card -->
          <div class="mt-12">

            <!-- Avatar + Actions -->
            <div class="px-5 pb-5">
              <div class="flex items-end justify-between -mt-10 mb-4">
                <!-- Avatar -->
                <div class="relative group">
                  <div class="w-[200px] h-[200px] rounded-2xl ring-4 ring-white dark:ring-gray-800 overflow-hidden bg-gray-200 dark:bg-gray-900/30 shrink-0">
                    <img
                      v-if="avatarUrl"
                      :src="avatarUrl"
                      :alt="profile.full_name"
                      class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-4xl font-bold text-gray-500 dark:text-gray-300">
                      {{ initials }}
                    </div>
                  </div>

                  <!-- Avatar upload overlay (own profile only) -->
                  <label
                    v-if="isOwnProfile"
                    class="absolute inset-0 rounded-2xl bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer flex items-center justify-center"
                  >
                    <input type="file" class="hidden" accept="image/*" @change="handleAvatarUpload" />
                    <Camera :size="18" class="text-white" />
                  </label>

                  <!-- Upload spinner -->
                  <div v-if="profileStore.loading.uploadingAvatar" class="absolute inset-0 rounded-2xl bg-black/50 flex items-center justify-center">
                    <Loader2 :size="18" class="text-white animate-spin" />
                  </div>
                </div>
              </div>

              <!-- Name & Role -->
              <div class="space-y-1 mb-4">
                <h1 class="text-lg font-bold text-gray-900 dark:text-gray-100 leading-tight">
                  {{ profile.full_name }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">@{{ profile.username }}</p>
                <span
                  v-if="roleName"
                  :class="roleBadge"
                  class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-medium capitalize"
                >
                  <Shield :size="11" />
                  {{ roleName }}
                </span>
              </div>

              <!-- Status Badge -->
              <div class="flex items-center gap-2 mb-4">
                <span
                  :class="profile.is_active
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                  class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-medium"
                >
                  <span :class="profile.is_active ? 'bg-green-500' : 'bg-red-500'" class="w-1.5 h-1.5 rounded-full" />
                  {{ profile.is_active ? 'Active' : 'Inactive' }}
                </span>
                <span v-if="profile.employee_id" class="text-xs text-gray-500 dark:text-gray-400">
                  #{{ profile.employee_id }}
                </span>
              </div>

              <!-- Quick Info -->
              <div class="space-y-2.5 text-sm">
                <div v-if="profile.position || profile.department" class="flex items-center gap-2.5 text-gray-600 dark:text-gray-400">
                  <Briefcase :size="14" class="shrink-0 text-gray-400" />
                  <span class="truncate">
                    {{ [profile.position, profile.department].filter(Boolean).join(' · ') }}
                  </span>
                </div>
                <div v-if="profile.email" class="flex items-center gap-2.5 text-gray-600 dark:text-gray-400">
                  <Mail :size="14" class="shrink-0 text-gray-400" />
                  <span class="truncate">{{ profile.email }}</span>
                </div>
                <div v-if="profile.phone_number" class="flex items-center gap-2.5 text-gray-600 dark:text-gray-400">
                  <Phone :size="14" class="shrink-0 text-gray-400" />
                  <span>{{ profile.phone_number }}</span>
                </div>
                <div v-if="profile.date_hired" class="flex items-center gap-2.5 text-gray-600 dark:text-gray-400">
                  <CalendarDays :size="14" class="shrink-0 text-gray-400" />
                  <span>Hired {{ formatDate(profile.date_hired) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Stats Sidebar Card -->
          <div class="rounded-2xl p-5 space-y-3">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Quick Stats</h3>
            <div class="space-y-2.5">
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5">
                  <FolderOpen :size="14" class="text-blue-500" /> Projects
                </span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ profileStore.projects.length }}</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5">
                  <ClipboardList :size="14" class="text-purple-500" /> Tasks
                </span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ profileStore.tasks.length }}</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5">
                  <CheckCircle2 :size="14" class="text-green-500" /> Done
                </span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ completedTasksCount }}</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5">
                  <Activity :size="14" class="text-orange-500" /> Activities
                </span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ profileStore.activity.length }}</span>
              </div>
            </div>
          </div>

          <!-- Last Login -->
          <div v-if="profile.last_login" class="p-5">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Last Login</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ formatFullDate(profile.last_login) }}</p>
          </div>

          <div class="relative group p-5">
            <!-- Action Buttons (own profile) -->
            <div v-if="isOwnProfile" class="flex gap-2">
              <button
                @click="showEditModal = true"
                class="w-full flex items-center justify-center gap-2 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
              >
                <Pencil :size="12" /> <span>Edit</span>
              </button>
              <button
                @click="showPasswordModal = true"
                class="w-full flex items-center justify-center gap-2 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
              >
                <KeyRound :size="12" /> <span>Password</span>
              </button>
            </div>

            <!-- Admin badge -->
            <div v-else class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-purple-700 dark:text-purple-300 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
              <ShieldCheck :size="12" />
              Admin View
            </div>
          </div>
        </aside>

        <!-- ── MAIN CONTENT ──────────────────────────────────────────────── -->
        <main class="flex-1 min-w-0 space-y-5">

          <!-- Tab Navigation -->
          <div class="rounded-2x lp-1 flex gap-1 border-b border-gray-200 dark:border-gray-700">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium transition-all',
                activeTab === tab.id
                  ? 'shadow-sm text-green-700 dark:text-green-400 border-b-2 border-green-600 dark:border-green-400'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100',
              ]"
            >
              <component :is="tab.icon" :size="15" />
              <span class="hidden sm:inline">{{ tab.label }}</span>
              <span
                v-if="tab.count !== undefined"
                :class="activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                class="text-xs px-1.5 py-0.5 rounded-full font-medium"
              >
                {{ tab.count }}
              </span>
            </button>
          </div>

          <!-- Tab Panels -->
          <Transition name="fade" mode="out-in">
            <KeepAlive>
              <ProfileOverview
                v-if="activeTab === 'overview'"
                :profile="profile as any"
                :tasks="profileStore.tasks"
                :activity="profileStore.activity"
              />

              <ProfileProjects
                v-else-if="activeTab === 'projects'"
                :projects="profileStore.projects"
                :loading="profileStore.loading.projects"
              />

              <ProfileTasks
                v-else-if="activeTab === 'tasks'"
                :tasks="profileStore.tasks"
                :loading="profileStore.loading.tasks"
              />

              <ProfileActivity
                v-else-if="activeTab === 'activity'"
                :activity="profileStore.activity"
                :loading="profileStore.loading.activity"
              />
            </KeepAlive>
          </Transition>
        </main>
      </div>
    </div>

    <!-- ── Modals ────────────────────────────────────────────────────────── -->
    <EditProfileModal
      v-if="showEditModal && profile && profile.role !== null"
      :profile="profile as any"
      @close="showEditModal = false"
      @saved="onProfileSaved"
    />

    <ChangePasswordModal
      v-if="showPasswordModal"
      @close="showPasswordModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  Loader2, AlertCircle, Camera, Pencil, KeyRound,
  ShieldCheck, Shield, Briefcase, Mail, Phone,
  CalendarDays, FolderOpen, ClipboardList, CheckCircle2,
  Activity, LayoutDashboard,
} from 'lucide-vue-next'

import { useProfileStore } from '@/store/profile'
import { useAuthStore } from '@/store/auth'
import ProfileOverview from './components/ProfileOverview.vue'
import ProfileProjects from './components/ProfileProjects.vue'
import ProfileTasks from './components/ProfileTasks.vue'
import ProfileActivity from './components/ProfileActivity.vue'
import EditProfileModal from './components/EditProfileModal.vue'
import ChangePasswordModal from './components/ChangePasswordModal.vue'

import { useAvatar } from '@/composables/useAvatar'

// ── Setup ─────────────────────────────────────────────────────────────────────

const route = useRoute()
const profileStore = useProfileStore()
const authStore = useAuthStore()

const activeTab = ref('overview')
const showEditModal = ref(false)
const showPasswordModal = ref(false)

// ── Determine context ─────────────────────────────────────────────────────────

const viewingUserId = computed(() => route.params.id as string | undefined)
const isOwnProfile = computed(() => !viewingUserId.value)

// ── Profile data ──────────────────────────────────────────────────────────────

const profile = computed(() => profileStore.profile)

const { avatarUrl } = useAvatar(computed(() => profileStore.profile))

const initials = computed(() => {
  if (!profile.value) return '?'
  const f = profile.value.first_name?.[0] ?? ''
  const l = profile.value.last_name?.[0] ?? ''
  return (f + l).toUpperCase()
})

const roleName = computed(() => profile.value?.role?.name ?? '')

const roleBadge = computed(() => {
  const r = roleName.value?.toLowerCase()
  if (r === 'superadmin') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  if (r === 'admin') return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
  if (r === 'assistant') return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
})

const completedTasksCount = computed(() =>
  profileStore.tasks.filter(t => t.status?.toLowerCase() === 'completed').length
)

// ── Tabs ──────────────────────────────────────────────────────────────────────

const tabs = computed(() => [
  {
    id: 'overview',
    label: 'Overview',
    icon: LayoutDashboard,
  },
  {
    id: 'projects',
    label: 'Projects',
    icon: FolderOpen,
    count: profileStore.projects.length,
  },
  {
    id: 'tasks',
    label: 'Tasks',
    icon: ClipboardList,
    count: profileStore.tasks.length,
  },
  {
    id: 'activity',
    label: 'Activity',
    icon: Activity,
    count: profileStore.activity.length,
  },
])

// ── Load Data ─────────────────────────────────────────────────────────────────

async function loadData() {
  const userId = viewingUserId.value

  if (userId) {
    // Admin viewing another user
    await profileStore.fetchUserProfile(userId)
  } else {
    // Own profile
    await profileStore.fetchOwnProfile()
  }

  // Determine the user ID to use for related data
  const targetId = userId ?? authStore.user?.id
  if (!targetId) return

  await Promise.all([
    profileStore.fetchProjects(targetId),
    profileStore.fetchTasks(targetId),
    profileStore.fetchActivity(targetId),
  ])
}

// ── Handlers ──────────────────────────────────────────────────────────────────

async function handleAvatarUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  await profileStore.uploadAvatar(file)
  input.value = '' // Reset input
}

function onProfileSaved() {
  // If viewing own profile, also refresh auth store user
  if (isOwnProfile.value) {
    authStore.refreshUser()
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(date: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatFullDate(date: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => loadData())

watch(() => route.params.id, () => {
  profileStore.reset()
  activeTab.value = 'overview'
  loadData()
})

onUnmounted(() => profileStore.reset())
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>