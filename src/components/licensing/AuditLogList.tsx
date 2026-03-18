// Audit log list — used both standalone and inside the detail panel.

import { format } from "date-fns";
import { Badge } from "@/components/ui/badge";
import type { AuditLog, AuditAction } from "@/types/licensing";

const actionColors: Record<AuditAction, string> = {
  created: "bg-success/10 text-success border-success/20",
  activated: "bg-info/10 text-info border-info/20",
  deactivated: "bg-muted text-muted-foreground",
  validated: "bg-primary/10 text-primary border-primary/20",
  expired: "bg-warning/10 text-warning border-warning/20",
  revoked: "bg-destructive/10 text-destructive border-destructive/20",
  updated: "bg-info/10 text-info border-info/20",
  deleted: "bg-destructive/10 text-destructive border-destructive/20",
};

interface Props {
  logs: AuditLog[];
  compact?: boolean;
}

export function AuditLogList({ logs, compact = false }: Props) {
  const isEmpty = logs.length === 0;

  if (isEmpty) {
    return (
      <p className="text-sm text-muted-foreground text-center py-4">
        No audit entries.
      </p>
    );
  }

  return (
    <div className="space-y-2">
      {logs.map((log) => (
        <div
          key={log.id}
          className={`flex items-center gap-3 rounded-md border border-border px-3 ${
            compact ? "py-2" : "py-3"
          }`}
        >
          <Badge
            variant="outline"
            className={`capitalize text-xs shrink-0 ${actionColors[log.action] ?? ""}`}
          >
            {log.action}
          </Badge>

          <div className="flex-1 min-w-0">
            {log.domain && (
              <span className="text-xs text-muted-foreground font-mono truncate block">
                {log.domain}
              </span>
            )}
            {log.ip_address && !compact && (
              <span className="text-xs text-muted-foreground">
                IP: {log.ip_address}
              </span>
            )}
          </div>

          <div className="text-xs text-muted-foreground whitespace-nowrap shrink-0">
            {format(new Date(log.created_at), compact ? "MMM d HH:mm" : "MMM d, yyyy HH:mm:ss")}
          </div>

          {!compact && log.license_id && (
            <span className="text-xs font-mono text-muted-foreground">
              #{log.license_id}
            </span>
          )}
        </div>
      ))}
    </div>
  );
}
