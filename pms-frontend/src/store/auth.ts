/**
 * Authentication Store
 * 
 * Manages user authentication state, login/logout operations,
 * and token management with Laravel Sanctum backend.
 */

import { defineStore } from 'pinia'
import axiosInstance from '@/utils/axiosInstance'
import type { 
  User, 
  LoginCredentials, 
  RegisterData, 
  LoginResponse,
  AuthResponse,
  AuthState 
} from '@/types/auth'

const TOKEN_KEY = 'auth_token'

export const useAuthStore = defineStore('auth', {
  // ==================== STATE ====================
  state: (): AuthState => ({
    user: null,
    token: null,
    isAuthenticated: false,
    isInitialized: false,
    permissions: [],
  }),

  // ==================== GETTERS ====================
  getters: {
    /**
     * Get current user's full name
     */
    userFullName: (state): string => {
      return state.user?.full_name || ''
    },

    /**
     * Get current user's role name
     */
    userRole: (state): string => {
      return state.user?.role?.name || ''
    },

    /**
     * Check if user has a specific role
     */
    hasRole: (state) => (roleName: string): boolean => {
      return state.user?.role?.name === roleName
    },

    /**
     * Check if user has a specific permission
     * @param permission - Full permission name like 'projects.view' or just resource like 'projects'
     */
    hasPermission: (state) => (permission: string): boolean => {
      return state.permissions.includes(permission)
    },

    /**
     * Check if user can perform action on resource
     * @param resource - Resource name like 'projects', 'users'
     * @param action - Action like 'view', 'create', 'update', 'delete'
     */
    can: (state) => (resource: string, action: string): boolean => {
      const permissionName = `${resource}.${action}`
      return state.permissions.includes(permissionName)
    },

    /**
     * Check if user can view a resource
     */
    canView: (state) => (resource: string): boolean => {
      return state.permissions.includes(`${resource}.view`)
    },

    /**
     * Check if user can create a resource
     */
    canCreate: (state) => (resource: string): boolean => {
      return state.permissions.includes(`${resource}.create`)
    },

    /**
     * Check if user can update a resource
     */
    canUpdate: (state) => (resource: string): boolean => {
      return state.permissions.includes(`${resource}.update`)
    },

    /**
     * Check if user can delete a resource
     */
    canDelete: (state) => (resource: string): boolean => {
      return state.permissions.includes(`${resource}.delete`)
    },
  },

  // ==================== ACTIONS ====================
  actions: {
    /**
     * Login user with credentials
     */
    async login(credentials: LoginCredentials): Promise<AuthResponse> {
      try {
        const response = await axiosInstance.post<LoginResponse>('/api/login', {
          email: credentials.email,
          password: credentials.password,
        })

        const { user, token } = response.data

        // Store token and user data
        this.setAuth(token, user)

        return { 
          success: true, 
          message: 'Login successful',
          data: user 
        }
      } catch (error: any) {
        console.error('Login error:', error)

        // Handle specific error cases
        if (error.response?.status === 403) {
          return {
            success: false,
            message: error.response.data.message || 'Account is deactivated'
          }
        }

        if (error.response?.status === 422) {
          return {
            success: false,
            message: error.response.data.message || 'Invalid credentials'
          }
        }

        return {
          success: false,
          message: error.response?.data?.message || 'Login failed. Please try again.'
        }
      }
    },

    /**
     * Register new user
     */
    async register(data: RegisterData): Promise<AuthResponse> {
      try {
        const response = await axiosInstance.post<LoginResponse>('/api/register', data)

        const { user, token } = response.data

        // Auto-login after registration
        this.setAuth(token, user)

        return {
          success: true,
          message: 'Registration successful',
          data: user
        }
      } catch (error: any) {
        console.error('Registration error:', error)

        // Handle validation errors
        if (error.response?.status === 422) {
          const errors = error.response.data.errors
          const firstError = errors ? Object.values(errors)[0] : null
          const message = Array.isArray(firstError) ? firstError[0] : 'Validation failed'
          
          return {
            success: false,
            message: message as string
          }
        }

        return {
          success: false,
          message: error.response?.data?.message || 'Registration failed. Please try again.'
        }
      }
    },

    /**
     * Initialize auth state from stored token
     * Called on app startup to restore session
     */
    async initialize(): Promise<void> {
      if (this.isInitialized) {
        return
      }

      try {
        // Check for stored token
        const token = localStorage.getItem(TOKEN_KEY)
        
        if (!token) {
          this.isInitialized = true
          return
        }

        // Set token first so it's included in the request
        this.token = token

        // Verify token and get current user
        const response = await axiosInstance.get<any>('/api/me')

        const userData = response.data.data || response.data

        // Update state
        this.user = userData
        this.isAuthenticated = true
        this.isInitialized = true
        
        this.permissions = this.extractPermissions(userData)
      } catch (error: any) {
        console.error('Auth initialization error:', error)
        this.clearAuth()
        this.isInitialized = true
      }
    },

    /**
     * Logout user
     */
    async logout(): Promise<void> {
      try {
        // Call backend to revoke token (if token exists)
        if (this.token) {
          await axiosInstance.post('/api/logout')
        }
      } catch (error) {
        console.error('Backend logout error:', error)
      } finally {
        this.clearAuth()
        window.location.href = '/login'
      }
    },

    /**
     * Refresh current user data
     */
    async refreshUser(): Promise<void> {
      try {
        const response = await axiosInstance.get<any>('/api/me')
        const userData = response.data.data || response.data
        this.user = userData
        this.permissions = this.extractPermissions(userData)
      } catch (error) {
        console.error('Failed to refresh user:', error)
        throw error
      }
    },

    // ==================== HELPER METHODS ====================

    /**
     * Set authentication data
     */
    setAuth(token: string, user: User): void {
      this.token = token
      this.user = user
      this.isAuthenticated = true
      this.isInitialized = true

      // Extract permissions from user's role
      this.permissions = this.extractPermissions(user)

      // Persist token to localStorage
      localStorage.setItem(TOKEN_KEY, token)
    },

    /**
     * Clear authentication data
     */
    clearAuth(): void {
      this.user = null
      this.token = null
      this.isAuthenticated = false
      this.isInitialized = false
      this.permissions = []

      // Remove token from localStorage
      localStorage.removeItem(TOKEN_KEY)
    },

    /**
     * Extract permission names from user's role
     */
    extractPermissions(user: User): string[] {
      if (!user.role?.permissions) {
        console.warn('⚠️ No permissions found in user role')
        return []
      }

      const permissions = user.role.permissions.map(permission => permission.name)
      return permissions
    },
  },
})