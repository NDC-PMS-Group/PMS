<template>
  <section class="nm-workspace" aria-labelledby="templates-heading">
    <aside class="nm-template-list">
      <div class="nm-section-heading">
        <div><h2 id="templates-heading">Templates</h2><span>{{ store.templates.length }} total</span></div>
        <button class="nm-icon-button" type="button" title="Refresh templates" :disabled="store.loadingTemplates" @click="load">
          <RefreshCw :class="{ spin: store.loadingTemplates }" aria-hidden="true" />
          <span class="sr-only">Refresh templates</span>
        </button>
      </div>
      <div v-if="store.loadingTemplates && !store.templates.length" class="nm-state">Loading templates...</div>
      <div v-else-if="store.templateError" class="nm-state nm-state-error" role="alert">{{ store.templateError }}</div>
      <div v-else-if="!store.templates.length" class="nm-state">No templates are configured.</div>
      <button
        v-for="template in store.templates"
        :key="template.id"
        type="button"
        class="nm-template-option"
        :class="{ active: selectedId === template.id }"
        @click="selectTemplate(template.id)"
      >
        <span>{{ readableName(template.name) }}</span>
        <small>{{ template.draft ? 'Draft changes' : `Published v${template.published?.version || 1}` }}</small>
      </button>
    </aside>

    <div v-if="selected" class="nm-editor">
      <header class="nm-editor-head">
        <div>
          <div class="nm-title-line">
            <h2>{{ readableName(selected.name) }}</h2>
            <span v-if="selected.draft" class="nm-badge warning">Draft v{{ selected.draft.version }}</span>
            <span v-else class="nm-badge success">Published</span>
          </div>
          <code>{{ selected.name }}</code>
        </div>
        <div class="nm-actions">
          <button class="nm-button secondary" type="button" :disabled="busy" @click="preview">Preview</button>
          <button class="nm-button secondary" type="button" :disabled="busy || !dirty" @click="save">Save draft</button>
          <button class="nm-button primary" type="button" :disabled="busy || !selected.draft || dirty" @click="publish">Publish</button>
        </div>
      </header>

      <p v-if="statusMessage" class="nm-inline-status" aria-live="polite">{{ statusMessage }}</p>
      <p v-if="formError" class="nm-inline-status error" role="alert">{{ formError }}</p>

      <div class="nm-editor-grid">
        <form class="nm-form" @submit.prevent="save">
          <label for="template-subject">Subject</label>
          <input id="template-subject" v-model="subject" maxlength="255" required @input="dirty = true" />

          <label for="template-body">Message body</label>
          <textarea id="template-body" ref="bodyField" v-model="body" rows="17" maxlength="20000" required @input="dirty = true"></textarea>

          <div class="nm-field-row">
            <span>Variables</span>
            <span>{{ usedVariables.length }} used</span>
          </div>
          <div class="nm-token-list" aria-label="Available template variables">
            <button v-for="variable in store.variables" :key="variable.key" type="button" :title="variable.description" @click="insertVariable(variable.token)">
              {{ variable.token }}
            </button>
          </div>
        </form>

        <aside class="nm-side-panel">
          <section>
            <h3>Test delivery</h3>
            <label for="test-email">Recipient email</label>
            <div class="nm-inline-form">
              <input id="test-email" v-model="testEmail" type="email" autocomplete="email" placeholder="name@example.com" />
              <button class="nm-icon-button" type="button" title="Send test email" :disabled="busy || !testEmail" @click="sendTest">
                <Send aria-hidden="true" /><span class="sr-only">Send test email</span>
              </button>
            </div>
          </section>

          <section v-if="previewData">
            <div class="nm-section-heading compact">
              <h3>Preview</h3>
              <button class="nm-icon-button" type="button" title="Close preview" @click="previewData = null"><X aria-hidden="true" /><span class="sr-only">Close preview</span></button>
            </div>
            <strong class="nm-preview-subject">{{ previewData.subject }}</strong>
            <pre>{{ previewData.body }}</pre>
          </section>

          <section>
            <h3>Version history</h3>
            <div v-if="loadingDetail" class="nm-state compact">Loading history...</div>
            <div v-else-if="!selected.versions?.length" class="nm-state compact">No version history.</div>
            <ol v-else class="nm-version-list">
              <li v-for="version in selected.versions" :key="version.id">
                <div><strong>Version {{ version.version }}</strong><span>{{ formatDate(version.published_at || version.created_at) }}</span></div>
                <button v-if="version.status === 'published'" type="button" :disabled="busy" @click="restore(version.id)">Restore</button>
              </li>
            </ol>
          </section>
        </aside>
      </div>
    </div>
    <div v-else-if="!store.loadingTemplates" class="nm-state nm-editor-empty">Select a template to edit.</div>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { RefreshCw, Send, X } from 'lucide-vue-next'
