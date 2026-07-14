<template>
  <main class="monitoring-page" :class="{ 'is-dark': isDark }">
    <header class="page-head">
      <div>
        <p class="eyebrow">SOI-02 Portfolio Compliance</p>
        <h1>{{ isExternalProponent ? 'Monitoring Compliance' : 'Implementation Monitoring' }}</h1>
        <p v-if="isExternalProponent">Input the requested project results, save your progress, and submit the completed compliance report to NDC.</p>
        <p v-else>Prepare, review, return, accept, and close monitoring reports without leaving this workspace.</p>
      </div>
      <div class="page-actions">
        <button class="button secondary" type="button" :disabled="exporting" @click="exportMonitoring">
          <Download aria-hidden="true" /> {{ exporting ? 'Preparing Excel...' : 'Export Excel' }}
        </button>
        <button class="button secondary" type="button" :disabled="loading" @click="loadReports">
          <RefreshCw :class="{ spin: loading }" aria-hidden="true" /> Refresh
        </button>
      </div>
    </header>

    <section class="summary-grid" aria-label="Monitoring summary">
      <article v-for="item in summaryCards" :key="item.label">
        <span class="summary-icon" :class="item.tone"><component :is="item.icon" /></span>
        <div><strong>{{ item.value }}</strong><span>{{ item.label }}</span></div>
      </article>
    </section>

    <section class="toolbar" aria-label="Monitoring filters">
      <label class="search-box">
        <Search aria-hidden="true" />
        <span class="sr-only">Search monitoring projects</span>
        <input v-model="search" placeholder="Search project, code, or proponent" @keyup.enter="applyFilters" />
      </label>
      <label><span class="sr-only">Sort projects</span><select v-model="sortBy" @change="applyFilters"><option value="status">Priority status</option><option value="due_date">Due date</option><option value="submitted_at">Latest submission</option><option value="title">Project title</option></select></label>
      <label class="overdue-toggle"><input v-model="overdueOnly" type="checkbox" @change="applyFilters" /> Overdue only</label>
      <button class="button primary" type="button" @click="applyFilters">Apply</button>
    </section>

    <div v-if="loading && !reports.length" class="state-card">Loading implementation monitoring workspace...</div>
    <div v-else-if="!reports.length" class="state-card">
      <ClipboardCheck aria-hidden="true" />
      <strong>No monitoring reports found</strong>
      <span v-if="isExternalProponent">NDC has not opened a monitoring submission for your projects.</span>
      <span v-else>Open a monitoring period from an eligible implementation project to begin.</span>
    </div>

    <template v-else>
      <section class="portfolio-register" aria-label="Monitoring compliance register">
        <header class="register-head">
          <div>
            <h2>Compliance register</h2>
            <p>Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} monitoring projects</p>
          </div>
          <div class="status-tabs" aria-label="Filter by report status">
            <button
              v-for="tab in statusTabs"
              :key="tab.value"
              type="button"
              :class="{ active: status === tab.value }"
              :aria-pressed="status === tab.value"
              @click="setStatus(tab.value)"
            >{{ tab.label }} <span>{{ tab.count }}</span></button>
          </div>
        </header>

        <div class="register-table-wrap">
          <table class="register-table">
            <thead><tr><th>Project</th><th>Proponent</th><th>Period</th><th>Due</th><th>Status</th><th>Jobs</th><th>Actual revenue</th><th>Project officer</th><th><span class="sr-only">Action</span></th></tr></thead>
            <tbody>
              <tr v-for="project in reports" :key="project.id" :class="{ selected: selectedProject?.id === project.id }">
                <td><button class="project-link" type="button" @click="selectProject(project)"><span>{{ project.project_code }}</span><strong>{{ project.title }}</strong></button></td>
                <td><strong>{{ project.proponent_name || project.proponent_user?.organization_name || 'Not recorded' }}</strong><span>{{ project.proponent_email || project.proponent_user?.email || '' }}</span></td>
                <td><strong>{{ project.financial_metrics?.reporting_period || 'Not supplied' }}</strong><span>{{ project.financial_metrics?.monitoring_frequency || 'No frequency' }}</span></td>
                <td :class="{ 'overdue-cell': isOverdue(project) }"><strong>{{ formatDate(project.monitoring_due_date) }}</strong><span>{{ dueLabel(project) }}</span></td>
                <td><span class="status-badge" :class="normalizedStatus(project)">{{ statusLabel(project.monitoring_submission_status) }}</span></td>
                <td><strong>{{ totalJobs(project).toLocaleString() }}</strong><span>reported</span></td>
                <td><strong>{{ money(project.financial_metrics?.actual_revenue) }}</strong><span>{{ varianceLabel(project) }}</span></td>
                <td><strong>{{ project.project_officer?.full_name || 'Unassigned' }}</strong><span>{{ project.current_stage?.name || '' }}</span></td>
                <td><button class="open-report" type="button" :aria-label="`Open ${project.project_code} report`" @click="selectProject(project)"><ChevronRight /></button></td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer class="register-footer">
          <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <div>
            <button class="icon-button bordered" type="button" aria-label="Previous page" :disabled="pagination.current_page <= 1 || loading" @click="changePage(pagination.current_page - 1)"><ChevronLeft /></button>
            <button class="icon-button bordered" type="button" aria-label="Next page" :disabled="pagination.current_page >= pagination.last_page || loading" @click="changePage(pagination.current_page + 1)"><ChevronRight /></button>
          </div>
        </footer>
      </section>

      <article v-if="selectedProject" id="selected-monitoring-report" class="report-workspace">
        <header class="report-head">
          <div>
            <div class="report-kicker">
              <span class="project-code">{{ selectedProject.project_code }}</span>
              <span class="status-badge" :class="selectedStatus">{{ statusLabel(selectedStatus) }}</span>
              <span v-if="isOverdue(selectedProject)" class="overdue-badge">Overdue</span>
            </div>
            <h2>{{ selectedProject.title }}</h2>
            <p>{{ selectedProject.monitoring_instructions || 'No special reporting instructions were provided.' }}</p>
          </div>
          <div class="report-actions">
            <button v-if="canEdit && !editing" class="button secondary" type="button" @click="startEdit">
              <Pencil aria-hidden="true" /> Edit report
            </button>
            <template v-if="editing">
              <button class="button secondary" type="button" :disabled="saving" @click="cancelEdit">Cancel</button>
              <button class="button secondary" type="button" :disabled="saving" @click="saveReport(false)">
                <Save aria-hidden="true" /> Save draft
              </button>
              <button v-if="canSubmit" class="button primary" type="button" :disabled="saving" @click="submitReport">
                <Send aria-hidden="true" /> Submit to NDC
              </button>
            </template>
            <button v-if="canClose" class="button danger" type="button" :disabled="saving" @click="closePeriod">Close period</button>
            <button v-if="canOpenCycle" class="button primary" type="button" @click="showActivation = !showActivation">Open new period</button>
          </div>
        </header>

        <section class="cycle-strip" aria-label="Monitoring cycle details">
          <div><span>Due date</span><strong>{{ formatDate(selectedProject.monitoring_due_date) }}</strong></div>
          <div><span>Reporting period</span><strong>{{ form.reporting_period || 'Not supplied' }}</strong></div>
          <div><span>Submitted by</span><strong>{{ selectedProject.monitoring_submitted_by?.full_name || 'Not submitted' }}</strong></div>
          <div><span>Reviewed by</span><strong>{{ selectedProject.monitoring_reviewed_by?.full_name || 'Not reviewed' }}</strong></div>
        </section>

        <form v-if="showActivation" class="activation-panel" @submit.prevent="activatePeriod">
          <div class="section-heading">
            <div><p class="eyebrow">New compliance cycle</p><h3>Request a monitoring submission</h3></div>
            <button class="icon-button" type="button" aria-label="Close activation form" @click="showActivation = false"><X /></button>
          </div>
          <div class="form-grid two">
            <label><span>Due date</span><input v-model="activation.due_date" type="date" required /></label>
            <label class="check-field"><input v-model="activation.proponent_access" type="checkbox" /> Allow proponent submission</label>
            <label class="full"><span>Instructions</span><textarea v-model="activation.instructions" rows="3" required placeholder="State the reporting period, evidence, and expected outcomes."></textarea></label>
          </div>
          <div class="panel-actions"><button class="button primary" type="submit" :disabled="saving">Open period and notify proponent</button></div>
        </form>

        <section v-if="selectedStatus === 'returned'" class="notice returned">
          <RotateCcw aria-hidden="true" />
          <div><strong>Returned for correction</strong><p>{{ selectedProject.monitoring_review_notes || 'Update the report using NDC review guidance, then resubmit.' }}</p></div>
        </section>
        <section v-else-if="selectedStatus === 'submitted'" class="notice submitted">
          <Clock3 aria-hidden="true" />
          <div><strong>Submitted for NDC review</strong><p>The report is locked for the proponent until NDC accepts or returns it.</p></div>
        </section>
        <section v-else-if="selectedStatus === 'accepted'" class="notice accepted">
          <CheckCircle2 aria-hidden="true" />
          <div><strong>Accepted by NDC</strong><p>The accepted indicators are now part of the project's monitoring record.</p></div>
        </section>

        <form class="report-form" @submit.prevent="saveReport(false)">
          <section class="form-section">
            <div class="section-heading"><div><p class="eyebrow">Employment outcomes</p><h3>Jobs generated and retained</h3></div><Briefcase aria-hidden="true" /></div>
            <div class="form-grid three">
              <MetricInput v-model="form.jobs_generated_direct" label="Direct jobs" :disabled="!editing" />
              <MetricInput v-model="form.jobs_generated_indirect" label="Indirect jobs" :disabled="!editing" />
              <MetricInput v-model="form.retained_jobs" label="Retained jobs" :disabled="!editing" />
              <MetricInput v-model="form.jobs_direct_male" label="Direct jobs - male" :disabled="!editing" />
              <MetricInput v-model="form.jobs_direct_female" label="Direct jobs - female" :disabled="!editing" />
              <MetricInput v-model="form.jobs_indirect_male" label="Indirect jobs - male" :disabled="!editing" />
              <MetricInput v-model="form.jobs_indirect_female" label="Indirect jobs - female" :disabled="!editing" />
              <MetricInput v-model="form.jobs_retained_male" label="Retained jobs - male" :disabled="!editing" />
              <MetricInput v-model="form.jobs_retained_female" label="Retained jobs - female" :disabled="!editing" />
            </div>
          </section>

          <section class="form-section">
            <div class="section-heading"><div><p class="eyebrow">Financial performance</p><h3>Revenue and remittance</h3></div><DollarSign aria-hidden="true" /></div>
            <div class="form-grid three">
              <MetricInput v-model="form.projected_revenue" label="Projected revenue" :disabled="!editing" step="0.01" />
              <MetricInput v-model="form.actual_revenue" label="Actual revenue" :disabled="!editing" step="0.01" />
              <MetricInput v-model="form.dividend_remittance" label="Dividend / remittance" :disabled="!editing" step="0.01" />
            </div>
          </section>

          <section class="form-section">
            <div class="section-heading"><div><p class="eyebrow">Monitoring narrative</p><h3>Period, milestones, and impact</h3></div><Activity aria-hidden="true" /></div>
            <div class="form-grid two">
              <label><span>Monitoring frequency</span><select v-model="form.monitoring_frequency" :disabled="!editing"><option value="">Select frequency</option><option>Monthly</option><option>Quarterly</option><option>Semi-annual</option><option>Annual</option></select></label>
              <label><span>Reporting period</span><input v-model="form.reporting_period" :disabled="!editing" placeholder="e.g. Q2 2026 or FY 2026" /></label>
              <label class="full"><span>Implementation milestones and indicators</span><textarea v-model="form.monitoring_indicators" :disabled="!editing" rows="4" placeholder="Progress, completed milestones, schedule issues, covenants, and next actions"></textarea></label>
              <label class="full"><span>Social and development impact</span><textarea v-model="form.social_impact_notes" :disabled="!editing" rows="4" placeholder="Beneficiaries, inclusion, regional impact, and other outcomes"></textarea></label>
            </div>
          </section>

          <section v-if="!isExternalProponent" class="form-section">
            <div class="section-heading"><div><p class="eyebrow">NDC review fields</p><h3>GCG classification and reportability</h3></div><ShieldCheck aria-hidden="true" /></div>
            <div class="form-grid three">
              <label class="check-field"><input v-model="form.gcg_relevance" type="checkbox" :disabled="!editing" /> GCG relevant</label>
              <label class="check-field"><input v-model="form.reportable_to_gcg" type="checkbox" :disabled="!editing" /> Reportable to GCG</label>
              <MetricInput v-model="form.gcg_score" label="GCG score" :disabled="!editing" step="0.01" />
              <label class="full"><span>GCG metrics and evidence</span><textarea v-model="form.gcg_metrics" :disabled="!editing" rows="3"></textarea></label>
            </div>
          </section>
        </form>

        <section v-if="canReview" class="review-panel">
          <div class="section-heading"><div><p class="eyebrow">NDC decision</p><h3>Review submitted report</h3></div><ClipboardCheck aria-hidden="true" /></div>
          <label><span>Review remarks</span><textarea v-model="reviewRemarks" rows="3" placeholder="Required when returning the report for correction"></textarea></label>
          <div class="panel-actions">
            <button class="button danger" type="button" :disabled="saving" @click="reviewReport('returned')">Return for correction</button>
            <button class="button primary" type="button" :disabled="saving" @click="reviewReport('accepted')">Accept report</button>
          </div>
        </section>
      </article>
    </template>
  </main>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Activity, Briefcase, CalendarClock, CheckCircle2, ChevronLeft, ChevronRight,
  ClipboardCheck, Clock3, DollarSign, Download, Pencil, RefreshCw, RotateCcw,
  Save, Search, Send, ShieldCheck, X,
} from 'lucide-vue-next';
import axiosInstance from '@/utils/axiosInstance';
import type { Project, ProjectFinancialMetrics } from '@/types/project';
import { useLayoutStore } from '@/store/layout';
import { useAuthStore } from '@/store/auth';
import { SITE_MODE } from '@/app/const';
import { toast } from 'vue3-toastify';

