<template>
  <div class="flex items-start gap-3">
    <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center shrink-0 mt-0.5">
      <component :is="iconComponent" :size="15" class="text-gray-500 dark:text-gray-400" />
    </div>
    <div class="min-w-0">
      <p class="text-xs text-gray-500 dark:text-gray-400">{{ label }}</p>
      <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ value || '—' }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  Mail, Phone, MapPin, Building2, Briefcase,
  BadgeCheck, CalendarDays, Cake, AtSign, User,
} from 'lucide-vue-next'

const iconMap: Record<string, any> = {
  Mail, Phone, MapPin, Building2, Briefcase,
  BadgeCheck, CalendarDays, Cake, AtSign, User,
}

const props = defineProps<{
  label: string
  value?: string | null
  icon?: string
}>()

const iconComponent = computed(() => iconMap[props.icon ?? 'User'] ?? User)
</script>