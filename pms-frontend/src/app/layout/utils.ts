import {
  Home,
  Bell,
  ListTodo,
  Settings,
  FolderKanban,
  Activity,
  Map,
  Users,
  ClipboardList,
} from "lucide-vue-next";
import { MenuItemType } from "@/app/layout/types";

export const menuItems: MenuItemType[] = [
  {
    title: "Dashboard",
    path: "/dashboard",
    icon: Home,
    roles: ["superadmin", "admin", "assistant"],
    guard: "dashboard.view",
  },
  {
    title: "Workspace",
    isHeader: true,
    guard: "projects.view",
  },
  {
    title: "Projects",
    path: "/projects",
    icon: FolderKanban,
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "projects.view",
  },
  {
    title: "Project Map",
    path: "/project-map",
    icon: Map,
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "project_map.view",
  },
  {
    icon: ListTodo,
    title: "Implementation Tasks",
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "tasks.view",
    subMenu: [
      {
        title: "All Implementation Tasks",
        path: "/tasks",
        guard: "tasks.view",
      },
    ],
  },
  {
    title: "Notifications",
    path: "/notifications",
    icon: Bell,
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "dashboard.view",
  },
  {
    title: "Project Lifecycle",
    isHeader: true,
    guard: "admin_tools.view",
  },
  {
    icon: Activity,
    title: "Lifecycle Operations",
    roles: ["superadmin", "admin"],
    guard: "admin_tools.view",
    subMenu: [
      {
        title: "Implementation Monitoring",
        path: "/admin/post-monitoring",
        guard: "admin_tools.view",
      },
      {
        title: "Exit Management",
        path: "/admin/divestment",
        guard: "admin_tools.view",
      },
      {
        title: "Saved & Export Reports",
        path: "/admin/reports",
        guard: "admin_tools.view",
      },
    ],
  },
  {
    title: "Administration",
    isHeader: true,
    guard: "admin_tools.view",
  },
  {
    icon: Users,
    title: "People & Access",
    roles: ["superadmin", "admin"],
    guard: "admin_tools.view",
    subMenu: [
      {
        title: "Pending Accounts",
        path: "/admin/pending-accounts",
        guard: "organization.view"
      },
      {
        title: "Organization",
        path: "/admin/organization",
        guard: "organization.view"
      },
      {
        title: "Access Settings",
        path: "/admin/access-settings",
        guard: "access_settings.view",
      },
    ],
  },
  {
    title: "System",
    isHeader: true,
    guard: "system_settings.view",
  },
  {
    icon: Settings,
    title: "System Settings",
    path: "/admin/system-settings",
    roles: ["superadmin", "admin"],
    guard: "system_settings.view",
  },
  {
    icon: Bell,
    title: "Notification Management",
    path: "/admin/notification-rules",
    roles: ["superadmin", "admin"],
    guard: "system_settings.view",
  },
  {
    icon: ClipboardList,
    title: "Audit",
    roles: ["superadmin", "admin"],
    guard: "activity_logs.view",
    subMenu: [
      {
        path: "/admin/activity-logs",
        title: "Activity Logs",
        guard: "activity_logs.view",
      },
    ],
  },
];