const MetricInput = defineComponent({
  props: { modelValue: [Number, String], label: { type: String, required: true }, disabled: Boolean, step: { type: String, default: '1' } },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => h('label', [
      h('span', props.label),
      h('input', {
        type: 'number', min: 0, step: props.step, disabled: props.disabled,
        value: props.modelValue ?? '',
        onInput: (event: Event) => emit('update:modelValue', (event.target as HTMLInputElement).value === '' ? null : Number((event.target as HTMLInputElement).value)),
      }),
    ]);
  },
});

const reports = ref<Project[]>([]);
const loading = ref(false);
const saving = ref(false);
const exporting = ref(false);
const editing = ref(false);
const showActivation = ref(false);
const search = ref('');
const status = ref('');
const sortBy = ref('status');
const overdueOnly = ref(false);
const page = ref(1);
const summary = ref({ total: 0, active: 0, submitted: 0, returned: 0, draft: 0, accepted: 0, overdue: 0, due_soon: 0 });
const pagination = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });
const selectedProject = ref<Project | null>(null);
const reviewRemarks = ref('');
const route = useRoute();
const router = useRouter();
const layoutStore = useLayoutStore();
const authStore = useAuthStore();
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK);
const role = computed(() => authStore.userRole.toLowerCase());
const isExternalProponent = computed(() => role.value === 'proponent');
const isManager = computed(() => ['superadmin', 'admin'].includes(role.value)
  || authStore.can('projects', 'update')
  || authStore.hasPermission('documents.review'));

