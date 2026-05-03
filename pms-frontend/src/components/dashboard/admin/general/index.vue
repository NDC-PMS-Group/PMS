<template>
  <div class="grid grid-cols-12 gap-4">
    <WelcomeCard />
    <ProjectsByRegionCard />
  </div>
</template>

<script setup lang="ts">
import WelcomeCard           from './WelcomeCard.vue'
import ProjectsByRegionCard  from './ProjectsByRegionCard.vue'
</script>
