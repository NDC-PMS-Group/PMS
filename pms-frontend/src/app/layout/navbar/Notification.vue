<script lang="ts" setup>
import NavBtn from "@/app/layout/navbar/Button.vue";
import { SITE_MODE } from "@/app/const";
import { useLayoutStore } from "@/store/layout";
import axiosInstance from "@/utils/axiosInstance";
import moment from "moment";
import {
  BellRing,
  CheckCheck,
  ClipboardCheck,
  FileText,
  FolderKanban,
  ListChecks,
  Mail,
  MoveRight,
  RefreshCw,
  RotateCcw,
  Users,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

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

const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);
const unreadCount = computed(() => notifications.value.filter((item) => !item.is_read).length);
const recentNotifications = computed(() => notifications.value.slice(0, 6));

const loadNotifications = async () => {
  loading.value = true;
  try {
    const response = await axiosInstance.get("/api/notifications", { params: { per_page: 8 } });
    notifications.value = response.data?.data || [];
  } finally {
    loading.value = false;
  }
};

const markAsRead = async (notification: AppNotification) => {
  if (notification.is_read) return;
  await axiosInstance.post(`/api/notifications/${notification.id}/read`);
  notification.is_read = true;
};

const markAllAsRead = async () => {
  await axiosInstance.post("/api/notifications/read-all");
  notifications.value = notifications.value.map((item) => ({ ...item, is_read: true }));
};

const openNotification = async (notification: AppNotification) => {
  await markAsRead(notification);
  const target = notificationTarget(notification);
  if (target) {
    router.push(target);
  }
};

const goToAll = () => {
  router.push("/notifications");
};

const notificationTarget = (notification: AppNotification) => {
  const entity = notification.related_entity_type || "";
  const id = notification.related_entity_id;

  if (entity.includes("Project") && id) {
    return { path: "/projects", query: { project_id: id, tab: projectTabForNotification(notification.type) } };
  }

  if (entity.includes("User") && id) {
    if (notification.type.includes("account_registered")) {
      return { path: "/admin/pending-accounts", query: { user_id: id } };
    }
    return { path: `/account/profile/${id}` };
  }

  if (entity.includes("Task")) {
    return { path: "/tasks", query: id ? { task_id: id } : {} };
  }

  return null;
};

const projectTabForNotification = (type: string) => {
  if (type.includes("requirement") || type.includes("document") || type.includes("file")) return "requirements";
  if (type.includes("monitoring")) return "monitoring";
  if (type.includes("approval") || type.includes("soi") || type.includes("returned")) return "approval";
  return "overview";
};

const iconFor = (type: string) => {
  if (type.includes("approval") || type.includes("soi")) return ClipboardCheck;
  if (type.includes("member") || type.includes("account")) return Users;
  if (type.includes("task")) return ListChecks;
  if (type.includes("document")) return FileText;
  if (type.includes("returned") || type.includes("revision")) return RotateCcw;
  return FolderKanban;
};

let refreshTimer: ReturnType<typeof window.setInterval> | null = null;

const refreshOnFocus = () => {
  if (!document.hidden) {
    loadNotifications();
  }
};

onMounted(() => {
  loadNotifications();
  refreshTimer = window.setInterval(loadNotifications, 30000);
  window.addEventListener("focus", refreshOnFocus);
  document.addEventListener("visibilitychange", refreshOnFocus);
});

onBeforeUnmount(() => {
  if (refreshTimer) {
    window.clearInterval(refreshTimer);
    refreshTimer = null;
  }
  window.removeEventListener("focus", refreshOnFocus);
  document.removeEventListener("visibilitychange", refreshOnFocus);
});
</script>

<template>
  <TMenu>
    <NavBtn class="dropdown" @click="loadNotifications">
      <BellRing
        class="inline-block size-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"
      />
      <span v-if="unreadCount" class="absolute top-0 right-0 flex min-w-[1rem] h-4 px-1 items-center justify-center rounded-full bg-sky-500 text-[10px] font-bold text-white">
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </NavBtn>
    <template #content>
      <div class="notify-popover" :class="{ 'is-dark': isDarkMode }">
        <div class="notify-head">
          <div>
            <h6>
              Notifications
              <span>
                {{ unreadCount }}
              </span>
            </h6>
            <p>Recent project activity</p>
          </div>
          <div class="flex items-center gap-1">
            <button class="icon-btn" title="Refresh" @click.stop="loadNotifications">
              <RefreshCw class="size-4" :class="{ 'animate-spin': loading }" />
            </button>
            <button class="icon-btn" title="Mark all as read" :disabled="!unreadCount" @click.stop="markAllAsRead">
              <CheckCheck class="size-4" />
            </button>
          </div>
        </div>

        <simplebar class="max-h-[360px]">
          <div v-if="loading && !notifications.length" class="notify-state">
            Loading notifications...
          </div>

          <div v-else-if="!recentNotifications.length" class="notify-state empty">
            <Mail class="size-5" />
            No notifications yet.
          </div>

          <div v-else class="flex flex-col">
            <button
              v-for="item in recentNotifications"
              :key="item.id"
              type="button"
              class="notify-item"
              :class="{ unread: !item.is_read }"
              @click="openNotification(item)"
            >
              <div class="notify-icon">
                <component :is="iconFor(item.type)" class="size-4" />
              </div>
              <div class="notify-copy">
                <div class="notify-title-row">
                  <h6>{{ item.title }}</h6>
                  <span v-if="!item.is_read"></span>
                </div>
                <p class="notify-message">{{ item.message }}</p>
                <p class="notify-time">{{ moment(item.created_at).fromNow() }}</p>
              </div>
            </button>
          </div>
        </simplebar>

        <div class="notify-foot">
          <button class="mark-read-btn" @click="markAllAsRead">
            Mark all read
          </button>
          <button class="view-all-btn" @click="goToAll">
            View all
            <MoveRight class="size-3.5" />
          </button>
        </div>
      </div>
    </template>
  </TMenu>
