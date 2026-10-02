<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="agreement-modal-overlay"
        :class="{ 'is-dark': isDark }"
        role="presentation"
        @mousedown.self="requestClose"
        @keydown="handleKeydown"
      >
        <section
          ref="dialogRef"
          class="agreement-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="draft-agreement-title"
          tabindex="-1"
        >
        <header class="agreement-modal-head">
          <div>
            <p class="agreement-eyebrow">{{ projectCode || 'Project' }}</p>
            <h2 id="draft-agreement-title">Draft Agreement Form</h2>
            <p class="agreement-subtitle">
              {{ projectTitle }}
              <template v-if="nextStepName"> · Before {{ nextStepName }}</template>
            </p>
          </div>
          <div class="agreement-head-actions">
            <span class="agreement-status" :class="statusClass">{{ statusLabel }}</span>
            <button
              ref="closeButtonRef"
              type="button"
              class="agreement-icon-btn"
              aria-label="Close draft agreement form"
              @click="requestClose"
            >
              <XIcon class="icon" />
            </button>
          </div>
        </header>

        <div v-if="form?.status === 'returned' && form.return_reason" class="agreement-return-note">
          <AlertCircleIcon class="icon" />
          <div>
            <strong>Returned by Legal</strong>
            <p>{{ form.return_reason }}</p>
          </div>
        </div>

        <div class="agreement-modal-body">
          <div v-if="loading" class="agreement-empty">
            <ClockIcon class="empty-icon" />
            <p>Loading agreement form...</p>
          </div>

          <div v-else-if="error" class="agreement-empty agreement-load-error" role="alert">
            <AlertCircleIcon class="empty-icon" />
            <p>{{ error }}</p>
            <button type="button" class="agreement-secondary-btn" @click="$emit('retry')">
              Retry
            </button>
          </div>

          <div v-else-if="readOnlySummary" class="agreement-readonly">
            <section class="legal-review-band" aria-labelledby="legal-review-title">
              <div>
                <p class="agreement-eyebrow">Legal Review</p>
                <h3 id="legal-review-title">Agreement drafting reference</h3>
                <p>{{ context.status_message || 'Submitted for Legal reference.' }}</p>
              </div>
              <div class="legal-review-metrics">
                <div>
                  <span>Status</span>
                  <strong>{{ statusLabel }}</strong>
                </div>
                <div>
                  <span>Submitted</span>
                  <strong>{{ submittedAtLabel }}</strong>
                </div>
                <div>
                  <span>Agreement</span>
                  <strong>{{ draft.agreement_type || 'Not provided' }}</strong>
                </div>
                <div>
                  <span>Legal Gate</span>
                  <strong>{{ nextStepName || 'Legal review' }}</strong>
                </div>
              </div>
            </section>

            <section class="agreement-reference-card" aria-labelledby="agreement-reference-title">
              <div>
                <h3 id="agreement-reference-title">
                  <FileTextIcon class="icon" /> Generated reference
                </h3>
                <p>
                  {{ form?.document
                    ? 'Printable reference generated from the submitted form.'
                    : 'Reference will be generated after submission.' }}
                </p>
              </div>
              <div class="agreement-reference-actions">
                <button
                  type="button"
                  class="agreement-secondary-btn compact"
                  :disabled="!form?.document"
                  @click="form?.document && $emit('view-document', form.document)"
                >
                  <ExternalLinkIcon class="icon" /> Open reference
                </button>
                <button
                  type="button"
                  class="agreement-secondary-btn compact"
                  :disabled="!form"
                  @click="$emit('download-reference-pdf')"
                >
                  <DownloadIcon class="icon" /> Download PDF
                </button>
              </div>
            </section>

            <section class="project-context-panel" aria-labelledby="project-context-title">
              <div class="agreement-section-head">
                <div>
                  <h3 id="project-context-title">Project Context</h3>
                  <p>Key project details Legal needs while drafting or checking the agreement.</p>
                </div>
              </div>
              <dl class="project-context-grid">
                <div v-for="item in projectContextItems" :key="item.label">
                  <dt>{{ item.label }}</dt>
                  <dd>{{ item.value }}</dd>
                </div>
              </dl>
            </section>

            <div class="agreement-party-list">
              <article v-for="(party, index) in draft.parties" :key="`readonly-party-${index}`" class="party-readonly-card">
                <h3>{{ party.label || partyLabel(index) }}</h3>
                <dl>
                  <div>
                    <dt>Company</dt>
                    <dd>{{ party.company_name || 'Not provided' }}</dd>
                  </div>
                  <div>
                    <dt>Office Address</dt>
                    <dd>{{ party.office_address || 'Not provided' }}</dd>
                  </div>
                  <div>
                    <dt>Authorized Signatory</dt>
                    <dd>{{ party.authorized_signatory || 'Not provided' }}</dd>
                  </div>
                  <div>
                    <dt>Position</dt>
                    <dd>{{ party.position || 'Not provided' }}</dd>
                  </div>
                  <div>
                    <dt>CTC / Passport ID</dt>
                    <dd>{{ party.ctc_passport_id || 'Not provided' }}</dd>
                  </div>
                  <div>
                    <dt>Date and Place of Issue</dt>
                    <dd>{{ party.issue_date_place || 'Not provided' }}</dd>
                  </div>
                </dl>
              </article>
            </div>

            <div class="agreement-term-summary">
              <span>Submitted Term Sheet</span>
              <p>{{ draft.term_sheet || 'Not provided' }}</p>
            </div>

            <form v-if="canReturn" class="agreement-return-form" @submit.prevent="submitReturn">
              <div>
                <label for="agreement-return-reason">Return reason</label>
                <p>Send this back when Legal needs corrected party details, agreement type, or term-sheet information.</p>
              </div>
              <textarea
                id="agreement-return-reason"
                v-model="returnReason"
                rows="3"
                placeholder="Explain what must be corrected before Legal proceeds."
              ></textarea>
              <div class="agreement-return-actions">
                <button type="submit" class="agreement-danger-btn" :disabled="returning || !returnReason.trim()">
                  {{ returning ? 'Returning...' : 'Return for Correction' }}
                </button>
              </div>
            </form>
          </div>

          <div v-else-if="!canEdit" class="agreement-empty">
            <InfoIcon class="empty-icon" />
            <p>{{ context.status_message || 'The assigned previous workflow reviewer must submit this form before Legal can proceed.' }}</p>
          </div>

          <form v-else class="agreement-edit-form" novalidate @submit.prevent="submitForm">
            <div v-if="showErrorSummary" class="agreement-error-summary" tabindex="-1" ref="errorSummaryRef">
              <AlertCircleIcon class="icon" />
              <span>Please complete the required fields before submitting for Legal review.</span>
            </div>

            <div class="agreement-note">
              <InfoIcon class="icon" />
              <span>This form is filled out by BDG/SPG or project staff as Legal's drafting reference.</span>
            </div>

            <div
              class="agreement-field agreement-field-full"
              @focusout="handleAgreementTypeFocusOut"
            >
              <label class="agreement-field-label" for="agreement-type">Type of Agreement *</label>
              <div class="agreement-combobox" :class="{ 'is-open': agreementTypeOpen }">
                <input
                  id="agreement-type"
                  v-model="draft.agreement_type"
                  name="agreement_type"
                  type="text"
                  role="combobox"
                  aria-autocomplete="list"
                  aria-controls="agreement-type-options"
                  :aria-activedescendant="activeAgreementTypeId"
                  :aria-expanded="agreementTypeOpen"
                  placeholder="Investment Agreement, Joint Venture Agreement, MOA, NDA..."
                  :aria-invalid="Boolean(fieldError('agreement_type'))"
                  @focus="openAgreementTypeOptions"
                  @input="openAgreementTypeOptions"
                  @blur="touch('agreement_type')"
                  @keydown.down.prevent="moveAgreementTypeSelection(1)"
                  @keydown.up.prevent="moveAgreementTypeSelection(-1)"
                  @keydown.enter.prevent="selectActiveAgreementType"
                  @keydown.esc.prevent="closeAgreementTypeOptions"
                />
                <button
                  type="button"
                  class="agreement-combobox-toggle"
                  aria-label="Show agreement type suggestions"
                  :aria-expanded="agreementTypeOpen"
                  aria-controls="agreement-type-options"
                  @mousedown.prevent
                  @click="toggleAgreementTypeOptions"
                >
                  <ChevronDownIcon class="icon" />
                </button>
                <div
                  v-if="agreementTypeOpen && filteredAgreementTypeOptions.length"
                  id="agreement-type-options"
                  class="agreement-options"
                  role="listbox"
                  aria-label="Agreement type suggestions"
                >
                  <button
                    v-for="(option, index) in filteredAgreementTypeOptions"
                    :id="agreementTypeOptionId(index)"
                    :key="option"
                    type="button"
                    class="agreement-option"
                    :class="{ 'is-active': index === activeAgreementTypeIndex }"
                    role="option"
                    :aria-selected="draft.agreement_type === option"
                    @mousedown.prevent
                    @click="selectAgreementType(option)"
                    @mouseenter="activeAgreementTypeIndex = index"
                  >
                    <span>{{ option }}</span>
                    <CheckIcon v-if="draft.agreement_type === option" class="icon" />
                  </button>
                </div>
              </div>
              <small v-if="fieldError('agreement_type')" class="agreement-field-error">{{ fieldError('agreement_type') }}</small>
            </div>

            <section class="agreement-parties-section" aria-labelledby="agreement-parties-title">
              <div class="agreement-section-head">
                <div>
                  <h3 id="agreement-parties-title">Parties</h3>
                  <p>First and second parties are required. Add more parties only when the agreement needs them.</p>
                </div>
                <button type="button" class="agreement-secondary-btn" @click="addParty">
                  <UserPlusIcon class="icon" /> Add Party
                </button>
              </div>

              <fieldset v-for="(party, index) in draft.parties" :key="`party-${index}`" class="party-card">
                <legend>
                  <span>{{ party.label || partyLabel(index) }}</span>
                  <button
                    v-if="index > 1"
                    type="button"
                    class="agreement-icon-btn danger"
                    :aria-label="`Remove ${party.label || partyLabel(index)}`"
                    @click="removeParty(index)"
                  >
                    <TrashIcon class="icon" />
                  </button>
                </legend>

                <div class="agreement-grid">
                  <label class="agreement-field" :for="fieldId(index, 'company_name')">
                    <span>Company Name *</span>
                    <input
                      :id="fieldId(index, 'company_name')"
                      v-model="party.company_name"
                      type="text"
                      :name="`parties[${index}][company_name]`"
                      :aria-invalid="Boolean(fieldError(`parties.${index}.company_name`))"
                      @blur="touch(`parties.${index}.company_name`)"
                    />
                    <small v-if="fieldError(`parties.${index}.company_name`)" class="agreement-field-error">{{ fieldError(`parties.${index}.company_name`) }}</small>
                  </label>

                  <label class="agreement-field" :for="fieldId(index, 'authorized_signatory')">
                    <span>Authorized Signatory *</span>
                    <input
                      :id="fieldId(index, 'authorized_signatory')"
                      v-model="party.authorized_signatory"
                      type="text"
                      :name="`parties[${index}][authorized_signatory]`"
                      :aria-invalid="Boolean(fieldError(`parties.${index}.authorized_signatory`))"
                      @blur="touch(`parties.${index}.authorized_signatory`)"
                    />
                    <small v-if="fieldError(`parties.${index}.authorized_signatory`)" class="agreement-field-error">{{ fieldError(`parties.${index}.authorized_signatory`) }}</small>
                  </label>

                  <label class="agreement-field agreement-field-full" :for="fieldId(index, 'office_address')">
                    <span>Office Address *</span>
                    <textarea
                      :id="fieldId(index, 'office_address')"
                      v-model="party.office_address"
                      rows="2"
                      :name="`parties[${index}][office_address]`"
                      :aria-invalid="Boolean(fieldError(`parties.${index}.office_address`))"
                      @blur="touch(`parties.${index}.office_address`)"
                    ></textarea>
                    <small v-if="fieldError(`parties.${index}.office_address`)" class="agreement-field-error">{{ fieldError(`parties.${index}.office_address`) }}</small>
                  </label>

                  <label class="agreement-field" :for="fieldId(index, 'position')">
                    <span>Position {{ index > 0 ? '*' : '' }}</span>
                    <input
                      :id="fieldId(index, 'position')"
                      v-model="party.position"
                      type="text"
                      :name="`parties[${index}][position]`"
                      :aria-invalid="Boolean(fieldError(`parties.${index}.position`))"
                      @blur="touch(`parties.${index}.position`)"
                    />
                    <small v-if="fieldError(`parties.${index}.position`)" class="agreement-field-error">{{ fieldError(`parties.${index}.position`) }}</small>
                  </label>

                  <label class="agreement-field" :for="fieldId(index, 'ctc_passport_id')">
                    <span>CTC / Passport ID *</span>
                    <input
                      :id="fieldId(index, 'ctc_passport_id')"
                      v-model="party.ctc_passport_id"
                      type="text"
                      :name="`parties[${index}][ctc_passport_id]`"
                      :aria-invalid="Boolean(fieldError(`parties.${index}.ctc_passport_id`))"
                      @blur="touch(`parties.${index}.ctc_passport_id`)"
                    />
                    <small v-if="fieldError(`parties.${index}.ctc_passport_id`)" class="agreement-field-error">{{ fieldError(`parties.${index}.ctc_passport_id`) }}</small>
                  </label>

                  <label class="agreement-field agreement-field-full" :for="fieldId(index, 'issue_date_place')">
                    <span>Date and Place of Issue *</span>
                    <input
                      :id="fieldId(index, 'issue_date_place')"
                      v-model="party.issue_date_place"
                      type="text"
                      placeholder="January 15, 2026 - Quezon City"
                      :name="`parties[${index}][issue_date_place]`"
                      :aria-invalid="Boolean(fieldError(`parties.${index}.issue_date_place`))"
                      @blur="touch(`parties.${index}.issue_date_place`)"
                    />
                    <small v-if="fieldError(`parties.${index}.issue_date_place`)" class="agreement-field-error">{{ fieldError(`parties.${index}.issue_date_place`) }}</small>
                  </label>
                </div>
              </fieldset>
            </section>

            <label class="agreement-field agreement-field-full" for="agreement-term-sheet">
              <span>Term Sheet *</span>
              <textarea
                id="agreement-term-sheet"
                v-model="draft.term_sheet"
                name="term_sheet"
                rows="7"
                placeholder="Summarize the commercial terms, scope, price/share, conditions, obligations, payment/release mechanics, and special clauses for Legal."
                :aria-invalid="Boolean(fieldError('term_sheet'))"
                @blur="touch('term_sheet')"
              ></textarea>
              <small v-if="fieldError('term_sheet')" class="agreement-field-error">{{ fieldError('term_sheet') }}</small>
            </label>
          </form>
        </div>

        <footer v-if="!loading && !error" class="agreement-modal-foot">
          <button type="button" class="agreement-secondary-btn" :disabled="saving || submitting || returning" @click="requestClose">
            {{ readOnlySummary ? 'Close' : 'Cancel' }}
          </button>
          <template v-if="canEdit && !readOnlySummary">
            <button type="button" class="agreement-secondary-btn" :disabled="saving || submitting" @click="saveDraft">
              {{ saving ? 'Saving...' : 'Save Draft' }}
            </button>
            <button type="button" class="agreement-primary-btn" :disabled="saving || submitting" @click="submitForm">
              {{ submitting ? 'Submitting...' : 'Submit for Legal Review' }}
            </button>
          </template>
        </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
  AlertCircle as AlertCircleIcon,
  Check as CheckIcon,
  ChevronDown as ChevronDownIcon,
  Clock as ClockIcon,
  Download as DownloadIcon,
  ExternalLink as ExternalLinkIcon,
  FileText as FileTextIcon,
  Info as InfoIcon,
  Trash as TrashIcon,
  UserPlus as UserPlusIcon,
  X as XIcon,
} from 'lucide-vue-next';
import type { Document as ProjectDocument, ProjectAgreementForm, ProjectAgreementParty } from '@/types/project';

