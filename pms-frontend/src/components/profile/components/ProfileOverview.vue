<template>
  <div class="space-y-6">

    <!-- ── Stats Header Bar ─────────────────────────────────────────────── -->
    <div class="overview-header rounded-xl overflow-hidden" :class="isDark ? 'is-dark' : 'is-light'">
      <!-- Top row: title + pills -->
      <div class="flex items-center justify-between px-6 py-4">
        <div>
          <h2 class="header-title text-lg font-bold">Overview</h2>
          <div class="flex items-center gap-2 mt-1.5 flex-wrap">
            <span class="stat-pill">{{ props.tasks.length }} tasks</span>
            <span class="stat-pill active">{{ completedTasks }} completed</span>
            <span v-if="overdueTasks > 0" class="stat-pill overdue">{{ overdueTasks }} overdue</span>
          </div>
        </div>
      </div>

      <!-- Stats row -->
      <div class="stats-row">
        <div v-for="stat in statCards" :key="stat.label" class="stat-card">
          <div class="stat-icon" :class="stat.colorClass">
            <component :is="stat.icon" :size="18" />
          </div>
          <div class="flex flex-col">
            <span class="stat-value">{{ stat.value }}</span>
            <span class="stat-label">{{ stat.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Recent Tasks + Activity ──────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Recent Tasks -->
      <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
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
      <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
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

    <!-- ── Personal Information ─────────────────────────────────────────── -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Personal Information</h3>
      </div>
      <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-8 gap-y-5">
        <InfoField label="Email"       :value="profile?.email"        icon="Mail" />
        <InfoField label="Phone"       :value="profile?.phone_number" icon="Phone" />
        <InfoField label="Address"     :value="profile?.address"      icon="MapPin" />
        <InfoField label="Department"  :value="profile?.department"   icon="Building2" />
        <InfoField label="Position"    :value="profile?.position"     icon="Briefcase" />
        <InfoField label="Employee ID" :value="profile?.employee_id"  icon="BadgeCheck" />
        <InfoField label="Date Hired"  :value="formatDate(profile?.date_hired)" icon="CalendarDays" />
        <InfoField label="Birth Date"  :value="formatDate(profile?.birth_date)" icon="Cake" />
        <InfoField label="Username"    :value="profile?.username"     icon="AtSign" />
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  FolderOpen, ClipboardList, CheckCircle2, Clock, AlertTriangle,
  Activity, Zap, CircleDot, CircleUser, CircleDashed,
} from 'lucide-vue-next'
import type { User } from '@/types/user'
import type { ProfileTask, ProfileActivity } from '@/types/profile'
import InfoField from './InfoField.vue'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'

const props = defineProps<{
  profile: User | null
  tasks: ProfileTask[]
  activity: ProfileActivity[]
}>()

const layoutStore = useLayoutStore()
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK)

// ── Stats ─────────────────────────────────────────────────────────────────────

const totalProjects   = computed(() => props.profile?.project_memberships?.length ?? 0)
const completedTasks  = computed(() => props.tasks.filter(t => t.status?.toLowerCase() === 'completed').length)
const inProgressTasks = computed(() => props.tasks.filter(t => ['in_progress', 'ongoing'].includes(t.status?.toLowerCase())).length)
const overdueTasks    = computed(() => props.tasks.filter(t => {
  if (!t.due_date || t.status?.toLowerCase() === 'completed') return false
  return new Date(t.due_date) < new Date()
}).length)

const statCards = computed(() => [
  { label: 'PROJECTS',    value: totalProjects.value,   icon: FolderOpen,   colorClass: 'blue'   },
  { label: 'TOTAL TASKS', value: props.tasks.length,    icon: ClipboardList, colorClass: 'purple' },
  { label: 'COMPLETED',   value: completedTasks.value,  icon: CheckCircle2, colorClass: 'green'  },
  { label: 'IN PROGRESS', value: inProgressTasks.value, icon: Clock,        colorClass: 'amber'  },
  { label: 'OVERDUE',     value: overdueTasks.value,    icon: AlertTriangle, colorClass: 'red'   },
])

const recentTasks    = computed(() => props.tasks.slice(0, 5))
const recentActivity = computed(() => props.activity.slice(0, 5))

// ── Helpers ───────────────────────────────────────────────────────────────────

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
  if (p === 'high')   return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  if (p === 'medium') return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
  if (p === 'low')    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
}
function formatAction(action: string) { return action?.replace(/_/g, ' ') ?? '—' }
function formatDate(date?: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
function timeAgo(dateStr: string) {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1)  return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24)  return `${hrs}h ago`
  return `${Math.floor(hrs / 24)}d ago`
}
</script>

