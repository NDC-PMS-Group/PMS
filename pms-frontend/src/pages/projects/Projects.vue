<!-- src/views/Projects.vue -->
<template>
  <div class="projects-page" :class="{ 'is-dark': isDarkMode }">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="header-text">
          <h1 class="page-title">Projects</h1>
          <p class="page-subtitle">
            <span class="stat-pill">{{ pagination?.total || 0 }} total</span>
            <span class="stat-pill active">{{ stats.active }} active</span>
            <span class="stat-pill overdue">{{ stats.overdue }} overdue</span>
          </p>
        </div>
        <div class="header-actions">
          <button class="btn-export" @click="exportProjects">
            <DownloadIcon class="btn-icon" /> Export
          </button>
          <button class="btn-create" @click="openCreateDialog">
            <PlusIcon class="btn-icon" /> New Project
          </button>
        </div>
      </div>
      <div class="stats-row">
        <div class="stat-card" v-for="stat in statCards" :key="stat.label">
          <div class="stat-icon" :class="stat.colorClass">
            <component :is="stat.icon" class="icon" />
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ stat.value }}</span>
            <span class="stat-label">{{ stat.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="search-wrapper" :class="{ focused: searchFocused }">
          <SearchIcon class="search-icon" />
          <input v-model="filters.search" type="text" placeholder="Search projects..."
            class="search-input" @focus="searchFocused = true" @blur="searchFocused = false" @keyup.enter="applyFilters" />
          <kbd class="search-kbd">⌘K</kbd>
        </div>
        <button class="btn-filter" @click="showFilters = !showFilters" :class="{ active: showFilters || activeFilterCount > 0 }">
          <FilterIcon class="btn-icon" /> Filters
          <span v-if="activeFilterCount > 0" class="filter-badge">{{ activeFilterCount }}</span>
        </button>
      </div>
      <div class="toolbar-right">
        <div class="view-toggle">
          <button class="view-btn" :class="{ active: viewMode === 'grid' }" @click="viewMode = 'grid'">
            <GridIcon class="icon" />
          </button>
          <button class="view-btn" :class="{ active: viewMode === 'list' }" @click="viewMode = 'list'">
            <ListIcon class="icon" />
          </button>
        </div>
        <select v-model="filters.per_page" class="per-page-select" @change="applyFilters">
          <option value="12">12 / page</option>
          <option value="24">24 / page</option>
          <option value="48">48 / page</option>
        </select>
      </div>
    </div>

    <!-- Filter Panel -->
    <Transition name="slide-down">
      <div v-if="showFilters" class="filters-panel">
        <div class="filters-grid">
          <div class="filter-group">
            <label class="filter-label" for="project-filter-type">Type</label>
            <select id="project-filter-type" v-model="filters.project_type_id" class="filter-select">
              <option :value="undefined">All Types</option>
              <option v-for="t in projectTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-industry">Industry</label>
            <select id="project-filter-industry" v-model="filters.industry_id" class="filter-select">
              <option :value="undefined">All Industries</option>
              <option v-for="i in industries" :key="i.id" :value="i.id">{{ i.name }}</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-sector">Sector</label>
            <select id="project-filter-sector" v-model="filters.sector_id" class="filter-select">
              <option :value="undefined">All Sectors</option>
              <option v-for="s in sectors" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-stage">Stage</label>
            <select id="project-filter-stage" v-model="filters.stage_id" class="filter-select">
              <option :value="undefined">All Stages</option>
              <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-status">Status</label>
            <select id="project-filter-status" v-model="filters.status_id" class="filter-select">
              <option :value="undefined">All Statuses</option>
              <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-svf">SVF</label>
            <select id="project-filter-svf" v-model="filters.is_svf" class="filter-select">
              <option :value="undefined">All Projects</option>
              <option :value="true">SVF Only</option>
              <option :value="false">Non-SVF</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-archive">Archive</label>
            <select id="project-filter-archive" v-model="filters.is_archived" class="filter-select">
              <option :value="false">Active Only</option>
              <option :value="true">Archived</option>
              <option :value="undefined">All</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-sort-by">Sort By</label>
            <select id="project-filter-sort-by" v-model="filters.sort_by" class="filter-select">
              <option value="created_at">Created Date</option>
              <option value="updated_at">Updated Date</option>
              <option value="title">Title</option>
              <option value="estimated_cost">Cost</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-order">Order</label>
            <select id="project-filter-order" v-model="filters.sort_order" class="filter-select">
              <option value="desc">Newest First</option>
              <option value="asc">Oldest First</option>
            </select>
          </div>
        </div>
        <div v-if="activeFilterTags.length > 0" class="filter-tags">
          <span v-for="tag in activeFilterTags" :key="tag.key" class="filter-tag">
            {{ tag.label }}: {{ tag.value }}
            <button @click="removeFilter(tag.key)" class="tag-remove">×</button>
          </span>
        </div>
        <div class="filter-actions">
          <button class="btn-ghost" @click="resetFilters"><RefreshCcwIcon class="btn-icon" /> Reset</button>
          <button class="btn-primary-sm" @click="applyFilters"><FilterIcon class="btn-icon" /> Apply</button>
        </div>
      </div>
    </Transition>

    <!-- Content -->
    <div class="content-area">
      <!-- Loading skeleton -->
      <div v-if="loading" class="loading-grid" :class="viewMode">
        <div v-for="i in 6" :key="i" class="skeleton-card">
          <div class="skel skel-h"></div>
          <div class="skel skel-l short"></div>
          <div class="skel skel-l"></div>
          <div class="skel skel-l medium"></div>
          <div class="skel skel-f"></div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="projects.length === 0" class="empty-state">
        <div class="empty-icon-wrap"><FolderOpenIcon class="empty-icon" /></div>
        <h3 class="empty-title">No projects found</h3>
        <p class="empty-desc">{{ filters.search ? `No results for "${filters.search}"` : 'Create your first project to get started.' }}</p>
        <button class="btn-create" @click="openCreateDialog"><PlusIcon class="btn-icon" /> Create Project</button>
      </div>

      <!-- Grid view -->
      <div v-else-if="viewMode === 'grid'" class="projects-grid">
        <ProjectCard v-for="project in projects" :key="project.id" :project="project"
          @view="openViewDialog" @edit="openEditDialog" @delete="confirmDelete" @archive="toggleArchive" />
      </div>

      <!-- List view -->
      <div v-else class="projects-list">
        <div class="list-header">
          <span>Project</span><span>Type / Industry</span><span>Status</span>
          <span>Progress</span><span>Cost</span><span>Updated</span><span></span>
        </div>
        <ProjectListRow v-for="project in projects" :key="project.id" :project="project"
          @view="openViewDialog" @edit="openEditDialog" @delete="confirmDelete" @archive="toggleArchive" />
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination">
        <button class="page-btn" :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)">
          <ChevronLeftIcon class="icon" />
        </button>
        <template v-for="page in visiblePages" :key="String(page)">
          <button v-if="page !== '...'" class="page-btn" :class="{ active: page === pagination.current_page }" @click="goToPage(Number(page))">{{ page }}</button>
          <span v-else class="page-ellipsis">…</span>
        </template>
        <button class="page-btn" :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)">
          <ChevronRightIcon class="icon" />
        </button>
      </div>
    </div>

    <!-- Dialogs -->
    <CreateEditProjectDialog v-model="showCreateEditDialog" :project="selectedProject" @saved="onProjectSaved" />
    <ViewProjectDialog v-model="showViewDialog" :project-id="selectedProjectId" @edit="openEditFromView" />
    <DeleteConfirmDialog v-model="showDeleteDialog" :project="projectToDelete" @confirmed="executeDelete" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, markRaw } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from 'vue3-toastify';
