import type { Division } from './division'

export interface Employee {
  id: number
  ID: number
  emp_code: string
  display_emp_code: string
  email: string
  name?: string
  first_name: string
  last_name: string
  middle_name: string
  suffix?: string
  username?: string
  hire_date?: string
  division_id: number
  division?: Division
  status: string
  designation?: string
  position?: string
  employment_type_id: number
  employment_type?: EmploymentType
  title?: string
  daily_salary?: number
  monthly_salary?: number
  role_id: number
  role?: Role
  profile?: Profile
  salary_grade_id?: number
  salary_grade?: SalaryGrade
  salary_step_id?: number
  salary_step?: SalaryStep
  is_executive: boolean
  created_at: string
  updated_at: string
}

export interface Role {
  ID: number
  name: string
}

export interface Profile {
  ID: number
  employee_id: number
}

export interface EmploymentType {
  ID: number
  name: string
}

export interface SalaryGrade {
  ID: number
  grade: string
}

export interface SalaryStep {
  ID: number
  step: number
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