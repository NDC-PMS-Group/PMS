<template>
  <div class="notifications-page" :class="{ 'is-dark': isDarkMode }">
    <div class="page-head">
      <div>
        <p class="eyebrow">PMS</p>
        <h1>Notifications</h1>
        <p>All project updates, approvals, assignments, and membership changes.</p>
      </div>
      <div class="head-actions">
        <button class="btn ghost" @click="loadNotifications" :disabled="loading">
          <RefreshCwIcon class="icon" :class="{ spinning: loading }" />
          Refresh
        </button>
        <button class="btn primary" @click="markAllAsRead" :disabled="!unreadCount">
          <CheckCheckIcon class="icon" />
          Mark All Read
        </button>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-card">
        <span>Total</span>
        <strong>{{ total }}</strong>
      </div>
      <div class="stat-card">
        <span>Unread</span>
        <strong>{{ unreadCount }}</strong>
      </div>
      <div class="stat-card">
        <span>This Page</span>
        <strong>{{ notifications.length }}</strong>
      </div>
    </div>

    <div class="notification-list">
      <div v-if="loading && !notifications.length" class="state-box">
        Loading notifications...
      </div>

      <div v-else-if="!notifications.length" class="state-box">
        <BellIcon class="state-icon" />
        <span>No notifications yet.</span>
      </div>

      <button
        v-for="item in notifications"
        v-else
        :key="item.id"
        class="notification-row"
        :class="{ unread: !item.is_read }"
        @click="openNotification(item)"
      >
        <div class="row-icon">
          <component :is="iconFor(item.type)" class="icon" />
        </div>
        <div class="row-main">
          <div class="row-title">
            <strong>{{ item.title }}</strong>
            <span v-if="!item.is_read">Unread</span>
          </div>
          <p>{{ item.message }}</p>
          <small>{{ formatType(item.type) }} · {{ moment(item.created_at).format('MMM D, YYYY h:mm A') }}</small>
        </div>
        <ArrowRightIcon class="row-arrow" />
      </button>
    </div>

    <div v-if="lastPage > 1" class="pagination-row">
      <button class="btn ghost" :disabled="page <= 1 || loading" @click="changePage(page - 1)">Previous</button>
      <span>Page {{ page }} of {{ lastPage }}</span>
      <button class="btn ghost" :disabled="page >= lastPage || loading" @click="changePage(page + 1)">Next</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { SITE_MODE } from '@/app/const';
import { useLayoutStore } from '@/store/layout';
import axiosInstance from '@/utils/axiosInstance';
import moment from 'moment';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  ArrowRight as ArrowRightIcon,
  Bell as BellIcon,
  CheckCheck as CheckCheckIcon,
  ClipboardCheck as ClipboardCheckIcon,
  FileText as FileTextIcon,
  FolderKanban as FolderKanbanIcon,
  ListChecks as ListChecksIcon,
  RefreshCw as RefreshCwIcon,
  RotateCcw as RotateCcwIcon,
  Users as UsersIcon,
} from 'lucide-vue-next';

interface AppNotification {
  id: number;
  type: string;
  title: string;
  message: string;
  related_entity_type?: string | null;
  related_entity_id?: number | null;
  is_read: boolean;
  created_at: string;
}

const router = useRouter();
const layoutStore = useLayoutStore();
const notifications = ref<AppNotification[]>([]);
const loading = ref(false);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);
const unreadCount = computed(() => notifications.value.filter((item) => !item.is_read).length);

const loadNotifications = async () => {
  loading.value = true;
  try {
    const response = await axiosInstance.get('/api/notifications', {
      params: { page: page.value, per_page: 15 },
    });
    notifications.value = response.data?.data || [];
    lastPage.value = response.data?.last_page || 1;
    total.value = response.data?.total || notifications.value.length;
  } finally {
    loading.value = false;
  }
};

const changePage = async (nextPage: number) => {
  page.value = nextPage;
  await loadNotifications();
};

const markAllAsRead = async () => {
  await axiosInstance.post('/api/notifications/read-all');
  notifications.value = notifications.value.map((item) => ({ ...item, is_read: true }));
};

const markAsRead = async (notification: AppNotification) => {
  if (notification.is_read) return;
  await axiosInstance.post(`/api/notifications/${notification.id}/read`);
  notification.is_read = true;
};

const openNotification = async (notification: AppNotification) => {
  await markAsRead(notification);
  const target = notificationTarget(notification);
  if (target) router.push(target);
};

const notificationTarget = (notification: AppNotification) => {
  const entity = notification.related_entity_type || '';
  const id = notification.related_entity_id;

  if (entity.includes('Project') && id) {
    return { path: '/projects', query: { project_id: id, tab: notification.type.includes('approval') ? 'approval' : 'overview' } };
  }

  if (entity.includes('Task')) {
    return { path: '/tasks', query: id ? { task_id: id } : {} };
  }

  return null;
};

