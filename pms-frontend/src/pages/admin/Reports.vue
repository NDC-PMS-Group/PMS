<template>
  <div class="reports-page" :class="{ 'is-dark': isDark }">
    <header class="page-head">
      <div>
        <p class="eyebrow">SOI-04 Portfolio Analytics</p>
        <h1>Saved & Export Reports</h1>
        <p>Select a predefined project report template, specify date ranges, select custom columns, and enter footnotes.</p>
      </div>
    </header>

    <div class="reports-layout">
      <!-- Left panel: Report selection and basic filters -->
      <main class="config-card">
        <div class="section-title">
          <Filter class="header-icon" />
          <h2>Report Configurations</h2>
        </div>

        <div class="form-content">
          <div class="form-group">
            <label>Predefined Report Template</label>
            <select v-model="selectedReportType" @change="onReportTypeChange">
              <option value="register">Project Register (Master List)</option>
              <option value="financial">Financial & Investment Performance</option>
              <option value="gcg">GCG Compliance & Jobs Report</option>
              <option value="monitoring">Monitoring Compliance Register</option>
              <option value="timeline">Lifecycle Timeline & Progress Report</option>
              <option value="completed">Project Closure & Completion Report</option>
            </select>
            <span class="preset-help-text">{{ reportTypeDescription }}</span>
          </div>

          <div class="form-group">
            <label>Search Query (Optional)</label>
            <div class="input-wrap">
              <Search class="input-icon" />
              <input v-model="filters.search" placeholder="Search code, title, proponent..." />
            </div>
          </div>

          <div class="date-section">
            <h3>Date Range Filters</h3>

            <div class="form-group">
              <label>Date Filter Field</label>
              <select v-model="filters.date_field">
                <option value="created_at">Date Created</option>
                <option value="updated_at">Date Updated</option>
                <option value="proposal_date">Proposal Date</option>
                <option value="start_date">Start Date</option>
                <option value="target_completion_date">Target Completion Date</option>
                <option value="actual_completion_date">Actual Completion Date</option>
                <option value="monitoring_due_date">Monitoring Due Date</option>
                <option value="monitoring_submitted_at">Monitoring Submitted At</option>
                <option value="monitoring_reviewed_at">Monitoring Reviewed At</option>
              </select>
            </div>

            <div v-if="selectedReportType === 'monitoring'" class="form-group">
              <label>Submission Status</label>
              <select v-model="filters.monitoring_submission_status">
                <option value="">All active periods</option>
                <option value="submitted">Needs NDC review</option>
                <option value="returned">Returned for correction</option>
                <option value="draft">Draft in progress</option>
                <option value="accepted">Accepted</option>
              </select>
            </div>

            <div class="date-row">
              <div class="form-group">
                <label>Date From</label>
                <input v-model="filters.date_from" type="date" />
              </div>
              <div class="form-group">
                <label>Date To</label>
                <input v-model="filters.date_to" type="date" />
              </div>
            </div>
          </div>

          <!-- Footnote disclaimer note -->
          <div class="form-group note-group">
            <div class="label-with-desc">
              <label>Custom Footnote / Note</label>
              <span>This text will appear at the bottom of the exported Excel spreadsheet.</span>
            </div>
            <textarea v-model="extractionNote" rows="3" placeholder="Enter report footnotes, disclaimers, or sign-off text here..."></textarea>
          </div>
        </div>
      </main>

      <!-- Right panel: Customize Columns checkboxes -->
      <aside class="columns-card">
        <div class="columns-header">
          <div class="section-title">
            <LayoutGrid class="header-icon" />
            <h2>Toggle Export Columns</h2>
          </div>
          <div class="button-group">
            <button class="small-btn" @click="selectAllColumns">Select All</button>
            <button class="small-btn" @click="clearAllColumns">Clear All</button>
          </div>
        </div>

        <p class="columns-desc">Toggle checkboxes below to customize this report's layout. Order is preserved.</p>

        <div class="columns-wrapper">
          <div v-for="group in columnGroups" :key="group.title" class="column-group-card">
            <h4>{{ group.title }}</h4>
            <div class="checkbox-list">
              <label v-for="col in group.items" :key="col.key" class="checkbox-label">
                <input type="checkbox" :value="col.key" v-model="selectedColumns" />
                <span>{{ col.label }}</span>
              </label>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Export Action footer -->
    <footer class="actions-footer">
      <button class="secondary-btn" @click="resetFilters">
        Reset to Template Defaults
      </button>
      <button class="primary-btn" :disabled="exporting" @click="exportToExcel">
        <RefreshCw v-if="exporting" class="icon spin" />
        <Download v-else class="icon" />
        {{ exporting ? 'Exporting Spreadsheet...' : 'Generate Excel Report' }}
      </button>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import {
  Download, Filter, Search, LayoutGrid, RefreshCw
} from 'lucide-vue-next';
import axiosInstance from '@/utils/axiosInstance';
import { useLayoutStore } from '@/store/layout';
import { useProjectStore } from '@/store/projects';
import { SITE_MODE } from '@/app/const';
import { toast } from 'vue3-toastify';

