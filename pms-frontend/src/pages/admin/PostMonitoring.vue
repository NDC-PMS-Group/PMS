<template>
  <main class="monitoring-page" :class="{ 'is-dark': isDark }">
    <header class="page-head">
      <div>
        <p class="eyebrow">Portfolio compliance register</p>
        <h1>Monitoring Compliance</h1>
        <p>
          Review submitted reports and add outstanding requirements across open projects.
        </p>
      </div>
      <div class="page-actions">
        <button
          class="button secondary"
          type="button"
          :disabled="exporting"
          @click="exportReports"
        >
          <Download /> {{ exporting ? "Preparing..." : "Export Excel" }}</button
        ><button
          class="button secondary"
          type="button"
          :disabled="exportingPdf"
          @click="exportReportsPdf"
        >
          <FileText /> {{ exportingPdf ? "Preparing..." : "Export PDF" }}</button
        ><button
          class="icon-button"
          type="button"
          :disabled="loading"
          aria-label="Refresh"
          @click="loadCycles"
        >
          <RefreshCw :class="{ spin: loading }" />
        </button>
      </div>
    </header>

    <div class="scope-tabs" role="tablist" aria-label="Monitoring cycle view">
      <button
        type="button"
        role="tab"
        :aria-selected="scope === 'active'"
        :class="{ active: scope === 'active' }"
        @click="changeScope('active')"
      >
        <Activity /> Active Monitoring</button
      ><button
        type="button"
        role="tab"
        :aria-selected="scope === 'history'"
        :class="{ active: scope === 'history' }"
        @click="changeScope('history')"
      >
        <History /> History
      </button>
    </div>

    <section
      v-if="scope === 'active'"
      class="summary-grid"
      aria-label="Active monitoring summary"
    >
      <article v-for="item in summaryCards" :key="item.label">
        <span class="summary-icon" :class="item.tone"
          ><component :is="item.icon"
        /></span>
        <div>
          <strong>{{ item.value }}</strong
          ><span>{{ item.label }}</span>
        </div>
      </article>
    </section>

    <section class="filter-bar" :class="{ 'is-manager': isManager }" aria-label="Filter monitoring cycles">
      <label><span>Classification</span><select v-model="filters.record_type" @change="applyFilters"><option value="">All records</option><option value="project">NDC Projects</option><option value="investment">Investments</option><option value="unclassified">Needs classification</option></select></label>
      <label><span>Investment lifecycle</span><select v-model="filters.investment_status" @change="applyFilters"><option value="">All stages</option><option value="under_evaluation">Under Evaluation</option><option value="board_approved">Board Approved — Awaiting Deployment</option><option value="portfolio">Investment Portfolio</option><option value="not_proceeding">Not Proceeding</option></select></label>
      <label class="search-box"
        ><Search /><span class="sr-only">Search projects</span
        ><input
          v-model.trim="filters.search"
          placeholder="Search project, code, or proponent"
          @keyup.enter="applyFilters"
      /></label>
      <label
        ><span>Year</span
        ><select v-model="filters.year" @change="applyFilters">
          <option value="">All years</option>
          <option v-for="year in availableYears" :key="year" :value="year">
            {{ year }}
          </option>
        </select></label
      >
      <label
        ><span>Quarter</span
        ><select v-model="filters.quarter" @change="applyFilters">
          <option value="">All quarters</option>
          <option v-for="quarter in 4" :key="quarter" :value="quarter">
            Q{{ quarter }}
          </option>
        </select></label
      >
      <label
        v-if="!isManager"
        ><span>Type</span
        ><select v-model="filters.compliance_type" @change="applyFilters">
          <option value="">All types</option>
          <option value="employment">Employment</option>
          <option value="financial">Financial</option>
          <option value="progress">Progress</option>
        </select></label
      >
      <label
        ><span>Report status</span
        ><select v-model="filters.status" @change="applyFilters">
          <option value="">All statuses</option>
          <option value="submitted">Needs review</option>
          <option value="returned">Returned</option>
          <option value="accepted">Accepted</option>
        </select></label
      >
      <div class="filter-actions">
        <button class="button secondary" type="button" @click="resetFilters">
          <RotateCcw /> Reset
        </button>
        <button
          v-if="isManager && scope === 'active'"
          class="button primary"
          type="button"
          @click="openReportLauncher"
        >
          <Plus /> Add report
        </button>
      </div>
    </section>

    <div v-if="loading && !cycles.length" class="state-card">
      Loading monitoring cycles...
    </div>
    <div v-else-if="!cycles.length" class="state-card">
      <ClipboardCheck /><strong>{{
        scope === "active"
          ? "No open monitoring periods"
          : "No monitoring history found"
      }}</strong
      ><span>{{
        scope === "active"
          ? "Open Monitoring from an eligible project before requesting compliance reports."
          : "Closed monitoring periods will appear here."
      }}</span>
    </div>

    <section
      v-else-if="isManager"
      class="register-shell"
      aria-labelledby="compliance-register-title"
    >
      <div class="register-head">
        <div>
          <h2 id="compliance-register-title">Compliance register</h2>
          <p>{{ managerRows.length }} submitted reports across this page</p>
        </div>
        <div class="report-type-tabs" role="tablist" aria-label="Report type">
          <button
            v-for="tab in managerTypeTabs"
            :key="tab.value"
            type="button"
            role="tab"
            :aria-selected="filters.compliance_type === tab.value"
            :class="{ active: filters.compliance_type === tab.value }"
            @click="selectReportType(tab.value)"
          >
            <component v-if="tab.icon" :is="tab.icon" />
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div class="table-scroll" tabindex="0" aria-label="Scrollable compliance register">
        <table class="compliance-table">
          <caption class="sr-only">
            Project monitoring reports, submission status, due dates, and review actions
          </caption>
          <thead>
            <tr>
              <th scope="col">Project</th>
              <th scope="col">Period</th>
              <th scope="col">Report</th>
              <th scope="col">Submitted values</th>
              <th scope="col">Due</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in managerRows" :key="row.key">
              <tr
                class="register-row"
                :class="{ 'has-report': true, expanded: expandedRowKey === row.key, overdue: row.overdue }"
                @click="toggleManagerRow(row)"
              >
                <td>
                  <button
                    class="project-trigger"
                    type="button"
                    :aria-expanded="expandedRowKey === row.key"
                    :aria-controls="`report-detail-${row.key}`"
                    @click.stop="toggleManagerRow(row)"
                  >
                    <strong>{{ row.cycle.project.title }}</strong>
                    <span>{{ row.cycle.project.project_code }} · {{ row.cycle.project.record_type_label }}</span>
                  </button>
                  <small>{{ row.cycle.project.proponent_name || "No proponent recorded" }}</small>
                </td>
                <td>
                  <strong>Q{{ row.cycle.quarter }} {{ row.cycle.reporting_year }}</strong>
                  <small>{{ formatRange(row.cycle.period_start, row.cycle.period_end) }}</small>
                </td>
                <td>
                  <span class="report-type"><component :is="typeIcon(row.type)" />{{ typeLabel(row.type) }}</span>
                  <small>{{ reportPeriod(row.report) }}</small>
                </td>
                <td class="summary-cell">
                  <span>{{ reportSummary(row.report) }}</span>
                </td>
                <td>
                  <strong>{{ formatDate(row.cycle.due_date) }}</strong>
                  <small :class="{ danger: row.overdue }">{{ rowDueLabel(row) }}</small>
                </td>
                <td>
                  <span class="status-badge" :class="row.report.status">
                    {{ statusLabel(row.report.status) }}
                  </span>
                  <ChevronDown class="row-chevron" :aria-hidden="true" />
                </td>
              </tr>
              <tr v-if="expandedRowKey === row.key" class="detail-row">
                <td :id="`report-detail-${row.key}`" colspan="6">
                  <div class="inline-review">
                    <div class="inline-review-head">
                      <div>
                        <span class="eyebrow">{{ typeLabel(row.type) }} compliance</span>
                        <h3>{{ row.cycle.project.title }}</h3>
                      </div>
                      <div class="submission-facts">
                        <span>Officer: {{ row.cycle.project.project_officer?.full_name || "Unassigned" }}</span>
                        <span v-if="row.report.submitted_at">Submitted {{ formatDateTime(row.report.submitted_at) }}</span>
                      </div>
                    </div>

                    <div>
                      <dl v-if="row.type === 'employment'" class="inline-metrics employment">
                        <div><dt>Generated male</dt><dd>{{ number(row.report.jobs_generated_male) }}</dd></div>
                        <div><dt>Generated female</dt><dd>{{ number(row.report.jobs_generated_female) }}</dd></div>
                        <div><dt>Generated total</dt><dd>{{ number(row.report.jobs_generated) }}</dd></div>
                        <div><dt>Retained male</dt><dd>{{ number(row.report.jobs_retained_male) }}</dd></div>
                        <div><dt>Retained female</dt><dd>{{ number(row.report.jobs_retained_female) }}</dd></div>
                        <div><dt>Retained total</dt><dd>{{ number(row.report.jobs_retained) }}</dd></div>
                      </dl>
                      <dl v-else-if="row.type === 'financial'" class="inline-metrics">
                        <div><dt>Revenue</dt><dd>{{ money(row.report.revenue) }}</dd></div>
                        <div><dt>Remittance</dt><dd>{{ money(row.report.remittance) }}</dd></div>
                      </dl>
                      <div v-else class="inline-narratives">
                        <div><span>Period milestones</span><p>{{ row.report.milestones || "Not recorded" }}</p></div>
                        <div><span>Project impact</span><p>{{ row.report.impact || "Not recorded" }}</p></div>
                        <div><span>Monitoring narrative</span><p>{{ row.report.monitoring_narrative || "Not recorded" }}</p></div>
                      </div>

                      <div v-if="row.report.review_notes" class="review-note">
                        <strong>Latest review remarks</strong>
                        <p>{{ row.report.review_notes }}</p>
                      </div>

                      <form
                        v-if="row.report.status === 'submitted'"
                        class="inline-review-form"
                        @submit.prevent="reviewReport(row, 'accepted')"
                      >
                        <label :for="`review-remarks-${row.report.id}`">Review remarks</label>
                        <textarea
                          :id="`review-remarks-${row.report.id}`"
                          v-model.trim="reviewRemarks[row.report.id]"
                          rows="2"
                          placeholder="Required when returning the report"
                          @click.stop
                        />
                        <div class="review-actions">
                          <button
                            class="button danger"
                            type="button"
                            :disabled="reviewingReportId === row.report.id"
                            @click.stop="reviewReport(row, 'returned')"
                          >
                            <RotateCcw /> Return for correction
                          </button>
                          <button
                            class="button primary"
                            type="submit"
                            :disabled="reviewingReportId === row.report.id"
                            @click.stop
                          >
                            <CheckCircle2 /> Accept report
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <footer class="pagination">
        <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
        <div>
          <button class="icon-button" type="button" aria-label="Previous page" :disabled="pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)"><ChevronLeft /></button>
          <button class="icon-button" type="button" aria-label="Next page" :disabled="pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)"><ChevronRight /></button>
        </div>
      </footer>
    </section>

    <section v-else class="cycle-list" aria-label="Monitoring cycles">
      <article v-for="cycle in cycles" :key="cycle.id" class="cycle-card">
        <header class="cycle-head">
          <div class="project-identity">
            <span>{{ cycle.project.project_code }} · {{ cycle.project.record_type_label }} · {{ cycle.project.investment_status_label }}</span>
            <h2>{{ cycle.project.title }}</h2>
            <p>{{ cycle.project.proponent_name || "No proponent recorded" }}</p>
          </div>
          <div class="cycle-meta">
            <strong>Q{{ cycle.quarter }} {{ cycle.reporting_year }}</strong
            ><span>{{ formatRange(cycle.period_start, cycle.period_end) }}</span
            ><span v-if="cycle.project.project_officer"
              >Officer: {{ cycle.project.project_officer.full_name }}</span
            >
          </div>
          <div class="due-block" :class="{ overdue: isOverdue(cycle) }">
            <span>{{
              cycle.status === "closed" ? "Closed period" : "Compliance due"
            }}</span
            ><strong>{{
              cycle.status === "closed"
                ? formatDate(cycle.closed_at)
                : formatDate(cycle.due_date)
            }}</strong
            ><small>{{
              cycle.status === "closed"
                ? "Archived in history"
                : dueLabel(cycle)
            }}</small>
          </div>
          <div class="cycle-actions">
            <button
              v-if="canAddReport(cycle)"
              class="button primary"
              type="button"
              @click="openNewReport(cycle)"
            >
              <Plus /> Add report
            </button>
          </div>
        </header>
        <p v-if="cycle.instructions" class="instructions">
          <Info /> {{ cycle.instructions }}
        </p>
        <div v-if="cycle.reports_list?.length" class="report-list">
          <button
            v-for="report in cycle.reports_list"
            :key="report.id"
            class="report-row"
            type="button"
            @click="openReport(cycle, report)"
          >
            <span class="tile-icon"><component :is="typeIcon(report.compliance_type)" /></span>
            <span class="report-main">
              <strong>{{ typeLabel(report.compliance_type) }} report</strong>
              <small>{{ reportPeriod(report) }}</small>
            </span>
            <span class="report-summary">{{ reportSummary(report) }}</span>
            <span class="status-badge" :class="report.status">{{ statusLabel(report.status) }}</span>
            <ChevronRight />
          </button>
        </div>
        <div v-else class="empty-report-list">
          <ClipboardCheck />
          <strong>No reports submitted for this project yet</strong>
          <span>Select Add report to submit Employment, Financial, or Progress compliance.</span>
        </div>
      </article>
      <footer class="pagination">
        <span
          >Page {{ pagination.current_page }} of
          {{ pagination.last_page }}</span
        >
        <div>
          <button
            class="icon-button"
            type="button"
            aria-label="Previous page"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >
            <ChevronLeft /></button
          ><button
            class="icon-button"
            type="button"
            aria-label="Next page"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            <ChevronRight />
          </button>
        </div>
      </footer>
    </section>

    <Teleport to="body">
      <div
        v-if="showReportLauncher"
        class="launcher-overlay"
        :class="{ 'is-dark': isDark }"
        @mousedown.self="closeReportLauncher"
        @keydown.esc="closeReportLauncher"
      >
        <section class="launcher-dialog" role="dialog" aria-modal="true" aria-labelledby="launcher-title">
          <header>
            <div>
              <p class="eyebrow">Monitoring compliance</p>
              <h2 id="launcher-title">Add report</h2>
              <p>Choose the project and report type before entering the compliance details.</p>
            </div>
            <button ref="launcherCloseButton" class="icon-button" type="button" aria-label="Close" @click="closeReportLauncher"><X /></button>
          </header>

          <div class="launcher-body">
            <div v-if="launcherLoading" class="launcher-state">Loading open monitoring projects...</div>
            <div v-else-if="!launcherCycles.length" class="launcher-state">
              <ClipboardCheck />
              <strong>No open monitoring projects</strong>
              <span>Open a monitoring period from the project workspace first.</span>
            </div>
            <template v-else>
              <label for="launcher-project">Project and monitoring period</label>
              <select id="launcher-project" v-model.number="launcherCycleId" @change="syncLauncherType">
                <option v-for="cycle in launcherCycles" :key="cycle.id" :value="cycle.id">
                  {{ cycle.project.project_code }} · {{ cycle.project.title }} · Q{{ cycle.quarter }} {{ cycle.reporting_year }}
                </option>
              </select>

              <fieldset class="launcher-types">
                <legend>Report type</legend>
                <label v-for="type in launcherAvailableTypes" :key="type">
                  <input v-model="launcherType" type="radio" name="launcher_report_type" :value="type" />
                  <span><component :is="typeIcon(type)" />{{ typeLabel(type) }}</span>
                </label>
              </fieldset>
            </template>
          </div>

          <footer>
            <button class="button secondary" type="button" @click="closeReportLauncher">Cancel</button>
            <button class="button primary" type="button" :disabled="!selectedLauncherCycle || !launcherType" @click="continueReportCreation">
              Continue <ChevronRight />
            </button>
          </footer>
        </section>
      </div>
    </Teleport>

    <MonitoringReportModal
      v-if="activeCycle"
      :open="showModal"
      :cycle="activeCycle"
      :type="activeType"
      :report="activeReport"
      :is-manager="isManager"
      :is-dark="isDark"
      @close="closeModal"
      @saved="handleSaved"
    />
  </main>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  Activity,
  Briefcase,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  Clock3,
  DollarSign,
  Download,
  FileText,
  History,
  Info,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  X,
} from "lucide-vue-next";
import { toast } from "vue3-toastify";
import axiosInstance from "@/utils/axiosInstance";
import { useLayoutStore } from "@/store/layout";
import { useAuthStore } from "@/store/auth";
import { SITE_MODE } from "@/app/const";
import MonitoringReportModal, {
  type ComplianceType,
  type MonitoringCycle,
} from "./components/MonitoringReportModal.vue";
const route = useRoute();
const router = useRouter();
const layoutStore = useLayoutStore();
const authStore = useAuthStore();
const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK);
const role = computed(() => authStore.userRole.toLowerCase());
const isManager = computed(
  () =>
    ["superadmin", "admin"].includes(role.value) ||
    authStore.can("projects", "update"),
);
const loading = ref(false);
const exporting = ref(false);
const exportingPdf = ref(false);
const showModal = ref(false);
const cycles = ref<MonitoringCycle[]>([]);
const activeCycle = ref<MonitoringCycle | null>(null);
const activeType = ref<ComplianceType | null>(null);
const activeReport = ref<any | null>(null);
const showReportLauncher = ref(false);
const launcherLoading = ref(false);
const launcherCycles = ref<MonitoringCycle[]>([]);
const launcherCycleId = ref<number | null>(null);
const launcherType = ref<ComplianceType | null>(null);
const launcherCloseButton = ref<HTMLButtonElement | null>(null);
const expandedRowKey = ref<string | null>(null);
const reviewingReportId = ref<number | null>(null);
const reviewRemarks = reactive<Record<number, string>>({});
const scope = ref<"active" | "history">(
  (route.query.scope as any) === "history" ? "history" : "active",
);
const page = ref(1);
const filters = reactive({
  record_type: "",
  investment_status: "",
  search: "",
  year: "",
  quarter: "",
  compliance_type: "",
  status: "",
});
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const summary = ref({
  active_cycles: 0,
  needs_review: 0,
  returned: 0,
  missing: 0,
  overdue: 0,
});
const currentYear = new Date().getFullYear();
const availableYears = Array.from(
  { length: 8 },
  (_, index) => currentYear + 1 - index,
);
const summaryCards = computed(() => [
  {
    label: "Open periods",
    value: summary.value.active_cycles,
    icon: Activity,
    tone: "blue",
  },
  {
    label: "Reports to add",
    value: summary.value.missing,
    icon: Plus,
    tone: "amber",
  },
  {
    label: "Needs review",
    value: summary.value.needs_review,
    icon: Clock3,
    tone: "violet",
  },
  {
    label: "Overdue periods",
    value: summary.value.overdue,
    icon: RotateCcw,
    tone: "red",
  },
]);
const managerTypeTabs = [
  { value: "", label: "All", icon: null },
  { value: "employment", label: "Employment", icon: Briefcase },
  { value: "financial", label: "Financial", icon: DollarSign },
  { value: "progress", label: "Progress", icon: FileText },
] as const;
type ManagerReportRow = {
  key: string;
  type: ComplianceType;
  cycle: MonitoringCycle;
  report: any;
  overdue: boolean;
};
const managerRows = computed<ManagerReportRow[]>(() =>
  cycles.value.flatMap((cycle) =>
    (cycle.reports_list || [])
      .filter(
        (report) =>
          (!filters.compliance_type ||
            report.compliance_type === filters.compliance_type) &&
          (!filters.status || report.status === filters.status),
      )
      .map((report) => ({
        key: `${cycle.id}-${report.compliance_type}-${report.id}`,
        type: report.compliance_type,
        cycle,
        report,
        overdue: isReportOverdue(cycle, report),
      }))),
);
const selectedLauncherCycle = computed(
  () =>
    launcherCycles.value.find((cycle) => cycle.id === launcherCycleId.value) ||
    null,
);
const launcherAvailableTypes = computed<ComplianceType[]>(() =>
  selectedLauncherCycle.value
    ? ["employment", "financial", "progress"]
    : [],
);
async function loadCycles() {
  loading.value = true;
  try {
    const response = await axiosInstance.get("/api/monitoring-reports", {
      params: {
        view: "grouped",
        scope: scope.value,
        page: page.value,
        per_page: 15,
        project_id: route.query.project_id || undefined,
        ...filters,
      },
    });
    cycles.value = response.data?.data || [];
    summary.value = { ...summary.value, ...(response.data?.summary || {}) };
    pagination.value = { ...pagination.value, ...(response.data?.meta || {}) };
    const requested = Number(route.query.report_id || 0);
    if (requested) {
      for (const cycle of cycles.value) {
        const report = cycle.reports_list?.find((item: any) => item.id === requested);
        if (report) {
          if (isManager.value) {
            expandedRowKey.value = `${cycle.id}-${report.compliance_type}-${report.id}`;
          } else {
            openReport(cycle, report, false);
          }
          break;
        }
      }
    }
  } catch (error: any) {
    toast.error(
      error?.response?.data?.message || "Failed to load monitoring compliance.",
    );
  } finally {
    loading.value = false;
  }
}
function changeScope(value: "active" | "history") {
  scope.value = value;
  page.value = 1;
  router.replace({
    query: { ...route.query, scope: value, report_id: undefined },
  });
  loadCycles();
}
function applyFilters() {
  page.value = 1;
  loadCycles();
}
function resetFilters() {
  Object.assign(filters, {
    record_type: "",
    investment_status: "",
    search: "",
    year: "",
    quarter: "",
    compliance_type: "",
    status: "",
  });
  applyFilters();
}
function changePage(value: number) {
  page.value = value;
  loadCycles();
}
function openNewReport(cycle: MonitoringCycle) {
  activeCycle.value = cycle;
  activeType.value = null;
  activeReport.value = null;
  showModal.value = true;
}
function selectReportType(value: string) {
  filters.compliance_type = value;
  applyFilters();
}
async function openReportLauncher() {
  showReportLauncher.value = true;
  launcherLoading.value = true;
  launcherCycles.value = [];
  launcherCycleId.value = null;
  launcherType.value = null;
  await nextTick();
  launcherCloseButton.value?.focus();

  try {
    const response = await axiosInstance.get("/api/monitoring-reports", {
      params: { view: "grouped", scope: "active", page: 1, per_page: 50 },
    });
    launcherCycles.value = response.data?.data || [];
    launcherCycleId.value = launcherCycles.value[0]?.id || null;
    syncLauncherType();
  } catch (error: any) {
    toast.error(
      error?.response?.data?.message || "Failed to load monitoring projects.",
    );
  } finally {
    launcherLoading.value = false;
  }
}
function syncLauncherType() {
  launcherType.value = launcherAvailableTypes.value[0] || null;
}
function closeReportLauncher() {
  showReportLauncher.value = false;
}
function continueReportCreation() {
  if (!selectedLauncherCycle.value || !launcherType.value) return;
  activeCycle.value = selectedLauncherCycle.value;
  activeType.value = launcherType.value;
  activeReport.value = null;
  showReportLauncher.value = false;
  showModal.value = true;
}
function openReport(cycle: MonitoringCycle, report: any, updateRoute = true) {
  if (isManager.value) {
    toggleManagerRow({
      key: `${cycle.id}-${report.compliance_type}-${report.id}`,
      type: report.compliance_type,
      cycle,
      report,
      overdue: isReportOverdue(cycle, report),
    });
    return;
  }
  activeCycle.value = cycle;
  activeType.value = report.compliance_type;
  activeReport.value = report;
  showModal.value = true;
  if (updateRoute && report)
    router.replace({ query: { ...route.query, report_id: String(report.id) } });
}
function toggleManagerRow(row: ManagerReportRow) {
  if (!row.report) return;
  const opening = expandedRowKey.value !== row.key;
  expandedRowKey.value = opening ? row.key : null;
  const query = { ...route.query };
  if (opening && row.report) query.report_id = String(row.report.id);
  else delete query.report_id;
  router.replace({ query });
}
async function reviewReport(
  row: ManagerReportRow,
  action: "accepted" | "returned",
) {
  if (!row.report) return;
  const remarks = reviewRemarks[row.report.id]?.trim() || "";
  if (action === "returned" && !remarks) {
    toast.error("Add review remarks before returning the report.");
    return;
  }
  reviewingReportId.value = row.report.id;
  try {
    await axiosInstance.post(`/api/monitoring-reports/${row.report.id}/review`, {
      action,
      remarks: remarks || null,
    });
    toast.success(
      action === "accepted"
        ? "Compliance report accepted."
        : "Report returned for correction.",
    );
    await loadCycles();
  } catch (error: any) {
    toast.error(
      error?.response?.data?.message || "Failed to review compliance report.",
    );
  } finally {
    reviewingReportId.value = null;
  }
}
function closeModal() {
  showModal.value = false;
  activeCycle.value = null;
  activeType.value = null;
  activeReport.value = null;
  if (route.query.report_id) {
    const query = { ...route.query };
    delete query.report_id;
    router.replace({ query });
  }
}
async function handleSaved() {
  await loadCycles();
}
async function exportReports() {
  exporting.value = true;
  try {
    const response = await axiosInstance.get("/api/monitoring-reports/export", {
      params: { scope: scope.value, ...filters },
      responseType: "blob",
    });
    const url = URL.createObjectURL(response.data);
    const link = document.createElement("a");
    link.href = url;
    link.download = `ndc-monitoring-compliance-${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toast.success("Monitoring compliance workbook generated.");
  } catch (error: any) {
    toast.error(error?.response?.data?.message || "Failed to export reports.");
  } finally {
    exporting.value = false;
  }
}
async function exportReportsPdf() {
  exportingPdf.value = true;
  try {
    const response = await axiosInstance.get("/api/monitoring-reports/export/pdf", {
      params: { scope: scope.value, ...filters },
      responseType: "blob",
    });
    const url = URL.createObjectURL(response.data);
    const link = document.createElement("a");
    link.href = url;
    link.download = `ndc-monitoring-compliance-${new Date().toISOString().slice(0, 10)}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toast.success("Monitoring compliance PDF generated.");
  } catch (error: any) {
    toast.error(error?.response?.data?.message || "Failed to export PDF.");
  } finally {
    exportingPdf.value = false;
  }
}
function canAddReport(cycle: MonitoringCycle) {
  return cycle.status === "open" && (isManager.value || cycle.project.monitoring_proponent_access);
}
function typeLabel(type: ComplianceType) {
  return {
    employment: "Employment",
    financial: "Financial",
    progress: "Progress",
  }[type];
}
function typeIcon(type: ComplianceType) {
  return { employment: Briefcase, financial: DollarSign, progress: FileText }[
    type
  ];
}
function statusLabel(status: string) {
  return (
    (
      {
        draft: "Legacy draft",
        submitted: "Needs review",
        returned: "Returned",
        accepted: "Accepted",
      } as Record<string, string>
    )[status] || status
  );
}
function formatDate(value?: string | null) {
  return value
    ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
      })
    : "Not set";
}
function formatRange(start?: string | null, end?: string | null) {
  return `${formatDate(start)} - ${formatDate(end)}`;
}
function formatDateTime(value?: string | null) {
  return value
    ? new Date(value.replace(" ", "T")).toLocaleString("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
      })
    : "Not recorded";
}
function number(value?: number | null) {
  return value == null
    ? "Not recorded"
    : new Intl.NumberFormat("en-PH").format(value);
}
function money(value?: number | null) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    minimumFractionDigits: 2,
  }).format(Number(value || 0));
}
function reportPeriod(report: any) {
  if (report.compliance_type === "employment") {
    return `Q${report.quarter} ${report.reporting_year}`;
  }
  if (report.compliance_type === "financial") {
    return formatRange(report.financial_period_start, report.financial_period_end);
  }
  return formatRange(report.narrative_period_start, report.narrative_period_end);
}
function reportSummary(report: any) {
  if (report.compliance_type === "employment") {
    return `${number(report.jobs_generated)} generated · ${number(report.jobs_retained)} retained`;
  }
  if (report.compliance_type === "financial") {
    return `${money(report.revenue)} revenue · ${money(report.remittance)} remittance`;
  }
  return report.milestones || report.monitoring_narrative || "Progress narrative";
}
function isOverdue(cycle: MonitoringCycle) {
  return Boolean(
    cycle.due_date &&
    cycle.status === "open" &&
    cycle.aggregate_status !== "accepted" &&
    new Date(`${cycle.due_date}T23:59:59`) < new Date(),
  );
}
function isReportOverdue(cycle: MonitoringCycle, report: any | null) {
  return Boolean(
    cycle.due_date &&
      cycle.status === "open" &&
      report?.status !== "accepted" &&
      new Date(`${cycle.due_date}T23:59:59`) < new Date(),
  );
}
function dueLabel(cycle: MonitoringCycle) {
  if (!cycle.due_date) return "No due date";
  if (cycle.aggregate_status === "accepted")
    return "All requested reports accepted";
  const days = Math.ceil(
    (new Date(`${cycle.due_date}T23:59:59`).getTime() - Date.now()) / 86400000,
  );
  return days < 0
    ? `${Math.abs(days)} day${Math.abs(days) === 1 ? "" : "s"} overdue`
    : days === 0
      ? "Due today"
      : `Due in ${days} day${days === 1 ? "" : "s"}`;
}
function rowDueLabel(row: ManagerReportRow) {
  if (row.report?.status === "accepted") return "Report accepted";
  if (!row.cycle.due_date) return "No due date";
  if (row.cycle.status === "closed") return "Monitoring period closed";
  const days = Math.ceil(
    (new Date(`${row.cycle.due_date}T23:59:59`).getTime() - Date.now()) /
      86400000,
  );
  return days < 0
    ? `${Math.abs(days)} day${Math.abs(days) === 1 ? "" : "s"} overdue`
    : days === 0
      ? "Due today"
      : `Due in ${days} day${days === 1 ? "" : "s"}`;
}
onMounted(loadCycles);
</script>

