<script lang="ts" setup>
  import { computed, reactive, ref } from "vue";
  import { useRouter } from "vue-router";
  import { useAuthStore } from "@/store/auth";
  import { LAYOUT_TYPES } from "@/layouts/types.ts";
import { toast } from "vue3-toastify";

  const { COVER, BOXED } = LAYOUT_TYPES;
  const props = defineProps({
    layout: {
      type: String,
      default: LAYOUT_TYPES.BASIC,
    },
  });

  const router = useRouter();
  const authStore = useAuthStore();

  const getTitleColor = computed(() => {
    if (props.layout === BOXED || props.layout === COVER) {
      return "text-purple-500 dark:text-purple-500";
    }
    return "text-slate-900 dark:text-custom-500";
  });

  // Form state
  const loginForm = reactive({
    email: "",
    password: "",
    rememberMe: false,
  });

  const errorMessage = ref("");
  const isLoading = ref(false);
  const showPassword = ref(false);

  const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
  };

  const handleLogin = async () => {
    errorMessage.value = "";
    isLoading.value = true;

    try {
      const result = await authStore.login({
        email: loginForm.email,
        password: loginForm.password,
        remember: loginForm.rememberMe,
      });

      if (result.success) {
        toast.success("Logged in successfully!")

        await router.push("/dashboard");
      } else {
        errorMessage.value = result.message;
      }
    } catch (error) {
      console.error("❌ Login failed:", error);
      errorMessage.value = "An unexpected error occurred. Please try again.";
    } finally {
      isLoading.value = false;
    }
  };

  const handleForgotPassword = () => {
    router.push("/forgot-password");
  };

  const handleSignUp = () => {
    router.push("/register");
  };
</script>

<template>
  <div class="w-full">
    <!-- Header -->
    <div class="text-center mb-10">
      <h1
        class="text-3xl font-semibold mb-2 tracking-tight"
        :class="getTitleColor"
      >
        Welcome to PMS
      </h1>
      <p class="text-slate-500 text-base">
        To get started, please sign in
      </p>
    </div>

    <!-- Error Message -->
    <div
      v-if="errorMessage"
      class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"
    >
      {{ errorMessage }}
    </div>

    <!-- Login Form -->
    <form @submit.prevent="handleLogin" class="space-y-5">
      <!-- Email Field -->
      <div>
        <label 
          for="email" 
          class="block text-sm font-medium text-slate-700 mb-2"
        >
          Email address
        </label>
        <input
          id="email"
          v-model="loginForm.email"
          type="email"
          required
          placeholder="name@company.com"
          class="w-full px-4 py-3 border border-slate-300 rounded-lg text-base
                focus:outline-none focus:ring-2 focus:ring-custom-500 focus:border-transparent
                bg-white text-slate-900 placeholder:text-slate-400
                transition-all duration-200
                hover:border-slate-400"
          :disabled="isLoading"
        />
      </div>

      <!-- Password Field -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label 
            for="password" 
            class="block text-sm font-medium text-slate-700"
          >
            Password
          </label>
          <button
            type="button"
            @click="handleForgotPassword"
            class="text-sm text-custom-600 hover:text-custom-700 font-medium
                  transition-colors duration-200"
            :disabled="isLoading"
          >
            Forgot?
          </button>
        </div>
        <div class="relative">
          <input
            id="password"
            v-model="loginForm.password"
            :type="showPassword ? 'text' : 'password'"
            required
            placeholder="Enter your password"
            class="w-full px-4 py-3 pr-12 border border-slate-300 rounded-lg text-base
                  focus:outline-none focus:ring-2 focus:ring-custom-500 focus:border-transparent
                  bg-white text-slate-900 placeholder:text-slate-400
                  transition-all duration-200
                  hover:border-slate-400"
            :disabled="isLoading"
          />
          <button
            type="button"
            @click="togglePasswordVisibility"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 
                  hover:text-slate-600 transition-colors duration-200
                  focus:outline-none focus:text-slate-600"
            :disabled="isLoading"
            tabindex="-1"
          >
            <!-- Eye icon (show password) -->
            <svg
              v-if="!showPassword"
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
            <!-- Eye slash icon (hide password) -->
            <svg
              v-else
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
              />
            </svg>
          </button>
        </div>
      </div>

      <!-- Remember Me -->
      <div class="flex items-center">
        <input
          id="rememberMe"
          v-model="loginForm.rememberMe"
          type="checkbox"
          class="w-4 h-4 text-custom-600 border-slate-300 rounded
                focus:ring-2 focus:ring-custom-500 cursor-pointer
                transition-all duration-200"
          :disabled="isLoading"
        />
        <label 
          for="rememberMe" 
          class="ml-2 text-sm text-slate-700 cursor-pointer select-none"
        >
          Remember me
        </label>
      </div>

      <!-- Login Button -->
      <div class="pt-2">
        <button
          type="submit"
          :disabled="isLoading"
          class="w-full bg-custom-600 hover:bg-custom-700 text-white font-medium
                py-3 px-4 rounded-lg transition-all duration-200
                focus:outline-none focus:ring-2 focus:ring-custom-500 focus:ring-offset-2
                disabled:opacity-50 disabled:cursor-not-allowed
                shadow-sm hover:shadow"
        >
          <span v-if="!isLoading">Continue</span>
          <span v-else class="flex items-center justify-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Signing in...
          </span>
        </button>
      </div>
    </form>

    <!-- Divider -->
    <div class="relative my-8">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-slate-200"></div>
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="px-4 bg-white text-slate-500">or</span>
      </div>
    </div>

    <!-- Sign Up Link -->
    <div class="text-center">
      <p class="text-sm text-slate-600">
        Don't have an account?
        <button
          @click="handleSignUp"
          :disabled="isLoading"
          class="text-custom-600 hover:text-custom-700 font-medium ml-1
                transition-colors duration-200
                disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Sign up
        </button>
      </p>
    </div>
  </div>
</template>

<style scoped>
/* Custom focus styles */
input:focus {
  box-shadow: 0 0 0 3px rgba(var(--custom-500-rgb, 59, 130, 246), 0.1);
}

/* Smooth transitions */
input,
button {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Loading spinner animation */
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>