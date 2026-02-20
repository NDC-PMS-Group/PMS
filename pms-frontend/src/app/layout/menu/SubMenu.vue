<script lang="ts" setup>
  import { computed } from "vue";
  import { useRoute } from "vue-router";
  import { useLayoutStore } from "@/store/layout";
  import { useAuthStore } from "@/store/auth";
  import { ChevronDown } from "lucide-vue-next";

  const emit = defineEmits(["toggleActivation"]);
  const route = useRoute();
  const path = computed(() => route.path);
  const layoutStore = useLayoutStore();

  const props = defineProps({
    menuItem: {
      type: Object,
      default: () => {},
    },
    isNested: {
      type: Boolean,
      default: false,
    },
    isCollapsed: {
      type: Boolean,
      default: false,
    },
  });

  const menuItemData = computed(() => props.menuItem);

  const isDrawerActive = computed(() => {
    return menuItemData.value.subMenu?.some((item: any) => {
      if (item.subMenu) {
        return isNestedMenuActivated(item.subMenu);
      }
      return item.path === path.value;
    });
  });

  const isNestedMenuActivated = (menuItems: any) => {
    return menuItems.some((menuItem: any) => {
      if (menuItem.path === path.value) {
        return true;
      } else if (menuItem.subMenu) {
        return isNestedMenuActivated(menuItem.subMenu);
      }
      return false;
    });
  };

  const toggleActivation = (id: number) => {
    if (!props.isCollapsed) {
      emit("toggleActivation", id);
    }
  };

  const authStore = useAuthStore();
  const permissions = computed(() => authStore?.permissions ?? []);
</script>

<template>
  <li class="relative group">
    <!-- Main Menu Button -->
    <button
      class="w-full flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 relative"
      :class="[
        isDrawerActive || menuItemData.isActive
          ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20'
          : 'text-slate-700 dark:text-slate-300',
        isCollapsed ? 'justify-center' : 'justify-between'
      ]"
      @click="toggleActivation(menuItemData.id)"
    >
      <div class="flex items-center" :class="isCollapsed ? 'justify-center w-full' : ''">
        <!-- Icon -->
        <component
          v-if="menuItemData.icon"
          :is="menuItemData.icon"
          class="w-5 h-5 flex-shrink-0 transition-colors"
          :class="[
            isDrawerActive || menuItemData.isActive
              ? 'text-blue-600 dark:text-blue-400'
              : 'text-slate-500 dark:text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-300',
              layoutStore.isSidebarCollapsed ? 'ml-3' : ''
          ]"
        />
        
        <!-- Label -->
        <span
          class="ml-3 whitespace-nowrap transition-all duration-300"
          :class="{
            'opacity-0 w-0 overflow-hidden': isCollapsed
          }"
        >
          {{ menuItemData.title }}
        </span>
      </div>

      <!-- Chevron (hidden when collapsed) -->
      <ChevronDown
        v-if="!isCollapsed"
        class="w-4 h-4 transition-transform duration-200"
        :class="[
          menuItemData.isActive ? 'rotate-180' : '',
          isDrawerActive || menuItemData.isActive
            ? 'text-blue-600 dark:text-blue-400'
            : 'text-slate-400 dark:text-slate-500'
        ]"
      />
    </button>

    <!-- Submenu Dropdown -->
    <div
      v-if="!isCollapsed"
      class="overflow-hidden transition-all duration-200"
      :class="{
        'max-h-0': !menuItemData.isActive && !isDrawerActive,
        'max-h-96': menuItemData.isActive || isDrawerActive
      }"
    >
      <ul class="mt-1 space-y-1">
        <template v-for="subMenu in menuItemData.subMenu" :key="subMenu.title">
          <template v-if="permissions.includes(subMenu.guard)">
            <!-- Single SubMenu Item -->
            <li v-if="!subMenu.subMenu && subMenu.path">
              <router-link
                :to="subMenu.path"
                class="flex items-center pl-11 pr-3 py-2 text-sm rounded-md transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                :class="[
                  subMenu.path === path
                    ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 font-medium'
                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
                ]"
              >
                <!-- Bullet point indicator -->
                <span 
                  class="w-1.5 h-1.5 rounded-full mr-3 flex-shrink-0"
                  :class="[
                    subMenu.path === path
                      ? 'bg-blue-600 dark:bg-blue-400'
                      : 'bg-slate-400 dark:bg-slate-600'
                  ]"
                ></span>
                {{ subMenu.title }}
              </router-link>
            </li>

            <!-- Nested SubMenu -->
            <SubMenu
              v-else-if="subMenu.subMenu"
              :menu-item="subMenu"
              :is-nested="true"
              :is-collapsed="isCollapsed"
              @toggleActivation="toggleActivation"
            />
          </template>
        </template>
      </ul>
    </div>
  </li>
</template>