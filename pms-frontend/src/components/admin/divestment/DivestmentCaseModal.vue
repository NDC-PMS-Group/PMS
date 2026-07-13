<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ArrowRight, X } from 'lucide-vue-next'
import { toast } from 'vue3-toastify'
import { useDivestmentStore } from '@/store/divestment'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'
import type { CloseDivestmentCasePayload, DivestmentCase } from '@/types/divestment'
import ExitPhaseProgress from './ExitPhaseProgress.vue'

const props = defineProps<{ item: DivestmentCase }>()
const emit = defineEmits<{ close: []; updated: [item: DivestmentCase] }>()
const store = useDivestmentStore()
const layoutStore = useLayoutStore()
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK)
const notes = ref('')
const boardApprovedAt = ref('')
const closure = ref<CloseDivestmentCasePayload>({
  board_approved_at: '', transfer_completed_at: '', proceeds_collected_at: '', closing_documents_completed_at: '', actual_proceeds: 0, closure_notes: '',
})
const phaseLabels: Record<string, string> = { assessment: 'Assessment', due_diligence: 'Due diligence', management_approval: 'Management approval', board_approval: 'Board approval', execution: 'Execution', closure: 'Closure' }
const isClosing = computed(() => props.item.phase === 'execution' && props.item.status === 'active')

watch(() => props.item, (item) => {
  const date = (value: string | null) => value?.slice(0, 10) || ''
  boardApprovedAt.value = date(item.closure_gates.board_approved_at)
  closure.value = {
    board_approved_at: date(item.closure_gates.board_approved_at),
    transfer_completed_at: date(item.closure_gates.transfer_completed_at),
    proceeds_collected_at: date(item.closure_gates.proceeds_collected_at),
    closing_documents_completed_at: date(item.closure_gates.closing_documents_completed_at),
    actual_proceeds: Number(item.actual_proceeds || item.estimated_proceeds || 0),
    closure_notes: item.closure_notes || '',
  }
}, { immediate: true })

async function advance() {
  if (!props.item.next_phase || props.item.next_phase === 'closure') return
  try {
    if (props.item.next_phase === 'execution' && boardApprovedAt.value) {
      await store.updateCase(props.item.id, { board_approved_at: boardApprovedAt.value } as any)
    }
    const updated = await store.transition(props.item.id, props.item.next_phase, notes.value)
    toast.success(`Case advanced to ${phaseLabels[updated.phase]}.`)
    notes.value = ''
    emit('updated', updated)
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to advance the case.')
  }
}

async function closeCase() {
  try {
    const updated = await store.closeCase(props.item.id, closure.value)
    toast.success('Divestment case closed.')
    emit('updated', updated)
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to close the case.')
  }
}
</script>

