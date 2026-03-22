import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
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
  Package,
  FileText,
  AlertTriangle,
  ArrowRight,
  Trash2,
} from "lucide-react";
import { api, Site, RemotePlugin, requireSuccess } from "@/lib/api";
import { isApiClientError } from "@/lib/api";
import { useErrorStore } from "@/stores/errorStore";
import { toast } from "sonner";
import type { SiteHealthSummaryResponse } from "@/lib/api";
import type { RemoteLogsStatusResponse } from "@/lib/api/types";

interface SiteHealthSummaryPanelProps {
  site: Site;
  open: boolean;
}

export function SiteHealthSummaryPanel({ site, open }: SiteHealthSummaryPanelProps) {
  const queryClient = useQueryClient();
  const [isClearingLogs, setIsClearingLogs] = useState(false);
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

  // Fetch remote plugins for version info
  const { data: remotePlugins } = useQuery({
    queryKey: ["sites", site.id, "remote-plugins"],
    queryFn: async () => {
      const res = await api.getRemotePlugins(site.id);
      return res.success ? (res.data ?? []) : [];
    },
    enabled: open,
    staleTime: 60_000,
    meta: { suppressGlobalError: true },
  });

  // Fetch expected versions
  const { data: expectedVersions } = useQuery({
    queryKey: ["version-json"],
    queryFn: async () => {
      const base = (import.meta.env.BASE_URL || "/").replace(/\/?$/, "/");
      const resp = await fetch(`${base}version.json`);
      if (!resp.ok) return null;
      return resp.json() as Promise<{ wpPluginVersion: string; quploadVersion: string }>;
    },
    staleTime: 5 * 60_000,
  });

  // Fetch remote logs status
  const { data: logsStatus } = useQuery({
    queryKey: ["sites", site.id, "remote-logs-status"],
    queryFn: async () => {
      const res = await api.getRemoteLogsStatus(site.id);
      return res.success ? (res.data as RemoteLogsStatusResponse) : null;
    },
    enabled: open,
    staleTime: 60_000,
    meta: { suppressGlobalError: true },
  });

  // Derive managed plugin versions
  const managedPlugins = (() => {
    if (!remotePlugins) return [];
    const plugins: Array<{
      name: string;
      slug: string;
      remoteVersion: string | null;
      expectedVersion: string | null;
      isActive: boolean;
      isOutdated: boolean;
      isMissing: boolean;
    }> = [];

    const uploaderSlug = "riseup-asia-uploader/riseup-asia-uploader.php";
    const quploadSlug = "qupload/qupload.php";

    const findPlugin = (slug: string) =>
      remotePlugins.find(
        (p: RemotePlugin) =>
          p.plugin === slug || p.slug === slug.split("/")[0]
      );

    const uploader = findPlugin(uploaderSlug);
    const qupload = findPlugin(quploadSlug);

    plugins.push({
      name: "Riseup Asia Uploader",
      slug: "riseup-asia-uploader",
      remoteVersion: uploader?.version || null,
      expectedVersion: expectedVersions?.wpPluginVersion || null,
      isActive: uploader?.status?.toLowerCase() === "active",
      isOutdated: !!(uploader?.version && expectedVersions?.wpPluginVersion && uploader.version !== expectedVersions.wpPluginVersion),
      isMissing: !uploader,
    });

    plugins.push({
      name: "QUpload",
      slug: "qupload",
      remoteVersion: qupload?.version || null,
      expectedVersion: expectedVersions?.quploadVersion || null,
      isActive: qupload?.status?.toLowerCase() === "active",
      isOutdated: !!(qupload?.version && expectedVersions?.quploadVersion && qupload.version !== expectedVersions.quploadVersion),
      isMissing: !qupload,
    });

    return plugins;
  })();

  // Derive logs summary
  const logsSummary = (() => {
    if (!logsStatus) return null;
    const errorFiles = logsStatus.files.filter(
      (f) => f.name.includes("error") || f.name.includes("stacktrace")
    );
    const hasErrors = errorFiles.some((f) => f.lineCount > 0);
    const totalLines = logsStatus.files.reduce((sum, f) => sum + f.lineCount, 0);
    const totalSize = logsStatus.totalSizeBytes;
    return { hasErrors, totalLines, totalSize, errorFiles, files: logsStatus.files };
  })();

  const handleClearAllLogs = async () => {
    if (!confirm("Clear all logs for both plugins (Riseup Asia + QUpload)?")) return;
    setIsClearingLogs(true);
    try {
      const response = await api.clearAllRemoteLogs(site.id);
      const data = requireSuccess(response, { endpoint: `/sites/${site.id}/remote-logs/clear-all`, method: "POST" });
      const rOk = data.riseup?.cleared;
      const qOk = data.qupload?.cleared;
      if (rOk && qOk) {
        toast.success("All logs cleared");
      } else {
        const failures: string[] = [];
        if (!rOk) failures.push(`Riseup: ${data.riseup?.error || "failed"}`);
        if (!qOk) failures.push(`QUpload: ${data.qupload?.error || "failed"}`);
        toast.warning(`Partial clear: ${failures.join("; ")}`);
      }
      queryClient.invalidateQueries({ queryKey: ["sites", site.id, "remote-logs-status"] });
    } catch (err) {
      const { captureError, captureException, openErrorModal } = useErrorStore.getState();
      if (isApiClientError(err)) {
        const captured = captureError(err.apiError, {
          endpoint: err.meta.requestUrl,
          method: err.meta.method,
          context: { source: "SiteHealthSummaryPanel.clearLogs" },
        });
        openErrorModal(captured);
      } else {
        const captured = captureException(err, {
          source: "SiteHealthSummaryPanel.clearLogs",
          endpoint: `/sites/${site.id}/remote-logs/clear-all`,
          method: "POST",
        });
        openErrorModal(captured);
      }
    } finally {
      setIsClearingLogs(false);
    }
  };

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

        {/* Managed Plugin Versions */}
        <Card>
          <CardHeader className="py-2.5 px-4">
            <CardTitle className="text-sm flex items-center gap-2">
              <Package className="h-4 w-4" />
              Managed Plugins
            </CardTitle>
          </CardHeader>
          <CardContent className="px-4 pb-3 space-y-2">
            {managedPlugins.map((p) => (
              <div key={p.slug} className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="text-sm">{p.name}</span>
                  {p.isActive ? (
                    <Badge variant="outline" className="text-xs px-1.5 py-0 bg-emerald-500/10 text-emerald-600 border-emerald-500/20">
                      Active
                    </Badge>
                  ) : p.isMissing ? (
                    <Badge variant="destructive" className="text-xs px-1.5 py-0">
                      Missing
                    </Badge>
                  ) : (
                    <Badge variant="secondary" className="text-xs px-1.5 py-0">
                      Inactive
                    </Badge>
                  )}
                </div>
                <div className="flex items-center gap-1.5">
                  {p.remoteVersion ? (
                    <>
                      <Badge variant="outline" className="text-xs font-mono px-1.5 py-0">
                        v{p.remoteVersion}
                      </Badge>
                      {p.isOutdated && (
                        <>
                          <ArrowRight className="h-3 w-3 text-warning" />
                          <Badge variant="outline" className="text-xs font-mono px-1.5 py-0 border-warning/40 text-warning">
                            v{p.expectedVersion}
                          </Badge>
                        </>
                      )}
                      {!p.isOutdated && p.expectedVersion && (
                        <CheckCircle className="h-3.5 w-3.5 text-emerald-500" />
                      )}
                    </>
                  ) : p.isMissing ? (
                    <span className="text-xs text-destructive">Not installed</span>
                  ) : (
                    <span className="text-xs text-muted-foreground">Unknown</span>
                  )}
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        {/* Logs Status */}
        {logsSummary && (
          <Card>
            <CardHeader className="py-2.5 px-4">
              <CardTitle className="text-sm flex items-center gap-2">
                <FileText className="h-4 w-4" />
                Remote Logs
                {logsSummary.hasErrors && (
                  <Badge variant="outline" className="text-xs px-1.5 py-0 bg-destructive/10 text-destructive border-destructive/20 gap-1">
                    <AlertTriangle className="h-3 w-3" />
                    Has Errors
                  </Badge>
                )}
                {!logsSummary.hasErrors && logsSummary.totalLines === 0 && (
                  <Badge variant="outline" className="text-xs px-1.5 py-0 bg-emerald-500/10 text-emerald-600 border-emerald-500/20 gap-1">
                    <CheckCircle className="h-3 w-3" />
                    Clean
                  </Badge>
                )}
              </CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-3">
              <div className="space-y-1.5">
                {logsSummary.files.map((f) => {
                  const isError = f.name.includes("error") || f.name.includes("stacktrace");
                  const hasContent = f.lineCount > 0;
                  return (
                    <div key={f.name} className="flex items-center justify-between text-xs">
                      <span className={`font-mono ${isError && hasContent ? "text-destructive" : "text-muted-foreground"}`}>
                        {f.name}
                      </span>
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">{f.lineCount} lines</span>
                        <span className="text-muted-foreground font-mono">
                          {f.sizeBytes < 1024
                            ? `${f.sizeBytes}B`
                            : f.sizeBytes < 1048576
                              ? `${(f.sizeBytes / 1024).toFixed(1)}KB`
                              : `${(f.sizeBytes / 1048576).toFixed(1)}MB`}
                        </span>
                        {isError && hasContent && <AlertTriangle className="h-3 w-3 text-destructive" />}
                        {!hasContent && <CheckCircle className="h-3 w-3 text-emerald-500" />}
                      </div>
                    </div>
                  );
                })}
              </div>
              {logsSummary.totalSize > 0 && (
                <div className="mt-2 pt-2 border-t text-xs text-muted-foreground flex justify-between">
                  <span>{logsSummary.totalLines} total lines</span>
                  <span className="font-mono">
                    {logsSummary.totalSize < 1024
                      ? `${logsSummary.totalSize}B`
                      : logsSummary.totalSize < 1048576
                        ? `${(logsSummary.totalSize / 1024).toFixed(1)}KB`
                        : `${(logsSummary.totalSize / 1048576).toFixed(1)}MB`}
                    {" total"}
                  </span>
                </div>
              )}
            </CardContent>
          </Card>
        )}

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
