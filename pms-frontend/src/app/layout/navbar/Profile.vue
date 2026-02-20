<script lang="ts" setup>
  import { userProfile } from "@/assets/images/users/utils";
  import { User2, LogOut } from "lucide-vue-next";
  import { useAuthStore } from "@/store/auth";
  import { computed } from "vue";

  const authStore = useAuthStore();
  const emailAddress = computed(() => authStore?.user?.email ?? "");
  const fullname = computed(
    () => `${authStore?.user?.first_name} ${authStore?.user?.last_name}`
  );

  const logoutHandler = async () => {
    try {
      // Use the auth store logout method (matches HRIS simple approach)
      await authStore.logout();
    } catch (err) {
      console.error("Logout error:", err);
      // Force redirect even on error
      window.location.href = `${window.location.origin}/login`;
    }
  };
</script>
<template>
  <TMenu>
    <div class="relative flex items-center dropdown h-header">
      <button
        type="button"
        class="inline-block p-0 transition-all duration-200 ease-linear bg-topbar rounded-full text-topbar-item dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200"
        id="dropdownMenuButton"
        data-bs-toggle="dropdown"
      >
        <div class="bg-pink-100 rounded-full">
          <img
            :src="authStore.photo_url || userProfile"
            alt=""
            class="object-cover size-[37.5px] rounded-full"
          />
        </div>
      </button>
    </div>
    <template #content>
      <div class="min-w-[14rem] p-4">
        <a
          href="#!"
          class="flex gap-3 mb-3"
        >
          <div class="relative inline-block shrink-0">
            <div class="rounded bg-slate-100 dark:bg-zink-500">
              <img
                :src="authStore.photo_url || userProfile"
                alt=""
                class="size-12 rounded object-cover"
              />
            </div>
          </div>
          <div>
            <h6
              class="mb-1 text-15"
              v-if="fullname"
            >
              {{ fullname }}
            </h6>
            <p
              class="text-slate-500 dark:text-zink-300"
              v-if="emailAddress"
            >
              {{ emailAddress }}
            </p>
          </div>
        </a>
        <ul>
          <li>
            <router-link
              class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500"
              to="/account/settings"
            >
              <User2 class="inline-block size-4 ltr:mr-2 rtl:ml-2" /> Account
            </router-link>
          </li>
          <li class="pt-2 mt-2 border-t border-slate-200 dark:border-zink-500">
            <a
              @click="logoutHandler"
              class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500 cursor-pointer"
            >
              <LogOut class="inline-block size-4 ltr:mr-2 rtl:ml-2" />Sign Out
            </a>
          </li>
        </ul>
      </div>
    </template>
  </TMenu>
</template>