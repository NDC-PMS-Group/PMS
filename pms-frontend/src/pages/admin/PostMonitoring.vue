<template>
  <main class="monitoring-page" :class="{ 'is-dark': isDark }">
    <header class="page-head">
      <div>
        <p class="eyebrow">SOI-02 Portfolio Compliance</p>
        <h1>{{ isExternalProponent ? 'Monitoring Compliance' : 'Implementation Monitoring' }}</h1>
        <p v-if="isExternalProponent">Input the requested project results, save your progress, and submit the completed compliance report to NDC.</p>
        <p v-else>Prepare, review, return, accept, and close monitoring reports without leaving this workspace.</p>
      </div>
      <button class="button secondary" type="button" :disabled="loading" @click="loadReports">
        <RefreshCw :class="{ spin: loading }" aria-hidden="true" /> Refresh
      </button>
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
        <input v-model="search" placeholder="Search project, code, or proponent" @keyup.enter="loadReports" />
      </label>
      <label>
        <span class="sr-only">Report status</span>
        <select v-model="status" @change="loadReports">
          <option value="">All report states</option>
          <option value="submitted">Submitted for review</option>
          <option value="returned">Returned</option>
          <option value="draft">Draft in progress</option>
          <option value="accepted">Accepted</option>
        </select>
      </label>
      <label class="overdue-toggle"><input v-model="overdueOnly" type="checkbox" @change="loadReports" /> Overdue only</label>
      <button class="button primary" type="button" @click="loadReports">Apply</button>
    </section>

    <div v-if="loading && !reports.length" class="state-card">Loading implementation monitoring workspace...</div>
    <div v-else-if="!reports.length" class="state-card">
      <ClipboardCheck aria-hidden="true" />
      <strong>No monitoring reports found</strong>
      <span v-if="isExternalProponent">NDC has not opened a monitoring submission for your projects.</span>
      <span v-else>Open a monitoring period from an eligible implementation project to begin.</span>
    </div>

    <section v-else class="workspace">
      <aside class="project-rail" aria-label="Monitoring projects">
        <div class="rail-head">
          <strong>{{ reports.length }} projects</strong>
          <span>Select a report to work on</span>
        </div>
        <button
          v-for="project in reports"
          :key="project.id"
          class="project-option"
          :class="{ selected: selectedProject?.id === project.id }"
          type="button"
          @click="selectProject(project)"
        >
          <span class="option-topline">
            <span class="project-code">{{ project.project_code }}</span>
            <span class="status-badge" :class="normalizedStatus(project)">{{ statusLabel(project.monitoring_submission_status) }}</span>
          </span>
          <strong>{{ project.title }}</strong>
          <span>{{ project.proponent_name || project.proponent_user?.organization_name || 'No proponent recorded' }}</span>
          <span class="option-due" :class="{ overdue: isOverdue(project) }">
            <CalendarClock aria-hidden="true" /> Due {{ formatDate(project.monitoring_due_date) }}
          </span>
        </button>
      </aside>

      <article v-if="selectedProject" class="report-workspace">
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
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Activity, Briefcase, CalendarClock, CheckCircle2, ClipboardCheck, Clock3,
  DollarSign, Pencil, RefreshCw, RotateCcw, Save, Search, Send, ShieldCheck, X,
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
const editing = ref(false);
const showActivation = ref(false);
const search = ref('');
const status = ref('');
const overdueOnly = ref(false);
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

const counts = computed(() => ({
  submitted: reports.value.filter((item) => normalizedStatus(item) === 'submitted').length,
  returned: reports.value.filter((item) => normalizedStatus(item) === 'returned').length,
  accepted: reports.value.filter((item) => normalizedStatus(item) === 'accepted').length,
  overdue: reports.value.filter(isOverdue).length,
}));
const summaryCards = computed(() => [
  { label: 'Awaiting review', value: counts.value.submitted, icon: Clock3, tone: 'blue' },
  { label: 'Returned', value: counts.value.returned, icon: RotateCcw, tone: 'red' },
  { label: 'Accepted', value: counts.value.accepted, icon: CheckCircle2, tone: 'green' },
  { label: 'Overdue', value: counts.value.overdue, icon: CalendarClock, tone: 'amber' },
]);

