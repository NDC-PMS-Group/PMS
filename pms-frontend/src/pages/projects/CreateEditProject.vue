<script lang="ts" setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Save, Loader2 } from 'lucide-vue-next'
import { toast } from 'vue3-toastify'
import { useProjectStore } from '@/store/projects'

import StepIndicator     from '@/components/projects/ProjectForm/StepIndicator.vue'
import ProjectFormWizard from '@/components/projects/ProjectForm/ProjectFormWizard.vue'

// ── Router / Store ──────────────────────────────────────────────────────
const route        = useRoute()
const router       = useRouter()
const projectStore = useProjectStore()

// ── Computed ────────────────────────────────────────────────────────────
const projectId  = computed(() => route.params.id ? Number(route.params.id) : null)
const isEditMode = computed(() => !!projectId.value)

// ── State ───────────────────────────────────────────────────────────────
const currentStep = ref(0)
// Show the loader from the very first paint when entering edit mode,
// otherwise the user sees an empty form while we silently fetch data.
const loading     = ref(!!route.params.id)
const saving      = ref(false)

const formRef = ref<InstanceType<typeof ProjectFormWizard> | null>(null)

// ── Steps Config ────────────────────────────────────────────────────────
const STEPS = [
  { id: 0, label: 'Basic Info',  short: 'Info'      },
  { id: 1, label: 'Status',      short: 'Status'    },
  { id: 2, label: 'Financial',   short: 'Money'     },
  { id: 3, label: 'Location',    short: 'Location'  },
  { id: 4, label: 'Proponent',   short: 'Proponent' },
]
const totalSteps = STEPS.length

// ── Navigation ──────────────────────────────────────────────────────────
const goBack    = () => { if (currentStep.value > 0) currentStep.value-- }
const goToStep  = (n: number) => { if (n < currentStep.value) currentStep.value = n }
const goNext    = () => {
  if (!formRef.value?.validateStep(currentStep.value)) return
  if (currentStep.value < totalSteps - 1) currentStep.value++
}

// ── Page Title ──────────────────────────────────────────────────────────
const pageTitle    = computed(() => (isEditMode.value ? 'Edit Project' : 'Create New Project'))
const pageSubtitle = computed(() =>
  isEditMode.value
    ? 'Update the details of this project'
    : 'Fill in the project details across the steps below',
)

// ── Submit ──────────────────────────────────────────────────────────────
const submitForm = async () => {
  if (!formRef.value) return

  // Validate every step before submitting; jump to the first failing one.
  for (let i = 0; i < totalSteps; i++) {
    if (!formRef.value.validateStep(i)) {
      currentStep.value = i
      return
    }
  }

  saving.value = true
  try {
    const payload = formRef.value.getPayload()

    if (isEditMode.value) {
      await projectStore.updateProject(projectId.value!, payload)
      toast.success('Project updated')
    } else {
      await projectStore.createProject(payload)
      toast.success('Project created')
    }

    router.push('/projects')
  } catch (err: any) {
    if (err?.response?.data?.errors) {
      formRef.value.applyServerErrors(err.response.data.errors)
      // Jump to the first step that now has errors.
      for (let i = 0; i < totalSteps; i++) {
        if (formRef.value.stepHasErrors(i)) {
          currentStep.value = i
          break
        }
      }
      const firstMessage = (Object.values(err.response.data.errors)[0] as any)?.[0]
      if (firstMessage) toast.error(firstMessage)
    } else {
      const msg =
        err?.response?.data?.message ||
        err?.response?.data?.error ||
        err?.message ||
        'Failed to save project'
      toast.error(msg)
    }
  } finally {
    saving.value = false
  }
}

// ── Cancel ──────────────────────────────────────────────────────────────
const cancel = () => router.push('/projects')

// ── Init ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (isEditMode.value) {
    // Edit mode: loader is already showing (initial state). Fire lookup
    // taxonomies and the project fetch in parallel to minimise wait time.
    const [, project] = await Promise.all([
      projectStore.loadAllLookupData?.().catch(() => null),
      projectStore.fetchProject(projectId.value!).catch((err) => {
        console.error('[CreateEditProject] fetchProject failed', err)
        return null
      }),
    ])

    if (!project) {
      toast.error('Failed to load project')
      router.push('/projects')
      return
    }

    // Hide loader so the form mounts, then push the data into it.
    loading.value = false
    await nextTick()
    await nextTick()
    formRef.value?.loadFromProject(project)
  } else {
    // Create mode: no loader; just make sure dropdown taxonomies arrive.
    // Form is already rendered with empty defaults.
    projectStore.loadAllLookupData?.().catch(() => {})
  }
})
</script>

<template>
  <div class="min-h-screen py-8 px-4">
    <div class="max-w-6xl mx-auto">

      <!-- ── Header ───────────────────────────────────────────────────── -->
      <div class="mb-6 flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            {{ pageTitle }}
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ pageSubtitle }}
          </p>
        </div>
        <button
          class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
          @click="cancel"
        >
          Cancel
        </button>
      </div>

      <!-- ── Loading ──────────────────────────────────────────────────── -->
      <div
        v-if="loading"
        class="bg-white dark:bg-gray-700/10 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-16 flex flex-col items-center gap-4"
      >
        <Loader2 class="h-8 w-8 animate-spin text-blue-600" />
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading project…</p>
      </div>

      <!-- ── Form Card ────────────────────────────────────────────────── -->
      <div
        v-else
        class="bg-white dark:bg-gray-900/30 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
      >
        <StepIndicator
          :steps="STEPS"
          :current-step="currentStep"
          @go-to-step="goToStep"
        />

        <ProjectFormWizard
          ref="formRef"
          :current-step="currentStep"
          :is-edit-mode="isEditMode"
        />

        <!-- ── Footer ───────────────────────────────────────────────── -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30">
          <button
            v-if="currentStep > 0"
            class="flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-sm font-medium"
            @click="goBack"
          >
            <ChevronLeft class="h-4 w-4" /> Back
          </button>
          <div v-else />

          <div class="flex items-center gap-3">
            <button
              class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
              @click="cancel"
            >
              Cancel
            </button>

            <button
              v-if="currentStep < totalSteps - 1"
              class="flex items-center gap-2 px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors"
              @click="goNext"
            >
              Next <ChevronRight class="h-4 w-4" />
            </button>

            <button
              v-else
              :disabled="saving"
              class="flex items-center gap-2 px-5 py-2 rounded-lg bg-green-600 hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-medium transition-colors min-w-[160px] justify-center"
              @click="submitForm"
            >
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
              <Save v-else class="h-4 w-4" />
              {{ saving ? 'Saving…' : isEditMode ? 'Save Changes' : 'Create Project' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
