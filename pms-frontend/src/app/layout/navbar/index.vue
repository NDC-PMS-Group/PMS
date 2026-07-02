<script lang="ts" setup>
  import { ref, computed } from "vue";
  import { logo } from "@/assets/images/utils";
  import { Search, Menu, Moon, Sun, ChevronLeft } from "lucide-vue-next";
  import Profile from "@/app/layout/navbar/Profile.vue";
  import Notification from "@/app/layout/navbar/Notification.vue";
  import { useLayoutStore } from "@/store/layout";
  import { useSystemSettingsStore } from "@/store/systemSettings";
  import { resolveImageUrl } from "@/utils/resolveImage";

  const systemSettingsStore = useSystemSettingsStore();
  const activeLogo = computed(() => resolveImageUrl(systemSettingsStore.publicSettings.app_logo) || logo);

  const props = defineProps<{
    isDark: boolean;
  }>();

  const emit = defineEmits(['toggle-theme']);
  const layoutStore = useLayoutStore();

  const isSearchFocused = ref(false);
  const searchQuery = ref("");
  const isMobileMenuOpen = ref(false);

  const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
    layoutStore.toggleMobileSidebar();
  };

  const handleSearch = () => {
    if (searchQuery.value.trim()) {
      console.log("Searching for:", searchQuery.value);
    }
  };

  const toggleTheme = () => {
    emit('toggle-theme');
  };

  const toggleSidebar = () => {
    layoutStore.toggleSidebar();
  };

  const toggleMobileSidebar = () => {
    layoutStore.isMobileSidebarOpen = !layoutStore.isMobileSidebarOpen;
    toggleSidebar();
  };
</script>

<template>
  <header
    id="page-topbar"
    class="fixed top-0 left-0 right-0 z-50 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 transition-all duration-200"
  >
    <div class="h-16 px-4 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <!-- Sidebar Toggle Button (Desktop) -->
        <button
          @click="toggleSidebar"
          class="hidden md:flex p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          :title="layoutStore.isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
          <Menu
            v-if="layoutStore.isSidebarCollapsed"
            class="w-5 h-5 text-slate-700 dark:text-slate-300 transform transition-transform"
          />
          <ChevronLeft
            v-else
            class="w-5 h-5 text-slate-700 dark:text-slate-300 transform transition-transform"
          />
        </button>

        <!-- Mobile Menu Toggle -->
        <button
          @click="toggleMobileSidebar"
          class="md:hidden p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          :title="layoutStore.isMobileSidebarOpen ? 'Close menu' : 'Open menu'"
        >
          <Menu class="w-5 h-5 text-slate-700 dark:text-slate-300" />
        </button>

        <!-- Logo -->
        <router-link
          to="/dashboard"
          class="flex items-center gap-3 group"
        >
          <img
            :src="activeLogo"
            alt="PMS Logo"
            class="h-8 w-auto"
          />
        </router-link>

        <!-- Search Bar (Desktop) -->
        <div class="hidden md:flex items-center ml-6">
          <div
            class="relative transition-all duration-300"
            :class="{ 'w-80': isSearchFocused, 'w-64': !isSearchFocused }"
          >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <Search class="w-4 h-4 text-slate-400" />
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search or jump to..."
              @focus="isSearchFocused = true"
              @blur="isSearchFocused = false"
              @keyup.enter="handleSearch"
              class="w-full pl-10 pr-4 py-2 text-sm bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-transparent text-slate-900 dark:text-slate-100 placeholder-slate-500 dark:placeholder-slate-400 transition-all duration-200"
            />
            <div class="absolute inset-y-0 right-0 flex items-center pr-2">
              <kbd class="inline-flex items-center px-1.5 py-0.5 text-xs font-mono text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 rounded border border-slate-200 dark:border-slate-600">
                ⌘K
              </kbd>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Section: Actions + Profile -->
      <div class="flex items-center gap-2">
        <!-- Mobile Menu Toggle -->
        <button
          @click="toggleMobileMenu"
          class="md:hidden p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          :title="layoutStore.isMobileSidebarOpen ? 'Close menu' : 'Open menu'"
        >
          <Search class="w-5 h-5 text-slate-700 dark:text-slate-300" />
        </button>

        <!-- Theme Toggle -->
        <button
          @click="toggleTheme"
          class="p-2 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors relative group"
          :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        >
          <Moon v-if="!isDark" class="w-5 h-5 text-slate-700 group-hover:text-blue-600 transition-colors" />
          <Sun v-else class="w-5 h-5 text-slate-300 group-hover:text-yellow-400 transition-colors" />
        </button>

        <!-- Notifications -->
        <Notification />

        <!-- Profile -->
        <Profile />
      </div>
    </div>

    <!-- Mobile Search -->
    <div v-if="isMobileMenuOpen" class="md:hidden px-4 pb-3 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition-all duration-200">
      <div class="relative mt-4">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <Search class="w-4 h-4 text-slate-400" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search..."
          @keyup.enter="handleSearch"
          class="w-full pl-10 pr-4 py-2 text-sm bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-slate-900 dark:text-slate-100 placeholder-slate-500 dark:placeholder-slate-400"
        />
      </div>
    </div>
  </header>
</template>

<style scoped>
  #page-topbar.is-sticky {
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
  }

  .dark #page-topbar.is-sticky {
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3), 0 1px 2px 0 rgba(0, 0, 0, 0.2);
  }

  button, input, a {
    transition: all 0.2s ease-in-out;
  }
</style>
