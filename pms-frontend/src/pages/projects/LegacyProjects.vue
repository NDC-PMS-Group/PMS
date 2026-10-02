<template>
  <div class="legacy-page">
    <header class="page-header">
      <div class="header-content">
        <div class="header-text">
          <p class="eyebrow">Project migration</p>
          <h1 class="page-title">{{ isProponentAccount ? 'My Legacy Projects' : 'Legacy Projects' }}</h1>
          <p class="page-subtitle">
            <span class="stat-pill">{{ pagination?.total ?? projects.length }} total</span>
            <span class="stat-pill warning">{{ statusCounts.needs_details }} need details</span>
            <span class="stat-pill active">{{ statusCounts.complete }} complete</span>
          </p>
        </div>
        <div class="header-actions">
          <button class="btn-export" type="button" @click="fetchLegacyProjects">
            <RefreshCw class="btn-icon" />
            Refresh
          </button>
          <button v-if="canImport" class="btn-create" type="button" @click="fileInput?.click()">
            <Upload class="btn-icon" />
            Upload Excel
          </button>
          <input ref="fileInput" class="sr-only" type="file" accept=".xlsx,.xls" @change="handleFileChange" />
        </div>
      </div>
      <div class="stats-row" aria-label="Legacy project summary">
        <div v-for="stat in legacyStatCards" :key="stat.label" class="stat-card">
          <div class="stat-icon" :class="stat.colorClass">
            <component :is="stat.icon" class="icon" />
          </div>
          <div class="stat-info">
            <span class="stat-value">{{ stat.value }}</span>
            <span class="stat-label">{{ stat.label }}</span>
          </div>
        </div>
      </div>
    </header>

    <section v-if="canImport && (selectedFile || preview)" class="import-panel">
      <div class="import-head">
        <div>
          <h2>Import preview</h2>
          <p>{{ selectedFile?.name || preview?.file_name || 'Selected workbook' }}</p>
        </div>
        <div class="import-actions">
          <button class="secondary-btn" type="button" @click="clearImport">
            <X />
            Clear
          </button>
          <button class="secondary-btn" type="button" :disabled="!selectedFile || previewLoading" @click="previewImport">
            <Eye />
            {{ previewLoading ? 'Previewing...' : 'Preview' }}
          </button>
          <button class="primary-btn" type="button" :disabled="!preview || !selectedFile || commitLoading || !canImport" @click="commitImport">
            <DatabaseZap />
            {{ commitLoading ? 'Importing...' : 'Confirm Import' }}
          </button>
        </div>
      </div>

      <div v-if="preview" class="preview-body">
        <div class="preview-stats">
          <div>
            <span>Rows found</span>
            <strong>{{ preview.summary.total_rows }}</strong>
          </div>
          <div>
            <span>Ready to import</span>
            <strong>{{ preview.summary.importable_rows }}</strong>
          </div>
          <div>
            <span>Duplicates</span>
            <strong>{{ preview.summary.duplicate_rows }}</strong>
          </div>
          <div>
            <span>Warnings</span>
            <strong>{{ preview.summary.warning_rows }}</strong>
          </div>
        </div>

        <div v-if="preview.warnings.length" class="warning-list" role="alert">
          <AlertTriangle />
          <div>
            <strong>Workbook warnings</strong>
            <p v-for="warning in preview.warnings" :key="warning">{{ warning }}</p>
          </div>
        </div>

        <div class="table-wrap preview-table">
          <table>
            <thead>
              <tr>
                <th>Row</th>
                <th>Project</th>
                <th>Source status</th>
                <th>Mapped PMS status</th>
                <th>Sector</th>
                <th>Cost</th>
                <th>Import state</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in preview.rows.slice(0, 10)" :key="row.row_fingerprint">
                <td>{{ row.source_row }}</td>
                <td>
                  <strong>{{ row.title || 'Missing title' }}</strong>
                  <small>{{ row.location || 'No location provided' }}</small>
                </td>
                <td>{{ row.source_status_raw || 'Not provided' }}</td>
                <td>
                  <span class="stacked">
                    <b>{{ row.mapped_stage }}</b>
                    <small>{{ row.mapped_status }}</small>
                  </span>
                </td>
                <td>{{ row.sector || 'Not set' }}</td>
                <td>{{ formatCurrency(row.estimated_cost) || row.source_cost_raw || 'Cost not set' }}</td>
                <td>
                  <span class="state-pill" :class="rowStateClass(row)">
                    {{ rowStateLabel(row) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="preview.rows.length > 10" class="preview-note">
          Showing first 10 rows. Confirm import will process all {{ preview.summary.total_rows }} detected rows.
        </p>
      </div>
    </section>

    <section class="workspace">
      <div class="filters-bar">
        <label class="search-field">
          <span>Search legacy projects</span>
          <Search />
          <input v-model="filters.search" type="search" placeholder="Search title, code, proponent, location..." @keyup.enter="applyFilters" />
        </label>
        <label class="filter-field">
          <span>Review state</span>
          <select v-model="filters.detail_status" @change="applyFilters">
            <option value="">All detail states</option>
            <option value="needs_details">Needs details</option>
            <option value="in_progress">In progress</option>
            <option value="complete">Complete</option>
          </select>
        </label>
        <label class="filter-field">
          <span>Source status</span>
          <select v-model="filters.source_status" @change="applyFilters">
            <option value="">All source statuses</option>
            <option v-for="status in sourceStatusOptions" :key="status" :value="status">{{ status }}</option>
          </select>
        </label>
        <label class="filter-field">
          <span>PMS status</span>
          <select v-model="filters.status_id" @change="applyFilters">
            <option value="">All PMS statuses</option>
            <option v-for="status in projectStore.statuses" :key="status.id" :value="status.id">{{ status.name }}</option>
          </select>
        </label>
        <label class="filter-field">
          <span>Sector</span>
          <select v-model="filters.sector_id" @change="applyFilters">
            <option value="">All sectors</option>
            <option v-for="sector in projectStore.sectors" :key="sector.id" :value="sector.id">{{ sector.name }}</option>
          </select>
        </label>
        <label class="filter-field">
          <span>Archive state</span>
          <select v-model="filters.is_archived" @change="applyFilters">
            <option value="">Active and archived</option>
            <option value="false">Active only</option>
            <option value="true">Archived only</option>
          </select>
        </label>
        <div class="filter-actions">
          <button class="secondary-btn" type="button" @click="resetFilters">
            Reset
          </button>
          <button class="secondary-btn" type="button" @click="applyFilters">
            <Filter />
            Apply
          </button>
        </div>
      </div>

      <div class="table-wrap legacy-table" aria-live="polite">
        <table>
          <caption>Legacy projects awaiting review and migration cleanup</caption>
          <thead>
            <tr>
              <th>Project</th>
              <th>Mapped status</th>
              <th>Source status</th>
              <th>Sector / location</th>
              <th>Cost / released</th>
              <th>Partner / account</th>
              <th>Review state</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="empty-cell">Loading legacy projects...</td>
            </tr>
            <tr v-else-if="projects.length === 0">
              <td colspan="7" class="empty-cell">{{ isProponentAccount ? 'No legacy projects are linked to your account yet.' : 'No legacy projects found. Upload the NDC project list to begin.' }}</td>
            </tr>
            <template v-else>
              <tr
                v-for="project in projects"
                :key="project.id"
                class="clickable-row"
                tabindex="0"
                @click="openEditor(project)"
                @keydown.enter.prevent="openEditor(project)"
                @keydown.space.prevent="openEditor(project)"
              >
                <td data-label="Project">
                  <button class="title-link" type="button" @click.stop="openEditor(project)">
                    {{ project.title }}
                  </button>
                  <small>{{ project.project_code }} <span v-if="project.is_archived">Archived</span></small>
                </td>
                <td data-label="Mapped status">
                  <span class="stacked">
                    <b>{{ project.current_stage?.name || 'Stage not set' }}</b>
                    <small>{{ project.status?.name || 'Status not set' }}</small>
                  </span>
                </td>
                <td data-label="Source status">{{ project.legacy_detail?.source_status_raw || 'Not provided' }}</td>
                <td data-label="Sector / location">
                  <span class="stacked">
                    <b>{{ project.sector?.name || 'Sector not set' }}</b>
                    <small>{{ project.location_address || 'No location provided' }}</small>
                  </span>
                </td>
                <td data-label="Cost / released">
                  <span class="stacked">
                    <b>{{ formatCurrency(project.estimated_cost) || 'Cost not set' }}</b>
                    <small>{{ formatCurrency(project.actual_cost) ? `Released ${formatCurrency(project.actual_cost)}` : 'No releases yet' }}</small>
                  </span>
                </td>
                <td data-label="Partner / account">
                  <span class="stacked">
                    <b>{{ project.proponent_name || project.legacy_detail?.source_partner_raw || 'Not assigned' }}</b>
                    <small class="account-line">
                      <span class="account-dot" :class="{ linked: project.proponent_user }"></span>
                      {{ project.proponent_user ? `Linked to ${displayUser(project.proponent_user)}` : 'Not linked' }}
                    </small>
                  </span>
                </td>
                <td data-label="Review state">
                  <span class="state-pill" :class="detailStatusClass(project.legacy_detail?.detail_status)">
                    {{ detailStatusLabel(project.legacy_detail?.detail_status) }}
                  </span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <nav v-if="pagination && pagination.last_page > 1" class="pagination" aria-label="Legacy project pages">
        <button class="secondary-btn" type="button" :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">
          Previous
        </button>
        <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <button class="secondary-btn" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)">
          Next
        </button>
      </nav>
    </section>

    <aside v-if="editingProject" class="drawer-backdrop" @click.self="closeEditor" @keydown.esc="closeEditor">
      <form ref="drawerForm" class="detail-drawer" tabindex="-1" aria-labelledby="legacy-review-title" @submit.prevent="saveProject">
        <header>
          <div>
            <p class="eyebrow">{{ editingProject.project_code }}</p>
            <h2 id="legacy-review-title">Review legacy project</h2>
          </div>
          <button class="icon-btn" type="button" title="Close review" aria-label="Close review" @click="closeEditor">
            <X />
          </button>
        </header>

        <div class="drawer-body">
          <section class="review-summary" :class="{ ready: editingProject.legacy_review?.monitoring_ready }">
            <div>
              <span class="summary-label">Monitoring readiness</span>
              <strong>{{ editingProject.legacy_review?.monitoring_ready ? 'Ready to open' : 'Needs attention' }}</strong>
              <p>{{ editingProject.legacy_review?.monitoring_message }}</p>
              <ul v-if="reviewChecklist.missing.length" class="missing-list" aria-label="Missing review details">
                <li v-for="item in reviewChecklist.missing" :key="item">{{ item }}</li>
              </ul>
            </div>
            <button class="secondary-btn" type="button" @click="openWorkspace(editingProject, 'monitoring')">
              <Activity /> Monitoring
            </button>
          </section>

          <nav class="review-nav" aria-label="Legacy review sections">
            <button type="button" @click="scrollDrawerSection('legacy-source')">Source</button>
            <button type="button" @click="scrollDrawerSection('legacy-profile')">Profile</button>
            <button type="button" @click="scrollDrawerSection('legacy-funding')">Funding</button>
            <button type="button" @click="scrollDrawerSection('legacy-context')">Context</button>
            <button type="button" @click="scrollDrawerSection('legacy-account')">Account</button>
          </nav>

          <section id="legacy-source" class="source-panel">
            <div class="section-heading"><div><p class="eyebrow">Migration audit</p><h3>Imported source</h3></div><span class="state-pill" :class="detailStatusClass(editingProject.legacy_detail?.detail_status)">{{ detailStatusLabel(editingProject.legacy_detail?.detail_status) }}</span></div>
            <dl>
              <div><dt>Source row</dt><dd>{{ editingProject.legacy_detail?.source_row || 'Not provided' }}</dd></div>
              <div><dt>Source status</dt><dd>{{ editingProject.legacy_detail?.source_status_raw || 'Not provided' }}</dd></div>
              <div><dt>Raw cost</dt><dd>{{ editingProject.legacy_detail?.source_cost_raw || 'Not provided' }}</dd></div>
              <div><dt>Raw released</dt><dd>{{ editingProject.legacy_detail?.source_fund_released_raw || 'Not provided' }}</dd></div>
              <div><dt>Import batch</dt><dd>{{ editingProject.legacy_detail?.batch?.file_name || editingProject.legacy_detail?.source_file || 'Legacy workbook' }}</dd></div>
              <div><dt>Imported</dt><dd>{{ formatDateTime(editingProject.legacy_detail?.batch?.created_at) }}</dd></div>
              <div class="wide"><dt>Remarks</dt><dd>{{ editingProject.legacy_detail?.source_remarks || 'No remarks supplied' }}</dd></div>
              <div v-if="editingProject.legacy_detail?.parse_warnings?.length" class="wide source-warnings"><dt>Import warnings</dt><dd>{{ editingProject.legacy_detail?.parse_warnings.join(' · ') }}</dd></div>
            </dl>
          </section>

          <section id="legacy-profile" class="form-section">
            <div class="section-heading"><div><p class="eyebrow">PMS profile</p><h3>Identity and ownership</h3></div><button class="inline-link" type="button" @click="openWorkspace(editingProject, 'overview')">Open full project <ExternalLink /></button></div>
            <div class="form-grid">
              <label class="wide"><span>Project title</span><input v-model="editForm.title" required /></label>
              <label><span>Project type</span><select v-model="editForm.project_type_id"><option :value="null">Unspecified</option><option v-for="item in projectStore.projectTypes" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
              <label><span>Industry</span><select v-model="editForm.industry_id"><option :value="null">Unspecified</option><option v-for="item in projectStore.industries" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
              <label><span>Sector</span><select v-model="editForm.sector_id"><option :value="null">Unspecified</option><option v-for="sector in projectStore.sectors" :key="sector.id" :value="sector.id">{{ sector.name }}</option></select></label>
              <label><span>Detail state</span><select v-model="editForm.detail_status"><option value="needs_details">Needs details</option><option value="in_progress">In progress</option><option value="complete">Complete</option></select></label>
              <label><span>PMS stage</span><select v-model.number="editForm.current_stage_id" required><option v-for="stage in projectStore.stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option></select></label>
              <label><span>PMS status</span><select v-model.number="editForm.status_id" required><option v-for="status in projectStore.statuses" :key="status.id" :value="status.id">{{ status.name }}</option></select></label>
              <label><span>Project officer</span><select v-model="editForm.project_officer_id"><option :value="null">Unassigned</option><option v-for="user in staffOptions" :key="user.id" :value="user.id">{{ displayUser(user) }}</option></select></label>
              <label><span>Workgroup head</span><select v-model="editForm.workgroup_head_id"><option :value="null">Unassigned</option><option v-for="user in staffOptions" :key="user.id" :value="user.id">{{ displayUser(user) }}</option></select></label>
            </div>
          </section>

          <section id="legacy-funding" class="form-section">
            <div class="section-heading"><div><p class="eyebrow">Investment profile</p><h3>Funding and NDC criteria</h3></div></div>
            <div class="form-grid">
              <label><span>Investment type</span><select v-model="editForm.investment_type_id"><option :value="null">Unspecified</option><option v-for="item in projectStore.investmentTypes" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
              <label><span>Funding source</span><select v-model="editForm.funding_source_id"><option :value="null">Unspecified</option><option v-for="item in projectStore.fundingSources" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
              <label><span>Estimated cost</span><input v-model="editForm.estimated_cost" type="number" min="0" step="0.01" /></label>
              <label><span>Fund released</span><input v-model="editForm.actual_cost" type="number" min="0" step="0.01" /></label>
              <label><span>Target amount to raise</span><input v-model="editForm.target_amount_to_raise" type="number" min="0" step="0.01" /></label>
              <label><span>NDC participation</span><input v-model="editForm.ndc_participation" type="number" min="0" step="0.01" /></label>
              <div class="wide criteria-field"><span>NDC investment criteria</span><div class="criteria-options"><label v-for="item in projectStore.investmentCriteria" :key="item.key"><input v-model="editForm.ndc_investment_criteria" type="checkbox" :value="item.key" />{{ item.name }}</label></div></div>
              <label v-if="editForm.ndc_investment_criteria.includes('others')" class="wide"><span>Other criterion</span><input v-model="editForm.ndc_investment_criteria_other" placeholder="Describe the additional criterion" /></label>
            </div>
          </section>

          <section id="legacy-context" class="form-section">
            <div class="section-heading"><div><p class="eyebrow">Project context</p><h3>Timeline, location, and notes</h3></div></div>
            <div class="form-grid">
              <label><span>Application date</span><input v-model="editForm.date_of_application" type="date" /></label>
              <label><span>Start date</span><input v-model="editForm.start_date" type="date" /></label>
              <label><span>Target completion</span><input v-model="editForm.target_completion_date" type="date" /></label>
              <label><span>Actual completion</span><input v-model="editForm.actual_completion_date" type="date" /></label>
              <label class="wide"><span>Location</span><input v-model="editForm.location_address" /></label>
              <label class="wide"><span>Description / notes</span><textarea v-model="editForm.description" rows="3" /></label>
              <label class="wide"><span>Project rationale</span><textarea v-model="editForm.project_rationale" rows="3" /></label>
              <label><span>Company background</span><textarea v-model="editForm.company_background" rows="3" /></label>
              <label><span>Target beneficiaries</span><textarea v-model="editForm.target_beneficiaries" rows="3" /></label>
              <label class="wide"><span>Expected benefits</span><textarea v-model="editForm.expected_benefits" rows="3" /></label>
              <label class="wide"><span>Next steps</span><textarea v-model="editForm.next_steps" rows="3" /></label>
            </div>
          </section>

          <section id="legacy-account" class="form-section">
            <div class="section-heading"><div><p class="eyebrow">External coordination</p><h3>Proponent and monitoring account</h3></div><button class="inline-link" type="button" @click="openWorkspace(editingProject, 'team')">Manage team <ExternalLink /></button></div>
            <div class="form-grid">
              <label><span>Proponent / partner</span><input v-model="editForm.proponent_name" /></label>
              <label><span>Contact number</span><input v-model="editForm.proponent_contact" /></label>
              <label class="wide"><span>Proponent email</span><input v-model="editForm.proponent_email" type="email" placeholder="Required before monitoring can be opened" /></label>
              <label class="wide">
                <span>Linked Proponent account</span>
                <select v-model.number="editForm.proponent_user_id" @change="syncLinkedProponent">
                  <option :value="null">No linked account</option>
                  <option v-for="user in proponentOptions" :key="user.id" :value="user.id">
                    {{ displayUser(user) }} · {{ user.email }}
                  </option>
                </select>
                <small v-if="editForm.proponent_user_id" class="linked-help">
                  Monitoring will be opened to {{ editForm.proponent_email || 'this selected account' }}.
                </small>
                <small v-else>
                  Choose an active external Proponent account before opening monitoring. Invite the contact from People & Access if needed.
                </small>
              </label>
            </div>
          </section>
        </div>

        <footer>
          <button class="secondary-btn" type="button" @click="closeEditor">Close</button>
          <button class="secondary-btn" type="button" @click="openWorkspace(editingProject, 'overview')"><ExternalLink /> Project workspace</button>
          <button class="secondary-btn" type="button" :disabled="saving || !canUpdate" @click="markComplete">
            <CheckCircle />
            Mark Complete
          </button>
          <button class="primary-btn" type="submit" :disabled="saving || !canUpdate">
            <Save />
            {{ saving ? 'Saving...' : 'Save Details' }}
          </button>
        </footer>
      </form>
    </aside>

    <ViewProjectDialog
      v-if="workspaceProjectId"
      :key="`${workspaceProjectId}-${workspaceTab}`"
      :modelValue="true"
      :projectId="workspaceProjectId"
      :initialTab="workspaceTab"
      @update:modelValue="closeWorkspace"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import {
  AlertTriangle,
  Archive,
  CheckCircle,
  Activity,
  DatabaseZap,
  Eye,
  Filter,
  ExternalLink,
  RefreshCw,
  Save,
  Search,
  Upload,
  X,
} from 'lucide-vue-next';
import axiosInstance from '@/utils/axiosInstance';
import { useAuthStore } from '@/store/auth';
import { useProjectStore } from '@/store/projects';
import { useUserStore } from '@/store/user';
import ViewProjectDialog from '@/components/projects/ViewProjectDialog.vue';
import type { LegacyProjectPreview, LegacyProjectPreviewRow, Project } from '@/types/project';
import type { PaginationMeta } from '@/types/paginationMeta';

type LegacyFilters = {
  search: string;
  detail_status: string;
  source_status: string;
  status_id: string | number;
  sector_id: string | number;
  is_archived: string;
  page: number;
  per_page: number;
};

type EditForm = {
  title: string;
  description: string | null;
  date_of_application: string | null;
  project_type_id: number | null;
  industry_id: number | null;
  sector_id: number | null;
  investment_type_id: number | null;
  funding_source_id: number | null;
  estimated_cost: string | number | null;
  actual_cost: string | number | null;
  target_amount_to_raise: string | number | null;
  ndc_participation: string | number | null;
  ndc_investment_criteria: string[];
  ndc_investment_criteria_other: string | null;
  project_rationale: string | null;
  company_background: string | null;
  target_beneficiaries: string | null;
  expected_benefits: string | null;
  next_steps: string | null;
  start_date: string | null;
  target_completion_date: string | null;
  actual_completion_date: string | null;
  location_address: string | null;
  proponent_name: string | null;
  proponent_contact: string | null;
  proponent_email: string | null;
  proponent_user_id: number | null;
  project_officer_id: number | null;
  workgroup_head_id: number | null;
  current_stage_id: number | null;
  status_id: number | null;
  detail_status: string;
};

const authStore = useAuthStore();
const projectStore = useProjectStore();
const userStore = useUserStore();
const route = useRoute();
const router = useRouter();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const preview = ref<LegacyProjectPreview | null>(null);
const previewLoading = ref(false);
const commitLoading = ref(false);
const loading = ref(false);
const saving = ref(false);
const projects = ref<Project[]>([]);
const pagination = ref<PaginationMeta | null>(null);
const editingProject = ref<Project | null>(null);
const drawerForm = ref<HTMLFormElement | null>(null);
const workspaceProjectId = ref<number | null>(null);
const workspaceTab = ref('overview');

const filters = reactive<LegacyFilters>({
  search: '',
  detail_status: '',
  source_status: '',
  status_id: '',
  sector_id: '',
  is_archived: '',
  page: 1,
  per_page: 15,
});

const editForm = reactive<EditForm>({
  title: '',
  description: null,
  date_of_application: null,
  project_type_id: null,
  industry_id: null,
  sector_id: null,
  investment_type_id: null,
  funding_source_id: null,
  estimated_cost: null,
  actual_cost: null,
  target_amount_to_raise: null,
  ndc_participation: null,
  ndc_investment_criteria: [],
  ndc_investment_criteria_other: null,
  project_rationale: null,
  company_background: null,
  target_beneficiaries: null,
  expected_benefits: null,
  next_steps: null,
  start_date: null,
  target_completion_date: null,
  actual_completion_date: null,
  location_address: null,
  proponent_name: null,
  proponent_contact: null,
  proponent_email: null,
  proponent_user_id: null,
  project_officer_id: null,
  workgroup_head_id: null,
  current_stage_id: null,
  status_id: null,
  detail_status: 'needs_details',
});

const canImport = computed(() => authStore.can('projects', 'create'));
const canUpdate = computed(() => authStore.can('projects', 'update'));
const isProponentAccount = computed(() => {
  const roleName = String(authStore.user?.role?.name || '').toLowerCase();
  const roleId = Number((authStore.user as any)?.default_role_id ?? authStore.user?.role?.id);
  return roleName === 'proponent' || roleId === 7;
});
const proponentRoleId = ref<number | null>(null);

const sourceStatusOptions = computed(() => {
  const fromProjects = projects.value
    .map((project) => project.legacy_detail?.source_status_raw)
    .filter(Boolean) as string[];

  return Array.from(new Set([
    'Identified',
    'Under Evaluation',
    'For Implementation',
    'On-going Implementation',
    'Operational',
    'Under Dissolution/Divestment',
    'Shelved Projects',
    ...fromProjects,
  ])).sort((a, b) => a.localeCompare(b));
});

const staffOptions = computed(() => userStore.users.filter((user: any) => user.is_active !== false));
const proponentOptions = computed(() => userStore.users.filter((user: any) => user.is_active !== false && String(user.role?.name || '').toLowerCase() === 'proponent'));

const statusCounts = computed(() => {
  return projects.value.reduce((counts, project) => {
    const key = project.legacy_detail?.detail_status || 'needs_details';
    counts[key] = (counts[key] || 0) + 1;
    return counts;
  }, { needs_details: 0, in_progress: 0, complete: 0 } as Record<string, number>);
});

const archivedCount = computed(() => projects.value.filter((project) => project.is_archived).length);

const legacyStatCards = computed(() => [
  {
    label: 'Total',
    value: pagination.value?.total ?? projects.value.length,
    icon: DatabaseZap,
    colorClass: 'blue',
  },
  {
    label: 'Needs Details',
    value: statusCounts.value.needs_details,
    icon: AlertTriangle,
    colorClass: 'amber',
  },
  {
    label: 'In Progress',
    value: statusCounts.value.in_progress,
    icon: Activity,
    colorClass: 'blue',
  },
  {
    label: 'Complete',
    value: statusCounts.value.complete,
    icon: CheckCircle,
    colorClass: 'green',
  },
  {
    label: 'Archived',
    value: archivedCount.value,
    icon: Archive,
    colorClass: 'slate',
  },
]);

const reviewChecklist = computed(() => {
  const missing: string[] = [];
  if (!editForm.title?.trim()) missing.push('Project title');
  if (!editForm.current_stage_id || !editForm.status_id) missing.push('PMS stage and status');
  if (!editForm.sector_id) missing.push('Sector');
  if (!editForm.estimated_cost && !editForm.ndc_participation) missing.push('Cost or NDC participation amount');
  if (!editForm.proponent_email && !editForm.proponent_user_id) missing.push('Monitoring contact or linked account');

  return { missing };
});

onMounted(async () => {
  await projectStore.loadAllLookupData();
  await loadProponentRole();
  await Promise.all([
    fetchLegacyProjects(),
    userStore.fetchUsers({
      per_page: 100,
      is_active: true,
      role_id: proponentRoleId.value,
      sort_by: 'last_name',
      sort_dir: 'asc',
    } as any).catch(() => undefined),
  ]);
  await openProjectFromRoute();
  window.addEventListener('keydown', handleGlobalKeydown);
});

watch(() => route.query.project_id, () => openProjectFromRoute());

onBeforeUnmount(() => window.removeEventListener('keydown', handleGlobalKeydown));

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement;
  selectedFile.value = input.files?.[0] || null;
  preview.value = null;
}

