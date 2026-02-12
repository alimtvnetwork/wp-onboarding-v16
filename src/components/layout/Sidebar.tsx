import { NavLink } from "react-router-dom";
import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import { Sheet, SheetContent, SheetTitle } from "@/components/ui/sheet";
import { VisuallyHidden } from "@radix-ui/react-visually-hidden";
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
  Shield,
  BarChart3,
  HeartPulse,
  Radio,
  Activity,
} from "lucide-react";

const navItems = [
  { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { to: "/sites", label: "Sites", icon: Globe },
  { to: "/plugins", label: "Plugins", icon: Package },
  { to: "/plugins/core", label: "Core Uploader", icon: Shield },
  { to: "/site-health", label: "Site Health", icon: HeartPulse },
  { to: "/publish-history", label: "Publish History", icon: BarChart3 },
  { to: "/activity", label: "Activity Feed", icon: Activity },
  { to: "/tests", label: "E2E Tests", icon: FlaskConical },
  { to: "/logs", label: "Logs", icon: ScrollText },
  { to: "/sessions", label: "Sessions", icon: History },
  { to: "/request-sessions", label: "Request Log", icon: Radio },
  { to: "/api-explorer", label: "API Explorer", icon: Code2 },
  { to: "/errors", label: "Errors", icon: AlertCircle },
  { to: "/settings", label: "Settings", icon: Settings },
];

interface SidebarProps {
  mobileOpen?: boolean;
  onMobileClose?: () => void;
}

function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
  const { data: versionInfo, isLoading } = useVersionInfo();
  const versionLabel = isLoading ? "v…" : `v${versionInfo?.version || "0.0.0"}`;

  return (
    <>
      <div className="p-4 sm:p-6">
        <div className="flex items-center gap-2">
          <Plug className="h-6 w-6" style={{ color: "hsl(var(--sb-accent))" }} />
          <span className="font-bold text-lg" style={{ color: "hsl(var(--sb-text-hover))" }}>
            WP Publish
          </span>
        </div>
      </div>

      <nav className="px-3 sm:px-4 pb-4 flex-1 overflow-y-auto">
        <ul className="space-y-0.5">
          {navItems.map((item) => (
            <li key={item.to}>
              <NavLink
                to={item.to}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    "sb-nav-item flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium border-l-[3px] transition-colors duration-150",
                    isActive
                      ? "sb-nav-active"
                      : "sb-nav-idle"
                  )
                }
              >
                <item.icon className="h-4 w-4 shrink-0" />
                {item.label}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      <div className="px-3 sm:px-4 py-3" style={{ borderTop: "1px solid hsl(var(--sb-divider))" }}>
        <NavLink
          to="/settings#about"
          onClick={onNavigate}
          className={({ isActive }) =>
            cn(
              "sb-nav-item flex items-center justify-between px-3 py-2 rounded-md text-sm font-medium border-l-[3px] transition-colors duration-150",
              isActive
                ? "sb-nav-active"
                : "sb-nav-idle"
            )
          }
        >
          <span>About</span>
          <Badge variant="outline" className="font-mono text-xs border-current/30">
            {versionLabel}
          </Badge>
        </NavLink>
      </div>
    </>
  );
}

export function Sidebar({ mobileOpen = false, onMobileClose }: SidebarProps) {
  return (
    <>
      {/* Desktop sidebar — hidden on mobile */}
      <aside
        className="hidden md:flex w-64 flex-col shrink-0"
        style={{
          background: "hsl(var(--sb-bg))",
          borderRight: "1px solid hsl(var(--sb-divider))",
        }}
      >
        <SidebarContent />
      </aside>

      {/* Mobile sidebar — sheet overlay */}
      <Sheet open={mobileOpen} onOpenChange={(open) => !open && onMobileClose?.()}>
        <SheetContent
          side="left"
          className="w-64 p-0 flex flex-col"
          style={{ background: "hsl(var(--sb-bg))" }}
        >
          <VisuallyHidden>
            <SheetTitle>Navigation</SheetTitle>
          </VisuallyHidden>
          <SidebarContent onNavigate={onMobileClose} />
        </SheetContent>
      </Sheet>
    </>
  );
}
