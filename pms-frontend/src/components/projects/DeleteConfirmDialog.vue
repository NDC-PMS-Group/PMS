<!-- src/components/projects/DeleteConfirmDialog.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="overlay" @mousedown.self="emit('update:modelValue', false)">
        <div class="dialog">
          <div class="d-icon"><AlertTriangleIcon class="icon" /></div>
          <h3 class="d-title">Delete Project</h3>
          <p class="d-desc">
            Are you sure you want to delete <strong>{{ project?.title }}</strong>?
            This action <strong>cannot be undone</strong>.
          </p>
          <div class="d-actions">
            <button class="btn-cancel" @click="emit('update:modelValue', false)">Cancel</button>
            <button class="btn-delete" @click="emit('confirmed')">
              <Trash2Icon class="bi" /> Delete Project
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import type { Project } from '@/types/project';
import { AlertTriangle as AlertTriangleIcon, Trash2 as Trash2Icon } from 'lucide-vue-next';
defineProps<{ modelValue: boolean; project: Project | null }>();
const emit = defineEmits<{ 'update:modelValue': [v: boolean]; confirmed: [] }>();
</script>

<style scoped>
.overlay {
  --dd-bg: #ffffff;
  --dd-text: #0f172a;
  --dd-text-2: #64748b;
  --dd-border: #e2e8f0;
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(15,23,42,0.65);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
:global(.dark) .overlay {
  --dd-bg: #1e293b;
  --dd-text: #f1f5f9;
  --dd-text-2: #94a3b8;
  --dd-border: #334155;
  background: rgba(0,0,0,0.75);
}
.dialog {
  background: var(--dd-bg);
  border-radius: 1rem; padding: 2rem; width: 100%; max-width: 420px;
  text-align: center;
  box-shadow: 0 24px 48px rgba(0,0,0,0.18);
}
:global(.dark) .dialog { box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
.d-icon { width: 3.75rem; height: 3.75rem; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.125rem; }
:global(.dark) .d-icon { background: #450a0a; }
.icon { width: 1.625rem; height: 1.625rem; color: #dc2626; }
:global(.dark) .icon { color: #f87171; }
.d-title { font-size: 1.125rem; font-weight: 700; color: var(--dd-text); margin: 0 0 0.625rem; }
.d-desc { font-size: 0.875rem; color: var(--dd-text-2); line-height: 1.65; margin: 0 0 1.375rem; }
.d-desc strong { color: var(--dd-text); }
.d-actions { display: flex; gap: 0.75rem; }
.btn-cancel { flex: 1; padding: 0.6875rem; background: transparent; border: 1.5px solid var(--dd-border); border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--dd-text-2); cursor: pointer; transition: all 0.15s; }
.btn-cancel:hover { border-color: #94a3b8; }
.btn-delete { flex: 1; padding: 0.6875rem; background: #dc2626; border: none; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.15s; }
.btn-delete:hover { background: #b91c1c; }
.bi { width: 0.825rem; height: 0.825rem; }

.modal-enter-active { animation: fadeIn 0.2s ease; }
.modal-leave-active { animation: fadeIn 0.15s ease reverse; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.modal-enter-active .dialog { animation: dialogIn 0.25s cubic-bezier(0.34,1.56,0.64,1); }
.modal-leave-active .dialog { animation: dialogIn 0.15s ease reverse; }
@keyframes dialogIn { from{transform:scale(0.88) translateY(10px)} to{transform:scale(1) translateY(0)} }
</style>