import {
  createRouter,
  createWebHistory,
} from "vue-router";
import { routes } from "@/router/routes.ts";
import { useAuthStore } from "@/store/auth";

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.onError((error: any) => {
  const message = String(error?.message || "");
  const isChunkLoadError =
    message.includes("Failed to fetch dynamically imported module") ||
    message.includes("Importing a module script failed");

  if (!isChunkLoadError) return;

  const key = "pms-route-reload-once";
  if (sessionStorage.getItem(key) === "1") {
    sessionStorage.removeItem(key);
    return;
  }

  sessionStorage.setItem(key, "1");
  window.location.reload();
});

router.beforeEach(async (to, from, next) => {
  const nearestWithTitle = to.matched
    .slice()
    .reverse()
    .find((r: any) => r.meta && r.meta.title);

  if (nearestWithTitle) {
    document.title = nearestWithTitle.meta.title + " | PMS";
  }

  window.scrollTo({ top: 0, behavior: "smooth" });
  
  const authStore = useAuthStore()
  const requiresAuth = to.meta.authRequired

  // ========== HANDLE PUBLIC ROUTES ==========
  if (!requiresAuth) {
    if (to.path === '/login' || to.path.startsWith('/login/')) {
      const token = localStorage.getItem('auth_token')
      if (token) {
        return next('/dashboard')
      }
    }
    
    return next()
  }

  // ========== HANDLE PROTECTED ROUTES ==========

  const token = localStorage.getItem('auth_token')
  
  if (!token) {
    console.log('No token found, redirecting to login')
    return next('/login')
  }

  // Step 2: Initialize auth store if needed
  if (!authStore.isInitialized) {
    try {
      await authStore.initialize()
    } catch (error) {
      console.error('Auth initialization failed:', error)
      authStore.clearAuth()
      return next('/login')
    }
  }

  // Step 3: Verify authentication status
  if (!authStore.isAuthenticated || !authStore.user) {
    console.log('Not authenticated or no user data')
    authStore.clearAuth()
    return next('/login')
  }

  // Step 4: Check role-based access
  const allowedRoles = to.meta.roles as string[] | undefined
  if (allowedRoles && allowedRoles.length > 0) {
    const userRole = authStore.userRole.toLowerCase()
    const hasRole = allowedRoles.some(role => role.toLowerCase() === userRole)
    
    if (!hasRole) {
      console.log(`Access denied: User role "${userRole}" not in allowed roles`)
      if (to.path !== '/dashboard') {
        return next('/dashboard')
      }
      return next(false)
    }
  }

  // Step 5: Check permission-based access
  const guardName = to.meta.guard as string | undefined
  if (guardName) {
    if (authStore.userRole.toLowerCase() === 'superadmin') {
      return next()
    }

    const hasPermission = authStore.canView(guardName)
    if (!hasPermission) {
      console.log(`Access denied: Missing permission "${guardName}.view"`)
      if (to.path !== '/dashboard') {
        return next('/dashboard')
      }
      return next(false)
    }
  }
  return next()
})

export default router;
