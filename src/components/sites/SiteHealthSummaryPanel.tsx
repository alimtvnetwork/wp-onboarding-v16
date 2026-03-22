import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import {
  Loader2,
  RefreshCw,
  Cpu,
  Database,
  HardDrive,
  Users,
  Puzzle,
  Shield,
  CheckCircle,
  XCircle,
  Camera,
  Archive,
} from "lucide-react";
import { api, Site } from "@/lib/api";
import type { SiteHealthSummaryResponse } from "@/lib/api";

interface SiteHealthSummaryPanelProps {
  site: Site;
  open: boolean;
}

export function SiteHealthSummaryPanel({ site, open }: SiteHealthSummaryPanelProps) {
  const queryKey = ["sites", site.id, "site-health-summary"];

  const { data: health, isLoading, error, refetch, isFetching } = useQuery({
    queryKey,
    queryFn: async () => {
      const response = await api.getRemoteSiteHealthSummary(site.id);
      if (!response.success) {
        throw new Error(response.error?.message || "Failed to load health summary");
      }
      return response.data as SiteHealthSummaryResponse;
    },
    enabled: open,
    retry: 1,
    staleTime: 60_000,
    meta: { suppressGlobalError: true },
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        <span className="ml-2 text-sm text-muted-foreground">Loading health summary...</span>
      </div>
    );
  }

  if (!health) {
    const errMsg = error instanceof Error ? error.message : "Could not load health summary";
    return (
      <div className="text-center py-8 space-y-3">
        <p className="text-sm text-muted-foreground">{errMsg}</p>
        <p className="text-xs text-muted-foreground">The remote plugin may need to be updated to v2.31.0+.</p>
        <Button size="sm" variant="outline" onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw className={`h-3 w-3 mr-1 ${isFetching ? "animate-spin" : ""}`} />
          Retry
        </Button>
      </div>
    );
  }

  const diskPercent = health.system.diskTotalBytes > 0
    ? Math.round(((health.system.diskTotalBytes - health.system.diskFreeBytes) / health.system.diskTotalBytes) * 100)
    : 0;

  return (
    <ScrollArea className="h-full">
      <div className="space-y-3 p-1">
        {/* Refresh */}
        <div className="flex justify-end">
          <Button
            size="sm"
            variant="outline"
            onClick={() => refetch()}
            disabled={isFetching}
          >
            <RefreshCw className={`h-3 w-3 mr-1 ${isFetching ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>

        {/* System */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Cpu className="h-4 w-4" />
              System
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="grid grid-cols-2 gap-1.5 text-xs">
              <div className="text-muted-foreground">PHP</div>
              <div className="font-mono">{health.system.phpVersion}</div>
              <div className="text-muted-foreground">WordPress</div>
              <div className="font-mono">{health.system.wpVersion}</div>
              <div className="text-muted-foreground">Memory</div>
              <div className="font-mono">{health.system.memoryUsage} / {health.system.memoryLimit}</div>
              <div className="text-muted-foreground">Peak Memory</div>
              <div className="font-mono">{health.system.memoryPeak}</div>
              <div className="text-muted-foreground">Server</div>
              <div className="font-mono truncate">{health.system.serverSoftware}</div>
              <div className="text-muted-foreground">SSL</div>
              <div className="flex items-center gap-1">
                {health.system.sslEnabled ? (
                  <><CheckCircle className="h-3 w-3 text-emerald-500" /> Enabled</>
                ) : (
                  <><XCircle className="h-3 w-3 text-destructive" /> Disabled</>
                )}
              </div>
              <div className="text-muted-foreground">Debug</div>
              <div className="flex items-center gap-1">
                {health.system.wpDebug ? (
                  <Badge variant="outline" className="text-xs px-1 py-0 text-warning border-warning/30">ON</Badge>
                ) : (
                  <Badge variant="outline" className="text-xs px-1 py-0">OFF</Badge>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Disk Usage */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <HardDrive className="h-4 w-4" />
              Disk Usage
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="space-y-2">
              <Progress value={diskPercent} className="h-2" />
              <div className="flex justify-between text-xs text-muted-foreground">
                <span>{diskPercent}% used</span>
                <span>{health.system.diskFree} free of {health.system.diskTotal}</span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Plugins */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Puzzle className="h-4 w-4" />
              Plugins
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="flex items-center gap-3 text-sm">
              <Badge variant="default" className="gap-1">
                <CheckCircle className="h-3 w-3" />
                {health.plugins.active} active
              </Badge>
              <Badge variant="secondary" className="gap-1">
                {health.plugins.inactive} inactive
              </Badge>
              <span className="text-xs text-muted-foreground">{health.plugins.total} total</span>
            </div>
          </CardContent>
        </Card>

        {/* Integrations */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Shield className="h-4 w-4" />
              Integrations
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3 space-y-2">
            {/* WP Reset */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Camera className="h-3.5 w-3.5 text-muted-foreground" />
                <span className="text-sm">WP Reset</span>
                {health.integrations.wpReset.isPro && (
                  <Badge variant="default" className="text-xs px-1 py-0">Pro</Badge>
                )}
              </div>
              {health.integrations.wpReset.available ? (
                <div className="flex items-center gap-2">
                  <Badge variant="outline" className="text-xs">
                    {health.integrations.wpReset.snapshots} snapshots
                  </Badge>
                  <CheckCircle className="h-3.5 w-3.5 text-emerald-500" />
                </div>
              ) : (
                <Badge variant="secondary" className="text-xs">Not installed</Badge>
              )}
            </div>
            <Separator />
            {/* UpdraftPlus */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Archive className="h-3.5 w-3.5 text-muted-foreground" />
                <span className="text-sm">UpdraftPlus</span>
              </div>
              {health.integrations.updraftPlus.available ? (
                <div className="flex items-center gap-2">
                  <Badge variant="outline" className="text-xs">
                    {health.integrations.updraftPlus.backups} backups
                  </Badge>
                  <CheckCircle className="h-3.5 w-3.5 text-emerald-500" />
                </div>
              ) : (
                <Badge variant="secondary" className="text-xs">Not installed</Badge>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Users */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Users className="h-4 w-4" />
              Users
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="flex items-center gap-3">
              <span className="text-2xl font-bold">{health.users.total}</span>
              <div className="flex flex-wrap gap-1">
                {Object.entries(health.users.byRole).map(([role, count]) => (
                  <Badge key={role} variant="outline" className="text-xs">
                    {role}: {count}
                  </Badge>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Database */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Database className="h-4 w-4" />
              Database
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="grid grid-cols-2 gap-1.5 text-xs">
              <div className="text-muted-foreground">Tables</div>
              <div className="font-mono">{health.database.tableCount}</div>
              <div className="text-muted-foreground">Size</div>
              <div className="font-mono">{health.database.totalSize}</div>
              <div className="text-muted-foreground">Prefix</div>
              <div className="font-mono">{health.database.prefix}</div>
            </div>
          </CardContent>
        </Card>

        {/* PHP Limits */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Cpu className="h-4 w-4" />
              PHP Limits
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3">
            <div className="grid grid-cols-2 gap-1.5 text-xs">
              <div className="text-muted-foreground">Upload Max</div>
              <div className="font-mono">{health.system.uploadMaxFilesize}</div>
              <div className="text-muted-foreground">Post Max</div>
              <div className="font-mono">{health.system.postMaxSize}</div>
              <div className="text-muted-foreground">Memory Limit</div>
              <div className="font-mono">{health.system.memoryLimit}</div>
              <div className="text-muted-foreground">Max Exec Time</div>
              <div className="font-mono">{health.system.maxExecutionTime}s</div>
            </div>
          </CardContent>
        </Card>
      </div>
    </ScrollArea>
  );
}