const iconFor = (type: string) => {
  if (type.includes('approval')) return ClipboardCheckIcon;
  if (type.includes('member')) return UsersIcon;
  if (type.includes('task')) return ListChecksIcon;
  if (type.includes('document')) return FileTextIcon;
  if (type.includes('returned') || type.includes('revision')) return RotateCcwIcon;
  return FolderKanbanIcon;
};

const formatType = (type: string) =>
  type.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

onMounted(loadNotifications);
</script>

<style scoped>
.notifications-page {
  --nt-bg: #ffffff;
  --nt-bg-2: #f8fafc;
  --nt-border: #e2e8f0;
  --nt-text: #0f172a;
  --nt-sub: #64748b;
  --nt-muted: #94a3b8;
  --nt-soft: #f1f5f9;
  --nt-hover: #f8fafc;
  --nt-unread: #eff6ff;
  --nt-icon-bg: #f1f5f9;
  --nt-icon: #2563eb;
  display:flex;
  flex-direction:column;
  gap:1rem;
}
.notifications-page.is-dark {
  --nt-bg: #172033;
  --nt-bg-2: #101827;
  --nt-border: #2b3950;
  --nt-text: #f8fafc;
  --nt-sub: #cbd5e1;
  --nt-muted: #94a3b8;
  --nt-soft: #111827;
  --nt-hover: #1e293b;
  --nt-unread: rgba(37, 99, 235, 0.18);
  --nt-icon-bg: #101827;
  --nt-icon: #60a5fa;
}
.page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
.page-head h1 { margin:0; color:var(--nt-text); font-size:1.65rem; font-weight:800; }
.page-head p { margin:0.3rem 0 0; color:var(--nt-sub); }
.eyebrow { margin:0 0 0.25rem !important; color:#2563eb !important; font-size:0.72rem; font-weight:800; letter-spacing:0.08em; text-transform:uppercase; }
.head-actions { display:flex; align-items:center; gap:0.5rem; }
.btn { display:inline-flex; align-items:center; justify-content:center; gap:0.45rem; min-height:2.4rem; padding:0 0.85rem; border-radius:0.55rem; font-size:0.82rem; font-weight:700; transition:0.15s ease; }
.btn.primary { background:#2563eb; color:white; }
.btn.primary:hover { background:#1d4ed8; }
.btn.ghost { border:1px solid var(--nt-border); color:var(--nt-sub); background:var(--nt-bg); }
.btn.ghost:hover { background:var(--nt-hover); color:var(--nt-text); }
.btn:disabled { opacity:0.5; cursor:not-allowed; }
.stats-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:0.75rem; }
.stat-card { display:flex; align-items:center; justify-content:space-between; padding:1rem; border:1px solid var(--nt-border); border-radius:0.7rem; background:linear-gradient(180deg,var(--nt-bg),var(--nt-bg-2)); }
.stat-card span { color:var(--nt-sub); font-size:0.75rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; }
.stat-card strong { color:var(--nt-text); font-size:1.35rem; font-weight:900; }
.notification-list { display:flex; flex-direction:column; overflow:hidden; border:1px solid var(--nt-border); border-radius:0.75rem; background:var(--nt-bg); }
.notification-row { width:100%; display:flex; align-items:center; gap:1rem; padding:1rem; border-bottom:1px solid var(--nt-border); background:var(--nt-bg); text-align:left; transition:0.15s ease; }
.notification-row:hover { background:var(--nt-hover); }
.notification-row.unread { background:var(--nt-unread); }
.row-icon { display:flex; align-items:center; justify-content:center; width:2.5rem; height:2.5rem; border-radius:0.6rem; background:var(--nt-icon-bg); color:var(--nt-icon); flex-shrink:0; }
.row-main { min-width:0; flex:1; }
.row-title { display:flex; align-items:center; gap:0.5rem; }
.row-title strong { color:var(--nt-text); font-size:0.95rem; }
.row-title span { padding:0.15rem 0.45rem; border-radius:999px; background:#2563eb; color:white; font-size:0.65rem; font-weight:800; text-transform:uppercase; }
.row-main p { margin:0.25rem 0; color:var(--nt-sub); font-size:0.85rem; }
.row-main small { color:var(--nt-muted); font-size:0.75rem; }
.row-arrow { width:1rem; height:1rem; color:var(--nt-muted); }
.state-box { min-height:12rem; display:flex; flex-direction:column; gap:0.5rem; align-items:center; justify-content:center; color:var(--nt-sub); }
.state-icon,.icon { width:1rem; height:1rem; }
.state-icon { width:1.6rem; height:1.6rem; }
.pagination-row { display:flex; align-items:center; justify-content:center; gap:1rem; color:var(--nt-sub); font-size:0.85rem; }
.spinning { animation:spin 0.8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width: 720px) {
  .page-head { flex-direction:column; }
  .head-actions,.stats-row { width:100%; }
  .stats-row { grid-template-columns:1fr; }
}
</style>
