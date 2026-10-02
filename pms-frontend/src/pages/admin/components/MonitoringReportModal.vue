<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="overlay"
        :class="{ 'is-dark': isDark }"
        @mousedown.self="requestClose"
        @keydown.esc="requestClose"
      >
        <section
          class="dialog"
          role="dialog"
          aria-modal="true"
          aria-labelledby="compliance-title"
        >
          <header class="dialog-head">
            <div>
              <p class="eyebrow">Project monitoring</p>
              <h2 id="compliance-title">{{ title }}</h2>
              <p>
                {{ cycle.project.project_code }} · {{ cycle.project.title }}
              </p>
            </div>
            <button
              ref="closeButton"
              class="icon-button"
              type="button"
              aria-label="Close"
              @click="requestClose"
            >
              <X />
            </button>
          </header>

          <div class="cycle-strip">
            <span><CalendarRange /> Monitoring submissions are open</span>
            <span :class="{ overdue: isOverdue }"
              ><Clock3 /> {{ dueText }}</span
            >
          </div>

          <div class="dialog-body">
            <section
              v-if="effectiveReport?.status === 'returned'"
              class="notice returned"
            >
              <RotateCcw />
              <div>
                <strong>Returned for correction</strong>
                <p>{{ effectiveReport.review_notes }}</p>
              </div>
            </section>
            <section
              v-else-if="effectiveReport?.status === 'submitted'"
              class="notice submitted"
            >
              <Clock3 />
              <div>
                <strong>Needs NDC review</strong>
                <p>
                  This report is locked until a reviewer accepts or returns it.
                </p>
              </div>
            </section>
            <section
              v-else-if="effectiveReport?.status === 'accepted'"
              class="notice accepted"
            >
              <BadgeCheck />
              <div>
                <strong>Accepted by NDC</strong>
                <p>This report is part of the monitoring history.</p>
              </div>
            </section>

            <form
              v-if="editable"
              id="compliance-form"
              class="report-form"
              @submit.prevent="submitReport"
            >
              <fieldset v-if="!props.report" class="type-picker">
                <legend>Choose report type</legend>
                <label
                  v-for="option in reportTypes"
                  :key="option.value"
                  :class="{ selected: currentType === option.value }"
                >
                  <input
                    v-model="selectedType"
                    type="radio"
                    name="compliance_type"
                    :value="option.value"
                    required
                  />
                  <span class="type-icon"><component :is="option.icon" /></span>
                  <span><strong>{{ option.label }}</strong><small>{{ option.description }}</small></span>
                </label>
              </fieldset>

              <div v-if="currentType" class="section-title">
                <span><component :is="typeIcon" /></span>
                <div>
                  <h3>{{ typeLabel }} compliance</h3>
                  <p>{{ typeDescription }}</p>
                </div>
              </div>

              <template v-if="currentType === 'employment'">
                <fieldset class="quarter-picker">
                  <legend>Reporting quarter</legend>
                  <label><span>Year</span><input v-model.number="form.reporting_year" type="number" min="2000" max="2100" required /></label>
                  <label><span>Quarter</span><select v-model.number="form.quarter" required><option v-for="quarter in 4" :key="quarter" :value="quarter">Q{{ quarter }}</option></select></label>
                  <p>{{ quarterRange }}</p>
                </fieldset>
                <div class="employment-table">
                  <div class="employment-head">
                    <span>Measure</span><strong>Male</strong
                    ><strong>Female</strong><strong>Total</strong>
                  </div>
                  <div class="employment-row">
                    <strong>Jobs generated</strong
                    ><FormattedNumberInput
                      v-model="form.jobs_generated_male"
                      :decimals="0"
                    /><FormattedNumberInput
                      v-model="form.jobs_generated_female"
                      :decimals="0"
                    /><output>{{ number(generatedTotal) }}</output>
                  </div>
                  <div class="employment-row">
                    <strong>Jobs retained</strong
                    ><FormattedNumberInput
                      v-model="form.jobs_retained_male"
                      :decimals="0"
                    /><FormattedNumberInput
                      v-model="form.jobs_retained_female"
                      :decimals="0"
                    /><output>{{ number(retainedTotal) }}</output>
                  </div>
                </div>
              </template>

              <template v-else-if="currentType === 'financial'">
                <DateRange
                  v-model:start="form.financial_period_start"
                  v-model:end="form.financial_period_end"
                  label="Revenue and remittance date range"
                />
                <div class="two-column">
                  <label
                    ><span>Revenue</span
                    ><FormattedNumberInput v-model="form.revenue" /></label
                  ><label
                    ><span>Remittance</span
                    ><FormattedNumberInput v-model="form.remittance"
                  /></label>
                </div>
              </template>

              <template v-else-if="currentType === 'progress'">
                <DateRange
                  v-model:start="form.narrative_period_start"
                  v-model:end="form.narrative_period_end"
                  label="Milestones, impact, and narrative date range"
                />
                <div class="narratives">
                  <label
                    ><span>Period milestones</span
                    ><textarea
                      v-model.trim="form.milestones"
                      rows="4"
                      required
                      maxlength="10000"
                    />
                  </label>
                  <label
                    ><span>Project impact</span
                    ><textarea
                      v-model.trim="form.impact"
                      rows="4"
                      required
                      maxlength="10000"
                    />
                  </label>
                  <label
                    ><span>Monitoring narrative</span
                    ><textarea
                      v-model.trim="form.monitoring_narrative"
                      rows="5"
                      required
                      maxlength="10000"
                    />
                  </label>
                </div>
              </template>
            </form>

            <section v-else class="report-view">
              <article
                v-if="currentType === 'employment'"
                class="metric-grid employment"
              >
                <div>
                  <span>Generated male</span
                  ><strong>{{
                    legacyNumber(effectiveReport?.jobs_generated_male)
                  }}</strong>
                </div>
                <div>
                  <span>Generated female</span
                  ><strong>{{
                    legacyNumber(effectiveReport?.jobs_generated_female)
                  }}</strong>
                </div>
                <div>
                  <span>Generated total</span
                  ><strong>{{ number(effectiveReport?.jobs_generated) }}</strong>
                </div>
                <div>
                  <span>Retained male</span
                  ><strong>{{
                    legacyNumber(effectiveReport?.jobs_retained_male)
                  }}</strong>
                </div>
                <div>
                  <span>Retained female</span
                  ><strong>{{
                    legacyNumber(effectiveReport?.jobs_retained_female)
                  }}</strong>
                </div>
                <div>
                  <span>Retained total</span
                  ><strong>{{ number(effectiveReport?.jobs_retained) }}</strong>
                </div>
              </article>
              <article v-else-if="currentType === 'financial'" class="metric-grid">
                <div>
                  <span>Revenue</span
                  ><strong>{{ money(effectiveReport?.revenue) }}</strong>
                </div>
                <div>
                  <span>Remittance</span
                  ><strong>{{ money(effectiveReport?.remittance) }}</strong>
                </div>
              </article>
              <article v-else class="narrative-view">
                <div>
                  <span>Period milestones</span>
                  <p>{{ effectiveReport?.milestones }}</p>
                </div>
                <div>
                  <span>Project impact</span>
                  <p>{{ effectiveReport?.impact }}</p>
                </div>
                <div>
                  <span>Monitoring narrative</span>
                  <p>{{ effectiveReport?.monitoring_narrative }}</p>
                </div>
              </article>
              <div class="submission-meta">
                <span
                  >Submitted by
                  {{ effectiveReport?.submitted_by?.full_name || "Unknown user" }}</span
                ><span>{{ formatDateTime(effectiveReport?.submitted_at) }}</span>
              </div>
              <section v-if="canReview" class="review-box">
                <label
                  ><span>Review remarks</span
                  ><textarea
                    v-model.trim="reviewRemarks"
                    rows="3"
                    placeholder="Required when returning the report"
                  />
                </label>
                <div>
                  <button
                    class="button danger"
                    type="button"
                    :disabled="busy"
                    @click="review('returned')"
                  >
                    Return</button
                  ><button
                    class="button primary"
                    type="button"
                    :disabled="busy"
                    @click="review('accepted')"
                  >
                    Accept report
                  </button>
                </div>
              </section>
            </section>
          </div>

          <footer class="dialog-footer">
            <button
              class="button secondary"
              type="button"
              @click="requestClose"
            >
              Cancel
            </button>
            <button
              v-if="editable"
              class="button primary"
              type="submit"
              form="compliance-form"
              :disabled="busy || !currentType"
            >
              <Send />
              {{
                busy
                  ? "Submitting..."
                  : effectiveReport?.status === "returned" || effectiveReport?.status === "draft"
                    ? "Resubmit report"
                    : "Submit report"
              }}
            </button>
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import {
  computed,
  defineComponent,
  h,
  nextTick,
  reactive,
  ref,
  watch,
} from "vue";
import {
  BadgeCheck,
  Briefcase,
  CalendarRange,
  Clock3,
  DollarSign,
  FileText,
  RotateCcw,
  Send,
  X,
} from "lucide-vue-next";
import { toast } from "vue3-toastify";
import axiosInstance from "@/utils/axiosInstance";
import FormattedNumberInput from "@/components/common/FormattedNumberInput.vue";

