<!-- src/app/layout/MainLayout.vue -->
<script lang="ts" setup>
  import { onMounted, ref, onBeforeUnmount } from "vue";
  import NavBar from "@/app/layout/navbar/index.vue";
  import MenuComponent from "@/app/layout/menu/index.vue";
  import { Settings } from "lucide-vue-next";
  import CustomizerDrawer from "@/app/layout/navbar/customizer/Drawer.vue";

  const customizerDrawer = ref(false);
  const isDark = ref(false);

  onMounted(() => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    isDark.value = savedTheme === 'dark';
    applyTheme(savedTheme);

    document.addEventListener("scroll", windowScroll);
  });

  const applyTheme = (theme: string) => {
    if (theme === 'dark') {
      document.documentElement.classList.add('dark');
      document.body.classList.add('dark:bg-slate-900', 'dark:text-slate-100');
    } else {
      document.documentElement.classList.remove('dark');
      document.body.classList.remove('dark:bg-slate-900', 'dark:text-slate-100');
    }
    
    // Set body classes with GitHub-inspired styling
    document.body.setAttribute(
      "class",
      "text-base bg-slate-50 text-slate-900 font-sans antialiased dark:bg-slate-900 dark:text-slate-100 transition-colors duration-200"
    );
  };

  const toggleTheme = () => {
    isDark.value = !isDark.value;
    const theme = isDark.value ? 'dark' : 'light';
    localStorage.setItem('theme', theme);
    applyTheme(theme);
  };

  const windowScroll = () => {
    const navbar = document.getElementById("page-topbar");
    if (navbar) {
      if (
        document.body.scrollTop >= 10 ||
        document.documentElement.scrollTop >= 10
      ) {
        navbar.classList.add("is-sticky");
      } else {
        navbar.classList.remove("is-sticky");
      }
    }
  };

  onBeforeUnmount(() => {
    document.removeEventListener("scroll", windowScroll);
  });
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-900 transition-colors duration-200">
    <NavBar :isDark="isDark" @toggle-theme="toggleTheme" />
    <MenuComponent />
    
    <!-- Settings Button (Optional) -->
    <div class="fixed bottom-6 ltr:right-6 rtl:left-6 z-50 hidden md:block">
      <button
        @click="customizerDrawer = true"
        class="w-12 h-12 rounded-full bg-white dark:bg-slate-800 shadow-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center hover:shadow-xl transition-all duration-200 hover:scale-110"
      >
        <Settings class="w-5 h-5 text-slate-600 dark:text-slate-400" />
      </button>
    </div>

    <!-- Customizer Drawer -->
    <TDrawer v-model="customizerDrawer" width="md:w-96">
      <template #title>
        <div class="flex flex-col">
          <h5 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Theme Customizer
          </h5>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
            Customize your workspace appearance
          </p>
        </div>
      </template>
      <template #content>
        <CustomizerDrawer v-if="customizerDrawer" />
      </template>
    </TDrawer>
  </div>
</template>

<style scoped>
/* GitHub-inspired smooth scrolling */
html {
  scroll-behavior: smooth;
}

/* Custom scrollbar styling */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.3);
  border-radius: 4px;
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
</style>