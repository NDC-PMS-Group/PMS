<script lang="ts" setup>
import { Check } from 'lucide-vue-next'

interface Step {
  id: number
  label: string
  short?: string
}

defineProps<{
  steps:        Step[]
  currentStep:  number
}>()

const emit = defineEmits<{
  'go-to-step': [n: number]
}>()
</script>

<template>
  <div class="flex items-center px-6 py-4 border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
    <template v-for="(step, idx) in steps" :key="step.id">
      <button
        type="button"
        :disabled="idx > currentStep"
        :class="[
          'flex items-center gap-2 flex-shrink-0 transition-colors',
          idx === currentStep
            ? 'text-blue-600 dark:text-blue-400'
            : idx < currentStep
              ? 'text-green-600 dark:text-green-400 hover:opacity-80 cursor-pointer'
              : 'text-gray-400 dark:text-gray-500 cursor-not-allowed',
        ]"
        @click="idx < currentStep && emit('go-to-step', idx)"
      >
        <span
          :class="[
            'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
            idx === currentStep
              ? 'bg-blue-600 text-white'
              : idx < currentStep
                ? 'bg-green-600 text-white'
                : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400',
          ]"
        >
          <Check v-if="idx < currentStep" class="h-3.5 w-3.5" />
          <span v-else>{{ idx + 1 }}</span>
        </span>
        <span class="text-sm font-medium hidden sm:inline">{{ step.label }}</span>
        <span class="text-sm font-medium sm:hidden">{{ step.short ?? step.label }}</span>
      </button>

      <div
        v-if="idx < steps.length - 1"
        class="flex-1 mx-3 h-px bg-gray-200 dark:bg-gray-700 min-w-[20px]"
      />
    </template>
  </div>
</template>