const emptyMetrics = (): ProjectFinancialMetrics => ({
  jobs_generated_direct: null, jobs_generated_indirect: null, retained_jobs: null,
  jobs_direct_male: null, jobs_direct_female: null, jobs_indirect_male: null,
  jobs_indirect_female: null, jobs_retained_male: null, jobs_retained_female: null,
  projected_revenue: null, actual_revenue: null, dividend_remittance: null,
  gcg_relevance: false, gcg_score: null, reportable_to_gcg: false, is_reportable: false,
  monitoring_frequency: '', reporting_period: '', monitoring_indicators: '', gcg_metrics: '', social_impact_notes: '',
});
const form = ref<ProjectFinancialMetrics>(emptyMetrics());
const activation = ref({ due_date: '', instructions: '', proponent_access: true });
const normalizeStatus = (value?: string) => value === 'approved' ? 'accepted' : (value || 'not_requested');
const normalizedStatus = (project: Project) => normalizeStatus(project.monitoring_submission_status);
const selectedStatus = computed(() => selectedProject.value ? normalizedStatus(selectedProject.value) : 'not_requested');
const canEdit = computed(() => Boolean(selectedProject.value
  && selectedProject.value.monitoring_status === 'active'
  && (isManager.value || (isExternalProponent.value
    && selectedProject.value.monitoring_proponent_access
    && ['draft', 'returned'].includes(selectedStatus.value)))));