function resetForm() {
  form.value = { ...emptyMetrics(), ...(selectedProject.value?.financial_metrics || {}) };
}
function selectProject(project: Project) {
  selectedProject.value = project;
  editing.value = false;
  showActivation.value = false;
  reviewRemarks.value = '';
  resetForm();
  router.replace({ query: { ...route.query, project_id: String(project.id) } });
}
async function loadReports() {
  loading.value = true;
  try {
    const response = await axiosInstance.get('/api/post-monitoring', { params: {
      per_page: 100, search: search.value || undefined,
      submission_status: status.value || undefined, overdue: overdueOnly.value ? 1 : undefined,
    } });
    reports.value = response.data?.data || [];
    const requestedId = Number(route.query.project_id || selectedProject.value?.id || 0);
    const next = reports.value.find((item) => item.id === requestedId) || reports.value[0] || null;
    if (next) selectProject(next); else selectedProject.value = null;
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to load monitoring reports.');
  } finally { loading.value = false; }
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
watch(() => route.query.project_id, (id) => {
  const project = reports.value.find((item) => item.id === Number(id));
  if (project && project.id !== selectedProject.value?.id) selectProject(project);
});
onMounted(loadReports);
</script>

<style scoped>
.monitoring-page{--bg:#f5f7fb;--card:#fff;--soft:#f8fafc;--border:#dbe3ee;--text:#0f172a;--muted:#64748b;min-height:100%;padding:2rem;background:var(--bg);color:var(--text)}
.monitoring-page.is-dark{--bg:#0b1220;--card:#111c2f;--soft:#162238;--border:#2b3a52;--text:#f1f5f9;--muted:#94a3b8}
.page-head,.report-head,.section-heading,.panel-actions,.report-actions,.report-kicker,.option-topline{display:flex;align-items:center}.page-head,.report-head,.section-heading{justify-content:space-between}.page-head{align-items:flex-start;gap:1rem;margin-bottom:1.25rem}
.eyebrow{margin:0 0 .25rem;color:#2563eb;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em}h1,h2,h3{margin:0;letter-spacing:0}h1{font-size:1.75rem}.page-head>div>p:last-child,.report-head p{margin:.4rem 0 0;color:var(--muted)}
.button{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:2.5rem;padding:0 .85rem;border:1px solid var(--border);border-radius:.45rem;font-weight:750;cursor:pointer}.button svg{width:1rem}.button:disabled{opacity:.55;cursor:not-allowed}.button.secondary{background:var(--card);color:var(--text)}.button.primary{background:#2563eb;border-color:#2563eb;color:#fff}.button.danger{background:#fff1f2;border-color:#fecdd3;color:#be123c}.is-dark .button.danger{background:#3b1620;border-color:#7f1d2d;color:#fecdd3}
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}.summary-grid article{display:flex;align-items:center;gap:.75rem;padding:1rem;border:1px solid var(--border);background:var(--card);border-radius:.5rem}.summary-grid article div{display:grid}.summary-grid strong{font-size:1.35rem}.summary-grid article span:last-child{color:var(--muted);font-size:.76rem}.summary-icon{width:2.25rem;height:2.25rem;display:grid;place-items:center;border-radius:.45rem}.summary-icon svg{width:1.1rem}.summary-icon.blue{background:#dbeafe;color:#2563eb}.summary-icon.red{background:#fee2e2;color:#dc2626}.summary-icon.green{background:#dcfce7;color:#16a34a}.summary-icon.amber{background:#fef3c7;color:#d97706}
.toolbar{display:grid;grid-template-columns:minmax(16rem,1fr) 13rem auto auto;gap:.65rem;padding:.8rem;margin-bottom:1rem;border:1px solid var(--border);background:var(--card);border-radius:.5rem}.search-box{display:flex;align-items:center;gap:.55rem}.search-box svg{width:1rem;color:var(--muted)}.search-box input,.toolbar select,input,select,textarea{width:100%;min-height:2.45rem;border:1px solid var(--border);border-radius:.4rem;background:var(--soft);color:var(--text);padding:.55rem .7rem}.search-box input{border:0;background:transparent;padding:0;outline:0}.overdue-toggle,.check-field{display:flex!important;align-items:center;gap:.5rem;color:var(--muted);font-size:.8rem;font-weight:700;white-space:nowrap}.overdue-toggle input,.check-field input{width:1rem;min-height:1rem;margin:0}
.workspace{display:grid;grid-template-columns:20rem minmax(0,1fr);gap:1rem;align-items:start}.project-rail,.report-workspace{border:1px solid var(--border);background:var(--card);border-radius:.5rem}.project-rail{max-height:calc(100vh - 17rem);overflow:auto}.rail-head{display:grid;gap:.15rem;padding:.85rem 1rem;border-bottom:1px solid var(--border)}.rail-head span{color:var(--muted);font-size:.72rem}.project-option{display:grid;gap:.35rem;width:100%;padding:.9rem 1rem;border:0;border-bottom:1px solid var(--border);background:transparent;color:var(--text);text-align:left;cursor:pointer}.project-option:hover,.project-option.selected{background:var(--soft)}.project-option.selected{box-shadow:inset 3px 0 #2563eb}.project-option>strong{font-size:.88rem}.project-option>span:not(.option-topline){color:var(--muted);font-size:.72rem}.option-topline{justify-content:space-between;gap:.5rem}.project-code{color:#2563eb;font-size:.68rem;font-weight:900;letter-spacing:.05em}.option-due{display:flex!important;align-items:center;gap:.3rem}.option-due svg{width:.8rem}.option-due.overdue{color:#dc2626!important}
.status-badge,.overdue-badge{padding:.18rem .45rem;border-radius:999px;font-size:.61rem;font-weight:900;text-transform:uppercase}.status-badge.submitted{background:#dbeafe;color:#1d4ed8}.status-badge.returned{background:#fee2e2;color:#b91c1c}.status-badge.draft{background:#fef3c7;color:#92400e}.status-badge.accepted{background:#dcfce7;color:#166534}.status-badge.not_requested{background:#e2e8f0;color:#475569}.overdue-badge{background:#fee2e2;color:#b91c1c}
.report-workspace{overflow:hidden}.report-head{align-items:flex-start;gap:1rem;padding:1.25rem}.report-head h2{margin-top:.45rem;font-size:1.25rem}.report-head p{max-width:52rem;font-size:.82rem}.report-kicker,.report-actions{flex-wrap:wrap;gap:.45rem}.report-actions{justify-content:flex-end}.cycle-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid var(--border);border-bottom:1px solid var(--border);background:var(--soft)}.cycle-strip div{display:grid;gap:.2rem;padding:.85rem 1rem;border-right:1px solid var(--border)}.cycle-strip span{color:var(--muted);font-size:.68rem;text-transform:uppercase;font-weight:800}.cycle-strip strong{font-size:.8rem}
.notice{display:flex;gap:.75rem;margin:1rem 1.25rem 0;padding:.85rem;border-radius:.45rem;border:1px solid}.notice svg{width:1.2rem;flex:none}.notice strong{font-size:.84rem}.notice p{margin:.2rem 0 0;font-size:.76rem}.notice.returned{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.notice.submitted{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.notice.accepted{background:#f0fdf4;border-color:#bbf7d0;color:#166534}.is-dark .notice{background:var(--soft)}
.activation-panel,.review-panel{margin:1rem 1.25rem;padding:1rem;border:1px solid var(--border);border-radius:.5rem;background:var(--soft)}.icon-button{width:2.25rem;height:2.25rem;display:grid;place-items:center;border:0;background:transparent;color:var(--muted);cursor:pointer}.icon-button svg{width:1rem}.report-form{display:grid;gap:0}.form-section{padding:1.25rem;border-top:1px solid var(--border)}.section-heading{margin-bottom:1rem}.section-heading h3{font-size:1rem}.section-heading>svg{width:1.2rem;color:#2563eb}.form-grid{display:grid;gap:.75rem}.form-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.form-grid label,.review-panel>label{display:grid;gap:.35rem}.form-grid label>span,.review-panel label>span{color:var(--muted);font-size:.72rem;font-weight:750}.form-grid .full{grid-column:1/-1}input:disabled,select:disabled,textarea:disabled{opacity:1;background:transparent;color:var(--text);cursor:default}textarea{resize:vertical}.panel-actions{justify-content:flex-end;gap:.55rem;margin-top:.85rem}
.state-card{min-height:14rem;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.45rem;border:1px dashed var(--border);border-radius:.5rem;background:var(--card);color:var(--muted)}.state-card svg{width:2rem}.state-card strong{color:var(--text)}.spin{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:1100px){.summary-grid{grid-template-columns:repeat(2,1fr)}.toolbar{grid-template-columns:1fr 1fr}.workspace{grid-template-columns:1fr}.project-rail{display:flex;max-height:none;overflow-x:auto}.rail-head{min-width:12rem}.project-option{min-width:17rem;border-right:1px solid var(--border)}.cycle-strip{grid-template-columns:repeat(2,1fr)}.form-grid.three{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.monitoring-page{padding:1rem}.page-head,.report-head{flex-direction:column}.summary-grid,.toolbar,.cycle-strip,.form-grid.two,.form-grid.three{grid-template-columns:1fr}.report-actions{justify-content:flex-start}.form-grid .full{grid-column:auto}.cycle-strip div{border-right:0;border-bottom:1px solid var(--border)}.project-rail{margin-inline:-1rem;border-radius:0}.report-head,.form-section{padding:1rem}.notice,.activation-panel,.review-panel{margin:1rem}}
</style>
