<script lang="ts" setup>
  import { onMounted, computed } from "vue";
  import { useLayoutStore } from "@/store/layout";
  import { setAttribute } from "@/app/utils";
  import { VueQueryDevtools } from "@tanstack/vue-query-devtools";

  import { useSystemSettingsStore } from '@/store/systemSettings'
  import { useSessionTimeout } from '@/composables/useSessionTimeout'
  import SessionTimeoutModal from '@/components/admin/systemSettings/components/SessionTimeoutModal.vue'

  const systemSettingsStore = useSystemSettingsStore()

  // Initialize session timeout
  const { 
    showWarning, 
    remainingSeconds, 
    extendSession, 
    logout 
  } = useSessionTimeout()

  const layoutStore = computed(() => useLayoutStore());

  onMounted(async  () => {
    try {
      // Fetch session timeout settings
      await systemSettingsStore.fetchPublicSettings()
    } catch (error) {
      console.error('Failed to fetch public settings:', error)
    }

    const {
      mode,
      layoutType,
      layoutWidth,
      topBarColor,
      sideBarSize,
      sideBarColor,
      dir,
      skinLayout,
      navType,
    } = layoutStore.value;

    setAttribute("data-mode", mode);
    setAttribute("data-topbar", topBarColor);
    setAttribute("data-sidebar", sideBarColor);
    setAttribute("data-layout", layoutType);
    setAttribute("data-skin", skinLayout);
    setAttribute("dir", dir);
    setAttribute("data-content", layoutWidth);
    setAttribute("data-sidebar-size", sideBarSize);
    setAttribute("data-navbar", navType);
  });
</script>

<template>
  <component
    v-if="$route.meta.layout"
    :is="$route.meta.layout"
  >
    <router-view />

    <SessionTimeoutModal
      :show="showWarning"
      :remaining-seconds="remainingSeconds"
      @extend="extendSession"
      @logout="logout"
    />

  </component>
  <router-view v-else />
  <VueQueryDevtools />
</template>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>

<style>
  .z-9999{
    z-index: 9999;
    margin-top: 0 !important;
  }
  
  .multiselect,
  .multiselect__input,
  .multiselect__single {
    font-size: 12px !important;
    min-height: 10px !important;
  }

  .multiselect__tags {
    border: none;
    min-height: 10px !important;
    /* padding: 2px 4px !important; */
  }

  .multiselect__single {
    overflow: hidden;
    display: inline-block;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  .multiselect__option--highlight {
    background-color: #3b82f6;
  }

  .multiselect__placeholder {
    padding-top: 0 !important;
    font-size: 12px !important;
  }

  /* Global Loading Styles */
  .global-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
  }

  .spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  .modal-scroll::-webkit-scrollbar {
    width: 8px;
  }

  .modal-scroll::-webkit-scrollbar-track {
    background: transparent;
  }

  .modal-scroll::-webkit-scrollbar-thumb {
    background: rgb(209 213 219);
    border-radius: 4px;
  }

  .dark .modal-scroll::-webkit-scrollbar-thumb {
    background: rgb(75 85 99);
  }

  .modal-scroll::-webkit-scrollbar-thumb:hover {
    background: rgb(156 163 175);
  }

  .dark .modal-scroll::-webkit-scrollbar-thumb:hover {
    background: rgb(107 114 128);
  }
</style>
