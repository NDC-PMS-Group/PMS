<script lang="ts" setup>
import GeneralAnalytics from './general/index.vue'
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="analytics-content">
      <GeneralAnalytics />
    </div>
  </div>
</template>

<style scoped>
.analytics-content {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>