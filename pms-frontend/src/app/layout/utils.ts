import {
  MapPinned,
  Home,
  Calendar,
  Layers,
  Inbox,
  UserCheck,
  ListTodo,
  Settings
} from "lucide-vue-next";
import { MenuItemType } from "@/app/layout/types";

export const menuItems: MenuItemType[] = [
  {
    title: "Home",
    isHeader: true,
    guard: "dashboard.view",
  },
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
    title: "My Tasks",
    isHeader: true,
    guard: "dashboard.view",
  },
  {
    title: "Assigned to me",
    path: "/tasks?assigned_to=me",
    icon: UserCheck,
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "dashboard.view",
  },
  {
    title: "Today & Overdue",
    path: "/tasks?overdue=true",
    icon: Calendar,
    roles: ["superadmin", "admin", "assistant", "employee"],
    guard: "dashboard.view",
  },
  {
    title: "Spaces",
    isHeader: true,
    guard: "projects.view",
  },
  {
    title: "All Projects",
    path: "/projects",
    icon: Layers,
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
    title: "All Tasks",
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
