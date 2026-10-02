<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue3-toastify'
import { RefreshCw, Plus, Save, Pencil, Trash2, X, Settings } from 'lucide-vue-next'
import { usePermission } from '@/composables/usePermission'
import { useProjectStore } from '@/store/projects'
import axiosInstance from '@/utils/axiosInstance'

const props = defineProps<{
  permissionKey: string
}>()

const { canCreate, canUpdate, canDelete } = usePermission()
const projectStore = useProjectStore()

interface ManagedInvestmentCriterion {
  id: number
  key: string
  name: string
  description: string | null
  sort_order: number
  is_active: boolean
}

interface InvestmentCriterionForm {
  key: string
  name: string
  description: string
  sort_order: number | null
  is_active: boolean
}

const emptyCriterionForm = (): InvestmentCriterionForm => ({
  key: '',
  name: '',
  description: '',
  sort_order: null,
  is_active: true,
})

const criteria = ref<ManagedInvestmentCriterion[]>([])
const loadingCriteria = ref(false)
const savingCriterion = ref(false)
const deletingCriterionId = ref<number | null>(null)
const editingCriterionId = ref<number | null>(null)
const criterionForm = ref<InvestmentCriterionForm>(emptyCriterionForm())

const activeCriteriaCount = computed(() => criteria.value.filter((criterion) => criterion.is_active).length)

const fetchInvestmentCriteria = async () => {
  loadingCriteria.value = true
  try {
    const response = await axiosInstance.get('/api/settings/investment-criteria')
    criteria.value = Array.isArray(response.data?.data) ? response.data.data : []
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to load investment criteria')
  } finally {
    loadingCriteria.value = false
  }
}

const resetCriterionForm = () => {
  editingCriterionId.value = null
  criterionForm.value = emptyCriterionForm()
}

const editCriterion = (criterion: ManagedInvestmentCriterion) => {
  editingCriterionId.value = criterion.id
  criterionForm.value = {
    key: criterion.key,
    name: criterion.name,
    description: criterion.description || '',
    sort_order: criterion.sort_order,
    is_active: criterion.is_active,
  }
}

const saveCriterion = async () => {
  if (editingCriterionId.value && !canUpdate(props.permissionKey)) {
    toast.error('You do not have permission to update investment criteria')
    return
  }

  if (!editingCriterionId.value && !canCreate(props.permissionKey)) {
    toast.error('You do not have permission to create investment criteria')
    return
  }

  const name = criterionForm.value.name.trim()
  if (!name) {
    toast.error('Criterion name is required')
    return
  }

  savingCriterion.value = true
  try {
    const payload = {
      key: criterionForm.value.key.trim() || undefined,
      name,
      description: criterionForm.value.description.trim() || null,
      sort_order: criterionForm.value.sort_order ?? undefined,
      is_active: criterionForm.value.is_active,
    }

    if (editingCriterionId.value) {
      await axiosInstance.put(`/api/settings/investment-criteria/${editingCriterionId.value}`, {
        name: payload.name,
        description: payload.description,
        sort_order: payload.sort_order ?? 0,
        is_active: payload.is_active,
      })
      toast.success('Investment criterion updated')
    } else {
      await axiosInstance.post('/api/settings/investment-criteria', payload)
      toast.success('Investment criterion added')
    }

    resetCriterionForm()
    await Promise.all([
      fetchInvestmentCriteria(),
      projectStore.fetchInvestmentCriteria(),
    ])
  } catch (error: any) {
    const errors = error.response?.data?.errors
    const firstError = errors ? Object.values(errors).flat()[0] : null
    toast.error(firstError || error.response?.data?.message || 'Failed to save investment criterion')
  } finally {
    savingCriterion.value = false
  }
}

const deleteCriterion = async (criterion: ManagedInvestmentCriterion) => {
  if (!canDelete(props.permissionKey)) {
    toast.error('You do not have permission to delete investment criteria')
    return
  }

  if (!window.confirm(`Delete "${criterion.name}" from the criteria catalog? Existing projects keep their saved key, but new forms will no longer show it.`)) {
    return
  }

  deletingCriterionId.value = criterion.id
  try {
    await axiosInstance.delete(`/api/settings/investment-criteria/${criterion.id}`)
    toast.success('Investment criterion deleted')
    if (editingCriterionId.value === criterion.id) {
      resetCriterionForm()
    }
    await Promise.all([
      fetchInvestmentCriteria(),
      projectStore.fetchInvestmentCriteria(),
    ])
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to delete investment criterion')
  } finally {
    deletingCriterionId.value = null
  }
}

onMounted(fetchInvestmentCriteria)
</script>

