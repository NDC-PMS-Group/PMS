<script setup lang="ts">
import { Check } from 'lucide-vue-next'
import { DIVESTMENT_PHASES, type DivestmentPhase } from '@/types/divestment'

const props = defineProps<{ phase: DivestmentPhase }>()

const labels: Record<DivestmentPhase, string> = {
  assessment: 'Assessment',
  due_diligence: 'Due diligence',
  management_approval: 'ManCom',
  board_approval: 'Board',
  execution: 'Execution',
  closure: 'Closed',
}

const currentIndex = () => DIVESTMENT_PHASES.indexOf(props.phase)
</script>

<template>
  <ol class="phase-progress" aria-label="Divestment case progress">
    <li
      v-for="(item, index) in DIVESTMENT_PHASES"
      :key="item"
      :class="{ complete: index < currentIndex(), current: item === phase }"
      :aria-current="item === phase ? 'step' : undefined"
    >
      <span class="phase-dot"><Check v-if="index < currentIndex()" :size="11" /></span>
      <span>{{ labels[item] }}</span>
    </li>
  </ol>
</template>

<style scoped>
.phase-progress{display:grid;grid-template-columns:repeat(6,minmax(4.5rem,1fr));gap:.25rem;margin:0;padding:0;list-style:none}.phase-progress li{display:grid;gap:.3rem;color:#64748b;font-size:.68rem;font-weight:700}.phase-dot{width:100%;height:.35rem;border-radius:.2rem;background:#e2e8f0}.complete .phase-dot,.current .phase-dot{background:#2563eb}.complete,.current{color:#1d4ed8}.phase-dot svg{display:none}@media(max-width:700px){.phase-progress{grid-template-columns:repeat(3,1fr);row-gap:.65rem}}
</style>