async function loadProponentRole() {
  try {
    const response = await axiosInstance.get('/api/lookup/roles');
    const roles = response.data?.data || [];
    const role = roles.find((item: any) => String(item.name || '').toLowerCase() === 'proponent');
    proponentRoleId.value = role?.id ?? null;
  } catch {
    proponentRoleId.value = null;
  }
}

async function previewImport() {
  if (!selectedFile.value) return;
  previewLoading.value = true;
  try {
    const payload = new FormData();
    payload.append('file', selectedFile.value);
    const response = await axiosInstance.post('/api/legacy-projects/import/preview', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    preview.value = response.data.data;
    toast.success('Legacy project list preview ready.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to preview the workbook.');
  } finally {
    previewLoading.value = false;
  }
}

async function commitImport() {
  if (!selectedFile.value || !preview.value) return;
  commitLoading.value = true;
  try {
    const payload = new FormData();
    payload.append('file', selectedFile.value);
    const response = await axiosInstance.post('/api/legacy-projects/import/commit', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    toast.success(response.data?.message || 'Legacy projects imported.');
    clearImport();
    await fetchLegacyProjects();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to import legacy projects.');
  } finally {
    commitLoading.value = false;
  }
}

function clearImport() {
  selectedFile.value = null;
  preview.value = null;
  if (fileInput.value) fileInput.value.value = '';
}

async function fetchLegacyProjects() {
  loading.value = true;
  try {
    const params = cleanParams(filters);
    const response = await axiosInstance.get('/api/legacy-projects', { params });
    projects.value = response.data?.data || [];
    pagination.value = response.data?.meta || null;
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to load legacy projects.');
  } finally {
    loading.value = false;
  }
}

function cleanParams(source: LegacyFilters) {
  return Object.fromEntries(
    Object.entries(source)
      .filter(([, value]) => value !== '' && value !== null && value !== undefined)
      .map(([key, value]) => [key, value === 'true' ? true : value === 'false' ? false : value])
  );
}

function applyFilters() {
  filters.page = 1;
  fetchLegacyProjects();
}

function resetFilters() {
  filters.search = '';
  filters.detail_status = '';
  filters.source_status = '';
  filters.status_id = '';
  filters.sector_id = '';
  filters.is_archived = '';
  filters.page = 1;
  fetchLegacyProjects();
}

function goToPage(page: number) {
  filters.page = page;
  fetchLegacyProjects();
}

function openEditor(project: Project) {
  editingProject.value = project;
  editForm.title = project.title;
  editForm.description = project.description || null;
  editForm.date_of_application = project.date_of_application || null;
  editForm.project_type_id = project.project_type_id || null;
  editForm.industry_id = project.industry_id || null;
  editForm.sector_id = project.sector_id || null;
  editForm.investment_type_id = project.investment_type_id || null;
  editForm.funding_source_id = project.funding_source_id || null;
  editForm.estimated_cost = project.estimated_cost ?? null;
  editForm.actual_cost = project.actual_cost ?? null;
  editForm.target_amount_to_raise = project.target_amount_to_raise ?? null;
  editForm.ndc_participation = project.ndc_participation ?? null;
  editForm.ndc_investment_criteria = [...(project.ndc_investment_criteria || [])];
  editForm.ndc_investment_criteria_other = project.ndc_investment_criteria_other || null;
  editForm.project_rationale = project.project_rationale || null;
  editForm.company_background = project.company_background || null;
  editForm.target_beneficiaries = project.target_beneficiaries || null;
  editForm.expected_benefits = project.expected_benefits || null;
  editForm.next_steps = project.next_steps || null;
  editForm.start_date = project.start_date || null;
  editForm.target_completion_date = project.target_completion_date || null;
  editForm.actual_completion_date = project.actual_completion_date || null;
  editForm.location_address = project.location_address || null;
  editForm.proponent_name = project.proponent_name || project.legacy_detail?.source_partner_raw || null;
  editForm.proponent_contact = project.proponent_contact || null;
  editForm.proponent_email = project.proponent_email || null;
  editForm.proponent_user_id = project.proponent_user?.id || null;
  editForm.project_officer_id = project.project_officer_id || null;
  editForm.workgroup_head_id = project.workgroup_head_id || null;
  editForm.current_stage_id = project.current_stage_id;
  editForm.status_id = project.status_id;
  editForm.detail_status = project.legacy_detail?.detail_status || 'needs_details';
  nextTick(() => drawerForm.value?.focus());
}

function closeEditor() {
  editingProject.value = null;
}

async function openProjectFromRoute() {
  const projectId = Number(route.query.project_id);
  if (!projectId) return;

  try {
    const response = await axiosInstance.get(`/api/legacy-projects/${projectId}`);
    openEditor(response.data?.data);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to open the legacy project.');
  } finally {
    await router.replace({ query: { ...route.query, project_id: undefined, tab: undefined } });
  }
}

function openWorkspace(project: Project, tab: string) {
  workspaceProjectId.value = project.id;
  workspaceTab.value = tab;
}

function scrollDrawerSection(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeWorkspace(open: boolean) {
  if (!open) {
    workspaceProjectId.value = null;
    fetchLegacyProjects();
  }
}

function syncLinkedProponent() {
  const user = proponentOptions.value.find((item: any) => Number(item.id) === Number(editForm.proponent_user_id));
  if (!user) {
    editForm.proponent_user_id = null;
    return;
  }
  editForm.proponent_email = user.email;
  editForm.proponent_name = user.organization_name || editForm.proponent_name || displayUser(user);
  if (!editForm.proponent_contact && user.phone_number) editForm.proponent_contact = user.phone_number;
}

function handleGlobalKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && editingProject.value) closeEditor();
}

async function saveProject() {
  if (!editingProject.value) return;
  saving.value = true;
  try {
    const payload = normalizeEditPayload();
    const response = await axiosInstance.patch(`/api/legacy-projects/${editingProject.value.id}`, payload);
    patchProject(response.data?.data);
    editingProject.value = response.data?.data;
    toast.success('Legacy project details saved.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to save legacy project details.');
  } finally {
    saving.value = false;
  }
}

async function markComplete() {
  if (!editingProject.value) return;
  saving.value = true;
  try {
    const response = await axiosInstance.post(`/api/legacy-projects/${editingProject.value.id}/complete`);
    patchProject(response.data?.data);
    editingProject.value = response.data?.data;
    editForm.detail_status = 'complete';
    toast.success('Legacy project marked complete.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to mark legacy project complete.');
  } finally {
    saving.value = false;
  }
}

function normalizeEditPayload() {
  const numberOrNull = (value: string | number | null) => {
    if (value === '' || value === null || value === undefined) return null;
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
  };

  return {
    title: editForm.title,
    description: editForm.description || null,
    date_of_application: editForm.date_of_application || null,
    project_type_id: editForm.project_type_id || null,
    industry_id: editForm.industry_id || null,
    sector_id: editForm.sector_id || null,
    investment_type_id: editForm.investment_type_id || null,
    funding_source_id: editForm.funding_source_id || null,
    estimated_cost: numberOrNull(editForm.estimated_cost),
    actual_cost: numberOrNull(editForm.actual_cost),
    target_amount_to_raise: numberOrNull(editForm.target_amount_to_raise),
    ndc_participation: numberOrNull(editForm.ndc_participation),
    ndc_investment_criteria: editForm.ndc_investment_criteria,
    ndc_investment_criteria_other: editForm.ndc_investment_criteria.includes('others') ? editForm.ndc_investment_criteria_other || null : null,
    project_rationale: editForm.project_rationale || null,
    company_background: editForm.company_background || null,
    target_beneficiaries: editForm.target_beneficiaries || null,
    expected_benefits: editForm.expected_benefits || null,
    next_steps: editForm.next_steps || null,
    start_date: editForm.start_date || null,
    target_completion_date: editForm.target_completion_date || null,
    actual_completion_date: editForm.actual_completion_date || null,
    location_address: editForm.location_address || null,
    proponent_name: editForm.proponent_name || null,
    proponent_contact: editForm.proponent_contact || null,
    proponent_email: editForm.proponent_email || null,
    proponent_user_id: editForm.proponent_user_id || null,
    project_officer_id: editForm.project_officer_id || null,
    workgroup_head_id: editForm.workgroup_head_id || null,
    current_stage_id: editForm.current_stage_id,
    status_id: editForm.status_id,
    detail_status: editForm.detail_status,
  };
}

function patchProject(project?: Project) {
  if (!project) return;
  const index = projects.value.findIndex((item) => item.id === project.id);
  if (index === -1) {
    projects.value.unshift(project);
  } else {
    projects.value[index] = project;
  }
}

function formatCurrency(value: number | string | null | undefined) {
  if (value === null || value === undefined || value === '') return '';
  const number = Number(value);
  if (!Number.isFinite(number)) return '';

  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: number >= 1_000_000 ? 0 : 2,
  }).format(number);
}