import { useProjectStore } from '@/store/projects';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import type { Project, ProjectFilters } from '@/types/project';
import ProjectCard from '@/components/projects/ProjectCard.vue';
import ProjectListRow from '@/components/projects/ProjectListRow.vue';
import CreateEditProjectDialog from '@/components/projects/CreateEditProjectDialog.vue';
import ViewProjectDialog from '@/components/projects/ViewProjectDialog.vue';
import DeleteConfirmDialog from '@/components/projects/DeleteConfirmDialog.vue';
import {
  Search as SearchIcon, Filter as FilterIcon, Plus as PlusIcon, Download as DownloadIcon,
  Grid as GridIcon, List as ListIcon, ChevronLeft as ChevronLeftIcon, ChevronRight as ChevronRightIcon,
  FolderOpen as FolderOpenIcon, RefreshCcw as RefreshCcwIcon,
  Briefcase, CheckCircle, Clock, AlertTriangle
} from 'lucide-vue-next';

const projectStore = useProjectStore();
const { projects, pagination, loading, projectTypes, industries, sectors, stages, statuses } = storeToRefs(projectStore);
const layoutStore = useLayoutStore();
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);

const viewMode = ref<'grid' | 'list'>('grid');
const showFilters = ref(false);
const searchFocused = ref(false);
const showCreateEditDialog = ref(false);
const showViewDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedProject = ref<Project | null>(null);
const selectedProjectId = ref<number | null>(null);
const projectToDelete = ref<Project | null>(null);

