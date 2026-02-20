import { defineAsyncComponent } from "vue";
import { LAYOUT_TYPES } from "@/layouts/types.ts";
import ProjectsPage from "@/pages/projects/Projects.vue";
import TasksPage from "@/pages/tasks/Tasks.vue";

const AdminLayout = defineAsyncComponent(() => import("@/layouts/Admin.vue"));
const GuestLayout = defineAsyncComponent(() => import("@/layouts/Guest.vue"));

const accountRoutes: any[] = [
  {
    path: "/login",
    name: "Sign In",
    component: () => import("@/views/account/Login.vue"),
    props: () => ({ layout: LAYOUT_TYPES.BASIC }),
    meta: {
      title: "Sign In",
      authRequired: false,
      layout: GuestLayout,
    },
  }
];

const dashboardRoutes = [
  {
    path: "/",
    redirect: "/dashboard",
  },
  {
    path: "/dashboard",
    name: "Dashboard",
    component: () => import("@/pages/dashboard/Admin.vue"),
    meta: {
      title: "Dashboard",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin", "assistant", "employee"],
      guard: "dashboard",
    },
  },
];

const adminRoutes = [
  //--- Main Tabs ------------------------------------------------------------------------------- //
  {
    path: '/pdf-viewer/:type/:id',
    name: 'PDFViewer',
    component: () => import('@/pages/admin/PDFViewer.vue'),
    meta: {
      title: 'PDF Viewer',
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin"],
      guard: "pdf-viewer",
    }
  },
  // Projects Route - Fixed
  {
    path: "/projects",
    name: "Projects",
    component: ProjectsPage,
    meta: {
      title: "Projects",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin", "assistant", "employee"],
      guard: "projects",  
    },
  },
  {
    path: "/tasks",
    name: "Tasks",
    component: TasksPage,
    meta: {
      title: "Tasks",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin", "assistant", "employee"],
      guard: "dashboard",
    },
  },


  //--- System Management Tabs ------------------------------------------------------------------ //
  {
    path: "/account/settings",
    name: "Profile",
    component: () => import("@/pages/account/Settings.vue"),
    meta: {
      title: "Settings",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin", "user", "employee"],
      guard: "account_settings",
    },
  },
  {
    path: "/account/settings/employee/profile/:id",
    name: "Employee Profile",
    component: () => import("@/pages/account/Settings.vue"),
    meta: {
      title: "Employee Profile",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin", "user", "employee"],
      guard: "profile",
    },
  },
  {
    path: "/admin/access-settings",
    name: "Access Settings",
    component: () => import("@/pages/admin/AccessSettings.vue"),
    meta: {
      title: "Access Settings",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin"],
      guard: "access_settings",
    },
  },
  {
    path: "/admin/system-settings",
    name: "System Settings",
    component: () => import("@/pages/admin/SystemSettings.vue"),
    meta: {
      title: "System Settings",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin"],
      guard: "system_settings",
    },
  },
  {
    path: "/admin/activity-logs",
    name: "Activity Logs",
    component: () => import("@/pages/admin/ActivityLogs.vue"),
    meta: {
      title: "Activity Logs",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin"],
      guard: "activity_logs",
    },
  },
  {
    path: "/admin/organization",
    name: "Organization",
    component: () => import("@/pages/admin/Organization.vue"),
    meta: {
      title: "Organization",
      authRequired: true,
      layout: AdminLayout,
      roles: ["superadmin", "admin"],
      guard: "organization",
    },
  },
];

const authRoutes = [
  {
    path: "/login/basic",
    name: "SignInBasic",
    component: () => import("@/views/authentication/Login.vue"),
    props: () => ({ layout: LAYOUT_TYPES.BASIC }),
    meta: {
      title: "Sign In",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/login/cover",
    name: "SignInCover",
    component: () => import("@/views/authentication/Login.vue"),
    props: () => ({ layout: LAYOUT_TYPES.COVER }),
    meta: {
      title: "Sign In",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/login/modern",
    name: "SignInModern",
    component: () => import("@/views/authentication/Login.vue"),
    props: () => ({ layout: LAYOUT_TYPES.MODERN }),
    meta: {
      title: "Sign In",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/register/modern",
    name: "RegisterModern",
    component: () => import("@/views/authentication/Register.vue"),
    props: () => ({ layout: LAYOUT_TYPES.MODERN }),
    meta: {
      title: "Register",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/verify-email/modern",
    name: "VerifyEmailModern",
    component: () => import("@/views/authentication/VerifyEmail.vue"),
    props: () => ({ layout: LAYOUT_TYPES.MODERN }),
    meta: {
      title: "Verify Email",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/two-steps/modern",
    name: "TwoStepModern",
    component: () => import("@/views/authentication/TwoStep.vue"),
    props: () => ({ layout: LAYOUT_TYPES.MODERN }),
    meta: {
      title: "Two Step",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/logout/modern",
    name: "LogoutModern",
    component: () => import("@/views/authentication/Logout.vue"),
    props: () => ({ layout: LAYOUT_TYPES.MODERN }),
    meta: {
      title: "Logout",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/reset-password/modern",
    name: "ResetPasswordModern",
    component: () => import("@/views/authentication/ResetPassword.vue"),
    props: () => ({ layout: LAYOUT_TYPES.MODERN }),
    meta: {
      title: "Reset Password",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/create-password/modern",
    name: "CreatePasswordModern",
    component: () => import("@/views/authentication/CreatePassword.vue"),
    props: () => ({ layout: LAYOUT_TYPES.MODERN }),
    meta: {
      title: "Create Password",
      authRequired: false,
      layout: GuestLayout,
    },
  },
];

export const routes = [
  ...dashboardRoutes,
  ...adminRoutes,
  ...authRoutes,
  ...accountRoutes,
];