<style scoped>
/* ── Dark mode header ───────────────────────────────────────────────────────── */
.overview-header.is-dark {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f4c81 100%);
}
.overview-header.is-dark .header-title { color: #f1f5f9; }
.overview-header.is-dark .stats-row    { border-top-color: rgba(255, 255, 255, 0.1); }
.overview-header.is-dark .stat-card    { border-right-color: rgba(255, 255, 255, 0.08); }
.overview-header.is-dark .stat-card:hover { background: rgba(255, 255, 255, 0.05); }
.overview-header.is-dark .stat-value   { color: #ffffff; }
.overview-header.is-dark .stat-label   { color: rgba(255, 255, 255, 0.5); }
.overview-header.is-dark .stat-pill    {
  background: rgba(255, 255, 255, 0.12);
  border-color: rgba(255, 255, 255, 0.2);
  color: rgba(255, 255, 255, 0.85);
}
.overview-header.is-dark .stat-pill.active  { background: rgba(34,197,94,0.2);  border-color: rgba(34,197,94,0.4);  color: #86efac; }
.overview-header.is-dark .stat-pill.overdue { background: rgba(239,68,68,0.2);  border-color: rgba(239,68,68,0.4);  color: #fca5a5; }

/* icon box colors — same in both modes since they're semi-transparent */
.overview-header.is-dark .stat-icon.blue   { background: rgba(59,130,246,0.2);  color: #93c5fd; }
.overview-header.is-dark .stat-icon.purple { background: rgba(168,85,247,0.2);  color: #d8b4fe; }
.overview-header.is-dark .stat-icon.green  { background: rgba(34,197,94,0.2);   color: #86efac; }
.overview-header.is-dark .stat-icon.amber  { background: rgba(245,158,11,0.2);  color: #fcd34d; }
.overview-header.is-dark .stat-icon.red    { background: rgba(239,68,68,0.2);   color: #fca5a5; }

/* ── Light mode header ──────────────────────────────────────────────────────── */
.overview-header.is-light {
  background: linear-gradient(135deg, #d6ddf5 0%, #2564eb79 60%, #0ea4e9a1 100%);
}
.overview-header.is-light .header-title { color: #222222; }
.overview-header.is-light .stats-row    { border-top-color: rgba(255, 255, 255, 0.2); }
.overview-header.is-light .stat-card    { border-right-color: rgba(255, 255, 255, 0.15); }
.overview-header.is-light .stat-card:hover { background: rgba(255, 255, 255, 0.1); }
.overview-header.is-light .stat-value   { color: #383838; }
.overview-header.is-light .stat-label   { color: rgba(54, 54, 54, 0.65); }
.overview-header.is-light .stat-pill    {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.35);
  color: #3b3b3b;
}
.overview-header.is-light .stat-pill.active  { background: rgba(34,197,94,0.25);  border-color: rgba(34,197,94,0.5);  color: #054d1e; }
.overview-header.is-light .stat-pill.overdue { background: rgba(239,68,68,0.25);  border-color: rgba(239,68,68,0.5);  color: #fee2e2; }

.overview-header.is-light .stat-icon.blue   { background: rgba(255,255,255,0.2);  color: #942f2f; }
.overview-header.is-light .stat-icon.purple { background: rgba(255,255,255,0.2);  color: #580baa; }
.overview-header.is-light .stat-icon.green  { background: rgba(255,255,255,0.2);  color: #057c2f; }
.overview-header.is-light .stat-icon.amber  { background: rgba(255,255,255,0.2);  color: #7a6408; }
.overview-header.is-light .stat-icon.red    { background: rgba(255,255,255,0.2);  color: #6b0707; }

/* ── Dot grid pattern overlay ───────────────────────────────────────────────── */
.overview-header::before {
  content: '';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}

/* ── Shared layout ──────────────────────────────────────────────────────────── */
.overview-header { position: relative; overflow: hidden; }

.stats-row {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  position: relative;
  z-index: 1;
}
.stat-card {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 1rem 1.25rem;
  border-right-width: 1px;
  border-right-style: solid;
  transition: background 0.15s;
  cursor: default;
}
.stat-card:last-child { border-right: none; }

.stat-icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-value {
  font-size: 1.375rem;
  font-weight: 700;
  line-height: 1;
}
.stat-label {
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.25rem;
}
.stat-pill {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  border-width: 1px;
  border-style: solid;
}

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 640px)  { .stats-row { grid-template-columns: repeat(2, 1fr); } .stat-card { padding: 0.875rem 1rem; } }
</style>