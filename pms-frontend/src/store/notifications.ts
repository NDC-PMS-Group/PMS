import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import axiosInstance from '@/utils/axiosInstance'
import type {
  DeliveryFilters,
  DeliveryOverview,
  NotificationDelivery,
  NotificationRule,
  NotificationTemplate,
  NotificationVariable,
  TemplatePreview,
} from '@/types/notifications'

export const useNotificationManagementStore = defineStore('notificationManagement', () => {
  const templates = ref<NotificationTemplate[]>([])
  const variables = ref<NotificationVariable[]>([])
  const ruleGroups = ref<Record<string, NotificationRule[]>>({})
  const deliveries = ref<NotificationDelivery[]>([])
  const deliveryTotal = ref(0)
  const deliveryLastPage = ref(1)
  const deliveryOverview = ref<DeliveryOverview | null>(null)
  const loadingTemplates = ref(false)
  const loadingRules = ref(false)
  const loadingDeliveries = ref(false)
  const templateError = ref('')
  const ruleError = ref('')
  const deliveryError = ref('')
  const savingRuleIds = ref(new Set<number>())

  const templateOptions = computed(() => templates.value.filter((template) => template.published))

  async function fetchTemplates() {
    loadingTemplates.value = true
    templateError.value = ''
    try {
      const { data } = await axiosInstance.get('/api/notification-templates')
      templates.value = data.templates || []
      variables.value = data.variables || []
    } catch (error: any) {
      templateError.value = error?.response?.data?.message || 'Templates could not be loaded.'
      throw error
    } finally {
      loadingTemplates.value = false
    }
  }

  async function fetchTemplate(id: number) {
    const { data } = await axiosInstance.get(`/api/notification-templates/${id}`)
    const index = templates.value.findIndex((template) => template.id === id)
    if (index >= 0) templates.value[index] = data.data
    return data.data as NotificationTemplate
  }

  async function saveDraft(id: number, subject: string, body: string) {
    await axiosInstance.put(`/api/notification-templates/${id}/draft`, { subject, body })
    return fetchTemplate(id)
  }

  async function previewTemplate(id: number, subject: string, body: string, sample_data: Record<string, string> = {}) {
    const { data } = await axiosInstance.post(`/api/notification-templates/${id}/preview`, { subject, body, sample_data })
    return data.preview as TemplatePreview
  }

  async function publishTemplate(id: number) {
    await axiosInstance.post(`/api/notification-templates/${id}/publish`)
    return fetchTemplate(id)
  }

  async function restoreVersion(templateId: number, versionId: number) {
    await axiosInstance.post(`/api/notification-templates/${templateId}/versions/${versionId}/restore`)
    return fetchTemplate(templateId)
  }

  async function sendTest(id: number, recipient_email: string, subject: string, body: string) {
    return axiosInstance.post(`/api/notification-templates/${id}/test`, { recipient_email, subject, body })
  }

  async function fetchRules() {
    loadingRules.value = true
    ruleError.value = ''
    try {
      const { data } = await axiosInstance.get('/api/notification-event-settings')
      ruleGroups.value = data.events || {}
    } catch (error: any) {
      ruleError.value = error?.response?.data?.message || 'Event rules could not be loaded.'
      throw error
    } finally {
      loadingRules.value = false
    }
  }

  async function updateRule(rule: NotificationRule) {
    savingRuleIds.value = new Set(savingRuleIds.value).add(rule.id)
    try {
      const { data } = await axiosInstance.put(`/api/notification-event-settings/${rule.id}`, {
        in_app_enabled: rule.in_app_enabled,
        email_enabled: rule.email_enabled,
        template_name: rule.template_name || null,
      })
      Object.values(ruleGroups.value).flat().forEach((item) => {
        if (item.id === rule.id) Object.assign(item, data.event)
      })
    } finally {
      const next = new Set(savingRuleIds.value)
      next.delete(rule.id)
      savingRuleIds.value = next
    }
  }

  async function fetchDeliveries(filters: DeliveryFilters) {
    loadingDeliveries.value = true
    deliveryError.value = ''
    try {
      const { data } = await axiosInstance.get('/api/notification-deliveries', {
        params: { ...filters, status: filters.status || undefined, event_key: filters.event_key || undefined, search: filters.search || undefined },
      })
      deliveries.value = data.data || []
      deliveryTotal.value = data.meta?.total || 0
      deliveryLastPage.value = data.meta?.last_page || 1
    } catch (error: any) {
      deliveryError.value = error?.response?.data?.message || 'Delivery activity could not be loaded.'
      throw error
    } finally {
      loadingDeliveries.value = false
    }
  }

  async function fetchDeliveryOverview() {
    const { data } = await axiosInstance.get('/api/notification-management/overview')
    deliveryOverview.value = data
  }

  async function retryDelivery(id: number) {
    await axiosInstance.post(`/api/notification-deliveries/${id}/retry`)
  }

  return {
    templates, variables, ruleGroups, deliveries, deliveryTotal, deliveryLastPage, deliveryOverview,
    loadingTemplates, loadingRules, loadingDeliveries, templateError, ruleError, deliveryError,
    savingRuleIds, templateOptions, fetchTemplates, fetchTemplate, saveDraft, previewTemplate,
    publishTemplate, restoreVersion, sendTest, fetchRules, updateRule, fetchDeliveries,
    fetchDeliveryOverview, retryDelivery,
  }
})
