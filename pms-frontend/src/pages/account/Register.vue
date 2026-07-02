<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import { useAuthStore } from '@/store/auth';
import { usePsgcStore } from '@/store/psgc';
import {
  Eye, EyeOff, Upload, FileText, CheckCircle2, AlertTriangle,
  ArrowLeft, ArrowRight, HelpCircle
} from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const psgcStore = usePsgcStore();

const form = reactive({
  organization_name: '',
  organization_type: 'Private Company',
  organization_registration_no: '',
  first_name: '',
  last_name: '',
  email: '',
  phone_number: '',
  address_line: '',
  location_region_code: '',
  location_province_code: '',
  location_city_code: '',
  location_barangay_code: '',
  address: '',
  authority_confirmed: false,
  privacy_consent: false,
  proponent_profile: {
    business_summary: '',
    project_experience: '',
    previous_projects: '',
    major_clients: '',
    certifications: '',
  },
  password: '',
  password_confirmation: '',
});

const currentStep = ref(1);
const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const registrationDocument = ref<File | null>(null);
const authorizationDocument = ref<File | null>(null);
const companyProfileDocument = ref<File | null>(null);

const allowedDocumentTypes = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'text/csv',
  'image/png',
  'image/jpeg',
  'image/webp',
];

const allowedDocumentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp'];
const maxDocumentSize = 10 * 1024 * 1024;

// Step Validation Computeds
const isStep1Valid = computed(() => {
  return form.organization_name.trim() !== '' &&
    form.organization_type.trim() !== '' &&
    form.organization_registration_no.trim() !== '' &&
    form.location_region_code !== '' &&
    form.location_province_code !== '' &&
    form.location_city_code !== '' &&
    form.location_barangay_code !== '' &&
    form.address_line.trim() !== '';
});

const isStep2Valid = computed(() => {
  return form.first_name.trim() !== '' &&
    form.last_name.trim() !== '' &&
    form.email.trim() !== '' &&
    form.phone_number.trim() !== '' &&
    form.password.length >= 8;
});

const isStep3Valid = computed(() => true); // Background info fields are optional

const isStep4Valid = computed(() => {
  return registrationDocument.value !== null &&
    authorizationDocument.value !== null &&
    form.authority_confirmed &&
    form.privacy_consent;
});

const currentStepValid = computed(() => {
  if (currentStep.value === 1) return isStep1Valid.value;
  if (currentStep.value === 2) return isStep2Valid.value;
  if (currentStep.value === 3) return isStep3Valid.value;
  if (currentStep.value === 4) return isStep4Valid.value;
  return false;
});

const canSubmit = computed(() =>
  isStep1Valid.value &&
  isStep2Valid.value &&
  isStep3Valid.value &&
  isStep4Valid.value
);

const stepTitle = computed(() => {
  return {
    1: 'Organization Profile',
    2: 'Representative & Security',
    3: 'Experience & Track Record',
    4: 'Uploads & Consent'
  }[currentStep.value] || '';
});

const goToStep = (step: number) => {
  if (step < currentStep.value) {
    currentStep.value = step;
  } else if (step === 2 && isStep1Valid.value) {
    currentStep.value = step;
  } else if (step === 3 && isStep1Valid.value && isStep2Valid.value) {
    currentStep.value = step;
  } else if (step === 4 && isStep1Valid.value && isStep2Valid.value && isStep3Valid.value) {
    currentStep.value = step;
  } else {
    toast.error('Please complete the required details on the current step before proceeding.');
  }
};

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value;
};

// PSGC address sync
const selectedRegion = computed(() =>
  psgcStore.regions.find((region) => region.region_code === form.location_region_code)
);
const selectedProvince = computed(() =>
  psgcStore.provinces.find((province) => province.province_code === form.location_province_code)
);
const selectedCity = computed(() =>
  psgcStore.cities.find((city) => city.city_municipality_code === form.location_city_code)
);
const selectedBarangay = computed(() =>
  psgcStore.barangays.find((barangay) => barangay.barangay_code === form.location_barangay_code)
);

