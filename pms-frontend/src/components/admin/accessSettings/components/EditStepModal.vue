<!-- src/components/admin/accessSettings/components/EditStepModal.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { X, ShieldAlert } from 'lucide-vue-next';
import type { Role } from '@/types/accessSettings';

interface StepData {
  id?: number;
  step_order: number;
  role_id: number;
  step_name: string;
  soi_section: string | null;
  sla_days: number | null;
  is_required: boolean;
  can_skip: boolean;
}

interface Props {
  step: StepData | null;
  roles: Role[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  close: [];
  save: [step: StepData];
}>();

const form = ref<StepData>({
  id: props.step?.id,
  step_order: props.step?.step_order ?? 1,
  role_id: props.step?.role_id ?? props.roles[0]?.id ?? 0,
  step_name: props.step?.step_name ?? '',
  soi_section: props.step?.soi_section ?? 'intake',
  sla_days: props.step?.sla_days ?? null,
  is_required: props.step?.is_required ?? true,
  can_skip: props.step?.can_skip ?? false,
});

const sectionOptions = [
  { value: 'intake', label: 'Intake' },
  { value: 'requirements', label: 'Requirements Check' },
  { value: 'due_diligence', label: 'Due Diligence / Evaluation' },
  { value: 'management_review', label: 'Management Review / ManCom' },
  { value: 'board_approval', label: 'Board Approval' },
  { value: 'agreement_fund_release', label: 'Agreement & Fund Release' },
  { value: 'implementation_monitoring', label: 'Implementation & Monitoring' },
  { value: 'post_investment_strategy', label: 'Post-Investment Strategy' },
  { value: 'divestment', label: 'Divestment' },
  { value: 'completion', label: 'Completion' },
];

const isValid = computed(() => {
  return form.value.step_name.trim() !== '' && form.value.role_id > 0;
});

const handleSubmit = () => {
  if (isValid.value) {
    emit('save', { ...form.value });
  }
};
</script>

<template>
  <div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-950">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-850">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ step?.id ? 'Edit Workflow Step' : 'Add Workflow Step' }}
        </h3>
        <button
          @click="emit('close')"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50 hover:text-slate-700 dark:hover:bg-slate-900 dark:hover:text-slate-200"
        >
          <X :size="20" />
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <!-- Step Name -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Step Name</label>
          <input
            v-model="form.step_name"
            type="text"
            required
            placeholder="e.g. Pre-screening / Completeness Check"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:focus:border-blue-500 dark:focus:ring-blue-950"
          />
        </div>

        <div>
          <label for="workflow-step-sla" class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">SLA (calendar days)</label>
          <input
            id="workflow-step-sla"
            v-model.number="form.sla_days"
            name="sla_days"
            type="number"
            min="1"
            max="365"
            placeholder="No SLA"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:focus:border-blue-500 dark:focus:ring-blue-950"
          />
        </div>

        <!-- Role & Step Order -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Assigned Role</label>
            <select
              v-model="form.role_id"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="role in roles" :key="role.id" :value="role.id">
                {{ role.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Step Order</label>
            <input
              v-model.number="form.step_order"
              type="number"
              min="1"
              required
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            />
          </div>
        </div>

        <!-- SOI Phase Section -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">SOI Phase / Section</label>
          <select
            v-model="form.soi_section"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
          >
            <option v-for="opt in sectionOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
          <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-550">
            This groups the step with its corresponding checklist documents and tasks.
          </p>
        </div>

        <!-- Required & Can Skip -->
        <div class="flex items-center gap-6 pt-2">
          <label class="flex items-center gap-2.5 cursor-pointer">
            <input
              v-model="form.is_required"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-350 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Required Step</span>
          </label>
          <label class="flex items-center gap-2.5 cursor-pointer">
            <input
              v-model="form.can_skip"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-350 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Can Skip Step</span>
          </label>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5 dark:border-slate-850">
          <button
            type="button"
            @click="emit('close')"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!isValid"
            class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700 focus:ring-4 focus:ring-blue-100 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-600 dark:hover:bg-blue-500"
          >
            Save Step
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