const canSubmit = computed(() => isExternalProponent.value && canEdit.value && ['draft', 'returned'].includes(selectedStatus.value));
const canReview = computed(() => isManager.value && selectedProject.value?.monitoring_status === 'active' && selectedStatus.value === 'submitted');
const canClose = computed(() => isManager.value && selectedProject.value?.monitoring_status === 'active' && selectedStatus.value === 'accepted');
const canOpenCycle = computed(() => isManager.value && selectedProject.value?.monitoring_status !== 'active');

const summaryCards = computed(() => [
  { label: 'Active compliance', value: summary.value.active, icon: Activity, tone: 'blue' },
  { label: 'Awaiting review', value: summary.value.submitted, icon: Clock3, tone: 'violet' },
  { label: 'Due within 14 days', value: summary.value.due_soon, icon: CalendarClock, tone: 'amber' },
  { label: 'Overdue', value: summary.value.overdue, icon: RotateCcw, tone: 'red' },
]);
const statusTabs = computed(() => [
  { value: '', label: 'All', count: summary.value.total },
  { value: 'submitted', label: 'Needs review', count: summary.value.submitted },
  { value: 'returned', label: 'Returned', count: summary.value.returned },
  { value: 'draft', label: 'Draft', count: summary.value.draft },
  { value: 'accepted', label: 'Accepted', count: summary.value.accepted },
]);