function formatDateTime(value?: string | null) {
  if (!value) return 'Unknown';
  return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(new Date(value));
}

function detailStatusLabel(status?: string | null) {
  return {
    needs_details: 'Needs details',
    in_progress: 'In progress',
    complete: 'Complete',
  }[status || 'needs_details'] || 'Needs details';
}

function detailStatusClass(status?: string | null) {
  return {
    needs_details: 'needs',
    in_progress: 'progress',
    complete: 'complete',
  }[status || 'needs_details'] || 'needs';
}

function rowStateLabel(row: LegacyProjectPreviewRow) {
  if (!row.is_importable) return 'Missing title';
  if (row.is_duplicate) return 'Duplicate';
  if (row.parse_warnings.length) return 'With warnings';
  return 'Ready';
}

function rowStateClass(row: LegacyProjectPreviewRow) {
  if (!row.is_importable || row.is_duplicate) return 'needs';
  if (row.parse_warnings.length) return 'progress';
  return 'complete';
}

function displayUser(user: any) {
  return user.full_name || [user.first_name, user.last_name].filter(Boolean).join(' ') || user.email || `User #${user.id}`;
}
</script>

<style scoped>
.legacy-page {
  --l-bg: #e8edf4;
  --l-surface: rgba(255, 255, 255, 0.82);
  --l-surface-solid: #ffffff;
  --l-surface-soft: rgba(248, 250, 252, 0.86);
  --l-border: rgba(148, 163, 184, 0.28);
  --l-border-strong: rgba(100, 116, 139, 0.34);
  --l-text: #0f172a;
  --l-text-soft: #334155;
  --l-muted: #64748b;
  --l-faint: #94a3b8;
  --l-accent: #2563eb;
  --l-accent-soft: rgba(37, 99, 235, 0.09);
  --l-green: #15803d;
  --l-green-soft: #dcfce7;
  --l-amber: #b45309;
  --l-amber-soft: #fef3c7;
  --l-blue-soft: #dbeafe;
  --l-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
  --l-blur: blur(14px);
  min-height: 100%;
  max-width: 100%;
  min-width: 0;
  overflow-x: hidden;
  color: var(--l-text);
  background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 54%, #e5e7eb 100%);
  font-family: inherit;
}

