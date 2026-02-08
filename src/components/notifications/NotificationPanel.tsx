import { useState } from "react";
import { Bell, X, CheckCheck, Trash2, Info } from "lucide-react";
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

/** Emoji icons by notification type for visual appeal */
const typeEmoji: Record<NotificationType, string> = {
  success: "✅",
  error: "❌",
  warning: "⚠️",
  info: "ℹ️",
};

/** Card background styles by type */
const typeCardStyles: Record<NotificationType, string> = {
  success: "bg-emerald-800/80 border-emerald-600/40 text-white",
  error: "bg-destructive/80 border-destructive/40 text-white",
  warning: "bg-amber-700/80 border-amber-500/40 text-white",
  info: "bg-sky-800/80 border-sky-600/40 text-white",
};

const typeCardUnread: Record<NotificationType, string> = {
  success: "bg-emerald-800 border-emerald-500/60",
  error: "bg-destructive border-destructive/60",
  warning: "bg-amber-700 border-amber-400/60",
  info: "bg-sky-800 border-sky-500/60",
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
  const emoji = typeEmoji[notification.type];
  const cardStyle = notification.read
    ? typeCardStyles[notification.type]
    : typeCardUnread[notification.type];

  return (
    <div
      className={cn(
        "group relative flex items-start gap-3 px-4 py-3 mx-2 my-1.5 rounded-lg border transition-colors cursor-pointer",
        cardStyle
      )}
      onClick={() => onRead(notification.id)}
    >
      {/* Emoji icon */}
      <span className="text-lg mt-0.5 shrink-0 select-none" role="img">
        {emoji}
      </span>

      {/* Content */}
      <div className="flex-1 min-w-0 space-y-0.5">
        <p className={cn("text-sm leading-snug", !notification.read ? "font-semibold" : "font-medium opacity-90")}>
          {notification.title}
        </p>
        {notification.description && (
          <p className="text-xs opacity-75 line-clamp-2 leading-relaxed">
            {notification.description}
          </p>
        )}
      </div>

      {/* Timestamp + Close button — right side */}
      <div className="flex flex-col items-end gap-1 shrink-0">
        <button
          className="p-0.5 rounded hover:bg-white/20 transition-opacity"
          onClick={(e) => {
            e.stopPropagation();
            onDismiss(notification.id);
          }}
          aria-label="Dismiss"
        >
          <X className="h-3.5 w-3.5" />
        </button>
        <span className="text-[10px] opacity-60 whitespace-nowrap">
          {formatTimeAgo(notification.timestamp)}
        </span>
      </div>
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
            <div className="py-1">
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
