<template>
  <div class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50 p-4 !mt-0">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden border border-gray-200 dark:border-gray-700 flex flex-col">
      
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200 dark:border-gray-700 shrink-0">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Edit Profile</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Update your personal information</p>
        </div>
        <button @click="$emit('close')" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
          <X :size="18" class="text-gray-500 dark:text-gray-400" />
        </button>
      </div>

      <!-- Body -->
      <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">
        <!-- Name row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="First Name" required>
            <input v-model="form.first_name" type="text" class="form-input" />
          </FormField>
          <FormField label="Middle Name">
            <input v-model="form.middle_name" type="text" class="form-input" />
          </FormField>
          <FormField label="Last Name" required>
            <input v-model="form.last_name" type="text" class="form-input" />
          </FormField>
        </div>

        <!-- Suffix + Username -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Suffix">
            <input v-model="form.suffix" type="text" class="form-input" placeholder="Jr., Sr., III" />
          </FormField>
          <FormField label="Username" required>
            <input v-model="form.username" type="text" class="form-input" />
          </FormField>
        </div>

        <!-- Email + Phone -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Email" required>
            <input v-model="form.email" type="email" class="form-input" />
          </FormField>
          <FormField label="Phone Number">
            <input v-model="form.phone_number" type="text" class="form-input" />
          </FormField>
        </div>

        <!-- Department + Position -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Department">
            <input v-model="form.department" type="text" class="form-input" />
          </FormField>
          <FormField label="Position">
            <input v-model="form.position" type="text" class="form-input" />
          </FormField>
        </div>

        <!-- Address -->
        <FormField label="Address">
          <input v-model="form.address" type="text" class="form-input" />
        </FormField>

        <!-- Birth Date -->
        <FormField label="Birth Date">
          <input v-model="form.birth_date" type="date" class="form-input" />
        </FormField>

        <section v-if="showProponentFields" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 p-4 space-y-4">
          <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Company / Proponent Profile</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Maintain company background and project track record details that NDC approvers can review during SOI evaluation.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="Company / Proponent Name">
              <input v-model="form.organization_name" type="text" class="form-input" />
            </FormField>
            <FormField label="Organization Type">
              <input v-model="form.organization_type" type="text" class="form-input" />
            </FormField>
          </div>

          <FormField label="Registration No.">
            <input v-model="form.organization_registration_no" type="text" class="form-input" placeholder="SEC / DTI / CDA / Agency reference" />
          </FormField>

          <FormField label="Business Summary">
            <textarea v-model="form.proponent_profile.business_summary" class="form-input min-h-[92px] resize-y" placeholder="What your company does and which sectors you serve"></textarea>
          </FormField>

          <FormField label="Project Experience">
            <textarea v-model="form.proponent_profile.project_experience" class="form-input min-h-[92px] resize-y" placeholder="Experience relevant to investment, JV, infrastructure, operations, or implementation"></textarea>
          </FormField>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="Major Clients / Partners">
              <textarea v-model="form.proponent_profile.major_clients" class="form-input min-h-[92px] resize-y"></textarea>
            </FormField>
            <FormField label="Certifications / Registrations">
              <textarea v-model="form.proponent_profile.certifications" class="form-input min-h-[92px] resize-y"></textarea>
            </FormField>
          </div>
        </section>

        <!-- Error -->
        <p v-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-4 py-2 rounded-lg">
          {{ error }}
        </p>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 shrink-0">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
        >
          Cancel
        </button>
        <button
          @click="submit"
          :disabled="loading"
          class="px-5 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <Loader2 v-if="loading" :size="14" class="animate-spin" />
          {{ loading ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { X, Loader2 } from 'lucide-vue-next'
import FormField from './FormField.vue'
import { useProfileStore  } from '@/store/profile'
import { UpdateProfilePayload } from '@/types/profile'
import type { User } from '@/types/user'

const props = defineProps<{ profile: User }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>()

const profileStore = useProfileStore()
const loading = ref(false)
const error = ref<string | null>(null)

const defaultProponentProfile = () => ({
  business_summary: '',
  project_experience: '',
  previous_projects: '',
  major_clients: '',
  certifications: '',
})

const form = reactive<UpdateProfilePayload>({
  first_name: '',
  middle_name: '',
  last_name: '',
  suffix: '',
  email: '',
  username: '',
  phone_number: '',
  address: '',
  organization_name: '',
  organization_type: '',
  organization_registration_no: '',
  proponent_profile: defaultProponentProfile(),
  department: '',
  position: '',
  birth_date: '',
})

// Populate form from profile
watch(() => props.profile, (p) => {
  if (!p) return
  form.first_name = p.first_name ?? ''
  form.middle_name = p.middle_name ?? ''
  form.last_name = p.last_name ?? ''
  form.suffix = p.suffix ?? ''
  form.email = p.email ?? ''
  form.username = p.username ?? ''
  form.phone_number = p.phone_number ?? ''
  form.address = p.address ?? ''
  form.organization_name = p.organization_name ?? ''
  form.organization_type = p.organization_type ?? ''
  form.organization_registration_no = p.organization_registration_no ?? ''
  form.proponent_profile = {
    ...defaultProponentProfile(),
    ...(p.proponent_profile || {}),
  }
  form.department = p.department ?? ''
  form.position = p.position ?? ''
  form.birth_date = p.birth_date ?? ''
}, { immediate: true })

const showProponentFields = computed(() => {
  const roleName = props.profile?.role?.name?.toLowerCase()
  return roleName === 'proponent' || Boolean(
    props.profile?.organization_name ||
    props.profile?.organization_type ||
    props.profile?.organization_registration_no
  )
})

async function submit() {
  error.value = null
  loading.value = true
  const result = await profileStore.updateProfile(form)
  loading.value = false
  if (result.success) {
    emit('saved')
    emit('close')
  } else {
    error.value = result.message
  }
}
</script>
