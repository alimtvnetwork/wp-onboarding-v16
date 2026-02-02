import { Moon, Sun } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useTheme } from "@/components/theme-provider";
import { useLocation } from "react-router-dom";
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
  const currentRoute = routeNames[location.pathname] || "";

  return (
    <header className="h-14 border-b border-border bg-primary flex items-center justify-between px-6">
      <div className="flex items-center gap-2">
        <h1 className="text-lg font-bold text-primary-foreground">WP Plugin Publish</h1>
        {currentRoute && (
          <>
            <span className="text-primary-foreground/50">/</span>
            <span className="text-sm text-primary-foreground/80">{currentRoute}</span>
          </>
        )}
      </div>

      <div className="flex items-center gap-2">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="text-primary-foreground hover:bg-primary-foreground/10">
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
