<script lang="ts" setup>
  // @ts-nocheck
  import { computed, ref, onMounted, watch, onBeforeUnmount } from "vue";
  import { useRoute, useRouter } from "vue-router";
  import { logo } from "@/assets/images/utils";
  import { menuItems } from "@/app/layout/utils";
  import SubMenu from "@/app/layout/menu/SubMenu.vue";
  import { useLayoutStore } from "@/store/layout";
  import { LucideNetwork } from "lucide-vue-next";
  import { LAYOUTS, SIDEBAR_SIZE } from "@/app/const";
  import { v4 as uuidv4 } from "uuid";
  import { MenuItemType, SubMenuType } from "@/app/layout/types";
  import { useAuthStore } from "@/store/auth";

  const layoutStore = computed(() => useLayoutStore());
  const layoutType = computed(() => layoutStore.value.layoutType);
  const isHorizontal = computed(() => layoutType.value === LAYOUTS.HORIZONTAL);

  const route = useRoute();
  const router = useRouter();
  const path = computed(() => route.path);

  const isMobile = ref(false);

  const sideBarComponent = computed(() => {
    const sideBarSize = layoutStore.value.sideBarSize;

    if (sideBarSize === SIDEBAR_SIZE.SMALL) {
      return "div";
    } else {
      return "simplebar";
    }
  });
  const onLogoClick = () => {
    router.push("/dashboard");
  };
  const mappedData: any = menuItems.map((item) => {
    if (item.subMenu) {
      const nestedSubMenu = item.subMenu.map((subMenu: SubMenuType) => {
        if (subMenu.subMenu) {
          return {
            ...subMenu,
            isActive: false,
            id: uuidv4(),
          };
        }
        return {
          ...subMenu,
        };
      });

      return {
        ...item,
        subMenu: nestedSubMenu,
        isActive: false,
        id: uuidv4(),
      };
    }
    return {
      ...item,
      id: uuidv4(),
    };
  });

  const clientWidth = ref(document.documentElement.clientWidth);

  const menuItemData = ref<MenuItemType[]>(mappedData);

  const onWindowResize = () => {
    isMobile.value = window.innerWidth < 768;
    clientWidth.value = document.documentElement.clientWidth;
    if (isHorizontal.value) {
      setTimeout(() => {
        setupHorizontalMenu();
      }, 300);
    } else {
      menuItemData.value = mappedData;
    }
  };

  onMounted(() => {
    isMobile.value = window.innerWidth < 768;
    window.addEventListener("resize", onWindowResize);

    if (isHorizontal.value) {
      setTimeout(() => {
        setupHorizontalMenu();
      }, 300);
    }
    document.documentElement.addEventListener("click", (event: any) => {
      isSubMenu(event.target);
    });
  });

  onBeforeUnmount(() => {
    document.documentElement.removeEventListener("click", (event: any) => {
      isSubMenu(event.target);
    });
    window.removeEventListener("resize", setupHorizontalMenu);
    window.removeEventListener("resize", onWindowResize);
  });

  const isSubMenu = (element: HTMLElement | null): boolean => {
    if (!element) {
      hideActivation();
      return false;
    }
    return (
      element.classList.contains("submenu-dropdown") ||
      element.classList.contains("app-menu") ||
      isSubMenu(element.parentElement)
    );
  };

  watch(layoutType, (newVal: string) => {
    if (newVal === LAYOUTS.HORIZONTAL) {
      setupHorizontalMenu();
    } else {
      menuItemData.value = mappedData;
    }
  });

  const setupHorizontalMenu = () => {
    if (clientWidth.value >= 768) {
      window.addEventListener("resize", setupHorizontalMenu);

      let isMoreMenu = false;
      const navData = mappedData.filter(
        (menuItem: MenuItemType) => !menuItem.isHeader
      );
      const horizontalMenuEle = document.querySelector(".app-menu");
      const navbarNav = document.querySelector(".navbar-header");
      if (navbarNav) {
        const fullMenuWidth = navbarNav.clientWidth - 100 || 0;

        let totalItemsWidth = 0;
        let visibleItems = [];
        let hiddenItems = [];

        for (let i = 0; i < navData.length; i++) {
          const element: HTMLElement = horizontalMenuEle?.querySelector(
            "#navbar-nav"
          )?.children[i] as HTMLElement;
          const itemWidth = element?.offsetWidth || 0;
          totalItemsWidth += itemWidth;

          if (totalItemsWidth + 250 <= fullMenuWidth) {
            visibleItems.push(navData[i]);
          } else {
            hiddenItems.push(navData[i]);
          }
        }

        const moreMenuItem = {
          title: "more",
          icon: LucideNetwork,
          isActive: false,
          subMenu: hiddenItems,
          id: uuidv4(),
          stateVariables: isMoreMenu,
          click: (e: any) => {
            e.preventDefault();
            isMoreMenu = !isMoreMenu;
          },
        };

        let updatedMenuItems: any = [...visibleItems];
        if (hiddenItems.length) {
          updatedMenuItems.push(moreMenuItem);
        }

        menuItemData.value = updatedMenuItems;
      }
      setTimeout(() => {
        handleDropdownMenu();
      }, 500);
    } else {
      menuItemData.value = mappedData;
    }
  };
  const toggleActivation = (menuItemId: string) => {
    menuItemData.value = menuItemData.value.map((item: MenuItemType) => {
      if (item.id === menuItemId) {
        return {
          ...item,
          isActive: !item.isActive,
        };
      } else if (item.subMenu) {
        const nestedSubmenu = getActivations(menuItemId, item);
        const firstLevelMenu = {
          ...item,
          isActive: nestedSubmenu.some(
            (subMenu: SubMenuType) => subMenu.isActive
          ),
          subMenu: nestedSubmenu,
        };
        return firstLevelMenu;
      }
      return { ...item, isActive: false };
    });
  };

  const getActivations: any = (menuItemId: string, menuItem: MenuItemType) => {
    const preparedData = menuItem.subMenu?.map((subMenu: SubMenuType) => {
      if (menuItemId && subMenu.id === menuItemId) {
        return {
          ...subMenu,
          isActive: !subMenu.isActive,
        };
      } else {
        if (subMenu.subMenu) {
          const nestedSubmenu = getActivations(menuItemId, subMenu);

          const temp = {
            ...subMenu,
            subMenu: nestedSubmenu,
            isActive: nestedSubmenu.some((item: SubMenuType) => item.isActive),
          };
          return temp;
        }
        return { ...subMenu, isActive: false };
      }
    });

    return preparedData;
  };

  const hideActivation = () => {
    menuItemData.value = menuItemData.value.map((item) => {
      if (item.subMenu) {
        const nestedSubmenu = item.subMenu.map((subMenu) => {
          return {
            ...subMenu,
            isActive: false,
          };
        });
        return {
          ...item,
          isActive: false,
          subMenu: nestedSubmenu,
        };
      }
      return { ...item, isActive: false };
    });
  };

  function handleDropdownMenu() {
    const dropdownToggleButtons = document
      .querySelector(".app-menu")
      ?.querySelectorAll(".dropdown-button");
    dropdownToggleButtons?.forEach((button) => {
      const content = button.nextElementSibling;
      button.addEventListener("click", () => {
        if (!content) {
          return;
        }
        setTimeout(() => {
          // get the dropdown menu element
          var dropdownMenu = button;
          const subMenus: any = dropdownMenu.nextElementSibling
            ? dropdownMenu.nextElementSibling
            : dropdownMenu.parentElement?.nextElementSibling;
          if (subMenus) {
            const isLeftFull = subMenus.classList.contains(
              "group-data-[layout=horizontal]:ltr:md:left-full"
            );

            const isRightFull = subMenus.classList.contains(
              "group-data-[layout=horizontal]:rtl:md:right-full"
            );
            if (isLeftFull || isRightFull) {
              if (dropdownMenu && subMenus) {
                // get the position and dimensions of the dropdown menu
                var dropdownOffset = subMenus.getBoundingClientRect();
                var dropdownWidth = subMenus.offsetWidth;
                var dropdownHeight = subMenus.offsetHeight;

                // get the dimensions of the screen
                var screenWidth = window.innerWidth;
                var screenHeight = window.innerHeight;

                // calculate the maximum x and y coordinates of the dropdown menu
                var maxDropdownX = dropdownOffset.left + dropdownWidth;
                var maxDropdownY = dropdownOffset.top + dropdownHeight;

                // check if the dropdown menu goes outside the screen
                var isDropdownOffScreen =
                  maxDropdownX > screenWidth || maxDropdownY > screenHeight;
                if (isDropdownOffScreen) {
                  if (isLeftFull) {
                    subMenus.classList.remove(
                      "group-data-[layout=horizontal]:ltr:md:left-full"
                    );
                    subMenus.classList.add(
                      "group-data-[layout=horizontal]:ltr:md:right-full"
                    );
                  }
                } else if (isRightFull) {
                  subMenus.classList.add(
                    "group-data-[layout=horizontal]:rtl:md:left-full"
                  );
                  subMenus.classList.remove(
                    "group-data-[layout=horizontal]:rtl:md:right-full"
                  );
                }
              }
            }
          }
        }, 10);
      });
    });
  }

  const authStore = useAuthStore();
  const permissions = computed(() => authStore?.permissions ?? []);

  if (!layoutStore.value.hasOwnProperty('isSidebarCollapsed')) {
    layoutStore.value.isSidebarCollapsed = false;
  }
  if (!layoutStore.value.hasOwnProperty('isMobileSidebarOpen')) {
    layoutStore.value.isMobileSidebarOpen = false;
  }
