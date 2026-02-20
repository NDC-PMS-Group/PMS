<script lang="ts" setup>
  import NavBtn from "@/app/layout/navbar/Button.vue";
  import {
    BellRing,
    Mail,
    MoveRight,
    CalendarPlus,
    GraduationCap,
    Megaphone,
    Fingerprint,
  } from "lucide-vue-next";
  import { ref, onMounted } from "vue";
  import { notificationData } from "@/app/layout/navbar/utils.ts";
  import { computed } from "vue";
  import axiosInstance from "@/utils/axiosInstance";
  import moment from "moment";

  const notificationData = ref([]);

  const getNotificationCount = async () => {
    const response = await axiosInstance.get("/api/v1/admin/notifications");
    const data = await response.data;
    notificationData.value = data.data || [];
  };

  onMounted(() => {
    getNotificationCount();
  });
</script>
<template>
  <TMenu>
    <NavBtn class="dropdown">
      <BellRing
        class="inline-block size-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"
      />
      <span class="absolute top-0 right-0 flex w-1.5 h-1.5">
        <span
          class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-sky-400"
        />
        <span
          class="relative inline-flex w-1.5 h-1.5 rounded-full bg-sky-500"
        />
      </span>
    </NavBtn>
    <template #content>
      <div
        class="w-[20rem] lg:w-[26rem] border border-slate-200 dark:border-zink-500"
      >
        <div class="p-4">
          <h6 class="text-sm">
            Notifications
            <span
              class="text-xs text-center px-1 pt-0 ml-1 font-medium border rounded-full text-white bg-orange-500 border-orange-500"
            >
              {{ notificationData.length }}
            </span>
          </h6>
        </div>
        <simplebar class="max-h-[350px]">
          <div class="flex flex-col gap-1">
            <a
              v-for="(item, index) in notificationData"
              :key="'notification-' + index"
              href="#!"
              class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 follower"
              :title="'Title: ' + item.Title + ' Message ' + item.Message"
            >
              <div class="relative pt-2 shrink-0">
                <div
                  v-if="
                    item.Title == 'Time In Notification' ||
                    item.Title == 'Time In/Out Notification'
                  "
                >
                  <Fingerprint
                    fill="#E0AB8B"
                    class="size-6"
                  ></Fingerprint>
                </div>
                <div v-else-if="item.Title == 'Leave Request Notification'">
                  <CalendarPlus
                    fill="#428bca"
                    class="size-6"
                  ></CalendarPlus>
                </div>
                <div v-else-if="item.Title == 'Training Request Submitted' || item.Title == 'Training Request Approved' || item.Title == 'Training Request Rejected' || item.Title == 'Training Program Created'">
                  <GraduationCap
                    fill="#6B7280"
                    class="size-6"
                  ></GraduationCap>
                </div>
                <div v-else>
                  <Megaphone
                    fill="#F54927"
                    class="size-6"
                  ></Megaphone>
                </div>
              </div>

              <div class="grow">
                <h6
                  class="mb-1 font-medium truncate w-[200px]"
                  v-html="item.Title"
                ></h6>
                <p
                  class="mb-0 text-sm text-slate-500 dark:text-zink-300 truncate max-w-[220px]"
                >
                  <Mail class="inline-block w-3.5 h-3.5 mr-1" />
                  {{ item.Message }}
                </p>
              </div>
              <div
                class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300"
              >
                <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div>
                {{ moment(item.CreatedAt).fromNow() }}
              </div>
            </a>
          </div>
        </simplebar>
        <!-- <div
          class="flex items-center gap-2 p-4 border-t border-slate-200 dark:border-zink-500"
        >
          <div class="grow">
            <a href="#!">Manage Notification</a>
          </div>
          <div class="shrink-0">
            <button
              type="button"
              class="px-2 py-1.5 text-xs text-white transition-all duration-200 ease-linear btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600 focus:ring focus:ring-custom-100 active:text-white active:bg-custom-600 active:border-custom-600 active:ring active:ring-custom-100"
            >
              View All Notification
              <MoveRight class="inline-block w-3.5 h-3.5 ml-1" />
            </button>
          </div>
        </div> -->
      </div>
    </template>
  </TMenu>
</template>