export type ComplianceType = "employment" | "financial" | "progress";
export interface MonitoringReport {
  id: number;
  project_id: number;
  monitoring_cycle_id?: number | null;
  reporting_year: number;
  quarter: number;
  compliance_type: ComplianceType;
  status: "draft" | "submitted" | "returned" | "accepted";
  employment_period_start?: string | null;
  employment_period_end?: string | null;
  financial_period_start?: string | null;
  financial_period_end?: string | null;
  narrative_period_start?: string | null;
  narrative_period_end?: string | null;
  jobs_generated: number;
  jobs_generated_male?: number | null;
  jobs_generated_female?: number | null;
  jobs_retained: number;
  jobs_retained_male?: number | null;
  jobs_retained_female?: number | null;
  revenue: number;
  remittance: number;
  milestones?: string | null;
  impact?: string | null;
  monitoring_narrative?: string | null;
  review_notes?: string | null;
  submitted_at?: string | null;
  submitted_by?: { full_name?: string } | null;
}
export interface MonitoringCycle {
  id: number;
  project_id: number;
  reporting_year: number;
  quarter: number;
  period_start: string;
  period_end: string;
  due_date?: string | null;
  instructions?: string | null;
  requested_compliance_types: ComplianceType[];
  status: "open" | "closed";
  aggregate_status: string;
  project: {
    record_type_label?: string;
    investment_status_label?: string | null;
    id: number;
    project_code: string;
    title: string;
    proponent_name?: string | null;
    monitoring_proponent_access: boolean;
    project_officer?: { id: number; full_name: string } | null;
  };
  reports: Record<ComplianceType, MonitoringReport | null>;
  reports_list?: MonitoringReport[];
}
const props = defineProps<{
  open: boolean;
  cycle: MonitoringCycle;
  type?: ComplianceType | null;
  report: MonitoringReport | null;
  isManager: boolean;
  isDark: boolean;
}>();
const emit = defineEmits<{ close: []; saved: [report: MonitoringReport] }>();
const busy = ref(false);
const selectedType = ref<ComplianceType | null>(null);
const reviewRemarks = ref("");
const closeButton = ref<HTMLButtonElement | null>(null);
const initialSnapshot = ref("");
const form = reactive<any>({
  reporting_year: new Date().getFullYear(),
  quarter: Math.floor(new Date().getMonth() / 3) + 1,
  employment_period_start: "",
  employment_period_end: "",
  financial_period_start: "",
  financial_period_end: "",
  narrative_period_start: "",
  narrative_period_end: "",
  jobs_generated_male: 0,
  jobs_generated_female: 0,
  jobs_retained_male: 0,
  jobs_retained_female: 0,
  revenue: 0,
  remittance: 0,
  milestones: "",
  impact: "",
  monitoring_narrative: "",
});
const currentType = computed<ComplianceType | null>(() =>
  props.report?.compliance_type || selectedType.value || props.type || null,
);
const effectiveReport = computed<MonitoringReport | null>(() => props.report || null);
const editable = computed(
  () => !effectiveReport.value || ["draft", "returned"].includes(effectiveReport.value.status),
);
const canReview = computed(
  () => props.isManager && effectiveReport.value?.status === "submitted",
);
const reportTypes = [
  { value: 'employment' as const, label: 'Employment', description: 'Quarterly jobs generated and retained.', icon: Briefcase },
  { value: 'financial' as const, label: 'Financial', description: 'Revenue and remittance for a date range.', icon: DollarSign },
  { value: 'progress' as const, label: 'Progress', description: 'Milestones, impact, and narrative for a date range.', icon: FileText },
];
const typeLabel = computed(
  () =>
    ({
      employment: "Employment",
      financial: "Financial",
      progress: "Progress",
    })[currentType.value || 'employment'],
);
const typeIcon = computed(
  () =>
    ({ employment: Briefcase, financial: DollarSign, progress: FileText })[
      currentType.value || 'employment'
    ],
);
const typeDescription = computed(
  () =>
    ({
      employment: "Jobs generated and retained, separated by male and female.",
      financial: "Actual revenue and remittance for the selected period.",
      progress: "Milestones, impact, issues, and corrective actions.",
    })[currentType.value || 'employment'],
);
const title = computed(() =>
  !effectiveReport.value
    ? "Submit compliance report"
    : effectiveReport.value.status === "returned"
      ? "Correct and resubmit report"
      : `${typeLabel.value} compliance report`,
);
const generatedTotal = computed(
  () =>
    Number(form.jobs_generated_male || 0) +
    Number(form.jobs_generated_female || 0),
);
const retainedTotal = computed(
  () =>
    Number(form.jobs_retained_male || 0) +
    Number(form.jobs_retained_female || 0),
);
const quarterRange = computed(() => {
  const startMonth = ((Number(form.quarter) - 1) * 3) + 1;
  const start = new Date(Number(form.reporting_year), startMonth - 1, 1);
  const end = new Date(Number(form.reporting_year), startMonth + 2, 0);
  return formatRange(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
});
const dirty = computed(
  () => editable.value && JSON.stringify(form) !== initialSnapshot.value,
);
const isOverdue = computed(() =>
  Boolean(
    props.cycle.due_date &&
    props.cycle.status === "open" &&
    new Date(`${props.cycle.due_date}T23:59:59`) < new Date(),
  ),
);
const dueText = computed(() =>
  props.cycle.due_date
    ? `Due ${formatDate(props.cycle.due_date)}`
    : "No due date",
);
const DateRange = defineComponent({
  props: {
    start: { type: String, required: true },
    end: { type: String, required: true },
    label: { type: String, required: true },
    min: String,
    max: String,
  },
  emits: ["update:start", "update:end"],
  setup(range, { emit }) {
    return () =>
      h("fieldset", { class: "date-range" }, [
        h("legend", range.label),
        h("label", [
          h("span", "From"),
          h("input", {
            type: "date",
            required: true,
            value: range.start,
            min: range.min,
            max: range.max,
            onInput: (event: any) => emit("update:start", event.target.value),
          }),
        ]),
        h("label", [
          h("span", "To"),
          h("input", {
            type: "date",
            required: true,
            value: range.end,
            min: range.min,
            max: range.max,
            onInput: (event: any) => emit("update:end", event.target.value),
          }),
        ]),
      ]);
  },
});
function populateForm(source: Partial<MonitoringReport> = {}) {
  Object.assign(form, {
    reporting_year: source.reporting_year || props.cycle.reporting_year || new Date().getFullYear(),
    quarter: source.quarter || props.cycle.quarter || Math.floor(new Date().getMonth() / 3) + 1,
    employment_period_start:
      source.employment_period_start || props.cycle.period_start,
    employment_period_end:
      source.employment_period_end || props.cycle.period_end,
    financial_period_start:
      source.financial_period_start || props.cycle.period_start,
    financial_period_end: source.financial_period_end || props.cycle.period_end,
    narrative_period_start:
      source.narrative_period_start || props.cycle.period_start,
    narrative_period_end: source.narrative_period_end || props.cycle.period_end,
    jobs_generated_male: source.jobs_generated_male ?? 0,
    jobs_generated_female: source.jobs_generated_female ?? 0,
    jobs_retained_male: source.jobs_retained_male ?? 0,
    jobs_retained_female: source.jobs_retained_female ?? 0,
    revenue: source.revenue ?? 0,
    remittance: source.remittance ?? 0,
    milestones: source.milestones || "",
    impact: source.impact || "",
    monitoring_narrative: source.monitoring_narrative || "",
  });
  initialSnapshot.value = JSON.stringify(form);
}
function reset() {
  selectedType.value = props.report?.compliance_type || props.type || null;
  populateForm(props.report || {});
  reviewRemarks.value = "";
  nextTick(() => closeButton.value?.focus());
}
watch(selectedType, (type) => {
  if (!props.report && type) {
    populateForm({});
  }
});
watch(
  () => props.open,
  (open) => {
    if (open) reset();
  },
);
function requestClose() {
  if (
    dirty.value &&
    !window.confirm("Discard the information entered in this report?")
  )
    return;
  emit("close");
}
function payload() {
  return { compliance_type: currentType.value, ...form };
}
async function submitReport() {
  busy.value = true;
  try {
    const report = effectiveReport.value;
    const response = report
      ? await axiosInstance.post(
          `/api/monitoring-reports/${report.id}/submit`,
          payload(),
        )
      : await axiosInstance.post(
          `/api/projects/${props.cycle.project_id}/monitoring-reports`,
          payload(),
        );
    const submitted = response.data?.data?.data || response.data?.data;
    initialSnapshot.value = JSON.stringify(form);
    toast.success(`${typeLabel.value} report submitted.`);
    emit("saved", submitted);
    emit("close");
  } catch (error: any) {
    toast.error(errorMessage(error, "Failed to submit compliance report."));
  } finally {
    busy.value = false;
  }
}
async function review(action: "accepted" | "returned") {
  if (action === "returned" && !reviewRemarks.value) {
    toast.error("Add review remarks before returning the report.");
    return;
  }
  busy.value = true;
  try {
    const response = await axiosInstance.post(
      `/api/monitoring-reports/${effectiveReport.value!.id}/review`,
      { action, remarks: reviewRemarks.value || null },
    );
    const reviewed = response.data?.data?.data || response.data?.data;
    toast.success(
      action === "accepted"
        ? "Compliance report accepted."
        : "Report returned for correction.",
    );
    emit("saved", reviewed);
    emit("close");
  } catch (error: any) {
    toast.error(errorMessage(error, "Failed to review report."));
  } finally {
    busy.value = false;
  }
}
function errorMessage(error: any, fallback: string) {
  const errors = error?.response?.data?.errors;
  const first = errors ? Object.values(errors)[0] : null;
  return Array.isArray(first)
    ? String(first[0])
    : error?.response?.data?.message || fallback;
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
  return new Intl.NumberFormat("en-PH").format(Number(value || 0));
}
function legacyNumber(value?: number | null) {
  return value == null ? "Not recorded" : number(value);
}
function money(value?: number | null) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    minimumFractionDigits: 2,
  }).format(Number(value || 0));
}
</script>

