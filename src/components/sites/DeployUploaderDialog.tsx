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
  ExternalLink, RefreshCw,
} from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
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

type DeployPhase = "preflight" | "zipping" | "uploading" | "verifying" | "complete";

interface DeployUploaderDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  sites: Array<{ id: number; name: string; url: string }>;
  onDeploy: (siteIds: number[]) => Promise<DeploySiteResult[]>;
  title?: string;
}

// Module-level cache so reopening dialog shows previous results
let preflightCache: PreflightSiteResult[] = [];

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
  const [preflightResults, setPreflightResults] = useState<PreflightSiteResult[]>(preflightCache);
  const [preflightLoading, setPreflightLoading] = useState(false);
  const [deployPhase, setDeployPhase] = useState<DeployPhase>("preflight");
  const [phaseTimings, setPhaseTimings] = useState<Record<string, { start: number; end?: number }>>({});
  const [, setTimerTick] = useState(0);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const [expandedSites, setExpandedSites] = useState<Set<number>>(new Set());
  const [completedSiteIds, setCompletedSiteIds] = useState<Set<number>>(new Set());
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
        transitionPhase("zipping");
      } else if (msg.includes("uploading") || msg.includes("cross-upload") || msg.includes("endpoint")) {
        transitionPhase("uploading");
      }

      // Track per-site completion from WS messages
      for (const site of sites) {
        if (msg.includes(site.name.toLowerCase()) && (msg.includes("success") || msg.includes("uploaded") || msg.includes("activated"))) {
          setCompletedSiteIds((prev) => new Set(prev).add(site.id));
        }
      }
    }
  }, [lastMessage, status, sites]);

  useEffect(() => {
    logsEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [logs]);

  // On open: use cache if available, run preflight in background
  useEffect(() => {
    if (open && sites.length > 0) {
      setLogs([]);
      setResults([]);
      setStatus(DeployStatus.Idle);
      setCurrentTab("progress");
      setPhaseTimings({});
      setCompletedSiteIds(new Set());
      stopTimer();
      setDeployPhase("preflight");
      setExpandedSites(new Set());

      // Restore cache
      if (preflightCache.length > 0) {
        setPreflightResults(preflightCache);
      } else {
        setPreflightResults([]);
        runPreflight();
      }
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
        const updated = existing >= 0
          ? prev.map((p, i) => (i === existing ? result : p))
          : [...prev, result];
        preflightCache = updated;
        return updated;
      });
    });

    return () => unsub();
  }, [open]);

  const runPreflight = useCallback(async () => {
    setPreflightLoading(true);
    setPreflightResults([]);
    preflightCache = [];
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
          preflightCache = merged;
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

  // Helper to transition phases with timing
  const transitionPhase = (phase: DeployPhase) => {
    const now = Date.now();
    setPhaseTimings((prev) => {
      const updated = { ...prev };
      // End previous active phase
      for (const key of Object.keys(updated)) {
        if (!updated[key].end) updated[key] = { ...updated[key], end: now };
      }
      // Start new phase (unless "complete")
      if (phase !== "complete") {
        updated[phase] = { start: now };
      }
      return updated;
    });
    setDeployPhase(phase);
  };

  // Start live timer during deploy
  const startTimer = () => {
    if (timerRef.current) clearInterval(timerRef.current);
    timerRef.current = setInterval(() => setTimerTick((t) => t + 1), 100);
  };
  const stopTimer = () => {
    if (timerRef.current) { clearInterval(timerRef.current); timerRef.current = null; }
  };

  const handleDeploy = async () => {
    setStatus(DeployStatus.Deploying);
    setPhaseTimings({});
    setCompletedSiteIds(new Set());
    startTimer();
    transitionPhase("zipping");
    setLogs([{
      timestamp: new Date().toISOString(),
      level: "info",
      step: "init",
      message: `Starting deployment to ${sites.length} site(s) — pushing v${localWpPluginVersion || "?"}`,
    }]);

    try {
      const siteIds = sites.map((s) => s.id);
      const deployResults = await onDeploy(siteIds);
      setResults(deployResults);
      // Mark all sites as completed from results
      setCompletedSiteIds(new Set(deployResults.map((r) => r.siteId)));

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
          step: "upload-done",
          message: `Upload complete: ${succeeded} succeeded, ${failed} failed`,
        },
      ]);

      // Auto-verify: refresh pre-flight to confirm versions on remote
      transitionPhase("verifying");
      setLogs((prev) => [...prev, {
        timestamp: new Date().toISOString(),
        level: "info",
        step: "verify",
        message: "Verifying deployed versions across all sites...",
      }]);

      try {
        setPreflightLoading(true);
        setPreflightResults([]);
        preflightCache = [];
        const response = await api.deployPreflight(siteIds);
        const data = response.data;
        if (data?.results) {
          setPreflightResults(data.results);
          preflightCache = data.results;
        }
        setLogs((prev) => [...prev, {
          timestamp: new Date().toISOString(),
          level: "info",
          step: "verify",
          message: `Post-deploy verification complete — ${data?.results?.length || 0} site(s) checked`,
        }]);
      } catch {
        setLogs((prev) => [...prev, {
          timestamp: new Date().toISOString(),
          level: "warn",
          step: "verify",
          message: "Post-deploy verification failed — use Refresh to retry",
        }]);
      } finally {
        setPreflightLoading(false);
      }

      transitionPhase("complete");
      stopTimer();
      setStatus(failed > 0 ? DeployStatus.Error : DeployStatus.Completed);

      if (failed === 0) {
        toast.success(`Deployed v${localWpPluginVersion || "?"} to ${succeeded} site(s) successfully`);
      } else {
        toast.warning(`Deployed to ${succeeded}/${sites.length} sites`);
        setCurrentTab("logs");
        surfacePartialFailure(deployResults, siteIds);
      }
    } catch (error: unknown) {
      setStatus(DeployStatus.Error);
      transitionPhase("complete");
      stopTimer();
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
      case "zipping": return 20;
      case "uploading": return 55;
      case "verifying": return 85;
      case "complete": return 100;
    }
  };

  const getPhaseLabel = () => {
    const ver = localWpPluginVersion ? ` v${localWpPluginVersion}` : "";
    switch (deployPhase) {
      case "preflight": return "Pre-flight checks";
      case "zipping": return `Creating ZIP archives${ver}...`;
      case "uploading": return `Uploading${ver} to ${sites.length} site(s)...`;
      case "verifying": return "Verifying deployed versions...";
      case "complete": return status === DeployStatus.Completed ? "Deployment complete" : "Deployment finished with errors";
    }
  };

  // Subtask chain items for the progress display
  const deploySubtasks = [
    { key: "zipping", label: `ZIP plugins${localWpPluginVersion ? ` v${localWpPluginVersion}` : ""}`, icon: "📦" },
    { key: "uploading", label: `Upload to ${sites.length} site(s)`, icon: "📤" },
    { key: "verifying", label: "Verify remote versions", icon: "🔍" },
  ] as const;

  const getSubtaskStatus = (key: string) => {
    const order = ["zipping", "uploading", "verifying", "complete"];
    const currentIdx = order.indexOf(deployPhase);
    const taskIdx = order.indexOf(key);
    if (taskIdx < currentIdx) return "done";
    if (taskIdx === currentIdx) return "active";
    return "pending";
  };

  const getSubtaskElapsed = (key: string): string | null => {
    const timing = phaseTimings[key];
    if (!timing) return null;
    const end = timing.end ?? Date.now();
    const ms = end - timing.start;
    return ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`;
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
          <DialogTitle className="flex items-center gap-2 text-lg">
            {getStatusIcon()}
            {title}
          </DialogTitle>
        </DialogHeader>

        <Tabs value={currentTab} onValueChange={setCurrentTab}>
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="progress">Progress</TabsTrigger>
            <TabsTrigger value="preflight">
              <Shield className="h-3.5 w-3.5 mr-1.5" />
              Pre-flight
              {preflightResults.length > 0 && (
                <Badge variant="secondary" className="ml-1.5 text-xs px-1.5 py-0 h-5">
                  {okChecks}/{totalPluginChecks}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="logs">Logs ({logs.length})</TabsTrigger>
          </TabsList>

          {/* ── Progress Tab ── */}
          <TabsContent value="progress" className="space-y-4">
            {/* Phase progress bar */}
            {(status === DeployStatus.Deploying || status === DeployStatus.Completed || status === DeployStatus.Error) && (
              <div className="space-y-3">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground font-medium">{getPhaseLabel()}</span>
                  <span className="font-mono text-xs text-muted-foreground">{getPhaseProgress()}%</span>
                </div>
                <Progress value={getPhaseProgress()} className="h-2" />

                {/* Chained subtask list */}
                <div className="space-y-1.5 pt-1">
                  {deploySubtasks.map((task) => {
                    const s = getSubtaskStatus(task.key);
                    return (
                      <div key={task.key} className="space-y-1">
                        <div className="flex items-center gap-2.5 text-sm">
                          {s === "done" ? (
                            <CheckCircle className="h-4 w-4 text-primary shrink-0" />
                          ) : s === "active" ? (
                            <Loader2 className="h-4 w-4 animate-spin text-primary shrink-0" />
                          ) : (
                            <div className="h-4 w-4 rounded-full border border-border/60 shrink-0" />
                          )}
                          <span className={`flex-1 ${
                            s === "done" ? "text-muted-foreground line-through" :
                            s === "active" ? "text-foreground font-medium" :
                            "text-muted-foreground/60"
                          }`}>
                            {task.icon} {task.label}
                          </span>
                          {(s === "done" || s === "active") && getSubtaskElapsed(task.key) && (
                            <span className="font-mono text-xs text-muted-foreground/70 tabular-nums">
                              {getSubtaskElapsed(task.key)}
                            </span>
                          )}
                          {s === "done" && (
                            <span className="text-xs text-primary font-mono">✓</span>
                          )}
                        </div>

                        {/* Per-site sub-items for upload task */}
                        {task.key === "uploading" && (s === "active" || s === "done") && (
                          <div className="ml-7 space-y-0.5">
                            {sites.map((site) => {
                              const siteResult = results.find((r) => r.siteId === site.id);
                              const isDone = completedSiteIds.has(site.id);
                              const isFailed = siteResult && !siteResult.isSuccess;
                              return (
                                <div key={site.id} className="flex items-center gap-2 text-xs">
                                  {isDone ? (
                                    isFailed ? (
                                      <XCircle className="h-3 w-3 text-destructive shrink-0" />
                                    ) : (
                                      <CheckCircle className="h-3 w-3 text-primary shrink-0" />
                                    )
                                  ) : s === "active" ? (
                                    <Loader2 className="h-3 w-3 animate-spin text-muted-foreground shrink-0" />
                                  ) : (
                                    <div className="h-3 w-3 rounded-full border border-border/40 shrink-0" />
                                  )}
                                  <span className={
                                    isDone
                                      ? isFailed ? "text-destructive" : "text-muted-foreground"
                                      : "text-muted-foreground/70"
                                  }>
                                    {site.name}
                                  </span>
                                </div>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Status banner */}
            <div className="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50">
              <div className="flex items-center gap-3">
                {getStatusIcon()}
                <div>
                  <p className="font-semibold text-base text-foreground">
                    {status === DeployStatus.Idle
                      ? `Ready to Deploy${localWpPluginVersion ? ` v${localWpPluginVersion}` : ""}`
                      : getPhaseLabel()}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    {sites.length} site(s) selected
                    {localWpPluginVersion && status === DeployStatus.Idle && (
                      <> · Riseup Asia v{localWpPluginVersion}{localQuploadVersion ? ` · QUpload v${localQuploadVersion}` : ""}</>
                    )}
                  </p>
                </div>
              </div>
            </div>

            {/* Site list with expandable preflight details */}
            <div className="space-y-3">
              <h4 className="text-sm font-semibold text-foreground">Target Sites</h4>
              <div className="max-h-[400px] overflow-y-auto space-y-2">
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
                      <div className="rounded-lg border border-border/60 bg-card overflow-hidden">
                        <CollapsibleTrigger asChild disabled={!hasPreflightData}>
                          <div className={`p-3.5 ${hasPreflightData ? "cursor-pointer hover:bg-muted/30 transition-colors" : ""}`}>
                            {/* Row 1: Site name + open link + status */}
                            <div className="flex items-center justify-between gap-2">
                              <div className="flex items-center gap-2 min-w-0">
                                {hasPreflightData && (
                                  isExpanded
                                    ? <ChevronDown className="h-4 w-4 text-muted-foreground shrink-0" />
                                    : <ChevronRight className="h-4 w-4 text-muted-foreground shrink-0" />
                                )}
                                <span className="text-sm font-semibold text-foreground truncate">{site.name}</span>
                                <TooltipProvider delayDuration={200}>
                                  <Tooltip>
                                    <TooltipTrigger asChild>
                                      <a
                                        href={site.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        onClick={(e) => e.stopPropagation()}
                                        className="text-muted-foreground hover:text-primary transition-colors shrink-0"
                                      >
                                        <ExternalLink className="h-3.5 w-3.5" />
                                      </a>
                                    </TooltipTrigger>
                                    <TooltipContent side="top" className="text-xs font-mono">
                                      {site.url}
                                    </TooltipContent>
                                  </Tooltip>
                                </TooltipProvider>
                              </div>
                              <div className="flex items-center gap-2 shrink-0">
                                {result && (
                                  result.isSuccess ? (
                                    <Badge variant="secondary" className="text-xs px-2 py-0.5 bg-primary/10 text-primary border-primary/20">
                                      <CheckCircle className="h-3 w-3 mr-1" /> Done
                                    </Badge>
                                  ) : (
                                    <Badge variant="destructive" className="text-xs px-2 py-0.5">
                                      <XCircle className="h-3 w-3 mr-1" /> Failed
                                    </Badge>
                                  )
                                )}
                                {status === DeployStatus.Deploying && !result && (
                                  <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                                )}
                                {status === DeployStatus.Idle && preflightLoading && !preflight && (
                                  <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                                )}
                                {preflight && !preflight.isReachable && (
                                  <Badge variant="destructive" className="text-xs px-2 py-0.5">Unreachable</Badge>
                                )}
                              </div>
                            </div>

                            {/* Row 2: Plugin version summary (always visible) */}
                            {preflight && (
                              <div className="flex items-center gap-3 mt-2 flex-wrap">
                                <PluginSummaryBadge
                                  label="QUpload"
                                  available={preflight.qUploadAvailable}
                                  remoteVersion={preflight.qUpload?.version}
                                  localVersion={localQuploadVersion}
                                />
                                <PluginSummaryBadge
                                  label="Riseup Asia"
                                  available={preflight.riseupAsiaAvailable}
                                  remoteVersion={preflight.riseupAsia?.version}
                                  localVersion={localWpPluginVersion}
                                />
                              </div>
                            )}
                          </div>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                          {hasPreflightData && (
                            <div className="border-t border-border/40 px-3.5 pb-3.5 pt-3 space-y-3">
                              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
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
                                <div className="flex items-center gap-2 text-sm text-destructive bg-destructive/10 p-3 rounded-md border border-destructive/20">
                                  <AlertTriangle className="h-4 w-4 shrink-0" />
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
              <div className="space-y-3">
                <h4 className="text-sm font-semibold text-foreground">Results</h4>
                <div className="space-y-2">
                  {results.map((result) => (
                    <div
                      key={result.siteId}
                      className={`p-3 rounded-lg text-sm ${
                        result.isSuccess ? "bg-primary/10 border border-primary/20" : "bg-destructive/10 border border-destructive/20"
                      }`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-foreground">{result.siteName}</span>
                        <span className={result.isSuccess ? "text-primary text-sm font-medium" : "text-destructive text-sm font-medium"}>
                          {result.isSuccess ? "✓ Success" : "✗ Failed"}
                        </span>
                      </div>
                      {result.error && (
                        <p className="text-sm text-destructive mt-1">{result.error}</p>
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
                <h4 className="text-sm font-semibold text-foreground">Plugin Status Summary</h4>
                {preflightResults.length > 0 && !preflightLoading && (
                  <p className="text-sm text-muted-foreground mt-1">
                    Sites: {preflightResults.length} · Checks: {totalPluginChecks} · OK: {okChecks} · Failed: {failedChecks}
                  </p>
                )}
              </div>
              <Button variant="ghost" size="sm" onClick={runPreflight} disabled={preflightLoading}>
                {preflightLoading ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <RefreshCw className="h-4 w-4 mr-1.5" />}
                Refresh
              </Button>
            </div>

            {preflightLoading && preflightResults.length === 0 && (
              <div className="flex items-center justify-center py-10 text-muted-foreground">
                <Loader2 className="h-5 w-5 animate-spin mr-2" />
                <span className="text-sm">Checking endpoints...</span>
              </div>
            )}

            {preflightResults.length > 0 && (
              <div className="space-y-3">
                {preflightResults.map((pf) => (
                  <div key={pf.siteId} className="rounded-lg border border-border/60 bg-card overflow-hidden">
                    {/* Site header */}
                    <div className="flex items-center justify-between p-3.5 border-b border-border/40 bg-muted/20">
                      <div className="flex items-center gap-2 min-w-0">
                        <span className="font-semibold text-sm text-foreground">{pf.siteName}</span>
                        <TooltipProvider delayDuration={200}>
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <a
                                href={pf.siteUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-muted-foreground hover:text-primary transition-colors"
                              >
                                <ExternalLink className="h-3.5 w-3.5" />
                              </a>
                            </TooltipTrigger>
                            <TooltipContent side="top" className="text-xs font-mono">
                              {pf.siteUrl}
                            </TooltipContent>
                          </Tooltip>
                        </TooltipProvider>
                      </div>
                      {pf.isReachable ? (
                        <Badge variant="secondary" className="text-xs px-2 py-0.5 bg-primary/10 text-primary border-primary/20">
                          <CheckCircle className="h-3 w-3 mr-1" /> Reachable
                        </Badge>
                      ) : (
                        <Badge variant="destructive" className="text-xs px-2 py-0.5">
                          <XCircle className="h-3 w-3 mr-1" /> Unreachable
                        </Badge>
                      )}
                    </div>

                    {/* Plugin details */}
                    {pf.isReachable && (
                      <div className="p-3.5 space-y-3">
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
                          <div className="flex items-center gap-2 text-sm text-destructive bg-destructive/10 p-3 rounded-md border border-destructive/20">
                            <AlertTriangle className="h-4 w-4 shrink-0" />
                            No upload endpoint available — deploy will fail
                          </div>
                        )}
                      </div>
                    )}

                    {pf.error && (
                      <div className="px-3.5 pb-3.5">
                        <p className="text-sm text-destructive">{pf.error}</p>
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
                <Copy className="h-3.5 w-3.5 mr-1.5" />
                Copy
              </Button>
            </div>
            <LogViewer logs={logs} className="h-64" />
            <div ref={logsEndRef} />
          </TabsContent>
        </Tabs>

        {/* Footer: Deploy + Refresh together, Refresh always visible */}
        <div className="flex items-center justify-between pt-4 border-t border-border/40">
          <div className="flex items-center gap-2">
            {preflightResults.length > 0 && (
              <span className="text-xs text-muted-foreground">
                {okChecks}/{totalPluginChecks} checks passed
              </span>
            )}
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={runPreflight} disabled={preflightLoading || status === DeployStatus.Deploying}>
              {preflightLoading ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <RefreshCw className="h-4 w-4 mr-1.5" />}
              Refresh
            </Button>
            {status === DeployStatus.Idle && (
              <Button onClick={handleDeploy} disabled={sites.length === 0}>
                <Upload className="h-4 w-4 mr-2" />
                Deploy to {sites.length} Site(s)
              </Button>
            )}
            {(status === DeployStatus.Completed || status === DeployStatus.Error) && (
              <>
                <Button
                  variant="outline"
                  onClick={() => {
                    setStatus(DeployStatus.Idle);
                    setDeployPhase("preflight");
                    setResults([]);
                    setLogs([]);
                    setPhaseTimings({});
                    setCompletedSiteIds(new Set());
                    stopTimer();
                  }}
                >
                  <RefreshCw className="h-4 w-4 mr-2" />
                  Deploy Again
                </Button>
                <Button variant="outline" onClick={() => onOpenChange(false)}>
                  Close
                </Button>
              </>
            )}
            {status === DeployStatus.Deploying && (
              <Button disabled>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Deploying...
              </Button>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

/* ── Compact plugin summary badge (shown in collapsed site row) ── */
function PluginSummaryBadge({
  label,
  available,
  remoteVersion,
  localVersion,
}: {
  label: string;
  available: boolean;
  remoteVersion?: string;
  localVersion?: string;
}) {
  const needsPublish = available && remoteVersion && localVersion && remoteVersion !== localVersion;
  const isUpToDate = available && remoteVersion && localVersion && remoteVersion === localVersion;

  return (
    <span className={`inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-md border font-medium ${
      available
        ? needsPublish
          ? "bg-warning/10 text-warning border-warning/20"
          : "bg-primary/10 text-primary border-primary/20"
        : "bg-muted text-muted-foreground border-border"
    }`}>
      {available ? <CheckCircle className="h-3 w-3" /> : <XCircle className="h-3 w-3" />}
      <span>{label}</span>
      {available && localVersion && (
        <span className="font-mono">v{localVersion}</span>
      )}
      {isUpToDate && (
        <span className="opacity-70">✓ up to date</span>
      )}
      {needsPublish && remoteVersion && (
        <>
          <span className="opacity-60">→</span>
          <span className="font-mono">v{remoteVersion}</span>
        </>
      )}
      {!available && (
        <span className="opacity-60">— not installed</span>
      )}
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
    <div className={`rounded-lg border p-3.5 space-y-2.5 ${
      available
        ? needsPublish
          ? "border-warning/30 bg-warning/5"
          : "border-border/60 bg-muted/20"
        : "border-border/40 bg-muted/10"
    }`}>
      {/* Plugin header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          {available ? (
            <CheckCircle className="h-4 w-4 text-primary shrink-0" />
          ) : (
            <XCircle className="h-4 w-4 text-muted-foreground shrink-0" />
          )}
          <span className="font-semibold text-sm text-foreground">{label}</span>
          <span className="text-muted-foreground text-xs">({sublabel})</span>
        </div>
        {preferred && available && !needsPublish && (
          <Badge variant="secondary" className="text-xs px-1.5 py-0 bg-primary/10 text-primary border-primary/20">
            ★ Preferred
          </Badge>
        )}
      </div>

      {!available && (
        <p className="text-sm text-muted-foreground italic">Not installed</p>
      )}

      {available && (
        <>
          {/* Version row: local → remote (pushing direction) */}
          <div className="flex items-center gap-2 flex-wrap">
            {localVersion && (
              <Badge variant="outline" className="text-xs px-2 py-0.5 font-mono border-foreground/20">
                local v{localVersion}
              </Badge>
            )}
            {localVersion && remoteVersion && (
              <span className="text-muted-foreground text-xs">→</span>
            )}
            {remoteVersion && (
              <Badge
                variant={needsPublish ? "destructive" : "secondary"}
                className="text-xs px-2 py-0.5 font-mono"
              >
                remote v{remoteVersion}
              </Badge>
            )}
            {versionUnknown && (
              <Badge variant="outline" className="text-xs px-2 py-0.5 text-muted-foreground border-muted-foreground/30">
                remote version unknown
              </Badge>
            )}
            {isUpToDate && (
              <span className="text-primary text-xs font-medium">(up to date)</span>
            )}
            {needsPublish && (
              <span className="text-warning text-xs font-medium flex items-center gap-1">
                <AlertTriangle className="h-3 w-3" /> Needs publish
              </span>
            )}
          </div>

          {/* Environment metadata */}
          {(plugin?.wpVersion || plugin?.phpVersion || plugin?.dbAvailable) && (
            <div className="flex items-center gap-3 text-muted-foreground flex-wrap text-xs">
              {plugin?.wpVersion && (
                <span className="flex items-center gap-1">
                  <Server className="h-3 w-3" /> WP {plugin.wpVersion}
                </span>
              )}
              {plugin?.phpVersion && (
                <span className="flex items-center gap-1">
                  PHP {plugin.phpVersion}
                </span>
              )}
              {plugin?.apiNamespace && (
                <span className="font-mono text-xs">
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

          {/* Server time + remote URL */}
          {(plugin?.serverTime || plugin?.remoteSiteUrl) && (
            <div className="flex items-center gap-3 text-muted-foreground text-xs">
              {plugin?.serverTime && (
                <span className="flex items-center gap-1">
                  <Clock className="h-3 w-3" />
                  {plugin.serverTime}
                </span>
              )}
              {plugin?.remoteSiteUrl && (
                <span className="flex items-center gap-1">
                  <Globe className="h-3 w-3" />
                  {plugin.remoteSiteUrl}
                </span>
              )}
            </div>
          )}

          {/* Status message / logs info */}
          {plugin?.message && plugin.status !== "OK" && (
            <div className="flex items-center gap-1.5 text-warning text-xs">
              <FileWarning className="h-3.5 w-3.5 shrink-0" />
              <span className="truncate">{plugin.message}</span>
            </div>
          )}
        </>
      )}
    </div>
  );
}
