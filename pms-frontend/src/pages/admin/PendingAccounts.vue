<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import { CheckCircle, Clock, Download, Eye, FileText, RefreshCw, UserCheck } from 'lucide-vue-next';
import { useUserStore } from '@/store/user';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import axiosInstance from '@/utils/axiosInstance';
import type { ProponentRegistrationDocument, User } from '@/types/user';

const userStore = useUserStore();
const router = useRouter();
const route = useRoute();
const layoutStore = useLayoutStore();
const approvingIds = ref<Set<number>>(new Set());
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);
const accountCardRefs = new Map<number, HTMLElement>();
const highlightedUserId = computed(() => Number(route.query.user_id || 0));

const pendingProponents = computed(() =>
  userStore.users.filter((user) =>
    !user.is_active &&
    (user.role?.name === 'Proponent' || user.position === 'External Proponent Representative')
  )
);

const setApproving = (id: number, value: boolean) => {
  const next = new Set(approvingIds.value);
  if (value) next.add(id);
  else next.delete(id);
  approvingIds.value = next;
};

const loadPending = async () => {
  await userStore.fetchUsers({
    is_active: false,
    per_page: 100,
    page: 1,
    sort_by: 'created_at',
    sort_dir: 'desc',
  });
  await scrollToHighlightedAccount();
};

const setAccountCardRef = (id: number, element: unknown) => {
  if (element instanceof HTMLElement) {
    accountCardRefs.set(id, element);
  } else {
    accountCardRefs.delete(id);
  }
};

const scrollToHighlightedAccount = async () => {
  if (!highlightedUserId.value) return;
  await nextTick();
  accountCardRefs.get(highlightedUserId.value)?.scrollIntoView({
    behavior: 'smooth',
    block: 'center',
  });
};

const approveAccount = async (user: User) => {
  setApproving(user.id, true);
  try {
    await userStore.toggleStatus(user.id, true);
    toast.success(`${user.full_name} can now sign in`);
    await loadPending();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to approve account');
  } finally {
    setApproving(user.id, false);
  }
};

const openProfile = (user: User) => {
  router.push(`/account/profile/${user.id}`);
};

const requiredDocuments = [
  { type: 'registration_proof', label: 'SEC / DTI / CDA / Agency registration proof', required: true },
  { type: 'representative_authorization', label: 'Representative authorization letter / SPA', required: true },
  { type: 'company_profile', label: 'Company profile / capability statement', required: false },
];

const documentByType = (user: User, type: string) =>
  user.registration_documents?.find((document) => document.document_type === type);

