import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type {
  User,
  UserFormData,
  UserFilters,
  UserState,
  StaffInviteFormData,
  StaffInviteResponse,
} from '@/types/user'
import type { PaginationMeta } from '@/types/paginationMeta'

const defaultUserFilters = (): UserFilters => ({
  search: '',
  role_id: null,
  department: null,
  is_active: undefined,
  sort_by: 'created_at',
  sort_dir: 'desc',
  per_page: 15,
  page: 1,
})

export const useUserStore = defineStore('user', {
  // ==================== STATE ====================
  state: (): UserState => ({
    users: [],
    selectedUser: null,
    pagination: null,
    filters: defaultUserFilters(),
    usersRequestId: 0,
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
    upsertUser(user: User): void {
      const index = this.users.findIndex((item) => item.id === user.id)
      if (index === -1) {
        this.users.unshift(user)
      } else {
        this.users[index] = user
      }

      if (this.selectedUser?.id === user.id) {
        this.selectedUser = user
      }
    },

    // ============================================
    // FETCH
    // ============================================

    /**
     * Fetch paginated list of users
     */
    async fetchUsers(filters?: Partial<UserFilters>): Promise<void> {
      const requestId = ++this.usersRequestId
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

        if (requestId !== this.usersRequestId) return

        this.users = response.data.data
        this.pagination = response.data.meta as PaginationMeta
      } catch (error) {
        if (requestId !== this.usersRequestId) return

        console.error('Failed to fetch users:', error)
        this.users = []
        this.pagination = null
        throw error
      } finally {
        if (requestId === this.usersRequestId) {
          this.loading = false
        }
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
        const alreadyListed = this.users.some(u => u.id === user.id)

        this.upsertUser(user)

        // Update total count
        if (this.pagination && !alreadyListed) {
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

    async inviteStaff(data: StaffInviteFormData): Promise<StaffInviteResponse> {
      this.submitting = true
      try {
        const response = await axiosInstance.post('/api/users/invite-staff', data)
        const payload = response.data
        const user = payload.user?.data || payload.user
        const alreadyListed = user ? this.users.some(u => u.id === user.id) : false
        if (user) this.upsertUser(user)
        if (this.pagination && user && !alreadyListed) this.pagination.total += 1
        return {
          user,
          invite_url: payload.invite_url,
          message: payload.message,
        }
      } catch (error) {
        console.error('Failed to invite staff:', error)
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

        this.upsertUser(user)

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

        this.upsertUser(user)

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
      this.resetFilterState()
      await this.fetchUsers()
    },

    resetFilterState(): void {
      this.filters = defaultUserFilters()
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
      this.usersRequestId += 1
      this.users = []
      this.selectedUser = null
      this.pagination = null
      this.filters = defaultUserFilters()
      this.loading = false
      this.submitting = false
    },
  },
})
