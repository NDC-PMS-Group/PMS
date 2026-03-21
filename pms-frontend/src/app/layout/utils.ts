import {
  Home,
  Inbox,
  ListTodo,
  Settings,
  FolderKanban,
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
    title: "Inbox",
    path: "/inbox",
    icon: Inbox,
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "dashboard.view",
  },
  {
    icon: FolderKanban,
    title: "Projects",
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "projects.view",
    subMenu: [
      {
        title: "All Projects",
        path: "/projects",
        guard: "projects.view",
      },
      {
        title: "Project Map",
        path: "/project-map",
        guard: "project_map.view",
      },
    ],
  },
  {
    icon: ListTodo,
    title: "My Tasks",
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "dashboard.view",
    subMenu: [
      {
        title: "All Tasks",
        path: "/tasks",
        guard: "dashboard.view",
      },
    ],
  },
  {
    icon: Settings,
    title: "Admin Tools",
    roles: ["superadmin", "admin"],
    guard: "admin_tools.view",
    subMenu: [
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
      {
        title: "System Settings",
        path: "/admin/system-settings",
        guard: "system_settings.view",
      },
      {
        path: "/admin/activity-logs",
        title: "Activity Logs",
        guard: "activity_logs.view",
      },
    ],
  },
];