// Theme detection
const layoutStore = useLayoutStore();
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK);

// Pinia Project store (used for loading metadata in background just in case)
const projectStore = useProjectStore();

// Component States
const selectedReportType = ref<'register' | 'financial' | 'gcg' | 'monitoring' | 'timeline' | 'completed'>('register');
const extractionNote = ref('');
const exporting = ref(false);

// Filter selections
const filters = ref({
  search: '',
  date_field: 'created_at',
  date_from: '',
  date_to: '',
  monitoring_submission_status: ''
});

// Dynamic Columns layout definitions
const columnGroups = [
  {
    title: 'General Info',
    items: [
      { key: 'project_code', label: 'Project Code' },
      { key: 'title', label: 'Project Title' },
      { key: 'stage', label: 'Current Stage' },
      { key: 'status', label: 'Status' },
      { key: 'process_track', label: 'Process Track' },
      { key: 'origin_track', label: 'Project Origin Route' },
      { key: 'lifecycle_phase', label: 'Lifecycle Phase' },
      { key: 'project_type', label: 'Project Type' },
      { key: 'industry', label: 'Industry' },
      { key: 'sector', label: 'Sector' },
      { key: 'proponent_name', label: 'Proponent Name' },
      { key: 'proponent_email', label: 'Proponent Email' },
      { key: 'project_officer', label: 'Project Officer' },
      { key: 'location_address', label: 'Location' },
      { key: 'updated_at', label: 'Updated At' }
    ]
  },
  {
    title: 'Financial Metrics',
    items: [
      { key: 'estimated_cost', label: 'Estimated Cost' },
      { key: 'actual_cost', label: 'Actual Cost' },
      { key: 'target_amount_to_raise', label: 'Target Amount to Raise' },
      { key: 'ndc_participation', label: 'NDC Participation' },
      { key: 'projected_revenue', label: 'Projected Revenue' },
      { key: 'actual_revenue', label: 'Actual Revenue' },
      { key: 'dividend_remittance', label: 'Dividend / Remittance' }
    ]
  },
  {
    title: 'Jobs & GCG Alignment',
    items: [
      { key: 'jobs_generated_direct', label: 'Direct Jobs' },
      { key: 'jobs_direct_male', label: 'Direct Jobs - Male' },
      { key: 'jobs_direct_female', label: 'Direct Jobs - Female' },
      { key: 'jobs_generated_indirect', label: 'Indirect Jobs' },
      { key: 'jobs_indirect_male', label: 'Indirect Jobs - Male' },
      { key: 'jobs_indirect_female', label: 'Indirect Jobs - Female' },
      { key: 'retained_jobs', label: 'Retained Jobs' },
      { key: 'jobs_retained_male', label: 'Retained Jobs - Male' },
      { key: 'jobs_retained_female', label: 'Retained Jobs - Female' },
      { key: 'gcg_relevance', label: 'GCG Relevant' },
      { key: 'gcg_score', label: 'GCG Score' },
      { key: 'reportable_to_gcg', label: 'Reportable to GCG' },
      { key: 'monitoring_frequency', label: 'Monitoring Frequency' },
      { key: 'reporting_period', label: 'Reporting Period' }
    ]
  },
  {
    title: 'Monitoring Compliance',
    items: [
      { key: 'monitoring_status', label: 'Monitoring Cycle' },
      { key: 'monitoring_submission_status', label: 'Submission Status' },
      { key: 'monitoring_due_date', label: 'Compliance Due Date' },
      { key: 'monitoring_instructions', label: 'Submission Instructions' },
      { key: 'monitoring_draft_saved_at', label: 'Draft Last Saved' },
      { key: 'monitoring_submitted_at', label: 'Submitted At' },
      { key: 'monitoring_submitted_by', label: 'Submitted By' },
      { key: 'monitoring_reviewed_at', label: 'Reviewed At' },
      { key: 'monitoring_reviewed_by', label: 'Reviewed By' },
      { key: 'monitoring_review_notes', label: 'Review Notes' },
      { key: 'monitoring_proponent_access', label: 'Proponent Access' },
      { key: 'monitoring_indicators', label: 'Monitoring Indicators / Milestones' },
      { key: 'social_impact_notes', label: 'Social Impact Notes' },
      { key: 'gcg_metrics', label: 'GCG Metrics / Notes' }
    ]
  },
  {
    title: 'Progress & Deadlines',
    items: [
      { key: 'progress_percentage', label: 'Progress Percentage' },
      { key: 'tasks_count', label: 'Task Count' },
      { key: 'documents_count', label: 'Document Count' },
      { key: 'target_completion_date', label: 'Target Completion' },
      { key: 'actual_completion_date', label: 'Actual Completion' },
      { key: 'is_overdue', label: 'Overdue Status' }
    ]
  }
];