type AgreementDraft = {
  agreement_type: string;
  parties: ProjectAgreementParty[];
  term_sheet: string;
};

type AgreementFormContext = {
  show_panel: boolean;
  can_submit: boolean;
  can_return: boolean;
  read_only: boolean;
  is_legal_user: boolean;
  requires_before_legal: boolean;
  status_message: string;
  current_step?: unknown;
  gate_step?: unknown;
};

type ProjectSummary = {
  code?: string | null;
  title?: string | null;
  category?: string | null;
  stage?: string | null;
  status?: string | null;
  proponent?: string | null;
  sector?: string | null;
  investmentType?: string | null;
  fundingSource?: string | null;
  estimatedCost?: string | null;
  projectOfficer?: string | null;
  workgroupHead?: string | null;
};

const props = defineProps<{
  open: boolean;
  projectCode?: string | null;
  projectTitle?: string | null;
  nextStepName?: string | null;
  projectSummary?: ProjectSummary | null;
  form: ProjectAgreementForm | null;
  context: AgreementFormContext;
  initialDraft: AgreementDraft;
  loading: boolean;
  error?: string;
  saving: boolean;
  submitting: boolean;
  returning: boolean;
  isDark?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  save: [payload: AgreementDraft];
  submit: [payload: AgreementDraft];
  return: [reason: string];
  retry: [];
  'view-document': [document: ProjectDocument];
  'download-reference-pdf': [];
}>();