</template>

<style scoped>
.notify-popover {
  --np-bg: #ffffff;
  --np-bg-2: #f8fafc;
  --np-border: #e2e8f0;
  --np-border-soft: #f1f5f9;
  --np-text: #0f172a;
  --np-sub: #64748b;
  --np-muted: #94a3b8;
  --np-icon-bg: #f1f5f9;
  --np-icon: #2563eb;
  --np-hover: #f8fafc;
  --np-unread: #eff6ff;
  width: 21rem;
  overflow: hidden;
  border: 1px solid var(--np-border);
  border-radius: 0.75rem;
  background: var(--np-bg);
  box-shadow: 0 22px 44px rgba(15, 23, 42, 0.16);
}

.notify-popover.is-dark {
  --np-bg: #111827;
  --np-bg-2: #0f172a;
  --np-border: #334155;
  --np-border-soft: #1f2937;
  --np-text: #f8fafc;
  --np-sub: #cbd5e1;
  --np-muted: #94a3b8;
  --np-icon-bg: #1e293b;
  --np-icon: #60a5fa;
  --np-hover: #1e293b;
  --np-unread: rgba(37, 99, 235, 0.2);
  box-shadow: 0 22px 44px rgba(0, 0, 0, 0.4);
}

.notify-head,
.notify-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border-color: var(--np-border);
}

.notify-head {
  border-bottom: 1px solid var(--np-border);
  padding: 1rem;
}

.notify-head h6 {
  margin: 0;
  color: var(--np-text);
  font-size: 0.9rem;
  font-weight: 800;
}

.notify-head h6 span {
  margin-left: 0.35rem;
  border-radius: 999px;
  background: #f97316;
  padding: 0.1rem 0.45rem;
  color: #fff;
  font-size: 0.75rem;
}

.notify-head p,
.notify-message,
.notify-time,
.notify-state {
  color: var(--np-sub);
}

.notify-head p {
  margin: 0.2rem 0 0;
  font-size: 0.75rem;
}

.notify-state {
  display: flex;
  min-height: 7rem;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
}

.notify-state.empty {
  flex-direction: column;
  gap: 0.5rem;
}

.notify-item {
  display: flex;
  width: 100%;
  gap: 0.75rem;
  border-bottom: 1px solid var(--np-border-soft);
  background: var(--np-bg);
  padding: 1rem;
  text-align: left;
  transition: 0.15s ease;
}

.notify-item:hover {
  background: var(--np-hover);
}

.notify-item.unread {
  background: var(--np-unread);
}

.notify-icon {
  display: flex;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  margin-top: 0.125rem;
  border-radius: 0.55rem;
  background: var(--np-icon-bg);
  color: var(--np-icon);
}

.notify-copy {
  min-width: 0;
  flex: 1;
}

.notify-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
}

.notify-title-row h6 {
  max-width: 100%;
  overflow: hidden;
  color: var(--np-text);
  font-size: 0.86rem;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notify-title-row span {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  margin-top: 0.3rem;
  border-radius: 999px;
  background: #0ea5e9;
}

.notify-message {
  display: -webkit-box;
  overflow: hidden;
  margin-top: 0.25rem;
  font-size: 0.78rem;
  line-height: 1.35rem;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.notify-time {
  margin-top: 0.25rem;
  color: var(--np-muted);
  font-size: 0.7rem;
  font-weight: 700;
}

.notify-foot {
  border-top: 1px solid var(--np-border);
  padding: 0.75rem;
}

.mark-read-btn {
  color: var(--np-sub);
  font-size: 0.75rem;
  font-weight: 800;
  transition: 0.15s ease;
}

.mark-read-btn:hover {
  color: var(--np-text);
}

.view-all-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border-radius: 0.5rem;
  background: #2563eb;
  padding: 0.55rem 0.8rem;
  color: white;
  font-size: 0.75rem;
  font-weight: 800;
  transition: 0.15s ease;
}

.view-all-btn:hover {
  background: #1d4ed8;
}

.icon-btn {
  display: inline-flex;
  width: 2rem;
  height: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  color: rgb(100 116 139);
  transition: 0.15s ease;
}

.icon-btn:hover {
  background: var(--np-hover);
  color: var(--np-text);
}

.icon-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

@media (min-width: 1024px) {
  .notify-popover {
    width: 28rem;
  }
}
</style>
