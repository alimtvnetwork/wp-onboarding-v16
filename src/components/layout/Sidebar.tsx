import { NavLink } from "react-router-dom";
import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import {
  LayoutDashboard,
  Globe,
  Package,
  Settings,
  AlertCircle,
  Plug,
  ScrollText,
  FlaskConical,
  History,
  Code2,
} from "lucide-react";

const navItems = [
  { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { to: "/sites", label: "Sites", icon: Globe },
  { to: "/plugins", label: "Plugins", icon: Package },
  { to: "/tests", label: "E2E Tests", icon: FlaskConical },
  { to: "/logs", label: "Logs", icon: ScrollText },
  { to: "/sessions", label: "Sessions", icon: History },
  { to: "/api-explorer", label: "API Explorer", icon: Code2 },
  { to: "/errors", label: "Errors", icon: AlertCircle },
  { to: "/settings", label: "Settings", icon: Settings },
];

export function Sidebar() {
  const { data: versionInfo, isLoading } = useVersionInfo();
  const versionLabel = isLoading ? "v…" : `v${versionInfo?.version || "0.0.0"}`;

  return (
    <aside className="w-64 border-r border-border bg-card flex flex-col">
      <div className="p-6">
        <div className="flex items-center gap-2">
          <Plug className="h-6 w-6 text-primary" />
          <span className="font-bold text-lg">WP Plugin Publish</span>
        </div>
      </div>

      <nav className="px-4 pb-4 flex-1">
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

      {/* Footer: version link to Settings → About */}
      <div className="px-4 py-3 border-t border-border">
        <NavLink
          to="/settings#about"
          className={({ isActive }) =>
            cn(
              "flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium transition-colors",
              isActive
                ? "bg-muted text-foreground"
                : "text-muted-foreground hover:text-foreground hover:bg-muted"
            )
          }
        >
          <span>About</span>
          <Badge variant="outline" className="font-mono text-xs">
            {versionLabel}
          </Badge>
        </NavLink>
      </div>
    </aside>
  );
}