const attemptedSubmit = ref(false);
const touched = ref<Record<string, boolean>>({});
const returnReason = ref('');
const errorSummaryRef = ref<HTMLElement | null>(null);
const dialogRef = ref<HTMLElement | null>(null);
const closeButtonRef = ref<HTMLButtonElement | null>(null);
const agreementTypeOpen = ref(false);
const activeAgreementTypeIndex = ref(0);
let previousBodyOverflow = '';
const draft = ref<AgreementDraft>({
  agreement_type: '',
  parties: [],
  term_sheet: '',
});

const agreementTypeOptions = [
  'Investment Agreement',
  'Joint Venture Agreement',
  'Shareholders Agreement',
  'Memorandum of Agreement',
  'Non-Disclosure Agreement',
  'Fund Release Agreement',
];

const agreementTypeMatches = (option: string, query: string) => {
  const normalizedOption = option.toLowerCase();
  const initials = option
    .split(/[\s-]+/)
    .map((word) => word[0])
    .join('')
    .toLowerCase();

  return normalizedOption.includes(query) || initials.includes(query);
};

const filteredAgreementTypeOptions = computed(() => {
  const query = draft.value.agreement_type.trim().toLowerCase();
  if (!query) return agreementTypeOptions;

  return agreementTypeOptions.filter((option) => agreementTypeMatches(option, query));
});

