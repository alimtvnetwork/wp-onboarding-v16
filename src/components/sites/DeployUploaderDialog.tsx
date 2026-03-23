import { useState, useEffect, useRef, useCallback } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LogViewer, LogEntry } from "@/components/shared/LogViewer";
import type { LogEntryDetails } from "@/lib/api";
import { isApiClientError } from "@/lib/api";
import {
  CheckCircle, XCircle, Loader2, Upload, Copy, Shield, AlertTriangle,
  ChevronDown, ChevronRight, Database, Globe, Server, Clock, FileWarning,
} from "lucide-react";
import { useWebSocket } from "@/hooks/useWebSocket";
import { toast } from "sonner";
import { DeployStatus } from "@/lib/constants";
import { useErrorStore } from "@/stores/errorStore";
import type { ApiError } from "@/lib/api/types";
import { Progress } from "@/components/ui/progress";
import { api } from "@/lib/api";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";

interface DeploySiteResult {
  siteId: number;
  siteName: string;
  isSuccess: boolean;
  message: string;
  isActivated?: boolean;
  error?: string;
  remoteResponseBody?: string;
  remoteStatusCode?: number;
  remoteUrl?: string;
}

interface PreflightPluginStatus {
  name?: string;
  available: boolean;
  namespace?: string;
  status?: string;
  httpStatus?: number;
  message?: string;
  version?: string;
  wpVersion?: string;
  phpVersion?: string;
  pluginName?: string;
  apiNamespace?: string;
  serverTime?: string;
  dbAvailable?: string;
  remoteSiteUrl?: string;
}

interface PreflightSiteResult {
  siteId: number;
  siteName: string;
  siteUrl: string;
  isReachable: boolean;
  riseupAsiaAvailable: boolean;
  riseupAsiaNamespace?: string;
  qUploadAvailable: boolean;
  qUploadNamespace?: string;
  riseupAsia?: PreflightPluginStatus;
  qUpload?: PreflightPluginStatus;
  error?: string;
}

type DeployPhase = "preflight" | "zipping" | "uploading" | "complete";

interface DeployUploaderDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  sites: Array<{ id: number; name: string; url: string }>;
  onDeploy: (siteIds: number[]) => Promise<DeploySiteResult[]>;
  title?: string;
}

