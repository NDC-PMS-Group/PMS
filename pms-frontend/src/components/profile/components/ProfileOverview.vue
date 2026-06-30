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

    <!-- ── Pending Project Invitations ──────────────────────────────────── -->
    <div v-if="pendingInvitations && pendingInvitations.length > 0" class="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-5 space-y-4">
      <div class="flex items-center gap-2 text-amber-800 dark:text-amber-300">
        <Mail :size="20" class="shrink-0" />
        <div>
          <h3 class="text-sm font-semibold">Pending Project Invitations</h3>
          <p class="text-xs opacity-90 mt-0.5">You have been invited to join the following projects. You must accept to access project details and tasks.</p>
        </div>
      </div>
      <div class="space-y-3">
        <div 
          v-for="invite in pendingInvitations" 
          :key="invite.id" 
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 border border-amber-200 dark:border-amber-800 rounded-xl bg-white dark:bg-slate-900 gap-3 shadow-sm"
        >
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2 py-0.5 rounded">
              {{ invite.project?.project_code || 'PROJ' }}
            </span>
            <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 mt-1.5">{{ invite.project?.title }}</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Invited by {{ invite.invited_by?.full_name || 'Project Manager' }} · Role: {{ invite.assignment_type }}
            </p>
          </div>
          <div class="flex gap-2 shrink-0">
            <button
              type="button"
              @click="declineInvite(invite.id)"
              :disabled="actioningInviteId === invite.id"
              class="px-4 py-2 text-xs font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
            >
              Decline
            </button>
            <button
              type="button"
              @click="acceptInvite(invite.id)"
              :disabled="actioningInviteId === invite.id"
              class="px-4 py-2 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors flex items-center gap-1.5"
            >
              <Loader2 v-if="actioningInviteId === invite.id" :size="12" class="animate-spin" />
              Accept
            </button>
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

    <!-- ── Company / Proponent Profile ──────────────────────────────────── -->
    <div v-if="hasCompanyProfile" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Company / Proponent Profile</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Company background and project track record details available to approvers during proposal evaluation.</p>
      </div>
      <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-8 gap-y-5">
        <InfoField label="Company" :value="profile?.organization_name" icon="Building2" />
        <InfoField label="Type" :value="profile?.organization_type" icon="Briefcase" />
        <InfoField label="Registration No." :value="profile?.organization_registration_no" icon="BadgeCheck" />
      </div>
      <div v-if="profileRows.length" class="px-5 pb-5 grid gap-3">
        <div
          v-for="row in profileRows"
          :key="row.label"
          class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30"
        >
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ row.label }}</p>
          <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">{{ row.value }}</p>
        </div>
      </div>
    </div>

    <!-- ── Proponent Track Record (Previous Projects) ───────────────────── -->
    <div v-if="localPreviousProjects.length || isOwnProfile" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
      <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-700/10">
        <div>
          <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Proponent Track Record</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Structured summary of projects completed outside the NDC PMS.</p>
        </div>
        <button
          v-if="isOwnProfile"
          type="button"
          @click="openAddModal"
          class="px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
        >
          + Add Project
        </button>
      </div>

      <!-- Projects list -->
      <div class="px-5 py-4">
        <div v-if="localPreviousProjects.length === 0" class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
          No previous projects registered yet.
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="proj in localPreviousProjects"
            :key="proj.id"
            class="p-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-slate-800/40 relative group/card"
          >
            <!-- Actions (Own Profile only) -->
            <div v-if="isOwnProfile && String(proj.id).startsWith('db-')" class="absolute top-3 right-3 flex gap-1.5 opacity-0 group-hover/card:opacity-100 transition-opacity">
              <button
                type="button"
                @click="openEditModal(proj)"
                class="p-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 rounded-lg shadow-sm"
                title="Edit Project"
              >
                <Pencil :size="12" />
              </button>
              <button
                type="button"
                @click="deleteProject(proj.id)"
                class="p-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 rounded-lg shadow-sm"
                title="Delete Project"
              >
                <Trash2 :size="12" />
              </button>
            </div>

            <div class="pr-12">
              <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ proj.title }}</h4>
              <p v-if="proj.client_partner" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                <strong>Client/Partner:</strong> {{ proj.client_partner }}
              </p>
              <p v-if="proj.project_value" class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                <strong>Value:</strong> {{ proj.project_value }}
              </p>
              <p v-if="proj.start_date || proj.end_date" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                <strong>Timeline:</strong> {{ proj.start_date || '—' }} to {{ proj.end_date || '—' }}
              </p>
              <p v-if="proj.status" class="text-xs mt-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold" :class="statusBadgeClass(proj.status)">
                  {{ proj.status }}
                </span>
              </p>
              <p v-if="proj.description" class="text-xs text-gray-600 dark:text-gray-400 mt-2 border-t border-gray-100 dark:border-gray-700/60 pt-2 whitespace-pre-line leading-relaxed">
                {{ proj.description }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Previous Project Modal -->
    <div v-if="showProjectModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50 p-4 !mt-0">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden border border-gray-200 dark:border-gray-700 flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center shrink-0">
          <h3 class="font-bold text-gray-900 dark:text-gray-100 text-sm">
            {{ isEditing ? 'Edit Track Record Project' : 'Add Track Record Project' }}
          </h3>
          <button @click="closeProjectModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 font-bold text-xl">&times;</button>
        </div>

        <form @submit.prevent="savePreviousProject" class="p-6 space-y-4 overflow-y-auto max-h-[80vh]">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Project Title *</label>
            <input v-model="projectForm.title" required class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-transparent text-sm text-gray-950 dark:text-white" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Client / Partner</label>
              <input v-model="projectForm.client_partner" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-transparent text-sm text-gray-950 dark:text-white" />
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Project Value</label>
              <input v-model="projectForm.project_value" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-transparent text-sm text-gray-950 dark:text-white" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Start Date</label>
              <input type="date" v-model="projectForm.start_date" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-transparent text-sm text-gray-950 dark:text-white" />
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">End Date</label>
              <input type="date" v-model="projectForm.end_date" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-transparent text-sm text-gray-950 dark:text-white" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Status</label>
            <select v-model="projectForm.status" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-950 dark:text-white">
              <option value="Completed">Completed</option>
              <option value="Ongoing">Ongoing</option>
              <option value="Pipeline">Pipeline</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Brief Description</label>
            <textarea v-model="projectForm.description" rows="3" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg bg-transparent text-sm text-gray-950 dark:text-white"></textarea>
          </div>

          <div class="pt-4 border-t border-gray-100 dark:border-gray-700/60 flex justify-end gap-2 shrink-0">
            <button type="button" @click="closeProjectModal" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors">
              Cancel
            </button>
            <button type="submit" :disabled="modalSaving" class="px-5 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors flex items-center gap-1.5">
              <Loader2 v-if="modalSaving" :size="14" class="animate-spin" />
              {{ isEditing ? 'Save Changes' : 'Add Project' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="registrationDocuments.length" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Registration Documents</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Documents submitted for account review before project proposal access.</p>
      </div>
      <div class="px-5 py-4 grid gap-3">
        <div
          v-for="document in registrationDocuments"
          :key="document.id"
          class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ documentLabel(document) }}</p>
            <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ document.file_name }} · {{ document.review_status || 'pending' }}</p>
          </div>
          <div class="flex gap-2">
            <button class="doc-action" type="button" @click="openRegistrationDocument(document, 'view')">View</button>
            <button class="doc-action" type="button" @click="openRegistrationDocument(document, 'download')">Download</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="monitoringProjects.length" class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Verified Project Performance</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">System-generated monitoring history used by NDC when profiling the proponent and evaluating future proposals.</p>
      </div>
      <div class="px-5 py-4 grid grid-cols-2 md:grid-cols-4 gap-3">
        <div v-for="metric in monitoringSummary" :key="metric.label" class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/30">
          <strong class="block text-lg text-gray-900 dark:text-gray-100">{{ metric.value }}</strong>
          <span class="text-xs text-gray-500 dark:text-gray-400">{{ metric.label }}</span>
        </div>
      </div>
      <div class="divide-y divide-gray-100 border-t border-gray-200 dark:divide-gray-700 dark:border-gray-700">
        <div v-for="project in monitoringProjects.slice(0, 5)" :key="project.id" class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ project.title }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ project.project_code }} · {{ performanceDetail(project) }}</p>
          </div>
          <span :class="monitoringStatusClass(project.monitoring_submission_status)" class="self-start rounded-full px-2.5 py-1 text-xs font-semibold capitalize sm:self-auto">
            {{ String(project.monitoring_submission_status || '').replace(/_/g, ' ') }}
          </span>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, ref, reactive } from 'vue'