const agreementTypeOptionId = (index: number) => `agreement-type-option-${index}`;

const activeAgreementTypeId = computed(() => (
  agreementTypeOpen.value && filteredAgreementTypeOptions.value[activeAgreementTypeIndex.value]
    ? agreementTypeOptionId(activeAgreementTypeIndex.value)
    : undefined
));

const partyLabel = (index: number) => {
  if (index === 0) return '1st Party';
  if (index === 1) return '2nd Party';
  if (index === 2) return '3rd Party';
  return `${index + 1}th Party`;
};

const makeParty = (index: number): ProjectAgreementParty => ({
  label: partyLabel(index),
  company_name: '',
  office_address: '',
  authorized_signatory: '',
  position: '',
  ctc_passport_id: '',
  issue_date_place: '',
});

const normalizeParties = (parties: ProjectAgreementParty[] = []) => {
  const normalized = (parties.length ? parties : [makeParty(0), makeParty(1)]).map((party, index) => ({
    ...makeParty(index),
    ...party,
    label: party.label || partyLabel(index),
  }));

  while (normalized.length < 2) {
    normalized.push(makeParty(normalized.length));
  }

  return normalized;
};

const hydrateDraft = () => {
  draft.value = {
    agreement_type: props.initialDraft?.agreement_type || '',
    parties: normalizeParties(props.initialDraft?.parties || []),
    term_sheet: props.initialDraft?.term_sheet || '',
  };
  attemptedSubmit.value = false;
  touched.value = {};
  returnReason.value = '';
};

const restoreBodyScroll = () => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = previousBodyOverflow;
};

watch(() => props.open, async (open) => {
  if (open) {
    hydrateDraft();
    if (typeof document !== 'undefined') {
      previousBodyOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
    }
    await nextTick();
    closeButtonRef.value?.focus();
    return;
  }

  restoreBodyScroll();
});

watch(() => props.initialDraft, () => {
  if (props.open) hydrateDraft();
}, { deep: true });

watch(filteredAgreementTypeOptions, () => {
  clampAgreementTypeIndex();
});

const statusLabel = computed(() => {
  if (props.form?.status === 'submitted') return 'Submitted';
  if (props.form?.status === 'returned') return 'Returned';
  if (props.form?.status === 'draft') return 'Draft';
  return 'Not Started';
});

const statusClass = computed(() => ({
  submitted: props.form?.status === 'submitted',
  returned: props.form?.status === 'returned',
  draft: props.form?.status === 'draft',
  empty: !props.form?.status,
}));