const updateBusinessAddress = () => {
  const parts = [
    form.address_line.trim(),
    selectedBarangay.value?.barangay_description,
    selectedCity.value?.city_municipality_description,
    selectedProvince.value?.province_description,
    selectedRegion.value?.region_description,
  ].filter(Boolean);

  form.address = parts.join(', ');
};

onMounted(async () => {
  await psgcStore.fetchRegions();
});

watch(() => form.location_region_code, async (code, oldCode) => {
  if (code === oldCode) return;
  form.location_province_code = '';
  form.location_city_code = '';
  form.location_barangay_code = '';
  await psgcStore.fetchProvinces(code);
  updateBusinessAddress();
});

watch(() => form.location_province_code, async (code, oldCode) => {
  if (code === oldCode) return;
  form.location_city_code = '';
  form.location_barangay_code = '';
  if (code) {
    await psgcStore.fetchCities(code);
  } else {
    psgcStore.clearCityDown();
  }
  updateBusinessAddress();
});

watch(() => form.location_city_code, async (code, oldCode) => {
  if (code === oldCode) return;
  form.location_barangay_code = '';
  if (code) {
    await psgcStore.fetchBarangays(code);
  } else {
    psgcStore.clearBarangays();
  }
  updateBusinessAddress();
});

watch(() => [
  form.location_barangay_code,
  form.address_line,
], () => {
  updateBusinessAddress();
});

const fileLabel = (file: File | null) => file ? `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)` : 'Drag and drop or click to choose file';

const validateFile = (file: File | null) => {
  if (!file) return true;
  const extension = file.name.split('.').pop()?.toLowerCase() || '';
  if (!allowedDocumentExtensions.includes(extension) && !allowedDocumentTypes.includes(file.type)) {
    errorMessage.value = 'Upload a PDF, Word, Excel, CSV, or image file only.';
    return false;
  }
  if (file.size > maxDocumentSize) {
    errorMessage.value = 'Each registration document must not exceed 10MB.';
    return false;
  }
  return true;
};

const setFile = (event: Event, target: 'registration' | 'authorization' | 'companyProfile') => {
  errorMessage.value = '';
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] || null;

  if (!validateFile(file)) {
    input.value = '';
    return;
  }

  if (target === 'registration') registrationDocument.value = file;
  if (target === 'authorization') authorizationDocument.value = file;
  if (target === 'companyProfile') companyProfileDocument.value = file;
};

// Previous projects (Track record)
const previous_projects = ref<Array<{
  title: string;
  description: string;
  client_partner: string;
  project_value: string;
  start_date: string;
  end_date: string;
  status: string;
}>>([]);

const addPreviousProject = () => {
  previous_projects.value.push({
    title: '',
    description: '',
    client_partner: '',
    project_value: '',
    start_date: '',
    end_date: '',
    status: 'Completed',
  });
};

const removePreviousProject = (index: number) => {
  previous_projects.value.splice(index, 1);
};