<template>
  <div class="case-dialog fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4" :class="{ 'is-dark': isDarkMode }" @click.self="emit('close')">
    <section class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="case-title">
      <header class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900">
        <div><p class="text-xs font-bold text-blue-600">{{ item.case_number }}</p><h2 id="case-title" class="mt-1 text-lg font-bold text-slate-950 dark:text-white">{{ item.project.title }}</h2><p class="mt-1 text-sm text-slate-500">{{ phaseLabels[item.phase] }} · {{ item.status }}</p></div>
        <button type="button" class="icon-btn" title="Close" @click="emit('close')"><X :size="19" /></button>
      </header>
      <div class="grid gap-6 p-5">
        <ExitPhaseProgress :phase="item.phase" />
        <div class="grid gap-4 sm:grid-cols-3"><div class="metric"><span>Target exit</span><strong>{{ item.target_exit_date || 'Not set' }}</strong></div><div class="metric"><span>Estimated proceeds</span><strong>{{ Number(item.estimated_proceeds || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' }) }}</strong></div><div class="metric"><span>Progress</span><strong>{{ item.progress_percentage }}%</strong></div></div>
        <section><h3 class="section-title">Exit strategy</h3><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600 dark:text-slate-300">{{ item.exit_strategy }}</p></section>

        <form v-if="item.status === 'active' && !isClosing" class="form-panel" @submit.prevent="advance">
          <div><h3 class="section-title">Advance to {{ phaseLabels[item.next_phase || ''] }}</h3><p class="mt-1 text-xs text-slate-500">Record the decision or evidence supporting this transition.</p></div>
          <label v-if="item.next_phase === 'execution'" class="field"><span>Board approval date</span><input v-model="boardApprovedAt" type="date" required /></label>
          <label class="field"><span>Transition notes</span><textarea v-model="notes" rows="3" required maxlength="5000" /></label>
          <button class="btn justify-self-end" type="submit" :disabled="store.submitting"><span>{{ store.submitting ? 'Advancing...' : 'Advance case' }}</span><ArrowRight :size="16" /></button>
        </form>

        <form v-else-if="isClosing" class="form-panel" @submit.prevent="closeCase">
          <div><h3 class="section-title">Closure evidence</h3><p class="mt-1 text-xs text-slate-500">All gates are required before the case and project lifecycle can close.</p></div>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="field"><span>Board approval date</span><input v-model="closure.board_approved_at" type="date" required /></label>
            <label class="field"><span>Transfer completed</span><input v-model="closure.transfer_completed_at" type="date" required /></label>
            <label class="field"><span>Proceeds collected</span><input v-model="closure.proceeds_collected_at" type="date" required /></label>
            <label class="field"><span>Closing documents complete</span><input v-model="closure.closing_documents_completed_at" type="date" required /></label>
          </div>
          <label class="field"><span>Actual proceeds</span><input v-model.number="closure.actual_proceeds" type="number" min="0" step="0.01" required /></label>
          <label class="field"><span>Closure notes</span><textarea v-model="closure.closure_notes" rows="3" required maxlength="10000" /></label>
          <button class="btn justify-self-end" type="submit" :disabled="store.submitting">{{ store.submitting ? 'Closing...' : 'Close case' }}</button>
        </form>

        <section><h3 class="section-title">Transition history</h3><ol class="mt-3 grid gap-3"><li v-for="transition in [...item.transitions].reverse()" :key="transition.id" class="timeline"><strong>{{ phaseLabels[transition.to_phase] }}</strong><span>{{ new Date(transition.transitioned_at).toLocaleString() }} · {{ transition.transitioned_by?.full_name || 'System' }}</span><p>{{ transition.notes }}</p></li></ol></section>
      </div>
    </section>
  </div>
</template>

<style scoped>
.icon-btn{display:grid;width:2.5rem;height:2.5rem;place-items:center;border-radius:.4rem;color:#64748b}.icon-btn:hover{background:#f1f5f9}.metric{display:grid;gap:.25rem;border-left:3px solid #2563eb;padding:.35rem .75rem}.metric span{color:#64748b;font-size:.7rem;font-weight:700;text-transform:uppercase}.metric strong{color:#0f172a;font-size:.9rem}.section-title{color:#0f172a;font-size:.9rem;font-weight:800}.form-panel{display:grid;gap:1rem;border:1px solid #cbd5e1;border-radius:.5rem;background:#f8fafc;padding:1rem}.field{display:grid;gap:.4rem;color:#334155;font-size:.78rem;font-weight:700}.field input,.field textarea{width:100%;border:1px solid #cbd5e1;border-radius:.4rem;background:white;padding:.65rem .75rem;color:#0f172a;font-size:.9rem;font-weight:400}.btn{display:inline-flex;min-height:2.5rem;align-items:center;gap:.45rem;border-radius:.4rem;background:#2563eb;padding:0 1rem;color:white;font-size:.85rem;font-weight:700}.btn:disabled{opacity:.55}.timeline{display:grid;gap:.2rem;border-left:2px solid #cbd5e1;padding-left:.8rem}.timeline strong{font-size:.82rem;color:#0f172a}.timeline span{font-size:.68rem;color:#64748b}.timeline p{margin:.2rem 0 0;color:#475569;font-size:.78rem}.case-dialog.is-dark .icon-btn:hover{background:#1e293b}.case-dialog.is-dark :is(.metric strong,.section-title,.timeline strong){color:#f8fafc}.case-dialog.is-dark .form-panel{border-color:#475569;background:#0f172a}.case-dialog.is-dark .field{color:#cbd5e1}.case-dialog.is-dark :is(.field input,.field textarea){border-color:#475569;background:#020617;color:#f8fafc}.case-dialog.is-dark .timeline{border-color:#475569}.case-dialog.is-dark .timeline p{color:#cbd5e1}
</style>
