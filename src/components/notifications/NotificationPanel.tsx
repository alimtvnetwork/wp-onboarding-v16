import { useState } from "react";
import { Bell, X, CheckCheck, Trash2, Rocket, GitBranch, Globe, AlertCircle, Info, RefreshCw, FlaskConical, Plug } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import { useNotificationStore, type AppNotification, type NotificationType } from "@/stores/notificationStore";

const sourceIcons: Record<string, typeof Rocket> = {
  publish: Rocket,
  "auto-publish": Rocket,
  sync: RefreshCw,
  git: GitBranch,
  connection: Globe,
  "remote-plugin": Plug,
  e2e: FlaskConical,
  error: AlertCircle,
};

const typeStyles: Record<NotificationType, { dot: string; iconBg: string; icon: string }> = {
  success: {
    dot: "bg-success",
    iconBg: "bg-success/15",
    icon: "text-success",
  },
  error: {
    dot: "bg-destructive",
    iconBg: "bg-destructive/15",
    icon: "text-destructive",
  },
  warning: {
    dot: "bg-warning",
    iconBg: "bg-warning/15",
    icon: "text-warning",
  },
  info: {
    dot: "bg-info",
    iconBg: "bg-info/15",
    icon: "text-info",
  },
};

function formatTimeAgo(timestamp: string): string {
  const now = Date.now();
  const then = new Date(timestamp).getTime();
  const diffMs = now - then;
  const diffMin = Math.floor(diffMs / 60000);
  if (diffMin < 1) return "just now";
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHrs = Math.floor(diffMin / 60);
  if (diffHrs < 24) return `${diffHrs}h ago`;
  const diffDays = Math.floor(diffHrs / 24);
  return `${diffDays}d ago`;
}

function NotificationCard({ notification, onDismiss, onRead }: {
  notification: AppNotification;
  onDismiss: (id: string) => void;
  onRead: (id: string) => void;
}) {
  const style = typeStyles[notification.type];
  const IconComponent = sourceIcons[notification.source] || Info;

  return (
    <div
      className={cn(
        "group relative flex items-start gap-3 px-4 py-3 transition-colors cursor-pointer",
        "hover:bg-accent/50",
        !notification.read && "bg-accent/30"
      )}
      onClick={() => onRead(notification.id)}
    >
      {/* Icon */}
      <div className={cn("mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full", style.iconBg)}>
        <IconComponent className={cn("h-4 w-4", style.icon)} />
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0 space-y-0.5">
        <div className="flex items-start justify-between gap-2">
          <p className={cn("text-sm leading-snug", !notification.read ? "font-semibold text-foreground" : "font-medium text-foreground/80")}>
            {notification.title}
          </p>
          <span className="shrink-0 text-[11px] text-muted-foreground whitespace-nowrap mt-0.5">
            {formatTimeAgo(notification.timestamp)}
          </span>
        </div>
        {notification.description && (
          <p className="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
            {notification.description}
          </p>
        )}
      </div>

      {/* Unread dot */}
      {!notification.read && (
        <div className={cn("absolute left-1.5 top-1/2 -translate-y-1/2 h-2 w-2 rounded-full", style.dot)} />
      )}

      {/* Dismiss */}
      <button
        className="opacity-0 group-hover:opacity-100 transition-opacity shrink-0 mt-0.5 p-0.5 rounded hover:bg-muted"
        onClick={(e) => {
          e.stopPropagation();
          onDismiss(notification.id);
        }}
      >
        <X className="h-3.5 w-3.5 text-muted-foreground" />
      </button>
    </div>
  );
}

export function NotificationPanel() {
  const [open, setOpen] = useState(false);
  const { notifications, markAllRead, markRead, dismiss, clearAll, unreadCount } = useNotificationStore();
  const count = unreadCount();

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative text-muted-foreground hover:text-foreground hover:bg-muted">
          <Bell className="h-4 w-4" />
          {count > 0 && (
            <Badge className="absolute -top-1 -right-1 h-4 min-w-4 px-1 text-[10px] font-bold bg-destructive text-destructive-foreground border-0 flex items-center justify-center">
              {count > 99 ? "99+" : count}
            </Badge>
          )}
          <span className="sr-only">Notifications</span>
        </Button>
      </PopoverTrigger>

      <PopoverContent
        align="end"
        className="w-[380px] p-0 overflow-hidden"
        sideOffset={8}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <h3 className="text-sm font-semibold text-foreground tracking-tight">
            NOTIFICATIONS
          </h3>
          <div className="flex items-center gap-1">
            {count > 0 && (
              <Button variant="ghost" size="sm" className="h-7 text-xs text-muted-foreground hover:text-foreground" onClick={markAllRead}>
                <CheckCheck className="h-3.5 w-3.5 mr-1" />
                Mark all read
              </Button>
            )}
            {notifications.length > 0 && (
              <Button variant="ghost" size="sm" className="h-7 text-xs text-muted-foreground hover:text-foreground" onClick={clearAll}>
                <Trash2 className="h-3.5 w-3.5 mr-1" />
                Clear
              </Button>
            )}
          </div>
        </div>

        {/* Notification list */}
        {notifications.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-10 px-4 text-center">
            <Bell className="h-8 w-8 text-muted-foreground/40 mb-2" />
            <p className="text-sm text-muted-foreground">No notifications yet</p>
            <p className="text-xs text-muted-foreground/60 mt-1">
              Activity from publishes, syncs, and other operations will appear here
            </p>
          </div>
        ) : (
          <ScrollArea className="max-h-[400px]">
            <div className="divide-y divide-border/50">
              {notifications.map((notification) => (
                <NotificationCard
                  key={notification.id}
                  notification={notification}
                  onDismiss={dismiss}
                  onRead={markRead}
                />
              ))}
            </div>
          </ScrollArea>
        )}
      </PopoverContent>
    </Popover>
  );
}