function resetForm() {
  form.value = { ...emptyMetrics(), ...(selectedProject.value?.financial_metrics || {}) };
}
async function selectProject(project: Project, reveal = true) {
  selectedProject.value = project;
  editing.value = false;
  showActivation.value = false;
  reviewRemarks.value = '';
  resetForm();
  router.replace({ query: { ...route.query, project_id: String(project.id) } });
  if (reveal) {
    await nextTick();
    document.getElementById('selected-monitoring-report')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
async function loadReports() {
  loading.value = true;
  try {
    const response = await axiosInstance.get('/api/post-monitoring', { params: {
      page: page.value, per_page: 25, search: search.value || undefined,
      submission_status: status.value || undefined, overdue: overdueOnly.value ? 1 : undefined,
      sort_by: sortBy.value,
    } });
    reports.value = response.data?.data || [];
    summary.value = { ...summary.value, ...(response.data?.summary || {}) };
    pagination.value = { ...pagination.value, ...(response.data?.meta || {}) };
    const requestedId = Number(route.query.project_id || selectedProject.value?.id || 0);
    const next = reports.value.find((item) => item.id === requestedId) || reports.value[0] || null;
    if (next) selectProject(next, false); else selectedProject.value = null;
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to load monitoring reports.');
  } finally { loading.value = false; }
}
function applyFilters() { page.value = 1; loadReports(); }
function setStatus(value: string) { status.value = value; overdueOnly.value = false; applyFilters(); }
function changePage(value: number) { page.value = value; loadReports(); }
async function exportMonitoring() {
  exporting.value = true;
  try {
    const columns = [
      'project_code', 'title', 'proponent_name', 'project_officer', 'origin_track',
      'lifecycle_phase', 'monitoring_status', 'monitoring_submission_status',
      'monitoring_frequency', 'reporting_period', 'monitoring_due_date',
      'monitoring_submitted_at', 'monitoring_reviewed_at', 'jobs_generated_direct',
      'jobs_generated_indirect', 'retained_jobs', 'projected_revenue', 'actual_revenue',
      'dividend_remittance', 'monitoring_indicators', 'social_impact_notes',
      'gcg_relevance', 'gcg_score', 'reportable_to_gcg', 'gcg_metrics',
    ];
    const response = await axiosInstance.get('/api/reports/projects/export', {
      params: {
        report_preset: 'monitoring',
        search: search.value || undefined,
        monitoring_submission_status: status.value || undefined,
        monitoring_overdue: overdueOnly.value ? 1 : undefined,
        columns: columns.join(','),
      },
      responseType: 'blob',
    });
    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = `ndc-monitoring-compliance-${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toast.success('Monitoring compliance spreadsheet generated.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to export monitoring compliance.');
  } finally { exporting.value = false; }
}
function startEdit() { resetForm(); editing.value = true; }
function cancelEdit() { resetForm(); editing.value = false; }
function proponentPayload(metrics: ProjectFinancialMetrics) {
  if (!isExternalProponent.value) return metrics;
  const { gcg_relevance, gcg_score, reportable_to_gcg, is_reportable, gcg_metrics, ...allowed } = metrics;
  return allowed;
}
async function saveReport(silent = false) {
  if (!selectedProject.value) return false;
  saving.value = true;
  try {
    const response = await axiosInstance.put(`/api/projects/${selectedProject.value.id}/monitoring`, {
      financial_metrics: proponentPayload(form.value),
    });
    replaceProject(response.data?.project?.data || response.data?.project);
    editing.value = false;
    if (!silent) toast.success(isExternalProponent.value ? 'Monitoring draft saved.' : 'Monitoring report saved.');
    return true;
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to save monitoring report.');
    return false;
  } finally { saving.value = false; }
}
async function submitReport() {
  if (!selectedProject.value || !(await saveReport(true))) return;
  saving.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${selectedProject.value.id}/monitoring/submit`);
    replaceProject(response.data?.project?.data || response.data?.project);
    toast.success('Monitoring report submitted to NDC.');
  } catch (error: any) { toast.error(error?.response?.data?.message || 'Failed to submit monitoring report.'); }
  finally { saving.value = false; }
}
async function reviewReport(action: 'accepted' | 'returned') {
  if (!selectedProject.value) return;
  if (action === 'returned' && !reviewRemarks.value.trim()) {
    toast.error('Add review remarks before returning the report.'); return;
  }
  saving.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${selectedProject.value.id}/monitoring/review`, {
      action, remarks: reviewRemarks.value.trim() || null,
    });
    replaceProject(response.data?.project?.data || response.data?.project);
    reviewRemarks.value = '';
    toast.success(action === 'accepted' ? 'Monitoring report accepted.' : 'Report returned for correction.');
  } catch (error: any) { toast.error(error?.response?.data?.message || 'Failed to review monitoring report.'); }
  finally { saving.value = false; }
}
async function closePeriod() {
  if (!selectedProject.value || !window.confirm('Close this accepted monitoring period?')) return;
  saving.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${selectedProject.value.id}/monitoring/close`);
    replaceProject(response.data?.project?.data || response.data?.project);
    toast.success('Monitoring period closed.');
  } catch (error: any) { toast.error(error?.response?.data?.message || 'Failed to close monitoring period.'); }
  finally { saving.value = false; }
}
async function activatePeriod() {
  if (!selectedProject.value) return;
  saving.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${selectedProject.value.id}/monitoring/activate`, activation.value);
    replaceProject(response.data?.project?.data || response.data?.project);
    showActivation.value = false;
    activation.value = { due_date: '', instructions: '', proponent_access: true };
    toast.success('Monitoring period opened and the proponent was notified.');
  } catch (error: any) { toast.error(error?.response?.data?.message || 'Failed to open monitoring period.'); }
  finally { saving.value = false; }
}
function replaceProject(project?: Project) {
  if (!project) return;
  const index = reports.value.findIndex((item) => item.id === project.id);
  if (index >= 0) reports.value[index] = project;
  selectedProject.value = project;
  resetForm();
}
function statusLabel(value?: string) {
  return ({ not_requested: 'Not requested', draft: 'Draft in progress', submitted: 'Submitted', returned: 'Returned', accepted: 'Accepted', approved: 'Accepted' } as Record<string, string>)[value || ''] || 'Not requested';
}
function formatDate(value?: string | null) {
  if (!value) return 'Not set';
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}
function isOverdue(project: Project) {
  if (!project.monitoring_due_date || normalizedStatus(project) === 'accepted') return false;
  return new Date(`${project.monitoring_due_date}T23:59:59`) < new Date();
}
function totalJobs(project: Project) {
  const metrics = project.financial_metrics || {};
  return Number(metrics.jobs_generated_direct || 0)
    + Number(metrics.jobs_generated_indirect || 0)
    + Number(metrics.retained_jobs || 0);
}
function money(value?: number | null) {
  if (value === null || value === undefined) return 'Not supplied';
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1 }).format(Number(value));
}
function dueLabel(project: Project) {
  if (!project.monitoring_due_date) return 'No due date';
  if (normalizedStatus(project) === 'accepted') return 'Compliant';
  const due = new Date(`${project.monitoring_due_date}T23:59:59`);
  const days = Math.ceil((due.getTime() - Date.now()) / 86400000);
  if (days < 0) return `${Math.abs(days)} day${Math.abs(days) === 1 ? '' : 's'} overdue`;
  if (days === 0) return 'Due today';
  return `Due in ${days} day${days === 1 ? '' : 's'}`;
}
function varianceLabel(project: Project) {
  const projected = Number(project.financial_metrics?.projected_revenue || 0);
  const actual = Number(project.financial_metrics?.actual_revenue || 0);
  if (!projected || project.financial_metrics?.actual_revenue === null || project.financial_metrics?.actual_revenue === undefined) return 'No variance';
  const variance = ((actual - projected) / projected) * 100;
  return `${variance >= 0 ? '+' : ''}${variance.toFixed(1)}% vs projected`;
}
watch(() => route.query.project_id, (id) => {
  const project = reports.value.find((item) => item.id === Number(id));
  if (project && project.id !== selectedProject.value?.id) selectProject(project);
});
onMounted(loadReports);
</script>

<style scoped>
.monitoring-page{--bg:#f5f7fb;--card:#fff;--soft:#f8fafc;--border:#dbe3ee;--text:#0f172a;--muted:#64748b;min-height:100%;padding:2rem;background:var(--bg);color:var(--text)}
.monitoring-page.is-dark{--bg:#0b1220;--card:#111c2f;--soft:#162238;--border:#2b3a52;--text:#f1f5f9;--muted:#94a3b8}
.page-head,.report-head,.section-heading,.panel-actions,.report-actions,.report-kicker,.page-actions,.register-head,.register-footer,.register-footer>div{display:flex;align-items:center}.page-head,.report-head,.section-heading,.register-head,.register-footer{justify-content:space-between}.page-head{align-items:flex-start;gap:1rem;margin-bottom:1.25rem}.page-actions{gap:.55rem;flex-wrap:wrap}
.eyebrow{margin:0 0 .25rem;color:#2563eb;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}h1,h2,h3{margin:0;letter-spacing:0}h1{font-size:1.75rem}.page-head>div>p:last-child,.report-head p{margin:.4rem 0 0;color:var(--muted)}
.button{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:2.5rem;padding:0 .85rem;border:1px solid var(--border);border-radius:.45rem;font-weight:750;cursor:pointer}.button svg{width:1rem}.button:disabled,.icon-button:disabled{opacity:.55;cursor:not-allowed}.button.secondary{background:var(--card);color:var(--text)}.button.primary{background:#2563eb;border-color:#2563eb;color:#fff}.button.danger{background:#fff1f2;border-color:#fecdd3;color:#be123c}.is-dark .button.danger{background:#3b1620;border-color:#7f1d2d;color:#fecdd3}
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}.summary-grid article{display:flex;align-items:center;gap:.75rem;padding:1rem;border:1px solid var(--border);background:var(--card);border-radius:.5rem}.summary-grid article div{display:grid}.summary-grid strong{font-size:1.35rem}.summary-grid article span:last-child{color:var(--muted);font-size:.76rem}.summary-icon{width:2.25rem;height:2.25rem;display:grid;place-items:center;border-radius:.45rem}.summary-icon svg{width:1.1rem}.summary-icon.blue{background:#dbeafe;color:#2563eb}.summary-icon.violet{background:#ede9fe;color:#6d28d9}.summary-icon.red{background:#fee2e2;color:#dc2626}.summary-icon.green{background:#dcfce7;color:#16a34a}.summary-icon.amber{background:#fef3c7;color:#d97706}
.toolbar{display:grid;grid-template-columns:minmax(16rem,1fr) 13rem auto auto;gap:.65rem;padding:.8rem;margin-bottom:1rem;border:1px solid var(--border);background:var(--card);border-radius:.5rem}.search-box{display:flex;align-items:center;gap:.55rem}.search-box svg{width:1rem;color:var(--muted)}.search-box input,.toolbar select,input,select,textarea{width:100%;min-height:2.45rem;border:1px solid var(--border);border-radius:.4rem;background:var(--soft);color:var(--text);padding:.55rem .7rem}.search-box input{border:0;background:transparent;padding:0;outline:0}.overdue-toggle,.check-field{display:flex!important;align-items:center;gap:.5rem;color:var(--muted);font-size:.8rem;font-weight:700;white-space:nowrap}.overdue-toggle input,.check-field input{width:1rem;min-height:1rem;margin:0}
.portfolio-register,.report-workspace{border:1px solid var(--border);background:var(--card);border-radius:.5rem}.register-head{align-items:flex-end;gap:1rem;padding:1rem;border-bottom:1px solid var(--border)}.register-head h2{font-size:1rem}.register-head p{margin:.2rem 0 0;color:var(--muted);font-size:.72rem}.status-tabs{display:flex;gap:.35rem;overflow-x:auto;overscroll-behavior-inline:contain}.status-tabs button{display:inline-flex;align-items:center;gap:.35rem;min-height:2.15rem;padding:0 .65rem;border:1px solid var(--border);border-radius:.4rem;background:var(--soft);color:var(--muted);font-size:.72rem;font-weight:750;white-space:nowrap;cursor:pointer}.status-tabs button span{display:grid;place-items:center;min-width:1.25rem;height:1.25rem;padding:0 .25rem;border-radius:999px;background:var(--card);color:var(--text)}.status-tabs button.active{border-color:#2563eb;background:#eff6ff;color:#1d4ed8}.is-dark .status-tabs button.active{background:#172554;color:#bfdbfe}
.register-table-wrap{overflow:auto;scrollbar-gutter:stable;overscroll-behavior:contain}.register-table{width:100%;min-width:82rem;border-collapse:separate;border-spacing:0}.register-table th{position:sticky;top:0;z-index:1;padding:.65rem .75rem;border-bottom:1px solid var(--border);background:var(--soft);color:var(--muted);font-size:.65rem;text-align:left;text-transform:uppercase}.register-table td{padding:.75rem;border-bottom:1px solid var(--border);vertical-align:middle;font-size:.76rem}.register-table tbody tr:hover,.register-table tbody tr.selected{background:var(--soft)}.register-table tbody tr.selected{box-shadow:inset 3px 0 #2563eb}.register-table td>strong,.register-table td>span{display:block}.register-table td>span{margin-top:.18rem;color:var(--muted);font-size:.67rem}.project-link{display:grid;gap:.2rem;max-width:19rem;padding:0;border:0;background:transparent;color:var(--text);text-align:left;cursor:pointer}.project-link span{color:#2563eb;font-size:.65rem;font-weight:850}.project-link strong{line-height:1.25}.open-report{width:2rem;height:2rem;display:grid;place-items:center;border:1px solid var(--border);border-radius:.35rem;background:var(--card);color:#2563eb;cursor:pointer}.open-report svg{width:.9rem}.overdue-cell strong,.overdue-cell span{color:#dc2626!important}.register-footer{padding:.7rem 1rem;color:var(--muted);font-size:.72rem}.register-footer>div{gap:.4rem}.project-code{color:#2563eb;font-size:.68rem;font-weight:900;letter-spacing:.05em}
.status-badge,.overdue-badge{padding:.18rem .45rem;border-radius:999px;font-size:.61rem;font-weight:900;text-transform:uppercase}.status-badge.submitted{background:#dbeafe;color:#1d4ed8}.status-badge.returned{background:#fee2e2;color:#b91c1c}.status-badge.draft{background:#fef3c7;color:#92400e}.status-badge.accepted{background:#dcfce7;color:#166534}.status-badge.not_requested{background:#e2e8f0;color:#475569}.overdue-badge{background:#fee2e2;color:#b91c1c}
.report-workspace{margin-top:1rem;overflow:hidden;scroll-margin-top:1rem}.report-head{align-items:flex-start;gap:1rem;padding:1.25rem}.report-head h2{margin-top:.45rem;font-size:1.25rem}.report-head p{max-width:52rem;font-size:.82rem}.report-kicker,.report-actions{flex-wrap:wrap;gap:.45rem}.report-actions{justify-content:flex-end}.cycle-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid var(--border);border-bottom:1px solid var(--border);background:var(--soft)}.cycle-strip div{display:grid;gap:.2rem;padding:.85rem 1rem;border-right:1px solid var(--border)}.cycle-strip span{color:var(--muted);font-size:.68rem;text-transform:uppercase;font-weight:800}.cycle-strip strong{font-size:.8rem}
.notice{display:flex;gap:.75rem;margin:1rem 1.25rem 0;padding:.85rem;border-radius:.45rem;border:1px solid}.notice svg{width:1.2rem;flex:none}.notice strong{font-size:.84rem}.notice p{margin:.2rem 0 0;font-size:.76rem}.notice.returned{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.notice.submitted{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.notice.accepted{background:#f0fdf4;border-color:#bbf7d0;color:#166534}.is-dark .notice{background:var(--soft)}
.activation-panel,.review-panel{margin:1rem 1.25rem;padding:1rem;border:1px solid var(--border);border-radius:.5rem;background:var(--soft)}.icon-button{width:2.25rem;height:2.25rem;display:grid;place-items:center;border:0;background:transparent;color:var(--muted);cursor:pointer}.icon-button.bordered{border:1px solid var(--border);border-radius:.35rem;background:var(--card)}.icon-button svg{width:1rem}.report-form{display:grid;gap:0}.form-section{padding:1.25rem;border-top:1px solid var(--border)}.section-heading{margin-bottom:1rem}.section-heading h3{font-size:1rem}.section-heading>svg{width:1.2rem;color:#2563eb}.form-grid{display:grid;gap:.75rem}.form-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.form-grid label,.review-panel>label{display:grid;gap:.35rem}.form-grid label>span,.review-panel label>span{color:var(--muted);font-size:.72rem;font-weight:750}.form-grid .full{grid-column:1/-1}input:disabled,select:disabled,textarea:disabled{opacity:1;background:transparent;color:var(--text);cursor:default}textarea{resize:vertical}.panel-actions{justify-content:flex-end;gap:.55rem;margin-top:.85rem}
.state-card{min-height:14rem;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.45rem;border:1px dashed var(--border);border-radius:.5rem;background:var(--card);color:var(--muted)}.state-card svg{width:2rem}.state-card strong{color:var(--text)}.spin{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:1100px){.summary-grid{grid-template-columns:repeat(2,1fr)}.toolbar{grid-template-columns:1fr 1fr}.register-head{align-items:flex-start;flex-direction:column}.status-tabs{width:100%}.cycle-strip{grid-template-columns:repeat(2,1fr)}.form-grid.three{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.monitoring-page{padding:1rem}.page-head,.report-head{flex-direction:column}.page-actions{width:100%}.page-actions .button{flex:1}.summary-grid,.toolbar,.cycle-strip,.form-grid.two,.form-grid.three{grid-template-columns:1fr}.report-actions{justify-content:flex-start}.form-grid .full{grid-column:auto}.cycle-strip div{border-right:0;border-bottom:1px solid var(--border)}.portfolio-register{margin-inline:-1rem;border-radius:0}.register-head{padding:1rem}.register-table{min-width:70rem}.report-head,.form-section{padding:1rem}.notice,.activation-panel,.review-panel{margin:1rem}}
</style>
