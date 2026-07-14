import { defineAsyncComponent } from "vue";
import ProjectsPage from "@/pages/projects/Projects.vue";
import TasksPage from "@/pages/tasks/Tasks.vue";

const AdminLayout = defineAsyncComponent(() => import("@/layouts/Admin.vue"));
const GuestLayout = defineAsyncComponent(() => import("@/layouts/Guest.vue"));

const accountRoutes: any[] = [
  {
    path: "/login",
    name: "Sign In",
    component: () => import("@/pages/account/Login.vue"),
    props: () => ({ layout: GuestLayout }),
    meta: {
      title: "Sign In",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/register",
    name: "Proponent Registration",
    component: () => import("@/pages/account/Register.vue"),
    props: () => ({ layout: GuestLayout }),
    meta: {
      title: "Proponent Registration",
      authRequired: false,
      layout: GuestLayout,
    },
  },
  {
    path: "/staff-invite/:token",
    name: "Staff Invite Setup",
    component: () => import("@/pages/account/StaffInviteSetup.vue"),
    props: () => ({ layout: GuestLayout }),
    meta: {
      title: "Staff Invite Setup",
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
      guard: "dashboard",
    },
  },
  {
    path: "/notifications",
    alias: "/inbox",
    name: "Notifications",
    component: () => import("@/pages/notifications/Notifications.vue"),
    meta: {
      title: "Notifications",
      authRequired: true,
      layout: AdminLayout,
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
      guard: "projects",  
    },
  },
  {
    path: "/tasks",
    name: "Tasks",
    component: TasksPage,
    meta: {
      title: "Implementation Tasks",
      authRequired: true,
      layout: AdminLayout,
      guard: "tasks",
    },
  },
  {
    path: "/projects/:id/tasks",
    name: "Project Tasks",
    component: TasksPage,
    meta: {
      title: "Implementation Plan",
      authRequired: true,
      layout: AdminLayout,
      guard: "tasks",
    },
  },
  {
    path: "/project-map",
    name: "Project Map",
    component:  () => import("@/pages/map/Map.vue"),
    meta: {
      title: "Project Map",
      authRequired: true,
      layout: AdminLayout,
      guard: "project_map",
    },
  },


  //--- System Management Tabs ------------------------------------------------------------------ //
  {
    path: "/account/profile",
    name: "Profile",
    component: () => import("@/pages/account/Profile.vue"),
    meta: {
      title: "Profile",
      authRequired: true,
      layout: AdminLayout,
      guard: "profile",
    },
  },
  {
    path: "/account/profile/:id",
    name: "Employee Profile",
    component: () => import("@/pages/account/Profile.vue"),
    meta: {
      title: "Employee Profile",
      authRequired: true,
      layout: AdminLayout,
      guard: "employee_profile",
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
      guard: "system_settings",
    },
  },
  {
    path: "/admin/notification-rules",
    name: "Notification Management",
    component: () => import("@/pages/admin/NotificationRules.vue"),
    meta: {
      title: "Notification Management",
      authRequired: true,
      layout: AdminLayout,
      guard: "system_settings",
    },
  },
  {
    path: "/implementation-monitoring",
    alias: "/admin/post-monitoring",
    name: "Monitoring Compliance",
    component: () => import("@/pages/admin/PostMonitoring.vue"),
    meta: {
      title: "Monitoring Compliance",
      authRequired: true,
      layout: AdminLayout,
      guard: "projects",
    },
  },
  {
    path: "/admin/divestment",
    name: "Exit Management",
    component: () => import("@/pages/admin/DivestmentDashboard.vue"),
    meta: {
      title: "Exit Management",
      authRequired: true,
      layout: AdminLayout,
      guard: "admin_tools",
      roles: ["superadmin", "admin"],
    },
  },
  {
    path: "/admin/reports",
    name: "Reports",
    component: () => import("@/pages/admin/Reports.vue"),
    meta: {
      title: "Reports",
      authRequired: true,
      layout: AdminLayout,
      guard: "admin_tools",
      roles: ["superadmin", "admin"],
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
      guard: "organization",
    },
  },
  {
    path: "/admin/pending-accounts",
    name: "Pending Accounts",
    component: () => import("@/pages/admin/PendingAccounts.vue"),
    meta: {
      title: "Pending Accounts",
      authRequired: true,
      layout: AdminLayout,
      guard: "organization",
    },
  },
];


export const routes = [
  ...dashboardRoutes,
  ...adminRoutes,
  ...accountRoutes,
];
