<!-- src/components/projects/ProjectApprovalTimeline.vue -->
<template>
  <div class="approval-timeline">
    <div v-if="loading" class="spinner-container">
      <div class="spinner-sm"></div>
      <span>Loading approval workflow...</span>
    </div>

    <div v-else-if="currentApproval">
      <div class="approval-header">
        <div class="ah-left">
          <h3 class="ah-title">Approval Routing Status</h3>
          <span class="ah-badge" :class="statusClass(currentApproval.overall_status)">
            {{ formatStatus(currentApproval.overall_status) }}
          </span>
        </div>
        <button v-if="canApprove" class="btn-action" @click="emit('open-action')">
          <Edit3Icon class="btn-icon" /> Take Action
        </button>
      </div>

      <div class="workflow-steps">
        <div v-for="(step, index) in steps" :key="step.id" 
             class="wf-step" :class="getStepState(step.id)">
          
          <div class="wfs-indicator">
            <div class="wfs-line" v-if="index < steps.length - 1"></div>
            <div class="wfs-dot">
              <CheckIcon v-if="getStepState(step.id) === 'completed'" class="dot-icon" />
              <div v-else-if="getStepState(step.id) === 'current'" class="dot-pulse"></div>
              <span v-else class="dot-num">{{ index + 1 }}</span>
            </div>
          </div>

          <div class="wfs-content">
            <h4 class="wfc-title">{{ step.step_name }}</h4>
            <div v-if="step.role" class="wfc-role"><UsersIcon class="r-icon" />{{ step.role.name }}</div>
            
            <div v-if="getStepRecord(step.id)" class="wfc-record">
              <div class="rec-head">
                <span class="rec-user">{{ getStepRecord(step.id)?.approver?.name || getStepRecord(step.id)?.approver?.full_name || 'System' }}</span>
                <span class="rec-date">{{ fmtDateTime(getStepRecord(step.id)?.reviewed_at || getStepRecord(step.id)?.submitted_at) }}</span>
              </div>
              <div class="rec-status" :class="statusClass(getStepRecord(step.id)?.status || '')">
                {{ formatStatus(getStepRecord(step.id)?.status || '') }}
              </div>
              <p v-if="getStepRecord(step.id)?.conditions" class="rec-cond"><strong>Conditions:</strong> {{ getStepRecord(step.id)?.conditions }}</p>
              <p v-if="getStepRecord(step.id)?.comments" class="rec-comm">"{{ getStepRecord(step.id)?.comments }}"</p>
            </div>
            <div v-else-if="getStepState(step.id) === 'current'" class="wfc-pending">
              Waiting for review...
            </div>
          </div>

        </div>
      </div>
    </div>
    
    <div v-else class="empty-state">
      <div class="empty-icon"><ActivityIcon /></div>
      <p>No active approval workflow for this project.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/store/auth';
import type { ProjectApproval, ApprovalStepRecord } from '@/types/project';
import { Check as CheckIcon, Users as UsersIcon, Edit3 as Edit3Icon, Activity as ActivityIcon } from 'lucide-vue-next';

interface Props {
  currentApproval: ProjectApproval | null;
  approvalHistory: ApprovalStepRecord[];
  loading?: boolean;
  projectCreatorId?: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  'open-action': [];
}>();

const authStore = useAuthStore();
const currentUserRole = computed(() => authStore.userRole);
const currentUserId = computed(() => authStore.user?.id);

const steps = computed(() => {
  if (!props.currentApproval?.workflow?.steps) return [];
  return [...props.currentApproval.workflow.steps].sort((a, b) => a.step_order - b.step_order);
});

const canApprove = computed(() => {
  if (!props.currentApproval || !props.currentApproval.current_step) return false;
  
  // Proponent check
  if (props.currentApproval.current_step.step_order === 1) {
    return props.projectCreatorId === currentUserId.value;
  }
  
  // Role check
  return props.currentApproval.current_step.role?.name === currentUserRole.value;
});

const getStepRecord = (stepId: number) => {
  // Find the latest record for this step
  return props.approvalHistory.find(r => r.step_id === stepId);
};

const getStepState = (stepId: number) => {
  if (props.currentApproval?.current_step_id === stepId) return 'current';
  const record = getStepRecord(stepId);
  if (record && record.status !== 'returned') return 'completed';
  if (record && record.status === 'returned') return 'returned';
  return 'pending';
};

const formatStatus = (status: string) => {
  return status.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
};

const statusClass = (status: string) => {
  if (status.includes('approved')) return 's-approved';
  if (status.includes('returned') || status.includes('rejected')) return 's-returned';
  if (status.includes('evaluation') || status.includes('approval') || status.includes('pending')) return 's-pending';
  if (status === 'completed') return 's-completed';
  return 's-default';
};

