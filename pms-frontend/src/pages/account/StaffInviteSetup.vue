<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import axiosInstance from '@/utils/axiosInstance'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const form = reactive({
  password: '',
  password_confirmation: '',
})

const canSubmit = computed(() =>
  form.password.length >= 8 && form.password === form.password_confirmation && !loading.value
)

async function submit() {
  if (!canSubmit.value) return
  loading.value = true
  try {
    await axiosInstance.post(`/api/staff-invitations/${route.params.token}/accept`, form)
    toast.success('Account setup complete. Please sign in.')
    router.push('/login')
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to complete staff invitation.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 px-4 py-10 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <form class="mx-auto grid w-full max-w-md gap-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
      <div>
        <p class="text-xs font-black uppercase tracking-widest text-blue-600">NDC PMS</p>
        <h1 class="mt-1 text-2xl font-black">Set up your staff account</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create your password to activate this invited admin or staff account.</p>
      </div>

      <label class="grid gap-1.5 text-sm font-bold">
        Password
        <input v-model="form.password" type="password" minlength="8" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" required />
      </label>

      <label class="grid gap-1.5 text-sm font-bold">
        Confirm Password
        <input v-model="form.password_confirmation" type="password" minlength="8" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" required />
      </label>

      <p v-if="form.password_confirmation && form.password !== form.password_confirmation" class="text-sm font-semibold text-red-600">Passwords do not match.</p>

      <button type="submit" :disabled="!canSubmit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
        {{ loading ? 'Activating...' : 'Activate Account' }}
      </button>
    </form>
  </div>
</template>
