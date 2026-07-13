export type NotificationTab = 'templates' | 'rules' | 'deliveries'
export type DeliveryStatus = 'queued' | 'processing' | 'sent' | 'failed' | 'skipped' | 'cancelled'

export interface DeliveryOverview {
  period: string
  total: number
  queued: number
  sent: number
  failed: number
}

export interface NotificationVariable {
  key: string
  token: string
  description: string
}

export interface NotificationTemplateVersion {
  id: number
  version: number
  status: 'draft' | 'published'
  subject: string
  body: string
  variables: string[]
  restored_from_id: number | null
  author?: string | null
  publisher?: string | null
  published_at: string | null
  created_at: string
}

export interface NotificationTemplate {
  id: number
  name: string
  is_active: boolean
  mapped_events: string[]
  draft?: NotificationTemplateVersion
  published?: NotificationTemplateVersion
  versions?: NotificationTemplateVersion[]
  updated_at: string
}

export interface NotificationRule {
  id: number
  event_key: string
  label: string
  category: string
  description: string | null
  in_app_enabled: boolean
  email_enabled: boolean
  template_name: string | null
}

export interface NotificationDelivery {
  id: number
  event_key: string | null
  channel: string
  recipient: string
  subject: string
  status: DeliveryStatus
  is_test: boolean
  attempts: number
  failure_reason: string | null
  template_version: number | null
  queued_at: string | null
  sent_at: string | null
  failed_at: string | null
  created_at: string
}

export interface DeliveryFilters {
  status: '' | DeliveryStatus
  event_key: string
  search: string
  page: number
  per_page: number
}

export interface TemplatePreview {
  subject: string
  body: string
  variables: string[]
}
