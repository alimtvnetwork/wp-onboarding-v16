import { useState } from "react";
import { Bell, X, CheckCheck, Trash2, CheckCircle2, XCircle, AlertTriangle, Info } from "lucide-react";
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

/** Lucide icon + accent color per type — clean, semantic */
const typeConfig: Record<NotificationType, { icon: typeof CheckCircle2; accent: string; iconClass: string }> = {
  success: {
    icon: CheckCircle2,
    accent: "border-l-emerald-500",
    iconClass: "text-emerald-500",
  },
  error: {
    icon: XCircle,
    accent: "border-l-red-500",
    iconClass: "text-red-500",
  },
  warning: {
    icon: AlertTriangle,
    accent: "border-l-amber-500",
    iconClass: "text-amber-500",
  },
  info: {
    icon: Info,
    accent: "border-l-sky-500",
    iconClass: "text-sky-500",
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
  const config = typeConfig[notification.type];
  const IconComponent = config.icon;

  return (
    <div
      className={cn(
        "group flex items-start gap-3 px-4 py-3 border-l-[3px] transition-colors cursor-pointer",
        "bg-card hover:bg-muted/50",
        config.accent,
        !notification.read && "bg-muted/30"
      )}
      onClick={() => onRead(notification.id)}
    >
      {/* Type icon */}
      <IconComponent className={cn("h-5 w-5 mt-0.5 shrink-0", config.iconClass)} />

      {/* Content */}
      <div className="flex-1 min-w-0 space-y-0.5">
        <p className={cn(
          "text-sm leading-snug text-foreground",
          !notification.read ? "font-semibold" : "font-medium"
        )}>
          {notification.title}
        </p>
        {notification.description && (
          <p className="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
            {notification.description}
          </p>
        )}
        <span className="text-[10px] text-muted-foreground/60">
          {formatTimeAgo(notification.timestamp)}
        </span>
      </div>

      {/* Close button — right side, always accessible */}
      <button
        className="shrink-0 mt-0.5 p-1 rounded-md opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-foreground hover:bg-muted"
        onClick={(e) => {
          e.stopPropagation();
          onDismiss(notification.id);
        }}
        aria-label="Dismiss"
      >
        <X className="h-3.5 w-3.5" />
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
        className="w-[380px] p-0 overflow-hidden bg-card border border-border shadow-lg z-50"
        sideOffset={8}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-border bg-card">
          <h3 className="text-sm font-semibold text-foreground tracking-tight">
            Notifications
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
          <div className="flex flex-col items-center justify-center py-10 px-4 text-center bg-card">
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