const filters = ref<ProjectFilters>({
  search: '', sort_by: 'created_at', sort_order: 'desc', per_page: 12, page: 1, is_archived: false
});

const stats = computed(() => ({
  active: projects.value.filter(p => !p.is_archived && !p.is_deleted).length,
  overdue: projects.value.filter(p => p.is_overdue).length,
}));
const statCards = computed(() => [
  { label: 'Total', value: pagination.value?.total || projects.value.length, icon: markRaw(Briefcase), colorClass: 'blue' },
  { label: 'Active', value: stats.value.active, icon: markRaw(CheckCircle), colorClass: 'green' },
  { label: 'Overdue', value: stats.value.overdue, icon: markRaw(AlertTriangle), colorClass: 'red' },
  { label: 'SVF', value: projects.value.filter(p => p.is_svf).length, icon: markRaw(Clock), colorClass: 'amber' },
]);
const activeFilterCount = computed(() => {
  let n = 0;
  if (filters.value.search) n++;
  if (filters.value.project_type_id) n++;
  if (filters.value.industry_id) n++;
  if (filters.value.sector_id) n++;
  if (filters.value.stage_id) n++;
  if (filters.value.status_id) n++;
  if (filters.value.is_svf !== undefined) n++;
  if (filters.value.is_archived) n++;
  return n;
});
const activeFilterTags = computed(() => {
  const tags: { key: string; label: string; value: string }[] = [];
  if (filters.value.search) tags.push({ key: 'search', label: 'Search', value: filters.value.search });
  if (filters.value.project_type_id) { const t = projectTypes.value.find(x => x.id === filters.value.project_type_id); if (t) tags.push({ key: 'project_type_id', label: 'Type', value: t.name }); }
  if (filters.value.industry_id) { const i = industries.value.find(x => x.id === filters.value.industry_id); if (i) tags.push({ key: 'industry_id', label: 'Industry', value: i.name }); }
  if (filters.value.sector_id) { const s = sectors.value.find(x => x.id === filters.value.sector_id); if (s) tags.push({ key: 'sector_id', label: 'Sector', value: s.name }); }
  if (filters.value.stage_id) { const s = stages.value.find(x => x.id === filters.value.stage_id); if (s) tags.push({ key: 'stage_id', label: 'Stage', value: s.name }); }
  if (filters.value.status_id) { const s = statuses.value.find(x => x.id === filters.value.status_id); if (s) tags.push({ key: 'status_id', label: 'Status', value: s.name }); }
  return tags;
});
const visiblePages = computed(() => {
  if (!pagination.value) return [];
  const { current_page, last_page } = pagination.value;
  const pages: (number | string)[] = [];
  if (last_page <= 7) { for (let i = 1; i <= last_page; i++) pages.push(i); }
  else {
    pages.push(1);
    if (current_page > 3) pages.push('...');
    for (let i = Math.max(2, current_page - 1); i <= Math.min(last_page - 1, current_page + 1); i++) pages.push(i);
    if (current_page < last_page - 2) pages.push('...');
    pages.push(last_page);
  }
  return pages;
});