</script>

<template>
  <aside
    :class="[
      'app-menu fixed top-16 left-0 z-30 transition-all duration-300 ease-in-out',
      'bg-white dark:bg-slate-900 border-r border-t border-slate-200 dark:border-slate-800',
      layoutStore.isSidebarCollapsed ? 'w-16' : 'w-64',
      'h-[calc(100vh-4rem)]',
      isMobile && layoutStore.isMobileSidebarOpen ? 'translate-x-0' : '',
      isMobile && layoutStore.isSidebarCollapsed ? '-translate-x-full' : '',
      isHorizontal ? 'hidden md:block' : 'block',
      'print:hidden'
    ]"
  >

    <!-- Menu Content -->
    <component
      :is="sideBarComponent"
      id="menu-scrollbar"
      class="h-[calc(100vh-4rem)] overflow-y-auto py-4"
    >
      <div class="px-3">
        <ul id="navbar-nav" class="space-y-1">
          <template
            v-for="menuItem in menuItemData"
            :key="menuItem.title"
          >
            <!-- Menu Header -->
            <li
              v-if="menuItem.isHeader && permissions.includes(menuItem.guard)"
              class="px-3 py-2 mt-4 first:mt-0"
              :class="{ 'hidden': layoutStore.isSidebarCollapsed }"
            >
              <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                {{ menuItem.title }}
              </span>
            </li>

            <!-- Menu Item with SubMenu -->
            <template
              v-else-if="
                menuItem.subMenu && permissions.includes(menuItem.guard)
              "
            >
              <SubMenu
                :menuItem="menuItem"
                @toggleActivation="toggleActivation"
                :is-collapsed="layoutStore.isSidebarCollapsed"
              />
            </template>

            <!-- Single Menu Item -->
            <li v-else-if="
                !menuItem.subMenu &&
                menuItem.path &&
                permissions.includes(menuItem.guard)
              "
              class="relative group"
            >
              <router-link
                class="flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors group-hover:bg-slate-100 dark:group-hover:bg-slate-800 relative"
                :class="[
                  path === menuItem.path
                    ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 font-medium'
                    : 'text-slate-700 dark:text-slate-300',
                  layoutStore.isSidebarCollapsed ? 'justify-center !text-center' : 'justify-start'
                ]"
                :to="menuItem.path"
                @click="toggleActivation"
              >
                <!-- Icon -->
                <component
                  :is="menuItem.icon"
                  class="w-5 h-5 flex-shrink-0 transition-colors"
                  :class="[
                    path === menuItem.path
                      ? 'text-blue-600 dark:text-blue-400'
                      : 'text-slate-500 dark:text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-300',
                      layoutStore.isSidebarCollapsed ? 'ml-3' : ''
                  ]"
                />
                
                <!-- Label -->
                <span
                  class="ml-3 whitespace-nowrap transition-all duration-300"
                  :class="{
                    'opacity-0 w-0 overflow-hidden': layoutStore.isSidebarCollapsed
                  }"
                >
                  {{ menuItem.title }}
                </span>

                
              </router-link>
            </li>
          </template>
        </ul>
      </div>
    </component>
  </aside>

  <!-- Mobile Overlay -->
  <div
  v-if="isMobile && layoutStore.isMobileSidebarOpen"
    @click="
      layoutStore.isMobileSidebarOpen = false,
      layoutStore.isSidebarCollapsed = true
    "
    class="fixed inset-0 z-20 bg-black/50 transition-opacity duration-300"
  ></div>
</template>

<style scoped>
/* Custom scrollbar - GitHub inspired */
#menu-scrollbar::-webkit-scrollbar {
  width: 6px;
}

#menu-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

#menu-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.3);
  border-radius: 3px;
}

#menu-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.5);
}

.dark #menu-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.2);
}

.dark #menu-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.4);
}

/* Smooth transitions */
.app-menu {
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* GitHub inspired active state */
.router-link-active {
  position: relative;
}
</style>