// Map report type preset to default columns list
const reportDefaults: Record<string, string[]> = {
  register: [
    'project_code', 'title', 'stage', 'status', 'proponent_name',
    'estimated_cost', 'actual_cost', 'target_completion_date', 'location_address'
  ],
  financial: [
    'project_code', 'title', 'stage', 'status', 'estimated_cost', 'actual_cost',
    'target_amount_to_raise', 'ndc_participation', 'projected_revenue', 'actual_revenue', 'dividend_remittance'
  ],
  gcg: [
    'project_code', 'title', 'stage', 'status', 'jobs_generated_direct', 'jobs_generated_indirect',
    'retained_jobs', 'gcg_relevance', 'gcg_score', 'reportable_to_gcg', 'monitoring_frequency', 'reporting_period'
  ],
  monitoring: [
    'project_code', 'title', 'proponent_name', 'project_officer', 'origin_track',
    'lifecycle_phase', 'monitoring_status', 'monitoring_submission_status',
    'monitoring_frequency', 'reporting_period', 'monitoring_due_date',
    'monitoring_submitted_at', 'monitoring_submitted_by', 'monitoring_reviewed_at',
    'monitoring_reviewed_by', 'jobs_generated_direct', 'jobs_generated_indirect',
    'retained_jobs', 'projected_revenue', 'actual_revenue', 'dividend_remittance',
    'monitoring_indicators', 'social_impact_notes', 'gcg_relevance', 'gcg_score',
    'reportable_to_gcg', 'gcg_metrics'
  ],
  timeline: [
    'project_code', 'title', 'stage', 'status', 'process_track', 'progress_percentage',
    'tasks_count', 'documents_count', 'target_completion_date', 'is_overdue'
  ],
  completed: [
    'project_code', 'title', 'stage', 'status', 'actual_completion_date', 'location_address', 'updated_at'
  ]
};

