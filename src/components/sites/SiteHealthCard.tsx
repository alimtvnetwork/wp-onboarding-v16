import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { RefreshCw, Heart, AlertTriangle, XCircle, HelpCircle, Upload } from "lucide-react";
import { formatDistanceToNow } from "date-fns";
import type { SiteHealthSummary } from "@/types/siteHealth";

const statusConfig: Record<string, { label: string; variant: "default" | "secondary" | "destructive" | "outline"; icon: typeof Heart; dotClass: string }> = {
  healthy: { label: "Healthy", variant: "default", icon: Heart, dotClass: "bg-emerald-500" },
  degraded: { label: "Degraded", variant: "secondary", icon: AlertTriangle, dotClass: "bg-yellow-500" },
  down: { label: "Down", variant: "destructive", icon: XCircle, dotClass: "bg-destructive" },
  unknown: { label: "Unknown", variant: "outline", icon: HelpCircle, dotClass: "bg-muted-foreground" },
};

interface SiteHealthCardProps {
  site: SiteHealthSummary;
  onCheck: (siteId: number) => void;
  isChecking: boolean;
}

export function SiteHealthCard({ site, onCheck, isChecking }: SiteHealthCardProps) {
  const cfg = statusConfig[site.currentStatus] || statusConfig.unknown;
  const StatusIcon = cfg.icon;
  const isDown = site.currentStatus === "down";
  const consecutiveWarning = site.consecutiveDown >= 3;

  return (
    <Card className={consecutiveWarning ? "border-destructive/50" : undefined}>
      <CardContent className="p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-start gap-3 min-w-0 flex-1">
            {/* Live status indicator */}
            <div className="mt-1.5 shrink-0 relative">
              <span className={`block h-3 w-3 rounded-full ${cfg.dotClass}`} />
              {site.currentStatus === "healthy" && (
                <span className={`absolute inset-0 h-3 w-3 rounded-full ${cfg.dotClass} animate-ping opacity-50`} />
              )}
            </div>

            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="font-medium truncate">{site.siteName}</span>
                <Badge variant={cfg.variant} className="text-xs">{cfg.label}</Badge>
                {site.uploaderVersion && (
                  <Badge variant="outline" className="text-xs gap-1">
                    <Upload className="h-2.5 w-2.5" />
                    v{site.uploaderVersion}
                  </Badge>
                )}
                {consecutiveWarning && (
                  <Badge variant="destructive" className="text-xs">
                    {site.consecutiveDown}× consecutive failures
                  </Badge>
                )}
              </div>

              <div className="text-sm text-muted-foreground flex items-center gap-3 mt-1 flex-wrap">
                <span className="truncate max-w-[200px]">{site.siteUrl}</span>
                {site.avgResponseMs > 0 && (
                  <span className={site.avgResponseMs > 2000 ? "text-warning" : ""}>
                    {Math.round(site.avgResponseMs)}ms avg
                  </span>
                )}
                {site.uptimePercent > 0 && (
                  <span className={site.uptimePercent < 95 ? "text-warning" : ""}>
                    {site.uptimePercent.toFixed(1)}% uptime
                  </span>
                )}
                {site.lastCheckedAt && (
                  <span>Checked {formatDistanceToNow(new Date(site.lastCheckedAt), { addSuffix: true })}</span>
                )}
              </div>

              {isDown && site.lastError && (
                <p className="text-xs text-destructive mt-1.5 bg-destructive/5 rounded px-2 py-1">
                  {site.lastError}
                </p>
              )}
            </div>
          </div>

          <Button
            size="sm"
            variant="outline"
            onClick={() => onCheck(site.siteId)}
            disabled={isChecking}
            className="shrink-0"
          >
            <RefreshCw className={`h-3 w-3 mr-1 ${isChecking ? "animate-spin" : ""}`} />
            Check
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