const formatFileSize = (size?: number | null) => {
  if (!size) return '';
  if (size < 1024 * 1024) return `${Math.ceil(size / 1024)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (value?: string | null) => {
  if (!value) return '';
  return new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
};

const profileItems = (user: User) => [
  { label: 'Business summary', value: user.proponent_profile?.business_summary },
  { label: 'Project experience', value: user.proponent_profile?.project_experience },
  { label: 'Previous projects', value: user.proponent_profile?.previous_projects },
].filter((item) => Boolean(item.value));

const openRegistrationDocument = async (user: User, document: ProponentRegistrationDocument, mode: 'view' | 'download') => {
  try {
    const response = await axiosInstance.get(`/api/users/${user.id}/registration-documents/${document.id}/${mode}`, {
      responseType: 'blob',
    });
    const blob = new Blob([response.data], { type: response.headers['content-type'] || document.file_type || 'application/octet-stream' });
    const url = URL.createObjectURL(blob);

    if (mode === 'view') {
      window.open(url, '_blank', 'noopener,noreferrer');
      setTimeout(() => URL.revokeObjectURL(url), 30_000);
      return;
    }

    const link = window.document.createElement('a');
    link.href = url;
    link.download = document.file_name || `${document.document_type}.pdf`;
    link.click();
    URL.revokeObjectURL(url);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to open registration document');
  }
};

onMounted(loadPending);
watch(() => route.query.user_id, () => scrollToHighlightedAccount());
</script>

<template>
  <div class="pending-page" :class="{ 'is-dark': isDarkMode }">
    <div class="page-head">
      <div>
        <p class="eyebrow">Admin Tools</p>
        <h1>Pending Accounts</h1>
        <p>Approve external proponent registrations before they can submit LOIs or proposals.</p>
      </div>
      <button class="refresh-btn" :disabled="userStore.loading" @click="loadPending">
        <RefreshCw class="icon" :class="{ spinning: userStore.loading }" />
        Refresh
      </button>
    </div>

    <div class="status-card">
      <Clock class="status-icon" />
      <div>
        <span>{{ pendingProponents.length }}</span>
        <p>registration{{ pendingProponents.length === 1 ? '' : 's' }} awaiting admin acceptance</p>
      </div>
    </div>

    <div v-if="userStore.loading" class="empty-card">
      <RefreshCw class="spinning" />
      <p>Loading pending registrations...</p>
    </div>

    <div v-else-if="pendingProponents.length" class="account-list">
      <article
        v-for="user in pendingProponents"
        :key="user.id"
        :ref="(element) => setAccountCardRef(user.id, element)"
        class="account-card"
        :class="{ highlighted: highlightedUserId === user.id }"
      >
        <div class="account-header">
          <div class="avatar">{{ user.initials || user.full_name?.slice(0, 2).toUpperCase() }}</div>
          <div class="account-main">
            <div class="account-title">
              <h2>{{ user.organization_name || user.full_name }}</h2>
              <span>Pending review</span>
            </div>
            <p>{{ user.full_name }} · {{ user.email }}</p>
            <div class="meta-row">
              <span v-if="user.phone_number">{{ user.phone_number }}</span>
              <span v-if="user.organization_type">{{ user.organization_type }}</span>
              <span v-if="user.organization_registration_no">Reg. {{ user.organization_registration_no }}</span>
              <span v-if="user.address">{{ user.address }}</span>
            </div>
          </div>
          <div class="account-actions">
            <button class="profile-btn" @click="openProfile(user)">
              <Eye class="icon" />
              Profile
            </button>
            <button class="approve-btn" :disabled="approvingIds.has(user.id)" @click="approveAccount(user)">
              <CheckCircle class="icon" />
              Approve
            </button>
          </div>
        </div>

        <div class="review-grid">
          <section class="review-panel">
            <h3>Company Background</h3>
            <div v-if="profileItems(user).length" class="background-list">
              <div v-for="item in profileItems(user)" :key="item.label">
                <strong>{{ item.label }}</strong>
                <p>{{ item.value }}</p>
              </div>
            </div>
            <p v-else class="muted-note">No company background details were provided.</p>
          </section>

          <section class="review-panel">
            <div class="panel-head">
              <h3>Supporting Documents</h3>
              <span>{{ user.registration_documents?.length || 0 }} files</span>
            </div>
            <div class="document-list">
              <div v-for="requirement in requiredDocuments" :key="requirement.type" class="document-item" :class="{ missing: !documentByType(user, requirement.type) }">
                <FileText class="icon" />
                <div>
                  <strong>{{ requirement.label }}</strong>
                  <p v-if="documentByType(user, requirement.type)">
                    {{ documentByType(user, requirement.type)?.file_name }}
                    <span>{{ formatFileSize(documentByType(user, requirement.type)?.file_size) }}</span>
                    <span>{{ formatDate(documentByType(user, requirement.type)?.uploaded_at) }}</span>
                  </p>
                  <p v-else>{{ requirement.required ? 'Required file missing' : 'Optional file not submitted' }}</p>
                </div>
                <div v-if="documentByType(user, requirement.type)" class="document-actions">
                  <button type="button" title="View document" @click="openRegistrationDocument(user, documentByType(user, requirement.type)!, 'view')">
                    <Eye class="icon" />
                  </button>
                  <button type="button" title="Download document" @click="openRegistrationDocument(user, documentByType(user, requirement.type)!, 'download')">
                    <Download class="icon" />
                  </button>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="consent-strip">
          <CheckCircle class="icon" />
          Authority and data privacy consent were accepted during registration.
        </div>
      </article>
    </div>

    <div v-else class="empty-card">
      <UserCheck />
      <h2>No pending proponent accounts</h2>
      <p>New external registrations will appear here for review and acceptance.</p>
    </div>
  </div>
</template>

<style scoped>
.pending-page { display: grid; gap: 1rem; color: #0f172a; }
.page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; }
.eyebrow { margin: 0 0 0.25rem; color: #2563eb; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.page-head h1 { margin: 0; font-size: 1.75rem; line-height: 1.15; }
.page-head p { margin: 0.35rem 0 0; color: #64748b; }
.refresh-btn, .approve-btn, .profile-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; border: 1px solid #cbd5e1; border-radius: 0.65rem; background: #fff; color: #334155; padding: 0.65rem 0.9rem; font-weight: 800; transition: all 0.15s; }
.refresh-btn:hover, .approve-btn:hover, .profile-btn:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
.icon { width: 1rem; height: 1rem; }
.spinning { animation: spin 0.9s linear infinite; }
.status-card { display: flex; align-items: center; gap: 0.9rem; border: 1px solid #dbeafe; border-radius: 0.9rem; background: #eff6ff; padding: 1rem; }
.status-icon { width: 2rem; height: 2rem; color: #2563eb; }
.status-card span { color: #0f172a; font-size: 1.5rem; font-weight: 900; }
.status-card p { margin: 0.1rem 0 0; color: #475569; }
.account-list { display: grid; gap: 0.85rem; }
.account-card { display: grid; gap: 0.95rem; border: 1px solid #e2e8f0; border-radius: 0.9rem; background: #fff; padding: 1rem; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06); }
.account-card.highlighted { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16), 0 12px 28px rgba(15, 23, 42, 0.08); }
.account-header { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 0.9rem; }
.avatar { display: grid; place-items: center; width: 3rem; height: 3rem; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-weight: 900; }
.account-main { min-width: 0; }
.account-title { display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap; }
.account-title h2 { margin: 0; font-size: 1rem; }
.account-title span { border-radius: 999px; background: #fef3c7; color: #92400e; padding: 0.18rem 0.5rem; font-size: 0.68rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.04em; }
.account-main p { margin: 0.2rem 0 0; color: #475569; font-weight: 700; }
.meta-row { display: flex; flex-wrap: wrap; gap: 0.45rem 0.8rem; margin-top: 0.45rem; color: #64748b; font-size: 0.8rem; }
.review-grid { display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); gap: 0.8rem; }
.review-panel { border: 1px solid #e2e8f0; border-radius: 0.8rem; background: #f8fafc; padding: 0.85rem; min-width: 0; }
.panel-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.panel-head span { color: #64748b; font-size: 0.75rem; font-weight: 900; }
.review-panel h3 { margin: 0 0 0.65rem; color: #0f172a; font-size: 0.86rem; }
.background-list { display: grid; gap: 0.65rem; }
.background-list strong, .document-item strong { display: block; color: #334155; font-size: 0.76rem; }
.background-list p, .document-item p, .muted-note { margin: 0.15rem 0 0; color: #64748b; font-size: 0.78rem; line-height: 1.45; }
.document-list { display: grid; gap: 0.55rem; }
.document-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 0.65rem; border: 1px solid #dbeafe; border-radius: 0.7rem; background: #fff; padding: 0.65rem; }
.document-item > .icon { color: #2563eb; }
.document-item.missing { border-color: #fde68a; background: #fffbeb; }
.document-item.missing > .icon { color: #d97706; }
.document-item p span { margin-left: 0.45rem; color: #94a3b8; }
.document-actions { display: flex; align-items: center; gap: 0.35rem; }
.document-actions button { display: inline-grid; place-items: center; width: 2rem; height: 2rem; border: 1px solid #bfdbfe; border-radius: 0.55rem; background: #eff6ff; color: #1d4ed8; }
.document-actions button:hover { background: #dbeafe; }
.account-actions { display: flex; align-items: center; gap: 0.55rem; }
.consent-strip { display: flex; align-items: center; gap: 0.45rem; border: 1px solid #bbf7d0; border-radius: 0.7rem; background: #f0fdf4; color: #166534; padding: 0.65rem 0.8rem; font-size: 0.78rem; font-weight: 800; }
.profile-btn { white-space: nowrap; }
.approve-btn { border-color: #bbf7d0; background: #dcfce7; color: #166534; white-space: nowrap; }
.approve-btn:hover { border-color: #16a34a; background: #bbf7d0; color: #14532d; }
.approve-btn:disabled, .refresh-btn:disabled, .profile-btn:disabled { cursor: not-allowed; opacity: 0.62; }
.empty-card { display: grid; place-items: center; gap: 0.45rem; min-height: 14rem; border: 1px dashed #cbd5e1; border-radius: 0.9rem; background: #fff; color: #64748b; text-align: center; padding: 2rem; }
.empty-card svg { width: 2rem; height: 2rem; color: #94a3b8; }
.empty-card h2 { margin: 0; color: #0f172a; font-size: 1rem; }
.empty-card p { margin: 0; }
:global(.dark) .pending-page, .pending-page.is-dark { color: #e5e7eb; }
:global(.dark) .page-head p, .pending-page.is-dark .page-head p, :global(.dark) .account-main p, .pending-page.is-dark .account-main p, :global(.dark) .meta-row, .pending-page.is-dark .meta-row, :global(.dark) .empty-card, .pending-page.is-dark .empty-card { color: #94a3b8; }
:global(.dark) .refresh-btn, .pending-page.is-dark .refresh-btn, :global(.dark) .profile-btn, .pending-page.is-dark .profile-btn { border-color: #334155; background: #0f172a; color: #cbd5e1; }
:global(.dark) .refresh-btn:hover, .pending-page.is-dark .refresh-btn:hover, :global(.dark) .profile-btn:hover, .pending-page.is-dark .profile-btn:hover { border-color: #60a5fa; background: #172554; color: #bfdbfe; }
:global(.dark) .status-card, .pending-page.is-dark .status-card { border-color: #1d4ed8; background: #172554; }
:global(.dark) .status-card span, .pending-page.is-dark .status-card span, :global(.dark) .page-head h1, .pending-page.is-dark .page-head h1, :global(.dark) .account-title h2, .pending-page.is-dark .account-title h2, :global(.dark) .empty-card h2, .pending-page.is-dark .empty-card h2 { color: #f8fafc; }
:global(.dark) .account-card, .pending-page.is-dark .account-card, :global(.dark) .empty-card, .pending-page.is-dark .empty-card { border-color: #334155; background: #111827; box-shadow: none; }
:global(.dark) .account-card.highlighted, .pending-page.is-dark .account-card.highlighted { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.22); }
:global(.dark) .avatar, .pending-page.is-dark .avatar { background: #172554; color: #93c5fd; }
:global(.dark) .review-panel, .pending-page.is-dark .review-panel { border-color: #29384e; background: #0f172a; }
:global(.dark) .review-panel h3, .pending-page.is-dark .review-panel h3 { color: #f8fafc; }
:global(.dark) .background-list strong, .pending-page.is-dark .background-list strong,
:global(.dark) .document-item strong, .pending-page.is-dark .document-item strong { color: #cbd5e1; }
:global(.dark) .background-list p, .pending-page.is-dark .background-list p,
:global(.dark) .document-item p, .pending-page.is-dark .document-item p,
:global(.dark) .muted-note, .pending-page.is-dark .muted-note,
:global(.dark) .panel-head span, .pending-page.is-dark .panel-head span { color: #94a3b8; }
:global(.dark) .document-item, .pending-page.is-dark .document-item { border-color: #29384e; background: #101a2b; }
:global(.dark) .document-item.missing, .pending-page.is-dark .document-item.missing { border-color: #4b3b1d; background: #1f1a0f; }
:global(.dark) .document-actions button, .pending-page.is-dark .document-actions button { border-color: #1e3a8a; background: #172554; color: #dbeafe; }
:global(.dark) .document-actions button:hover, .pending-page.is-dark .document-actions button:hover { background: #2563eb; }
:global(.dark) .consent-strip, .pending-page.is-dark .consent-strip { border-color: #1e3a8a; background: #101f3f; color: #bfdbfe; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 720px) {
  .page-head, .account-header { align-items: stretch; grid-template-columns: 1fr; flex-direction: column; }
  .review-grid { grid-template-columns: 1fr; }
  .account-actions { align-items: stretch; flex-direction: column; }
  .approve-btn, .profile-btn { width: 100%; }
}
</style>