import {
  FolderOpen, ClipboardList, CheckCircle2, Clock, AlertTriangle,
  Activity, Zap, CircleDot, CircleUser, CircleDashed, Pencil, Trash2, Loader2,
} from 'lucide-vue-next'
import { toast } from 'vue3-toastify'
import axiosInstance from '@/utils/axiosInstance'
import type { ProponentRegistrationDocument, User } from '@/types/user'
import type { ProfileProject, ProfileTask, ProfileActivity } from '@/types/profile'
import InfoField from './InfoField.vue'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'

const props = defineProps<{
  profile: User | null
  projects: ProfileProject[]
  tasks: ProfileTask[]
  activity: ProfileActivity[]
  isOwnProfile?: boolean
}>()

const emit = defineEmits<{ (e: 'refresh'): void }>()

const layoutStore = useLayoutStore()
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK)

// ── Stats ─────────────────────────────────────────────────────────────────────

const totalProjects   = computed(() => props.projects.length)
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
const proponentProfile = computed(() => props.profile?.proponent_profile || {})
const profileRows = computed(() => [
  { label: 'Business Summary', value: proponentProfile.value.business_summary },
  { label: 'Project Experience', value: proponentProfile.value.project_experience },
  { label: 'Major Clients / Partners', value: proponentProfile.value.major_clients },
  { label: 'Certifications / Registrations', value: proponentProfile.value.certifications },
].filter((row) => String(row.value || '').trim().length > 0))
const hasCompanyProfile = computed(() => Boolean(
  props.profile?.organization_name ||
  props.profile?.organization_type ||
  props.profile?.organization_registration_no ||
  profileRows.value.length
))
const registrationDocuments = computed(() => props.profile?.registration_documents || [])
const monitoringProjects = computed(() => props.projects.filter((project) =>
  ['submitted', 'returned', 'accepted'].includes(project.monitoring_submission_status || '')
))
const acceptedMonitoringProjects = computed(() =>
  monitoringProjects.value.filter((project) => project.monitoring_submission_status === 'accepted')
)
const verifiedJobs = computed(() => acceptedMonitoringProjects.value.reduce((total, project) => {
  const metrics = project.monitoring_metrics || {}
  return total
    + Number(metrics.jobs_generated_direct || 0)
    + Number(metrics.jobs_generated_indirect || 0)
    + Number(metrics.retained_jobs || 0)
}, 0))
const verifiedRevenue = computed(() => acceptedMonitoringProjects.value.reduce(
  (total, project) => total + Number(project.monitoring_metrics?.actual_revenue || 0),
  0
))
const monitoringSummary = computed(() => [
  { label: 'Reports submitted', value: monitoringProjects.value.length },
  { label: 'Accepted by NDC', value: acceptedMonitoringProjects.value.length },
  { label: 'Verified jobs', value: verifiedJobs.value.toLocaleString('en-PH') },
  {
    label: 'Verified revenue',
    value: new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      notation: 'compact',
      maximumFractionDigits: 1,
    }).format(verifiedRevenue.value),
  },
])

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
function performanceDetail(project: ProfileProject) {
  const metrics = project.monitoring_metrics || {}
  const jobs = Number(metrics.jobs_generated_direct || 0)
    + Number(metrics.jobs_generated_indirect || 0)
    + Number(metrics.retained_jobs || 0)
  const revenue = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(Number(metrics.actual_revenue || 0))
  return `${jobs.toLocaleString('en-PH')} jobs · ${revenue} actual revenue`
}
function monitoringStatusClass(status?: string | null) {
  if (status === 'accepted') return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  if (status === 'returned') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
}
function documentLabel(document: ProponentRegistrationDocument) {
  const labels: Record<string, string> = {
    registration_proof: 'Business registration proof',
    representative_authorization: 'Representative authorization',
    company_profile: 'Company profile / capability statement',
  }
  return labels[document.document_type] || document.title || document.file_name
}
async function openRegistrationDocument(document: ProponentRegistrationDocument, mode: 'view' | 'download') {
  if (!props.profile) return
  const response = await axiosInstance.get(`/api/users/${props.profile.id}/registration-documents/${document.id}/${mode}`, {
    responseType: 'blob',
  })
  const blob = new Blob([response.data], { type: response.headers['content-type'] || document.file_type || 'application/octet-stream' })
  const url = URL.createObjectURL(blob)

  if (mode === 'view') {
    window.open(url, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(url), 30_000)
    return
  }

  const link = window.document.createElement('a')
  link.href = url
  link.download = document.file_name || `${document.document_type}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

// ── Previous Projects CRUD Logic ──────────────────────────────────────────────

const showProjectModal = ref(false)
const isEditing = ref(false)
const modalSaving = ref(false)
const currentProjectId = ref<number | null>(null)

const projectForm = reactive({
  title: '',
  description: '',
  client_partner: '',
  project_value: '',
  start_date: '',
  end_date: '',
  status: 'Completed',
})

const openAddModal = () => {
  isEditing.value = false
  currentProjectId.value = null
  projectForm.title = ''
  projectForm.description = ''
  projectForm.client_partner = ''
  projectForm.project_value = ''
  projectForm.start_date = ''
  projectForm.end_date = ''
  projectForm.status = 'Completed'
  showProjectModal.value = true
}

const openEditModal = (proj: any) => {
  isEditing.value = true
  currentProjectId.value = Number(String(proj.id).replace('db-', ''))
  projectForm.title = proj.title || ''
  projectForm.description = proj.description || ''
  projectForm.client_partner = proj.client_partner || ''
  projectForm.project_value = proj.project_value || ''
  projectForm.start_date = proj.start_date || ''
  projectForm.end_date = proj.end_date || ''
  projectForm.status = proj.status || 'Completed'
  showProjectModal.value = true
}

const closeProjectModal = () => {
  showProjectModal.value = false
}

const savePreviousProject = async () => {
  modalSaving.value = true
  try {
    if (isEditing.value && currentProjectId.value) {
      await axiosInstance.put(`/api/profile/previous-projects/${currentProjectId.value}`, projectForm)
      toast.success('Project updated successfully.')
    } else {
      await axiosInstance.post('/api/profile/previous-projects', projectForm)
      toast.success('Project added successfully.')
    }
    showProjectModal.value = false
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save project.')
  } finally {
    modalSaving.value = false
  }
}

const deleteProject = async (id: any) => {
  const dbId = Number(String(id).replace('db-', ''))
  if (!confirm('Are you sure you want to delete this project?')) return
  try {
    await axiosInstance.delete(`/api/profile/previous-projects/${dbId}`)
    toast.success('Project deleted successfully.')
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to delete project.')
  }
}

const localPreviousProjects = computed(() => {
  return props.profile?.previous_projects || []
})

const statusBadgeClass = (status: string) => {
  if (status === 'Completed') return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
  if (status === 'Ongoing') return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
  return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
}

const actioningInviteId = ref<number | null>(null)

const pendingInvitations = computed(() => {
  return (props.profile?.received_invitations || []).filter(invite => invite.status === 'pending')
})

const acceptInvite = async (id: number) => {
  actioningInviteId.value = id
  try {
    await axiosInstance.post(`/api/invitations/${id}/accept`)
    toast.success('Invitation accepted! You are now a project member.')
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to accept invitation.')
  } finally {
    actioningInviteId.value = null
  }
}

const declineInvite = async (id: number) => {
  actioningInviteId.value = id
  try {
    await axiosInstance.post(`/api/invitations/${id}/decline`)
    toast.success('Invitation declined.')
    emit('refresh')
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to decline invitation.')
  } finally {
    actioningInviteId.value = null
  }
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
.doc-action {
  border: 1px solid #bfdbfe;
  border-radius: 0.55rem;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 0.45rem 0.7rem;
  font-size: 0.78rem;
  font-weight: 800;
}
.doc-action:hover {
  border-color: #2563eb;
  background: #dbeafe;
}
:global(.dark) .doc-action {
  border-color: #1e3a8a;
  background: #172554;
  color: #bfdbfe;
}
:global(.dark) .doc-action:hover {
  border-color: #60a5fa;
  background: #1e3a8a;
}

/* ── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 640px)  { .stats-row { grid-template-columns: repeat(2, 1fr); } .stat-card { padding: 0.875rem 1rem; } }
</style>