// Map report selection to query preset parameter
const reportPresetQueryMap: Record<string, string> = {
  register: 'all',
  financial: 'approved',
  gcg: 'reportable',
  monitoring: 'monitoring',
  timeline: 'ongoing',
  completed: 'completed'
};

const allColumnKeys = columnGroups.flatMap(group => group.items.map(item => item.key));
const selectedColumns = ref<string[]>([...reportDefaults.register]);

// Computed descriptions
const reportTypeDescription = computed(() => {
  return {
    register: 'Exports all active projects with codes, statuses, proponents, costs, and locations.',
    financial: 'Highlights costs, funding, target raise amounts, NDC participation, and remittances.',
    gcg: 'Analyzes direct/indirect job creation, retained workforce, GCG relevance, and scorecard results.',
    monitoring: 'Exports active compliance periods with deadlines, submission and review status, jobs, revenue, and impact narratives.',
    timeline: 'Displays workgroup process track, milestone task progress, and overdue indicators.',
    completed: 'Reviews closed out, completed, or divested projects and their completion metrics.'
  }[selectedReportType.value] || '';
});

// Update checked columns automatically when template changes
function onReportTypeChange() {
  const defaults = reportDefaults[selectedReportType.value];
  if (defaults) {
    selectedColumns.value = [...defaults];
  }
}

// Columns toolbar selectors
function selectAllColumns() {
  selectedColumns.value = [...allColumnKeys];
}
function clearAllColumns() {
  selectedColumns.value = [];
}

// Reset page filters to defaults of the active template
function resetFilters() {
  filters.value = {
    search: '',
    date_field: 'created_at',
    date_from: '',
    date_to: '',
    monitoring_submission_status: ''
  };
  extractionNote.value = '';
  onReportTypeChange();
}

// Export Trigger
async function exportToExcel() {
  if (selectedColumns.value.length === 0) {
    toast.error('Please select at least one column to export.');
    return;
  }

  exporting.value = true;
  try {
    const preset = reportPresetQueryMap[selectedReportType.value] || 'all';
    const params: Record<string, any> = {
      report_preset: preset,
      search: filters.value.search || undefined,
      date_field: filters.value.date_field,
      date_from: filters.value.date_from || undefined,
      date_to: filters.value.date_to || undefined,
      monitoring_submission_status: filters.value.monitoring_submission_status || undefined,
      note: extractionNote.value || undefined,
      columns: selectedColumns.value.join(',')
    };

    const response = await axiosInstance.get('/api/reports/projects/export', {
      params,
      responseType: 'blob'
    });

    const contentDisposition = response.headers['content-disposition'];
    let filename = `ndc-report-${selectedReportType.value}-${new Date().toISOString().slice(0, 10)}.xlsx`;
    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
      if (filenameMatch && filenameMatch[1]) {
        filename = filenameMatch[1];
      }
    }

    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);

    toast.success('Excel spreadsheet generated successfully.');
  } catch (error: any) {
    console.error('Export failed:', error);
    toast.error('Failed to generate Excel report.');
  } finally {
    exporting.value = false;
  }
}

onMounted(() => {
  // Fetch metadata lookups silently in case needed in other parts of lifecycle
  projectStore.fetchStages();
  projectStore.fetchStatuses();
});
</script>

<style scoped>
.reports-page {
  --bg: #f8fafc;
  --card: #ffffff;
  --border: #dbe3ee;
  --text: #0f172a;
  --muted: #64748b;
  --accent: #2563eb;
  --c-subtle: #f1f5f9;
  --c-hover: #f8fafc;

  min-height: 100%;
  padding: 2rem;
  background: var(--bg);
  color: var(--text);
  display: flex;
  flex-direction: column;
}

.reports-page.is-dark {
  --bg: #0f172a;
  --card: #162238;
  --border: #2b3a52;
  --text: #f1f5f9;
  --muted: #94a3b8;
  --accent: #3b82f6;
  --c-subtle: #1e293b;
  --c-hover: #1f2e4d;
}

