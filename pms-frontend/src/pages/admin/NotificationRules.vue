<template>
  <div class="rules-page animate-fade-in" :class="{ 'is-dark': isDarkMode }">
    <header class="page-head">
      <div>
        <p class="eyebrow">Admin Tools</p>
        <h1>Notification Rules</h1>
        <p>Control which project transactions create in-app notices and email alerts.</p>
      </div>
      <button class="refresh-btn" :disabled="loading" @click="loadRules">
        <RefreshCwIcon :class="{ spin: loading }" />
        Refresh
      </button>
    </header>

    <div class="policy-note">
      <MailCheckIcon />
      <div>
        <strong>SOI communication policy</strong>
        <p>All transaction emails are enabled by default. Disable an event only when NDC has another documented communication channel.</p>
      </div>
    </div>

    <div v-if="loading && !Object.keys(groups).length" class="loading-card">
      <div class="loader-spinner"></div>
      <span>Loading notification rules...</span>
    </div>

    <section v-for="(events, category) in groups" :key="category" class="rule-section">
      <div class="section-head">
        <div>
          <h2>{{ category }}</h2>
          <span>{{ events.length }} transaction{{ events.length === 1 ? '' : 's' }}</span>
        </div>
      </div>

      <div class="rule-list">
        <article v-for="event in events" :key="event.id" class="rule-row">
          <div class="rule-copy">
            <strong>{{ event.label }}</strong>
            <p>{{ event.description }}</p>
            <code>{{ event.event_key }}</code>
          </div>

          <div class="switch-field">
            <span>In App</span>
            <label class="toggle-switch">
              <input v-model="event.in_app_enabled" type="checkbox" @change="saveRule(event)" />
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="switch-field">
            <span>Email</span>
            <label class="toggle-switch">
              <input v-model="event.email_enabled" type="checkbox" @change="saveRule(event)" />
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="template-field">
            <span>Email Template</span>
            <select v-model="event.template_name" @change="saveRule(event)">
              <option :value="null">Plain transaction message</option>
              <option v-for="template in templates" :key="template.name" :value="template.name">
                {{ template.name }}
              </option>
            </select>
          </div>

          <div class="save-status-wrapper">
            <span class="save-state" :class="{ saving: savingIds.has(event.id) }">
              <span class="indicator-dot" :class="{ pulse: savingIds.has(event.id) }"></span>
              {{ savingIds.has(event.id) ? 'Saving' : 'Saved' }}
            </span>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { MailCheck as MailCheckIcon, RefreshCw as RefreshCwIcon } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axiosInstance from '@/utils/axiosInstance';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';

interface NotificationRule {
  id: number;
  event_key: string;
  label: string;
  category: string;
  description: string | null;
  in_app_enabled: boolean;
  email_enabled: boolean;
  template_name: string | null;
}

interface EmailTemplateOption {
  name: string;
  subject: string;
  is_active: boolean;
}

const groups = ref<Record<string, NotificationRule[]>>({});
const templates = ref<EmailTemplateOption[]>([]);
const loading = ref(false);
const savingIds = ref(new Set<number>());
const layoutStore = useLayoutStore();
const isDarkMode = computed(() =>
  layoutStore.mode === SITE_MODE.DARK
  || (typeof document !== 'undefined' && document.documentElement.classList.contains('dark'))
);

const loadRules = async () => {
  loading.value = true;
  try {
    const response = await axiosInstance.get('/api/notification-event-settings');
    groups.value = response.data?.events || {};
    templates.value = response.data?.templates || [];
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to load notification rules.');
  } finally {
    loading.value = false;
  }
};

const saveRule = async (event: NotificationRule) => {
  savingIds.value = new Set(savingIds.value).add(event.id);
  try {
    await axiosInstance.put(`/api/notification-event-settings/${event.id}`, {
      in_app_enabled: event.in_app_enabled,
      email_enabled: event.email_enabled,
      template_name: event.template_name || null,
    });
  } catch (error: any) {
    toast.error(error?.response?.data?.message || `Failed to update ${event.label}.`);
    await loadRules();
  } finally {
    const next = new Set(savingIds.value);
    next.delete(event.id);
    savingIds.value = next;
  }
};

onMounted(loadRules);
</script>

<style scoped>
.rules-page {
  --card: #ffffff;
  --soft: #f8fafc;
  --border: #e2e8f0;
  --text: #0f172a;
  --sub: #64748b;
  --shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.02);
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  color: var(--text);
  font-family: inherit;
}

:global(.dark) .rules-page, .rules-page.is-dark {
  --card: #1e293b;
  --soft: #0f172a;
  --border: #334155;
  --text: #f8fafc;
  --sub: #94a3b8;
  --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
}

.page-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.page-head h1 {
  margin: 0.25rem 0;
  font-size: 1.5rem;
  font-weight: 700;
}

.page-head p {
  margin: 0;
  font-size: 0.875rem;
  color: var(--sub);
}

.eyebrow {
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #2563eb !important;
}

.refresh-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  padding: 0.6rem 1rem;
  border-radius: 0.5rem;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: var(--shadow);
  transition: all 0.2s;
}