export function DeployUploaderDialog({
  open,
  onOpenChange,
  sites,
  onDeploy,
  title = "Deploy Riseup Asia Uploader",
}: DeployUploaderDialogProps) {
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [status, setStatus] = useState<DeployStatus>(DeployStatus.Idle);
  const [results, setResults] = useState<DeploySiteResult[]>([]);
  const [currentTab, setCurrentTab] = useState("progress");
  const [preflightResults, setPreflightResults] = useState<PreflightSiteResult[]>([]);
  const [preflightLoading, setPreflightLoading] = useState(false);
  const [deployPhase, setDeployPhase] = useState<DeployPhase>("preflight");
  const [expandedSites, setExpandedSites] = useState<Set<number>>(new Set());
  const logsEndRef = useRef<HTMLDivElement>(null);
  const { data: versionInfo } = useVersionInfo();
  const localWpPluginVersion = (versionInfo as unknown as Record<string, string>)?.wpPluginVersion;
  const localQuploadVersion = (versionInfo as unknown as Record<string, string>)?.quploadVersion;
  const { lastMessage } = useWebSocket();

  // Listen for WebSocket log messages
  useEffect(() => {
    if (lastMessage?.type === "log" && status === DeployStatus.Deploying) {
      const data = lastMessage.data as LogEntryDetails | undefined;
      const logEntry: LogEntry = {
        timestamp: new Date().toISOString(),
        level: (data?.level as LogEntry["level"]) || "info",
        step: (data?.step as string) || "deploy",
        message: (data?.message as string) || (lastMessage as { message?: string }).message || "",
        details: data?.details as LogEntryDetails | undefined,
      };
      setLogs((prev) => [...prev, logEntry]);

      const msg = logEntry.message.toLowerCase();
      if (msg.includes("creating plugin zip") || msg.includes("zip archive created")) {
        setDeployPhase("zipping");
      } else if (msg.includes("uploading") || msg.includes("cross-upload") || msg.includes("endpoint")) {
        setDeployPhase("uploading");
      }
    }
  }, [lastMessage, status]);

  useEffect(() => {
    logsEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [logs]);

  // Run pre-flight when dialog opens
  useEffect(() => {
    if (open && sites.length > 0) {
      setLogs([]);
      setResults([]);
      setStatus(DeployStatus.Idle);
      setCurrentTab("progress");
      setPreflightResults([]);
      setDeployPhase("preflight");
      setExpandedSites(new Set());
      runPreflight();
    }
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  // Listen for streamed preflight results via WebSocket
  useEffect(() => {
    if (!open) return;

    const unsub = wsClient.on(WS_EVENTS.PREFLIGHT_SITE_RESULT, (data: unknown) => {
      const result = data as PreflightSiteResult;
      if (!result?.siteId) return;
      setPreflightResults((prev) => {
        const existing = prev.findIndex((p) => p.siteId === result.siteId);
        if (existing >= 0) {
          const updated = [...prev];
          updated[existing] = result;
          return updated;
        }
        return [...prev, result];
      });
    });

    return () => unsub();
  }, [open]);

  const runPreflight = useCallback(async () => {
    setPreflightLoading(true);
    setPreflightResults([]);
    try {
      const siteIds = sites.map((s) => s.id);
      const response = await api.deployPreflight(siteIds);
      const data = response.data;
      if (data?.results) {
        setPreflightResults((prev) => {
          const merged = [...prev];
          for (const r of data.results) {
            if (!merged.some((p) => p.siteId === r.siteId)) {
              merged.push(r);
            }
          }
          return merged;
        });
      }
    } catch {
      toast.error("Pre-flight check failed — you can still deploy");
    } finally {
      setPreflightLoading(false);
    }
  }, [sites]);

  const toggleSiteExpanded = (siteId: number) => {
    setExpandedSites((prev) => {
      const next = new Set(prev);
      if (next.has(siteId)) next.delete(siteId);
      else next.add(siteId);
      return next;
    });
  };

  const handleDeploy = async () => {
    setStatus(DeployStatus.Deploying);
    setDeployPhase("zipping");
    setLogs([{
      timestamp: new Date().toISOString(),
      level: "info",
      step: "init",
      message: `Starting deployment to ${sites.length} site(s)...`,
    }]);

    try {
      const siteIds = sites.map((s) => s.id);
      const deployResults = await onDeploy(siteIds);
      setResults(deployResults);
      setDeployPhase("complete");

      const resultLogs: LogEntry[] = deployResults.map((result) => ({
        timestamp: new Date().toISOString(),
        level: result.isSuccess ? "info" : "error",
        step: result.isSuccess ? "deploy-success" : "deploy-failed",
        message: result.isSuccess
          ? `${result.siteName}: ${result.message}`
          : `${result.siteName}: ${result.error || result.message}`,
      }));

      const succeeded = deployResults.filter((r) => r.isSuccess).length;
      const failed = deployResults.length - succeeded;

      setLogs((prev) => [
        ...prev,
        ...resultLogs,
        {
          timestamp: new Date().toISOString(),
          level: failed > 0 ? "warn" : "info",
          step: "complete",
          message: `Deployment complete: ${succeeded} succeeded, ${failed} failed`,
        },
      ]);

      setStatus(failed > 0 ? DeployStatus.Error : DeployStatus.Completed);

      if (failed === 0) {
        toast.success(`Deployed to ${succeeded} site(s) successfully`);
      } else {
        toast.warning(`Deployed to ${succeeded}/${sites.length} sites`);
        setCurrentTab("logs");
        surfacePartialFailure(deployResults, siteIds);
      }
    } catch (error: unknown) {
      setStatus(DeployStatus.Error);
      setDeployPhase("complete");
      const errorMsg = error instanceof Error ? error.message : "Deployment failed";
      setLogs((prev) => [
        ...prev,
        { timestamp: new Date().toISOString(), level: "error", step: "error", message: errorMsg },
      ]);
      surfaceException(error);
    }
  };

  const surfacePartialFailure = (deployResults: DeploySiteResult[], siteIds: number[]) => {
    const failedResults = deployResults.filter((r) => !r.isSuccess);
    const summaryLines = failedResults.map((r) => `${r.siteName}: ${r.error || r.message}`);

    const remoteResponses = failedResults
      .filter((r) => r.remoteResponseBody)
      .map((r) => `--- ${r.siteName} (${r.remoteStatusCode || "?"} from ${r.remoteUrl || "unknown"}) ---\n${r.remoteResponseBody}`)
      .join("\n\n");

    const modalError: ApiError = {
      code: "E3009",
      message: "Bulk uploader deployment failed on one or more sites",
      details: summaryLines.join("\n"),
      timestamp: new Date().toISOString(),
      context: {
        source: "DeployUploaderDialog",
        remoteResponseBody: remoteResponses || undefined,
        failedSites: failedResults.map((r) => ({
          siteId: r.siteId, siteName: r.siteName, error: r.error || r.message,
          remoteStatusCode: r.remoteStatusCode, remoteUrl: r.remoteUrl,
        })),
      },
    };

    const backendLogs = logs.map((entry) => ({
      timestamp: entry.timestamp, level: entry.level, message: `[${entry.step}] ${entry.message}`,
    }));

    const { captureError, openErrorModal } = useErrorStore.getState();
    const captured = captureError(modalError, {
      endpoint: "/sites/bulk-bootstrap-uploader",
      method: "POST",
      requestBody: { siteIds },
      responseStatus: 500,
      backendLogs,
      context: {
        source: "DeployUploaderDialog",
        triggerAction: "bulk-bootstrap-uploader",
        remoteResponseBody: remoteResponses || undefined,
      },
    });
    openErrorModal(captured);
  };

  const surfaceException = (error: unknown) => {
    const { captureError, captureException, openErrorModal } = useErrorStore.getState();
    if (isApiClientError(error)) {
      const captured = captureError(error.apiError, {
        endpoint: error.meta.requestUrl,
        method: error.meta.method,
        requestBody: error.meta.requestBody,
        responseStatus: (error.apiError.context?.responseStatus as number | undefined) ?? undefined,
        context: { source: "DeployUploaderDialog" },
      });
      openErrorModal(captured);
    } else {
      const captured = captureException(error, {
        source: "DeployUploaderDialog",
        endpoint: "/sites/bulk-bootstrap-uploader",
        method: "POST",
      });
      openErrorModal(captured);
    }
  };

  const handleCopyLogs = () => {
    const logText = logs.map((l) => `[${l.timestamp}] [${l.step}] [${l.level.toUpperCase()}] ${l.message}`).join("\n");
    navigator.clipboard.writeText(logText);
    toast.success("Logs copied to clipboard");
  };

  const getStatusIcon = () => {
    switch (status) {
      case DeployStatus.Deploying:
        return <Loader2 className="h-5 w-5 animate-spin text-primary" />;
      case DeployStatus.Completed:
        return <CheckCircle className="h-5 w-5 text-primary" />;
      case DeployStatus.Error:
        return <XCircle className="h-5 w-5 text-destructive" />;
      default:
        return <Upload className="h-5 w-5 text-muted-foreground" />;
    }
  };

  const getPhaseProgress = () => {
    switch (deployPhase) {
      case "preflight": return 0;
      case "zipping": return 25;
      case "uploading": return 60;
      case "complete": return 100;
    }
  };

  const getPhaseLabel = () => {
    switch (deployPhase) {
      case "preflight": return "Pre-flight checks";
      case "zipping": return "Creating ZIP archives...";
      case "uploading": return "Uploading to sites (cross-upload)...";
      case "complete": return status === DeployStatus.Completed ? "Deployment complete" : "Deployment finished with errors";
    }
  };

  // Compute totals for preflight summary
  const totalPluginChecks = preflightResults.length * 2;
  const okChecks = preflightResults.reduce((sum, pf) => {
    let count = 0;
    if (pf.qUpload?.status === "OK") count++;
    if (pf.riseupAsia?.status === "OK") count++;
    return sum + count;
  }, 0);
  const failedChecks = totalPluginChecks - okChecks;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {getStatusIcon()}
            {title}
          </DialogTitle>
        </DialogHeader>

        <Tabs value={currentTab} onValueChange={setCurrentTab}>
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="progress">Progress</TabsTrigger>
            <TabsTrigger value="preflight">
              <Shield className="h-3 w-3 mr-1" />
              Pre-flight
              {preflightResults.length > 0 && (
                <Badge variant="secondary" className="ml-1.5 text-[10px] px-1 py-0 h-4">
                  {okChecks}/{totalPluginChecks}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="logs">Logs ({logs.length})</TabsTrigger>
          </TabsList>

          {/* ── Progress Tab ── */}
          <TabsContent value="progress" className="space-y-4">
            {status === DeployStatus.Deploying && (
              <div className="space-y-2">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">{getPhaseLabel()}</span>
                  <span className="font-mono text-xs">{getPhaseProgress()}%</span>
                </div>
                <Progress value={getPhaseProgress()} className="h-2" />
              </div>
            )}

            <div className="flex items-center justify-between p-4 rounded-lg bg-muted/50">
              <div className="flex items-center gap-3">
                {getStatusIcon()}
                <div>
                  <p className="font-medium">
                    {status === DeployStatus.Idle ? "Ready to Deploy" : getPhaseLabel()}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    {sites.length} site(s) selected
                  </p>
                </div>
              </div>
            </div>

            {/* Site list with expandable preflight details */}
            <div className="space-y-2">
              <h4 className="text-sm font-medium">Target Sites</h4>
              <div className="max-h-[400px] overflow-y-auto space-y-1.5">
                {sites.map((site) => {
                  const result = results.find((r) => r.siteId === site.id);
                  const preflight = preflightResults.find((p) => p.siteId === site.id);
                  const isExpanded = expandedSites.has(site.id);
                  const hasPreflightData = preflight && preflight.isReachable;

                  return (
                    <Collapsible
                      key={site.id}
                      open={isExpanded}
                      onOpenChange={() => hasPreflightData && toggleSiteExpanded(site.id)}
                    >
                      <div className="rounded-lg border bg-card overflow-hidden">
                        <CollapsibleTrigger asChild disabled={!hasPreflightData}>
                          <div className={`flex items-center justify-between p-3 ${hasPreflightData ? "cursor-pointer hover:bg-muted/50" : ""}`}>
                            <div className="flex items-center gap-2 flex-1 min-w-0">
                              {hasPreflightData && (
                                isExpanded
                                  ? <ChevronDown className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                  : <ChevronRight className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                              )}
                              <div className="flex-1 min-w-0">
                                <span className="text-sm font-medium">{site.name}</span>
                                <span className="text-xs text-muted-foreground ml-2 truncate">{site.url}</span>
                                {preflight && (
                                  <div className="flex items-center gap-2 mt-1">
                                    <EndpointBadge label="QUpload" available={preflight.qUploadAvailable} namespace={preflight.qUploadNamespace} />
                                    <EndpointBadge label="Riseup" available={preflight.riseupAsiaAvailable} namespace={preflight.riseupAsiaNamespace} />
                                  </div>
                                )}
                              </div>
                            </div>
                            <div className="flex items-center gap-1.5 shrink-0">
                              {result && (
                                result.isSuccess ? (
                                  <CheckCircle className="h-4 w-4 text-primary" />
                                ) : (
                                  <XCircle className="h-4 w-4 text-destructive" />
                                )
                              )}
                              {status === DeployStatus.Deploying && !result && (
                                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                              )}
                              {status === DeployStatus.Idle && preflightLoading && !preflight && (
                                <Loader2 className="h-3 w-3 animate-spin text-muted-foreground" />
                              )}
                              {preflight && !preflight.isReachable && (
                                <Badge variant="destructive" className="text-[10px] px-1.5 py-0">Unreachable</Badge>
                              )}
                            </div>
                          </div>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                          {hasPreflightData && (
                            <div className="border-t px-3 pb-3 pt-2 space-y-2">
                              <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <PluginDetailCard
                                  label="QUpload"
                                  sublabel="cross-upload"
                                  plugin={preflight.qUpload}
                                  available={preflight.qUploadAvailable}
                                  namespace={preflight.qUploadNamespace}
                                  localVersion={localQuploadVersion}
                                  preferred
                                />
                                <PluginDetailCard
                                  label="Riseup Asia"
                                  sublabel="fallback"
                                  plugin={preflight.riseupAsia}
                                  available={preflight.riseupAsiaAvailable}
                                  namespace={preflight.riseupAsiaNamespace}
                                  localVersion={localWpPluginVersion}
                                />
                              </div>
                              {!preflight.qUploadAvailable && !preflight.riseupAsiaAvailable && (
                                <div className="flex items-center gap-1.5 text-xs text-destructive bg-destructive/10 p-2 rounded">
                                  <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                                  No upload endpoint available — deploy will fail
                                </div>
                              )}
                            </div>
                          )}
                        </CollapsibleContent>
                      </div>
                    </Collapsible>
                  );
                })}
              </div>
            </div>

            {/* Results summary */}
            {results.length > 0 && (
              <div className="space-y-2">
                <h4 className="text-sm font-medium">Results</h4>
                <div className="space-y-1">
                  {results.map((result) => (
                    <div
                      key={result.siteId}
                      className={`p-2 rounded text-sm ${
                        result.isSuccess ? "bg-primary/10" : "bg-destructive/10"
                      }`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-medium">{result.siteName}</span>
                        <span className={result.isSuccess ? "text-primary" : "text-destructive"}>
                          {result.isSuccess ? "Success" : "Failed"}
                        </span>
                      </div>
                      {result.error && (
                        <p className="text-xs text-destructive mt-1">{result.error}</p>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </TabsContent>

          {/* ── Pre-flight Tab ── */}
          <TabsContent value="preflight" className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium">Plugin Status Summary</h4>
                {preflightResults.length > 0 && !preflightLoading && (
                  <p className="text-xs text-muted-foreground mt-0.5">
                    Sites: {preflightResults.length} · Plugins: 2 · Checks: {totalPluginChecks} · OK: {okChecks} · Failed: {failedChecks}
                  </p>
                )}
              </div>
              <Button variant="ghost" size="sm" onClick={runPreflight} disabled={preflightLoading}>
                {preflightLoading ? <Loader2 className="h-3 w-3 animate-spin mr-1" /> : <Shield className="h-3 w-3 mr-1" />}
                Refresh
              </Button>
            </div>

            {preflightLoading && preflightResults.length === 0 && (
              <div className="flex items-center justify-center py-8 text-muted-foreground">
                <Loader2 className="h-5 w-5 animate-spin mr-2" />
                Checking endpoints...
              </div>
            )}

            {preflightResults.length > 0 && (
              <div className="space-y-3">
                {preflightResults.map((pf) => (
                  <div key={pf.siteId} className="rounded-lg border bg-card overflow-hidden">
                    {/* Site header */}
                    <div className="flex items-center justify-between p-3 border-b bg-muted/30">
                      <div className="flex items-center gap-2 min-w-0">
                        <Globe className="h-4 w-4 text-muted-foreground shrink-0" />
                        <div className="min-w-0">
                          <span className="font-medium text-sm">{pf.siteName}</span>
                          <span className="text-xs text-muted-foreground ml-2 truncate">{pf.siteUrl}</span>
                        </div>
                      </div>
                      {pf.isReachable ? (
                        <Badge variant="secondary" className="text-[10px] bg-primary/10 text-primary border-primary/20">
                          <CheckCircle className="h-2.5 w-2.5 mr-1" /> Reachable
                        </Badge>
                      ) : (
                        <Badge variant="destructive" className="text-[10px]">
                          <XCircle className="h-2.5 w-2.5 mr-1" /> Unreachable
                        </Badge>
                      )}
                    </div>

                    {/* Plugin details */}
                    {pf.isReachable && (
                      <div className="p-3 space-y-2">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                          <PluginDetailCard
                            label="QUpload"
                            sublabel="cross-upload"
                            plugin={pf.qUpload}
                            available={pf.qUploadAvailable}
                            namespace={pf.qUploadNamespace}
                            localVersion={localQuploadVersion}
                            preferred
                          />
                          <PluginDetailCard
                            label="Riseup Asia"
                            sublabel="fallback"
                            plugin={pf.riseupAsia}
                            available={pf.riseupAsiaAvailable}
                            namespace={pf.riseupAsiaNamespace}
                            localVersion={localWpPluginVersion}
                          />
                        </div>
                        {!pf.qUploadAvailable && !pf.riseupAsiaAvailable && (
                          <div className="flex items-center gap-1.5 text-xs text-destructive bg-destructive/10 p-2 rounded">
                            <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                            No upload endpoint available — deploy will fail
                          </div>
                        )}
                      </div>
                    )}

                    {pf.error && (
                      <div className="px-3 pb-3">
                        <p className="text-xs text-destructive">{pf.error}</p>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </TabsContent>

          {/* ── Logs Tab ── */}
          <TabsContent value="logs" className="space-y-2">
            <div className="flex justify-end">
              <Button variant="ghost" size="sm" onClick={handleCopyLogs} disabled={logs.length === 0}>
                <Copy className="h-3 w-3 mr-1" />
                Copy
              </Button>
            </div>
            <LogViewer logs={logs} className="h-64" />
            <div ref={logsEndRef} />
          </TabsContent>
        </Tabs>

        <div className="flex justify-end gap-2 pt-4 border-t">
          {status === DeployStatus.Idle && (
            <Button onClick={handleDeploy} disabled={sites.length === 0 || preflightLoading}>
              <Upload className="h-4 w-4 mr-2" />
              Deploy to {sites.length} Site(s)
            </Button>
          )}
          {(status === DeployStatus.Completed || status === DeployStatus.Error) && (
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Close
            </Button>
          )}
          {status === DeployStatus.Deploying && (
            <Button disabled>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              Deploying...
            </Button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}

/* ── Small inline badge for endpoint availability ── */
function EndpointBadge({ label, available, namespace }: { label: string; available: boolean; namespace?: string }) {
  return (
    <span className={`inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded ${
      available ? "bg-primary/10 text-primary" : "bg-muted text-muted-foreground"
    }`}>
      {available ? <CheckCircle className="h-2.5 w-2.5" /> : <XCircle className="h-2.5 w-2.5" />}
      {label}
      {namespace && <span className="opacity-60">({namespace})</span>}
    </span>
  );
}

/* ── Rich plugin detail card with full metadata ── */
function PluginDetailCard({
  label,
  sublabel,
  plugin,
  available,
  namespace,
  localVersion,
  preferred,
}: {
  label: string;
  sublabel: string;
  plugin?: PreflightPluginStatus;
  available: boolean;
  namespace?: string;
  localVersion?: string;
  preferred?: boolean;
}) {
  const remoteVersion = plugin?.version;
  const needsPublish = available && remoteVersion && localVersion && remoteVersion !== localVersion;
  const isUpToDate = available && remoteVersion && localVersion && remoteVersion === localVersion;
  const versionUnknown = available && !remoteVersion;

  return (
    <div className={`rounded-lg border p-3 text-xs space-y-2 ${
      available
        ? needsPublish
          ? "border-amber-500/30 bg-amber-500/5"
          : "border-primary/30 bg-primary/5"
        : "border-border bg-muted/30"
    }`}>
      {/* Plugin header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-1.5">
          {available ? (
            <CheckCircle className="h-3.5 w-3.5 text-primary shrink-0" />
          ) : (
            <XCircle className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
          )}
          <span className="font-semibold">{label}</span>
          <span className="text-muted-foreground">({sublabel})</span>
        </div>
        {preferred && available && !needsPublish && (
          <Badge variant="secondary" className="text-[9px] px-1 py-0 h-3.5 bg-primary/10 text-primary border-primary/20">
            ★ Preferred
          </Badge>
        )}
      </div>

      {!available && (
        <p className="text-muted-foreground">Not installed</p>
      )}

      {available && (
        <>
          {/* Version row */}
          <div className="flex items-center gap-1.5 flex-wrap">
            {remoteVersion && (
              <Badge variant="outline" className="text-[10px] px-1.5 py-0 font-mono">
                v{remoteVersion}
              </Badge>
            )}
            {versionUnknown && (
              <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-muted-foreground">
                version unknown
              </Badge>
            )}
            {localVersion && (
              <>
                <span className="text-muted-foreground">→</span>
                <Badge variant={needsPublish ? "destructive" : "secondary"} className="text-[10px] px-1.5 py-0 font-mono">
                  local v{localVersion}
                </Badge>
              </>
            )}
            {isUpToDate && (
              <span className="text-primary text-[10px] font-medium ml-1">(up to date)</span>
            )}
            {needsPublish && (
              <span className="text-destructive text-[10px] font-medium flex items-center gap-0.5 ml-1">
                <AlertTriangle className="h-2.5 w-2.5" /> Needs publish
              </span>
            )}
          </div>

          {/* Environment metadata */}
          {(plugin?.wpVersion || plugin?.phpVersion || plugin?.dbAvailable) && (
            <div className="flex items-center gap-3 text-muted-foreground flex-wrap">
              {plugin?.wpVersion && (
                <span className="flex items-center gap-1">
                  <Server className="h-3 w-3" /> WP {plugin.wpVersion}
                </span>
              )}
              {plugin?.phpVersion && (
                <span className="flex items-center gap-1">
                  <Server className="h-3 w-3" /> PHP {plugin.phpVersion}
                </span>
              )}
              {plugin?.apiNamespace && (
                <span className="flex items-center gap-1 font-mono">
                  API {plugin.apiNamespace}
                </span>
              )}
              {plugin?.dbAvailable && (
                <span className="flex items-center gap-1">
                  <Database className="h-3 w-3" />
                  DB {plugin.dbAvailable === "true" || plugin.dbAvailable === "1" ? "✓" : "✗"}
                </span>
              )}
            </div>
          )}

          {/* Server time & URL */}
          {(plugin?.serverTime || plugin?.remoteSiteUrl) && (
            <div className="flex items-center gap-3 text-muted-foreground flex-wrap">
              {plugin?.serverTime && (
                <span className="flex items-center gap-1">
                  <Clock className="h-3 w-3" /> {plugin.serverTime}
                </span>
              )}
              {plugin?.remoteSiteUrl && (
                <span className="flex items-center gap-1 truncate">
                  <Globe className="h-3 w-3 shrink-0" />
                  <span className="truncate">{plugin.remoteSiteUrl}</span>
                </span>
              )}
            </div>
          )}

          {/* Status message / logs info */}
          {plugin?.message && plugin.status !== "OK" && (
            <div className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
              <FileWarning className="h-3 w-3 shrink-0" />
              <span className="truncate">{plugin.message}</span>
            </div>
          )}
        </>
      )}
    </div>
  );
}
