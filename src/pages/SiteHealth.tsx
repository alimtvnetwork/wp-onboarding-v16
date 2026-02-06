import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { RefreshCw, Heart, AlertTriangle, XCircle, HelpCircle, Activity } from "lucide-react";
import { toast } from "sonner";
import { formatDistanceToNow } from "date-fns";

interface SiteHealthSummary {
  siteId: number;
  siteName: string;
  siteUrl: string;
  currentStatus: string;
  lastCheckedAt?: string;
  avgResponseMs: number;
  uptimePercent: number;
  totalChecks: number;
  healthyChecks: number;
  downChecks: number;
  lastErrorAt?: string;
  lastError?: string;
  consecutiveDown: number;
}

interface SiteHealthStats {
  totalSites: number;
  healthySites: number;
  degradedSites: number;
  downSites: number;
  unknownSites: number;
  avgResponseMs: number;
  avgUptime: number;
}

const statusConfig: Record<string, { label: string; variant: "default" | "secondary" | "destructive" | "outline"; icon: typeof Heart }> = {
  healthy: { label: "Healthy", variant: "default", icon: Heart },
  degraded: { label: "Degraded", variant: "secondary", icon: AlertTriangle },
  down: { label: "Down", variant: "destructive", icon: XCircle },
  unknown: { label: "Unknown", variant: "outline", icon: HelpCircle },
};

export default function SiteHealth() {
  const queryClient = useQueryClient();

  const { data: summariesResp, isLoading: loadingSummaries } = useQuery({
    queryKey: ["site-health-summaries"],
    queryFn: () => api.getSiteHealthSummaries(),
  });

  const { data: statsResp, isLoading: loadingStats } = useQuery({
    queryKey: ["site-health-stats"],
    queryFn: () => api.getSiteHealthStats(),
  });

  const checkAllMutation = useMutation({
    mutationFn: () => api.checkAllSitesHealth(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["site-health-summaries"] });
      queryClient.invalidateQueries({ queryKey: ["site-health-stats"] });
      toast.success("Health checks completed");
    },
  });

  const checkSiteMutation = useMutation({
    mutationFn: (siteId: number) => api.checkSiteHealth(siteId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["site-health-summaries"] });
      queryClient.invalidateQueries({ queryKey: ["site-health-stats"] });
    },
  });

  const healthStats = (statsResp as any)?.data as SiteHealthStats | undefined;
  const healthSummaries = ((summariesResp as any)?.data as SiteHealthSummary[] | undefined) || [];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Site Health Monitor</h1>
          <p className="text-muted-foreground">Monitor the health and uptime of your WordPress sites</p>
        </div>
        <Button
          onClick={() => checkAllMutation.mutate()}
          disabled={checkAllMutation.isPending}
        >
          <RefreshCw className={`h-4 w-4 mr-2 ${checkAllMutation.isPending ? "animate-spin" : ""}`} />
          Check All Sites
        </Button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {loadingStats ? (
          Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}><CardContent className="p-6"><Skeleton className="h-8 w-16" /></CardContent></Card>
          ))
        ) : (
          <>
            <Card>
              <CardContent className="p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-sm mb-1">
                  <Heart className="h-4 w-4 text-green-500" /> Healthy
                </div>
                <p className="text-2xl font-bold">{healthStats?.healthySites ?? 0}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-sm mb-1">
                  <AlertTriangle className="h-4 w-4 text-yellow-500" /> Degraded
                </div>
                <p className="text-2xl font-bold">{healthStats?.degradedSites ?? 0}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-sm mb-1">
                  <XCircle className="h-4 w-4 text-destructive" /> Down
                </div>
                <p className="text-2xl font-bold">{healthStats?.downSites ?? 0}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-sm mb-1">
                  <Activity className="h-4 w-4" /> Avg Response
                </div>
                <p className="text-2xl font-bold">{healthStats?.avgResponseMs ? `${Math.round(healthStats.avgResponseMs)}ms` : "—"}</p>
              </CardContent>
            </Card>
          </>
        )}
      </div>

      {/* Site Cards */}
      <div className="space-y-3">
        {loadingSummaries ? (
          Array.from({ length: 3 }).map((_, i) => (
            <Card key={i}><CardContent className="p-6"><Skeleton className="h-16 w-full" /></CardContent></Card>
          ))
        ) : healthSummaries.length === 0 ? (
          <Card>
            <CardContent className="p-12 text-center text-muted-foreground">
              <Activity className="h-12 w-12 mx-auto mb-4 opacity-30" />
              <p className="text-lg font-medium">No health data yet</p>
              <p className="text-sm mt-1">Click "Check All Sites" to run your first health check</p>
            </CardContent>
          </Card>
        ) : (
          healthSummaries.map((site) => {
            const cfg = statusConfig[site.currentStatus] || statusConfig.unknown;
            const StatusIcon = cfg.icon;
            return (
              <Card key={site.siteId}>
                <CardContent className="p-4 flex items-center justify-between">
                  <div className="flex items-center gap-4 min-w-0">
                    <StatusIcon className={`h-5 w-5 flex-shrink-0 ${
                      site.currentStatus === "healthy" ? "text-green-500" :
                      site.currentStatus === "degraded" ? "text-yellow-500" :
                      site.currentStatus === "down" ? "text-destructive" : "text-muted-foreground"
                    }`} />
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="font-medium truncate">{site.siteName}</span>
                        <Badge variant={cfg.variant}>{cfg.label}</Badge>
                      </div>
                      <div className="text-sm text-muted-foreground flex items-center gap-3 mt-0.5">
                        <span className="truncate">{site.siteUrl}</span>
                        {site.avgResponseMs > 0 && <span>{Math.round(site.avgResponseMs)}ms avg</span>}
                        {site.uptimePercent > 0 && <span>{site.uptimePercent.toFixed(1)}% uptime</span>}
                        {site.lastCheckedAt && (
                          <span>Checked {formatDistanceToNow(new Date(site.lastCheckedAt), { addSuffix: true })}</span>
                        )}
                      </div>
                      {site.lastError && site.currentStatus === "down" && (
                        <p className="text-xs text-destructive mt-1">{site.lastError}</p>
                      )}
                    </div>
                  </div>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => checkSiteMutation.mutate(site.siteId)}
                    disabled={checkSiteMutation.isPending}
                  >
                    <RefreshCw className={`h-3 w-3 mr-1 ${checkSiteMutation.isPending ? "animate-spin" : ""}`} />
                    Check
                  </Button>
                </CardContent>
              </Card>
            );
          })
        )}
      </div>
    </div>
  );
}
