import type { Region } from './region'
import type { Employee } from './employee'

export interface Division {
  id: number
  region_id: number
  dept_code: string
  dept_name: string
  dept_description?: string
  dept_head_id?: number
  supervisor_id?: number
  administrator_id?: number
  dept_head?: Employee | null
  supervisor?: Employee | null
  administrator?: Employee | null
  employees?: any[]
  employee_count?: number
  created_at?: string
  updated_at?: string

  // Relationships
  region?: Region
}

export interface CreateDivisionPayload {
  region_id: number
  dept_code: string
  dept_name: string
  dept_description?: string
  dept_head_id?: number | null
  supervisor_id?: number | null
  administrator_id?: number | null
}

export interface UpdateDivisionPayload {
  region_id: number
  dept_code: string
  dept_name: string
  dept_description?: string
  dept_head_id?: number | null
  supervisor_id?: number | null
  administrator_id?: number | null
}
