/**
 * Authentication Type Definitions
 * 
 * This file contains all TypeScript interfaces and types
 * related to authentication functionality.
 */

import type { ProponentProfile, ProponentRegistrationDocument } from './user'

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
  phone_number?: string | null
  address?: string | null
  organization_name?: string | null
  organization_type?: string | null
  organization_registration_no?: string | null
  proponent_profile?: ProponentProfile | null
  registration_documents?: ProponentRegistrationDocument[]
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
  username?: string
  email: string
  password: string
  password_confirmation: string
  first_name: string
  last_name: string
  phone_number?: string
  organization_name: string
  organization_type?: string
  organization_registration_no?: string
  proponent_profile?: ProponentProfile
  address?: string
  authority_confirmed?: boolean
  registration_document?: File | null
  authorization_document?: File | null
  company_profile_document?: File | null
}

// ==================== Response Types ====================

export interface LoginResponse {
  user: User
  token?: string
  message?: string
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