const canEdit = computed(() => props.context.can_submit && !props.context.read_only && props.form?.status !== 'submitted');
const canReturn = computed(() => props.context.can_return && props.form?.status === 'submitted');
const readOnlySummary = computed(() => props.context.read_only || props.form?.status === 'submitted');
const submittedAtLabel = computed(() => formatDateTime(props.form?.submitted_at));
const projectContextItems = computed(() => [
  { label: 'Project Code', value: props.projectSummary?.code || props.projectCode || 'Not provided' },
  { label: 'Project Title', value: props.projectSummary?.title || props.projectTitle || 'Not provided' },
  { label: 'Category', value: props.projectSummary?.category || 'Not provided' },
  { label: 'Stage / Status', value: [props.projectSummary?.stage, props.projectSummary?.status].filter(Boolean).join(' / ') || 'Not provided' },
  { label: 'Proponent', value: props.projectSummary?.proponent || 'Not provided' },
  { label: 'Sector', value: props.projectSummary?.sector || 'Not provided' },
  { label: 'Investment Type', value: props.projectSummary?.investmentType || 'Not provided' },
  { label: 'Funding Source', value: props.projectSummary?.fundingSource || 'Not provided' },
  { label: 'Estimated Cost', value: props.projectSummary?.estimatedCost || 'Not provided' },
  { label: 'Project Officer', value: props.projectSummary?.projectOfficer || 'Not assigned' },
  { label: 'Workgroup Head', value: props.projectSummary?.workgroupHead || 'Not assigned' },
]);

const formatDateTime = (value?: string | null) => {
  if (!value) return 'Not submitted';
  const date = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const payload = () => ({
  agreement_type: (draft.value.agreement_type || '').trim(),
  term_sheet: (draft.value.term_sheet || '').trim(),
  parties: normalizeParties(draft.value.parties).map((party, index) => ({
    label: party.label || partyLabel(index),
    company_name: (party.company_name || '').trim(),
    office_address: (party.office_address || '').trim(),
    authorized_signatory: (party.authorized_signatory || '').trim(),
    position: (party.position || '').trim(),
    ctc_passport_id: (party.ctc_passport_id || '').trim(),
    issue_date_place: (party.issue_date_place || '').trim(),
  })),
});

const errors = computed<Record<string, string>>(() => {
  const form = payload();
  const validation: Record<string, string> = {};

  if (!form.agreement_type) validation.agreement_type = 'Type of agreement is required.';
  if (!form.term_sheet || form.term_sheet.length < 10) validation.term_sheet = 'Term sheet must describe the key agreement terms.';
  if (form.parties.length < 2) validation.parties = 'At least two parties are required.';

  form.parties.forEach((party, index) => {
    if (!party.company_name) validation[`parties.${index}.company_name`] = 'Company name is required.';
    if (!party.office_address) validation[`parties.${index}.office_address`] = 'Office address is required.';
    if (!party.authorized_signatory) validation[`parties.${index}.authorized_signatory`] = 'Authorized signatory is required.';
    if (index > 0 && !party.position) validation[`parties.${index}.position`] = 'Position is required.';
    if (!party.ctc_passport_id) validation[`parties.${index}.ctc_passport_id`] = 'CTC or passport ID is required.';
    if (!party.issue_date_place) validation[`parties.${index}.issue_date_place`] = 'Date and place of issue are required.';
  });

  return validation;
});

const showErrorSummary = computed(() => attemptedSubmit.value && Object.keys(errors.value).length > 0);

const touch = (field: string) => {
  touched.value = { ...touched.value, [field]: true };
};

const fieldError = (field: string) => {
  if (!attemptedSubmit.value && !touched.value[field]) return '';
  return errors.value[field] || '';
};

const fieldId = (index: number, field: string) => `agreement-party-${index}-${field}`;

const clampAgreementTypeIndex = () => {
  const optionCount = filteredAgreementTypeOptions.value.length;
  activeAgreementTypeIndex.value = optionCount
    ? Math.min(activeAgreementTypeIndex.value, optionCount - 1)
    : 0;
};

const openAgreementTypeOptions = () => {
  agreementTypeOpen.value = true;
  clampAgreementTypeIndex();
};

const closeAgreementTypeOptions = () => {
  agreementTypeOpen.value = false;
};

const toggleAgreementTypeOptions = () => {
  agreementTypeOpen.value = !agreementTypeOpen.value;
  if (agreementTypeOpen.value) clampAgreementTypeIndex();
};

const moveAgreementTypeSelection = (direction: number) => {
  if (!agreementTypeOpen.value) {
    openAgreementTypeOptions();
    return;
  }

  const optionCount = filteredAgreementTypeOptions.value.length;
  if (!optionCount) return;

  activeAgreementTypeIndex.value = (activeAgreementTypeIndex.value + direction + optionCount) % optionCount;
};

const selectAgreementType = (option: string) => {
  draft.value.agreement_type = option;
  touch('agreement_type');
  closeAgreementTypeOptions();
};

const selectActiveAgreementType = () => {
  const option = filteredAgreementTypeOptions.value[activeAgreementTypeIndex.value];
  if (agreementTypeOpen.value && option) {
    selectAgreementType(option);
    return;
  }

  closeAgreementTypeOptions();
};

const handleAgreementTypeFocusOut = (event: FocusEvent) => {
  const nextTarget = event.relatedTarget;
  if (event.currentTarget instanceof HTMLElement && nextTarget instanceof Node && event.currentTarget.contains(nextTarget)) {
    return;
  }

  closeAgreementTypeOptions();
};

const addParty = () => {
  draft.value.parties.push(makeParty(draft.value.parties.length));
};

const removeParty = (index: number) => {
  if (index <= 1 || draft.value.parties.length <= 2) return;
  draft.value.parties.splice(index, 1);
  draft.value.parties = normalizeParties(draft.value.parties);
};

const focusErrors = async () => {
  await nextTick();
  errorSummaryRef.value?.focus();
};

const submitForm = async () => {
  attemptedSubmit.value = true;
  if (Object.keys(errors.value).length > 0) {
    await focusErrors();
    return;
  }
  emit('submit', payload());
};

const saveDraft = () => {
  emit('save', payload());
};

const submitReturn = () => {
  if (!returnReason.value.trim()) return;
  emit('return', returnReason.value.trim());
};

const requestClose = () => {
  if (props.saving || props.submitting || props.returning) return;
  emit('close');
};

const focusableElements = () => {
  if (!dialogRef.value) return [];

  return Array.from(dialogRef.value.querySelectorAll<HTMLElement>(
    'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
  )).filter((element) => element.offsetParent !== null);
};

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault();
    requestClose();
    return;
  }

  if (event.key !== 'Tab') return;

  const focusable = focusableElements();
  if (focusable.length === 0) {
    event.preventDefault();
    dialogRef.value?.focus();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
};