import { useNotificationManagementStore } from '@/store/notifications'
import type { TemplatePreview } from '@/types/notifications'

const store = useNotificationManagementStore()
const selectedId = ref<number | null>(null)
const subject = ref('')
const body = ref('')
const testEmail = ref('')
const dirty = ref(false)
const busy = ref(false)
const loadingDetail = ref(false)
const formError = ref('')
const statusMessage = ref('')
const previewData = ref<TemplatePreview | null>(null)
const bodyField = ref<HTMLTextAreaElement | null>(null)
const selected = computed(() => store.templates.find((template) => template.id === selectedId.value))
const usedVariables = computed(() => [...subject.value.matchAll(/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/gi), ...body.value.matchAll(/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/gi)].map((match) => match[1]).filter((key, index, list) => list.indexOf(key) === index))

function readableName(name: string) { return name.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ') }
function formatDate(value: string | null) { return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Not published' }
function setFields() {
  const version = selected.value?.draft || selected.value?.published
  subject.value = version?.subject || ''
  body.value = version?.body || ''
  dirty.value = false
  previewData.value = null
}
function messageFrom(error: any) { return Object.values(error?.response?.data?.errors || {}).flat()[0] as string || error?.response?.data?.message || 'The request could not be completed.' }

async function load() {
  try {
    await store.fetchTemplates()
    if (!selectedId.value && store.templates.length) await selectTemplate(store.templates[0].id)
  } catch { /* Store exposes the error state. */ }
}
async function selectTemplate(id: number) {
  selectedId.value = id
  loadingDetail.value = true
  formError.value = ''
  statusMessage.value = ''
  try { await store.fetchTemplate(id); setFields() } catch (error) { formError.value = messageFrom(error) } finally { loadingDetail.value = false }
}
async function save() {
  if (!selectedId.value) return
  busy.value = true; formError.value = ''; statusMessage.value = ''
  try { await store.saveDraft(selectedId.value, subject.value, body.value); setFields(); statusMessage.value = 'Draft saved.' } catch (error) { formError.value = messageFrom(error) } finally { busy.value = false }
}
async function preview() {
  if (!selectedId.value) return
  busy.value = true; formError.value = ''
  try { previewData.value = await store.previewTemplate(selectedId.value, subject.value, body.value) } catch (error) { formError.value = messageFrom(error) } finally { busy.value = false }
}
async function publish() {
  if (!selectedId.value) return
  busy.value = true; formError.value = ''; statusMessage.value = ''
  try { await store.publishTemplate(selectedId.value); setFields(); statusMessage.value = 'Template published.' } catch (error) { formError.value = messageFrom(error) } finally { busy.value = false }
}
async function restore(versionId: number) {
  if (!selectedId.value) return
  busy.value = true; formError.value = ''; statusMessage.value = ''
  try { await store.restoreVersion(selectedId.value, versionId); setFields(); statusMessage.value = 'Version restored as a draft.' } catch (error) { formError.value = messageFrom(error) } finally { busy.value = false }
}
async function sendTest() {
  if (!selectedId.value) return
  busy.value = true; formError.value = ''; statusMessage.value = ''
  try { await store.sendTest(selectedId.value, testEmail.value, subject.value, body.value); statusMessage.value = `Test queued for ${testEmail.value}.` } catch (error) { formError.value = messageFrom(error) } finally { busy.value = false }
}
async function insertVariable(token: string) {
  const field = bodyField.value
  if (!field) { body.value += token; return }
  const start = field.selectionStart
  body.value = `${body.value.slice(0, start)}${token}${body.value.slice(field.selectionEnd)}`
  dirty.value = true
  await nextTick()
  field.focus(); field.setSelectionRange(start + token.length, start + token.length)
}

onMounted(load)
</script>
