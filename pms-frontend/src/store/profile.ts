/**
 * Profile Store
 *
 * Handles fetching and mutating profile data for both
 * "own profile" and "admin viewing another user" contexts.
 */

import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type { User } from '@/types/auth'

// ─── Types ────────────────────────────────────────────────────────────────────

export interface ProfileProject {
  id: number
  title: string
  status: string
  role: string | null
  start_date: string | null
  end_date: string | null
}

export interface ProfileTask {
  id: number
  title: string
  status: string
  priority: string | null
  due_date: string | null
  progress: number
  project: { id: number; title: string } | null
}

export interface ProfileActivity {
  id: number
  action: string
  description: string | null
  model_type: string | null
  model_id: number | null
  created_at: string
}

export interface ProfileStats {
  totalProjects: number
  activeProjects: number
  totalTasks: number
  completedTasks: number
  inProgressTasks: number
}

export interface UpdateProfilePayload {
  first_name?: string
  middle_name?: string
  last_name?: string
  suffix?: string
  email?: string
  username?: string
  phone_number?: string
  address?: string
  department?: string
  position?: string
  birth_date?: string
}

export interface ChangePasswordPayload {
  current_password: string
  new_password: string
  new_password_confirmation: string
}

interface ProfileState {
  profile: User | null
  projects: ProfileProject[]
  tasks: ProfileTask[]
  activity: ProfileActivity[]
  stats: ProfileStats | null

  loading: {
    profile: boolean
    projects: boolean
    tasks: boolean
    activity: boolean
    updating: boolean
    uploadingAvatar: boolean
    changingPassword: boolean
  }

  errors: {
    profile: string | null
    projects: string | null
    tasks: string | null
    activity: string | null
  }

  taskFilter: string
}

// ─── Store ────────────────────────────────────────────────────────────────────

export const useProfileStore = defineStore('profile', {
  state: (): ProfileState => ({
    profile: null,
    projects: [],
    tasks: [],
    activity: [],
    stats: null,

    loading: {
      profile: false,
      projects: false,
      tasks: false,
      activity: false,
      updating: false,
      uploadingAvatar: false,
      changingPassword: false,
    },

    errors: {
      profile: null,
      projects: null,
      tasks: null,
      activity: null,
    },

    taskFilter: '',
  }),

  getters: {
    isLoading: (state) => state.loading.profile,

    computedStats: (state): ProfileStats => {
      const projects = state.projects
      const tasks = state.tasks

      return {
        totalProjects: projects.length,
        activeProjects: projects.filter((p) =>
          ['active', 'ongoing', 'in_progress'].includes(p.status?.toLowerCase())
        ).length,
        totalTasks: tasks.length,
        completedTasks: tasks.filter((t) => t.status?.toLowerCase() === 'completed').length,
        inProgressTasks: tasks.filter((t) =>
          ['in_progress', 'ongoing'].includes(t.status?.toLowerCase())
        ).length,
      }
    },
  },

  actions: {
    // ── Fetch own profile ───────────────────────────────────────────────────

    async fetchOwnProfile(): Promise<void> {
      this.loading.profile = true
      this.errors.profile = null
      try {
        const res = await axiosInstance.get('/api/profile')
        this.profile = res.data.data
      } catch (e: any) {
        this.errors.profile = e.response?.data?.message || 'Failed to load profile.'
      } finally {
        this.loading.profile = false
      }
    },

    // ── Fetch another user's profile (admin) ───────────────────────────────

    async fetchUserProfile(userId: number | string): Promise<void> {
      this.loading.profile = true
      this.errors.profile = null
      try {
        const res = await axiosInstance.get(`/api/users/${userId}/profile`)
        this.profile = res.data.data
      } catch (e: any) {
        this.errors.profile = e.response?.data?.message || 'Failed to load user profile.'
      } finally {
        this.loading.profile = false
      }
    },

    // ── Fetch projects ──────────────────────────────────────────────────────

    async fetchProjects(userId: number | string): Promise<void> {
      this.loading.projects = true
      this.errors.projects = null
      try {
        const res = await axiosInstance.get(`/api/users/${userId}/projects`)
        this.projects = res.data.data
      } catch (e: any) {
        this.errors.projects = e.response?.data?.message || 'Failed to load projects.'
      } finally {
        this.loading.projects = false
      }
    },

    // ── Fetch tasks ─────────────────────────────────────────────────────────

    async fetchTasks(userId: number | string, status?: string): Promise<void> {
      this.loading.tasks = true
      this.errors.tasks = null
      try {
        const params: Record<string, any> = { limit: 50 }
        if (status) params.status = status

        const res = await axiosInstance.get(`/api/users/${userId}/tasks`, { params })
        this.tasks = res.data.data
      } catch (e: any) {
        this.errors.tasks = e.response?.data?.message || 'Failed to load tasks.'
      } finally {
        this.loading.tasks = false
      }
    },

    // ── Fetch activity ──────────────────────────────────────────────────────

    async fetchActivity(userId: number | string): Promise<void> {
      this.loading.activity = true
      this.errors.activity = null
      try {
        const res = await axiosInstance.get(`/api/users/${userId}/activity`, {
          params: { limit: 30 },
        })
        this.activity = res.data.data
      } catch (e: any) {
        this.errors.activity = e.response?.data?.message || 'Failed to load activity.'
      } finally {
        this.loading.activity = false
      }
    },

    // ── Update own profile ──────────────────────────────────────────────────

    async updateProfile(payload: UpdateProfilePayload): Promise<{ success: boolean; message: string }> {
      this.loading.updating = true
      try {
        const res = await axiosInstance.put('/api/profile', payload)
        this.profile = res.data.data
        return { success: true, message: res.data.message || 'Profile updated.' }
      } catch (e: any) {
        const message = e.response?.data?.message || 'Failed to update profile.'
        return { success: false, message }
      } finally {
        this.loading.updating = false
      }
    },

    // ── Upload avatar ───────────────────────────────────────────────────────

    async uploadAvatar(file: File): Promise<{ success: boolean; message: string }> {
      this.loading.uploadingAvatar = true
      try {
        const form = new FormData()
        form.append('avatar', file)

        const res = await axiosInstance.post('/api/profile/avatar', form, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })

        // Update local profile photo
        if (this.profile) {
          this.profile = { ...this.profile, profile_photo_url: res.data.profile_photo } as User
        }

        return { success: true, message: res.data.message || 'Avatar updated.' }
      } catch (e: any) {
        return { success: false, message: e.response?.data?.message || 'Failed to upload avatar.' }
      } finally {
        this.loading.uploadingAvatar = false
      }
    },

    // ── Change password ─────────────────────────────────────────────────────

    async changePassword(payload: ChangePasswordPayload): Promise<{ success: boolean; message: string; errors?: Record<string, string[]> }> {
      this.loading.changingPassword = true
      try {
        const res = await axiosInstance.put('/api/profile/password', payload)
        return { success: true, message: res.data.message || 'Password changed.' }
      } catch (e: any) {
        return {
          success: false,
          message: e.response?.data?.message || 'Failed to change password.',
          errors: e.response?.data?.errors,
        }
      } finally {
        this.loading.changingPassword = false
      }
    },

    // ── Reset store ─────────────────────────────────────────────────────────

    reset(): void {
      this.profile = null
      this.projects = []
      this.tasks = []
      this.activity = []
      this.stats = null
      this.taskFilter = ''
      this.errors = { profile: null, projects: null, tasks: null, activity: null }
    },
  },
})