onBeforeUnmount(restoreBodyScroll);
</script>

<style scoped>
.agreement-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 10020;
  display: grid;
  place-items: center;
  padding: 1.5rem;
  background: rgba(15, 23, 42, 0.58);
  backdrop-filter: blur(5px);
}

.agreement-load-error {
  color: #b91c1c;
}

.agreement-modal-overlay.is-dark .agreement-load-error {
  color: #fecaca;
}

.agreement-modal {
  width: min(940px, 100%);
  max-height: min(88vh, 940px);
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  overflow: hidden;
  border: 1px solid #dbe4f0;
  border-radius: 8px;
  background: #ffffff;
  color: #111827;
  box-shadow: 0 30px 80px rgba(15, 23, 42, 0.24);
}

.agreement-modal-overlay.is-dark .agreement-modal {
  border-color: #26384f;
  background: #0f172a;
  color: #e5edf8;
}

.agreement-modal-head,
.agreement-modal-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  background: #ffffff;
}

.agreement-modal-foot {
  justify-content: flex-end;
  border-top: 1px solid #e2e8f0;
  border-bottom: 0;
}

.agreement-modal-overlay.is-dark .agreement-modal-head,
.agreement-modal-overlay.is-dark .agreement-modal-foot {
  border-color: #26384f;
  background: #111c31;
}

.agreement-eyebrow {
  margin: 0 0 0.25rem;
  color: #2563eb;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.agreement-modal h2 {
  margin: 0;
  font-size: 1.25rem;
  line-height: 1.25;
}

.agreement-subtitle {
  margin: 0.25rem 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.agreement-modal-overlay.is-dark .agreement-subtitle {
  color: #9fb0c8;
}

.agreement-head-actions {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
}

.agreement-icon-btn,
.agreement-secondary-btn,
.agreement-primary-btn,
.agreement-danger-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 2.5rem;
  border-radius: 8px;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s, transform 0.15s;
}

.agreement-icon-btn {
  width: 2.5rem;
  border: 1px solid #dbe4f0;
  background: #f8fafc;
  color: #475569;
}

.agreement-icon-btn:hover {
  color: #2563eb;
  border-color: #93c5fd;
  background: #eff6ff;
}

.agreement-icon-btn.danger:hover {
  color: #dc2626;
  border-color: #fecaca;
  background: #fef2f2;
}

.agreement-secondary-btn,
.agreement-primary-btn,
.agreement-danger-btn {
  min-width: 7rem;
  padding: 0 0.95rem;
  border: 1px solid #dbe4f0;
  background: #ffffff;
  color: #334155;
}

.agreement-secondary-btn.compact {
  min-width: auto;
  min-height: 2.25rem;
  padding: 0 0.75rem;
  font-size: 0.82rem;
}

.agreement-primary-btn {
  border-color: #2563eb;
  background: #2563eb;
  color: #ffffff;
}

.agreement-danger-btn {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}

.agreement-secondary-btn:hover:not(:disabled) {
  border-color: #93c5fd;
  color: #2563eb;
  background: #eff6ff;
}

.agreement-primary-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.agreement-danger-btn:hover:not(:disabled) {
  background: #fee2e2;
}

button:disabled {
  cursor: not-allowed;
  opacity: 0.62;
}

.agreement-modal-overlay.is-dark .agreement-icon-btn,
.agreement-modal-overlay.is-dark .agreement-secondary-btn {
  border-color: #33465f;
  background: #17243a;
  color: #dbe7f8;
}

.agreement-status {
  display: inline-flex;
  align-items: center;
  min-height: 1.85rem;
  padding: 0 0.7rem;
  border-radius: 999px;
  font-size: 0.76rem;
  font-weight: 900;
  white-space: nowrap;
}

.agreement-status.empty {
  background: #f1f5f9;
  color: #475569;
}

.agreement-status.draft {
  background: #dbeafe;
  color: #1d4ed8;
}

.agreement-status.submitted {
  background: #dcfce7;
  color: #166534;
}