<style scoped>
.monitoring-page {
  --bg: #f5f7fb;
  --card: #fff;
  --soft: #f8fafc;
  --border: #dbe3ee;
  --text: #0f172a;
  --muted: #64748b;
  min-height: 100%;
  padding: 2rem;
  background: var(--bg);
  color: var(--text);
}
.monitoring-page.is-dark {
  --bg: #0b1220;
  --card: #111c2f;
  --soft: #162238;
  --border: #2b3a52;
  --text: #f1f5f9;
  --muted: #94a3b8;
}
.page-head,
.page-actions,
.scope-tabs,
.cycle-head,
.tile-head,
.pagination,
.pagination > div {
  display: flex;
  align-items: center;
}
.page-head,
.cycle-head,
.pagination {
  justify-content: space-between;
}
.page-head {
  align-items: flex-start;
  gap: 1rem;
}
.page-head h1 {
  margin: 0;
  font-size: 1.75rem;
  letter-spacing: 0;
}
.page-head p:last-child {
  margin: 0.35rem 0 0;
  color: var(--muted);
}
.eyebrow {
  margin: 0 0 0.2rem;
  color: #2563eb;
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.page-actions {
  gap: 0.55rem;
}
.page-actions .button,
.filter-actions .button {
  white-space: nowrap;
}
.button,
.icon-button {
  display: inline-flex;
  min-height: 2.6rem;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 0 0.8rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--card);
  color: var(--text);
  font-weight: 750;
  cursor: pointer;
}
.button svg,
.icon-button svg {
  width: 1rem;
}
.icon-button {
  width: 2.6rem;
  padding: 0;
}
.button:disabled,
.icon-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.button.primary {
  border-color: #2563eb;
  background: #2563eb;
  color: #fff;
}
.button.danger {
  border-color: #fecaca;
  background: var(--card);
  color: #b91c1c;
}
.button:focus-visible,
.icon-button:focus-visible,
.scope-tabs button:focus-visible,
.project-trigger:focus-visible,
.table-scroll:focus-visible {
  outline: 3px solid color-mix(in srgb, #2563eb 32%, transparent);
  outline-offset: 2px;
}
.scope-tabs {
  width: max-content;
  margin: 1.1rem 0;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--card);
  overflow: hidden;
}
.scope-tabs button {
  display: flex;
  min-height: 2.65rem;
  align-items: center;
  gap: 0.45rem;
  padding: 0 0.9rem;
  border: 0;
  border-right: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  font-weight: 750;
  cursor: pointer;
}
.scope-tabs button:last-child {
  border-right: 0;
}
.scope-tabs button.active {
  background: #2563eb;
  color: #fff;
}
.scope-tabs svg {
  width: 1rem;
}
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.summary-grid article {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--card);
}
.summary-grid article div {
  display: grid;
}
.summary-grid strong {
  font-size: 1.35rem;
}
.summary-grid article span:last-child {
  color: var(--muted);
  font-size: 0.75rem;
}
.summary-icon {
  display: grid;
  width: 2.3rem;
  height: 2.3rem;
  place-items: center;
  border-radius: 0.4rem;
}
.summary-icon svg {
  width: 1.05rem;
}
.summary-icon.blue {
  background: #dbeafe;
  color: #2563eb;
}
.summary-icon.amber {
  background: #fef3c7;
  color: #d97706;
}
.summary-icon.violet {
  background: #ede9fe;
  color: #6d28d9;
}
.summary-icon.red {
  background: #fee2e2;
  color: #dc2626;
}
.filter-bar {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
  gap: 0.65rem;
  align-items: end;
  padding: 0.8rem;
  margin-bottom: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--card);
}
.filter-bar.is-manager {
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
}
.filter-actions {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}
.filter-bar label {
  display: grid;
  min-width: 0;
  gap: 0.3rem;
  color: var(--muted);
  font-size: 0.7rem;
  font-weight: 750;
}
.filter-bar select {
  width: 100%;
  min-width: 0;
  min-height: 2.55rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
  color: var(--text);
}
.search-box {
  display: flex !important;
  min-width: 0;
  align-items: center;
  gap: 0.5rem;
  min-height: 2.55rem;
  padding: 0 0.7rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
}
.search-box svg {
  width: 1rem;
}
.search-box input {
  width: 100%;
  min-width: 0;
  flex: 1 1 auto;
  border: 0;
  background: transparent;
  color: var(--text);
  outline: 0;
}
.state-card {
  display: grid;
  min-height: 16rem;
  place-items: center;
  align-content: center;
  gap: 0.5rem;
  padding: 2rem;
  border: 1px dashed var(--border);
  border-radius: 0.45rem;
  background: var(--card);
  color: var(--muted);
  text-align: center;
}
.state-card svg {
  width: 2rem;
}
.state-card strong {
  color: var(--text);
}
.cycle-list {
  display: grid;
  gap: 1rem;
}
.cycle-card {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--card);
}
.cycle-head {
  display: grid;
  grid-template-columns: minmax(16rem, 1.5fr) minmax(13rem, 1fr) minmax(
      10rem,
      0.7fr
    ) auto;
  gap: 1rem;
  padding: 1rem 1.1rem;
  border-bottom: 1px solid var(--border);
}
.project-identity span {
  color: #2563eb;
  font-size: 0.68rem;
  font-weight: 800;
}
.project-identity h2 {
  margin: 0.18rem 0;
  font-size: 1rem;
}
.project-identity p,
.cycle-meta span,
.due-block span,
.due-block small {
  margin: 0;
  color: var(--muted);
  font-size: 0.72rem;
}
.cycle-meta,
.due-block {
  display: grid;
  align-content: center;
}
.cycle-meta strong,
.due-block strong {
  font-size: 0.83rem;
}
.due-block {
  text-align: right;
}
.cycle-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}
.due-block.overdue strong,
.due-block.overdue small {
  color: #dc2626;
}
.instructions {
  display: flex;
  gap: 0.5rem;
  margin: 0;
  padding: 0.75rem 1.1rem;
  border-bottom: 1px solid var(--border);
  background: var(--soft);
  color: var(--muted);
  font-size: 0.75rem;
  line-height: 1.4;
}
.instructions svg {
  width: 1rem;
  flex: none;
}
.compliance-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 1rem;
}
.compliance-tile {
  display: grid;
  gap: 0.8rem;
  min-width: 0;
  min-height: 10rem;
  padding: 0.9rem;
  border: 1px solid var(--border);
  border-top: 3px solid #94a3b8;
  border-radius: 0.4rem;
  background: var(--card);
  color: var(--text);
  text-align: left;
  cursor: pointer;
}
.compliance-tile.employment {
  border-top-color: #16a34a;
}
.compliance-tile.financial {
  border-top-color: #d97706;
}
.compliance-tile.progress {
  border-top-color: #2563eb;
}
.compliance-tile:disabled {
  cursor: default;
}
.tile-head {
  gap: 0.6rem;
}
.tile-head > div {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
}
.tile-head > svg {
  width: 1rem;
  color: var(--muted);
}
.tile-icon {
  display: grid;
  width: 2rem;
  height: 2rem;
  flex: none;
  place-items: center;
  border-radius: 0.35rem;
  background: var(--soft);
}
.tile-icon svg {
  width: 1rem;
}
.tile-content,
.missing-content {
  display: grid;
  align-content: start;
}
.tile-content strong {
  overflow: hidden;
  margin-top: 0.3rem;
  font-size: 0.84rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tile-content span,
.missing-content span {
  overflow: hidden;
  color: var(--muted);
  font-size: 0.7rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.missing-content strong {
  margin-top: 0.35rem;
  color: #2563eb;
  font-size: 0.78rem;
}
.status-badge {
  display: inline-flex;
  padding: 0.2rem 0.4rem;
  border-radius: 999px;
  font-size: 0.59rem;
  font-weight: 800;
  white-space: nowrap;
}
.status-badge.missing,
.status-badge.draft {
  background: #e2e8f0;
  color: #475569;
}
.status-badge.submitted {
  background: #ede9fe;
  color: #6d28d9;
}
.status-badge.returned {
  background: #fee2e2;
  color: #b91c1c;
}
.status-badge.accepted {
  background: #dcfce7;
  color: #15803d;
}
.report-list {
  display: grid;
  gap: 0.55rem;
  padding: 0.9rem 1rem 1rem;
}
.report-row {
  display: grid;
  grid-template-columns: auto minmax(12rem, 1.2fr) minmax(12rem, 1fr) auto auto;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
  padding: 0.8rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
  color: var(--text);
  text-align: left;
  cursor: pointer;
}
.report-row:hover,
.report-row:focus-visible {
  border-color: #93c5fd;
  outline: none;
}
.report-row > svg {
  width: 1rem;
  color: var(--muted);
}
.report-main {
  display: grid;
  min-width: 0;
  gap: 0.15rem;
}
.report-main strong,
.report-main small,
.report-summary {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.report-main strong {
  font-size: 0.86rem;
}
.report-main small,
.report-summary,
.empty-report-list span {
  color: var(--muted);
  font-size: 0.72rem;
}
.empty-report-list {
  display: grid;
  min-height: 8.5rem;
  place-items: center;
  align-content: center;
  gap: 0.35rem;
  margin: 1rem;
  padding: 1.5rem;
  border: 1px dashed var(--border);
  border-radius: 0.45rem;
  background: var(--soft);
  color: var(--muted);
  text-align: center;
}
.empty-report-list svg {
  width: 1.8rem;
}
.empty-report-list strong {
  color: var(--text);
}
.register-shell {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--card);
}
.register-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.8rem 1rem;
  border-bottom: 1px solid var(--border);
}
.register-head h2,
.register-head p {
  margin: 0;
}
.register-head h2 {
  font-size: 0.95rem;
}
.register-head p,
.register-hint {
  margin-top: 0.15rem;
  color: var(--muted);
  font-size: 0.7rem;
}
.report-type-tabs {
  display: flex;
  align-items: center;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
  overflow: hidden;
}
.report-type-tabs button {
  display: inline-flex;
  min-height: 2.15rem;
  align-items: center;
  gap: 0.3rem;
  padding: 0 0.65rem;
  border: 0;
  border-right: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  font-size: 0.68rem;
  font-weight: 750;
  cursor: pointer;
}
.report-type-tabs button:last-child {
  border-right: 0;
}
.report-type-tabs button.active {
  background: #2563eb;
  color: #fff;
}
.report-type-tabs button:focus-visible {
  position: relative;
  z-index: 1;
  outline: 3px solid color-mix(in srgb, #2563eb 35%, transparent);
  outline-offset: -3px;
}
.report-type-tabs svg {
  width: 0.85rem;
}
.table-scroll {
  max-width: 100%;
  overflow: auto;
  scrollbar-gutter: stable;
  overscroll-behavior-inline: contain;
}
.compliance-table {
  width: 100%;
  min-width: 70rem;
  border-collapse: collapse;
  table-layout: fixed;
}
.compliance-table th {
  padding: 0.65rem 0.8rem;
  border-bottom: 1px solid var(--border);
  background: var(--soft);
  color: var(--muted);
  font-size: 0.64rem;
  font-weight: 800;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.compliance-table th:nth-child(1) { width: 24%; }
.compliance-table th:nth-child(2) { width: 14%; }
.compliance-table th:nth-child(3) { width: 14%; }
.compliance-table th:nth-child(4) { width: 20%; }
.compliance-table th:nth-child(5) { width: 14%; }
.compliance-table th:nth-child(6) { width: 14%; }
.register-row {
  cursor: default;
  transition: background-color 0.15s ease;
}
.register-row.has-report {
  cursor: pointer;
}
.register-row:hover,
.register-row.expanded {
  background: color-mix(in srgb, #2563eb 6%, var(--card));
}
.register-row.overdue {
  box-shadow: inset 3px 0 #dc2626;
}
.register-row td {
  padding: 0.72rem 0.8rem;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.register-row td > strong,
.register-row td > small,
.project-trigger strong,
.project-trigger span {
  display: block;
}
.register-row td > strong,
.project-trigger strong,
.report-type {
  color: var(--text);
  font-size: 0.76rem;
  font-weight: 800;
}
.register-row td > small,
.project-trigger span,
.register-row td:first-child > small,
.summary-cell span {
  margin-top: 0.15rem;
  color: var(--muted);
  font-size: 0.67rem;
  line-height: 1.35;
}
.project-trigger {
  display: block;
  width: 100%;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}
.project-trigger strong,
.summary-cell span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.project-trigger:hover strong {
  color: #2563eb;
}
.project-trigger:disabled {
  cursor: default;
}
.project-trigger:disabled:hover strong {
  color: var(--text);
}
.report-type {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.report-type svg {
  width: 0.9rem;
  color: #2563eb;
}
.danger {
  color: #dc2626 !important;
  font-weight: 750;
}
.register-row td:last-child {
  position: relative;
  padding-right: 2rem;
}
.row-chevron {
  position: absolute;
  top: 50%;
  right: 0.7rem;
  width: 0.9rem;
  color: var(--muted);
  transform: translateY(-50%);
  transition: transform 0.15s ease;
}
.register-row.expanded .row-chevron {
  transform: translateY(-50%) rotate(180deg);
}
.detail-row > td {
  padding: 0;
  border-bottom: 1px solid var(--border);
  background: var(--soft);
}
.inline-review {
  padding: 1rem 1.2rem 1.1rem;
  box-shadow: inset 3px 0 #2563eb;
}
.inline-review-head,
.submission-facts,
.review-actions {
  display: flex;
  align-items: center;
}
.inline-review-head {
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.8rem;
}
.inline-review-head h3 {
  margin: 0.1rem 0 0;
  font-size: 0.9rem;
}
.submission-facts {
  align-items: flex-end;
  flex-direction: column;
  color: var(--muted);
  font-size: 0.68rem;
}
.inline-empty {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.8rem;
  border: 1px dashed var(--border);
  border-radius: 0.4rem;
  background: var(--card);
}
.inline-empty svg {
  width: 1.25rem;
  color: var(--muted);
}
.inline-empty p,
.review-note p {
  margin: 0.15rem 0 0;
  color: var(--muted);
  font-size: 0.7rem;
}
.inline-metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin: 0;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--card);
  overflow: hidden;
}
.inline-metrics.employment {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.inline-metrics div {
  padding: 0.7rem 0.8rem;
  border-right: 1px solid var(--border);
}
.inline-metrics div:last-child {
  border-right: 0;
}
.inline-metrics dt,
.inline-narratives span {
  color: var(--muted);
  font-size: 0.64rem;
  font-weight: 750;
}
.inline-metrics dd {
  margin: 0.2rem 0 0;
  font-size: 0.86rem;
  font-weight: 800;
}
.inline-narratives {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}
.inline-narratives div {
  min-width: 0;
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--card);
}
.inline-narratives p {
  margin: 0.3rem 0 0;
  color: var(--text);
  font-size: 0.72rem;
  line-height: 1.45;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.review-note {
  margin-top: 0.75rem;
  padding: 0.65rem 0.75rem;
  border-left: 3px solid #d97706;
  background: color-mix(in srgb, #f59e0b 9%, var(--card));
}
.inline-review-form {
  display: grid;
  grid-template-columns: minmax(14rem, 1fr) auto;
  gap: 0.35rem 0.75rem;
  align-items: end;
  margin-top: 0.8rem;
  padding-top: 0.8rem;
  border-top: 1px solid var(--border);
}
.inline-review-form label {
  grid-column: 1;
  color: var(--muted);
  font-size: 0.68rem;
  font-weight: 750;
}
.inline-review-form textarea {
  grid-column: 1;
  width: 100%;
  min-height: 4.2rem;
  resize: vertical;
  padding: 0.65rem 0.7rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--card);
  color: var(--text);
  font: inherit;
  color-scheme: light;
}
.monitoring-page.is-dark .inline-review-form textarea {
  color-scheme: dark;
}
.inline-review-form textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px color-mix(in srgb, #2563eb 18%, transparent);
}
.review-actions {
  grid-column: 2;
  grid-row: 1 / span 2;
  gap: 0.5rem;
}
.register-shell > .pagination {
  border: 0;
  border-top: 1px solid var(--border);
  border-radius: 0;
}
.launcher-overlay {
  --card: #fff;
  --soft: #f8fafc;
  --border: #dbe3ee;
  --text: #0f172a;
  --muted: #64748b;
  position: fixed;
  inset: 0;
  z-index: 110;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgba(2, 6, 23, 0.65);
  backdrop-filter: blur(3px);
}
.launcher-overlay.is-dark {
  --card: #111c2f;
  --soft: #162238;
  --border: #2b3a52;
  --text: #f1f5f9;
  --muted: #94a3b8;
  color-scheme: dark;
}
.launcher-dialog {
  width: min(35rem, 100%);
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--card);
  color: var(--text);
  box-shadow: 0 24px 60px rgba(2, 6, 23, 0.3);
}
.launcher-dialog > header,
.launcher-dialog > footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.1rem;
}
.launcher-dialog > header {
  align-items: flex-start;
  border-bottom: 1px solid var(--border);
}
.launcher-dialog > header h2 {
  margin: 0;
  font-size: 1.1rem;
}
.launcher-dialog > header p:last-child {
  margin: 0.25rem 0 0;
  color: var(--muted);
  font-size: 0.73rem;
}
.launcher-dialog > footer {
  justify-content: flex-end;
  border-top: 1px solid var(--border);
  background: var(--soft);
}
.launcher-body {
  display: grid;
  gap: 0.55rem;
  padding: 1.1rem;
}
.launcher-body > label,
.launcher-types legend {
  color: var(--muted);
  font-size: 0.68rem;
  font-weight: 800;
}
.launcher-body > select {
  width: 100%;
  min-height: 2.8rem;
  padding: 0 0.7rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
  color: var(--text);
  font: inherit;
}
.launcher-body > select:focus,
.launcher-types input:focus-visible + span {
  outline: 3px solid color-mix(in srgb, #2563eb 22%, transparent);
  outline-offset: 2px;
}
.launcher-types {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  margin: 0.5rem 0 0;
  padding: 0;
  border: 0;
}
.launcher-types legend {
  grid-column: 1 / -1;
  margin-bottom: 0.15rem;
}
.launcher-types label {
  cursor: pointer;
}
.launcher-types input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.launcher-types span {
  display: flex;
  min-height: 3rem;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 800;
}
.launcher-types input:checked + span {
  border-color: #2563eb;
  background: color-mix(in srgb, #2563eb 10%, var(--card));
  color: #2563eb;
}
.launcher-types svg {
  width: 0.95rem;
}
.launcher-state {
  display: grid;
  min-height: 9rem;
  place-items: center;
  align-content: center;
  gap: 0.35rem;
  color: var(--muted);
  text-align: center;
}
.launcher-state svg {
  width: 1.5rem;
}
.launcher-state strong {
  color: var(--text);
}
.launcher-state span {
  margin: 0;
  color: var(--muted);
  font-size: 0.7rem;
}
.pagination {
  padding: 0.8rem 1rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--card);
  color: var(--muted);
  font-size: 0.72rem;
}
.pagination > div {
  gap: 0.4rem;
}
.spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
@media (max-width: 1200px) {
  .filter-bar {
    grid-template-columns: 1fr repeat(2, 9rem);
  }
  .filter-bar.is-manager {
    grid-template-columns: repeat(3, minmax(8rem, 1fr)) auto;
  }
  .search-box {
    grid-column: 1/-1;
  }
  .compliance-grid {
    grid-template-columns: 1fr;
  }
  .compliance-tile {
    min-height: auto;
  }
  .cycle-head {
    grid-template-columns: 1fr 1fr;
  }
  .due-block {
    grid-column: 2;
    grid-row: 1;
    text-align: right;
  }
  .cycle-meta {
    grid-column: 1/-1;
    grid-row: 2;
  }
  .cycle-actions {
    grid-column: 1/-1;
    justify-content: flex-start;
  }
  .report-row {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }
  .report-summary {
    grid-column: 2/-1;
  }
  .inline-review-form {
    grid-template-columns: 1fr;
  }
  .review-actions {
    grid-column: 1;
    grid-row: auto;
    justify-content: flex-end;
  }
}
@media (max-width: 700px) {
  .monitoring-page {
    padding: 1rem;
  }
  .page-head {
    display: grid;
  }
  .summary-grid {
    grid-template-columns: 1fr 1fr;
  }
  .filter-bar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .filter-bar.is-manager {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .filter-actions {
    grid-column: 1 / -1;
  }
  .filter-actions .button {
    min-width: 0;
    flex: 1;
  }
  .search-box {
    grid-column: 1/-1;
  }
  .cycle-head {
    grid-template-columns: 1fr;
  }
  .due-block,
  .cycle-meta,
  .cycle-actions {
    grid-column: 1;
    grid-row: auto;
    text-align: left;
  }
  .report-row {
    grid-template-columns: auto minmax(0, 1fr);
  }
  .report-row > .status-badge,
  .report-row > svg {
    grid-column: 2;
  }
  .compliance-grid {
    padding: 0.75rem;
  }
  .tile-head > div {
    display: grid;
    justify-content: start;
  }
  .scope-tabs {
    width: 100%;
  }
  .scope-tabs button {
    flex: 1;
    justify-content: center;
  }
  .register-head,
  .inline-review-head {
    align-items: flex-start;
    flex-direction: column;
  }
  .report-type-tabs {
    width: 100%;
    overflow-x: auto;
  }
  .report-type-tabs button {
    flex: 1 0 auto;
    justify-content: center;
  }
  .submission-facts {
    align-items: flex-start;
  }
  .inline-metrics,
  .inline-metrics.employment,
  .inline-narratives {
    grid-template-columns: 1fr;
  }
  .inline-metrics div {
    border-right: 0;
    border-bottom: 1px solid var(--border);
  }
  .inline-metrics div:last-child {
    border-bottom: 0;
  }
  .review-actions {
    align-items: stretch;
    flex-direction: column-reverse;
  }
  .review-actions .button {
    width: 100%;
  }
  .launcher-types {
    grid-template-columns: 1fr;
  }
  .launcher-dialog > footer .button {
    flex: 1;
  }
}
</style>
