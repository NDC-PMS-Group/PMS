<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import { useAuthStore } from '@/store/auth';
import { usePsgcStore } from '@/store/psgc';

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

const canSubmit = computed(() =>
  form.organization_name.trim() &&
  form.organization_type.trim() &&
  form.organization_registration_no.trim() &&
  form.first_name.trim() &&
  form.last_name.trim() &&
  form.email.trim() &&
  form.phone_number.trim() &&
  form.address.trim() &&
  form.authority_confirmed &&
  form.privacy_consent &&
  registrationDocument.value &&
  authorizationDocument.value &&
  form.password.length >= 8 &&
  form.password === form.password_confirmation
);

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

const fileLabel = (file: File | null) => file ? `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)` : 'No file selected';

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
  payload.append('password_confirmation', form.password_confirmation);
  payload.append('first_name', form.first_name.trim());
  payload.append('last_name', form.last_name.trim());
  payload.append('phone_number', form.phone_number.trim());
  payload.append('organization_name', form.organization_name.trim());
  payload.append('organization_type', form.organization_type.trim());
  payload.append('organization_registration_no', form.organization_registration_no.trim());
  payload.append('address', form.address.trim());
  payload.append('authority_confirmed', form.authority_confirmed ? '1' : '0');

  Object.entries(form.proponent_profile).forEach(([key, value]) => {
    // Exclude legacy previous_projects flat string to prevent duplication
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
    errorMessage.value = 'Please complete all required details, attach the required documents, confirm authority and data privacy consent, and match the passwords.';
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
      <p>Register your organization before submitting an LOI or project proposal.</p>
    </div>

    <div
      v-if="errorMessage"
      class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
    >
      {{ errorMessage }}
    </div>

    <form class="registration-form" @submit.prevent="submit">
      <section class="section-panel company-panel">
        <div>
          <h2 class="section-title">Company</h2>
          <p class="section-copy">Use the registered legal name and registration details that match your supporting documents.</p>
        </div>
        <div>
          <label class="field-label">Company / Proponent Name <span>*</span></label>
          <input v-model="form.organization_name" required class="form-input-simple" placeholder="Registered company or organization name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
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
            <input v-model="form.organization_registration_no" required class="form-input-simple" placeholder="SEC / DTI / CDA / Agency reference" />
          </div>
        </div>
      </section>

      <section class="section-panel representative-panel">
        <div>
          <h2 class="section-title">Authorized Representative</h2>
          <p class="section-copy">This person will receive email verification and account approval notices.</p>
        </div>
        <div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="field-label">Representative First Name <span>*</span></label>
              <input v-model="form.first_name" required class="form-input-simple" />
            </div>
            <div>
              <label class="field-label">Representative Last Name <span>*</span></label>
              <input v-model="form.last_name" required class="form-input-simple" />
            </div>
          </div>
        </div>
      </section>

      <section class="section-panel contact-panel">
        <div>
          <h2 class="section-title">Contact</h2>
          <p class="section-copy">NDC will use these details for verification, account approval, and registration follow-ups.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label">Email Address <span>*</span></label>
            <input v-model="form.email" required type="email" class="form-input-simple" placeholder="proposal@company.com" />
          </div>
          <div>
            <label class="field-label">Contact Number <span>*</span></label>
            <input v-model="form.phone_number" required class="form-input-simple" placeholder="+63" />
          </div>
        </div>
      </section>

      <section class="section-panel address-panel">
        <div>
          <h2 class="section-title">Business Address</h2>
          <p class="section-copy">Choose the administrative location for the business address and then enter the office or street line.</p>
        </div>
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
            <input
              v-model="form.address_line"
              required
              class="form-input-simple"
              placeholder="Floor, building, street, or office suite"
            />
          </div>
        </div>
        <p class="address-preview">
          {{ form.address || 'Select the location details and enter the office line to build the full business address.' }}
        </p>
      </section>

      <section class="section-panel background-panel">
        <div>
          <h2 class="section-title">Company Background</h2>
          <p class="section-copy">This helps NDC reviewers understand your organization before proposal evaluation.</p>
        </div>
        <div>
          <label class="field-label">Business Summary</label>
          <textarea v-model="form.proponent_profile.business_summary" class="form-textarea-simple" rows="3" placeholder="What your company does and which sectors you serve"></textarea>
        </div>
        <div>
          <label class="field-label">Project Experience</label>
          <textarea v-model="form.proponent_profile.project_experience" class="form-textarea-simple" rows="3" placeholder="Experience relevant to investment, JV, infrastructure, operations, or implementation"></textarea>
        </div>
        <div class="previous-projects-container col-span-2">
          <div class="flex justify-between items-center mb-3">
            <label class="field-label font-bold mb-0">Previous Projects (Track Record)</label>
            <button type="button" @click="addPreviousProject" class="px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition">
              + Add Previous Project
            </button>
          </div>
          
          <div v-if="previous_projects.length === 0" class="p-4 border border-dashed border-gray-300 rounded text-center text-gray-500 text-sm mb-4">
            No previous projects added yet. Click "+ Add Previous Project" above to record your track record.
          </div>

          <div v-for="(project, index) in previous_projects" :key="index" class="p-4 border border-gray-200 rounded-lg mb-4 bg-gray-50 dark:bg-slate-800 relative">
            <button type="button" @click="removePreviousProject(index)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold text-lg">
              &times;
            </button>
            <h4 class="font-bold text-xs uppercase tracking-wider text-gray-500 mb-3">Project #{{ index + 1 }}</h4>
            
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="field-label">Project Title <span class="text-red-500">*</span></label>
                <input v-model="project.title" required class="form-input-simple" placeholder="e.g. Solar Power Plant Installation" />
              </div>
              <div>
                <label class="field-label">Client / Partner</label>
                <input v-model="project.client_partner" class="form-input-simple" placeholder="e.g. LGU Dagupan or CleanEnergy Corp" />
              </div>
              <div>
                <label class="field-label">Project Value</label>
                <input v-model="project.project_value" class="form-input-simple" placeholder="e.g. PHP 15,000,000" />
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
              <div class="sm:col-span-2">
                <label class="field-label">Brief Description</label>
                <textarea v-model="project.description" class="form-textarea-simple" rows="2" placeholder="Describe the scope, outcome, or details of the project"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label">Major Clients / Partners</label>
            <textarea v-model="form.proponent_profile.major_clients" class="form-textarea-simple" rows="3" placeholder="Key customers, LGUs, agencies, partners, or financiers"></textarea>
          </div>
          <div>
            <label class="field-label">Certifications / Registrations</label>
            <textarea v-model="form.proponent_profile.certifications" class="form-textarea-simple" rows="3" placeholder="SEC, DTI, permits, accreditations, ISO, licenses, or compliance references"></textarea>
          </div>
        </div>
      </section>

      <section class="section-panel documents-panel">
        <div>
          <h2 class="section-title">Supporting Documents</h2>
          <p class="section-copy">Upload PDF, Word, Excel, CSV, or image files up to 10MB each. Proposal files stay in Requirements after approval.</p>
        </div>
        <div class="document-grid">
          <label class="file-card" :class="{ selected: registrationDocument }">
            <span class="file-title">SEC / DTI / CDA / Agency registration proof <strong>*</strong></span>
            <span class="file-hint">Business identity evidence</span>
            <span class="file-copy">{{ fileLabel(registrationDocument) }}</span>
            <input type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp" @change="setFile($event, 'registration')" />
          </label>
          <label class="file-card" :class="{ selected: authorizationDocument }">
            <span class="file-title">Representative authorization letter / SPA <strong>*</strong></span>
            <span class="file-hint">Authority to transact with NDC</span>
            <span class="file-copy">{{ fileLabel(authorizationDocument) }}</span>
            <input type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp" @change="setFile($event, 'authorization')" />
          </label>
          <label class="file-card optional" :class="{ selected: companyProfileDocument }">
            <span class="file-title">Company profile / capability statement</span>
            <span class="file-hint">Optional reviewer context</span>
            <span class="file-copy">{{ fileLabel(companyProfileDocument) }}</span>
            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp" @change="setFile($event, 'companyProfile')" />
          </label>
        </div>
      </section>

      <section class="section-panel security-panel">
        <div>
          <h2 class="section-title">Account Security</h2>
          <p class="section-copy">Use at least 8 characters. Your account remains pending until NDC approves it.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label">Password <span>*</span></label>
            <input v-model="form.password" required type="password" minlength="8" class="form-input-simple" />
          </div>
          <div>
            <label class="field-label">Confirm Password <span>*</span></label>
            <input v-model="form.password_confirmation" required type="password" minlength="8" class="form-input-simple" />
          </div>
        </div>
      </section>

      <label class="consent-card">
        <input v-model="form.authority_confirmed" required type="checkbox" />
        <span>
          I confirm that I am authorized to create this account and submit documents on behalf of the organization named above.
        </span>
      </label>

      <label class="privacy-card">
        <input v-model="form.privacy_consent" required type="checkbox" />
        <span>
          <strong>Data Privacy Notice</strong>
          I consent to the collection, use, storage, and review of the personal, company, and uploaded document information provided in this registration for NDC PMS email verification, account approval, project intake, due diligence, and official communication, consistent with applicable government records and data privacy requirements.
        </span>
      </label>

      <button
        type="submit"
        :disabled="loading || !canSubmit"
        class="w-full rounded-lg bg-custom-500 px-4 py-3 text-base font-semibold text-white transition hover:bg-custom-600 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {{ loading ? 'Creating account...' : 'Create Proponent Account' }}
      </button>
    </form>

    <div class="mt-8 text-center">
      <router-link to="/login" class="text-sm font-semibold text-custom-600 hover:text-custom-700">
        Already registered? Sign in
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.register-page {
  width: 100%;
}
.register-hero {
  margin-bottom: 1.5rem;
  text-align: center;
}
.hero-kicker {
  display: inline-flex;
  margin-bottom: 0.45rem;
  color: rgb(37 99 235);
  font-size: 0.72rem;
  font-weight: 900;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
.register-hero h1 {
  margin: 0;
  color: rgb(15 23 42);
  font-size: 2rem;
  font-weight: 900;
  letter-spacing: 0;
  line-height: 1.15;
}
.register-hero p {
  max-width: 34rem;
  margin: 0.5rem auto 0;
  color: rgb(71 85 105);
  font-size: 0.95rem;
  line-height: 1.5;
}
.registration-form {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0;
  border: 1px solid rgb(226 232 240);
  border-radius: 0.9rem;
  background: white;
  overflow: hidden;
}
.registration-form > .section-panel,
.registration-form > .consent-card,
.registration-form > .privacy-card,
.registration-form > button {
  min-width: 0;
}
.company-panel,
.representative-panel,
.contact-panel,
.background-panel,
.documents-panel,
.security-panel,
.consent-card,
.privacy-card {
  grid-column: 1 / -1;
}
.registration-form > button {
  grid-column: 1 / -1;
  margin: 1.25rem;
  width: calc(100% - 2.5rem);
}
.form-input-simple {
  width: 100%;
  min-width: 0;
  border: 1px solid rgb(203 213 225);
  border-radius: 0.5rem;
  background: white;
  padding: 0.75rem 1rem;
  color: rgb(15 23 42);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.form-input-simple:focus {
  border-color: rgb(14 165 233);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, .16);
}
.field-label {
  display: block;
  margin-bottom: 0.5rem;
  color: rgb(51 65 85);
  font-size: 0.875rem;
  font-weight: 700;
}
.field-label span {
  color: rgb(220 38 38);
}
.form-textarea-simple {
  width: 100%;
  min-width: 0;
  border: 1px solid rgb(203 213 225);
  border-radius: 0.5rem;
  background: white;
  padding: 0.75rem 1rem;
  color: rgb(15 23 42);
  outline: none;
  resize: vertical;
  transition: border-color .15s, box-shadow .15s;
}
.form-textarea-simple:focus {
  border-color: rgb(14 165 233);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, .16);
}
.section-panel {
  display: grid;
  gap: 0.95rem;
  align-content: start;
  border: 0;
  border-bottom: 1px solid rgb(226 232 240);
  border-radius: 0;
  background: white;
  padding: 1.25rem;
}
.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: rgb(15 23 42);
}
.section-copy {
  margin-top: 0.2rem;
  font-size: 0.86rem;
  color: rgb(100 116 139);
}
.documents-panel {
  align-content: stretch;
}
.address-panel {
  gap: 1rem;
}
.address-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem;
}
.address-grid .span-2 {
  grid-column: 1 / -1;
}
.document-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}
.document-grid .file-card.optional {
  grid-column: 1 / -1;
}
.file-card {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(11rem, auto);
  gap: 0.35rem 1rem;
  align-items: center;
  cursor: pointer;
  border: 1px solid rgb(203 213 225);
  border-radius: 0.6rem;
  background: white;
  padding: 0.9rem;
  transition: border-color .15s, box-shadow .15s;
}
.file-card:hover {
  border-color: rgb(37 99 235);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
}
.file-card.selected {
  border-color: rgb(37 99 235);
  background: rgb(239 246 255);
  box-shadow: inset 0 0 0 1px rgba(37, 99, 235, .12);
}
.file-title {
  color: rgb(15 23 42);
  font-size: 0.92rem;
  font-weight: 800;
  line-height: 1.35;
  grid-column: 1 / 2;
}
.file-title strong {
  color: rgb(220 38 38);
}
.file-hint {
  grid-column: 1 / 2;
  width: fit-content;
  border-radius: 999px;
  background: rgb(241 245 249);
  color: rgb(71 85 105);
  padding: 0.18rem 0.55rem;
  font-size: 0.68rem;
  font-weight: 800;
}
.file-copy {
  grid-column: 1 / 2;
  color: rgb(100 116 139);
  font-size: 0.8rem;
  overflow-wrap: anywhere;
}
.file-card input {
  grid-column: 2 / 3;
  grid-row: 1 / span 3;
  width: 100%;
  color: rgb(71 85 105);
  font-size: 0.8rem;
}
.file-card.optional {
  border-style: dashed;
}
.address-preview {
  color: rgb(100 116 139);
  font-size: 0.82rem;
  line-height: 1.5;
}
.consent-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  border: 0;
  border-bottom: 1px solid rgb(226 232 240);
  border-radius: 0;
  background: rgb(248 250 252);
  padding: 1rem 1.25rem;
  color: rgb(30 41 59);
  font-size: 0.9rem;
  font-weight: 700;
  line-height: 1.5;
}
.consent-card input {
  margin-top: 0.2rem;
  width: 1rem;
  height: 1rem;
}
.privacy-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  border: 0;
  border-bottom: 1px solid rgb(226 232 240);
  border-radius: 0;
  background: rgb(248 250 252);
  padding: 1rem 1.25rem;
  color: rgb(51 65 85);
  font-size: 0.84rem;
  line-height: 1.55;
}
.privacy-card strong {
  display: block;
  margin-bottom: 0.22rem;
  color: rgb(15 23 42);
  font-size: 0.9rem;
}
.privacy-card input {
  margin-top: 0.24rem;
  width: 1rem;
  height: 1rem;
  flex: 0 0 auto;
}
:global(.dark) .register-hero h1 {
  color: rgb(248 250 252);
}
:global(.dark) .register-hero p {
  color: rgb(148 163 184);
}
:global(.dark) .registration-form {
  border-color: rgb(51 65 85);
  background: rgb(15 23 42);
}
:global(.dark) .form-input-simple {
  border-color: rgb(71 85 105);
  background: rgb(30 41 59);
  color: rgb(241 245 249);
}
:global(.dark) .field-label {
  color: rgb(226 232 240);
}
:global(.dark) .form-textarea-simple {
  border-color: rgb(71 85 105);
  background: rgb(30 41 59);
  color: rgb(241 245 249);
}
:global(.dark) .section-panel {
  border-color: rgb(51 65 85);
  background: rgb(15 23 42);
}
:global(.dark) .section-title {
  color: rgb(241 245 249);
}
:global(.dark) .section-copy {
  color: rgb(148 163 184);
}
:global(.dark) .file-card {
  border-color: rgb(51 65 85);
  background: rgb(15 23 42);
}
:global(.dark) .file-card:hover {
  border-color: rgb(96 165 250);
  box-shadow: none;
}
:global(.dark) .file-card.selected {
  border-color: rgb(96 165 250);
  background: rgb(23 37 84);
}
:global(.dark) .file-title {
  color: rgb(241 245 249);
}
:global(.dark) .file-hint {
  background: rgb(30 41 59);
  color: rgb(203 213 225);
}
:global(.dark) .file-copy,
:global(.dark) .file-card input {
  color: rgb(148 163 184);
}
:global(.dark) .consent-card {
  border-color: rgb(51 65 85);
  background: rgb(15 23 42);
  color: rgb(219 234 254);
}
:global(.dark) .privacy-card {
  border-color: rgb(51 65 85);
  background: rgb(15 23 42);
  color: rgb(203 213 225);
}
:global(.dark) .privacy-card strong {
  color: rgb(248 250 252);
}
@media (max-width: 760px) {
  .address-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 860px) {
  .file-card {
    grid-template-columns: 1fr;
  }

  .file-title,
  .file-hint,
  .file-copy,
  .file-card input {
    grid-column: 1 / -1;
    grid-row: auto;
  }
}
</style>
