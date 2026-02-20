import type { Division } from './division'

export interface Employee {
  id: number
  email: string
  name?: string
  first_name: string
  last_name: string
  middle_name: string
  suffix?: string
  username?: string
  division_id: number
  division?: Division
  status: string
  designation?: string
  position?: string
  employment_type_id: number
  employment_type?: EmploymentType
  title?: string
  role_id: number
  role?: Role
  profile?: Profile
  created_at: string
  updated_at: string
}

export interface Role {
  id: number
  name: string
}

export interface Profile {
  id: number
  employee_id: number
}

export interface EmploymentType {
  id: number
  name: string
}

export interface EmployeeWithDivision extends Employee {
  division_name?: string
  full_name?: string
}

export interface EmployeeResponse {
  message: string
  data: Employee[]
  employment_type_counts: Array<{ name: string; count: number }>
  total_employees: number
}

export interface SingleEmployeeResponse {
  message: string
  data: Employee
}