const buildRegistrationPayload = () => {
  const payload = new FormData();
  payload.append('email', form.email.trim());
  payload.append('password', form.password);
  payload.append('password_confirmation', form.password); // Programmatic confirmation parameter
  payload.append('first_name', form.first_name.trim());
  payload.append('last_name', form.last_name.trim());
  payload.append('phone_number', form.phone_number.trim());
  payload.append('organization_name', form.organization_name.trim());
  payload.append('organization_type', form.organization_type.trim());
  payload.append('organization_registration_no', form.organization_registration_no.trim());
  payload.append('address', form.address.trim());
  payload.append('authority_confirmed', form.authority_confirmed ? '1' : '0');

  Object.entries(form.proponent_profile).forEach(([key, value]) => {
    if (key !== 'previous_projects') {
      payload.append(`proponent_profile[${key}]`, value || '');
    }
  });

  previous_projects.value.forEach((proj, idx) => {
    payload.append(`previous_projects[${idx}][title]`, proj.title || '');
    payload.append(`previous_projects[${idx}][description]`, proj.description || '');
    payload.append(`previous_projects[${idx}][client_partner]`, proj.client_partner || '');
    payload.append(`previous_projects[${idx}][project_value]`, proj.project_value || '');
    payload.append(`previous_projects[${idx}][start_date]`, proj.start_date || '');
    payload.append(`previous_projects[${idx}][end_date]`, proj.end_date || '');
    payload.append(`previous_projects[${idx}][status]`, proj.status || '');
  });

  if (registrationDocument.value) payload.append('registration_document', registrationDocument.value);
  if (authorizationDocument.value) payload.append('authorization_document', authorizationDocument.value);
  if (companyProfileDocument.value) payload.append('company_profile_document', companyProfileDocument.value);

  return payload;
};