.legacy-page,
.legacy-page *,
.legacy-page *::before,
.legacy-page *::after {
  box-sizing: border-box;
}

:global(.dark) .legacy-page {
  --l-bg: #0f172a;
  --l-surface: rgba(15, 23, 42, 0.74);
  --l-surface-solid: #111827;
  --l-surface-soft: rgba(30, 41, 59, 0.58);
  --l-border: rgba(148, 163, 184, 0.16);
  --l-border-strong: rgba(148, 163, 184, 0.26);
  --l-text: #f8fafc;
  --l-text-soft: #dbeafe;
  --l-muted: #94a3b8;
  --l-faint: #64748b;
  --l-accent: #60a5fa;
  --l-accent-soft: rgba(59, 130, 246, 0.16);
  --l-green: #86efac;
  --l-green-soft: rgba(34, 197, 94, 0.16);
  --l-amber: #fcd34d;
  --l-amber-soft: rgba(245, 158, 11, 0.16);
  --l-blue-soft: rgba(59, 130, 246, 0.18);
  --l-shadow: 0 18px 50px rgba(0, 0, 0, 0.32);
  background: linear-gradient(180deg, #020617 0%, #0f172a 58%, #111827 100%);
}

.legacy-page :where(h1, h2, h3, p, dl) {
  margin: 0;
}

.eyebrow {
  margin-bottom: 0.28rem;
  color: var(--l-accent);
  font-size: 0.68rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.page-header {
  position: relative;
  overflow: hidden;
  padding: 1.35rem clamp(1rem, 2vw, 1.5rem) 0;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 250, 252, 0.9) 65%, rgba(238, 242, 247, 0.88) 100%);
  border-bottom: 1px solid var(--l-border);
}

