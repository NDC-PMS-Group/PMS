/**
 * Authentication Type Definitions
 * 
 * This file contains all TypeScript interfaces and types
 * related to authentication functionality.
 */

// ==================== User Types ====================

export interface Permission {
  id: number
  name: string
  resource: string
  action: string
  description?: string
}

export interface Role {
  id: number
  name: string
  description?: string
  is_system_role: boolean
  permissions?: Permission[]
}

export interface User {
  id: number
  username: string
  email: string
  first_name: string
  last_name: string
  full_name: string
  role?: Role
  is_active: boolean
  last_login: string | null
  created_at: string
}

// ==================== Request Types ====================

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterData {
  username: string
  email: string
  password: string
  password_confirmation: string
  first_name: string
  last_name: string
}

// ==================== Response Types ====================

export interface LoginResponse {
  user: User
  token: string
}

export interface AuthResponse {
  success: boolean
  message: string
  data?: any
}

// ==================== Store State Types ====================

export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isInitialized: boolean
  permissions: string[] // Array of permission names like ['projects.view', 'users.create']
}