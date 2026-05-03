<!-- src/components/projects/ApprovalActionModal.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" :class="{ 'is-dark': isDarkMode }" @mousedown.self="handleClose">
        <div class="modal-panel">
          <div class="modal-header">
            <h2 class="modal-title">Approval Action</h2>
            <button class="close-btn" @click="handleClose"><XIcon class="h-icon" /></button>
          </div>
          
          <div class="modal-body">
            <div class="info-banner" v-if="currentStep">
              You are completing the step: <strong>{{ currentStep.step_name }}</strong>
            </div>
            
            <div class="form-group required">
              <label class="form-label">Action</label>
              <div class="action-grid">
                <button type="button" class="action-card btn-approve" :class="{ selected: form.status === 'approved' }" @click="form.status = 'approved'">
                  <CheckCircleIcon class="ac-icon" />
                  <span>Approve</span>
                </button>
                <button type="button" class="action-card btn-conditions" :class="{ selected: form.status === 'approved_with_conditions' }" @click="form.status = 'approved_with_conditions'">
                  <AlertCircleIcon class="ac-icon" />
                  <span>Approve with Conditions</span>
                </button>
                <button type="button" class="action-card btn-return" :class="{ selected: form.status === 'returned' }" @click="form.status = 'returned'">
                  <CornerUpLeftIcon class="ac-icon" />
                  <span>Return / Reject</span>
                </button>
              </div>
            </div>

            <div v-if="form.status === 'approved_with_conditions'" class="form-group slide-down">
              <label class="form-label required">Conditions</label>
              <textarea v-model="form.conditions" class="form-textarea" rows="3" placeholder="Specify the conditions for approval..."></textarea>
            </div>

            <div class="form-group">
              <label class="form-label" :class="{ required: form.status === 'returned' }">Comments / Remarks</label>
              <textarea v-model="form.comments" class="form-textarea" rows="3" placeholder="Add any final remarks or justification..."></textarea>
            </div>

            <div v-if="errorMsg" class="error-banner">{{ errorMsg }}</div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="handleClose" :disabled="loading">Cancel</button>
            <button class="btn-submit" :class="submitBtnClass" @click="handleSubmit" :disabled="loading || !isValid">
              <span v-if="loading" class="spinner-sm"></span>
              {{ loading ? 'Submitting...' : submitBtnText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import { X as XIcon, CheckCircle as CheckCircleIcon, AlertCircle as AlertCircleIcon, CornerUpLeft as CornerUpLeftIcon } from 'lucide-vue-next';
import type { ApprovalStep } from '@/types/project';

interface Props {
  modelValue: boolean;
  approvalId: number | null;
  currentStep?: ApprovalStep;
}
const props = defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [v: boolean];
  submit: [data: { status: string; comments?: string; conditions?: string }];
  close: [];
}>();

const layoutStore = useLayoutStore();
const isDarkMode = computed(() => {
  const htmlDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  return layoutStore.mode === SITE_MODE.DARK || htmlDark;
});

const form = ref({
  status: '',
  comments: '',
  conditions: '',
});

const loading = ref(false);
const errorMsg = ref('');

const isValid = computed(() => {
  if (!form.value.status) return false;
  if (form.value.status === 'approved_with_conditions' && !form.value.conditions.trim()) return false;
  if (form.value.status === 'returned' && !form.value.comments.trim()) return false;
  return true;
});

const submitBtnText = computed(() => {
  if (form.value.status === 'approved' || form.value.status === 'approved_with_conditions') return 'Submit Approval';
  if (form.value.status === 'returned') return 'Return Project';
  return 'Submit';
});

const submitBtnClass = computed(() => {
  if (form.value.status === 'returned') return 'btn-danger';
  if (form.value.status === 'approved_with_conditions') return 'btn-warning';
  return 'btn-success';
});

const handleClose = () => {
  emit('update:modelValue', false);
  emit('close');
  setTimeout(() => {
    form.value = { status: '', comments: '', conditions: '' };
    errorMsg.value = '';
  }, 200);
};

const handleSubmit = async () => {
  if (!isValid.value) return;
  // Let parent handle the actual dispatch, this modal just collects data
  emit('submit', { 
    status: form.value.status, 
    comments: form.value.comments, 
    conditions: form.value.conditions 
  });
};
</script>

<style scoped>
.modal-overlay {
  --ma-bg: #ffffff;
  --ma-border: #e2e8f0;
  --ma-overlay: rgba(15,23,42,0.65);
  --ma-text: #0f172a;
  --ma-text-2: #475569;
  --ma-text-3: #94a3b8;
  --ma-input: #f8fafc;
  --ma-info: #eff6ff;
  --ma-info-text: #1e40af;
  position: fixed; inset: 0; z-index: 10005;
  background: var(--ma-overlay); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal-overlay.is-dark {
  --ma-bg: #1e293b;
  --ma-border: #334155;
  --ma-overlay: rgba(0,0,0,0.75);
  --ma-text: #f1f5f9;
  --ma-text-2: #94a3b8;
  --ma-text-3: #64748b;
  --ma-input: #0f172a;
  --ma-info: #172554;
  --ma-info-text: #60a5fa;
}

.modal-panel { background: var(--ma-bg); border: 1px solid var(--ma-border); border-radius: 1rem; width: 100%; max-width: 500px; box-shadow: 0 24px 64px rgba(0,0,0,0.3); display: flex; flex-direction: column; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem 1rem; border-bottom: 1px solid var(--ma-border); }
.modal-title { font-size: 1.125rem; font-weight: 700; color: var(--ma-text); margin: 0; }
.close-btn { background: transparent; border: none; color: var(--ma-text-3); cursor: pointer; padding: 0.25rem; border-radius: 0.375rem; transition: all 0.15s; }
.close-btn:hover { background: rgba(239,68,68,0.1); color: #ef4444; }
.h-icon { width: 1.25rem; height: 1.25rem; }

.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.info-banner { background: var(--ma-info); color: var(--ma-info-text); padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; border: 1px solid rgba(59,130,246,0.3); }

.form-group { display: flex; flex-direction: column; gap: 0.5rem; }
.form-label { font-size: 0.8rem; font-weight: 600; color: var(--ma-text-2); }
.form-label.required::after { content: ' *'; color: #ef4444; }

.action-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; }
.action-card { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; background: var(--ma-input); border: 2px solid var(--ma-border); border-radius: 0.75rem; cursor: pointer; text-align: left; font-size: 0.9rem; font-weight: 600; color: var(--ma-text); transition: all 0.15s; }
.action-card:hover { border-color: #94a3b8; }
.ac-icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
.btn-approve.selected { border-color: #22c55e; background: #f0fdf4; color: #166534; }
.btn-approve.selected .ac-icon { color: #22c55e; }
.modal-overlay.is-dark .btn-approve.selected { background: #14532d; color: #86efac; }
.btn-conditions.selected { border-color: #f59e0b; background: #fffbeb; color: #b45309; }
.btn-conditions.selected .ac-icon { color: #f59e0b; }
.modal-overlay.is-dark .btn-conditions.selected { background: #78350f; color: #fcd34d; }
.btn-return.selected { border-color: #ef4444; background: #fef2f2; color: #b91c1c; }
.btn-return.selected .ac-icon { color: #ef4444; }
.modal-overlay.is-dark .btn-return.selected { background: #450a0a; color: #fca5a5; }

.form-textarea { width: 100%; border: 1.5px solid var(--ma-border); border-radius: 0.5rem; padding: 0.75rem; font-size: 0.875rem; background: var(--ma-input); color: var(--ma-text); font-family: inherit; resize: vertical; box-sizing: border-box; }
.form-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

.error-banner { padding: 0.75rem; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 0.5rem; font-size: 0.8rem; }
.modal-overlay.is-dark .error-banner { background: #450a0a; border-color: #7f1d1d; color: #fca5a5; }

.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--ma-border); background: rgba(0,0,0,0.02); }
.modal-overlay.is-dark .modal-footer { background: rgba(0,0,0,0.15); }
.btn-cancel, .btn-submit { padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; }
.btn-cancel { background: transparent; border: 1px solid var(--ma-border); color: var(--ma-text-2); }
.btn-cancel:hover { background: rgba(0,0,0,0.05); }
.modal-overlay.is-dark .btn-cancel:hover { background: rgba(255,255,255,0.05); }
.btn-submit { border: none; color: white; display: flex; align-items: center; gap: 0.5rem; }
.btn-success { background: #22c55e; }
.btn-success:hover:not(:disabled) { background: #16a34a; }
.btn-warning { background: #f59e0b; }
.btn-warning:hover:not(:disabled) { background: #d97706; }
.btn-danger { background: #ef4444; }
.btn-danger:hover:not(:disabled) { background: #dc2626; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner-sm { width: 1rem; height: 1rem; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.slide-down { animation: slideDown 0.2s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

.modal-enter-active { animation: fadeIn 0.15s ease-out; }
.modal-leave-active { animation: fadeIn 0.15s ease-in reverse; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-enter-active .modal-panel { animation: slideUp 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.modal-leave-active .modal-panel { animation: slideUp 0.15s ease-in reverse; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
</style>
