<script lang="ts" setup>
import { ref, computed, watch } from 'vue'
import { useUserStore } from '@/store/user'
import { toast } from 'vue3-toastify'
import { Save, X, Eye, EyeOff } from 'lucide-vue-next'
import type { User, UserFormData } from '@/types/user'
import type { Role } from '@/types/accessSettings'

interface Props {
  user?: User
  roles: Role[]
}

interface Emits {
  (e: 'close'): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const userStore = useUserStore()

// State
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const form = ref<UserFormData>({
  first_name: '',
  middle_name: null,
  last_name: '',
  suffix: null,
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  default_role_id: 0,
  is_active: true,
})

// Computed
const submitting = computed(() => userStore.submitting)
const isEditMode = computed(() => props.user !== undefined)
const modalTitle = computed(() => isEditMode.value ? 'Edit User' : 'Add New User')
const modalSubtitle = computed(() =>
  isEditMode.value ? 'Update user information' : 'Create a new user account'
)
const submitButtonText = computed(() => isEditMode.value ? 'Update User' : 'Create User')

// resetForm must be defined BEFORE the watch that calls it immediately
const resetForm = () => {
  form.value = {
    first_name:            '',
    middle_name:           null,
    last_name:             '',
    suffix:                null,
    username:              '',
    email:                 '',
    password:              '',
    password_confirmation: '',
    default_role_id:       0,
    is_active:             true,
  }
  showPassword.value = false
  showPasswordConfirmation.value = false
}

// Initialize form when user prop changes
watch(() => props.user, (user) => {
  if (user) {
    form.value = {
      first_name:            user.first_name,
      middle_name:           user.middle_name,
      last_name:             user.last_name,
      suffix:                user.suffix,
      username:              user.username,
      email:                 user.email,
      password:              '',
      password_confirmation: '',
      default_role_id:       user.role?.id ?? 0,
      is_active:             user.is_active,
    }
  } else {
    resetForm()
  }
}, { immediate: true })

// Validation
const errors = ref<Partial<Record<keyof UserFormData, string>>>({})

const validate = (): boolean => {
  errors.value = {}

  if (!form.value.first_name.trim())  errors.value.first_name  = 'First name is required'
  if (!form.value.last_name.trim())   errors.value.last_name   = 'Last name is required'
  if (!form.value.username.trim())    errors.value.username    = 'Username is required'
  if (!form.value.email.trim())       errors.value.email       = 'Email is required'
  else if (!/\S+@\S+\.\S+/.test(form.value.email)) errors.value.email = 'Enter a valid email'
  if (!form.value.default_role_id)    errors.value.default_role_id = 'Role is required'

  if (!isEditMode.value) {
    if (!form.value.password)         errors.value.password    = 'Password is required'
    else if (form.value.password !== form.value.password_confirmation) {
      errors.value.password_confirmation = 'Passwords do not match'
    }
  } else if (form.value.password) {
    if (form.value.password !== form.value.password_confirmation) {
      errors.value.password_confirmation = 'Passwords do not match'
    }
  }

  return Object.keys(errors.value).length === 0
}

// Submit
const handleSubmit = async () => {
  if (!validate()) return

  try {
    // Build payload — exclude empty password on edit
    const payload: Partial<UserFormData> = {
      first_name:      form.value.first_name,
      middle_name:     form.value.middle_name || null,
      last_name:       form.value.last_name,
      suffix:          form.value.suffix || null,
      username:        form.value.username,
      email:           form.value.email,
      default_role_id: form.value.default_role_id,
      is_active:       form.value.is_active,
    }

    if (form.value.password) {
      payload.password              = form.value.password
      payload.password_confirmation = form.value.password_confirmation
    }

    if (isEditMode.value && props.user) {
      await userStore.updateUser(props.user.id, payload)
      toast.success('User updated successfully')
    } else {
      await userStore.createUser(payload as UserFormData)
      toast.success('User created successfully')
    }

    emit('saved')
  } catch (error: any) {
    // Handle Laravel validation errors
    if (error.response?.status === 422) {
      const laravelErrors = error.response.data.errors as Record<string, string[]>
      Object.keys(laravelErrors).forEach(field => {
        errors.value[field as keyof UserFormData] = laravelErrors[field][0]
      })
      toast.error('Please fix the errors and try again')
    } else {
      toast.error(error.response?.data?.message || 'Failed to save user')
    }
    console.error(error)
  }
}

const closeModal = () => {
  emit('close')
}
</script>

<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70 flex items-center justify-center z-50 p-4 !mt-0">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden border border-gray-200 dark:border-gray-700">

      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
        <div>
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ modalTitle }}
          </h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ modalSubtitle }}
          </p>
        </div>
        <button
          @click="closeModal"
          :disabled="submitting"
          class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-50"
        >
          <X :size="20" class="text-gray-500 dark:text-gray-400" />
        </button>
      </div>

      <!-- Loading Overlay -->
      <div
        v-if="submitting"
        class="absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 dark:bg-opacity-75 flex items-center justify-center z-10 rounded-lg"
      >
        <div class="flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">Saving user...</span>
        </div>
      </div>

      <!-- Form Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)] modal-scroll">
        <form @submit.prevent="handleSubmit" class="space-y-4">

          <!-- Name Row -->
          <div class="grid grid-cols-2 gap-4">
            <!-- First Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                First Name <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.first_name"
                type="text"
                placeholder="Juan"
                :class="[
                  'w-full rounded-lg border px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500',
                  errors.first_name
                    ? 'border-red-400 dark:border-red-500'
                    : 'border-gray-300 dark:border-gray-600'
                ]"
              />
              <p v-if="errors.first_name" class="mt-1 text-xs text-red-500">{{ errors.first_name }}</p>
            </div>

            <!-- Last Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Last Name <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.last_name"
                type="text"
                placeholder="Dela Cruz"
                :class="[
                  'w-full rounded-lg border px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500',
                  errors.last_name
                    ? 'border-red-400 dark:border-red-500'
                    : 'border-gray-300 dark:border-gray-600'
                ]"
              />
              <p v-if="errors.last_name" class="mt-1 text-xs text-red-500">{{ errors.last_name }}</p>
            </div>
          </div>

          <!-- Middle Name & Suffix Row -->
          <div class="grid grid-cols-3 gap-4">
            <!-- Middle Name -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Middle Name
              </label>
              <input
                v-model="form.middle_name"
                type="text"
                placeholder="Optional"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
              />
            </div>

            <!-- Suffix -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Suffix
              </label>
              <input
                v-model="form.suffix"
                type="text"
                placeholder="Jr., Sr."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
              />
            </div>
          </div>

          <!-- Username -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Username <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.username"
              type="text"
              placeholder="juan.delacruz"
              :class="[
                'w-full rounded-lg border px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 font-mono',
                errors.username
                  ? 'border-red-400 dark:border-red-500'
                  : 'border-gray-300 dark:border-gray-600'
              ]"
            />
            <p v-if="errors.username" class="mt-1 text-xs text-red-500">{{ errors.username }}</p>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Email Address <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.email"
              type="email"
              placeholder="juan@example.com"
              :class="[
                'w-full rounded-lg border px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500',
                errors.email
                  ? 'border-red-400 dark:border-red-500'
                  : 'border-gray-300 dark:border-gray-600'
              ]"
            />
            <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Password
              <span v-if="!isEditMode" class="text-red-500">*</span>
              <span v-else class="text-gray-400 dark:text-gray-500 font-normal text-xs ml-1">(leave blank to keep current)</span>
            </label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Min. 8 characters"
                :class="[
                  'w-full rounded-lg border px-4 py-2.5 text-sm pr-10 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500',
                  errors.password
                    ? 'border-red-400 dark:border-red-500'
                    : 'border-gray-300 dark:border-gray-600'
                ]"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
              >
                <Eye v-if="!showPassword" :size="16" />
                <EyeOff v-else :size="16" />
              </button>
            </div>
            <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password }}</p>
          </div>

          <!-- Password Confirmation -->
          <div v-if="!isEditMode || form.password">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Confirm Password
              <span v-if="!isEditMode" class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input
                v-model="form.password_confirmation"
                :type="showPasswordConfirmation ? 'text' : 'password'"
                placeholder="Re-enter password"
                :class="[
                  'w-full rounded-lg border px-4 py-2.5 text-sm pr-10 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500',
                  errors.password_confirmation
                    ? 'border-red-400 dark:border-red-500'
                    : 'border-gray-300 dark:border-gray-600'
                ]"
              />
              <button
                type="button"
                @click="showPasswordConfirmation = !showPasswordConfirmation"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
              >
                <Eye v-if="!showPasswordConfirmation" :size="16" />
                <EyeOff v-else :size="16" />
              </button>
            </div>
            <p v-if="errors.password_confirmation" class="mt-1 text-xs text-red-500">{{ errors.password_confirmation }}</p>
          </div>

          <!-- Role -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Role <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.default_role_id"
              :class="[
                'w-full rounded-lg border px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white',
                errors.default_role_id
                  ? 'border-red-400 dark:border-red-500'
                  : 'border-gray-300 dark:border-gray-600'
              ]"
            >
              <option :value="0" disabled>Select a role</option>
              <option v-for="role in roles" :key="role.id" :value="role.id">
                {{ role.name }}
              </option>
            </select>
            <p v-if="errors.default_role_id" class="mt-1 text-xs text-red-500">{{ errors.default_role_id }}</p>
          </div>

          <!-- Status Toggle -->
          <div class="flex items-center justify-between py-2">
            <div>
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Status</p>
              <p class="text-xs text-gray-500 dark:text-gray-400">Inactive users cannot log in</p>
            </div>
            <button
              type="button"
              @click="form.is_active = !form.is_active"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                form.is_active
                  ? 'bg-blue-600 dark:bg-blue-500'
                  : 'bg-gray-300 dark:bg-gray-600'
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
                  form.is_active ? 'translate-x-6' : 'translate-x-1'
                ]"
              />
            </button>
          </div>

        </form>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-3 p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
        <button
          @click="closeModal"
          :disabled="submitting"
          class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm font-medium disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          @click="handleSubmit"
          :disabled="submitting"
          class="px-4 py-2.5 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors flex items-center gap-2 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <Save v-else :size="16" />
          <span>{{ submitButtonText }}</span>
        </button>
      </div>

    </div>
  </div>
</template>