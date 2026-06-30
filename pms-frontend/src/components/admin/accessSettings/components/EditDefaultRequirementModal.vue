<!-- src/components/admin/accessSettings/components/EditDefaultRequirementModal.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { X, Upload, FileText, CheckCircle } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import axiosInstance from '@/utils/axiosInstance';

interface RequirementData {
  id?: number;
  track: string;
  group_name: string;
  item_name: string;
  source_document: string | null;
  owner_type: 'proponent' | 'internal';
  visibility: 'proponent_visible' | 'internal_only';
  soi_section: string;
  gate_step: string | null;
  is_required: boolean;
  svf_only: boolean;
  sort_order: number;
  template_file_path?: string | null;
}

interface Props {
  requirement: RequirementData | null;
  track: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  close: [];
  saved: [req: RequirementData];
}>();

const form = ref<RequirementData>({
  id: props.requirement?.id,
  track: props.requirement?.track ?? props.track,
  group_name: props.requirement?.group_name ?? '1. Intake Pack',
  item_name: props.requirement?.item_name ?? '',
  source_document: props.requirement?.source_document ?? 'BDG/SPG Checklist',
  owner_type: props.requirement?.owner_type ?? 'proponent',
  visibility: props.requirement?.visibility ?? 'proponent_visible',
  soi_section: props.requirement?.soi_section ?? 'intake',
  gate_step: props.requirement?.gate_step ?? null,
  is_required: props.requirement?.is_required ?? true,
  svf_only: props.requirement?.svf_only ?? false,
  sort_order: props.requirement?.sort_order ?? 10,
  template_file_path: props.requirement?.template_file_path,
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

const ownerOptions = [
  { value: 'proponent', label: 'External Proponent' },
  { value: 'internal', label: 'Internal NDC Team' },
];

const visibilityOptions = [
  { value: 'proponent_visible', label: 'Visible to Proponents & Internal' },
  { value: 'internal_only', label: 'Internal NDC Staff Only' },
];

const gateStepOptions = [
  { value: null, label: 'No Approval Block' },
  { value: 'mancom', label: 'Blocks Management Review (ManCom)' },
  { value: 'board', label: 'Blocks Board Approval' },
  { value: 'fund_release', label: 'Blocks Agreement & Fund Release' },
  { value: 'monitoring', label: 'Blocks Monitoring Activation' },
];

// Handle owner change to sync visibility
const handleOwnerChange = () => {
  if (form.value.owner_type === 'internal') {
    form.value.visibility = 'internal_only';
  } else {
    form.value.visibility = 'proponent_visible';
  }
};

const fileInputRef = ref<HTMLInputElement | null>(null);
const uploadingFile = ref(false);

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (!target.files?.length || !form.value.id) return;

  const file = target.files[0];
  const formData = new FormData();
  formData.append('file', file);
  
  uploadingFile.value = true;
  try {
    const res = await axiosInstance.post(`/api/access-settings/default-requirements/${form.value.id}/upload-template`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    
    form.value.template_file_path = res.data.data.template_file_path;
    toast.success('Template file uploaded successfully');
  } catch (error: any) {
    console.error('File upload error:', error);
    toast.error(error.response?.data?.message || 'Failed to upload template file');
  } finally {
    uploadingFile.value = false;
    if (fileInputRef.value) fileInputRef.value.value = '';
  }
};

const triggerFileInput = () => {
  fileInputRef.value?.click();
};

const saving = ref(false);
const isValid = computed(() => {
  return form.value.group_name.trim() !== '' && form.value.item_name.trim() !== '';
});

const handleSubmit = async () => {
  if (!isValid.value) return;
  saving.value = true;
  try {
    let res;
    if (form.value.id) {
      res = await axiosInstance.put(`/api/access-settings/default-requirements/${form.value.id}`, form.value);
    } else {
      res = await axiosInstance.post('/api/access-settings/default-requirements', form.value);
    }
    
    emit('saved', res.data.data);
    toast.success(form.value.id ? 'Template checklist item updated' : 'Template checklist item created');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to save checklist template');
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
          {{ requirement?.id ? 'Edit Checklist Document' : 'Add Required Document' }}
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
        <!-- Group Name -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Requirement Group / Phase</label>
          <input
            v-model="form.group_name"
            type="text"
            required
            placeholder="e.g. 1. Intake Pack or 4. Feasibility Study"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
          />
        </div>

        <!-- Item Name -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Document Name / Description</label>
          <input
            v-model="form.item_name"
            type="text"
            required
            placeholder="e.g. Letter of Intent (LOI) signed by representative"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
          />
        </div>

        <!-- Source Reference / Document -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Source Document / Reference</label>
          <input
            v-model="form.source_document"
            type="text"
            placeholder="e.g. SPG SOI Annex A or BDG Checklist"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
          />
        </div>

        <!-- Owner & Stage Section -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Responsible Owner</label>
            <select
              v-model="form.owner_type"
              @change="handleOwnerChange"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="opt in ownerOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
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
          </div>
        </div>

        <!-- Visibility & Gate Steps -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Visibility</label>
            <select
              v-model="form.visibility"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="opt in visibilityOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Workflow Gate Step</label>
            <select
              v-model="form.gate_step"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
              <option v-for="opt in gateStepOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Sort Order & SVF / Required flags -->
        <div class="grid grid-cols-3 gap-4 items-center">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Sort Order</label>
            <input
              v-model.number="form.sort_order"
              type="number"
              required
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            />
          </div>
          <div class="pt-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="form.is_required"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-350 text-blue-600 focus:ring-blue-500"
              />
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Mandatory</span>
            </label>
          </div>
          <div class="pt-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="form.svf_only"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-350 text-blue-600 focus:ring-blue-500"
              />
              <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">SVF Only</span>
            </label>
          </div>
        </div>

        <!-- Template File Upload Area (only active when the requirement is saved) -->
        <div class="border-t border-slate-100 pt-4 dark:border-slate-850">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Downloadable Document Template</label>
          
          <div v-if="!form.id" class="rounded-xl bg-slate-50 p-4 border border-dashed border-slate-200 text-center text-xs text-slate-500 dark:bg-slate-900 dark:border-slate-800">
            Create and save this requirement first before attaching a template file.
          </div>
          
          <div v-else class="flex flex-col gap-2">
            <div v-if="form.template_file_path" class="flex items-center justify-between rounded-xl bg-blue-50/50 p-3 border border-blue-100 dark:bg-blue-950/20 dark:border-blue-900">
              <div class="flex items-center gap-2.5 min-w-0">
                <FileText class="text-blue-600 shrink-0" :size="18" />
                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate">
                  {{ form.template_file_path.split('/').pop() }}
                </span>
              </div>
              <span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full dark:bg-green-950/40 dark:text-green-400 flex items-center gap-1">
                <CheckCircle :size="10" /> Ready
              </span>
            </div>
            
            <input
              type="file"
              ref="fileInputRef"
              class="hidden"
              @change="handleFileUpload"
              accept=".docx,.xlsx,.pdf,.doc,.xls,.zip"
            />
            
            <button
              type="button"
              @click="triggerFileInput"
              :disabled="uploadingFile"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-700 transition dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900"
            >
              <Upload :size="14" :class="{ 'animate-pulse': uploadingFile }" />
              {{ form.template_file_path ? 'Replace Template File' : 'Upload Template File (Word / Excel / PDF)' }}
            </button>
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
            {{ saving ? 'Saving...' : 'Save Requirement' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
