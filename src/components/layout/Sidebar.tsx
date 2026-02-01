import { NavLink } from "react-router-dom";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  Globe,
  Package,
  RefreshCw,
  Settings,
  AlertCircle,
  Plug,
  ScrollText,
} from "lucide-react";

const navItems = [
  { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { to: "/sites", label: "Sites", icon: Globe },
  { to: "/plugins", label: "Plugins", icon: Package },
  { to: "/sync", label: "Sync", icon: RefreshCw },
  { to: "/logs", label: "Logs", icon: ScrollText },
  { to: "/errors", label: "Errors", icon: AlertCircle },
  { to: "/settings", label: "Settings", icon: Settings },
];

export function Sidebar() {
  return (
    <aside className="w-64 border-r border-border bg-card">
      <div className="p-6">
        <div className="flex items-center gap-2">
          <Plug className="h-6 w-6 text-primary" />
          <span className="font-bold text-lg">WP Plugin Publish</span>
        </div>
      </div>

      <nav className="px-4 pb-4">
        <ul className="space-y-1">
          {navItems.map((item) => (
            <li key={item.to}>
              <NavLink
                to={item.to}
                className={({ isActive }) =>
                  cn(
                    "flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors",
                    isActive
                      ? "bg-primary text-primary-foreground"
                      : "text-muted-foreground hover:text-foreground hover:bg-muted"
                  )
                }
              >
                <item.icon className="h-4 w-4" />
                {item.label}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>
    </aside>
  );
}
