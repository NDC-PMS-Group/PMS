<template>
  <section aria-labelledby="rules-heading">
    <div class="nm-toolbar">
      <div><h2 id="rules-heading">Event rules</h2><p>{{ ruleCount }} configured events</p></div>
      <button class="nm-icon-button" type="button" title="Refresh event rules" :disabled="store.loadingRules" @click="load"><RefreshCw :class="{ spin: store.loadingRules }" aria-hidden="true" /><span class="sr-only">Refresh event rules</span></button>
    </div>
    <div v-if="store.loadingRules && !ruleCount" class="nm-state">Loading event rules...</div>
    <div v-else-if="store.ruleError" class="nm-state nm-state-error" role="alert">{{ store.ruleError }} <button type="button" @click="load">Retry</button></div>
    <div v-else-if="!ruleCount" class="nm-state">No notification events are configured.</div>
    <div v-else class="nm-rule-groups">
      <section v-for="(rules, category) in store.ruleGroups" :key="category" class="nm-rule-group">
        <header><h3>{{ category }}</h3><span>{{ rules.length }}</span></header>
        <div class="nm-table-wrap">
          <table>
            <caption class="sr-only">{{ category }} notification event rules</caption>
            <thead><tr><th scope="col">Event</th><th scope="col">In app</th><th scope="col">Email</th><th scope="col">Template</th><th scope="col">State</th></tr></thead>
            <tbody>
              <tr v-for="rule in rules" :key="rule.id">
                <td><strong>{{ rule.label }}</strong><span>{{ rule.description }}</span><code>{{ rule.event_key }}</code></td>
                <td><label class="nm-switch"><input v-model="rule.in_app_enabled" type="checkbox" :disabled="saving(rule.id)" @change="save(rule)" /><span></span><b class="sr-only">In-app notifications for {{ rule.label }}</b></label></td>
                <td><label class="nm-switch"><input v-model="rule.email_enabled" type="checkbox" :disabled="saving(rule.id)" @change="save(rule)" /><span></span><b class="sr-only">Email notifications for {{ rule.label }}</b></label></td>
                <td><select v-model="rule.template_name" :disabled="saving(rule.id) || !rule.email_enabled" :aria-label="`Template for ${rule.label}`" @change="save(rule)"><option :value="null">Plain event message</option><option v-for="template in store.templateOptions" :key="template.name" :value="template.name">{{ readableName(template.name) }}</option></select></td>
                <td><span class="nm-save-state" :class="{ saving: saving(rule.id) }">{{ saving(rule.id) ? 'Saving' : 'Saved' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RefreshCw } from 'lucide-vue-next'
import { toast } from 'vue3-toastify'
import { useNotificationManagementStore } from '@/store/notifications'
import type { NotificationRule } from '@/types/notifications'

const store = useNotificationManagementStore()
const ruleCount = computed(() => Object.values(store.ruleGroups).flat().length)
const saving = (id: number) => store.savingRuleIds.has(id)
const readableName = (name: string) => name.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
async function load() { try { await Promise.all([store.fetchRules(), store.templates.length ? Promise.resolve() : store.fetchTemplates()]) } catch { /* Stores expose errors. */ } }
async function save(rule: NotificationRule) { try { await store.updateRule(rule) } catch (error: any) { toast.error(error?.response?.data?.message || `Could not update ${rule.label}.`); await store.fetchRules() } }
onMounted(load)
</script>
