<!-- src/components/admin/accessSettings/components/EditDefaultTaskModal.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { X } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axiosInstance from '@/utils/axiosInstance';

interface TaskData {
  id?: number;
  track: string;
  title: string;
  description: string | null;
  task_type: string | null;
  soi_section: string;
  assigned_role: string;
  days: number;
  priority: string;
  is_milestone: boolean;
  parent_task_title: string | null;
  sort_order: number;
}

interface Props {
  task: TaskData | null;
  track: string;
  soiSection: string;
  parentTasks: TaskData[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
  close: [];
  saved: [task: TaskData];
}>();

const form = ref<TaskData>({
  id: props.task?.id,
  track: props.task?.track ?? props.track,
  title: props.task?.title ?? '',
  description: props.task?.description ?? '',
  task_type: props.task?.task_type ?? props.soiSection,
  soi_section: props.task?.soi_section ?? props.soiSection,
  assigned_role: props.task?.assigned_role ?? 'Project Officer',
  days: props.task?.days ?? 10,
  priority: props.task?.priority ?? 'medium',
  is_milestone: props.task?.is_milestone ?? false,
  parent_task_title: props.task?.parent_task_title ?? null,
  sort_order: props.task?.sort_order ?? 10,
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

const roleOptions = [
  { value: 'Project Officer', label: 'Project Officer' },
  { value: 'Proponent', label: 'Proponent' },
  { value: 'Workgroup Head', label: 'Workgroup Head' },
];

const priorityOptions = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'critical', label: 'Critical' },
];

const typeOptions = [
  { value: 'intake', label: 'Intake' },
  { value: 'requirements', label: 'Requirements Check' },
  { value: 'compliance', label: 'Compliance' },
  { value: 'due_diligence', label: 'Due Diligence' },
  { value: 'approval', label: 'Approval' },
  { value: 'fund_release', label: 'Fund Release' },
  { value: 'monitoring', label: 'Monitoring' },
  { value: 'post_investment', label: 'Post Investment' },
  { value: 'divestment', label: 'Divestment' },
];

const saving = ref(false);
const isValid = computed(() => {
  return form.value.title.trim() !== '';
});

// Filter parent tasks to avoid referencing self
const filteredParentTasks = computed(() => {
  if (!form.value.id) return props.parentTasks;
  return props.parentTasks.filter(t => t.id !== form.value.id && t.title !== form.value.title);
});

const handleSubmit = async () => {
  if (!isValid.value) return;
  saving.value = true;
  try {
    let res;
    if (form.value.id) {
      res = await axiosInstance.put(`/api/access-settings/default-tasks/${form.value.id}`, form.value);
    } else {
      res = await axiosInstance.post('/api/access-settings/default-tasks', form.value);
    }
    
    emit('saved', res.data.task);
    toast.success(form.value.id ? 'Work plan default task updated' : 'Work plan default task created');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save default task template');
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-950">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-850">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ task?.id ? 'Edit Default Task' : 'Add Default Task' }}
        </h3>
        <button
          @click="emit('close')"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50 hover:text-slate-700 dark:hover:bg-slate-900 dark:hover:text-slate-200"
        >
          <X :size="20" />
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4 max-h-[78vh] overflow-y-auto">
        <!-- Title -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Task Title</label>
          <input
            v-model="form.title"
            type="text"
            required
            placeholder="e.g. Conduct pre-screening / KYC meeting"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
          />
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Description / Notes</label>
          <textarea
            v-model="form.description"
            rows="3"
            placeholder="Provide context, details, or checklist instructions for this task..."
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
          ></textarea>
        </div>

        <!-- Parent Task Link -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Parent Task (Create Subtask)</label>
          <select
            v-model="form.parent_task_title"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
          >
            <option :value="null">None (Is a Parent Task)</option>
            <option v-for="t in filteredParentTasks" :key="t.id" :value="t.title">
              {{ t.title }}
            </option>
          </select>
        </div>

        <!-- Role & Section -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Assigned Role</label>
            <select
              v-model="form.assigned_role"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">SOI Phase / Section</label>
            <select
              v-model="form.soi_section"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="opt in sectionOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Task Type & Priority -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Task Type</label>
            <select
              v-model="form.task_type"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
            >
              <option :value="null">Default</option>
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Priority</label>
            <select
              v-model="form.priority"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="opt in priorityOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Sort Order & Timeline Days & Milestone -->
        <div class="grid grid-cols-3 gap-4 items-center">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Timeline Days</label>
            <input
              v-model.number="form.days"
              type="number"
              min="0"
              required
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
            />
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Sort Order</label>
            <input
              v-model.number="form.sort_order"
              type="number"
              required
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-880 dark:bg-slate-900 dark:text-white"
            />
          </div>
          <div class="pt-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="form.is_milestone"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-350 text-blue-600 focus:ring-blue-500"
              />
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Milestone</span>
            </label>
          </div>
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
            :disabled="!isValid || saving"
            class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700 focus:ring-4 focus:ring-blue-100 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-600 dark:hover:bg-blue-500"
          >
            {{ saving ? 'Saving...' : 'Save Task' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
