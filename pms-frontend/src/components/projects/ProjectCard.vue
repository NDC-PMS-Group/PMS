<!-- src/components/projects/ProjectCard.vue -->
<template>
  <div class="project-card" :class="{ 'is-dark': isDarkMode, archived: project.is_archived, overdue: project.is_overdue }" @click="$emit('view', project)">
    <div class="accent-line" :style="{ background: accentColor }"></div>

    <!-- Header -->
    <div class="card-header">
      <div class="card-meta">
        <span class="project-code">{{ project.project_code }}</span>
        <div class="badges">
          <span v-if="project.is_svf" class="badge svf">SVF</span>
          <span v-if="project.is_overdue" class="badge overdue">Overdue</span>
          <span v-if="project.is_archived" class="badge archived">Archived</span>
        </div>
      </div>
      <div class="dropdown-wrap" ref="dropdownRef" @click.stop>
        <button class="menu-btn" @click.stop="toggleDropdown" :class="{ active: showDropdown }">
          <MoreHorizontalIcon class="icon" />
        </button>
        <Transition name="dd">
          <div v-if="showDropdown" class="dropdown">
            <button class="dd-item" @click="handleAction('view')"><EyeIcon class="di" /> View Details</button>
            <button class="dd-item" @click="handleAction('edit')"><EditIcon class="di" /> Edit Project</button>
            <div class="dd-sep"></div>
            <button class="dd-item" @click="handleAction('archive')"><ArchiveIcon class="di" /> {{ project.is_archived ? 'Unarchive' : 'Archive' }}</button>
            <button class="dd-item danger" @click="handleAction('delete')"><Trash2Icon class="di" /> Delete</button>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Title + Desc -->
    <div class="card-body">
      <h3 class="project-title">{{ project.title }}</h3>
      <p class="project-desc">{{ project.description || 'No description provided.' }}</p>
    </div>

    <!-- Status badges -->
    <div class="status-row">
      <span v-if="project.current_stage" class="s-badge" :style="stageBadgeStyle">{{ project.current_stage.name }}</span>
      <span v-if="project.status" class="s-badge" :style="statusBadgeStyle">{{ project.status.name }}</span>
    </div>

    <!-- Progress -->
    <div v-if="project.progress_percentage !== undefined" class="progress-section">
      <div class="progress-head">
        <span>Progress</span>
        <span :style="{ color: progressColor }">{{ project.progress_percentage }}%</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill" :style="{ width: `${project.progress_percentage}%`, background: progressColor }"></div>
      </div>
    </div>

    <!-- Info -->
    <div class="info-grid">
      <div v-if="project.project_type" class="info-item"><BriefcaseIcon class="ii" /><span>{{ project.project_type.name }}</span></div>
      <div v-if="project.industry" class="info-item"><BuildingIcon class="ii" /><span>{{ project.industry.name }}</span></div>
      <div v-if="project.estimated_cost" class="info-item cost"><DollarSignIcon class="ii" /><span>{{ fmtCur(project.estimated_cost, project.currency) }}</span></div>
      <div v-if="project.target_completion_date" class="info-item" :class="{ 'overdue-date': project.is_overdue }"><CalendarIcon class="ii" /><span>{{ fmtDate(project.target_completion_date) }}</span></div>
    </div>

    <!-- Footer -->
    <div class="card-footer">
      <div class="team-row" v-if="activeMembers.length > 0">
        <div v-for="(m, i) in visibleMembers" :key="m.id" class="avatar" :style="{ zIndex: visibleMembers.length - i, marginLeft: i > 0 ? '-0.45rem' : '0' }" :title="m.name">
          <img v-if="m.avatar" :src="m.avatar" :alt="m.name" />
          <span v-else>{{ initials(m.name) }}</span>
        </div>
        <div v-if="activeMembers.length > maxAv" class="avatar more">+{{ activeMembers.length - maxAv }}</div>
      </div>
      <div v-else class="no-team"><UsersIcon class="nt-icon" /> No team</div>
      <div class="footer-right">
        <span v-if="project.tasks" class="f-stat"><CheckSquareIcon class="fs-icon" /> {{ project.tasks.length }}</span>
        <span v-if="project.documents" class="f-stat"><FileTextIcon class="fs-icon" /> {{ project.documents.length }}</span>
        <span class="f-time">{{ relTime(project.updated_at) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { Project } from '@/types/project';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import { MoreHorizontal as MoreHorizontalIcon, Eye as EyeIcon, Edit as EditIcon, Archive as ArchiveIcon, Trash2 as Trash2Icon, Briefcase as BriefcaseIcon, Building as BuildingIcon, DollarSign as DollarSignIcon, Calendar as CalendarIcon, Users as UsersIcon, CheckSquare as CheckSquareIcon, FileText as FileTextIcon } from 'lucide-vue-next';

const props = defineProps<{ project: Project }>();
const emit = defineEmits<{ view: [p: Project]; edit: [p: Project]; delete: [p: Project]; archive: [p: Project] }>();
const layoutStore = useLayoutStore();
const isDarkMode = computed(() => {
  const htmlDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  return layoutStore.mode === SITE_MODE.DARK || htmlDark;
});

const showDropdown = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);
const maxAv = 4;

const toggleDropdown = () => { showDropdown.value = !showDropdown.value; };
const handleAction = (a: 'view' | 'edit' | 'archive' | 'delete') => { showDropdown.value = false; emit(a, props.project); };
const outside = (e: MouseEvent) => { if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) showDropdown.value = false; };
onMounted(() => document.addEventListener('mousedown', outside));
onUnmounted(() => document.removeEventListener('mousedown', outside));

