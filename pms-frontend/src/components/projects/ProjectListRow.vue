<!-- src/components/projects/ProjectListRow.vue -->
<template>
  <div class="list-row" :class="{ 'is-dark': isDarkMode, archived: project.is_archived, overdue: project.is_overdue }" @click="$emit('view', project)">
    <div class="col col-title">
      <div class="status-dot" :style="{ background: dotColor }"></div>
      <div class="p-info">
        <span class="p-code">{{ project.project_code }}</span>
        <span class="p-name">{{ project.title }}</span>
        <div class="badges">
          <span v-if="project.is_svf" class="mb svf">SVF</span>
          <span v-if="project.is_overdue" class="mb overdue">Overdue</span>
          <span v-if="project.is_archived" class="mb archived">Archived</span>
        </div>
      </div>
    </div>
    <div class="col col-type">
      <span v-if="project.project_type" class="meta-t">{{ project.project_type.name }}</span>
      <span v-if="project.industry" class="meta-s">{{ project.industry.name }}</span>
    </div>
    <div class="col col-status">
      <span class="s-pill stage" :style="stagePillStyle">{{ project.current_stage?.name || '—' }}</span>
      <span class="s-pill status" :style="statusPillStyle">{{ project.status?.name || '—' }}</span>
    </div>
    <div class="col col-progress">
      <div class="inline-p">
        <div class="p-track"><div class="p-fill" :style="{ width: `${project.progress_percentage || 0}%`, background: progressColor }"></div></div>
        <span class="p-pct" :style="{ color: progressColor }">{{ project.progress_percentage || 0 }}%</span>
      </div>
    </div>
    <div class="col col-cost">
      <span v-if="project.estimated_cost" class="cost-v">{{ fmtCur(project.estimated_cost, project.currency) }}</span>
      <span v-else class="meta-s">—</span>
    </div>
    <div class="col col-date"><span class="date-t">{{ relTime(project.updated_at) }}</span></div>
    <div class="col col-actions" @click.stop>
      <div class="row-actions">
        <button class="a-btn" @click.stop="$emit('view', project)" title="View"><EyeIcon class="icon" /></button>
        <button class="a-btn" @click.stop="$emit('edit', project)" title="Edit"><EditIcon class="icon" /></button>
        <button class="a-btn danger" @click.stop="$emit('delete', project)" title="Delete"><Trash2Icon class="icon" /></button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Project } from '@/types/project';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import { Eye as EyeIcon, Edit as EditIcon, Trash2 as Trash2Icon } from 'lucide-vue-next';

const props = defineProps<{ project: Project }>();
defineEmits<{ view:[p:Project]; edit:[p:Project]; delete:[p:Project]; archive:[p:Project] }>();
const layoutStore = useLayoutStore();
const isDarkMode = computed(() => {
  const htmlDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  return layoutStore.mode === SITE_MODE.DARK || htmlDark;
});

const dotColor = computed(() => {
  if (props.project.is_archived) return '#94a3b8';
  if (props.project.is_overdue) return '#ef4444';
  const m: Record<string,string> = { Active:'#22c55e','On Hold':'#f59e0b', Completed:'#3b82f6', Cancelled:'#ef4444' };
  return m[props.project.status?.name || ''] || '#6366f1';
});

const stagePillStyle = computed(() => {
  const m: Record<string,{bg:string;color:string}> = {
    Proposal:{bg:'#dbeafe',color:'#1d4ed8'},
    Evaluation:{bg:'#ede9fe',color:'#6d28d9'},
    Approval:{bg:'#fef3c7',color:'#b45309'},
    Implementation:{bg:'#ffedd5',color:'#c2410c'},
    Construction:{bg:'#dcfce7',color:'#15803d'},
    Operation:{bg:'#ecfccb',color:'#3f6212'},
    Completion:{bg:'#cffafe',color:'#0e7490'},
    Divestment:{bg:'#f1f5f9',color:'#475569'},
  };
  const s = m[props.project.current_stage?.name || ''] || {bg:'#f1f5f9',color:'#475569'};
  return { background: s.bg, color: s.color };
});
const statusPillStyle = computed(() => {
  const m: Record<string,{bg:string;color:string}> = { Active:{bg:'#dcfce7',color:'#15803d'},'On Hold':{bg:'#fef3c7',color:'#b45309'}, Completed:{bg:'#dbeafe',color:'#1d4ed8'}, Cancelled:{bg:'#fee2e2',color:'#b91c1c'} };
  const s = m[props.project.status?.name || ''] || {bg:'#f1f5f9',color:'#475569'};
  return { background: s.bg, color: s.color };
});
const progressColor = computed(() => {
  const p = props.project.progress_percentage || 0;
  if (p >= 75) return '#22c55e'; if (p >= 50) return '#3b82f6'; if (p >= 25) return '#f59e0b'; return '#ef4444';
});
const fmtCur = (a: number, cur = 'PHP') => new Intl.NumberFormat('en-PH',{style:'currency',currency:cur,maximumFractionDigits:0}).format(a);
const fmtDate = (d: string) => new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
const relTime = (d: string) => {
  const diff = Math.floor((Date.now()-new Date(d).getTime())/1000);
  if (diff<60) return 'just now'; if (diff<3600) return `${Math.floor(diff/60)}m ago`;
  if (diff<86400) return `${Math.floor(diff/3600)}h ago`; if (diff<604800) return `${Math.floor(diff/86400)}d ago`;
  return fmtDate(d);
};
</script>

