<template>
  <main class="notification-management animate-fade-in" :class="{ 'is-dark': isDarkMode }">
    <header class="nm-page-head">
      <div><p class="nm-eyebrow">Admin tools</p><h1>Notification management</h1></div>
      <span class="nm-page-status"><Activity aria-hidden="true" /> Operational</span>
    </header>

    <div class="nm-tabs" role="tablist" aria-label="Notification management sections" @keydown="handleTabKeys">
      <button v-for="tab in tabs" :id="`${tab.id}-tab`" :key="tab.id" type="button" role="tab" :aria-selected="activeTab === tab.id" :aria-controls="`${tab.id}-panel`" :tabindex="activeTab === tab.id ? 0 : -1" @click="activeTab = tab.id">
        <component :is="tab.icon" aria-hidden="true" />{{ tab.label }}
      </button>
    </div>

    <div v-for="tab in tabs" v-show="activeTab === tab.id" :id="`${tab.id}-panel`" :key="`${tab.id}-panel`" role="tabpanel" :aria-labelledby="`${tab.id}-tab`" tabindex="0" class="nm-tab-panel">
      <component :is="tab.component" />
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Activity, FileText, ListChecks, Send } from 'lucide-vue-next'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'
import type { NotificationTab } from '@/types/notifications'
import NotificationTemplatesTab from '@/components/admin/notifications/NotificationTemplatesTab.vue'
import NotificationEventRulesTab from '@/components/admin/notifications/NotificationEventRulesTab.vue'
import NotificationDeliveryActivityTab from '@/components/admin/notifications/NotificationDeliveryActivityTab.vue'
import '@/components/admin/notifications/notification-management.css'

const layoutStore = useLayoutStore()
const activeTab = ref<NotificationTab>('templates')
const tabs = [
  { id: 'templates' as const, label: 'Templates', icon: FileText, component: NotificationTemplatesTab },
  { id: 'rules' as const, label: 'Event Rules', icon: ListChecks, component: NotificationEventRulesTab },
  { id: 'deliveries' as const, label: 'Delivery Activity', icon: Send, component: NotificationDeliveryActivityTab },
]
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK || (typeof document !== 'undefined' && document.documentElement.classList.contains('dark')))

function handleTabKeys(event: KeyboardEvent) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  const current = tabs.findIndex((tab) => tab.id === activeTab.value)
  const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length
  activeTab.value = tabs[next].id
  requestAnimationFrame(() => document.getElementById(`${activeTab.value}-tab`)?.focus())
}
</script>
