import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { RefreshCw, Heart, AlertTriangle, XCircle, Activity } from "lucide-react";
import { SiteHealthCard } from "@/components/sites/SiteHealthCard";
import {
  useSiteHealthSummaries,
  useSiteHealthStats,
  useCheckAllSitesHealth,
  useCheckSiteHealth,
} from "@/hooks/useSiteHealth";

export default function SiteHealth() {
  const { data: summaries = [], isLoading: loadingSummaries } = useSiteHealthSummaries();
  const { data: stats, isLoading: loadingStats } = useSiteHealthStats();
  const checkAll = useCheckAllSitesHealth();
  const checkSite = useCheckSiteHealth();

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold">Site Health Monitor</h1>
          <p className="text-sm text-muted-foreground">
            Live health and uptime monitoring for your WordPress sites
          </p>
        </div>
        <Button
          onClick={() => checkAll.mutate()}
          disabled={checkAll.isPending}
        >
          <RefreshCw className={`h-4 w-4 mr-2 ${checkAll.isPending ? "animate-spin" : ""}`} />
          Re-test All Sites
        </Button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4">
        {loadingStats ? (
          Array.from({ length: 5 }).map((_, i) => (
            <Card key={i}><CardContent className="p-4 sm:p-6"><Skeleton className="h-8 w-16" /></CardContent></Card>
          ))
        ) : (
          <>
            <Card>
              <CardContent className="p-4 sm:p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-xs sm:text-sm mb-1">
                  <Heart className="h-4 w-4 text-emerald-500" /> Healthy
                </div>
                <p className="text-xl sm:text-2xl font-bold">{stats?.healthySites ?? 0}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4 sm:p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-xs sm:text-sm mb-1">
                  <AlertTriangle className="h-4 w-4 text-yellow-500" /> Degraded
                </div>
                <p className="text-xl sm:text-2xl font-bold">{stats?.degradedSites ?? 0}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4 sm:p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-xs sm:text-sm mb-1">
                  <XCircle className="h-4 w-4 text-destructive" /> Down
                </div>
                <p className="text-xl sm:text-2xl font-bold">{stats?.downSites ?? 0}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4 sm:p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-xs sm:text-sm mb-1">
                  <Activity className="h-4 w-4" /> Avg Response
                </div>
                <p className="text-xl sm:text-2xl font-bold">
                  {stats?.avgResponseMs ? `${Math.round(stats.avgResponseMs)}ms` : "—"}
                </p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4 sm:p-6">
                <div className="flex items-center gap-2 text-muted-foreground text-xs sm:text-sm mb-1">
                  <Activity className="h-4 w-4" /> Avg Uptime
                </div>
                <p className="text-xl sm:text-2xl font-bold">
                  {stats?.avgUptime ? `${stats.avgUptime.toFixed(1)}%` : "—"}
                </p>
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
        ) : summaries.length === 0 ? (
          <Card>
            <CardContent className="p-12 text-center text-muted-foreground">
              <Activity className="h-12 w-12 mx-auto mb-4 opacity-30" />
              <p className="text-lg font-medium">No health data yet</p>
              <p className="text-sm mt-1">Click "Re-test All Sites" to run your first health check</p>
            </CardContent>
          </Card>
        ) : (
          summaries.map((site) => (
            <SiteHealthCard
              key={site.siteId}
              site={site}
              onCheck={(id) => checkSite.mutate(id)}
              isChecking={checkSite.isPending}
            />
          ))
        )}
      </div>
    </div>
  );
}