<style scoped>
.overlay {
  --card: #fff;
  --soft: #f8fafc;
  --border: #dbe3ee;
  --text: #0f172a;
  --muted: #64748b;
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgba(2, 6, 23, 0.68);
  backdrop-filter: blur(4px);
}
.overlay.is-dark {
  --card: #111c2f;
  --soft: #162238;
  --border: #2b3a52;
  --text: #f1f5f9;
  --muted: #94a3b8;
}
.dialog {
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr) auto;
  width: min(52rem, 100%);
  max-height: min(92vh, 54rem);
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--card);
  color: var(--text);
  box-shadow: 0 24px 60px rgba(2, 6, 23, 0.3);
}
.dialog-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid var(--border);
}
.dialog-head h2 {
  margin: 0;
  font-size: 1.2rem;
  letter-spacing: 0;
}
.dialog-head p:last-child {
  margin: 0.25rem 0 0;
  color: var(--muted);
  font-size: 0.78rem;
}
.eyebrow {
  margin: 0 0 0.2rem;
  color: #2563eb;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.icon-button {
  display: grid;
  width: 2.5rem;
  height: 2.5rem;
  place-items: center;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
  color: var(--text);
  cursor: pointer;
}
.icon-button svg {
  width: 1rem;
}
.cycle-strip {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.7rem 1.25rem;
  border-bottom: 1px solid var(--border);
  background: var(--soft);
  color: var(--muted);
  font-size: 0.73rem;
}
.cycle-strip span {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.cycle-strip svg {
  width: 0.95rem;
}
.cycle-strip .overdue {
  color: #dc2626;
  font-weight: 800;
}
.dialog-body {
  overflow-y: auto;
  padding: 1.25rem;
}
.report-form,
.report-view {
  display: grid;
  gap: 1rem;
}
.type-picker {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.65rem;
  margin: 0;
  padding: 0;
  border: 0;
}
.type-picker legend,
.quarter-picker legend {
  grid-column: 1 / -1;
  margin-bottom: 0.2rem;
  color: var(--text);
  font-size: 0.78rem;
  font-weight: 800;
}
.type-picker label {
  position: relative;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.6rem;
  align-items: start;
  min-height: 5.4rem;
  padding: 0.8rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--soft);
  cursor: pointer;
}
.type-picker label.selected {
  border-color: #2563eb;
  background: color-mix(in srgb, #2563eb 8%, var(--card));
  box-shadow: 0 0 0 1px #2563eb;
}
.type-picker label.unavailable {
  opacity: 0.5;
  cursor: not-allowed;
}
.type-picker input {
  position: absolute;
  inset-block-start: 0.6rem;
  inset-inline-end: 0.6rem;
  accent-color: #2563eb;
}
.type-picker .type-icon {
  display: grid;
  width: 2rem;
  height: 2rem;
  place-items: center;
  border-radius: 0.35rem;
  background: #dbeafe;
  color: #2563eb;
}
.type-picker svg {
  width: 1rem;
}
.type-picker strong,
.type-picker small {
  display: block;
  padding-inline-end: 0.8rem;
}
.type-picker strong {
  color: var(--text);
  font-size: 0.78rem;
}
.type-picker small {
  margin-top: 0.25rem;
  color: var(--muted);
  font-size: 0.67rem;
  font-weight: 500;
  line-height: 1.35;
}
.quarter-picker {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin: 0;
  padding: 0.9rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--soft);
}
.quarter-picker input,
.quarter-picker select {
  width: 100%;
  min-height: 2.7rem;
  padding: 0.6rem 0.7rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--card);
  color: var(--text);
  font: inherit;
}
.quarter-picker p {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--muted);
  font-size: 0.72rem;
}
.section-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.section-title > span {
  display: grid;
  width: 2.5rem;
  height: 2.5rem;
  place-items: center;
  border-radius: 0.4rem;
  background: #dbeafe;
  color: #2563eb;
}
.section-title svg {
  width: 1.15rem;
}
.section-title h3 {
  margin: 0;
  font-size: 1rem;
}
.section-title p {
  margin: 0.2rem 0 0;
  color: var(--muted);
  font-size: 0.76rem;
}
:deep(.date-range) {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin: 0;
  padding: 0.9rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--soft);
}
:deep(.date-range legend) {
  padding: 0 0.35rem;
  color: var(--text);
  font-size: 0.75rem;
  font-weight: 800;
}
:deep(.date-range label),
label {
  display: grid;
  gap: 0.35rem;
  color: var(--muted);
  font-size: 0.73rem;
  font-weight: 750;
}
:deep(.date-range input),
textarea {
  width: 100%;
  min-height: 2.7rem;
  padding: 0.6rem 0.7rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--card);
  color: var(--text);
  font: inherit;
}
textarea {
  resize: vertical;
  line-height: 1.45;
}
.two-column {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem;
}
.narratives {
  display: grid;
  gap: 0.8rem;
}
.employment-table {
  display: grid;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
}
.employment-head,
.employment-row {
  display: grid;
  grid-template-columns: minmax(9rem, 1.25fr) repeat(3, minmax(7rem, 1fr));
  gap: 0.7rem;
  align-items: center;
  padding: 0.7rem;
}
.employment-head {
  background: var(--soft);
  color: var(--muted);
  font-size: 0.7rem;
}
.employment-row {
  border-top: 1px solid var(--border);
}
.employment-row > strong {
  font-size: 0.76rem;
}
.employment-row output {
  display: flex;
  min-height: 2.7rem;
  align-items: center;
  padding: 0 0.7rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
  font-weight: 800;
}
.notice {
  display: flex;
  gap: 0.65rem;
  padding: 0.8rem;
  margin-bottom: 1rem;
  border: 1px solid;
  border-radius: 0.45rem;
}
.notice svg {
  width: 1.1rem;
  flex: none;
}
.notice p {
  margin: 0.15rem 0 0;
  font-size: 0.75rem;
}
.notice.returned {
  border-color: #fecdd3;
  background: #fff1f2;
  color: #9f1239;
}
.notice.submitted {
  border-color: #ddd6fe;
  background: #f5f3ff;
  color: #5b21b6;
}
.notice.accepted {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #166534;
}
.metric-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
}
.metric-grid.employment {
  grid-template-columns: repeat(3, 1fr);
}
.metric-grid div,
.narrative-view div {
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  background: var(--soft);
}
.metric-grid span,
.narrative-view span {
  color: var(--muted);
  font-size: 0.67rem;
  text-transform: uppercase;
}
.metric-grid strong {
  display: block;
  margin-top: 0.25rem;
}
.narrative-view {
  display: grid;
  gap: 0.75rem;
}
.narrative-view p {
  margin: 0.35rem 0 0;
  white-space: pre-wrap;
  line-height: 1.5;
}
.submission-meta {
  display: flex;
  justify-content: space-between;
  color: var(--muted);
  font-size: 0.72rem;
}
.review-box {
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
}
.review-box > div {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.75rem;
}
.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  padding: 0.9rem 1.25rem;
  border-top: 1px solid var(--border);
  background: var(--soft);
}
.button {
  display: inline-flex;
  min-height: 2.7rem;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 0 0.9rem;
  border: 1px solid var(--border);
  border-radius: 0.4rem;
  font-weight: 750;
  cursor: pointer;
}
.button svg {
  width: 1rem;
}
.button.primary {
  border-color: #2563eb;
  background: #2563eb;
  color: #fff;
}
.button.secondary {
  background: var(--card);
  color: var(--text);
}
.button.danger {
  border-color: #fecdd3;
  background: #fff1f2;
  color: #be123c;
}
.button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.18s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
@media (max-width: 700px) {
  .overlay {
    padding: 0;
  }
  .dialog {
    width: 100%;
    height: 100dvh;
    max-height: none;
    border: 0;
    border-radius: 0;
  }
  .dialog-head,
  .dialog-body {
    padding: 1rem;
  }
  .cycle-strip {
    display: grid;
    padding: 0.7rem 1rem;
  }
  .two-column,
  .type-picker,
  .quarter-picker,
  :deep(.date-range),
  .metric-grid,
  .metric-grid.employment {
    grid-template-columns: 1fr;
  }
  .employment-table {
    overflow-x: auto;
  }
  .employment-head,
  .employment-row {
    min-width: 36rem;
  }
  .dialog-footer {
    padding: 0.75rem 1rem;
  }
  .dialog-footer .button {
    flex: 1;
  }
}
</style>
