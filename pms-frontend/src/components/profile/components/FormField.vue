<template>
  <div class="profile-form-field flex flex-col gap-1.5">
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
.profile-form-field :deep(.form-input) {
  @apply w-full px-3 py-2 text-sm rounded-lg border border-gray-500 bg-white text-gray-900 transition-colors;
  --profile-field-placeholder: theme('colors.gray.600');
  --profile-field-focus: theme('colors.blue.600');
  color-scheme: light;
}

.profile-form-field :deep(.form-input::placeholder) {
  color: var(--profile-field-placeholder);
  opacity: 1;
}

.profile-form-field :deep(.form-input:focus-visible) {
  outline: 2px solid var(--profile-field-focus);
  outline-offset: 2px;
}

:global([data-mode="dark"] .profile-form-field .form-input) {
  @apply border-gray-400 bg-gray-700 text-gray-100;
  --profile-field-placeholder: theme('colors.gray.300');
  --profile-field-focus: theme('colors.blue.400');
  color-scheme: dark;
}
</style>