.page-header::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(100, 116, 139, 0.16) 1px, transparent 0);
  background-size: 28px 28px;
  pointer-events: none;
}

:global(.dark) .page-header {
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.96) 0%, rgba(17, 24, 39, 0.94) 100%);
}

:global(.dark) .page-header::before {
  background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.09) 1px, transparent 0);
}

.header-content,
.stats-row {
  position: relative;
  z-index: 1;
}

.header-content,
.import-head,
.filters-bar,
.pagination,
.detail-drawer header,
.detail-drawer footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.header-content {
  margin-bottom: 1rem;
}

.page-title {
  color: var(--l-text);
  font-size: clamp(1.75rem, 2.4vw, 2.25rem);
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.05;
}

.page-subtitle {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
  margin-top: 0.55rem;
}

.stat-pill,
.state-pill {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  border-radius: 999px;
  font-weight: 900;
  line-height: 1.2;
  white-space: nowrap;
}

.stat-pill {
  border: 1px solid var(--l-border);
  background: var(--l-surface-soft);
  color: var(--l-text-soft);
  padding: 0.24rem 0.6rem;
  font-size: 0.72rem;
}

.stat-pill.active {
  border-color: rgba(34, 197, 94, 0.26);
  background: var(--l-green-soft);
  color: var(--l-green);
}

