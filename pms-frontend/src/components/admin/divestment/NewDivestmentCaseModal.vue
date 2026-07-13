<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { X } from 'lucide-vue-next'
import { toast } from 'vue3-toastify'
import axiosInstance from '@/utils/axiosInstance'
import { useDivestmentStore } from '@/store/divestment'
import { useLayoutStore } from '@/store/layout'
import { SITE_MODE } from '@/app/const'
import type { Project } from '@/types/project'

const props = defineProps<{ excludedProjectIds: number[] }>()
const emit = defineEmits<{ close: []; created: [] }>()
const store = useDivestmentStore()
const layoutStore = useLayoutStore()
const isDarkMode = computed(() => layoutStore.mode === SITE_MODE.DARK)
const projects = ref<Project[]>([])
const loadingProjects = ref(false)
const form = ref({ project_id: 0, exit_strategy: '', target_exit_date: '', estimated_proceeds: null as number | null, notes: '' })

onMounted(async () => {
  loadingProjects.value = true
  try {
    const response = await axiosInstance.get('/api/projects', { params: { per_page: 100, sort_by: 'title', sort_order: 'asc' } })
    projects.value = (response.data.data || []).filter((item: Project) => !props.excludedProjectIds.includes(item.id))
  } catch {
    toast.error('Failed to load eligible projects.')
  } finally {
    loadingProjects.value = false
  }
})

async function submit() {
  try {
    await store.createCase({
      project_id: form.value.project_id,
      exit_strategy: form.value.exit_strategy,
      target_exit_date: form.value.target_exit_date || null,
      estimated_proceeds: form.value.estimated_proceeds,
      notes: form.value.notes || null,
    })
    toast.success('Divestment case opened.')
    emit('created')
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to open divestment case.')
  }
}
</script>

<template>
  <div class="new-case-dialog fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4" :class="{ 'is-dark': isDarkMode }" @click.self="emit('close')">
    <section class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="new-exit-title">
      <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
        <div><h2 id="new-exit-title" class="text-lg font-bold text-slate-950 dark:text-white">Open divestment case</h2><p class="mt-1 text-sm text-slate-500">Start formal exit planning for a portfolio project.</p></div>
        <button type="button" class="icon-btn" title="Close" @click="emit('close')"><X :size="19" /></button>
      </header>
      <form class="grid gap-4 p-5" @submit.prevent="submit">
        <label class="field"><span>Project</span><select v-model.number="form.project_id" name="project_id" required :disabled="loadingProjects"><option :value="0" disabled>{{ loadingProjects ? 'Loading projects...' : 'Select a project' }}</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.project_code }} - {{ project.title }}</option></select></label>
        <label class="field"><span>Exit strategy</span><textarea v-model="form.exit_strategy" name="exit_strategy" rows="4" maxlength="10000" required /></label>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="field"><span>Target exit date</span><input v-model="form.target_exit_date" name="target_exit_date" type="date" /></label>
          <label class="field"><span>Estimated proceeds</span><input v-model.number="form.estimated_proceeds" name="estimated_proceeds" type="number" min="0" step="0.01" /></label>
        </div>
        <label class="field"><span>Opening notes</span><textarea v-model="form.notes" name="notes" rows="2" maxlength="10000" /></label>
        <footer class="flex justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-700"><button type="button" class="btn secondary" @click="emit('close')">Cancel</button><button type="submit" class="btn" :disabled="store.submitting || !projects.length">{{ store.submitting ? 'Opening...' : 'Open case' }}</button></footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.icon-btn{display:grid;width:2.5rem;height:2.5rem;place-items:center;border-radius:.4rem;color:#64748b}.icon-btn:hover{background:#f1f5f9}.field{display:grid;gap:.4rem;color:#334155;font-size:.78rem;font-weight:700}.field input,.field select,.field textarea{width:100%;border:1px solid #cbd5e1;border-radius:.4rem;background:white;padding:.7rem .75rem;color:#0f172a;font-size:.9rem;font-weight:400}.field textarea{resize:vertical}.btn{min-height:2.5rem;border-radius:.4rem;background:#2563eb;padding:0 1rem;color:white;font-size:.85rem;font-weight:700}.btn.secondary{border:1px solid #cbd5e1;background:transparent;color:#334155}.btn:disabled{opacity:.55}.new-case-dialog.is-dark .field{color:#cbd5e1}.new-case-dialog.is-dark :is(.field input,.field select,.field textarea){border-color:#475569;background:#0f172a;color:#f8fafc}.new-case-dialog.is-dark .btn.secondary{border-color:#475569;color:#e2e8f0}.new-case-dialog.is-dark .icon-btn:hover{background:#1e293b}
</style>
