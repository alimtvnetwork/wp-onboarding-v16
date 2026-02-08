import { Moon, Sun, Menu } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useTheme } from "@/components/theme-provider";
import { useLocation } from "react-router-dom";
import { VersionBadge } from "@/components/settings/VersionBadge";
import { WebSocketIndicator } from "@/components/shared/WebSocketIndicator";
import { GlobalPublishProgress } from "@/components/plugins/GlobalPublishProgress";
import { ErrorQueueBadge } from "@/components/errors/ErrorQueueBadge";
import { NotificationPanel } from "@/components/notifications/NotificationPanel";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const routeNames: Record<string, string> = {
  "/": "Dashboard",
  "/sites": "Sites",
  "/plugins": "Plugins",
  "/sync": "Sync",
  "/errors": "Errors",
  "/logs": "Logs",
  "/settings": "Settings",
  "/tests": "E2E Tests",
};

interface HeaderProps {
  onMenuToggle?: () => void;
}

export function Header({ onMenuToggle }: HeaderProps) {
  const { setTheme } = useTheme();
  const location = useLocation();
  const currentRoute = routeNames[location.pathname] || "Dashboard";

  return (
    <header className="h-14 border-b border-border bg-card flex items-center justify-between px-3 sm:px-6">
      <div className="flex items-center gap-2">
        {/* Mobile hamburger */}
        <Button
          variant="ghost"
          size="icon"
          className="md:hidden text-muted-foreground hover:text-foreground"
          onClick={onMenuToggle}
        >
          <Menu className="h-5 w-5" />
          <span className="sr-only">Toggle menu</span>
        </Button>

        <h1 className="text-lg sm:text-xl font-semibold text-foreground tracking-tight truncate">
          {currentRoute}
        </h1>
      </div>

      <div className="flex items-center gap-1.5 sm:gap-3">
        <NotificationPanel />
        <ErrorQueueBadge />
        <GlobalPublishProgress />

        {/* Only show WS toasts on pages that actively use WebSocket */}
        {(() => {
          const wsRoutes = ["/", "/logs", "/sites", "/sync", "/errors"];
          const showWsToasts = wsRoutes.includes(location.pathname);
          return (
            <>
              <span className="hidden sm:inline-flex">
                <WebSocketIndicator showLabel showToasts={showWsToasts} />
              </span>
              <span className="sm:hidden">
                <WebSocketIndicator showToasts={showWsToasts} />
              </span>
            </>
          );
        })()}

        <div className="hidden sm:block h-4 w-px bg-border" />
        <span className="hidden sm:inline-flex">
          <VersionBadge className="mr-1" />
        </span>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground hover:bg-muted">
              <Sun className="h-4 w-4 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
              <Moon className="absolute h-4 w-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
              <span className="sr-only">Toggle theme</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => setTheme("light")}>
              Light
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => setTheme("dark")}>
              Dark
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => setTheme("system")}>
              System
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}