const activeMembers = computed(() => (props.project.members || []).filter(m => !m.removed_at && m.user).map(m => ({ id: m.user!.id, name: m.user!.name, avatar: m.user!.avatar })));
const visibleMembers = computed(() => activeMembers.value.slice(0, maxAv));

const accentColor = computed(() => {
  if (props.project.is_archived) return 'linear-gradient(90deg,#94a3b8,#64748b)';
  const map: Record<string, string> = { Active:'linear-gradient(90deg,#22c55e,#16a34a)', 'On Hold':'linear-gradient(90deg,#f59e0b,#d97706)', Completed:'linear-gradient(90deg,#3b82f6,#2563eb)', Cancelled:'linear-gradient(90deg,#ef4444,#dc2626)' };
  return map[props.project.status?.name || ''] || 'linear-gradient(90deg,#6366f1,#4f46e5)';
});

const stageBadgeStyle = computed(() => {
  const map: Record<string, {bg:string;color:string}> = {
    Proposal:{bg:'#dbeafe',color:'#1d4ed8'},
    Evaluation:{bg:'#ede9fe',color:'#6d28d9'},
    Approval:{bg:'#fef3c7',color:'#b45309'},
    Implementation:{bg:'#ffedd5',color:'#c2410c'},
    Construction:{bg:'#dcfce7',color:'#15803d'},
    Operation:{bg:'#ecfccb',color:'#3f6212'},
    Completion:{bg:'#cffafe',color:'#0e7490'},
    Divestment:{bg:'#f1f5f9',color:'#475569'},
  };
  const s = map[props.project.current_stage?.name || ''] || {bg:'#f1f5f9',color:'#475569'};
  return { background: s.bg, color: s.color };
});

const statusBadgeStyle = computed(() => {
  const map: Record<string, {bg:string;color:string}> = { Active:{bg:'#dcfce7',color:'#15803d'}, 'On Hold':{bg:'#fef3c7',color:'#b45309'}, Completed:{bg:'#dbeafe',color:'#1d4ed8'}, Cancelled:{bg:'#fee2e2',color:'#b91c1c'} };
  const s = map[props.project.status?.name || ''] || {bg:'#f1f5f9',color:'#475569'};
  return { background: s.bg, color: s.color };
});

