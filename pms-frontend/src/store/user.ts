import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type {
  User,
  UserFormData,
  UserFilters,
  UserState
} from '@/types/user'
import type { PaginationMeta } from '@/types/paginationMeta'

export const useUserStore = defineStore('user', {
  // ==================== STATE ====================
  state: (): UserState => ({
    users: [],
    selectedUser: null,
    pagination: null,
    filters: {
      search: '',
      role_id: null,
      department: null,
      is_active: undefined,
      sort_by: 'created_at',
      sort_dir: 'desc',
      per_page: 15,
      page: 1,
    },
    loading: false,
    submitting: false,
  }),

  // ==================== GETTERS ====================
  getters: {
    /**
     * Get user by ID
     */
    getUserById: (state) => (id: number): User | undefined => {
      return state.users.find(user => user.id === id)
    },

    /**
     * Get active users only
     */
    activeUsers: (state): User[] => {
      return state.users.filter(user => user.is_active)
    },

    /**
     * Get inactive users only
     */
    inactiveUsers: (state): User[] => {
      return state.users.filter(user => !user.is_active)
    },

    /**
     * Check if there are more pages
     */
    hasMorePages: (state): boolean => {
      if (!state.pagination) return false
      return state.pagination.current_page < state.pagination.last_page
    },

    /**
     * Total user count from pagination
     */
    totalUsers: (state): number => {
      return state.pagination?.total ?? 0
    },
  },

  // ==================== ACTIONS ====================
  actions: {
    // ============================================
    // FETCH
    // ============================================

    /**
     * Fetch paginated list of users
     */
    async fetchUsers(filters?: Partial<UserFilters>): Promise<void> {
      this.loading = true
      try {
        // Merge incoming filters with current state filters
        if (filters) {
          this.filters = { ...this.filters, ...filters }
        }

        // Build clean params — strip out null/undefined/empty string values
        const params: Record<string, any> = {}
        const f = this.filters

        if (f.search)                         params.search     = f.search
        if (f.role_id != null)                params.role_id    = f.role_id
        if (f.department)                     params.department = f.department
        if (f.is_active !== undefined)        params.is_active  = f.is_active
        if (f.sort_by)                        params.sort_by    = f.sort_by
        if (f.sort_dir)                       params.sort_dir   = f.sort_dir
        if (f.per_page)                       params.per_page   = f.per_page
        if (f.page)                           params.page       = f.page

        const response = await axiosInstance.get('/api/users', { params })

        this.users = response.data.data
        this.pagination = response.data.meta as PaginationMeta
      } catch (error) {
        console.error('Failed to fetch users:', error)
        this.users = []
        this.pagination = null
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Fetch a single user by ID
     */
    async fetchUser(id: number): Promise<User> {
      this.loading = true
      try {
        const response = await axiosInstance.get(`/api/users/${id}`)
        const user = response.data.data || response.data
        this.selectedUser = user
        return user
      } catch (error) {
        console.error('Failed to fetch user:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    // ============================================
    // CREATE
    // ============================================

    /**
     * Create a new user
     */
    async createUser(data: UserFormData): Promise<User> {
      this.submitting = true
      try {
        const response = await axiosInstance.post('/api/users', data)
        const user = response.data.data || response.data

        // Prepend to list so it appears at top
        this.users.unshift(user)

        // Update total count
        if (this.pagination) {
          this.pagination.total += 1
        }

        return user
      } catch (error) {
        console.error('Failed to create user:', error)
        throw error
      } finally {
        this.submitting = false
      }
    },

    // ============================================
    // UPDATE
    // ============================================

    /**
     * Update an existing user
     */
    async updateUser(id: number, data: Partial<UserFormData>): Promise<User> {
      this.submitting = true
      try {
        const response = await axiosInstance.patch(`/api/users/${id}`, data)
        const user = response.data.data || response.data

        // Update in local state
        const index = this.users.findIndex(u => u.id === id)
        if (index !== -1) {
          this.users[index] = user
        }

        // Update selectedUser if it's the same
        if (this.selectedUser?.id === id) {
          this.selectedUser = user
        }

        return user
      } catch (error) {
        console.error('Failed to update user:', error)
        throw error
      } finally {
        this.submitting = false
      }
    },

    /**
     * Toggle user active/inactive status
     */
    async toggleStatus(id: number, is_active: boolean): Promise<User> {
      this.submitting = true
      try {
        const response = await axiosInstance.patch(`/api/users/${id}`, { is_active })
        const user = response.data.data || response.data

        // Update in local state
        const index = this.users.findIndex(u => u.id === id)
        if (index !== -1) {
          this.users[index] = user
        }

        // Update selectedUser if it's the same
        if (this.selectedUser?.id === id) {
          this.selectedUser = user
        }

        return user
      } catch (error) {
        console.error('Failed to toggle user status:', error)
        throw error
      } finally {
        this.submitting = false
      }
    },

    // ============================================
    // DELETE
    // ============================================

    /**
     * Deactivate a user (soft delete)
     */
    async deactivateUser(id: number): Promise<void> {
      this.submitting = true
      try {
        await axiosInstance.delete(`/api/users/${id}`)

        // Remove from local list
        this.users = this.users.filter(u => u.id !== id)

        // Update total count
        if (this.pagination) {
          this.pagination.total -= 1
        }

        // Clear selectedUser if it's the same
        if (this.selectedUser?.id === id) {
          this.selectedUser = null
        }
      } catch (error) {
        console.error('Failed to deactivate user:', error)
        throw error
      } finally {
        this.submitting = false
      }
    },

    // ============================================
    // FILTERS & PAGINATION
    // ============================================

    /**
     * Update filters and re-fetch
     */
    async setFilters(filters: Partial<UserFilters>): Promise<void> {
      // Reset to page 1 whenever filters change
      this.filters = { ...this.filters, ...filters, page: 1 }
      await this.fetchUsers()
    },

    /**
     * Go to a specific page
     */
    async goToPage(page: number): Promise<void> {
      this.filters.page = page
      await this.fetchUsers()
    },

    /**
     * Reset filters to default
     */
    async resetFilters(): Promise<void> {
      this.filters = {
        search: '',
        role_id: null,
        department: null,
        is_active: undefined,
        sort_by: 'created_at',
        sort_dir: 'desc',
        per_page: 15,
        page: 1,
      }
      await this.fetchUsers()
    },

    // ============================================
    // MISC
    // ============================================

    /**
     * Set selected user
     */
    setSelectedUser(user: User | null): void {
      this.selectedUser = user
    },

    /**
     * Clear all state
     */
    clearState(): void {
      this.users = []
      this.selectedUser = null
      this.pagination = null
      this.loading = false
      this.submitting = false
    },
  },
})