const applyFilters = async () => {
  filters.value.page = 1;
  try {
    await projectStore.fetchProjects(filters.value);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to load projects');
  }
};
const resetFilters = async () => {
  filters.value = { search: '', sort_by: 'created_at', sort_order: 'desc', per_page: 12, page: 1, is_archived: false };
  await applyFilters();
};
const removeFilter = (key: string) => {
  (filters.value as any)[key] = undefined;
  if (key === 'is_archived') filters.value.is_archived = false;
  applyFilters();
};
const goToPage = async (page: number) => {
  filters.value.page = page;
  try {
    await projectStore.fetchProjects(filters.value);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to load projects');
  }
};
const openCreateDialog = () => { selectedProject.value = null; showCreateEditDialog.value = true; };
const openEditDialog = (p: Project) => { selectedProject.value = p; showCreateEditDialog.value = true; };
const openViewDialog = (p: Project) => { selectedProjectId.value = p.id; showViewDialog.value = true; };
const openEditFromView = (p: Project) => { showViewDialog.value = false; openEditDialog(p); };
const confirmDelete = (p: Project) => { projectToDelete.value = p; showDeleteDialog.value = true; };
const executeDelete = async () => {
  if (!projectToDelete.value) return;
  try {
    await projectStore.deleteProject(projectToDelete.value.id);
    showDeleteDialog.value = false;
    projectToDelete.value = null;
    await applyFilters();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to delete project');
  }
};
const toggleArchive = async (p: Project) => {
  try {
    await projectStore.archiveProject(p.id);
    await applyFilters();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to archive project');
  }
};
const onProjectSaved = async () => {
  await applyFilters();
};
const exportProjects = () => console.log('Export...');

onMounted(async () => {
  try {
    await Promise.all([projectStore.loadAllLookupData(), projectStore.fetchProjects(filters.value)]);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to initialize projects');
  }
});
</script>

<style scoped>
/* ─── CSS Custom Properties (light + dark) ─── */
.projects-page {
  --c-bg: #f8fafc;
  --c-card: #ffffff;
  --c-subtle: #f8fafc;
  --c-muted: #f1f5f9;
  --c-border: #e2e8f0;
  --c-border-sub: #f1f5f9;
  --c-text: #0f172a;
  --c-text-2: #475569;
  --c-text-3: #94a3b8;
  --c-text-in: #1e293b;
  --c-accent: #2563eb;
  --c-accent-bg: #eff6ff;
  --c-toolbar: #ffffff;
  --c-skel-a: #f1f5f9;
  --c-skel-b: #e2e8f0;
  background: var(--c-bg);
  font-family: 'Inter', -apple-system, sans-serif;
  min-height: 100vh;
}

/* Dark mode — Tailwind puts .dark on <html> */
:global(.dark) .projects-page {
  --c-bg: #0f172a;
  --c-card: #1e293b;
  --c-subtle: #1e293b;
  --c-muted: #293548;
  --c-border: #334155;
  --c-border-sub: #253348;
  --c-text: #f1f5f9;
  --c-text-2: #94a3b8;
  --c-text-3: #64748b;
  --c-text-in: #e2e8f0;
  --c-accent: #3b82f6;
  --c-accent-bg: #1e3a5f;
  --c-toolbar: #1e293b;
  --c-skel-a: #293548;
  --c-skel-b: #334155;
}

.projects-page.is-dark {
  --c-bg: #0f172a;
  --c-card: #1e293b;
  --c-subtle: #1e293b;
  --c-muted: #293548;
  --c-border: #334155;
  --c-border-sub: #253348;
  --c-text: #f1f5f9;
  --c-text-2: #94a3b8;
  --c-text-3: #64748b;
  --c-text-in: #e2e8f0;
  --c-accent: #3b82f6;
  --c-accent-bg: #1e3a5f;
  --c-toolbar: #1e293b;
  --c-skel-a: #293548;
  --c-skel-b: #334155;
}

