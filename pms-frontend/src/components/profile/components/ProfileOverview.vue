<template>
  <div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div
        v-for="stat in statCards"
        :key="stat.label"
        class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 flex flex-col gap-1"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ stat.label }}</span>
          <component :is="stat.icon" :size="16" :class="stat.iconClass" />
        </div>
        <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stat.value }}</span>
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ stat.sub }}</span>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Recent Tasks -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Tasks</h3>
          <span class="text-xs text-gray-500 dark:text-gray-400">Last 5</span>
        </div>

        <div v-if="recentTasks.length === 0" class="px-5 py-8 text-center">
          <ClipboardList :size="32" class="text-gray-300 dark:text-gray-600 mx-auto mb-2" />
          <p class="text-sm text-gray-500 dark:text-gray-400">No tasks yet</p>
        </div>

        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
          <li
            v-for="task in recentTasks"
            :key="task.id"
            class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
          >
            <div class="mt-0.5">
              <component :is="taskStatusIcon(task.status)" :size="15" :class="taskStatusColor(task.status)" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ task.title }}</p>
              <p v-if="task.project" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ task.project.title }}
              </p>
            </div>
            <span :class="priorityBadge(task.priority)" class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0">
              {{ task.priority || '—' }}
            </span>
          </li>
        </ul>
      </div>

      <!-- Recent Activity -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Activity</h3>
          <span class="text-xs text-gray-500 dark:text-gray-400">Last 5</span>
        </div>

        <div v-if="recentActivity.length === 0" class="px-5 py-8 text-center">
          <Activity :size="32" class="text-gray-300 dark:text-gray-600 mx-auto mb-2" />
          <p class="text-sm text-gray-500 dark:text-gray-400">No recent activity</p>
        </div>

        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
          <li
            v-for="log in recentActivity"
            :key="log.id"
            class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
          >
            <div class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shrink-0 mt-0.5">
              <Zap :size="13" class="text-blue-600 dark:text-blue-400" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-gray-100 capitalize">
                {{ formatAction(log.action) }}
              </p>
              <p v-if="log.description" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                {{ log.description }}
              </p>
            </div>
            <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">
              {{ timeAgo(log.created_at) }}
            </span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bio / Personal Info -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Personal Information</h3>
      </div>
      <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-8 gap-y-5">
        <InfoField label="Email" :value="profile?.email" icon="Mail" />
        <InfoField label="Phone" :value="profile?.phone_number" icon="Phone" />
        <InfoField label="Address" :value="profile?.address" icon="MapPin" />
        <InfoField label="Department" :value="profile?.department" icon="Building2" />
        <InfoField label="Position" :value="profile?.position" icon="Briefcase" />
        <InfoField label="Employee ID" :value="profile?.employee_id" icon="BadgeCheck" />
        <InfoField label="Date Hired" :value="formatDate(profile?.date_hired)" icon="CalendarDays" />
        <InfoField label="Birth Date" :value="formatDate(profile?.birth_date)" icon="Cake" />
        <InfoField label="Username" :value="profile?.username" icon="AtSign" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  FolderOpen, ClipboardList, CheckCircle2, Clock,
  Activity, Zap,
  CircleDot, CircleUser, CircleDashed,
} from 'lucide-vue-next'
import type { User } from '@/types/auth'
import type { ProfileTask, ProfileActivity } from '@/store/profile'
import InfoField from './InfoField.vue'

const props = defineProps<{
  profile: User | null
  tasks: ProfileTask[]
  activity: ProfileActivity[]
}>()

// ── Stats ────────────────────────────────────────────────────────────────────

const totalProjects = computed(() => props.profile?.project_memberships?.length ?? 0)
const completedTasks = computed(() => props.tasks.filter(t => t.status?.toLowerCase() === 'completed').length)
const inProgressTasks = computed(() => props.tasks.filter(t => ['in_progress', 'ongoing'].includes(t.status?.toLowerCase())).length)

const statCards = computed(() => [
  {
    label: 'Projects',
    value: totalProjects.value,
    sub: 'memberships',
    icon: FolderOpen,
    iconClass: 'text-blue-500',
  },
  {
    label: 'Total Tasks',
    value: props.tasks.length,
    sub: 'assigned',
    icon: ClipboardList,
    iconClass: 'text-purple-500',
  },
  {
    label: 'Completed',
    value: completedTasks.value,
    sub: 'tasks done',
    icon: CheckCircle2,
    iconClass: 'text-green-500',
  },
  {
    label: 'In Progress',
    value: inProgressTasks.value,
    sub: 'active tasks',
    icon: Clock,
    iconClass: 'text-orange-500',
  },
])

const recentTasks = computed(() => props.tasks.slice(0, 5))
const recentActivity = computed(() => props.activity.slice(0, 5))

// ── Helpers ──────────────────────────────────────────────────────────────────

function taskStatusIcon(status: string) {
  const s = status?.toLowerCase()
  if (s === 'completed') return CircleUser
  if (['in_progress', 'ongoing'].includes(s)) return CircleDot
  return CircleDashed
}

function taskStatusColor(status: string) {
  const s = status?.toLowerCase()
  if (s === 'completed') return 'text-green-500'
  if (['in_progress', 'ongoing'].includes(s)) return 'text-blue-500'
  return 'text-gray-400'
}

function priorityBadge(priority: string | null) {
  const p = priority?.toLowerCase()
  if (p === 'high') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  if (p === 'medium') return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
  if (p === 'low') return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
}

function formatAction(action: string) {
  return action?.replace(/_/g, ' ') ?? '—'
}

function formatDate(date?: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function timeAgo(dateStr: string) {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  return `${days}d ago`
}
</script>