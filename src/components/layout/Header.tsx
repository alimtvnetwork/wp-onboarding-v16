import { Moon, Sun } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useTheme } from "@/components/theme-provider";
import { useLocation } from "react-router-dom";
import { VersionBadge } from "@/components/settings/VersionBadge";
import { WebSocketIndicator } from "@/components/shared/WebSocketIndicator";
import { GlobalPublishProgress } from "@/components/plugins/GlobalPublishProgress";
import { ErrorQueueBadge } from "@/components/errors/ErrorQueueBadge";
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

export function Header() {
  const { setTheme } = useTheme();
  const location = useLocation();
  const currentRoute = routeNames[location.pathname] || "Dashboard";

  return (
    <header className="h-14 border-b border-border bg-card flex items-center justify-between px-6">
      <h1 className="text-xl font-semibold text-foreground tracking-tight">
        {currentRoute}
      </h1>

      <div className="flex items-center gap-3">
        {/* Error queue badge */}
        <ErrorQueueBadge />
        
        {/* Global publish progress indicator */}
        <GlobalPublishProgress />
        
        {/* WebSocket connection indicator */}
        <WebSocketIndicator showLabel />
        
        <div className="h-4 w-px bg-border" />
        
        <VersionBadge className="mr-1" />
        
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
