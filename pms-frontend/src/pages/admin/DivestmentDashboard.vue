<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ArrowRight, CheckCircle2, Clock3, Plus, RefreshCw, Search, TrendingDown } from 'lucide-vue-next'
import { toast } from 'vue3-toastify'
import { useDivestmentStore } from '@/store/divestment'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'
import type { DivestmentCase, DivestmentPhase } from '@/types/divestment'
import ExitPhaseProgress from '@/components/admin/divestment/ExitPhaseProgress.vue'
import NewDivestmentCaseModal from '@/components/admin/divestment/NewDivestmentCaseModal.vue'
import DivestmentCaseModal from '@/components/admin/divestment/DivestmentCaseModal.vue'
import ViewProjectDialog from '@/components/projects/ViewProjectDialog.vue'

const store = useDivestmentStore()
const layoutStore = useLayoutStore()
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK)
const search = ref('')
const phase = ref<DivestmentPhase | ''>('')
const status = ref<'active' | 'closed' | ''>('active')
const showNewCase = ref(false)
const selectedCase = ref<DivestmentCase | null>(null)
const selectedProjectId = ref<number | null>(null)

const cases = computed(() => store.cases)
const summaryCards = computed(() => [
  { label: 'Active cases', value: cases.value.filter(item => item.status === 'active').length, icon: Clock3, tone: 'blue' },
  { label: 'In due diligence', value: cases.value.filter(item => item.phase === 'due_diligence').length, icon: Search, tone: 'amber' },
  { label: 'For approval', value: cases.value.filter(item => ['management_approval', 'board_approval'].includes(item.phase)).length, icon: CheckCircle2, tone: 'green' },
  { label: 'In execution', value: cases.value.filter(item => item.phase === 'execution').length, icon: TrendingDown, tone: 'red' },
])

const phaseLabels: Record<string, string> = {
  assessment: 'Assessment', due_diligence: 'Due diligence', management_approval: 'Management approval', board_approval: 'Board approval', execution: 'Execution', closure: 'Closed',
}

async function loadCases() {
  try {
    await store.fetchCases({ search: search.value || undefined, phase: phase.value, status: status.value, per_page: 100 })
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to load divestment cases.')
  }
}

function money(value: number | string | null) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1 }).format(Number(value || 0))
}

function isOverdue(item: DivestmentCase) {
  return item.status === 'active' && Boolean(item.target_exit_date && new Date(`${item.target_exit_date}T23:59:59`) < new Date())
}

function handleUpdated(item: DivestmentCase) {
  selectedCase.value = item
}

onMounted(loadCases)
</script>

<template>
  <main class="exit-page" :class="{ 'is-dark': isDarkMode }">
    <header class="page-head">
      <div><p class="eyebrow">SOI-03 Portfolio Exit Strategy</p><h1>Divestment Dashboard</h1><p>Track due diligence, approvals, transaction execution, collections, and closure evidence.</p></div>
      <div class="head-actions"><button class="btn secondary" :disabled="store.loading" @click="loadCases"><RefreshCw :size="16" :class="{ spin: store.loading }" />Refresh</button><button class="btn" @click="showNewCase = true"><Plus :size="16" />Open case</button></div>
    </header>

    <section class="summary-grid" aria-label="Divestment summary">
      <article v-for="item in summaryCards" :key="item.label"><span class="summary-icon" :class="item.tone"><component :is="item.icon" :size="18" /></span><div><strong>{{ item.value }}</strong><span>{{ item.label }}</span></div></article>
    </section>

    <form class="toolbar" @submit.prevent="loadCases">
      <label class="search-box"><span class="sr-only">Search cases</span><Search :size="17" /><input v-model="search" name="search" type="search" placeholder="Search case, project, or proponent" /></label>
      <label><span class="sr-only">Phase</span><select v-model="phase" name="phase"><option value="">All phases</option><option v-for="(label, value) in phaseLabels" :key="value" :value="value">{{ label }}</option></select></label>
      <label><span class="sr-only">Status</span><select v-model="status" name="status"><option value="">All statuses</option><option value="active">Active</option><option value="closed">Closed</option></select></label>
      <button class="btn" type="submit">Apply</button>
    </form>

    <div v-if="store.loading && !cases.length" class="state-panel">Loading divestment cases...</div>
    <div v-else-if="!cases.length" class="state-panel"><TrendingDown :size="30" /><strong>No matching divestment cases</strong><span>Open a case when a portfolio project is ready for formal exit planning.</span></div>
    <section v-else class="case-list" aria-label="Divestment cases">
      <article v-for="item in cases" :key="item.id" class="case-row">
        <div class="case-main">
          <div class="topline"><span class="case-number">{{ item.case_number }}</span><span class="status" :class="item.status">{{ item.status }}</span><span class="phase">{{ phaseLabels[item.phase] }}</span><span v-if="isOverdue(item)" class="overdue">Target overdue</span></div>
          <h2>{{ item.project.title }}</h2><p>{{ item.project.project_code }} · {{ item.project.proponent_name || item.project.proponent_user?.organization_name || 'No proponent recorded' }}</p>
          <ExitPhaseProgress class="mt-4" :phase="item.phase" />
        </div>
        <dl class="metrics"><div><dt>Estimated proceeds</dt><dd>{{ money(item.estimated_proceeds) }}</dd></div><div><dt>Target exit</dt><dd>{{ item.target_exit_date || 'Not set' }}</dd></div><div><dt>Progress</dt><dd>{{ item.progress_percentage }}%</dd></div></dl>
        <div class="row-actions"><button class="btn" @click="selectedCase = item">Manage<ArrowRight :size="15" /></button><button class="btn secondary" @click="selectedProjectId = item.project_id">Project</button></div>
      </article>
    </section>

    <NewDivestmentCaseModal v-if="showNewCase" :excluded-project-ids="cases.map(item => item.project_id)" @close="showNewCase = false" @created="showNewCase = false; loadCases()" />
    <DivestmentCaseModal v-if="selectedCase" :item="selectedCase" @close="selectedCase = null" @updated="handleUpdated" />
    <ViewProjectDialog v-if="selectedProjectId" :model-value="true" :project-id="selectedProjectId" initial-tab="overview" @update:model-value="value => { if (!value) selectedProjectId = null }" />
  </main>
