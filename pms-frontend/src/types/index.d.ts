export interface User {
  email: string
  name: string
  sub: string
  first_name?: string
  last_name?: string
  role_id?: number
  division_id?: number
  role?: string
  profile_url?: string
}

export interface AuthState {
  user: User | null
  permissions: string[]
  authStatus: boolean
  photo_url: string | null
  isInitialized: boolean
  isVerifiedError: boolean
}

export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
  errors?: Record<string, string[]>
}