.refresh-btn:hover {
  background: var(--soft);
}

.refresh-btn svg {
  width: 0.95rem;
  height: 0.95rem;
}

.spin {
  animation: spin 0.8s linear infinite;
}

.policy-note {
  display: flex;
  gap: 0.85rem;
  align-items: flex-start;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  padding: 1rem;
  border-radius: 0.75rem;
  color: #1e3a8a;
  font-size: 0.875rem;
}

:global(.dark) .policy-note, .rules-page.is-dark .policy-note {
  border-color: #1d4ed8;
  background: rgba(30, 64, 175, 0.2);
  color: #bfdbfe;
}

.policy-note svg {
  width: 1.25rem;
  height: 1.25rem;
  flex-shrink: 0;
  color: #2563eb;
}

:global(.dark) .policy-note svg, .rules-page.is-dark .policy-note svg {
  color: #60a5fa;
}

.policy-note strong {
  display: block;
  font-weight: 700;
  margin-bottom: 0.15rem;
}

.policy-note p {
  margin: 0;
  color: inherit;
  opacity: 0.9;
  line-height: 1.45;
}

.rule-section {
  border: 1px solid var(--border);
  background: var(--card);
  border-radius: 0.75rem;
  overflow: hidden;
  box-shadow: var(--shadow);
}

.loading-card {
  border: 1px solid var(--border);
  background: var(--card);
  border-radius: 0.75rem;
  padding: 3rem;
  text-align: center;
  color: var(--sub);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.875rem;
}

.loader-spinner {
  width: 1.5rem;
  height: 1.5rem;
  border: 2px solid var(--border);
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.section-head {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border);
  background: var(--soft);
}

.section-head h2 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.section-head span {
  color: var(--sub);
  font-size: 0.72rem;
  font-weight: 600;
}

.rule-list {
  display: flex;
  flex-direction: column;
}

.rule-row {
  display: grid;
  grid-template-columns: minmax(16rem, 1fr) 5.5rem 5.5rem minmax(13rem, 0.55fr) 4.5rem;
  align-items: center;
  gap: 1.25rem;
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid var(--border);
  transition: background-color 0.15s ease;
}

.rule-row:hover {
  background-color: var(--soft);
}

.rule-row:last-child {
  border-bottom: 0;
}

.rule-copy strong {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
}

.rule-copy p {
  margin: 0.25rem 0;
  color: var(--sub);
  font-size: 0.75rem;
  line-height: 1.4;
}

.rule-copy code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.65rem;
  color: #2563eb;
  background: rgba(37, 99, 235, 0.08);
  padding: 0.1rem 0.3rem;
  border-radius: 0.25rem;
}

:global(.dark) .rule-copy code, .rules-page.is-dark .rule-copy code {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.12);
}

.switch-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  color: var(--sub);
  font-size: 0.7rem;
  font-weight: 700;
  align-items: flex-start;
}

/* Custom Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 2.25rem;
  height: 1.25rem;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--border);
  transition: 0.2s;
  border-radius: 9999px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 0.95rem;
  width: 0.95rem;
  left: 2px;
  bottom: 2px;
  background-color: #ffffff;
  transition: 0.2s;
  border-radius: 50%;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
}

input:checked + .toggle-slider {
  background-color: #2563eb;
}

input:checked + .toggle-slider:before {
  transform: translateX(1rem);
}

.template-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  color: var(--sub);
  font-size: 0.7rem;
  font-weight: 700;
}

.template-field select {
  width: 100%;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  padding: 0.45rem 2rem 0.45rem 0.65rem;
  border-radius: 0.375rem;
  font-size: 0.8rem;
  font-weight: 600;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.6rem center;
  background-size: 0.85rem;
  cursor: pointer;
  transition: all 0.15s ease;
  box-shadow: var(--shadow);
}

.template-field select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 2.5px rgba(37, 99, 235, 0.18);
}

.save-status-wrapper {
  display: flex;
  justify-content: flex-end;
}

.save-state {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.7rem;
  font-weight: 700;
  color: #10b981;
}

.save-state.saving {
  color: #f59e0b;
}

.indicator-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
  background-color: #10b981;
}

.save-state.saving .indicator-dot {
  background-color: #f59e0b;
}

.pulse {
  animation: pulse-dot 1.2s infinite ease-in-out;
}

/* Animations */
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes pulse-dot {
  0%, 100% {
    opacity: 0.5;
  }
  50% {
    opacity: 1;
    transform: scale(1.2);
  }
}

.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive constraints */
@media (max-width: 960px) {
  .rule-row {
    grid-template-columns: 1fr 5.5rem 5.5rem;
  }
  
  .template-field {
    grid-column: 1 / -1;
  }
  
  .save-status-wrapper {
    grid-column: 1 / -1;
    justify-content: flex-start;
    padding-top: 0.25rem;
  }
}

@media (max-width: 560px) {
  .page-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }
  
  .page-head button {
    width: 100%;
    justify-content: center;
  }

  .rule-row {
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }
  
  .rule-copy {
    grid-column: 1 / -1;
  }
}
</style>