const progressColor = computed(() => {
  const p = props.project.progress_percentage || 0;
  if (p >= 75) return '#22c55e'; if (p >= 50) return '#3b82f6'; if (p >= 25) return '#f59e0b'; return '#ef4444';
});

const initials = (n: string) => n?.split(' ').map(x => x[0]).slice(0,2).join('').toUpperCase() || '?';
const fmtCur = (a: number, cur = 'PHP') => new Intl.NumberFormat('en-PH', { style:'currency', currency:cur, maximumFractionDigits:0 }).format(a);
const fmtDate = (d: string) => new Date(d).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
const relTime = (d: string) => {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000);
  if (diff < 60) return 'just now'; if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff/3600)}h ago`; if (diff < 604800) return `${Math.floor(diff/86400)}d ago`;
  return fmtDate(d);
};
</script>

<style scoped>
.project-card {
  --pc-bg: #ffffff;
  --pc-border: #e2e8f0;
  --pc-border-sub: #f1f5f9;
  --pc-text: #0f172a;
  --pc-text-2: #64748b;
  --pc-text-3: #94a3b8;
  --pc-muted: #f1f5f9;
  --pc-menu-bg: #ffffff;
  background: var(--pc-bg);
  border-radius: 0.875rem;
  border: 1px solid var(--pc-border);
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow 0.22s, transform 0.22s, border-color 0.22s;
  display: flex;
  flex-direction: column;
}
:global(.dark) .project-card {
  --pc-bg: #1e293b;
  --pc-border: #334155;
  --pc-border-sub: #253548;
  --pc-text: #f1f5f9;
  --pc-text-2: #94a3b8;
  --pc-text-3: #64748b;
  --pc-muted: #293548;
  --pc-menu-bg: #1e293b;
}
.project-card.is-dark {
  --pc-bg: #1e293b;
  --pc-border: #334155;
  --pc-border-sub: #253548;
  --pc-text: #f1f5f9;
  --pc-text-2: #94a3b8;
  --pc-text-3: #64748b;
  --pc-muted: #293548;
  --pc-menu-bg: #1e293b;
}
.project-card:hover { border-color: #a5b4fc; box-shadow: 0 8px 24px rgba(99,102,241,0.1), 0 2px 6px rgba(0,0,0,0.06); transform: translateY(-2px); }
:global(.dark) .project-card:hover { border-color: #4338ca; box-shadow: 0 8px 24px rgba(99,102,241,0.15); }
.project-card.archived { opacity: 0.68; }
.project-card.overdue { border-color: #fecaca; }
:global(.dark) .project-card.overdue { border-color: #7f1d1d; }

.accent-line { height: 4px; width: 100%; flex-shrink: 0; }

.card-header { display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 0.875rem 0.5rem; }
.card-meta { display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0; }
.project-code { font-size: 0.68rem; font-weight: 700; color: var(--pc-text-3); text-transform: uppercase; letter-spacing: 0.08em; white-space: nowrap; }
.badges { display: flex; gap: 0.3rem; flex-wrap: wrap; }
.badge { font-size: 0.62rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 999px; letter-spacing: 0.04em; text-transform: uppercase; }
.badge.svf { background: #fef3c7; color: #92400e; }
.badge.overdue { background: #fee2e2; color: #991b1b; }
.badge.archived { background: var(--pc-muted); color: var(--pc-text-3); }
:global(.dark) .badge.svf { background: #422006; color: #fcd34d; }
:global(.dark) .badge.overdue { background: #450a0a; color: #fca5a5; }

.dropdown-wrap { position: relative; flex-shrink: 0; }
.menu-btn { width: 1.875rem; height: 1.875rem; display: flex; align-items: center; justify-content: center; border: none; background: transparent; border-radius: 0.375rem; cursor: pointer; color: var(--pc-text-3); transition: all 0.15s; }
.menu-btn:hover, .menu-btn.active { background: var(--pc-muted); color: var(--pc-text-2); }
.menu-btn .icon { width: 1rem; height: 1rem; }

.dropdown { position: absolute; right: 0; top: calc(100% + 0.375rem); background: var(--pc-menu-bg); border: 1px solid var(--pc-border); border-radius: 0.625rem; box-shadow: 0 10px 25px rgba(0,0,0,0.14); min-width: 10.5rem; z-index: 50; overflow: hidden; padding: 0.25rem; }
:global(.dark) .dropdown { box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
.dd-item { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.5rem 0.625rem; background: none; border: none; border-radius: 0.375rem; text-align: left; font-size: 0.8rem; font-weight: 500; color: var(--pc-text); cursor: pointer; transition: background 0.1s; }
.dd-item:hover { background: var(--pc-muted); }
.dd-item.danger { color: #dc2626; }
.dd-item.danger:hover { background: #fef2f2; }
:global(.dark) .dd-item.danger:hover { background: #450a0a; }
.di { width: 0.825rem; height: 0.825rem; }
.dd-sep { height: 1px; background: var(--pc-border-sub); margin: 0.25rem 0; }

.card-body { padding: 0 0.875rem 0.75rem; }
.project-title { font-size: 0.9375rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.375rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; transition: color 0.15s; }
.project-card:hover .project-title { color: #2563eb; }
:global(.dark) .project-card:hover .project-title { color: #60a5fa; }
.project-desc { font-size: 0.8rem; color: var(--pc-text-2); line-height: 1.55; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.status-row { display: flex; gap: 0.375rem; padding: 0 0.875rem 0.75rem; flex-wrap: wrap; }
.s-badge { font-size: 0.68rem; font-weight: 700; padding: 0.22rem 0.55rem; border-radius: 999px; letter-spacing: 0.02em; }

.progress-section { padding: 0 0.875rem 0.75rem; }
.progress-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; font-size: 0.73rem; font-weight: 500; color: var(--pc-text-2); }
.progress-track { height: 0.35rem; background: var(--pc-muted); border-radius: 999px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.3rem 0.5rem; padding: 0.625rem 0.875rem 0.75rem; border-top: 1px solid var(--pc-border-sub); }
.info-item { display: flex; align-items: center; gap: 0.3rem; font-size: 0.73rem; color: var(--pc-text-2); overflow: hidden; }
.info-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.info-item.cost { color: #059669; }
:global(.dark) .info-item.cost { color: #34d399; }
.info-item.overdue-date { color: #dc2626; }
:global(.dark) .info-item.overdue-date { color: #f87171; }
.ii { width: 0.75rem; height: 0.75rem; flex-shrink: 0; }

.card-footer { display: flex; align-items: center; justify-content: space-between; padding: 0.625rem 0.875rem; border-top: 1px solid var(--pc-border-sub); margin-top: auto; }
.team-row { display: flex; align-items: center; }
.avatar { width: 1.625rem; height: 1.625rem; border-radius: 50%; border: 2px solid var(--pc-bg); background: #e0e7ff; color: #4338ca; font-size: 0.55rem; font-weight: 700; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
:global(.dark) .avatar { border-color: #1e293b; background: #312e81; color: #a5b4fc; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar.more { background: var(--pc-muted); color: var(--pc-text-2); font-size: 0.6rem; }
.no-team { display: flex; align-items: center; gap: 0.3rem; font-size: 0.72rem; color: var(--pc-text-3); }
.nt-icon { width: 0.8rem; height: 0.8rem; }
.footer-right { display: flex; align-items: center; gap: 0.5rem; }
.f-stat { display: flex; align-items: center; gap: 0.2rem; font-size: 0.72rem; color: var(--pc-text-3); }
.fs-icon { width: 0.75rem; height: 0.75rem; }
.f-time { font-size: 0.68rem; color: var(--pc-text-3); }

.dd-enter-active { animation: ddIn 0.14s ease; }
.dd-leave-active { animation: ddIn 0.1s ease reverse; }
@keyframes ddIn { from{opacity:0;transform:scale(0.94) translateY(-4px)} to{opacity:1;transform:scale(1) translateY(0)} }
</style>
