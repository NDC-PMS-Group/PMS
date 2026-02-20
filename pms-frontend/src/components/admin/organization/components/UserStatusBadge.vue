<script lang="ts" setup>
interface Props {
  isActive: boolean
  size?: 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md'
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center font-semibold rounded-full',
      size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs',
      isActive
        ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400'
        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
    ]"
  >
    <span
      :class="[
        'w-1.5 h-1.5 rounded-full mr-1.5',
        isActive ? 'bg-green-500 dark:bg-green-400' : 'bg-gray-400 dark:bg-gray-500'
      ]"
    />
    {{ isActive ? 'Active' : 'Inactive' }}
  </span>
</template>