.agreement-status.returned {
  background: #fee2e2;
  color: #991b1b;
}

.agreement-modal-body {
  min-height: 0;
  overflow: auto;
  padding: 1.25rem;
}

.agreement-empty {
  display: grid;
  min-height: 14rem;
  place-items: center;
  gap: 0.75rem;
  color: #64748b;
  text-align: center;
}

.empty-icon {
  width: 2.25rem;
  height: 2.25rem;
  color: #2563eb;
}

.agreement-edit-form,
.agreement-readonly,
.agreement-party-list,
.agreement-parties-section {
  display: grid;
  gap: 1rem;
}

.legal-review-band,
.agreement-reference-card,
.project-context-panel {
  border: 1px solid #dbe4f0;
  border-radius: 8px;
  background: #f8fafc;
}

.legal-review-band {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.35fr);
  gap: 1rem;
  padding: 1rem;
  background: #eef5ff;
  border-color: #bfdbfe;
}

.legal-review-band h3,
.agreement-reference-card h3,
.project-context-panel h3 {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  line-height: 1.25;
}

.legal-review-band p,
.agreement-reference-card p,
.agreement-return-form p {
  margin: 0.3rem 0 0;
  color: #64748b;
  line-height: 1.45;
}

.legal-review-metrics,
.project-context-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.legal-review-metrics div,
.project-context-grid div {
  min-width: 0;
  padding: 0.75rem;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.76);
}

.legal-review-metrics span,
.project-context-grid dt {
  display: block;
  margin: 0 0 0.25rem;
  color: #64748b;
  font-size: 0.7rem;
  font-weight: 900;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.legal-review-metrics strong,
.project-context-grid dd {
  margin: 0;
  color: #0f172a;
  font-weight: 850;
  overflow-wrap: anywhere;
}

.agreement-reference-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.95rem 1rem;
}

.agreement-reference-card h3 {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.agreement-reference-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.project-context-panel {
  display: grid;
  gap: 0.85rem;
  padding: 1rem;
}

.agreement-note,
.agreement-return-note,
.agreement-error-summary {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.85rem 1rem;
  border-radius: 8px;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  color: #1e40af;
  font-weight: 700;
}

.agreement-return-note {
  margin: 1rem 1.25rem 0;
  border-color: #f59e0b;
  background: #fffbeb;
  color: #92400e;
}

.agreement-return-note p {
  margin: 0.2rem 0 0;
  color: #92400e;
  font-weight: 600;
}

.agreement-return-actions {
  display: flex;
  justify-content: flex-end;
}

.agreement-error-summary {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}

.agreement-modal-overlay.is-dark .agreement-note {
  border-color: #315f9f;
  background: #162944;
  color: #bfdbfe;
}

.agreement-modal-overlay.is-dark .legal-review-band {
  border-color: #315f9f;
  background: #162944;
}

.agreement-modal-overlay.is-dark .agreement-reference-card,
.agreement-modal-overlay.is-dark .project-context-panel {
  border-color: #26384f;
  background: #111c31;
}

.agreement-modal-overlay.is-dark .legal-review-band h3,
.agreement-modal-overlay.is-dark .agreement-reference-card h3,
.agreement-modal-overlay.is-dark .project-context-panel h3,
.agreement-modal-overlay.is-dark .legal-review-metrics strong,
.agreement-modal-overlay.is-dark .project-context-grid dd {
  color: #e5edf8;
}

.agreement-modal-overlay.is-dark .legal-review-band p,
.agreement-modal-overlay.is-dark .agreement-reference-card p,
.agreement-modal-overlay.is-dark .agreement-return-form p {
  color: #9fb0c8;
}

.agreement-modal-overlay.is-dark .legal-review-metrics div,
.agreement-modal-overlay.is-dark .project-context-grid div {
  border-color: #26384f;
  background: rgba(15, 23, 42, 0.58);
}

.agreement-modal-overlay.is-dark .agreement-error-summary {
  border-color: #7f1d1d;
  background: #3b1720;
  color: #fecaca;
}

.agreement-grid,
.agreement-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem;
}

.agreement-field,
.agreement-return-form {
  display: grid;
  gap: 0.4rem;
}

.agreement-field-full {
  grid-column: 1 / -1;
}

.agreement-field span,
.agreement-field-label,
.agreement-return-form label,
.agreement-section-head h3,
.agreement-summary-card span,
.agreement-term-summary span {
  color: #64748b;
  font-size: 0.76rem;
  font-weight: 900;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.agreement-field input,
.agreement-field textarea,
.agreement-return-form textarea {
  width: 100%;
  border: 1px solid #dbe4f0;
  border-radius: 8px;
  background: #ffffff;
  color: #111827;
  font: inherit;
  font-size: 0.9rem;
  padding: 0.78rem 0.85rem;
  resize: vertical;
}

.agreement-combobox {
  position: relative;
}

.agreement-combobox input {
  padding-right: 3rem;
}

.agreement-combobox-toggle {
  position: absolute;
  top: 50%;
  right: 0.45rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #475569;
  cursor: pointer;
  transform: translateY(-50%);
  transition: background 0.15s, color 0.15s, transform 0.15s;
}

.agreement-combobox.is-open .agreement-combobox-toggle {
  color: #2563eb;
  transform: translateY(-50%) rotate(180deg);
}

.agreement-combobox-toggle:hover {
  background: #eff6ff;
  color: #2563eb;
}

.agreement-options {
  position: absolute;
  z-index: 4;
  top: calc(100% + 0.35rem);
  left: 0;
  right: 0;
  display: grid;
  max-height: 16rem;
  overflow: auto;
  padding: 0.35rem;
  border: 1px solid #c7d7ee;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
}

.agreement-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  width: 100%;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #1f2937;
  cursor: pointer;
  font: inherit;
  font-size: 0.88rem;
  font-weight: 800;
  line-height: 1.25;
  padding: 0.7rem 0.75rem;
  text-align: left;
}

