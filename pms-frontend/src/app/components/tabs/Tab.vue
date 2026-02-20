<script lang="ts" setup>
import { inject } from "vue";
const injectedTabData: any = inject("tabs");
const { onTabChange, activeTab } = injectedTabData;

defineProps({
  value: {
    type: String,
    required: true
  },
  tabClass: {
    type: String,
    default: ""
  },
  grow: {
    type: Boolean,
    default: false
  }
});
const onClick = (data: any) => {
  onTabChange(data);
};
</script>
<template>
  <div
    class="group cursor-pointer group/item tabs"
    :class="{
      active: activeTab === value,
      grow: grow
    }"
  >
  <div
  @click="onClick(value)"
  :class="[
    tabClass,
    'px-4 py-2 rounded-md transition-colors duration-200',
    activeTab === value ? 'bg-blue-50 text-blue' : 'bg-blue text-gray-600 hover:bg-blue-100'
  ]"
>
      <slot />
    </div>
  </div>
</template>
