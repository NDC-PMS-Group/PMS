import { App, createApp } from "vue";
import "@/assets/scss/tailwind.scss";
import './assets/main.css'; 
import MainApp from "@/App.vue";
import i18n from "@/plugins/i18n";
import router from "@/router/index";
import VueApexCharts from "vue3-apexcharts";
import PageHeader from "@/app/common/PageHeader.vue";
import simplebar from "simplebar-vue";
import VueTippy from "vue-tippy";
import Popper from "vue3-popper";

import { Mask } from "maska";
import VueEasyLightbox from "vue-easy-lightbox";
import { initGlobalComponents } from "@/plugins/components";

import appConfigs from "@/app/appConfig.ts";
import { initFirebaseBackend } from "@/app/service/httpService/firebaseService.ts";
import { VueQueryPlugin } from "@tanstack/vue-query";
import Vue3Toastify, { type ToastContainerOptions } from "vue3-toastify";

import DataTable from "datatables.net-vue3";
import DataTablesCore from "datatables.net";

import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import { useAuthStore } from '@/store/auth';

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);


if (appConfigs.auth === "firebase") {
  const firebaseConfig = {
    apiKey: appConfigs.fireBaseApiKey,
    authDomain: appConfigs.fireBaseAuthDomain,
    databaseURL: appConfigs.fireBaseDBUrl,
    projectId: appConfigs.fireBaseProjectId,
    storageBucket: appConfigs.fireBaseStorageBucket,
    messagingSenderId: appConfigs.fireBaseMsgSenderId,
  };

  initFirebaseBackend(firebaseConfig);
}

export const app: App = createApp(MainApp);
initGlobalComponents(app);

DataTable.use(DataTablesCore);
app.use(pinia);
app.use(i18n);
app.use(VueApexCharts);
app.use(router);
app.use(
  VueTippy,
  {
    directive: "tippy",
    component: "tippy",
  }
);

app.use(VueQueryPlugin);
app.use(VueEasyLightbox);
app.directive("mask", Mask as any);
app.component("simplebar", simplebar);
app.component("PageHeader", PageHeader);
app.component("Popper", Popper);

app.use(Vue3Toastify, {
  autoClose: 1200,
  position: "bottom-right",
} as ToastContainerOptions);

async function initializeApp() {
  const authStore = useAuthStore();
  await authStore.initialize();

  await router.isReady();

  app.mount("#app");
}

initializeApp().catch(error => {
  console.error('App initialization failed:', error);
  app.mount("#app");
});