<template>
  <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-5 dark:border-slate-800/80 dark:bg-slate-900/50">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
          <div class="flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 p-2.5 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20">
            <Settings :size="20" />
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Investment Criteria</h2>
            <p class="mt-0.5 text-xs leading-normal text-slate-500 dark:text-slate-400">
              Manage the NDC criteria options shown in the project intake form.
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            {{ activeCriteriaCount }}/{{ criteria.length }} active
          </span>
          <button
            type="button"
            class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            :disabled="loadingCriteria"
            @click="fetchInvestmentCriteria"
          >
            <RefreshCw :size="14" :class="{ 'animate-spin': loadingCriteria }" />
            Refresh
          </button>
        </div>
      </div>
    </div>

    <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_24rem]">
      <div class="border-b border-slate-200 p-6 dark:border-slate-800 xl:border-b-0 xl:border-r">
        <div v-if="loadingCriteria && criteria.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
          <div class="mb-3 h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-b-blue-600 dark:border-slate-700 dark:border-b-blue-400"></div>
          <p class="text-sm text-slate-500 dark:text-slate-400">Loading investment criteria...</p>
        </div>

        <div v-else-if="criteria.length === 0" class="py-12 text-center">
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No criteria configured yet.</p>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Add the first criterion using the form beside this list.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[46rem] text-left">
            <thead>
              <tr class="border-b border-slate-200 text-[11px] uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                <th class="pb-3 font-bold">Criterion</th>
                <th class="pb-3 font-bold">Key</th>
                <th class="pb-3 text-right font-bold">Order</th>
                <th class="pb-3 text-center font-bold">Status</th>
                <th class="pb-3 text-right font-bold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
              <tr v-for="criterion in criteria" :key="criterion.id" class="align-top">
                <td class="py-4 pr-4">
                  <p class="text-sm font-bold text-slate-900 dark:text-white">{{ criterion.name }}</p>
                  <p v-if="criterion.description" class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ criterion.description }}
                  </p>
                </td>
                <td class="py-4 pr-4">
                  <code class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    {{ criterion.key }}
                  </code>
                </td>
                <td class="py-4 pr-4 text-right text-sm font-semibold text-slate-700 dark:text-slate-300">
                  {{ criterion.sort_order }}
                </td>
                <td class="py-4 pr-4 text-center">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                    :class="criterion.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                  >
                    {{ criterion.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="py-4 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                      :disabled="!canUpdate(permissionKey)"
                      title="Edit criterion"
                      @click="editCriterion(criterion)"
                    >
                      <Pencil :size="15" />
                    </button>
                    <button
                      type="button"
                      class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border border-red-200 text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-950/20"
                      :disabled="!canDelete(permissionKey) || deletingCriterionId === criterion.id"
                      title="Delete criterion"
                      @click="deleteCriterion(criterion)"
                    >
                      <Trash2 :size="15" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <form class="bg-slate-50/50 p-6 dark:bg-slate-950/30" @submit.prevent="saveCriterion">
        <div class="mb-5 flex items-start justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
              {{ editingCriterionId ? 'Edit Criterion' : 'Add Criterion' }}
            </h3>
            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
              Active criteria appear in new project forms.
            </p>
          </div>
          <button
            v-if="editingCriterionId"
            type="button"
            class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition-colors hover:bg-white dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
            title="Cancel editing"
            @click="resetCriterionForm"
          >
            <X :size="15" />
          </button>
        </div>

        <div class="space-y-4">
          <div>
            <label for="criterion-name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Name
            </label>
            <input
              id="criterion-name"
              v-model="criterionForm.name"
              type="text"
              name="name"
              required
              maxlength="120"
              class="min-h-12 w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-900 placeholder-slate-400 transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
              placeholder="e.g. Climate resilient"
              :disabled="savingCriterion || (!editingCriterionId && !canCreate(permissionKey)) || (Boolean(editingCriterionId) && !canUpdate(permissionKey))"
            />
          </div>

          <div>
            <label for="criterion-key" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Key
            </label>
            <input
              id="criterion-key"
              v-model="criterionForm.key"
              type="text"
              name="key"
              pattern="[a-z0-9_]+"
              maxlength="80"
              class="min-h-12 w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2 font-mono text-sm text-slate-900 placeholder-slate-400 transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
              placeholder="Auto-generated if blank"
              :disabled="Boolean(editingCriterionId) || savingCriterion || !canCreate(permissionKey)"
              aria-describedby="criterion-key-help"
            />
            <p id="criterion-key-help" class="mt-1.5 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
              Keys use lowercase letters, numbers, and underscores. Existing keys are locked to protect saved project data.
            </p>
          </div>

          <div>
            <label for="criterion-description" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
              Description
            </label>
            <textarea
              id="criterion-description"
              v-model="criterionForm.description"
              name="description"
              rows="3"
              maxlength="1000"
              class="w-full resize-y rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm text-slate-900 placeholder-slate-400 transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
              placeholder="Optional internal explanation"
              :disabled="savingCriterion || (!editingCriterionId && !canCreate(permissionKey)) || (Boolean(editingCriterionId) && !canUpdate(permissionKey))"
            ></textarea>
          </div>

          <div class="grid items-end gap-4 sm:grid-cols-[1fr_auto]">
            <div>
              <label for="criterion-order" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Sort Order
              </label>
              <input
                id="criterion-order"
                v-model.number="criterionForm.sort_order"
                type="number"
                name="sort_order"
                min="0"
                max="9999"
                step="1"
                class="min-h-12 w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-right text-sm font-semibold text-slate-900 placeholder-slate-400 transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                placeholder="Next"
                :disabled="savingCriterion || (!editingCriterionId && !canCreate(permissionKey)) || (Boolean(editingCriterionId) && !canUpdate(permissionKey))"
              />
            </div>

            <label class="inline-flex min-h-12 cursor-pointer select-none items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 dark:border-slate-800 dark:bg-slate-950">
              <input
                v-model="criterionForm.is_active"
                type="checkbox"
                name="is_active"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                :disabled="savingCriterion || (!editingCriterionId && !canCreate(permissionKey)) || (Boolean(editingCriterionId) && !canUpdate(permissionKey))"
              />
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Active</span>
            </label>
          </div>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row">
          <button
            type="submit"
            class="flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="savingCriterion || (!editingCriterionId && !canCreate(permissionKey)) || (Boolean(editingCriterionId) && !canUpdate(permissionKey))"
          >
            <Plus v-if="!editingCriterionId" :size="16" />
            <Save v-else :size="16" />
            {{ savingCriterion ? 'Saving...' : editingCriterionId ? 'Save Criterion' : 'Add Criterion' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
            @click="resetCriterionForm"
          >
            Clear
          </button>
        </div>
      </form>
    </div>
  </section>
</template>