</template>

<style scoped>
.exit-page{--card:#fff;--surface:#f8fafc;--border:#dbe3ee;--text:#0f172a;--muted:#64748b;min-height:100%;padding:2rem;background:var(--surface);color:var(--text)}.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem}.eyebrow{margin:0;color:#2563eb;font-size:.72rem;font-weight:800;text-transform:uppercase}.page-head h1{margin:.25rem 0 0;font-size:1.7rem;letter-spacing:0}.page-head p:last-child{margin:.35rem 0 0;color:var(--muted)}.head-actions,.row-actions{display:flex;gap:.5rem}.btn{display:inline-flex;min-height:2.5rem;align-items:center;justify-content:center;gap:.4rem;border:1px solid #2563eb;border-radius:.4rem;background:#2563eb;padding:0 .9rem;color:white;font-size:.82rem;font-weight:750}.btn.secondary{border-color:var(--border);background:var(--card);color:var(--text)}.btn:disabled{opacity:.55}.spin{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.summary-grid article{display:flex;align-items:center;gap:.7rem;border:1px solid var(--border);border-radius:.5rem;background:var(--card);padding:1rem}.summary-grid article div{display:grid}.summary-grid strong{font-size:1.3rem}.summary-grid article div span{color:var(--muted);font-size:.72rem}.summary-icon{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.4rem}.summary-icon.blue{background:#dbeafe;color:#2563eb}.summary-icon.amber{background:#fef3c7;color:#b45309}.summary-icon.green{background:#dcfce7;color:#15803d}.summary-icon.red{background:#fee2e2;color:#dc2626}.toolbar{display:grid;grid-template-columns:minmax(15rem,1fr) 12rem 10rem auto;gap:.6rem;margin-bottom:1rem;border:1px solid var(--border);border-radius:.5rem;background:var(--card);padding:.75rem}.search-box{display:flex;min-height:2.5rem;align-items:center;gap:.45rem;border:1px solid var(--border);border-radius:.4rem;padding:0 .7rem;color:var(--muted)}.search-box input{min-width:0;flex:1;border:0;background:transparent;color:var(--text);outline:0}.toolbar select{width:100%;min-height:2.5rem;border:1px solid var(--border);border-radius:.4rem;background:var(--surface);padding:0 .65rem;color:var(--text)}.state-panel{display:flex;min-height:15rem;flex-direction:column;align-items:center;justify-content:center;gap:.45rem;border:1px dashed var(--border);border-radius:.5rem;background:var(--card);color:var(--muted)}.state-panel strong{color:var(--text)}.case-list{display:grid;gap:.65rem}.case-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:1.2rem;border:1px solid var(--border);border-radius:.5rem;background:var(--card);padding:1rem}.topline{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem}.case-number{color:#2563eb;font-size:.7rem;font-weight:900}.status,.phase,.overdue{border-radius:999px;padding:.18rem .5rem;font-size:.64rem;font-weight:850;text-transform:uppercase}.status.active{background:#dbeafe;color:#1d4ed8}.status.closed{background:#dcfce7;color:#166534}.phase{background:#f1f5f9;color:#475569}.overdue{background:#fee2e2;color:#b91c1c}.case-main h2{margin:.4rem 0 .15rem;font-size:1rem}.case-main>p{margin:0;color:var(--muted);font-size:.78rem}.metrics{display:grid;grid-template-columns:repeat(3,7.5rem);margin:0}.metrics div{display:grid;gap:.2rem;border-left:1px solid var(--border);padding:0 .7rem}.metrics dt{color:var(--muted);font-size:.65rem}.metrics dd{margin:0;font-size:.8rem;font-weight:800}.row-actions{flex-direction:column}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.exit-page.is-dark{--card:#162238;--surface:#0f172a;--border:#334155;--text:#f1f5f9;--muted:#94a3b8}.exit-page.is-dark .phase{background:#1e293b;color:#cbd5e1}@media(max-width:1050px){.summary-grid{grid-template-columns:repeat(2,1fr)}.case-row{grid-template-columns:1fr}.metrics{grid-template-columns:repeat(3,1fr)}.row-actions{flex-direction:row}}@media(max-width:700px){.exit-page{padding:1rem}.page-head{flex-direction:column}.head-actions{width:100%}.head-actions .btn{flex:1}.summary-grid,.toolbar{grid-template-columns:1fr}.metrics{grid-template-columns:1fr}.metrics div{border-left:0;border-top:1px solid var(--border);padding:.55rem 0}}
</style>
