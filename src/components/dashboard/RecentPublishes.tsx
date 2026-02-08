import { Link } from "react-router-dom";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Rocket, CheckCircle2, XCircle, AlertTriangle, ArrowRight } from "lucide-react";
import { formatDistanceToNow } from "date-fns";
import type { PublishHistoryEntry, PublishHistoryStats } from "@/lib/api";

interface RecentPublishesProps {
  entries: PublishHistoryEntry[];
  stats: PublishHistoryStats | null;
}

const statusConfig = {
  success: { icon: CheckCircle2, label: "Success", variant: "default" as const, className: "bg-emerald-500/10 text-emerald-600 border-emerald-500/20" },
  failed: { icon: XCircle, label: "Failed", variant: "destructive" as const, className: "" },
  partial: { icon: AlertTriangle, label: "Partial", variant: "outline" as const, className: "border-warning text-warning" },
};

export function RecentPublishes({ entries, stats }: RecentPublishesProps) {
  const successRate = stats && stats.totalPublishes > 0
    ? Math.round((stats.successCount / stats.totalPublishes) * 100)
    : null;

  return (
    <Card>
      <CardHeader className="p-4 sm:p-6 pb-2 sm:pb-4">
        <div className="flex items-center justify-between">
          <CardTitle className="text-base sm:text-lg flex items-center gap-2">
            <Rocket className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
            Recent Publishes
          </CardTitle>
          <Link to="/publish-history" className="text-xs text-primary hover:underline flex items-center gap-1">
            View all <ArrowRight className="h-3 w-3" />
          </Link>
        </div>
        {stats && (
          <div className="flex gap-3 text-xs text-muted-foreground mt-1">
            <span>{stats.totalPublishes} total</span>
            {successRate !== null && <span>• {successRate}% success</span>}
            {stats.avgDurationMs > 0 && (
              <span>• avg {(stats.avgDurationMs / 1000).toFixed(1)}s</span>
            )}
          </div>
        )}
      </CardHeader>
      <CardContent className="p-4 sm:p-6 pt-0">
        {entries.length > 0 ? (
          <div className="space-y-2">
            {entries.map((entry) => {
              const cfg = statusConfig[entry.status] || statusConfig.partial;
              const StatusIcon = cfg.icon;
              return (
                <div
                  key={entry.id}
                  className="flex items-center gap-2 sm:gap-3 p-2 rounded-lg border-l-2 border-l-transparent transition-colors hover:bg-secondary/50 hover:border-l-primary/60"
                >
                  <StatusIcon className={`h-4 w-4 shrink-0 ${
                    entry.status === "success" ? "text-emerald-600" :
                    entry.status === "failed" ? "text-destructive" :
                    "text-warning"
                  }`} />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">
                      {entry.pluginName}
                      <span className="text-muted-foreground font-normal"> → {entry.siteName}</span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {entry.filesUpdated} files • {(entry.durationMs / 1000).toFixed(1)}s
                    </p>
                  </div>
                  <span className="text-xs text-muted-foreground shrink-0">
                    {formatDistanceToNow(new Date(entry.createdAt), { addSuffix: true })}
                  </span>
                </div>
              );
            })}
          </div>
        ) : (
          <p className="text-xs sm:text-sm text-muted-foreground">
            No publishes yet. Deploy a plugin to see history here.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
