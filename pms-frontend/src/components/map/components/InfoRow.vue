<template>
  <div v-if="value" class="flex items-start gap-3">
    <div class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center flex-shrink-0 mt-0.5">
      <component :is="iconComponent" :size="13" class="text-gray-500 dark:text-gray-400" />
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-0.5">
        {{ label }}
      </p>
      <p class="text-sm text-gray-800 dark:text-gray-200 leading-snug">
        {{ value }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  MapPin, User, Layers, DollarSign,
  Calendar, CalendarCheck, FileText,
} from 'lucide-vue-next'

const props = defineProps<{
  icon: string
  label: string
  value: string | null | undefined
}>()

const iconMap: Record<string, any> = {
  MapPin, User, Layers, DollarSign, Calendar, CalendarCheck, FileText,
}

const iconComponent = computed(() => iconMap[props.icon] ?? FileText)
</script>