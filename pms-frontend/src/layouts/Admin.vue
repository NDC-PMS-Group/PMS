<script setup lang="ts">
  import NavBar from "@/app/layout/navbar/index.vue";
  import MenuComponent from "@/app/layout/menu/index.vue";
  import { computed, watch, onMounted, onUnmounted } from "vue";
  import { useLayoutStore } from "@/store/layout";
  import { useRoute } from "vue-router";
  import { SITE_MODE } from "@/app/const";

  const layoutStore = useLayoutStore();
  const route = useRoute();
  const currentYear = computed(() => new Date().getFullYear());

  // Handle route changes - close mobile sidebar when navigating
  watch(
    () => route.path,
    () => {
      if (window.innerWidth < 768) {
        layoutStore.isMobileSidebarOpen = false;
        layoutStore.isSidebarCollapsed = true;
      }
    }
  );

  // Handle window resize - auto-close sidebar on mobile
  const handleResize = () => {
    const isMobile = window.innerWidth < 768;
    
    if (isMobile) {
      // On mobile, always close the sidebar
      layoutStore.isMobileSidebarOpen = false;
      layoutStore.isSidebarCollapsed = true;
    } else {
      // On desktop, restore the persisted sidebar state
      // The isSidebarCollapsed state will be maintained from localStorage
      // Just ensure mobile sidebar is closed
      layoutStore.isMobileSidebarOpen = false;
    }
  };

  // Content margin based on sidebar state
  const contentMargin = computed(() => {
    if (layoutStore.isSidebarCollapsed) {
      return {
        desktop: 'md:ml-16',
        mobile: 'ml-0'
      };
    } else {
      return {
        desktop: 'md:ml-64',
        mobile: 'ml-0'
      };
    }
  });

  // Theme handling
  const isDark = computed(() => layoutStore.mode === SITE_MODE.DARK);
  
  // Apply theme on mount and when it changes
  onMounted(() => {
    // Apply the persisted theme
    applyTheme(layoutStore.mode);
    
    // Set up resize listener
    window.addEventListener('resize', handleResize);
    
    // Handle initial state based on screen size
    handleResize();
  });

  onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
  });

  watch(() => layoutStore.mode, (newMode) => {
    applyTheme(newMode);
  });

  const applyTheme = (mode: string) => {
    if (mode === SITE_MODE.DARK) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  };
  
  const toggleTheme = () => {
    const newMode = isDark.value ? SITE_MODE.LIGHT : SITE_MODE.DARK;
    layoutStore.changeSiteMode(newMode);
  };
</script>

<template>
  <NavBar :isDark="isDark" @toggle-theme="toggleTheme" />
  <MenuComponent />

  <div class="relative min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors duration-200 flex flex-col">
    <!-- Main Content Area -->
    <div  class="pb-10"
      :class="[
        'pt-16 transition-all duration-300 ease-in-out flex-1',
        contentMargin.mobile,
        contentMargin.desktop
      ]"
      :style="{
        transitionProperty: 'margin',
        transitionDuration: '300ms',
        transitionTimingFunction: 'cubic-bezier(0.4, 0, 0.2, 1)'
      }"
    >
      <div class="px-4 py-6 mx-auto max-w-[1400px]">
        <!-- Optional: Breadcrumb or Page Header (GitHub style) -->
        <div class="mb-6 hidden md:block">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
              <router-link 
                to="/dashboard" 
                class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors"
              >
                PMS
              </router-link>
              <span class="mx-1">/</span>
              <span class="text-slate-900 dark:text-slate-100 font-medium">
                {{ route.meta.title || route.name }}
              </span>
            </div>
          </div>
        </div>
        
        <!-- Router View Content -->
        <router-view />
      </div>
    </div>
    
    <!-- Footer -->
    <footer
      class="md:absolute bottom-0 bg-slate-50 dark:bg-slate-900 px-0 md:px-6 w-fit"
      :class="[
        contentMargin.mobile,
        contentMargin.desktop
      ]"
      :style="{
        transitionProperty: 'margin',
        transitionDuration: '300ms',
        transitionTimingFunction: 'cubic-bezier(0.4, 0, 0.2, 1)'
      }"
    >
      <div class="py-4 w-fit flex justify-center">
        <div
          class="pl-4 flex flex-col md:flex-row items-center justify-center md:justify-between text-sm text-slate-600 dark:text-slate-400 md:gap-10"
        >
          <div class="flex justify-center md:justify-start">
            <p class="text-center md:text-left">{{ currentYear }} © NDC Project Management System</p>
          </div>
          <div class="flex items-center gap-4">
            <a href="#" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
              Terms
            </a>
            <a href="#" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
              Privacy
            </a>
            <a href="#" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
              Help
            </a>
            <a href="#" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
              Status
            </a>
            <a href="#" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">
              Contact
            </a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

* {
  transition: background-color 0.2s ease-in-out, 
              border-color 0.2s ease-in-out,
              color 0.2s ease-in-out;
}

::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.3);
  border-radius: 5px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.5);
}

.dark ::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
}

.dark ::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.4);
}

@media (max-width: 768px) {
  .max-w-\[1400px\] {
    max-width: 100%;
    padding-left: 1rem;
    padding-right: 1rem;
  }
  
  .px-4 {
    padding-left: 1rem;
    padding-right: 1rem;
  }
}
</style>
