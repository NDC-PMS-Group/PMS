<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue3-toastify';
import { useAuthStore } from '@/store/auth';

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
  organization_name: '',
  organization_type: 'Private Company',
  organization_registration_no: '',
  first_name: '',
  last_name: '',
  email: '',
  phone_number: '',
  address: '',
  password: '',
  password_confirmation: '',
});

const loading = ref(false);
const errorMessage = ref('');

const canSubmit = computed(() =>
  form.organization_name.trim() &&
  form.first_name.trim() &&
  form.last_name.trim() &&
  form.email.trim() &&
  form.password.length >= 8 &&
  form.password === form.password_confirmation
);

const submit = async () => {
  errorMessage.value = '';

  if (!canSubmit.value) {
    errorMessage.value = 'Please complete the required fields and confirm the password.';
    return;
  }

  loading.value = true;
  try {
    const result = await authStore.register({
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      first_name: form.first_name,
      last_name: form.last_name,
      phone_number: form.phone_number,
      organization_name: form.organization_name,
      organization_type: form.organization_type,
      organization_registration_no: form.organization_registration_no,
      address: form.address,
    });

    if (!result.success) {
      errorMessage.value = result.message;
      return;
    }

    toast.success('Proponent account created');
    await router.push('/projects');
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="w-full">
    <div class="mb-8 text-center">
      <h1 class="mb-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-custom-500">
        Proponent Registration
      </h1>
      <p class="text-base text-slate-500 dark:text-slate-300">
        Register your company before submitting an LOI or project proposal.
      </p>
    </div>

    <div
      v-if="errorMessage"
      class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
    >
      {{ errorMessage }}
    </div>

    <form class="space-y-5" @submit.prevent="submit">
      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Company / Proponent Name</label>
        <input v-model="form.organization_name" required class="form-input-simple" placeholder="Registered company or organization name" />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Organization Type</label>
          <select v-model="form.organization_type" class="form-input-simple">
            <option>Private Company</option>
            <option>Government Agency</option>
            <option>LGU</option>
            <option>NGO</option>
            <option>Cooperative</option>
            <option>Other</option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Registration No.</label>
          <input v-model="form.organization_registration_no" class="form-input-simple" placeholder="SEC / DTI / CDA / Agency reference" />
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Representative First Name</label>
          <input v-model="form.first_name" required class="form-input-simple" />
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Representative Last Name</label>
          <input v-model="form.last_name" required class="form-input-simple" />
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Email Address</label>
          <input v-model="form.email" required type="email" class="form-input-simple" placeholder="proposal@company.com" />
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Contact Number</label>
          <input v-model="form.phone_number" class="form-input-simple" placeholder="+63" />
        </div>
      </div>

      <div>
        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Business Address</label>
        <input v-model="form.address" class="form-input-simple" placeholder="Office address" />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Password</label>
          <input v-model="form.password" required type="password" class="form-input-simple" />
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Confirm Password</label>
          <input v-model="form.password_confirmation" required type="password" class="form-input-simple" />
        </div>
      </div>

      <button
        type="submit"
        :disabled="loading"
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
.form-input-simple {
  width: 100%;
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
:global(.dark) .form-input-simple {
  border-color: rgb(71 85 105);
  background: rgb(30 41 59);
  color: rgb(241 245 249);
}
</style>