.agreement-option span {
  color: inherit;
  font-size: inherit;
  font-weight: inherit;
  letter-spacing: 0;
  text-transform: none;
}

.agreement-option:hover,
.agreement-option.is-active {
  background: #eff6ff;
  color: #1d4ed8;
}

.agreement-option[aria-selected='true'] {
  color: #1d4ed8;
}

.agreement-field input[aria-invalid='true'],
.agreement-field textarea[aria-invalid='true'] {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.agreement-field input:focus,
.agreement-field textarea:focus,
.agreement-return-form textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
}

.agreement-modal-overlay.is-dark .agreement-field input,
.agreement-modal-overlay.is-dark .agreement-field textarea,
.agreement-modal-overlay.is-dark .agreement-return-form textarea {
  border-color: #33465f;
  background: #0b1220;
  color: #e5edf8;
}

.agreement-modal-overlay.is-dark .agreement-combobox-toggle {
  color: #9fb0c8;
}

.agreement-modal-overlay.is-dark .agreement-combobox-toggle:hover,
.agreement-modal-overlay.is-dark .agreement-combobox.is-open .agreement-combobox-toggle {
  background: #162944;
  color: #bfdbfe;
}

.agreement-modal-overlay.is-dark .agreement-options {
  border-color: #33465f;
  background: #111c31;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.36);
}

.agreement-modal-overlay.is-dark .agreement-option {
  color: #dbe7f8;
}

.agreement-modal-overlay.is-dark .agreement-option:hover,
.agreement-modal-overlay.is-dark .agreement-option.is-active {
  background: #162944;
  color: #bfdbfe;
}

.agreement-modal-overlay.is-dark .agreement-option[aria-selected='true'] {
  color: #bfdbfe;
}

.agreement-field-error {
  color: #dc2626;
  font-size: 0.78rem;
  font-weight: 800;
}

.agreement-section-head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
}

.agreement-section-head h3 {
  margin: 0;
  color: #111827;
  font-size: 1rem;
  text-transform: none;
  letter-spacing: 0;
}

.agreement-section-head p {
  margin: 0.25rem 0 0;
  color: #64748b;
}

.party-card,
.party-readonly-card,
.agreement-summary-card,
.agreement-term-summary {
  border: 1px solid #dbe4f0;
  border-radius: 8px;
  background: #f8fafc;
}

.party-card {
  display: grid;
  gap: 0.85rem;
  padding: 1rem;
}

.party-card legend {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0;
  color: #0f172a;
  font-weight: 900;
}

.party-readonly-card,
.agreement-summary-card,
.agreement-term-summary {
  padding: 0.9rem 1rem;
}

.party-readonly-card h3 {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
}

.party-readonly-card dl {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  margin: 0;
}

.party-readonly-card dt {
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 900;
  text-transform: uppercase;
}

.party-readonly-card dd {
  margin: 0.2rem 0 0;
  color: #0f172a;
  font-weight: 700;
}

.agreement-summary-card {
  display: grid;
  gap: 0.35rem;
}

.agreement-summary-card strong {
  color: #0f172a;
}

.agreement-term-summary p {
  margin: 0.35rem 0 0;
  color: #334155;
  white-space: pre-wrap;
}

.agreement-modal-overlay.is-dark .agreement-section-head h3,
.agreement-modal-overlay.is-dark .party-card legend,
.agreement-modal-overlay.is-dark .party-readonly-card dd,
.agreement-modal-overlay.is-dark .agreement-summary-card strong {
  color: #e5edf8;
}

.agreement-modal-overlay.is-dark .party-card,
.agreement-modal-overlay.is-dark .party-readonly-card,
.agreement-modal-overlay.is-dark .agreement-summary-card,
.agreement-modal-overlay.is-dark .agreement-term-summary {
  border-color: #26384f;
  background: #111c31;
}

.agreement-modal-overlay.is-dark .agreement-term-summary p {
  color: #c8d5e7;
}

.icon {
  width: 1rem;
  height: 1rem;
  flex: 0 0 auto;
}

@media (max-width: 720px) {
  .agreement-modal-overlay {
    padding: 0.5rem;
    align-items: end;
  }

  .agreement-modal {
    max-height: 94vh;
  }

  .agreement-modal-head,
  .agreement-modal-foot,
  .agreement-section-head,
  .agreement-reference-card {
    align-items: stretch;
    flex-direction: column;
  }

  .agreement-head-actions,
  .agreement-modal-foot,
  .agreement-reference-actions {
    flex-direction: row;
    flex-wrap: wrap;
  }

  .legal-review-band,
  .agreement-grid,
  .agreement-summary-grid,
  .legal-review-metrics,
  .project-context-grid,
  .party-readonly-card dl {
    grid-template-columns: 1fr;
  }

  .agreement-secondary-btn,
  .agreement-primary-btn,
  .agreement-danger-btn {
    flex: 1 1 auto;
  }
}
</style>