.stat-pill.warning {
  border-color: rgba(245, 158, 11, 0.32);
  background: var(--l-amber-soft);
  color: var(--l-amber);
}

.header-actions,
.import-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.55rem;
}

.primary-btn,
.secondary-btn,
.icon-btn,
.btn-export,
.btn-create,
.inline-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 2.25rem;
  border-radius: 0.48rem;
  font-size: 0.8rem;
  font-weight: 850;
  transition: background 0.16s ease, border-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.primary-btn,
.btn-create {
  border: 1px solid #2563eb;
  background: #2563eb;
  color: #ffffff;
  padding: 0 0.95rem;
}

.primary-btn:hover:not(:disabled),
.btn-create:hover:not(:disabled) {
  background: #1d4ed8;
  transform: translateY(-1px);
  box-shadow: 0 7px 18px rgba(37, 99, 235, 0.24);
}

.secondary-btn,
.btn-export,
.icon-btn {
  border: 1px solid var(--l-border);
  background: var(--l-surface-soft);
  color: var(--l-text-soft);
  padding: 0 0.75rem;
}

.secondary-btn:hover:not(:disabled),
.btn-export:hover:not(:disabled),
.icon-btn:hover:not(:disabled) {
  border-color: var(--l-accent);
  background: var(--l-accent-soft);
  color: var(--l-accent);
}

