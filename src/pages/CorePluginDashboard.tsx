import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { api, Plugin, PluginVersion, PublishHistoryEntry } from "@/lib/api";
import { useSites } from "@/hooks/useSites";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  ArrowLeft,
  Shield,
  Globe,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Clock,
  Upload,
  Activity,
  RefreshCw,
  ExternalLink,
  Loader2,
  Server,
  Zap,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { SyncStatus, PublishStatus } from "@/lib/constants";
import { formatDistanceToNow, format } from "date-fns";
import { useState } from "react";

/** Identify the core uploader plugin by slug pattern */
function isCorePlugin(plugin: Plugin): boolean {
  const name = plugin.name.toLowerCase();
  const path = plugin.path.toLowerCase();
  return (
    name.includes("riseup") ||
    name.includes("rise up") ||
    name.includes("uploader") ||
    path.includes("riseup-asia-uploader")
  );
}

export default function CorePluginDashboard() {
  const navigate = useNavigate();
  const { data: sites } = useSites();
  const [isBootstrapping, setIsBootstrapping] = useState<number | null>(null);

  // Fetch all plugins to find the core one
  const { data: pluginsResult, isLoading: loadingPlugins } = useQuery({
    queryKey: ["plugins"],
    queryFn: () => api.getPlugins(),
  });

  const plugins = pluginsResult?.data;
  const corePlugin = plugins?.find(isCorePlugin);

  // Fetch version history for the core plugin
  const { data: versionsResult } = useQuery({
    queryKey: ["plugin-versions", corePlugin?.id],
    queryFn: () => api.getPluginVersions(corePlugin!.id, undefined, 10),
    enabled: !!corePlugin?.id,
  });
  const versions = versionsResult?.data ?? [];

  // Fetch publish history filtered to the core plugin
  const { data: historyResult } = useQuery({
    queryKey: ["publish-history", "core-plugin", corePlugin?.id],
    queryFn: () =>
      api.getPublishHistory({
        pluginId: corePlugin!.id,
        limit: 10,
      }),
    enabled: !!corePlugin?.id,
  });
  const history = historyResult?.data?.entries ?? [];

  // Bootstrap uploader to a site
  const handleBootstrap = async (siteId: number) => {
    setIsBootstrapping(siteId);
    try {
      const resp = await api.bootstrapUploader(siteId);
      if (resp.success) {
        // Handled by toast
      }
    } finally {
      setIsBootstrapping(null);
    }
  };

  if (loadingPlugins) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-64" />
        <div className="grid gap-4 md:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Card key={i}><CardContent className="p-6"><Skeleton className="h-20" /></CardContent></Card>
          ))}
        </div>
      </div>
    );
  }

  if (!corePlugin) {
    return (
      <div className="space-y-6">
        <Button variant="ghost" onClick={() => navigate("/plugins")} className="gap-2">
          <ArrowLeft className="h-4 w-4" /> Back to Plugins
        </Button>
        <Card>
          <CardContent className="py-12 text-center">
            <Shield className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
            <h2 className="text-lg font-semibold mb-2">Core Plugin Not Found</h2>
            <p className="text-muted-foreground max-w-md mx-auto">
              The Rise Up Asia Uploader plugin is not registered. Register it in the Plugins page first.
            </p>
            <Button className="mt-4" onClick={() => navigate("/plugins")}>
              Go to Plugins
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  const mappings = corePlugin.mappings ?? [];
  const healthySites = mappings.filter((m) => m.syncStatus === SyncStatus.Synced || m.syncStatus === SyncStatus.Ok);
  const warningSites = mappings.filter((m) => m.syncStatus === SyncStatus.Modified || m.syncStatus === SyncStatus.Pending);
  const errorSites = mappings.filter((m) => m.syncStatus === SyncStatus.Error || m.syncStatus === SyncStatus.Failed);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => navigate("/plugins")}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div className="p-2.5 rounded-xl bg-primary/10">
            <Shield className="h-6 w-6 text-primary" />
          </div>
          <div>
            <h1 className="text-xl sm:text-2xl font-bold flex items-center gap-2">
              {corePlugin.name}
              {corePlugin.version && (
                <Badge className="font-mono text-xs">v{corePlugin.version}</Badge>
              )}
            </h1>
            <p className="text-sm text-muted-foreground font-mono">{corePlugin.path}</p>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => navigate("/api-explorer")}>
            <ExternalLink className="h-4 w-4 mr-2" />
            API Explorer
          </Button>
        </div>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <StatCard
          icon={<Globe className="h-4 w-4 text-primary" />}
          label="Mapped Sites"
          value={mappings.length}
        />
        <StatCard
          icon={<CheckCircle2 className="h-4 w-4 text-emerald-500" />}
          label="Healthy"
          value={healthySites.length}
        />
        <StatCard
          icon={<AlertTriangle className="h-4 w-4 text-yellow-500" />}
          label="Pending"
          value={warningSites.length}
        />
        <StatCard
          icon={<XCircle className="h-4 w-4 text-destructive" />}
          label="Errors"
          value={errorSites.length}
        />
      </div>

      {/* Main Grid */}
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Site Health Panel */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Server className="h-4 w-4" />
              Site Deployment Status
            </CardTitle>
            <CardDescription>Connection and sync status per mapped site</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {mappings.length === 0 ? (
              <p className="text-sm text-muted-foreground py-4 text-center">
                No sites mapped yet. Map this plugin to a site in the Plugins page.
              </p>
            ) : (
              mappings.map((mapping) => {
                const site = sites?.find((s) => s.id === mapping.siteId);
                const statusColor =
                  mapping.syncStatus === SyncStatus.Synced || mapping.syncStatus === SyncStatus.Ok
                    ? "text-emerald-500"
                    : mapping.syncStatus === SyncStatus.Error || mapping.syncStatus === SyncStatus.Failed
                    ? "text-destructive"
                    : "text-yellow-500";

                return (
                  <div
                    key={mapping.id}
                    className="flex items-center justify-between p-3 rounded-lg border bg-muted/30"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <div className={cn("h-2.5 w-2.5 rounded-full", {
                        "bg-emerald-500": mapping.syncStatus === SyncStatus.Synced || mapping.syncStatus === SyncStatus.Ok,
                        "bg-yellow-500": mapping.syncStatus === SyncStatus.Modified || mapping.syncStatus === SyncStatus.Pending,
                        "bg-destructive": mapping.syncStatus === SyncStatus.Error || mapping.syncStatus === SyncStatus.Failed,
                        "bg-muted-foreground": !mapping.syncStatus,
                      })} />
                      <div className="min-w-0">
                        <p className="text-sm font-medium truncate">{mapping.siteName}</p>
                        <p className="text-xs text-muted-foreground font-mono truncate">
                          {mapping.remoteSlug}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Tooltip>
                        <TooltipTrigger>
                          <Badge variant="outline" className={cn("text-xs capitalize", statusColor)}>
                            {mapping.syncStatus || "unknown"}
                          </Badge>
                        </TooltipTrigger>
                        <TooltipContent>
                          {mapping.lastSyncAt
                            ? `Last sync: ${formatDistanceToNow(new Date(mapping.lastSyncAt), { addSuffix: true })}`
                            : "Never synced"}
                        </TooltipContent>
                      </Tooltip>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        disabled={isBootstrapping === mapping.siteId}
                        onClick={() => handleBootstrap(mapping.siteId)}
                      >
                        {isBootstrapping === mapping.siteId ? (
                          <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        ) : (
                          <RefreshCw className="h-3.5 w-3.5" />
                        )}
                      </Button>
                    </div>
                  </div>
                );
              })
            )}
          </CardContent>
        </Card>

        {/* Version History Panel */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Zap className="h-4 w-4" />
              Version History
            </CardTitle>
            <CardDescription>Recent version deployments across sites</CardDescription>
          </CardHeader>
          <CardContent>
            {versions.length === 0 ? (
              <p className="text-sm text-muted-foreground py-4 text-center">
                No version history yet.
              </p>
            ) : (
              <div className="space-y-2">
                {versions.map((v: PluginVersion) => (
                  <div
                    key={v.id}
                    className="flex items-center justify-between p-2.5 rounded-lg border bg-muted/30"
                  >
                    <div className="flex items-center gap-2.5">
                      <Badge variant="outline" className="font-mono text-xs">
                        v{v.version}
                      </Badge>
                      <span className="text-xs text-muted-foreground">{v.siteName}</span>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                      <span>{v.filesUpdated} files</span>
                      <span>·</span>
                      <Tooltip>
                        <TooltipTrigger>
                          <span>{formatDistanceToNow(new Date(v.createdAt), { addSuffix: true })}</span>
                        </TooltipTrigger>
                        <TooltipContent>{format(new Date(v.createdAt), "PPpp")}</TooltipContent>
                      </Tooltip>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Deployment History (Full Width) */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base flex items-center gap-2">
                <Activity className="h-4 w-4" />
                Recent Deployments
              </CardTitle>
              <CardDescription>Publish activity for the core uploader</CardDescription>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate(`/publish-history?pluginId=${corePlugin.id}`)}
            >
              View All
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {history.length === 0 ? (
            <p className="text-sm text-muted-foreground py-4 text-center">
              No deployment history yet.
            </p>
          ) : (
            <div className="space-y-2">
              {history.map((entry: PublishHistoryEntry) => (
                <div
                  key={entry.id}
                  className="flex items-center justify-between p-3 rounded-lg border bg-muted/30"
                >
                  <div className="flex items-center gap-3">
                    {entry.status === PublishStatus.Success ? (
                      <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0" />
                    ) : entry.status === PublishStatus.Failed ? (
                      <XCircle className="h-4 w-4 text-destructive shrink-0" />
                    ) : (
                      <AlertTriangle className="h-4 w-4 text-yellow-500 shrink-0" />
                    )}
                    <div>
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-sm font-medium">{entry.siteName}</span>
                        {entry.version && (
                          <Badge variant="outline" className="font-mono text-[10px] h-5">
                            v{entry.version}
                          </Badge>
                        )}
                        {entry.newVersion && entry.newVersion !== entry.version && (
                          <>
                            <span className="text-muted-foreground text-xs">→</span>
                            <Badge className="font-mono text-[10px] h-5">
                              v{entry.newVersion}
                            </Badge>
                          </>
                        )}
                        {entry.isSelfUpdate && (
                          <Badge variant="secondary" className="text-[10px] h-5">
                            <RefreshCw className="h-2.5 w-2.5 mr-1" />
                            Self-Update
                          </Badge>
                        )}
                      </div>
                      <div className="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                        <span>{entry.filesUpdated} files</span>
                        <span>·</span>
                        <span>{Math.round(entry.durationMs / 1000)}s</span>
                        {entry.machineName && (
                          <>
                            <span>·</span>
                            <span className="font-mono">{entry.machineName}</span>
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                  <div className="text-xs text-muted-foreground whitespace-nowrap">
                    <Tooltip>
                      <TooltipTrigger>
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {formatDistanceToNow(new Date(entry.createdAt), { addSuffix: true })}
                        </span>
                      </TooltipTrigger>
                      <TooltipContent>{format(new Date(entry.createdAt), "PPpp")}</TooltipContent>
                    </Tooltip>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function StatCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: number }) {
  return (
    <Card>
      <CardContent className="p-4 sm:p-6">
        <div className="flex items-center gap-2 text-muted-foreground text-xs sm:text-sm mb-1">
          {icon} {label}
        </div>
        <p className="text-xl sm:text-2xl font-bold">{value}</p>
      </CardContent>
    </Card>
  );
}
