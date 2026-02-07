import { useEffect, useState, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { Command } from "cmdk";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import { VisuallyHidden } from "@radix-ui/react-visually-hidden";
import {
  LayoutDashboard,
  Globe,
  Package,
  HeartPulse,
  BarChart3,
  FlaskConical,
  ScrollText,
  History,
  Code2,
  AlertCircle,
  Settings,
  Moon,
  Sun,
  Monitor,
} from "lucide-react";
import { useTheme } from "@/components/theme-provider";

interface PaletteItem {
  id: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  action: () => void;
  group: string;
  keywords?: string[];
}

export function CommandPalette() {
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();
  const { setTheme } = useTheme();

  // ⌘K / Ctrl+K toggle
  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === "k" && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        setOpen((prev) => !prev);
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, []);

  const go = useCallback(
    (path: string) => {
      navigate(path);
      setOpen(false);
    },
    [navigate]
  );

  const items: PaletteItem[] = [
    // Navigation
    { id: "dashboard", label: "Dashboard", icon: LayoutDashboard, action: () => go("/dashboard"), group: "Navigation", keywords: ["home", "overview"] },
    { id: "sites", label: "Sites", icon: Globe, action: () => go("/sites"), group: "Navigation", keywords: ["wordpress"] },
    { id: "plugins", label: "Plugins", icon: Package, action: () => go("/plugins"), group: "Navigation", keywords: ["extensions"] },
    { id: "site-health", label: "Site Health", icon: HeartPulse, action: () => go("/site-health"), group: "Navigation", keywords: ["uptime", "status"] },
    { id: "publish-history", label: "Publish History", icon: BarChart3, action: () => go("/publish-history"), group: "Navigation", keywords: ["deploy"] },
    { id: "tests", label: "E2E Tests", icon: FlaskConical, action: () => go("/tests"), group: "Navigation", keywords: ["testing"] },
    { id: "logs", label: "Logs", icon: ScrollText, action: () => go("/logs"), group: "Navigation" },
    { id: "sessions", label: "Sessions", icon: History, action: () => go("/sessions"), group: "Navigation" },
    { id: "api-explorer", label: "API Explorer", icon: Code2, action: () => go("/api-explorer"), group: "Navigation", keywords: ["swagger", "rest"] },
    { id: "errors", label: "Errors", icon: AlertCircle, action: () => go("/errors"), group: "Navigation" },
    { id: "settings", label: "Settings", icon: Settings, action: () => go("/settings"), group: "Navigation", keywords: ["config", "preferences"] },
    // Actions
    { id: "theme-light", label: "Switch to Light theme", icon: Sun, action: () => { setTheme("light"); setOpen(false); }, group: "Actions", keywords: ["theme", "appearance"] },
    { id: "theme-dark", label: "Switch to Dark theme", icon: Moon, action: () => { setTheme("dark"); setOpen(false); }, group: "Actions", keywords: ["theme", "appearance"] },
    { id: "theme-system", label: "Use System theme", icon: Monitor, action: () => { setTheme("system"); setOpen(false); }, group: "Actions", keywords: ["theme", "appearance"] },
  ];

  const groups = [...new Set(items.map((i) => i.group))];

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent className="p-0 gap-0 max-w-lg overflow-hidden rounded-xl border-border shadow-lg [&>button]:hidden">
        <VisuallyHidden>
          <DialogTitle>Command Palette</DialogTitle>
        </VisuallyHidden>
        <Command className="bg-popover text-popover-foreground" loop>
          <div className="flex items-center border-b border-border px-3">
            <Command.Input
              placeholder="Type a command or search…"
              className="flex-1 h-12 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            />
            <kbd className="hidden sm:inline-flex h-5 items-center gap-1 rounded border border-border bg-muted px-1.5 font-mono text-[10px] text-muted-foreground">
              ESC
            </kbd>
          </div>

          <Command.List className="max-h-72 overflow-y-auto p-2">
            <Command.Empty className="py-6 text-center text-sm text-muted-foreground">
              No results found.
            </Command.Empty>

            {groups.map((group) => (
              <Command.Group key={group} heading={group} className="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground">
                {items
                  .filter((i) => i.group === group)
                  .map((item) => (
                    <Command.Item
                      key={item.id}
                      value={[item.label, ...(item.keywords ?? [])].join(" ")}
                      onSelect={item.action}
                      className="flex items-center gap-3 px-2 py-2 rounded-md text-sm cursor-pointer aria-selected:bg-accent aria-selected:text-accent-foreground"
                    >
                      <item.icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                      {item.label}
                    </Command.Item>
                  ))}
              </Command.Group>
            ))}
          </Command.List>
        </Command>
      </DialogContent>
    </Dialog>
  );
}