<style scoped>
.list-row {
  --lr-bg: #ffffff;
  --lr-bg-h: #f8fafc;
  --lr-border: #f1f5f9;
  --lr-text: #0f172a;
  --lr-text-2: #334155;
  --lr-text-3: #94a3b8;
  display: grid;
  grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1fr 80px;
  gap: 1rem; align-items: center;
  padding: 0.875rem 1.25rem;
  border-bottom: 1px solid var(--lr-border);
  cursor: pointer; transition: background 0.15s;
  background: var(--lr-bg);
}
:global(.dark) .list-row {
  --lr-bg: #1e293b;
  --lr-bg-h: #253548;
  --lr-border: #253548;
  --lr-text: #f1f5f9;
  --lr-text-2: #cbd5e1;
  --lr-text-3: #64748b;
}
.list-row.is-dark {
  --lr-bg: #1e293b;
  --lr-bg-h: #253548;
  --lr-border: #253548;
  --lr-text: #f1f5f9;
  --lr-text-2: #cbd5e1;
  --lr-text-3: #64748b;
}
.list-row:last-child { border-bottom: none; }
.list-row:hover { background: var(--lr-bg-h); }
.list-row.archived { opacity: 0.65; }
.list-row.overdue { background: #fff8f8; }
:global(.dark) .list-row.overdue { background: #1c1212; }

.col { overflow: hidden; }
.col-title { display: flex; align-items: flex-start; gap: 0.625rem; min-width: 0; }
.status-dot { width: 0.5rem; height: 0.5rem; border-radius: 50%; flex-shrink: 0; margin-top: 0.4rem; }
.p-info { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.p-code { font-size: 0.68rem; font-weight: 700; color: var(--lr-text-3); letter-spacing: 0.06em; text-transform: uppercase; }
.p-name { font-size: 0.875rem; font-weight: 600; color: var(--lr-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.15s; }
.list-row:hover .p-name { color: #2563eb; }
:global(.dark) .list-row:hover .p-name { color: #60a5fa; }
.badges { display: flex; gap: 0.25rem; flex-wrap: wrap; }
.mb { font-size: 0.58rem; font-weight: 700; padding: 0.05rem 0.3rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em; }
.mb.svf { background: #fef3c7; color: #92400e; }
.mb.overdue { background: #fee2e2; color: #991b1b; }
.mb.archived { background: #f1f5f9; color: #64748b; }
:global(.dark) .mb.svf { background: #422006; color: #fcd34d; }
:global(.dark) .mb.overdue { background: #450a0a; color: #fca5a5; }
:global(.dark) .mb.archived { background: #293548; color: #94a3b8; }

.col-type { display: flex; flex-direction: column; gap: 0.1rem; }
.meta-t { font-size: 0.8rem; font-weight: 500; color: var(--lr-text-2); }
.meta-s { font-size: 0.73rem; color: var(--lr-text-3); }

.col-status { display: flex; flex-direction: column; gap: 0.25rem; }
.s-pill { font-size: 0.68rem; font-weight: 700; padding: 0.18rem 0.45rem; border-radius: 999px; max-width: fit-content; }

.col-progress { display: flex; align-items: center; }
.inline-p { display: flex; align-items: center; gap: 0.4rem; width: 100%; }
.p-track { flex: 1; height: 0.3rem; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
:global(.dark) .p-track { background: #293548; }
.p-fill { height: 100%; border-radius: 999px; transition: width 0.4s ease; }
.p-pct { font-size: 0.72rem; font-weight: 700; white-space: nowrap; min-width: 2.25rem; text-align: right; }

.cost-v { font-size: 0.8rem; font-weight: 600; color: #059669; }
:global(.dark) .cost-v { color: #34d399; }
.date-t { font-size: 0.73rem; color: var(--lr-text-3); }

.row-actions { display: flex; gap: 0.25rem; justify-content: flex-end; }
.a-btn { width: 1.75rem; height: 1.75rem; display: flex; align-items: center; justify-content: center; border: none; background: transparent; border-radius: 0.375rem; cursor: pointer; color: var(--lr-text-3); transition: all 0.15s; }
.a-btn:hover { background: #f1f5f9; color: #2563eb; }
:global(.dark) .a-btn:hover { background: #293548; color: #60a5fa; }
.a-btn.danger:hover { background: #fef2f2; color: #dc2626; }
:global(.dark) .a-btn.danger:hover { background: #450a0a; color: #f87171; }
.a-btn .icon { width: 0.8rem; height: 0.8rem; }
</style>
