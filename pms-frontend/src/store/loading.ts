import { defineStore } from "pinia";

export const useLoadingStore = defineStore("loading-store", {
  state: () => ({ loading: false }),
  actions: {
    toggleLoadingState(value: boolean) {
      this.loading = value;
    },
  },
});
