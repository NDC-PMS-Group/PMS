import {
  Briefcase,
  ListTodo,
  MonitorDot,
  Settings,
  MapPinned
} from "lucide-vue-next";
import { MenuItemType } from "@/app/layout/types";

export const menuItems: MenuItemType[] = [
  {
    title: "Dashboard",
    path: "/dashboard",
    icon: MonitorDot,
    roles: ["superadmin", "admin", "assistant"],
    guard: "dashboard.view",
  },
  {
    title: "Projects",
    path: "/projects",
    icon: Briefcase,
    roles: ["superadmin", "admin", "assistant"],
    guard: "projects.view",
  },
  {
    title: "Project Map",
    path: "/project-map",
    icon: MapPinned,
    roles: ["superadmin", "admin", "assistant"],
    guard: "project_map.view",
  },
  {
    title: "Tasks",
    path: "/tasks",
    icon: ListTodo,
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "dashboard.view",
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
