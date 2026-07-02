<script lang="ts" setup>
  import { computed } from "vue";
  import { logo as defaultLogo } from "@/assets/images/utils";
  import { useSystemSettingsStore } from "@/store/systemSettings";
  import { resolveImageUrl } from "@/utils/resolveImage";

  const systemSettingsStore = useSystemSettingsStore();
  const logo = computed(() => resolveImageUrl(systemSettingsStore.publicSettings.app_logo) || defaultLogo);
</script>

<template>
  <div class="relative flex items-center justify-center min-h-screen w-full overflow-hidden bg-gradient-to-br from-slate-50 via-white to-blue-50">
    <!-- Subtle animated background elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <!-- Gentle floating orbs -->
      <div class="floating-orb orb-1"></div>
      <div class="floating-orb orb-2"></div>
      <div class="floating-orb orb-3"></div>
      
      <!-- Subtle grid overlay -->
      <div class="absolute inset-0 opacity-[0.015]">
        <div class="w-full h-full bg-grid"></div>
      </div>
    </div>

    <!-- Main content container -->
    <div class="auth-shell relative z-10 w-full max-w-[440px] px-6">
      <!-- Logo -->
      <div class="flex justify-center mb-12 animate-fade-in">
        <img
          :src="logo"
          alt="PMS Logo"
          class="h-12 w-auto object-contain"
        />
      </div>

      <!-- Login Card -->
      <div class="auth-card bg-white rounded-2xl shadow-xl shadow-slate-200/50 p-10 animate-slide-up border border-slate-100/50">
        <slot />
      </div>

      <!-- Footer text -->
      <div class="mt-8 text-center animate-fade-in-delayed">
        <p class="text-sm text-slate-500">
          Tools For Managing Your Projects & Task Workflow
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Background grid */
.bg-grid {
  background-image: 
    linear-gradient(rgba(148, 163, 184, 0.4) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, 0.4) 1px, transparent 1px);
  background-size: 50px 50px;
}

/* Floating orbs with pastel brand colors */
.floating-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.08;
  animation: float 25s ease-in-out infinite;
}

.orb-1 {
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(132, 204, 22, 0.3), transparent);
  top: -10%;
  left: -10%;
  animation-delay: 0s;
}

.orb-2 {
  width: 450px;
  height: 450px;
  background: radial-gradient(circle, rgba(251, 146, 60, 0.3), transparent);
  bottom: -10%;
  right: -10%;
  animation-delay: -8s;
}

.orb-3 {
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(20, 184, 166, 0.3), transparent);
  top: 50%;
  right: 20%;
  animation-delay: -16s;
}

@keyframes float {
  0%, 100% {
    transform: translate(0, 0) scale(1);
  }
  33% {
    transform: translate(30px, -30px) scale(1.05);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.95);
  }
}

/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.8s ease-out;
}

.animate-fade-in-delayed {
  animation: fadeIn 0.8s ease-out 0.3s both;
}

.animate-slide-up {
  animation: slideUp 0.8s ease-out 0.2s both;
}

.auth-shell:has(.register-page) {
  max-width: 860px;
}

.auth-shell:has(.register-page) .auth-card {
  padding: 2rem;
}

@media (max-width: 640px) {
  .auth-shell {
    padding-left: 1rem;
    padding-right: 1rem;
  }

  .auth-shell:has(.register-page) .auth-card {
    padding: 1.1rem;
  }
}
</style>