const submit = async () => {
  errorMessage.value = '';

  if (!canSubmit.value) {
    errorMessage.value = 'Please complete all required details, attach the required documents, confirm authority and data privacy consent.';
    return;
  }

  loading.value = true;
  try {
    const result = await authStore.register(buildRegistrationPayload());

    if (!result.success) {
      errorMessage.value = result.message;
      return;
    }

    toast.success(result.message || 'Registration submitted for approval');
    await router.push('/login');
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="register-page w-full">
    <div class="register-hero">
      <span class="hero-kicker">NDC PMS Account Review</span>
      <h1>Proponent Registration</h1>
      <p>Register your organization in 4 easy steps before submitting a proposal.</p>
    </div>

    <div v-if="errorMessage" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ errorMessage }}
    </div>

    <!-- Stepped progress bar -->
    <div class="steps-progress-wrapper">
      <div class="steps-progress">
        <div class="step-progress-bar">
          <div class="step-progress-bar-fill" :style="{ width: `${((currentStep - 1) / 3) * 100}%` }"></div>
        </div>
        <button
          type="button"
          v-for="step in 4"
          :key="step"
          class="step-bubble"
          :class="{ active: currentStep >= step, current: currentStep === step }"
          @click="goToStep(step)"
        >
          <CheckCircle2 v-if="step === 1 && isStep1Valid" class="check-icon" />
          <CheckCircle2 v-else-if="step === 2 && isStep2Valid" class="check-icon" />
          <CheckCircle2 v-else-if="step === 4 && isStep4Valid" class="check-icon" />
          <span v-else>{{ step }}</span>
        </button>
      </div>
      <div class="step-titles text-center mt-2 mb-6">
        <span class="step-subtitle font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400">
          Step {{ currentStep }} of 4
        </span>
        <h2 class="text-lg font-extrabold text-slate-800 dark:text-slate-100">
          {{ stepTitle }}
        </h2>
      </div>
    </div>

    <!-- Main Registration form -->
    <form class="registration-form" @submit.prevent="submit">

      <!-- Step 1 Panel: Organization Details -->
      <div v-show="currentStep === 1" class="step-panel">
        <div class="panel-header">
          <h3 class="section-title">Company Profile</h3>
          <p class="section-copy">Use the registered legal name and details matching your supporting documents.</p>
        </div>

        <div class="form-body">
          <div class="form-group-full">
            <label class="field-label">Company / Proponent Name <span>*</span></label>
            <input v-model="form.organization_name" required autocomplete="organization" class="form-input-simple" placeholder="Registered company or organization name" />
          </div>

          <div class="form-row-2">
            <div>
              <label class="field-label">Organization Type <span>*</span></label>
              <select v-model="form.organization_type" required class="form-input-simple">
                <option>Private Company</option>
                <option>Government Agency</option>
                <option>LGU</option>
                <option>NGO</option>
                <option>Cooperative</option>
                <option>Other</option>
              </select>
            </div>
            <div>
              <label class="field-label">Registration No. <span>*</span></label>
              <input v-model="form.organization_registration_no" required class="form-input-simple" placeholder="SEC / DTI / CDA reference" />
            </div>
          </div>

          <div class="address-section">
            <h4>Business Address</h4>
            <p class="address-copy">Select location criteria from the dropdowns below.</p>

            <div class="address-grid">
              <div>
                <label class="field-label">Region <span>*</span></label>
                <select v-model="form.location_region_code" required class="form-input-simple">
                  <option value="">Select region</option>
                  <option v-for="region in psgcStore.regions" :key="region.region_code" :value="region.region_code">
                    {{ region.region_description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="field-label">Province <span>*</span></label>
                <select v-model="form.location_province_code" required class="form-input-simple" :disabled="!psgcStore.provinces.length">
                  <option value="">Select province</option>
                  <option v-for="province in psgcStore.provinces" :key="province.province_code" :value="province.province_code">
                    {{ province.province_description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="field-label">City / Municipality <span>*</span></label>
                <select v-model="form.location_city_code" required class="form-input-simple" :disabled="!psgcStore.cities.length">
                  <option value="">Select city / municipality</option>
                  <option v-for="city in psgcStore.cities" :key="city.city_municipality_code" :value="city.city_municipality_code">
                    {{ city.city_municipality_description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="field-label">Barangay <span>*</span></label>
                <select v-model="form.location_barangay_code" required class="form-input-simple" :disabled="!psgcStore.barangays.length">
                  <option value="">Select barangay</option>
                  <option v-for="barangay in psgcStore.barangays" :key="barangay.barangay_code" :value="barangay.barangay_code">
                    {{ barangay.barangay_description }}
                  </option>
                </select>
              </div>
              <div class="span-2">
                <label class="field-label">Office / Street / Building <span>*</span></label>
                <input v-model="form.address_line" required autocomplete="street-address" class="form-input-simple" placeholder="Floor, building, street, or suite number" />
              </div>
            </div>
            <p class="address-preview">
              <strong>Preview:</strong> {{ form.address || 'Select location drop downs and enter office line.' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Step 2 Panel: Representative Details & Security -->
      <div v-show="currentStep === 2" class="step-panel">
        <div class="panel-header">
          <h3 class="section-title">Authorized Representative & Security</h3>
          <p class="section-copy">This user will hold management authority for submissions and review notifications.</p>
        </div>

        <div class="form-body">
          <div class="form-row-2">
            <div>
              <label class="field-label">First Name <span>*</span></label>
              <input v-model="form.first_name" required autocomplete="given-name" class="form-input-simple" placeholder="First Name" />
            </div>
            <div>
              <label class="field-label">Last Name <span>*</span></label>
              <input v-model="form.last_name" required autocomplete="family-name" class="form-input-simple" placeholder="Last Name" />
            </div>
          </div>

          <div class="form-row-2">
            <div>
              <label class="field-label">Email Address <span>*</span></label>
              <input v-model="form.email" required type="email" autocomplete="username" class="form-input-simple" placeholder="proponent@company.com" />
            </div>
            <div>
              <label class="field-label">Contact Number <span>*</span></label>
              <input v-model="form.phone_number" required type="tel" autocomplete="tel" inputmode="tel" class="form-input-simple" placeholder="+63 9XX XXX XXXX" />
            </div>
          </div>

          <div class="security-section">
            <h4>Account Password</h4>
            <div class="form-group-full">
              <label class="field-label">Password <span>*</span></label>
              <div class="password-input-wrap">
                <input
                  v-model="form.password"
                  required
                  :type="showPassword ? 'text' : 'password'"
                  id="new-password"
                  name="new-password"
                  autocomplete="new-password"
                  minlength="8"
                  class="form-input-simple"
                  placeholder="At least 8 characters"
                />
                <button type="button" class="password-toggle-btn" @click="togglePasswordVisibility" title="Toggle visibility">
                  <Eye v-if="!showPassword" />
                  <EyeOff v-else />
                </button>
              </div>
              <span class="preset-help-text">Password managers will suggest a strong, secure key here.</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 3 Panel: Company Background -->
      <div v-show="currentStep === 3" class="step-panel">
        <div class="panel-header">
          <h3 class="section-title">Company Background</h3>
          <p class="section-copy">Provides Context to evaluating managers for your proposal portfolio.</p>
        </div>

        <div class="form-body">
          <div class="form-group-full">
            <label class="field-label">Business Summary</label>
            <textarea v-model="form.proponent_profile.business_summary" class="form-textarea-simple" rows="3" placeholder="Core services, industry segments, and company background..."></textarea>
          </div>

          <div class="form-group-full">
            <label class="field-label">Project Experience</label>
            <textarea v-model="form.proponent_profile.project_experience" class="form-textarea-simple" rows="3" placeholder="Past experience in JV projects, funding, capability..."></textarea>
          </div>

          <div class="previous-projects-container">
            <div class="projects-header">
              <h4>Previous Projects (Track Record)</h4>
              <button type="button" @click="addPreviousProject" class="add-project-btn">
                + Add Record
              </button>
            </div>

            <div v-if="previous_projects.length === 0" class="empty-projects-state">
              No previous projects recorded yet. Click "+ Add Record" to list your track record.
            </div>

            <div v-for="(project, index) in previous_projects" :key="index" class="project-item-card">
              <button type="button" @click="removePreviousProject(index)" class="remove-project-btn" title="Remove project">
                &times;
              </button>
              <h5 class="project-item-title">Project #{{ index + 1 }}</h5>

              <div class="project-form-grid">
                <div class="span-2">
                  <label class="field-label">Project Title <span class="required">*</span></label>
                  <input v-model="project.title" required class="form-input-simple" placeholder="e.g. Solar Farm Implementation" />
                </div>
                <div>
                  <label class="field-label">Client / Partner</label>
                  <input v-model="project.client_partner" class="form-input-simple" placeholder="e.g. LGU Dagupan" />
                </div>
                <div>
                  <label class="field-label">Project Value</label>
                  <input v-model="project.project_value" class="form-input-simple" placeholder="e.g. PHP 25,000,000" />
                </div>
                <div>
                  <label class="field-label">Start Date</label>
                  <input type="date" v-model="project.start_date" class="form-input-simple" />
                </div>
                <div>
                  <label class="field-label">End Date</label>
                  <input type="date" v-model="project.end_date" class="form-input-simple" />
                </div>
                <div>
                  <label class="field-label">Status</label>
                  <select v-model="project.status" class="form-input-simple">
                    <option value="Completed">Completed</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Pipeline">Pipeline</option>
                  </select>
                </div>
                <div class="span-2">
                  <label class="field-label">Brief Scope Description</label>
                  <textarea v-model="project.description" class="form-textarea-simple" rows="2" placeholder="Brief project summary..."></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="form-row-2">
            <div>
              <label class="field-label">Major Clients / Partners</label>
              <textarea v-model="form.proponent_profile.major_clients" class="form-textarea-simple" rows="3" placeholder="Key client companies..."></textarea>
            </div>
            <div>
              <label class="field-label">Certifications / Registrations</label>
              <textarea v-model="form.proponent_profile.certifications" class="form-textarea-simple" rows="3" placeholder="ISO compliance, accreditations..."></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 4 Panel: Documents & Consents -->
      <div v-show="currentStep === 4" class="step-panel">
        <div class="panel-header">
          <h3 class="section-title">Supporting Documents & Consents</h3>
          <p class="section-copy">Upload verification document attachments (up to 10MB each) and authorize privacy consents.</p>
        </div>

        <div class="form-body">
          <div class="documents-upload-grid">
            <!-- Registration Document -->
            <label class="file-card-new" :class="{ selected: registrationDocument }">
              <div class="file-card-icon-wrap">
                <Upload class="file-card-icon" />
              </div>
              <div class="file-card-body">
                <span class="file-title">SEC / DTI / CDA / Agency registration proof <strong>*</strong></span>
                <span class="file-hint">Business identity evidence</span>
                <span class="file-copy">{{ fileLabel(registrationDocument) }}</span>
              </div>
              <input type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp" @change="setFile($event, 'registration')" class="sr-only" />
            </label>

            <!-- Authorization SPA Document -->
            <label class="file-card-new" :class="{ selected: authorizationDocument }">
              <div class="file-card-icon-wrap">
                <Upload class="file-card-icon" />
              </div>
              <div class="file-card-body">
                <span class="file-title">Representative authorization letter / SPA <strong>*</strong></span>
                <span class="file-hint">Authority to transact with NDC</span>
                <span class="file-copy">{{ fileLabel(authorizationDocument) }}</span>
              </div>
              <input type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp" @change="setFile($event, 'authorization')" class="sr-only" />
            </label>

            <!-- Optional Capability Statement -->
            <label class="file-card-new optional" :class="{ selected: companyProfileDocument }">
              <div class="file-card-icon-wrap">
                <Upload class="file-card-icon" />
              </div>
              <div class="file-card-body">
                <span class="file-title">Company Capability Statement / Profile</span>
                <span class="file-hint">Optional reviewer context</span>
                <span class="file-copy">{{ fileLabel(companyProfileDocument) }}</span>
              </div>
              <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp" @change="setFile($event, 'companyProfile')" class="sr-only" />
            </label>
          </div>

          <div class="consents-block">
            <label class="consent-card-new">
              <input v-model="form.authority_confirmed" required type="checkbox" />
              <div class="consent-text">
                I confirm that I am authorized to create this account and submit documents on behalf of the organization named above.
              </div>
            </label>

            <label class="privacy-card-new">
              <input v-model="form.privacy_consent" required type="checkbox" />
              <div class="consent-text">
                <strong>Data Privacy Notice & Consent</strong>
                I consent to the collection, use, storage, and review of the personal, company, and uploaded document information provided in this registration for NDC PMS email verification, account approval, project intake, due diligence, and official communication, consistent with applicable government records and data privacy requirements.
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Navigation buttons footer -->
      <footer class="wizard-footer">
        <button
          v-if="currentStep > 1"
          type="button"
          @click="currentStep--"
          class="secondary-btn"
        >
          <ArrowLeft class="btn-icon" /> Back
        </button>
        <div v-else></div> <!-- spacer when back is missing -->

        <button
          v-if="currentStep < 4"
          type="button"
          :disabled="!currentStepValid"
          @click="goToStep(currentStep + 1)"
          class="primary-btn"
        >
          Next Step <ArrowRight class="btn-icon" />
        </button>

        <button
          v-else
          type="submit"
          :disabled="loading || !canSubmit"
          class="primary-btn submit-btn"
        >
          <CheckCircle2 class="btn-icon" />
          {{ loading ? 'Creating account...' : 'Create Proponent Account' }}
        </button>
      </footer>
    </form>

    <div class="signin-redirect">
      <router-link to="/login" class="redirect-link">
        Already registered? Sign in
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.register-page {
  --bg: #f8fafc;
  --card: #ffffff;
  --border: #e2e8f0;
  --text: #0f172a;
  --muted: #64748b;
  --accent: #2563eb;
  --c-subtle: #f1f5f9;
  --c-hover: #f8fafc;

  width: 100%;
}

:global(.dark) .register-page {
  --bg: #0f172a;
  --card: #162238;
  --border: #2b3a52;
  --text: #f1f5f9;
  --muted: #94a3b8;
  --accent: #3b82f6;
  --c-subtle: #1e293b;
  --c-hover: #1f2e4d;
}

.register-hero {
  margin-bottom: 2rem;
  text-align: center;
}

.hero-kicker {
  display: inline-flex;
  margin-bottom: 0.45rem;
  color: var(--accent);
  font-size: 0.72rem;
  font-weight: 900;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.register-hero h1 {
  margin: 0;
  color: var(--text);
  font-size: 2rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.register-hero p {
  max-width: 34rem;
  margin: 0.5rem auto 0;
  color: var(--muted);
  font-size: 0.95rem;
  line-height: 1.5;
}

/* Stepped progress bar styles */
.steps-progress-wrapper {
  margin-bottom: 1.5rem;
}

.steps-progress {
  position: relative;
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 24rem;
  margin: 1.5rem auto 0.5rem;
  padding: 0 0.5rem;
}

.step-progress-bar {
  position: absolute;
  top: 50%;
  left: 0.5rem;
  right: 0.5rem;
  height: 3px;
  background: var(--border);
  z-index: 1;
  transform: translateY(-50%);
}

.step-progress-bar-fill {
  height: 100%;
  background: var(--accent);
  width: 0%;
  transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.step-bubble {
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 9999px;
  background: var(--card);
  border: 2px solid var(--border);
  color: var(--muted);
  font-weight: 800;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
  cursor: pointer;
  transition: all 0.3s ease;
}

.step-bubble.active {
  border-color: var(--accent);
  background: var(--accent);
  color: white;
}

.step-bubble.current {
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18);
  font-weight: 900;
}

.check-icon {
  width: 1.25rem;
  height: 1.25rem;
}

.step-subtitle {
  font-weight: 800;
  font-size: 0.72rem;
}

.step-titles h2 {
  font-size: 1.15rem;
  font-weight: 800;
  margin-top: 0.15rem;
}

/* Form Styles */
.registration-form {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 0.9rem;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.025);
}

.step-panel {
  padding: 1.75rem;
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.panel-header {
  border-bottom: 1px solid var(--border);
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text);
  margin: 0;
}

.section-copy {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  color: var(--muted);
}

.form-body {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group-full {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.form-input-simple, select, textarea {
  width: 100%;
  min-height: 2.45rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
  background: var(--bg);
  color: var(--text);
  padding: 0 0.85rem;
  outline: none;
  font-size: 0.84rem;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.form-input-simple:focus, select:focus, textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

textarea {
  padding: 0.65rem 0.85rem;
  resize: vertical;
}

.field-label {
  display: block;
  font-size: 0.76rem;
  font-weight: 800;
  color: var(--text);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.field-label span {
  color: #ef4444;
}

/* Address & Password Sections */
.address-section, .security-section, .previous-projects-container {
  border-top: 1px dashed var(--border);
  padding-top: 1.25rem;
  margin-top: 0.5rem;
}

.address-section h4, .security-section h4, .projects-header h4 {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--accent);
  letter-spacing: 0.04em;
}

.address-copy {
  font-size: 0.78rem;
  color: var(--muted);
  margin: 0 0 0.85rem;
}

.address-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.address-grid .span-2 {
  grid-column: 1 / -1;
}

.address-preview {
  margin-top: 0.85rem;
  color: var(--muted);
  font-size: 0.82rem;
  background: var(--c-subtle);
  padding: 0.65rem 0.85rem;
  border-radius: 0.35rem;
  border-left: 3px solid var(--accent);
}

.password-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input-wrap input {
  padding-right: 2.75rem !important;
}

.password-toggle-btn {
  position: absolute;
  right: 0.85rem;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.password-toggle-btn:hover {
  color: var(--text);
}

.password-toggle-btn svg {
  width: 1.1rem;
  height: 1.1rem;
}

/* Projects grid */
.projects-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.projects-header h4 {
  margin-bottom: 0;
}

.add-project-btn {
  padding: 0.3rem 0.75rem;
  background: var(--accent);
  color: white;
  border: none;
  border-radius: 0.35rem;
  font-size: 0.76rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s;
}

.add-project-btn:hover {
  background: #1d4ed8;
}

.empty-projects-state {
  text-align: center;
  padding: 2rem;
  border: 1px dashed var(--border);
  border-radius: 0.5rem;
  color: var(--muted);
  font-size: 0.84rem;
  background: var(--c-hover);
  margin-bottom: 1rem;
}

.project-item-card {
  border: 1px solid var(--border);
  border-radius: 0.6rem;
  padding: 1.25rem;
  background: var(--c-subtle);
  position: relative;
  margin-bottom: 1.25rem;
}

.project-item-title {
  margin: 0 0 1rem;
  font-size: 0.76rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--muted);
  letter-spacing: 0.05em;
}

.remove-project-btn {
  position: absolute;
  top: 0.75rem;
  right: 0.85rem;
  background: transparent;
  border: none;
  color: var(--muted);
  font-size: 1.5rem;
  cursor: pointer;
  line-height: 1;
}

.remove-project-btn:hover {
  color: #ef4444;
}

.project-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
}

.project-form-grid .span-2 {
  grid-column: 1 / -1;
}

/* File Upload custom cards */
.documents-upload-grid {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.file-card-new {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.6rem;
  background: var(--card);
  cursor: pointer;
  transition: all 0.2s ease;
}

.file-card-new:hover {
  border-color: var(--accent);
  box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.05);
}

.file-card-new.selected {
  border-color: var(--accent);
  background: rgba(37, 99, 235, 0.04);
}

.file-card-icon-wrap {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.45rem;
  background: var(--c-subtle);
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.file-card-icon {
  width: 1.15rem;
  height: 1.15rem;
}

.file-card-body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.file-title {
  font-size: 0.85rem;
  font-weight: 800;
  color: var(--text);
}

.file-title strong {
  color: #ef4444;
}

.file-hint {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--muted);
  letter-spacing: 0.02em;
}

.file-copy {
  font-size: 0.76rem;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.file-card-new.optional {
  border-style: dashed;
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
  border-width: 0;
}

/* Consent checkboxes */
.consents-block {
  border-top: 1px dashed var(--border);
  padding-top: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.consent-card-new, .privacy-card-new {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--c-subtle);
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  cursor: pointer;
  transition: border-color 0.15s;
}

.consent-card-new:hover, .privacy-card-new:hover {
  border-color: var(--accent);
}

.consent-card-new input, .privacy-card-new input {
  margin-top: 0.2rem;
  width: 1.05rem;
  height: 1.05rem;
  accent-color: var(--accent);
  cursor: pointer;
  flex-shrink: 0;
}

.consent-text {
  font-size: 0.82rem;
  line-height: 1.5;
  color: var(--text);
}

.consent-text strong {
  display: block;
  margin-bottom: 0.2rem;
  color: var(--text);
  font-size: 0.85rem;
  font-weight: 800;
}

/* Wizard footer navigation */
.wizard-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem;
  border-top: 1px solid var(--border);
  background: var(--c-subtle);
}

.primary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  background: var(--accent);
  border: 1px solid var(--accent);
  color: white;
  min-height: 2.5rem;
  padding: 0 1.25rem;
  border-radius: 0.4rem;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.primary-btn:hover:not(:disabled) {
  background: #1d4ed8;
  border-color: #1d4ed8;
}

.primary-btn:disabled {
  opacity: 0.65;
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
  border-radius: 0.4rem;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.secondary-btn:hover {
  background: var(--c-hover);
  border-color: var(--accent);
  color: var(--accent);
}

.btn-icon {
  width: 0.95rem;
  height: 0.95rem;
}

.signin-redirect {
  margin-top: 2rem;
  text-align: center;
}

.redirect-link {
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--accent);
  text-decoration: none;
  transition: color 0.15s;
}

.redirect-link:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

/* Responsiveness adjustments */
@media (max-width: 768px) {
  .form-row-2, .project-form-grid {
    grid-template-columns: 1fr;
  }
  .address-grid {
    grid-template-columns: 1fr;
  }
}
</style>