.primary-btn svg,
.secondary-btn svg,
.icon-btn svg,
.btn-icon,
.inline-link svg,
.search-field svg,
.warning-list svg {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
.detail-drawer:focus-visible {
  outline: 2px solid var(--l-accent);
  outline-offset: 2px;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  border-top: 1px solid var(--l-border);
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  min-width: 0;
  padding: 0.8rem 1rem;
  border-right: 1px solid var(--l-border);
}

.stat-card:last-child {
  border-right: 0;
}

.stat-icon {
  display: flex;
  width: 2rem;
  height: 2rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 0.55rem;
}

.stat-icon .icon {
  width: 1rem;
  height: 1rem;
}

.stat-icon.blue { background: var(--l-blue-soft); color: var(--l-accent); }
.stat-icon.green { background: var(--l-green-soft); color: var(--l-green); }
.stat-icon.amber { background: var(--l-amber-soft); color: var(--l-amber); }
.stat-icon.slate { background: rgba(100, 116, 139, 0.14); color: var(--l-muted); }

.stat-info {
  display: grid;
  min-width: 0;
  gap: 0.12rem;
}

.stat-value {
  color: var(--l-text);
  font-size: 1.18rem;
  font-weight: 900;
  line-height: 1;
}

.stat-label,
.preview-stats span,
dt {
  color: var(--l-muted);
  font-size: 0.64rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.import-panel,
.workspace {
  overflow: hidden;
  width: auto;
  min-width: 0;
  max-width: none;
  margin: 1rem clamp(1rem, 2vw, 1.5rem) 0;
  border: 1px solid var(--l-border);
  border-radius: 0.65rem;
  background: var(--l-surface);
  box-shadow: var(--l-shadow);
  backdrop-filter: var(--l-blur);
  -webkit-backdrop-filter: var(--l-blur);
}

.workspace {
  margin-bottom: 2.5rem;
}

.import-head,
.filters-bar,
.pagination {
  border-color: var(--l-border);
  background: var(--l-surface-soft);
}

.import-head {
  padding: 0.8rem 1rem;
  border-bottom: 1px solid var(--l-border);
}

.import-head h2,
.detail-drawer h2 {
  color: var(--l-text);
  font-size: 1rem;
  font-weight: 900;
}

.import-head p,
.preview-note,
.empty-cell {
  color: var(--l-muted);
  font-size: 0.78rem;
}

.preview-body {
  display: grid;
  gap: 0.85rem;
  padding: 1rem;
}

.preview-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.6rem;
}

.preview-stats > div,
.form-section,
.review-summary,
.source-panel {
  border: 1px solid var(--l-border);
  border-radius: 0.6rem;
  background: var(--l-surface-solid);
}

.preview-stats > div {
  display: grid;
  gap: 0.16rem;
  padding: 0.7rem;
}

.preview-stats strong {
  color: var(--l-text);
  font-size: 1.15rem;
  font-weight: 900;
}

.warning-list {
  display: flex;
  gap: 0.65rem;
  border: 1px solid rgba(245, 158, 11, 0.32);
  border-radius: 0.6rem;
  background: var(--l-amber-soft);
  color: var(--l-amber);
  padding: 0.75rem;
  font-size: 0.8rem;
}

.filters-bar {
  display: grid;
  grid-template-columns: minmax(18rem, 1.5fr) repeat(auto-fit, minmax(10.5rem, 1fr));
  align-items: end;
  justify-content: stretch;
  padding: 0.8rem;
  border-bottom: 1px solid var(--l-border);
}

.search-field,
.filter-field {
  position: relative;
  display: grid;
  width: 100%;
  min-width: 0;
  gap: 0.32rem;
}

.search-field > span,
.filter-field > span {
  color: var(--l-muted);
  font-size: 0.64rem;
  font-weight: 900;
  letter-spacing: 0.055em;
  text-transform: uppercase;
}

.search-field svg {
  position: absolute;
  left: 0.7rem;
  bottom: 0.64rem;
  color: var(--l-faint);
  pointer-events: none;
}

.search-field input,
.filters-bar select,
.form-grid input,
.form-grid select,
.form-grid textarea {
  width: 100%;
  border: 1px solid var(--l-border);
  border-radius: 0.48rem;
  background: var(--l-surface-solid);
  color: var(--l-text);
  font-size: 0.8rem;
  accent-color: var(--l-accent);
}

.search-field input {
  min-height: 2.28rem;
  padding: 0 0.75rem 0 2rem;
}

.filters-bar select {
  width: 100%;
  min-width: 0;
  min-height: 2.28rem;
  padding: 0 2rem 0 0.75rem;
  text-overflow: ellipsis;
}

.filter-actions {
  display: flex;
  align-items: end;
  justify-content: flex-end;
  gap: 0.45rem;
  min-width: 0;
}

.filter-actions .secondary-btn {
  min-width: 5.4rem;
  white-space: nowrap;
}

.search-field input::placeholder {
  color: var(--l-faint);
}

.table-wrap {
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  scrollbar-color: var(--l-faint) transparent;
  scrollbar-width: thin;
}

.table-wrap caption {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.table-wrap table {
  width: 100%;
  min-width: 1040px;
  border-collapse: collapse;
  table-layout: fixed;
}

.table-wrap th,
.table-wrap td {
  border-bottom: 1px solid var(--l-border);
  text-align: left;
  vertical-align: middle;
}

.table-wrap th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: 0.62rem 0.75rem;
  background: rgba(248, 250, 252, 0.58);
  color: var(--l-muted);
  font-size: 0.66rem;
  font-weight: 760;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}

:global(.dark) .table-wrap th {
  background: rgba(15, 23, 42, 0.52);
}

.table-wrap td {
  padding: 0.62rem 0.75rem;
  color: var(--l-text-soft);
  font-size: 0.76rem;
}

.table-wrap tbody tr {
  transition: background 0.14s ease, box-shadow 0.14s ease;
}

.table-wrap tbody tr:hover {
  background: color-mix(in srgb, var(--l-accent-soft) 62%, transparent);
}

.clickable-row {
  cursor: pointer;
}

.clickable-row:focus-visible {
  outline: 2px solid var(--l-accent);
  outline-offset: -2px;
}

.table-wrap td:nth-child(1), .table-wrap th:nth-child(1) { width: 21%; }
.table-wrap td:nth-child(2), .table-wrap th:nth-child(2) { width: 15%; }
.table-wrap td:nth-child(3), .table-wrap th:nth-child(3) { width: 12%; }
.table-wrap td:nth-child(4), .table-wrap th:nth-child(4) { width: 15%; }
.table-wrap td:nth-child(5), .table-wrap th:nth-child(5) { width: 13%; }
.table-wrap td:nth-child(6), .table-wrap th:nth-child(6) { width: 16%; }
.table-wrap td:nth-child(7), .table-wrap th:nth-child(7) { width: 8%; }

.table-wrap strong,
.stacked b {
  display: block;
  overflow-wrap: anywhere;
  color: var(--l-text);
  font-size: 0.79rem;
  font-weight: 760;
  line-height: 1.28;
}

.title-link {
  display: block;
  max-width: 100%;
  border: 0;
  background: transparent;
  color: var(--l-text);
  padding: 0;
  text-align: left;
  font: inherit;
  font-size: 0.79rem;
  font-weight: 760;
  line-height: 1.28;
  cursor: pointer;
  overflow-wrap: anywhere;
}

.title-link:hover {
  color: var(--l-accent);
  text-decoration: none;
}

.table-wrap small,
.stacked small {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.18rem;
  color: var(--l-muted);
  font-size: 0.68rem;
  font-weight: 560;
  line-height: 1.25;
}

.state-pill {
  padding: 0.24rem 0.52rem;
  font-size: 0.65rem;
  font-weight: 760;
}

.state-pill.needs { background: var(--l-amber-soft); color: var(--l-amber); }
.state-pill.progress { background: var(--l-blue-soft); color: var(--l-accent); }
.state-pill.complete { background: var(--l-green-soft); color: var(--l-green); }

.icon-btn {
  width: 2.25rem;
  padding: 0;
}

.account-dot {
  width: 0.45rem;
  height: 0.45rem;
  flex: 0 0 auto;
  border-radius: 999px;
  background: #f59e0b;
}

.account-dot.linked {
  background: #16a34a;
}

.account-line {
  display: inline-flex;
  max-width: 100%;
  align-items: center;
  gap: 0.35rem;
}

.empty-cell {
  height: 8rem;
  text-align: center;
  vertical-align: middle;
}

.pagination {
  justify-content: center;
  padding: 0.75rem;
  color: var(--l-muted);
  font-size: 0.78rem;
}

.drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  justify-content: flex-end;
  background: rgba(15, 23, 42, 0.46);
}