/* ─── Header ─── */
.page-header {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f4c81 100%);
  padding: 2rem 2rem 0;
  color: white;
  position: relative;
  overflow: hidden;
}
.page-header::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
.header-content { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; position: relative; z-index: 1; }
.page-title { font-size: 2rem; font-weight: 800; letter-spacing: -0.025em; margin: 0 0 0.5rem; }
.page-subtitle { display: flex; align-items: center; gap: 0.5rem; margin: 0; flex-wrap: wrap; }
.stat-pill { font-size: 0.73rem; font-weight: 600; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); padding: 0.2rem 0.6rem; border-radius: 999px; color: rgba(255,255,255,0.85); }
.stat-pill.active { background: rgba(34,197,94,0.2); border-color: rgba(34,197,94,0.4); color: #86efac; }
.stat-pill.overdue { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); color: #fca5a5; }
.header-actions { display: flex; gap: 0.75rem; flex-shrink: 0; }
.btn-export { display: flex; align-items: center; gap: 0.5rem; padding: 0.575rem 1.1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-export:hover { background: rgba(255,255,255,0.18); }
.btn-create { display: flex; align-items: center; gap: 0.5rem; padding: 0.575rem 1.1rem; background: #2563eb; border: none; color: white; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-create:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.35); }
.btn-icon { width: 1rem; height: 1rem; flex-shrink: 0; }

.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid rgba(255,255,255,0.1); position: relative; z-index: 1; }
.stat-card { display: flex; align-items: center; gap: 0.875rem; padding: 1.125rem 1.5rem; border-right: 1px solid rgba(255,255,255,0.08); transition: background 0.15s; }
.stat-card:last-child { border-right: none; }
.stat-card:hover { background: rgba(255,255,255,0.05); }
.stat-icon { width: 2.375rem; height: 2.375rem; border-radius: 0.625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon .icon { width: 1.125rem; height: 1.125rem; }
.stat-icon.blue { background: rgba(59,130,246,0.2); color: #93c5fd; }
.stat-icon.green { background: rgba(34,197,94,0.2); color: #86efac; }
.stat-icon.red { background: rgba(239,68,68,0.2); color: #fca5a5; }
.stat-icon.amber { background: rgba(245,158,11,0.2); color: #fcd34d; }
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.375rem; font-weight: 700; color: white; line-height: 1; }
.stat-label { font-size: 0.68rem; color: rgba(255,255,255,0.5); margin-top: 0.25rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }

/* ─── Toolbar ─── */
.toolbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.875rem 2rem;
  background: var(--c-toolbar);
  border-bottom: 1px solid var(--c-border);
  gap: 1rem; flex-wrap: wrap;
  /* Intentionally NOT sticky to prevent footer overlap */
}
.toolbar-left { display: flex; align-items: center; gap: 0.625rem; flex: 1; min-width: 0; }
.toolbar-right { display: flex; align-items: center; gap: 0.625rem; flex-shrink: 0; }

.search-wrapper { display: flex; align-items: center; gap: 0.5rem; background: var(--c-muted); border: 1.5px solid transparent; border-radius: 0.5rem; padding: 0.5rem 0.75rem; transition: all 0.2s; min-width: 240px; max-width: 360px; }
.search-wrapper.focused { border-color: var(--c-accent); background: var(--c-card); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.search-icon { width: 0.9rem; height: 0.9rem; color: var(--c-text-3); flex-shrink: 0; }
.search-input { border: none; background: transparent; outline: none; font-size: 0.875rem; color: var(--c-text-in); flex: 1; min-width: 0; }
.search-input::placeholder { color: var(--c-text-3); }
.search-kbd { font-size: 0.63rem; padding: 0.1rem 0.35rem; background: var(--c-card); border: 1px solid var(--c-border); border-radius: 0.25rem; color: var(--c-text-3); font-family: monospace; white-space: nowrap; flex-shrink: 0; }

.btn-filter { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.875rem; background: var(--c-muted); border: 1.5px solid var(--c-border); border-radius: 0.5rem; font-size: 0.8rem; font-weight: 500; color: var(--c-text-2); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.btn-filter:hover, .btn-filter.active { background: var(--c-accent-bg); border-color: var(--c-accent); color: var(--c-accent); }
.filter-badge { background: var(--c-accent); color: white; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 999px; }

.view-toggle { display: flex; background: var(--c-muted); border-radius: 0.5rem; padding: 0.2rem; gap: 0.2rem; }
.view-btn { padding: 0.35rem; border: none; background: transparent; border-radius: 0.3rem; cursor: pointer; color: var(--c-text-3); transition: all 0.15s; display: flex; }
.view-btn .icon { width: 1rem; height: 1rem; }
.view-btn.active { background: var(--c-card); color: var(--c-accent); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

.per-page-select { padding: 0.5rem 2rem 0.5rem 0.75rem; background: var(--c-muted); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.625rem center; border: 1.5px solid var(--c-border); border-radius: 0.5rem; font-size: 0.8rem; color: var(--c-text-2); cursor: pointer; appearance: none; font-weight: 500; }

/* ─── Filters Panel ─── */
.filters-panel { background: var(--c-toolbar); border-bottom: 1px solid var(--c-border); padding: 1.25rem 2rem; }
.filters-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 0.875rem; margin-bottom: 1rem; }
.filter-group { display: flex; flex-direction: column; gap: 0.3rem; }
.filter-label { font-size: 0.68rem; font-weight: 700; color: var(--c-text-3); text-transform: uppercase; letter-spacing: 0.07em; }
.filter-select { padding: 0.5rem 1.75rem 0.5rem 0.625rem; background: var(--c-muted); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.5rem center; border: 1px solid var(--c-border); border-radius: 0.375rem; font-size: 0.8rem; color: var(--c-text); appearance: none; cursor: pointer; }
.filter-select:focus { outline: none; border-color: var(--c-accent); box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }

.filter-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.875rem; }
.filter-tag { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.2rem 0.6rem; background: var(--c-accent-bg); color: var(--c-accent); border-radius: 999px; font-size: 0.78rem; font-weight: 500; }
.tag-remove { background: none; border: none; color: currentColor; cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0; opacity: 0.6; }
.tag-remove:hover { opacity: 1; }
.filter-actions { display: flex; justify-content: flex-end; gap: 0.625rem; }
.btn-ghost { padding: 0.5rem 1rem; background: transparent; border: 1px solid var(--c-border); border-radius: 0.5rem; font-size: 0.875rem; color: var(--c-text-2); cursor: pointer; display: flex; align-items: center; gap: 0.375rem; }
.btn-ghost:hover { background: var(--c-muted); }
.btn-primary-sm { padding: 0.5rem 1rem; background: var(--c-accent); border: none; border-radius: 0.5rem; font-size: 0.875rem; color: white; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.375rem; }
.btn-primary-sm:hover { opacity: 0.9; }

/* ─── Content Area ─── */
.content-area { padding: 1.5rem 2rem 3rem; }

/* Skeleton */
.loading-grid { display: grid; gap: 1.25rem; }
.loading-grid.grid { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
.loading-grid.list { grid-template-columns: 1fr; }
@keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
.skeleton-card { background: var(--c-card); border-radius: 0.75rem; padding: 1.25rem; border: 1px solid var(--c-border); }
.skel { background: linear-gradient(90deg, var(--c-skel-a) 25%, var(--c-skel-b) 50%, var(--c-skel-a) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 0.375rem; }
.skel-h { height: 1.5rem; width: 60%; margin-bottom: 1rem; }
.skel-l { height: 0.75rem; margin-bottom: 0.5rem; }
.skel-l.short { width: 30%; } .skel-l.medium { width: 75%; }
.skel-f { height: 2.5rem; margin-top: 1rem; }

/* Empty */
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 5rem 2rem; text-align: center; }
.empty-icon-wrap { width: 5rem; height: 5rem; background: var(--c-muted); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; }
.empty-icon { width: 2.5rem; height: 2.5rem; color: var(--c-text-3); }
.empty-title { font-size: 1.25rem; font-weight: 700; color: var(--c-text); margin: 0 0 0.5rem; }
.empty-desc { font-size: 0.9375rem; color: var(--c-text-2); margin: 0 0 1.5rem; }

/* Grid */
.projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem; }

/* List */
.projects-list { background: var(--c-card); border-radius: 0.75rem; border: 1px solid var(--c-border); overflow: hidden; }
.list-header { display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1fr 80px; gap: 1rem; padding: 0.75rem 1.25rem; background: var(--c-subtle); border-bottom: 1px solid var(--c-border); font-size: 0.68rem; font-weight: 700; color: var(--c-text-3); text-transform: uppercase; letter-spacing: 0.05em; }

/* Pagination */
.pagination { display: flex; align-items: center; justify-content: center; gap: 0.375rem; margin-top: 2rem; }
.page-btn { min-width: 2.25rem; height: 2.25rem; padding: 0 0.5rem; display: flex; align-items: center; justify-content: center; border: 1px solid var(--c-border); background: var(--c-card); border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--c-text-2); cursor: pointer; transition: all 0.15s; }
.page-btn:hover:not(:disabled) { background: var(--c-accent-bg); border-color: var(--c-accent); color: var(--c-accent); }
.page-btn.active { background: var(--c-accent); border-color: var(--c-accent); color: white; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn .icon { width: 1rem; height: 1rem; }
.page-ellipsis { font-size: 0.875rem; color: var(--c-text-3); padding: 0 0.25rem; }

/* Transition */
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.22s ease; overflow: hidden; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-6px); }

@media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
  .page-header { padding: 1.25rem 1rem 0; }
  .header-content { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .toolbar { padding: 0.75rem 1rem; }
  .search-wrapper { min-width: unset; flex: 1; }
  .content-area { padding: 1rem 1rem 3rem; }
  .stats-row { grid-template-columns: repeat(2, 1fr); }
  .projects-grid { grid-template-columns: 1fr; }
  .list-header { display: none; }
}
</style>
