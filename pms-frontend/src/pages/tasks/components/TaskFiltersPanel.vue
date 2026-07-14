<template>
  <section class="filters" :class="{ open }">
    <div class="filter-bar">
      <label class="search-field">
        <span class="sr-only">Search tasks</span>
        <Search aria-hidden="true" />
        <input :value="filters.search || ''" type="search" placeholder="Search tasks or projects" @input="patch('search', ($event.target as HTMLInputElement).value)" @keyup.enter="$emit('apply')" />
      </label>
      <button type="button" class="filter-toggle" :aria-expanded="open" aria-controls="task-filters" @click="$emit('toggle')">
        <SlidersHorizontal aria-hidden="true" /> Filters <span v-if="activeCount">{{ activeCount }}</span>
      </button>
      <button type="button" class="icon-button" title="Refresh tasks" aria-label="Refresh tasks" @click="$emit('apply')"><RefreshCw aria-hidden="true" /></button>
    </div>
    <div v-if="open" id="task-filters" class="filter-grid">
      <label v-if="!projectLocked">Project<select :value="filters.project_id || ''" @change="patchNumber('project_id', $event)"><option value="">All projects</option><option v-for="item in facets.projects" :key="item.id" :value="item.id">{{ item.label }} ({{ item.count }})</option></select></label>
      <label>Status<select :value="filters.status || ''" @change="patch('status', ($event.target as HTMLSelectElement).value || undefined)"><option value="">Any status</option><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
      <label>Priority<select :value="filters.priority || ''" @change="patch('priority', ($event.target as HTMLSelectElement).value || undefined)"><option value="">Any priority</option><option v-for="item in facets.priorities" :key="item.value" :value="item.value">{{ formatLabel(item.value) }} ({{ item.count }})</option></select></label>
      <label>Workstream<select :value="filters.workstream || ''" @change="patch('workstream', ($event.target as HTMLSelectElement).value || undefined)"><option value="">All workstreams</option><option v-for="item in facets.soi_sections" :key="item.value" :value="item.value">{{ item.label || formatLabel(item.value) }} ({{ item.count }})</option></select></label>
      <label>Assignee<select :value="filters.assigned_to || ''" @change="patchNumber('assigned_to', $event)"><option value="">Any assignee</option><option v-for="item in facets.assignees" :key="item.id" :value="item.id">{{ item.label }} ({{ item.count }})</option></select></label>
      <label>Sort<select :value="filters.sort_by || 'smart_priority'" @change="patch('sort_by', ($event.target as HTMLSelectElement).value)"><option value="smart_priority">Smart priority</option><option value="due_date">Due date</option><option value="updated_at">Recently updated</option><option value="title">Title</option></select></label>
      <label class="check"><input :checked="filters.overdue || false" type="checkbox" @change="patch('overdue', ($event.target as HTMLInputElement).checked || undefined)" /> Overdue only</label>
      <label class="check"><input :checked="filters.urgent || false" type="checkbox" @change="patch('urgent', ($event.target as HTMLInputElement).checked || undefined)" /> Urgent only</label>
      <button type="button" class="clear" @click="$emit('clear')"><RotateCcw aria-hidden="true" /> Clear filters</button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { RefreshCw, RotateCcw, Search, SlidersHorizontal } from "lucide-vue-next";
import type { TaskFacets, TaskFilters } from "@/types/task";
const props = defineProps<{ filters: TaskFilters; facets: TaskFacets; open: boolean; projectLocked: boolean }>();
const emit = defineEmits<{ patch: [patch: Partial<TaskFilters>]; apply: []; clear: []; toggle: [] }>();
const statuses = [{ value: "pending", label: "Pending" }, { value: "in_progress", label: "In progress" }, { value: "completed", label: "Completed" }, { value: "cancelled", label: "Cancelled" }];
const activeCount = computed(() => [props.filters.project_id, props.filters.status, props.filters.priority, props.filters.workstream, props.filters.assigned_to, props.filters.overdue, props.filters.urgent].filter(Boolean).length);
const patch = (key: keyof TaskFilters, value: any) => emit("patch", { [key]: value });
const patchNumber = (key: keyof TaskFilters, event: Event) => { const value = Number((event.target as HTMLSelectElement).value); patch(key, value || undefined); };
const formatLabel = (value?: string) => (value || "Unassigned").replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
</script>

<style scoped>
.filters { border:1px solid #e2e8f0; background:#fff; }.filter-bar { display:flex; gap:8px; padding:9px; }.search-field { position:relative; flex:1; }.search-field svg { position:absolute; width:16px; left:10px; top:10px; color:#94a3b8; }.search-field input,.filter-grid select { width:100%; height:36px; border:1px solid #cbd5e1; background:#fff; color:#0f172a; padding:0 10px; font-size:13px; }.search-field input { padding-left:34px; }.filter-toggle,.icon-button,.clear { min-height:36px; display:inline-flex; align-items:center; justify-content:center; gap:7px; border:1px solid #cbd5e1; padding:0 11px; color:#334155; font-size:13px; font-weight:700; background:#f8fafc; }.filter-toggle svg,.icon-button svg,.clear svg { width:16px; }.filter-toggle span { min-width:18px; padding:1px 5px; background:#2563eb; color:#fff; border-radius:9px; font-size:10px; }.icon-button { width:36px; padding:0; }.filter-grid { display:grid; grid-template-columns:repeat(6,minmax(130px,1fr)); gap:10px; padding:12px; border-top:1px solid #e2e8f0; }.filter-grid label { display:grid; gap:5px; color:#475569; font-size:11px; font-weight:800; }.filter-grid .check { display:flex; align-items:center; gap:8px; align-self:end; height:36px; }.check input { width:16px; height:16px; }.clear { align-self:end; }
:global(.dark) .filters { border-color:#334155; background:#0f172a; }:global(.dark) .filter-grid { border-color:#334155; }:global(.dark) .search-field input,:global(.dark) .filter-grid select { border-color:#475569; background:#1e293b; color:#f8fafc; }:global(.dark) .filter-toggle,:global(.dark) .icon-button,:global(.dark) .clear { border-color:#475569; background:#1e293b; color:#e2e8f0; }:global(.dark) .filter-grid label { color:#cbd5e1; }
@media(max-width:1100px){.filter-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}@media(max-width:640px){.filter-grid{grid-template-columns:1fr 1fr}.filter-toggle{font-size:0}.filter-toggle span{font-size:10px}.search-field input{min-width:0}.clear{width:100%}}
</style>