.page-head {
  margin-bottom: 2rem;
}

.eyebrow {
  margin: 0 0 0.25rem;
  color: var(--accent);
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

h1 {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 750;
}

.page-head p:last-child {
  margin: 0.35rem 0 0;
  color: var(--muted);
  font-size: 0.9rem;
}

.reports-layout {
  display: grid;
  grid-template-columns: 24rem minmax(0, 1fr);
  gap: 1.5rem;
  flex-grow: 1;
  align-items: start;
}

/* Card wrappers */
.config-card, .columns-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border);
}

.section-title h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.header-icon {
  width: 1.1rem;
  color: var(--accent);
}

.form-content {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-group label {
  font-size: 0.76rem;
  font-weight: 800;
  color: var(--text);
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.preset-help-text {
  font-size: 0.74rem;
  color: var(--muted);
  margin-top: 0.15rem;
  display: block;
}

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 0.75rem;
  width: 0.95rem;
  color: var(--muted);
}

.input-wrap input {
  padding-left: 2.25rem !important;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  min-height: 2.45rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--bg);
  color: var(--text);
  padding: 0 0.75rem;
  outline: none;
  font-size: 0.82rem;
  transition: border-color 0.15s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--accent);
}

/* Date filter styling */
.date-section {
  border-top: 1px dashed var(--border);
  padding-top: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.date-section h3 {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--muted);
  letter-spacing: 0.04em;
}

.date-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.date-row input {
  padding: 0 0.65rem;
}

/* Footnotes */
.note-group {
  border-top: 1px dashed var(--border);
  padding-top: 1.25rem;
}

.label-with-desc {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  margin-bottom: 0.25rem;
}

.label-with-desc span {
  font-size: 0.7rem;
  color: var(--muted);
}

.note-group textarea {
  padding: 0.65rem;
  resize: vertical;
  min-height: 4.5rem;
}

/* Columns toggle styling */
.columns-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.columns-header .section-title {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: 0;
  flex-grow: 1;
}

.button-group {
  display: flex;
  gap: 0.5rem;
}

.small-btn {
  padding: 0.25rem 0.65rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
  background: var(--card);
  color: var(--text);
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.small-btn:hover {
  background: var(--c-subtle);
  border-color: var(--accent);
  color: var(--accent);
}

.columns-desc {
  font-size: 0.8rem;
  color: var(--muted);
  margin: 0.5rem 0 1.25rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border);
}

.columns-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.column-group-card {
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  padding: 1rem;
  background: var(--c-subtle);
}

.column-group-card h4 {
  margin: 0 0 0.85rem;
  font-size: 0.8rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--accent);
  letter-spacing: 0.04em;
}

.checkbox-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  color: var(--text);
}

.checkbox-label input {
  width: 0.95rem;
  height: 0.95rem;
  cursor: pointer;
  accent-color: var(--accent);
}

/* Footer layout actions */
.actions-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
  padding: 1rem 1.5rem;
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--card);
}

.primary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  background: var(--accent);
  border: 1px solid var(--accent);
  color: #ffffff;
  min-height: 2.5rem;
  padding: 0 1.5rem;
  border-radius: 0.45rem;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.primary-btn:hover:not(:disabled) {
  background: #1d4ed8;
  border-color: #1d4ed8;
}

.primary-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.secondary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  background: var(--card);
  border: 1px solid var(--border);
  color: var(--text);
  min-height: 2.5rem;
  padding: 0 1.25rem;
  border-radius: 0.45rem;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.secondary-btn:hover {
  background: var(--c-hover);
  border-color: var(--accent);
  color: var(--accent);
}

.primary-btn svg,
.secondary-btn svg {
  width: 1rem;
  height: 1rem;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive constraints */
@media (max-width: 1200px) {
  .reports-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .checkbox-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 600px) {
  .checkbox-list {
    grid-template-columns: 1fr;
  }
  .date-row {
    grid-template-columns: 1fr;
  }
}
</style>
