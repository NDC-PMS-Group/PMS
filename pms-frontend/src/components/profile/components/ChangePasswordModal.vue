<template>
  <div class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50 p-4 !mt-0">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md border border-gray-200 dark:border-gray-700">
      
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200 dark:border-gray-700">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Change Password</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Enter your current and new password</p>
        </div>
        <button @click="$emit('close')" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
          <X :size="18" class="text-gray-500 dark:text-gray-400" />
        </button>
      </div>

      <!-- Body -->
      <div class="px-6 py-5 space-y-4">
        <FormField label="Current Password" required>
          <div class="relative">
            <input
              v-model="form.current_password"
              :type="showCurrent ? 'text' : 'password'"
              class="form-input pr-10"
              placeholder="Your current password"
            />
            <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <Eye v-if="!showCurrent" :size="16" />
              <EyeOff v-else :size="16" />
            </button>
          </div>
          <p v-if="fieldErrors.current_password" class="text-xs text-red-500 mt-1">
            {{ fieldErrors.current_password[0] }}
          </p>
        </FormField>

        <FormField label="New Password" required>
          <div class="relative">
            <input
              v-model="form.new_password"
              :type="showNew ? 'text' : 'password'"
              class="form-input pr-10"
              placeholder="Minimum 8 characters"
            />
            <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <Eye v-if="!showNew" :size="16" />
              <EyeOff v-else :size="16" />
            </button>
          </div>
        </FormField>

        <FormField label="Confirm New Password" required>
          <input
            v-model="form.new_password_confirmation"
            :type="showNew ? 'text' : 'password'"
            class="form-input"
            placeholder="Repeat new password"
          />
          <p v-if="mismatch" class="text-xs text-red-500 mt-1">Passwords do not match.</p>
        </FormField>

        <!-- Strength indicator -->
        <div v-if="form.new_password" class="space-y-1">
          <div class="flex gap-1">
            <div v-for="i in 4" :key="i" :class="[
              'h-1 flex-1 rounded-full transition-all',
              strength >= i ? strengthColor : 'bg-gray-200 dark:bg-gray-700'
            ]" />
          </div>
          <p :class="strengthTextColor" class="text-xs font-medium">{{ strengthLabel }}</p>
        </div>

        <!-- Global error -->
        <p v-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-2 rounded-lg">
          {{ error }}
        </p>

        <!-- Success -->
        <div v-if="success" class="flex items-center gap-2 text-sm text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-4 py-2 rounded-lg">
          <CheckCircle2 :size="16" />
          Password changed successfully!
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
        >
          Cancel
        </button>
        <button
          @click="submit"
          :disabled="loading || mismatch || !form.new_password || !form.current_password"
          class="px-5 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <Loader2 v-if="loading" :size="14" class="animate-spin" />
          {{ loading ? 'Changing...' : 'Change Password' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { X, Loader2, Eye, EyeOff, CheckCircle2 } from 'lucide-vue-next'
import FormField from './FormField.vue'
import { useProfileStore } from '@/store/profile'

const emit = defineEmits<{ (e: 'close'): void }>()
const profileStore = useProfileStore()

const loading = ref(false)
const error = ref<string | null>(null)
const success = ref(false)
const showCurrent = ref(false)
const showNew = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})

const form = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const mismatch = computed(() =>
  form.new_password_confirmation.length > 0 &&
  form.new_password !== form.new_password_confirmation
)

// Password strength
const strength = computed(() => {
  const p = form.new_password
  let score = 0
  if (p.length >= 8) score++
  if (/[A-Z]/.test(p)) score++
  if (/[0-9]/.test(p)) score++
  if (/[^A-Za-z0-9]/.test(p)) score++
  return score
})

const strengthLabel = computed(() => ['Weak', 'Fair', 'Good', 'Strong'][strength.value - 1] ?? '')
const strengthColor = computed(() => ['bg-red-500', 'bg-yellow-500', 'bg-blue-500', 'bg-green-500'][strength.value - 1] ?? '')
const strengthTextColor = computed(() => ['text-red-600', 'text-yellow-600', 'text-blue-600', 'text-green-600'][strength.value - 1] ?? '')

async function submit() {
  error.value = null
  fieldErrors.value = {}
  loading.value = true

  const result = await profileStore.changePassword(form)
  loading.value = false

  if (result.success) {
    success.value = true
    setTimeout(() => emit('close'), 1500)
  } else {
    error.value = result.message
    if (result.errors) fieldErrors.value = result.errors
  }
}
</script>