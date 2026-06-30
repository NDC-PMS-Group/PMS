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
          <button class="view-btn" :class="{ active: viewMode === 'grid' }" @click="viewMode = 'grid'" title="Grid View">
            <GridIcon class="icon" />
          </button>
          <button class="view-btn" :class="{ active: viewMode === 'list' }" @click="viewMode = 'list'" title="List View">
            <ListIcon class="icon" />
          </button>
          <button class="view-btn" :class="{ active: viewMode === 'gantt' }" @click="viewMode = 'gantt'" title="Gantt Chart">
            <ActivityIcon class="icon" />
          </button>
          <button class="view-btn" :class="{ active: viewMode === 'calendar' }" @click="viewMode = 'calendar'" title="Calendar View">
            <CalendarDaysIcon class="icon" />
          </button>
        </div>
        <select v-model="filters.per_page" class="per-page-select" @change="applyFilters">
          <option value="12">12 / page</option>
          <option value="24">24 / page</option>
          <option value="48">48 / page</option>
        </select>
      </div>
    </div>

    <div class="report-preset-strip">
      <div class="preset-copy">
        <span>Report Views</span>
        <p>Filter and export project lists by lifecycle, status, and category.</p>
      </div>
      <div class="preset-control">
        <label class="preset-label" for="project-report-preset">Project List</label>
        <div class="report-select-wrap">
          <FilterIcon class="preset-select-icon" />
          <select id="project-report-preset" v-model="filters.report_preset" class="report-preset-select" @change="applyFilters">
            <option v-for="preset in reportPresetOptions" :key="preset.value" :value="preset.value">{{ preset.label }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Filter Panel -->
    <Transition name="slide-down">
      <div v-if="showFilters" class="filters-panel">
        <div class="filters-grid">
          <div class="filter-group">
            <label class="filter-label" for="project-filter-report">Report View</label>
            <select id="project-filter-report" v-model="filters.report_preset" class="filter-select">
              <option v-for="preset in reportPresetOptions" :key="preset.value" :value="preset.value">{{ preset.label }}</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-track">SOI Track</label>
            <select id="project-filter-track" v-model="filters.process_track" class="filter-select">
              <option :value="undefined">All Tracks</option>
              <option v-for="track in processTrackOptions" :key="track.value" :value="track.value">{{ track.label }}</option>
            </select>
          </div>
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
            <label class="filter-label" for="project-filter-overdue">Overdue</label>
            <select id="project-filter-overdue" v-model="filters.is_overdue" class="filter-select">
              <option :value="undefined">Any</option>
              <option :value="true">Overdue only</option>
              <option :value="false">Not overdue</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-reportable">GCG Reportable</label>
            <select id="project-filter-reportable" v-model="filters.reportable_to_gcg" class="filter-select">
              <option :value="undefined">Any</option>
              <option :value="true">Reportable</option>
              <option :value="false">Not reportable</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-sort-by">Sort By</label>
            <select id="project-filter-sort-by" v-model="filters.sort_by" class="filter-select">
              <option value="created_at">Created Date</option>
              <option value="updated_at">Updated Date</option>
              <option value="title">Title</option>
              <option value="project_code">Project Code</option>
              <option value="estimated_cost">Cost</option>
              <option value="actual_cost">Actual Cost</option>
              <option value="target_completion_date">Target Completion</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-order">Order</label>
            <select id="project-filter-order" v-model="filters.sort_order" class="filter-select">
              <option value="desc">Newest First</option>
              <option value="asc">Oldest First</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-date-field">Date Field</label>
            <select id="project-filter-date-field" v-model="filters.date_field" class="filter-select">
              <option v-for="field in dateFieldOptions" :key="field.value" :value="field.value">{{ field.label }}</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-date-from">From</label>
            <input id="project-filter-date-from" v-model="filters.date_from" type="date" class="filter-input" />
          </div>
          <div class="filter-group">
            <label class="filter-label" for="project-filter-date-to">To</label>
            <input id="project-filter-date-to" v-model="filters.date_to" type="date" class="filter-input" />
          </div>
          <div class="filter-group range-group">
            <label class="filter-label">Estimated Cost</label>
            <div class="range-inputs">
              <input v-model="filters.estimated_cost_min" type="number" min="0" class="filter-input" placeholder="Min" />
              <input v-model="filters.estimated_cost_max" type="number" min="0" class="filter-input" placeholder="Max" />
            </div>
          </div>
          <div class="filter-group range-group">
            <label class="filter-label">Actual Cost</label>
            <div class="range-inputs">
              <input v-model="filters.actual_cost_min" type="number" min="0" class="filter-input" placeholder="Min" />
              <input v-model="filters.actual_cost_max" type="number" min="0" class="filter-input" placeholder="Max" />
            </div>
          </div>
          <div class="filter-group range-group">
            <label class="filter-label">Progress %</label>
            <div class="range-inputs">
              <input v-model="filters.progress_min" type="number" min="0" max="100" class="filter-input" placeholder="Min" />
              <input v-model="filters.progress_max" type="number" min="0" max="100" class="filter-input" placeholder="Max" />
            </div>
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
        <ProjectCard v-for="project in projects" :key="project.id" :project="project" :openHandler="openViewDialog"
          @view="openViewDialog" @edit="openEditDialog" @delete="confirmDelete" @archive="toggleArchive" />
      </div>

      <!-- List view -->
      <div v-else-if="viewMode === 'list'" class="projects-list">
        <div class="list-header">
          <span>Project</span><span>Type / Industry</span><span>Status</span>
          <span>Progress</span><span>Cost</span><span>Updated</span><span></span>
        </div>
        <ProjectListRow v-for="project in projects" :key="project.id" :project="project"
          @view="openViewDialog" @edit="openEditDialog" @delete="confirmDelete" @archive="toggleArchive" />
      </div>

      <!-- Gantt Chart View -->
      <div v-else-if="viewMode === 'gantt'" class="gantt-view">
        <div class="gantt-toolbar">
          <span class="gantt-label">Portfolio Timeline</span>
          <div class="gantt-mode-toggle">
            <button v-for="mode in (['Day', 'Week', 'Month'] as const)" :key="mode" class="gantt-mode-btn" :class="{ active: ganttViewMode === mode }" @click="ganttViewMode = mode; initPortfolioGantt();">{{ mode }}</button>
          </div>
        </div>
        <div v-if="!ganttProjects.length" class="empty-state">
          <div class="empty-icon-wrap"><ActivityIcon class="empty-icon" /></div>
          <h3 class="empty-title">No project timelines available</h3>
          <p class="empty-desc">Projects need start and target completion dates to appear on the Gantt chart.</p>
        </div>
        <div v-else ref="ganttContainer" class="gantt-container"></div>
      </div>

      <!-- Calendar View -->
      <div v-else-if="viewMode === 'calendar'" class="calendar-view">
        <div v-if="!calendarEvents.length" class="empty-state">
          <div class="empty-icon-wrap"><CalendarDaysIcon class="empty-icon" /></div>
          <h3 class="empty-title">No project dates available</h3>
          <p class="empty-desc">Projects need start and target completion dates to appear on the calendar.</p>
        </div>
        <div v-else class="calendar-layout-split flex flex-col lg:flex-row gap-6">
          <div class="calendar-main flex-1 overflow-x-auto">
            <div class="calendar-wrapper min-w-[700px]">
              <FullCalendar :options="calendarOptions" />
            </div>
          </div>
          <!-- Selection Detail Panel -->
          <div class="calendar-side-panel w-full lg:w-80 shrink-0 border border-gray-200 dark:border-slate-700/80 rounded-xl bg-slate-50 dark:bg-slate-800/40 p-4 transition-all duration-300">
            <div v-if="selectedCalendarProject" class="flex flex-col h-full gap-4">
              <div class="panel-header border-b border-gray-200 dark:border-slate-700 pb-3">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                  {{ selectedCalendarProject.project_code || 'No Code' }}
                </span>
                <h4 class="text-sm font-bold text-gray-800 dark:text-gray-100 mt-1 leading-tight">{{ selectedCalendarProject.title }}</h4>
              </div>
              <div class="panel-body text-xs space-y-3 flex-1">
                <div class="flex justify-between">
                  <span class="text-gray-400">Proponent:</span>
                  <span class="font-medium text-gray-700 dark:text-gray-300 truncate max-w-[150px]">{{ selectedCalendarProject.proponent_name || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Current Stage:</span>
                  <span class="px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 font-semibold">{{ selectedCalendarProject.current_stage?.name || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Status:</span>
                  <span class="px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 font-semibold">{{ selectedCalendarProject.status?.name || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Cost:</span>
                  <span class="font-bold text-gray-800 dark:text-gray-200">{{ money(selectedCalendarProject.estimated_cost) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Dates:</span>
                  <span class="text-gray-700 dark:text-gray-300">
                    {{ selectedCalendarProject.start_date ? fmtDate(selectedCalendarProject.start_date) : 'N/A' }} → {{ selectedCalendarProject.target_completion_date ? fmtDate(selectedCalendarProject.target_completion_date) : 'N/A' }}
                  </span>
                </div>
                <div class="progress-box space-y-1 pt-2">
                  <div class="flex justify-between text-[11px]">
                    <span class="text-gray-400">Execution Progress:</span>
                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ selectedCalendarProject.progress_percentage || 0 }}%</span>
                  </div>
                  <div class="w-full h-2 bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-600" :style="{ width: `${selectedCalendarProject.progress_percentage || 0}%` }"></div>
                  </div>
                </div>
              </div>
              <button 
                class="w-full mt-2 py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition-colors flex items-center justify-center gap-1.5"
                @click="openViewDialog(selectedCalendarProject)"
              >
                Open Detailed Dossier <ArrowRightIcon class="w-3.5 h-3.5" />
              </button>
            </div>
            <div v-else class="flex flex-col items-center justify-center h-full text-center py-8 text-gray-400 dark:text-gray-500">
              <CalendarDaysIcon class="w-8 h-8 mb-2 opacity-50" />
              <p class="text-xs">Click a project timeline bar in the calendar to inspect its details here.</p>
            </div>
          </div>
        </div>
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
    <ViewProjectDialog
      v-if="showViewDialog && selectedProjectId"
      :key="`${selectedProjectId}-${selectedInitialTab}-${selectedRequirementId || 'none'}`"
      :modelValue="true"
      :projectId="selectedProjectId"
      :initialTab="selectedInitialTab"
      :initialRequirementId="selectedRequirementId"
      @update:modelValue="handleViewDialogVisibility"
      @edit="openEditFromView"
    />
    <DeleteConfirmDialog v-model="showDeleteDialog" :project="projectToDelete" @confirmed="executeDelete" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, markRaw, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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
import Gantt from 'frappe-gantt';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import {
  Search as SearchIcon, Filter as FilterIcon, Plus as PlusIcon, Download as DownloadIcon,
  Grid as GridIcon, List as ListIcon, ChevronLeft as ChevronLeftIcon, ChevronRight as ChevronRightIcon,
  FolderOpen as FolderOpenIcon, RefreshCcw as RefreshCcwIcon,
  Briefcase, CheckCircle, AlertTriangle, ArrowRight as ArrowRightIcon,
  Activity as ActivityIcon, CalendarDays as CalendarDaysIcon
} from 'lucide-vue-next';

const projectStore = useProjectStore();
const { projects, pagination, loading, projectTypes, industries, sectors, stages, statuses } = storeToRefs(projectStore);
const layoutStore = useLayoutStore();
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK);
const route = useRoute();
const router = useRouter();

const viewMode = ref<'grid' | 'list' | 'gantt' | 'calendar'>('grid');
const ganttContainer = ref<HTMLElement | null>(null);
let ganttInstance: any = null;
const ganttViewMode = ref<'Day' | 'Week' | 'Month'>('Month');
const showFilters = ref(false);
const searchFocused = ref(false);
const showCreateEditDialog = ref(false);
const showViewDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedProject = ref<Project | null>(null);
const selectedProjectId = ref<number | null>(null);
const selectedInitialTab = ref('overview');
const selectedRequirementId = ref<number | null>(null);
const projectToDelete = ref<Project | null>(null);

const reportPresetOptions = [
  { value: 'all', label: 'All Projects' },
  { value: 'approved', label: 'Approved' },
  { value: 'ongoing', label: 'Ongoing' },
  { value: 'completed', label: 'Completed' },
  { value: 'categorized', label: 'Categorized' },
  { value: 'reportable', label: 'Reportable to GCG' },
] as const;
const processTrackOptions = [
  { value: 'bdg_investment', label: 'External Investment Proposal (BDG)' },
  { value: 'spg_jv', label: 'Joint Venture Proposal (SPG)' },
  { value: 'spg_traditional', label: 'Traditional Equity Funding (SPG)' },
  { value: 'spg_ndc_own', label: 'NDC-Owned Project (SPG)' },
  { value: 'implementation_monitoring', label: 'Approved Project for Monitoring' },
  { value: 'divestment', label: 'Post-Investment / Divestment' },
] as const;
const dateFieldOptions = [
  { value: 'created_at', label: 'Created' },
  { value: 'updated_at', label: 'Updated' },
  { value: 'proposal_date', label: 'Proposal Date' },
  { value: 'start_date', label: 'Start Date' },
  { value: 'target_completion_date', label: 'Target Completion' },
  { value: 'actual_completion_date', label: 'Actual Completion' },
] as const;

const filters = ref<ProjectFilters>({
  search: '',
  sort_by: 'created_at',
  sort_order: 'desc',
  per_page: 12,
  page: 1,
  is_archived: false,
  report_preset: 'all',
  date_field: 'created_at',
});

const stats = computed(() => ({
  active: projects.value.filter(p => !p.is_archived && !p.is_deleted).length,
  overdue: projects.value.filter(p => p.is_overdue).length,
}));
const statCards = computed(() => [
  { label: 'Total', value: pagination.value?.total || projects.value.length, icon: markRaw(Briefcase), colorClass: 'blue' },
  { label: 'Active', value: stats.value.active, icon: markRaw(CheckCircle), colorClass: 'green' },
  { label: 'Overdue', value: stats.value.overdue, icon: markRaw(AlertTriangle), colorClass: 'red' },
]);
const activeFilterCount = computed(() => {
  let n = 0;
  if (filters.value.search) n++;
  if (filters.value.project_type_id) n++;
  if (filters.value.industry_id) n++;
  if (filters.value.sector_id) n++;
  if (filters.value.stage_id) n++;
  if (filters.value.status_id) n++;
  if (filters.value.process_track) n++;
  if (filters.value.report_preset && filters.value.report_preset !== 'all') n++;
  if (filters.value.date_from) n++;
  if (filters.value.date_to) n++;
  if (filters.value.estimated_cost_min) n++;
  if (filters.value.estimated_cost_max) n++;
  if (filters.value.actual_cost_min) n++;
  if (filters.value.actual_cost_max) n++;
  if (filters.value.progress_min) n++;
  if (filters.value.progress_max) n++;
  if (filters.value.is_overdue !== undefined) n++;
  if (filters.value.reportable_to_gcg !== undefined) n++;
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
  if (filters.value.process_track) { const t = processTrackOptions.find(x => x.value === filters.value.process_track); tags.push({ key: 'process_track', label: 'SOI Track', value: t?.label || filters.value.process_track }); }
  if (filters.value.report_preset && filters.value.report_preset !== 'all') { const r = reportPresetOptions.find(x => x.value === filters.value.report_preset); tags.push({ key: 'report_preset', label: 'Report View', value: r?.label || filters.value.report_preset }); }
  if (filters.value.date_from) tags.push({ key: 'date_from', label: 'From', value: filters.value.date_from });
  if (filters.value.date_to) tags.push({ key: 'date_to', label: 'To', value: filters.value.date_to });
  if (filters.value.estimated_cost_min) tags.push({ key: 'estimated_cost_min', label: 'Estimated Min', value: String(filters.value.estimated_cost_min) });
  if (filters.value.estimated_cost_max) tags.push({ key: 'estimated_cost_max', label: 'Estimated Max', value: String(filters.value.estimated_cost_max) });
  if (filters.value.actual_cost_min) tags.push({ key: 'actual_cost_min', label: 'Actual Min', value: String(filters.value.actual_cost_min) });
  if (filters.value.actual_cost_max) tags.push({ key: 'actual_cost_max', label: 'Actual Max', value: String(filters.value.actual_cost_max) });
  if (filters.value.progress_min) tags.push({ key: 'progress_min', label: 'Progress Min', value: `${filters.value.progress_min}%` });
  if (filters.value.progress_max) tags.push({ key: 'progress_max', label: 'Progress Max', value: `${filters.value.progress_max}%` });
  if (filters.value.is_overdue !== undefined) tags.push({ key: 'is_overdue', label: 'Overdue', value: filters.value.is_overdue ? 'Yes' : 'No' });
  if (filters.value.reportable_to_gcg !== undefined) tags.push({ key: 'reportable_to_gcg', label: 'GCG Reportable', value: filters.value.reportable_to_gcg ? 'Yes' : 'No' });
  if (filters.value.is_svf !== undefined) tags.push({ key: 'is_svf', label: 'SVF', value: filters.value.is_svf ? 'Yes' : 'No' });
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

const stageColorMap: Record<string, string> = {
  'Intake': '#64748b',
  'Requirements': '#8b5cf6',
  'Evaluation': '#2563eb',
  'Management Approval': '#0891b2',
  'Agreement & Release': '#059669',
  'Implementation & Monitoring': '#d97706',
  'Post-Investment': '#dc2626',
  'Divestment': '#7c3aed',
  'Completion': '#16a34a',
};

const ganttProjects = computed(() => {
  return projects.value
    .filter(p => p.start_date && p.target_completion_date)
    .map(p => ({
      id: String(p.id),
      name: `${p.project_code || ''} ${p.title}`.trim(),
      start: String(p.start_date!).split('T')[0],
      end: String(p.target_completion_date!).split('T')[0],
      progress: p.progress_percentage || 0,
      custom_class: '',
      _project: p,
    }));
});

const calendarEvents = computed(() => {
  return projects.value
    .filter(p => p.start_date || p.target_completion_date)
    .map(p => {
      const stageName = p.current_stage?.name || 'Intake';
      const color = stageColorMap[stageName] || '#64748b';
      return {
        id: String(p.id),
        title: `${p.project_code || ''} ${p.title}`.trim(),
        start: p.start_date || p.target_completion_date!,
        end: p.target_completion_date || p.start_date!,
        color,
        allDay: true,
        extendedProps: { project: p },
      };
    });
});

const selectedCalendarProject = ref<Project | null>(null);

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek,dayGridDay' },
  events: calendarEvents.value,
  height: 'auto',
  eventContent: (arg: any) => {
    const title = arg.event.title;
    const extend = arg.event.extendedProps;
    if (extend.project) {
      const p = extend.project;
      const progress = p.progress_percentage !== undefined ? `${p.progress_percentage}%` : '0%';
      const svfBadge = p.is_svf ? '⭐ ' : '';
      return {
        html: `
          <div class="fc-event-custom-content flex items-center justify-between gap-1 w-full overflow-hidden px-1.5 py-0.5">
            <span class="truncate font-semibold text-[11px]">${svfBadge}${title}</span>
            <span class="text-[9px] bg-black/25 dark:bg-white/25 px-1 rounded-sm font-bold shrink-0 text-white">${progress}</span>
          </div>
        `
      };
    }
    return { text: title };
  },
  eventClick: (info: any) => {
    const project = info.event.extendedProps?.project;
    if (project) {
      selectedCalendarProject.value = project;
    }
  },
}));

function money(value?: number | null): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(Number(value || 0));
}

function fmtDate(d?: string | null): string {
  if (!d) return 'N/A';
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function initPortfolioGantt() {
  if (!ganttContainer.value || !ganttProjects.value.length) return;
  ganttContainer.value.innerHTML = '';
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  ganttContainer.value.appendChild(svg);
  try {
    ganttInstance = new Gantt(svg, ganttProjects.value, {
      view_mode: ganttViewMode.value,
      date_format: 'YYYY-MM-DD',
      bar_height: 24,
      bar_corner_radius: 4,
      padding: 14,
      on_click: (task: any) => {
        const p = projects.value.find(pr => String(pr.id) === task.id);
        if (p) openViewDialog(p);
      },
    });
  } catch (e) {
    console.warn('Gantt init error:', e);
  }
}

const applyFilters = async () => {
  filters.value.page = 1;
  try {
    await projectStore.fetchProjects(filters.value);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to load projects');
  }
};
const resetFilters = async () => {
  filters.value = { search: '', sort_by: 'created_at', sort_order: 'desc', per_page: 12, page: 1, is_archived: false, report_preset: 'all', date_field: 'created_at' };
  await applyFilters();
};
const removeFilter = (key: string) => {
  (filters.value as any)[key] = undefined;
  if (key === 'is_archived') filters.value.is_archived = false;
  if (key === 'report_preset') filters.value.report_preset = 'all';
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
const openEditDialog = (p: Project) => {
  if (p.approval_lock?.is_locked) {
    toast.info(p.approval_lock.message || 'Project details are locked. Request a revision before editing.');
    openViewDialog(p, 'approval');
    return;
  }
  selectedProject.value = p;
  showCreateEditDialog.value = true;
};
const openViewDialog = (p: Project, tab = 'overview') => {
  selectedInitialTab.value = tab;
  selectedRequirementId.value = null;
  selectedProjectId.value = p.id;
  showViewDialog.value = true;
};
const handleViewDialogVisibility = (value: boolean) => {
  showViewDialog.value = value;
  if (!value) {
    selectedProjectId.value = null;
    selectedRequirementId.value = null;
  }
};
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
const onProjectSaved = async (savedProject: Project | null) => {
  if (savedProject?.id) {
    selectedInitialTab.value = 'overview';
    selectedRequirementId.value = null;
    selectedProjectId.value = savedProject.id;
    showViewDialog.value = true;
  }
  await applyFilters();
};
const exportProjects = async () => {
  try {
    const blob = await projectStore.exportProjects(filters.value);
    const preset = filters.value.report_preset || 'all';
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `ndc-projects-${preset}-${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toast.success('Project list exported');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to export projects');
  }
};

onMounted(async () => {
  try {
    await Promise.all([projectStore.loadAllLookupData(), projectStore.fetchProjects(filters.value)]);
    openProjectFromRoute();
    window.setTimeout(openProjectFromRoute, 450);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to initialize projects');
  }
});

watch(() => route.fullPath, async () => {
  await nextTick();
  openProjectFromRoute();
  window.setTimeout(openProjectFromRoute, 250);
}, { immediate: true });

watch([viewMode, () => projects.value], () => {
  if (viewMode.value === 'gantt') {
    nextTick(() => initPortfolioGantt());
  }
}, { flush: 'post' });

const openProjectFromRoute = async () => {
  const projectId = Number(route.query.project_id);
  if (!projectId) return;

  showViewDialog.value = false;
  await nextTick();
  selectedInitialTab.value = String(route.query.tab || 'overview');
  selectedRequirementId.value = route.query.requirement_id ? Number(route.query.requirement_id) : null;
  selectedProjectId.value = projectId;
  showViewDialog.value = true;

  await router.replace({ query: { ...route.query, project_id: undefined, tab: undefined, requirement_id: undefined } });
};
</script>

<style scoped>
/* ─── CSS Custom Properties (light + dark) ─── */
.projects-page {
  --c-bg: #e2e8f0;
  --c-card: rgba(255, 255, 255, 0.75);
  --c-subtle: rgba(255, 255, 255, 0.4);
  --c-muted: rgba(255, 255, 255, 0.3);
  --c-border: rgba(255, 255, 255, 0.6);
  --c-border-sub: rgba(255, 255, 255, 0.4);
  --c-text: #0f172a;
  --c-text-2: #475569;
  --c-text-3: #64748b;
  --c-text-in: #1e293b;
  --c-accent: #2563eb;
  --c-accent-bg: rgba(239, 246, 255, 0.65);
  --c-toolbar: rgba(255, 255, 255, 0.6);
  --c-skel-a: rgba(241, 245, 249, 0.5);
  --c-skel-b: rgba(226, 232, 240, 0.5);
  --glass-blur: blur(16px);
  --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
  background: var(--c-bg);
  background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 52%, #e5e7eb 100%);
  background-attachment: fixed;
  --header-bg: linear-gradient(180deg, #ffffff 0%, #f8fafc 60%, #eef2f7 100%);
  --header-text: #0f172a;
  --header-text-muted: #64748b;
  --header-border: rgba(15,23,42,0.1);
  --header-hover: rgba(15,23,42,0.04);
  --header-pill-bg: rgba(15,23,42,0.06);
  --header-pill-border: rgba(15,23,42,0.1);
  --header-pill-text: #475569;
}

/* Dark mode — Tailwind puts .dark on <html> */
:global(.dark) .projects-page, .projects-page.is-dark {
  --c-bg: #0f172a;
  --c-card: rgba(30, 41, 59, 0.6);
  --c-subtle: rgba(30, 41, 59, 0.3);
  --c-muted: rgba(41, 53, 72, 0.4);
  --c-border: rgba(255, 255, 255, 0.12);
  --c-border-sub: rgba(255, 255, 255, 0.06);
  --c-text: #f1f5f9;
  --c-text-2: #94a3b8;
  --c-text-3: #64748b;
  --c-text-in: #e2e8f0;
  --c-accent: #3b82f6;
  --c-accent-bg: rgba(30, 58, 95, 0.5);
  --c-toolbar: rgba(30, 41, 59, 0.5);
  --c-skel-a: rgba(41, 53, 72, 0.5);
  --c-skel-b: rgba(51, 65, 85, 0.5);
  --glass-blur: blur(20px);
  --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
  background: linear-gradient(180deg, #020617 0%, #0f172a 58%, #111827 100%);
  background-attachment: fixed;
  --header-bg: linear-gradient(180deg, #0f172a 0%, #111827 60%, #1f2937 100%);
  --header-text: #ffffff;
  --header-text-muted: rgba(255,255,255,0.5);
  --header-border: rgba(255,255,255,0.1);
  --header-hover: rgba(255,255,255,0.05);
  --header-pill-bg: rgba(255,255,255,0.12);
  --header-pill-border: rgba(255,255,255,0.2);
  --header-pill-text: rgba(255,255,255,0.85);
}

/* ─── Header ─── */
.page-header {
  background: var(--header-bg);
  padding: 2rem 2rem 0;
  color: var(--header-text);
  position: relative;
  overflow: hidden;
}
.page-header::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%2364748b' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
:global(.dark) .page-header::before, .projects-page.is-dark .page-header::before {
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
.header-content { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; position: relative; z-index: 1; }
.page-title { font-size: 2rem; font-weight: 800; letter-spacing: -0.025em; margin: 0 0 0.5rem; }
.page-subtitle { display: flex; align-items: center; gap: 0.5rem; margin: 0; flex-wrap: wrap; }
.stat-pill { font-size: 0.73rem; font-weight: 600; background: var(--header-pill-bg); border: 1px solid var(--header-pill-border); padding: 0.2rem 0.6rem; border-radius: 999px; color: var(--header-pill-text); }
.stat-pill.active { background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.3); color: #15803d; }
:global(.dark) .stat-pill.active, .projects-page.is-dark .stat-pill.active { background: rgba(34,197,94,0.2); border-color: rgba(34,197,94,0.4); color: #86efac; }
.stat-pill.overdue { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #b91c1c; }
:global(.dark) .stat-pill.overdue, .projects-page.is-dark .stat-pill.overdue { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); color: #fca5a5; }
.header-actions { display: flex; gap: 0.75rem; flex-shrink: 0; }
.btn-export { display: flex; align-items: center; gap: 0.5rem; padding: 0.575rem 1.1rem; background: var(--header-pill-bg); border: 1px solid var(--header-pill-border); color: var(--header-text); border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: background 0.2s; }
.btn-export:hover { background: var(--header-hover); }
.btn-create { display: flex; align-items: center; gap: 0.5rem; padding: 0.575rem 1.1rem; background: #2563eb; border: none; color: white; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-create:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.35); }
.btn-icon { width: 1rem; height: 1rem; flex-shrink: 0; }

.stats-row { display: grid; grid-template-columns: repeat(5, 1fr); border-top: 1px solid var(--header-border); position: relative; z-index: 1; }
.stat-card { display: flex; align-items: center; gap: 0.875rem; padding: 1.125rem 1.5rem; border-right: 1px solid var(--header-border); transition: background 0.15s; }
.stat-card:last-child { border-right: none; }
.stat-card:hover { background: var(--header-hover); }
.stat-icon { width: 2.375rem; height: 2.375rem; border-radius: 0.625rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon .icon { width: 1.125rem; height: 1.125rem; }
.stat-icon.blue { background: rgba(59,130,246,0.15); color: #2563eb; }
:global(.dark) .stat-icon.blue, .projects-page.is-dark .stat-icon.blue { background: rgba(59,130,246,0.2); color: #93c5fd; }
.stat-icon.green { background: rgba(34,197,94,0.15); color: #16a34a; }
:global(.dark) .stat-icon.green, .projects-page.is-dark .stat-icon.green { background: rgba(34,197,94,0.2); color: #86efac; }
.stat-icon.red { background: rgba(239,68,68,0.15); color: #dc2626; }
:global(.dark) .stat-icon.red, .projects-page.is-dark .stat-icon.red { background: rgba(239,68,68,0.2); color: #fca5a5; }
.stat-icon.amber { background: rgba(245,158,11,0.15); color: #d97706; }
:global(.dark) .stat-icon.amber, .projects-page.is-dark .stat-icon.amber { background: rgba(245,158,11,0.2); color: #fcd34d; }
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.375rem; font-weight: 700; color: var(--header-text); line-height: 1; }
.stat-label { font-size: 0.68rem; color: var(--header-text-muted); margin-top: 0.25rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; }

/* ─── Toolbar ─── */
.toolbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.875rem 2rem;
  background: var(--c-toolbar);
  backdrop-filter: var(--glass-blur);
  -webkit-backdrop-filter: var(--glass-blur);
  border-bottom: 1px solid var(--c-border);
  box-shadow: var(--glass-shadow);
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

.report-preset-strip {
  display: grid;
  grid-template-columns: minmax(13rem, 1fr) minmax(16rem, 0.65fr);
  align-items: center;
  gap: 1rem;
  padding: 0.9rem 2rem;
  background: var(--c-card);
  border-bottom: 1px solid var(--c-border);
  backdrop-filter: var(--glass-blur);
  -webkit-backdrop-filter: var(--glass-blur);
}
.preset-copy span { display: block; color: var(--c-text); font-size: 0.78rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.06em; }
.preset-copy p { margin: 0.2rem 0 0; color: var(--c-text-3); font-size: 0.78rem; line-height: 1.35; }
.preset-control { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.preset-label { color: var(--c-text-3); font-size: 0.68rem; font-weight: 900; letter-spacing: 0.07em; text-transform: uppercase; }
.report-select-wrap { position: relative; display: flex; align-items: center; min-width: 0; }
.preset-select-icon { position: absolute; left: 0.75rem; width: 0.95rem; height: 0.95rem; color: var(--c-accent); pointer-events: none; }
.report-preset-select {
  width: 100%;
  min-height: 2.55rem;
  border: 1px solid var(--c-border);
  border-radius: 0.55rem;
  background: var(--c-muted);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  color: var(--c-text-2);
  padding: 0.55rem 2.25rem 0.55rem 2.25rem;
  font-size: 0.82rem;
  font-weight: 800;
  appearance: none;
  cursor: pointer;
  transition: all 0.15s ease;
}
.report-preset-select:hover, .report-preset-select:focus { border-color: var(--c-accent); background-color: var(--c-accent-bg); color: var(--c-accent); outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.visible-reporting { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; }
.visible-reporting span { border: 1px solid var(--c-border); border-radius: 999px; background: var(--c-muted); color: var(--c-text-3); padding: 0.25rem 0.55rem; font-size: 0.7rem; font-weight: 800; white-space: nowrap; }
.visible-reporting strong { color: var(--c-text); }

/* ─── Filters Panel ─── */
.filters-panel { background: var(--c-toolbar); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border-bottom: 1px solid var(--c-border); box-shadow: var(--glass-shadow); padding: 1.25rem 2rem; }
.filters-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 0.875rem; margin-bottom: 1rem; }
.filter-group { display: flex; flex-direction: column; gap: 0.3rem; }
.filter-group.range-group { grid-column: span 2; min-width: 0; }
.range-inputs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem; }
.filter-label { font-size: 0.68rem; font-weight: 700; color: var(--c-text-3); text-transform: uppercase; letter-spacing: 0.07em; }
.filter-select { padding: 0.5rem 1.75rem 0.5rem 0.625rem; background: var(--c-muted); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.5rem center; border: 1px solid var(--c-border); border-radius: 0.375rem; font-size: 0.8rem; color: var(--c-text); appearance: none; cursor: pointer; }
.filter-input { padding: 0.5rem 0.625rem; background: var(--c-muted); border: 1px solid var(--c-border); border-radius: 0.375rem; font-size: 0.8rem; color: var(--c-text); }
.filter-select:focus, .filter-input:focus { outline: none; border-color: var(--c-accent); box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }

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
.projects-list { background: var(--c-card); border-radius: 0.75rem; border: 1px solid var(--c-border); overflow: hidden; backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); box-shadow: var(--glass-shadow); }
.list-header { display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1fr 80px; gap: 1rem; padding: 0.75rem 1.25rem; background: var(--c-subtle); border-bottom: 1px solid var(--c-border); font-size: 0.68rem; font-weight: 700; color: var(--c-text-3); text-transform: uppercase; letter-spacing: 0.05em; backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); }

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

@media (max-width: 1180px) {
  .stats-row { grid-template-columns: repeat(3, 1fr); }
  .report-preset-strip { grid-template-columns: 1fr; align-items: stretch; }
  .visible-reporting { justify-content: flex-start; }
}
@media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
  .page-header { padding: 1.25rem 1rem 0; }
  .header-content { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .toolbar { padding: 0.75rem 1rem; }
  .report-preset-strip { padding: 0.85rem 1rem; }
  .search-wrapper { min-width: unset; flex: 1; }
  .content-area { padding: 1rem 1rem 3rem; }
  .stats-row { grid-template-columns: repeat(2, 1fr); }
  .projects-grid { grid-template-columns: 1fr; }
  .list-header { display: none; }
}

/* ─── Gantt View ─── */
.gantt-view { background: var(--c-card); border-radius: 0.75rem; border: 1px solid var(--c-border); overflow: hidden; backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); box-shadow: var(--glass-shadow); }
.gantt-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.25rem; border-bottom: 1px solid var(--c-border); background: var(--c-subtle); }
.gantt-label { font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--c-text-2); }
.gantt-mode-toggle { display: flex; gap: 0.25rem; background: var(--c-muted); border-radius: 0.4rem; padding: 0.15rem; }
.gantt-mode-btn { padding: 0.3rem 0.7rem; border: none; background: transparent; border-radius: 0.3rem; cursor: pointer; font-size: 0.75rem; font-weight: 600; color: var(--c-text-3); transition: all 0.15s; }
.gantt-mode-btn.active { background: var(--c-card); color: var(--c-accent); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.gantt-container {
  padding: 1rem;
  overflow-x: auto;
  --scrollbar-thumb: var(--c-text-3);
  --scrollbar-track: var(--c-muted);
  scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
  scrollbar-width: thin;
}
@supports not (scrollbar-color: auto) {
  .gantt-container::-webkit-scrollbar {
    height: 6px;
  }
  .gantt-container::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 99px;
  }
  .gantt-container::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
  }
}
.gantt-container :deep(.gantt) .grid-header { fill: transparent; stroke: var(--c-border); }
.gantt-container :deep(.gantt) .grid-row { fill: transparent; }
.gantt-container :deep(.gantt) .grid-row:nth-child(even) { fill: rgba(0,0,0,0.02); }
.gantt-container :deep(.gantt) .tick { stroke: var(--c-border); stroke-dasharray: 4; }
.gantt-container :deep(.gantt) .bar-label { fill: white; font-size: 11px; font-weight: 600; }
.gantt-container :deep(.gantt) .lower-text, .gantt-container :deep(.gantt) .upper-text { fill: var(--c-text-2); font-size: 11px; }
:global(.dark) .gantt-container :deep(.gantt) .grid-row:nth-child(even) { fill: rgba(255,255,255,0.02); }

/* ─── Calendar View ─── */
.calendar-view {
  background: var(--c-card);
  border-radius: 0.75rem;
  border: 1px solid var(--c-border);
  overflow-x: auto;
  padding: 1.25rem;
  backdrop-filter: var(--glass-blur);
  -webkit-backdrop-filter: var(--glass-blur);
  box-shadow: var(--glass-shadow);
  --scrollbar-thumb: var(--c-text-3);
  --scrollbar-track: var(--c-muted);
  scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
  scrollbar-width: thin;
}
@supports not (scrollbar-color: auto) {
  .calendar-view::-webkit-scrollbar {
    height: 6px;
  }
  .calendar-view::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 99px;
  }
  .calendar-view::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
  }
}
.calendar-view :deep(.fc) { --fc-border-color: var(--c-border); --fc-button-bg-color: var(--c-accent); --fc-button-hover-bg-color: #1d4ed8; --fc-today-bg-color: rgba(37,99,235,0.08); --fc-page-bg-color: transparent; --fc-neutral-bg-color: var(--c-subtle); }
.calendar-view :deep(.fc .fc-toolbar-title) { font-size: 1.1rem; font-weight: 700; color: var(--c-text); }
.calendar-view :deep(.fc .fc-button) { border-radius: 0.375rem; font-size: 0.8rem; font-weight: 600; padding: 0.35rem 0.75rem; }
.calendar-view :deep(.fc .fc-daygrid-day-number) { color: var(--c-text-2); font-size: 0.8rem; font-weight: 500; padding: 0.35rem; }
.calendar-view :deep(.fc .fc-event) { border: none; border-radius: 0.25rem; padding: 0.15rem 0.4rem; font-size: 0.72rem; font-weight: 600; cursor: pointer; }
.calendar-view :deep(.fc .fc-col-header-cell-cushion) { color: var(--c-text-3); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
</style>
