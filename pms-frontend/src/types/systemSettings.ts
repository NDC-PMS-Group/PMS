export interface SystemSetting {
  id: number
  key: string
  value: any
  type: 'string' | 'integer' | 'boolean' | 'json' | 'float'
  label: string
  description?: string
}

export interface GroupedSettings {
  [group: string]: SystemSetting[]
}