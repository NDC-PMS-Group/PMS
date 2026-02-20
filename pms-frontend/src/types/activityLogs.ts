export interface ActivityLog {
  id: number
  employee_id: number
  email: string
  action: string 
  action_type: string
  description: string
  model_type?: string
  model_id?: number
  entity_type?: string
  entity_id?: number
  ip_address: string
  user_agent: string
  device_type?: string
  browser?: string
  browser_version?: string
  platform?: string
  platform_version?: string
  old_values?: string | null 
  new_values?: string | null
  changes?: ActivityLogChanges | null
  created_at: string
  user_id?: number
  employee?: {
    first_name: string
    last_name: string
    middle_name?: string
    suffix?: string
  }
  user?: {
    id: number
    first_name: string
    last_name: string
    middle_name?: string
    suffix?: string
    email: string
  }
}

export interface ActivityLogChanges {
  old?: Record<string, any> | null
  new?: Record<string, any> | null
}

export interface ActivityLogStatistics {
  total_activities: number
  total_logins: number
  total_creates: number
  total_updates: number
  total_deletes: number
  unique_users: number
}

export interface ActivityLogSettings {
  retention_months: number
  max_id: number
  auto_cleanup_enabled: boolean
  last_cleanup_at?: string
}

export interface ActivityLogFilters {
  search: string
  action_type: string
  start_date: string
  end_date: string
}

export interface ActionType {
  value: string
  label: string
}

export interface ActiveFilter {
  key: string
  label: string
  value: string
}

// Export data format for CSV
export interface ActivityLogExportData {
  id: number
  date: string
  employee_name: string
  email: string
  action_type: string
  description: string
  ip_address: string
  device_type: string
  browser: string
  platform: string
}