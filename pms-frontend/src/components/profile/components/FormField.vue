<template>
  <div class="flex flex-col gap-1.5">
    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    <slot />
  </div>
</template>

<script setup lang="ts">
defineProps<{ label: string; required?: boolean }>()
</script>

<style scoped>
:global(.form-input) {
  @apply w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 
         bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
         placeholder-gray-400 dark:placeholder-gray-300
         focus:outline-none focus:border-transparent
         transition-colors;
}
</style>