const fmtDateTime = (d?: string | null) => {
  if (!d) return '';
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<style scoped>
.approval-timeline {
  --at-bg: var(--v-card, #fafafa);
  --at-border: var(--v-border, #e2e8f0);
  --at-text: var(--v-text, #0f172a);
  --at-text-2: var(--v-text-2, #475569);
  --at-text-3: var(--v-text-3, #94a3b8);
  --at-accent: var(--v-accent, #2563eb);
  --at-accent-bg: var(--v-accent-bg, #eff6ff);
  background: var(--at-bg);
  border: 1px solid var(--at-border);
  border-radius: 0.75rem;
  padding: 1.25rem;
}
:global(.dark) .approval-timeline,
.is-dark .approval-timeline {
  --at-bg: var(--v-card, #1e293b);
  --at-border: var(--v-border, #334155);
  --at-text: var(--v-text, #f1f5f9);
  --at-text-2: var(--v-text-2, #94a3b8);
  --at-text-3: var(--v-text-3, #64748b);
  --at-accent: var(--v-accent, #3b82f6);
  --at-accent-bg: var(--v-accent-bg, #1e3a5f);
}

.spinner-container { display: flex; align-items: center; gap: 0.75rem; color: var(--at-text-3); font-size: 0.875rem; padding: 2rem; justify-content: center; }
.spinner-sm { width: 1.25rem; height: 1.25rem; border: 2px solid var(--at-border); border-top-color: var(--at-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.approval-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--at-border); }
.ah-left { display: flex; align-items: center; gap: 0.75rem; }
.ah-title { font-size: 1rem; font-weight: 700; color: var(--at-text); margin: 0; }
.ah-badge { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; }

.btn-action { display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.875rem; background: var(--at-accent); border: none; border-radius: 0.5rem; font-size: 0.78rem; font-weight: 600; color: white; cursor: pointer; transition: all 0.15s; }
.btn-action:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
.btn-icon { width: 0.875rem; height: 0.875rem; }

.workflow-steps { display: flex; flex-direction: column; }
.wf-step { display: flex; gap: 1rem; position: relative; padding-bottom: 1.5rem; }
.wf-step:last-child { padding-bottom: 0; }

.wfs-indicator { display: flex; flex-direction: column; align-items: center; width: 1.5rem; flex-shrink: 0; position: relative; }
.wfs-line { position: absolute; top: 1.5rem; bottom: -0.5rem; width: 2px; background: var(--at-border); z-index: 0; }
.wf-step.completed .wfs-line { background: #22c55e; }
:global(.dark) .wf-step.completed .wfs-line { background: #166534; }

.wfs-dot { width: 1.5rem; height: 1.5rem; border-radius: 50%; background: var(--at-bg); border: 2px solid var(--at-border); display: flex; align-items: center; justify-content: center; z-index: 1; position: relative; }
.wf-step.completed .wfs-dot { background: #22c55e; border-color: #22c55e; color: white; }
:global(.dark) .wf-step.completed .wfs-dot { background: #166534; border-color: #166534; color: #86efac; }
.wf-step.current .wfs-dot { border-color: var(--at-accent); background: var(--at-accent-bg); }
.wf-step.returned .wfs-dot { border-color: #ef4444; background: #fef2f2; color: #ef4444; }
:global(.dark) .wf-step.returned .wfs-dot { border-color: #ef4444; background: #450a0a; }

.dot-icon { width: 0.875rem; height: 0.875rem; }
.dot-num { font-size: 0.7rem; font-weight: 700; color: var(--at-text-3); }
.wf-step.returned .dot-num { color: #ef4444; }
.dot-pulse { width: 0.5rem; height: 0.5rem; background: var(--at-accent); border-radius: 50%; animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(37,99,235,0.4); } 70% { box-shadow: 0 0 0 6px rgba(37,99,235,0); } 100% { box-shadow: 0 0 0 0 rgba(37,99,235,0); } }

.wfc-title { margin: 0 0 0.2rem; font-size: 0.9rem; font-weight: 600; color: var(--at-text); }
.wfc-role { display: flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; color: var(--at-text-3); margin-bottom: 0.5rem; }
.r-icon { width: 0.75rem; height: 0.75rem; }

.wfc-record { background: rgba(0,0,0,0.02); border: 1px solid var(--at-border); border-radius: 0.5rem; padding: 0.75rem; margin-top: 0.25rem; }
:global(.dark) .wfc-record { background: rgba(0,0,0,0.15); }
.rec-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.4rem; }
.rec-user { font-size: 0.75rem; font-weight: 600; color: var(--at-text-2); }
.rec-date { font-size: 0.68rem; color: var(--at-text-3); }
.rec-status { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 0.25rem; margin-bottom: 0.4rem; }
.rec-cond { font-size: 0.8rem; color: #b45309; margin: 0 0 0.25rem; background: #fffbeb; padding: 0.4rem; border-radius: 0.25rem; }
:global(.dark) .rec-cond { color: #fcd34d; background: #78350f; }
.rec-comm { font-size: 0.8rem; color: var(--at-text-2); margin: 0; font-style: italic; }

.wfc-pending { font-size: 0.8rem; color: var(--at-accent); font-weight: 500; margin-top: 0.25rem; }

.empty-state { display: flex; flex-direction: column; align-items: center; padding: 2rem 1rem; color: var(--at-text-3); text-align: center; }
.empty-icon { width: 2.5rem; height: 2.5rem; opacity: 0.5; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; }
.empty-icon svg { width: 100%; height: 100%; }

/* Status Badges */
.s-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
:global(.dark) .s-approved { background: #14532d; color: #86efac; border-color: #166534; }
.s-returned { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
:global(.dark) .s-returned { background: #450a0a; color: #fca5a5; border-color: #7f1d1d; }
.s-pending { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
:global(.dark) .s-pending { background: #422006; color: #fef08a; border-color: #713f12; }
.s-completed { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
:global(.dark) .s-completed { background: #1e3a8a; color: #bfdbfe; border-color: #1e3a8a; }
.s-default { background: var(--at-border); color: var(--at-text-2); }
</style>