.detail-drawer {
  display: flex;
  width: min(780px, 100vw);
  height: 100%;
  flex-direction: column;
  overflow: hidden;
  background: var(--l-bg);
  color: var(--l-text);
  box-shadow: -22px 0 50px rgba(15, 23, 42, 0.24);
}

.detail-drawer header,
.detail-drawer footer {
  flex: 0 0 auto;
  border-color: var(--l-border);
  background: var(--l-surface);
  padding: 0.85rem 1rem;
  backdrop-filter: var(--l-blur);
  -webkit-backdrop-filter: var(--l-blur);
}

.detail-drawer header {
  border-bottom: 1px solid var(--l-border);
}

.detail-drawer footer {
  justify-content: flex-end;
  border-top: 1px solid var(--l-border);
}

.drawer-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  padding: 1rem;
  scrollbar-color: var(--l-faint) transparent;
  scrollbar-width: thin;
}

.review-summary,
.source-panel,
.form-section {
  margin-bottom: 0.85rem;
  padding: 0.9rem;
}

.review-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  border-left: 3px solid #f59e0b;
  background: var(--l-amber-soft);
}

.review-summary.ready {
  border-left-color: #16a34a;
  background: var(--l-green-soft);
}

.summary-label {
  color: var(--l-muted);
  font-size: 0.65rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.review-summary strong {
  display: block;
  margin-top: 0.16rem;
  color: var(--l-text);
  font-size: 0.9rem;
  font-weight: 900;
}

.review-summary p,
.form-grid small {
  margin-top: 0.16rem;
  color: var(--l-muted);
  font-size: 0.75rem;
}

.missing-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin: 0.55rem 0 0;
  padding: 0;
  list-style: none;
}

.missing-list li {
  border: 1px solid rgba(180, 83, 9, 0.26);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.54);
  color: var(--l-amber);
  padding: 0.2rem 0.48rem;
  font-size: 0.66rem;
  font-weight: 850;
}

.review-nav {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  gap: 0.4rem;
  margin: -0.2rem 0 0.85rem;
  padding: 0.45rem;
  border: 1px solid var(--l-border);
  border-radius: 0.6rem;
  background: color-mix(in srgb, var(--l-surface-solid) 88%, transparent);
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
  overflow-x: auto;
  backdrop-filter: var(--l-blur);
  -webkit-backdrop-filter: var(--l-blur);
}

.review-nav button {
  flex: 1 0 auto;
  border: 1px solid transparent;
  border-radius: 0.45rem;
  background: transparent;
  color: var(--l-text-soft);
  padding: 0.42rem 0.62rem;
  font: inherit;
  font-size: 0.72rem;
  font-weight: 850;
  cursor: pointer;
  white-space: nowrap;
}

.review-nav button:hover,
.review-nav button:focus-visible {
  border-color: var(--l-border);
  background: var(--l-accent-soft);
  color: var(--l-accent);
}

.form-grid .linked-help {
  color: var(--l-green);
  font-weight: 800;
}

.source-panel {
  background: var(--l-accent-soft);
}

.source-panel dl {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.source-panel .wide,
.form-grid .wide {
  grid-column: 1 / -1;
}

dd {
  margin: 0.16rem 0 0;
  overflow-wrap: anywhere;
  color: var(--l-text);
  font-size: 0.78rem;
  font-weight: 850;
}

.source-warnings dd {
  color: var(--l-amber);
}

.section-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.8rem;
}

.section-heading h3 {
  color: var(--l-text);
  font-size: 0.95rem;
  font-weight: 900;
}

.inline-link {
  min-height: auto;
  border: 0;
  background: transparent;
  color: var(--l-accent);
  padding: 0;
  white-space: nowrap;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
}

.form-grid label,
.criteria-field {
  display: grid;
  gap: 0.35rem;
}

.form-grid span,
.criteria-field > span {
  color: var(--l-text-soft);
  font-size: 0.72rem;
  font-weight: 900;
}

.form-grid input,
.form-grid select {
  min-height: 2.38rem;
  padding: 0 0.72rem;
}

.form-grid textarea {
  min-height: 5.25rem;
  padding: 0.65rem 0.72rem;
  resize: vertical;
}

.criteria-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.criteria-options label {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  border: 1px solid var(--l-border);
  border-radius: 0.48rem;
  background: var(--l-surface-soft);
  color: var(--l-text-soft);
  padding: 0.42rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 850;
}

.criteria-options input {
  width: 0.9rem;
  height: 0.9rem;
  accent-color: var(--l-accent);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@supports not (scrollbar-color: auto) {
  .table-wrap::-webkit-scrollbar,
  .drawer-body::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  .table-wrap::-webkit-scrollbar-thumb,
  .drawer-body::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: var(--l-faint);
  }
}

@media (max-width: 1180px) {
  .stats-row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .filters-bar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .search-field {
    grid-column: 1 / -1;
  }

  .filter-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  .page-header {
    padding: 1rem 1rem 0;
  }

  .header-content,
  .import-head,
  .review-summary,
  .section-heading {
    align-items: stretch;
    flex-direction: column;
  }

  .header-actions,
  .import-actions {
    justify-content: stretch;
  }

  .btn-export,
  .btn-create,
  .primary-btn,
  .secondary-btn {
    flex: 1 1 auto;
  }

  .stats-row,
  .preview-stats,
  .source-panel dl,
  .form-grid {
    grid-template-columns: 1fr;
  }

  .stat-card {
    border-right: 0;
    border-bottom: 1px solid var(--l-border);
    padding: 0.75rem 0;
  }

  .stat-card:last-child {
    border-bottom: 0;
  }

  .import-panel,
  .workspace {
    margin-inline: 1rem;
  }

  .filters-bar {
    grid-template-columns: 1fr;
  }

  .search-field {
    grid-column: auto;
  }

  .filter-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    width: 100%;
  }

  .filter-actions .secondary-btn {
    width: 100%;
    min-width: 0;
  }

  .detail-drawer footer {
    flex-wrap: wrap;
  }

  .legacy-table {
    overflow-x: visible;
  }

  .legacy-table table,
  .legacy-table thead,
  .legacy-table tbody,
  .legacy-table tr,
  .legacy-table td {
    display: block;
    width: 100%;
    min-width: 0;
  }

  .legacy-table td:nth-child(n),
  .legacy-table th:nth-child(n) {
    width: 100%;
  }

  .legacy-table table {
    border-collapse: separate;
    border-spacing: 0;
  }

  .legacy-table thead {
    display: none;
  }

  .legacy-table tbody {
    display: grid;
    gap: 0.75rem;
    padding: 0.75rem;
  }

  .legacy-table tbody tr {
    border: 1px solid var(--l-border);
    border-radius: 0.65rem;
    background: var(--l-surface-solid);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }

  .legacy-table td {
    display: grid;
    grid-template-columns: minmax(6.5rem, 38%) minmax(0, 1fr);
    gap: 0.75rem;
    align-items: start;
    border-bottom: 1px solid var(--l-border);
    padding: 0.72rem;
  }

  .legacy-table td:last-child {
    border-bottom: 0;
  }

  .legacy-table td::before {
    content: attr(data-label);
    color: var(--l-muted);
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.055em;
    text-transform: uppercase;
  }

  .legacy-table .empty-cell {
    display: block;
    height: auto;
    text-align: center;
  }

  .legacy-table .empty-cell::before {
    content: none;
  }
}
</style>
