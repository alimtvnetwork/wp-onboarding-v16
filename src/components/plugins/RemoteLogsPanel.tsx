import { useState, useCallback, useEffect, useMemo, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";


import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  FileText,
  Trash2,
  Mail,
  Loader2,
  RefreshCw,
  AlertTriangle,
  CheckCircle,
  
  Copy,
  Download,
  Eye,
  ScrollText,
  FlaskConical,
  XCircle,
  Code2,
  MoreVertical,
  X,
  Move,
} from "lucide-react";
import { api, requireSuccess } from "@/lib/api";
import type {
  RemoteLogsStatusResponse,
  RemoteLogsClearResponse,
  LogsRetrieveResult,
  PluginLogsData,
  LogRetrieveFileData,
} from "@/lib/api/types";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";
import { isApiClientError } from "@/lib/api";
import { ScrollArea } from "@/components/ui/scroll-area";
import { LogContentViewer } from "./LogContentViewer";
import { InlineErrorDiagnostic, extractDiagnostic, type InlineDiagnostic } from "./InlineErrorDiagnostic";
import { useDraggable } from "@/hooks/useDraggable";


interface RemoteLogsPanelProps {
  siteId: number;
  siteName?: string;
  autoOpen?: boolean;
  onClose?: () => void;
}

interface CapturedInlineError {
  diagnostic: InlineDiagnostic;
  originalError: unknown;
  endpoint: string;
  method: string;
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}

function surfaceError(err: unknown, fallbackEndpoint: string, fallbackMethod: string) {
  const { captureError, captureException, openErrorModal } = useErrorStore.getState();

  if (isApiClientError(err)) {
    const captured = captureError(err.apiError, {
      endpoint: err.meta.requestUrl,
      method: err.meta.method,
      requestBody: err.meta.requestBody,
      responseStatus: (err.apiError.context?.responseStatus as number | undefined) ?? undefined,
      context: { source: "RemoteLogsPanel" },
    });
    openErrorModal(captured);
    return;
  }

  const captured = captureException(err, {
    source: "RemoteLogsPanel",
    endpoint: fallbackEndpoint,
    method: fallbackMethod,
  });
  openErrorModal(captured);
}

// ── Plugin Logs Tab Content ────────────────────────────────────
function PluginLogsTabs({ plugin, showLabel }: { plugin: PluginLogsData; showLabel?: boolean }) {
  if (!plugin.available) {
    return (
      <div className="flex flex-col items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
        <AlertTriangle className="h-5 w-5 text-warning" />
        <p>Plugin not available on this site</p>
      </div>
    );
  }

  const infoLines = plugin.infoLog?.lines ?? 0;
  const errorLines = plugin.errorLog?.lines ?? 0;
  const stackLines = plugin.stacktrace?.lines ?? 0;

  // Auto-select the first non-empty log tab
  const defaultLogTab = infoLines > 0 ? "info" : errorLines > 0 ? "error" : stackLines > 0 ? "stacktrace" : "info";

  return (
    <div className="space-y-2">
      {showLabel && (
        <div className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-[0.1em] text-muted-foreground px-1">
          <ScrollText className="h-3 w-3" />
          {plugin.label}
        </div>
      )}
      <Tabs defaultValue={defaultLogTab} className="w-full">
        <TabsList className="w-full grid grid-cols-3 h-8 rounded-lg bg-muted/30 border border-border/40 p-0.5 gap-0.5">
          <TabsTrigger
            value="info"
            className="text-xs rounded-md gap-1.5 data-[state=active]:bg-background data-[state=active]:shadow-sm transition-all"
          >
            Info
            <span className={`text-[10px] tabular-nums ${infoLines > 0 ? "text-muted-foreground" : "opacity-40"}`}>
              {infoLines > 0 ? infoLines.toLocaleString() : "—"}
            </span>
          </TabsTrigger>
          <TabsTrigger
            value="error"
            className="text-xs rounded-md gap-1.5 data-[state=active]:bg-background data-[state=active]:shadow-sm data-[state=active]:text-destructive transition-all"
          >
            Error
            <span className={`text-[10px] tabular-nums ${errorLines > 0 ? "text-destructive/70" : "opacity-40"}`}>
              {errorLines > 0 ? errorLines.toLocaleString() : "—"}
            </span>
          </TabsTrigger>
          <TabsTrigger
            value="stacktrace"
            className="text-xs rounded-md gap-1.5 data-[state=active]:bg-background data-[state=active]:shadow-sm transition-all"
          >
            Trace
            <span className={`text-[10px] tabular-nums ${stackLines > 0 ? "text-muted-foreground" : "opacity-40"}`}>
              {stackLines > 0 ? stackLines.toLocaleString() : "—"}
            </span>
          </TabsTrigger>
        </TabsList>
        <TabsContent value="info" className="mt-3">
          <LogContentViewer file={plugin.infoLog} label="info log" />
        </TabsContent>
        <TabsContent value="error" className="mt-3">
          <LogContentViewer file={plugin.errorLog} label="error log" />
        </TabsContent>
        <TabsContent value="stacktrace" className="mt-3">
          <LogContentViewer file={plugin.stacktrace} label="stacktrace" />
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function RemoteLogsPanel({ siteId, siteName, autoOpen = false, onClose }: RemoteLogsPanelProps) {
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState<RemoteLogsStatusResponse | null>(null);
  const [isDemoMode, setIsDemoMode] = useState(false);
  const { style: dragStyle, onMouseDown: onDragMouseDown, onTouchStart: onDragTouchStart, resetPosition, isDragged } = useDraggable();

  // Retrieve state
  const [retrieveData, setRetrieveData] = useState<LogsRetrieveResult | null>(null);
  const [isRetrieving, setIsRetrieving] = useState(false);
  const [maxLines, setMaxLines] = useState(200);


  // Demo mode toggle
  const activateDemo = useCallback(async () => {
    const { createDemoLogsStatus, createDemoRetrieveResult } = await import("./demoRemoteLogsData");
    setStatus(createDemoLogsStatus());
    setRetrieveData(createDemoRetrieveResult());
    setIsDemoMode(true);
    toast.info("Demo mode activated — showing sample log data");
  }, []);

  const deactivateDemo = useCallback(() => {
    setStatus(null);
    setRetrieveData(null);
    setIsDemoMode(false);
    
    toast.info("Demo mode deactivated");
  }, []);

  // Auto-load demo data from sessionStorage (set by Settings page)
  useEffect(() => {
    const shouldActivate = sessionStorage.getItem("remoteLogs:demoActivate");
    if (shouldActivate === "true") {
      try {
        const storedStatus = sessionStorage.getItem("remoteLogs:demoStatus");
        const storedRetrieve = sessionStorage.getItem("remoteLogs:demoRetrieve");
        if (storedStatus && storedRetrieve) {
          setStatus(JSON.parse(storedStatus));
          setRetrieveData(JSON.parse(storedRetrieve));
          setIsDemoMode(true);
          toast.success("Demo mode auto-activated from Settings", {
            description: "Showing sample log data — no backend required.",
          });
        }
      } finally {
        sessionStorage.removeItem("remoteLogs:demoActivate");
        sessionStorage.removeItem("remoteLogs:demoStatus");
        sessionStorage.removeItem("remoteLogs:demoRetrieve");
      }
    }
  }, []);

  // Clear state
  const [clearToken, setClearToken] = useState<string | null>(null);
  const [clearExpiry, setClearExpiry] = useState<number>(0);
  const [isClearing, setIsClearing] = useState(false);
  const [isConfirming, setIsConfirming] = useState(false);
  const [isClearingAll, setIsClearingAll] = useState(false);

  // Email state
  const [showEmailDialog, setShowEmailDialog] = useState(false);
  const [emailRecipient, setEmailRecipient] = useState("");
  const [includeArchives, setIncludeArchives] = useState(false);
  const [isSendingEmail, setIsSendingEmail] = useState(false);

  // Inline error diagnostics
  const [inlineErrors, setInlineErrors] = useState<CapturedInlineError[]>([]);
  const [showPayloadInspector, setShowPayloadInspector] = useState(false);

  const captureInlineError = useCallback((err: unknown, endpoint: string, method: string) => {
    const diag = extractDiagnostic(err, endpoint, method);
    setInlineErrors((prev) => [
      { diagnostic: diag, originalError: err, endpoint, method },
      ...prev,
    ].slice(0, 5)); // keep last 5
  }, []);

  const dismissInlineError = useCallback((idx: number) => {
    setInlineErrors(prev => prev.filter((_, i) => i !== idx));
  }, []);

  const openInGlobalModal = useCallback((err: unknown, endpoint: string, method: string) => {
    surfaceError(err, endpoint, method);
  }, []);

  const fetchStatus = useCallback(async () => {
    if (isDemoMode) {
      toast.info("Demo mode — using sample data (no backend call)");
      return;
    }
    setIsLoading(true);
    try {
      const response = await api.getRemoteLogsStatus(siteId);
      const data = requireSuccess(response, { endpoint: `/sites/${siteId}/remote-logs`, method: "GET" });
      setStatus(data);
    } catch (err) {
      const message = err instanceof Error ? err.message.toLowerCase() : "";
      const isOutdatedLogsEndpoint =
        message.includes("/logs/status") &&
        (message.includes("status 404") || message.includes("not found"));

      if (isOutdatedLogsEndpoint) {
        setStatus({
          files: [],
          totalSizeBytes: 0,
          archiveCount: 0,
          pluginOutdated: true,
          outdatedMessage:
            "Remote plugin is outdated — the /logs/status endpoint is not available. Please update the plugin using Deploy Uploader.",
        });
      } else {
        captureInlineError(err, `/sites/${siteId}/remote-logs`, "GET");
      }
    } finally {
      setIsLoading(false);
    }
  }, [siteId, isDemoMode]);

  const fetchLogContent = useCallback(async () => {
    if (isDemoMode) {
      toast.info("Demo mode — using sample data (no backend call)");
      return;
    }

    const endpoint = `/sites/${siteId}/remote-logs/retrieve`;
    setIsRetrieving(true);
    try {
      const response = await api.retrieveRemoteLogs(siteId, { max_lines: maxLines });
      const data = requireSuccess(response, { endpoint, method: "GET" });
      setRetrieveData(data);

      const hasAvailablePlugin = data.plugins?.some((p) => p.available) ?? false;
      const hasReadableLogContent =
        data.plugins?.some((p) => p.infoLog?.exists || p.errorLog?.exists || p.stacktrace?.exists) ?? false;

      if (!hasAvailablePlugin) {
        toast.warning("No log retrieval endpoints available — the remote plugin may be outdated.");
        return;
      }

      if ((status?.files?.length ?? 0) > 0 && !hasReadableLogContent) {
        // Build a synthetic ApiError with response context so the Delegated Logs tab
        // shows the actual response body and endpoint info (see issue 018).
        const delegatedAt = response.envelope?.attributes?.RequestDelegatedAt;
        const responseBodyStr = JSON.stringify(data, null, 2);
        const mismatchApiError: import("@/lib/api/types").ApiError = {
          code: "E9003",
          message: "Remote logs status reported files, but retrieve returned no readable log content.",
          details: `Retrieve returned ${data.plugins?.length ?? 0} plugin(s) but none had readable log content. Status reported ${status?.files?.length ?? 0} file(s).`,
          context: {
            source: "RemoteLogsPanel",
            remoteResponseBody: responseBodyStr,
            ...(delegatedAt ? { requestDelegatedAt: delegatedAt } : {}),
            delegatedRequestServer: {
              DelegatedEndpoint: endpoint,
              Method: "GET",
              StatusCode: 200,
              Namespace: data.plugins?.[0]?.namespace || "",
            },
            statusFiles: status?.files?.map(f => `${f.name} (${f.lineCount} lines, ${f.sizeBytes}B)`) ?? [],
            retrievePlugins: data.plugins?.map(p => ({
              namespace: p.namespace,
              label: p.label,
              available: p.available,
               infoExists: p.infoLog?.exists,
               errorExists: p.errorLog?.exists,
               stacktraceExists: p.stacktrace?.exists,
            })) ?? [],
          },
          timestamp: new Date().toISOString(),
        };
        const { captureError, openErrorModal } = useErrorStore.getState();
        const mismatchError = new Error(mismatchApiError.message);
        captureInlineError(mismatchError, endpoint, "GET");
        const captured = captureError(mismatchApiError, {
          endpoint,
          method: "GET",
          responseStatus: 200,
          context: { source: "RemoteLogsPanel" },
        });
        openErrorModal(captured);
      }
    } catch (err) {
      setRetrieveData(null);
      captureInlineError(err, endpoint, "GET");
      surfaceError(err, endpoint, "GET");
    } finally {
      setIsRetrieving(false);
    }
  }, [siteId, maxLines, isDemoMode, status, captureInlineError]);

  // Auto-fetch status on mount
  useEffect(() => {
    if (!status) {
      fetchStatus();
    }
  }, [fetchStatus, status]);

  // ── Download All ──────────────────────────────────────────────
  const handleDownloadAll = () => {
    if (!retrieveData) return;

    const parts: string[] = [];
    for (const plugin of retrieveData.plugins) {
      if (!plugin.available) continue;
      const files = [
        { label: "INFO LOG", data: plugin.infoLog },
        { label: "ERROR LOG", data: plugin.errorLog },
        { label: "STACKTRACE", data: plugin.stacktrace },
      ];
      for (const f of files) {
        if (f.data?.exists && f.data.content) {
          parts.push(`========== ${plugin.label} — ${f.label} ==========\n${f.data.content}\n`);
        }
      }
    }

    if (parts.length === 0) {
      toast.info("No log content to download");
      return;
    }

    const blob = new Blob([parts.join("\n")], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `logs-site-${siteId}-${new Date().toISOString().slice(0, 10)}.txt`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Logs downloaded");
  };

  // ── Two-Step Clear ────────────────────────────────────────────
  const handleClearStep1 = async () => {
    setIsClearing(true);
    try {
      const response = await api.clearRemoteLogs(siteId);
      const data = requireSuccess(response, { endpoint: `/sites/${siteId}/remote-logs/clear`, method: "DELETE" });
      setClearToken(data.token);
      setClearExpiry(data.expiresIn);
      toast.info("Clear token issued — confirm within " + data.expiresIn + "s");
    } catch (err) {
      captureInlineError(err, `/sites/${siteId}/remote-logs/clear`, "DELETE");
    } finally {
      setIsClearing(false);
    }
  };

  const handleClearConfirm = async () => {
    if (!clearToken) return;
    setIsConfirming(true);
    try {
      const response = await api.confirmClearRemoteLogs(siteId, clearToken);
      requireSuccess(response, { endpoint: `/sites/${siteId}/remote-logs/clear/confirm`, method: "POST" });
      toast.success("Remote logs cleared successfully");
      setClearToken(null);
      await fetchStatus();
    } catch (err) {
      captureInlineError(err, `/sites/${siteId}/remote-logs/clear/confirm`, "POST");
    } finally {
      setIsConfirming(false);
    }
  };

  const handleClearCancel = () => setClearToken(null);

  // ── Clear All (both plugins) ──────────────────────────────────
  const handleClearAllPlugins = async () => {
    if (!confirm("Clear all logs for both plugins (Riseup Asia + QUpload)?")) return;
    setIsClearingAll(true);
    try {
      const response = await api.clearAllRemoteLogs(siteId);
      const data = requireSuccess(response, { endpoint: `/sites/${siteId}/remote-logs/clear-all`, method: "POST" });
      const rOk = data.riseup?.cleared;
      const qOk = data.qupload?.cleared;
      if (rOk && qOk) {
        toast.success("All logs cleared for both plugins");
      } else {
        const failures: string[] = [];
        if (!rOk) failures.push(`Riseup: ${data.riseup?.error || "failed"}`);
        if (!qOk) failures.push(`QUpload: ${data.qupload?.error || "failed"}`);
        toast.warning(`Partial clear: ${failures.join("; ")}`);
      }
      await fetchStatus();
    } catch (err) {
      captureInlineError(err, `/sites/${siteId}/remote-logs/clear-all`, "POST");
    } finally {
      setIsClearingAll(false);
    }
  };

  // ── Email Logs ────────────────────────────────────────────────
  const handleSendEmail = async () => {
    setIsSendingEmail(true);
    try {
      const response = await api.emailRemoteLogs(siteId, {
        recipient: emailRecipient || undefined,
        include_archives: includeArchives,
      });
      requireSuccess(response, { endpoint: `/sites/${siteId}/remote-logs/email`, method: "POST" });
      toast.success("Logs emailed successfully");
      setShowEmailDialog(false);
      setEmailRecipient("");
      setIncludeArchives(false);
    } catch (err) {
      captureInlineError(err, `/sites/${siteId}/remote-logs/email`, "POST");
    } finally {
      setIsSendingEmail(false);
    }
  };

  const hasFiles = status?.files && status.files.length > 0;
  const availablePlugins = useMemo(() => {
    const plugins = retrieveData?.plugins.filter((p) => p.available) ?? [];
    const pluginScore = (plugin: PluginLogsData) =>
      (plugin.infoLog?.lines ?? 0) + (plugin.errorLog?.lines ?? 0) + (plugin.stacktrace?.lines ?? 0);

    return [...plugins].sort((a, b) => pluginScore(b) - pluginScore(a));
  }, [retrieveData]);
  const totalLoadedBytes = useMemo(() => {
    return availablePlugins.reduce((sum, plugin) => {
      return sum + (plugin.infoLog?.totalSize ?? 0) + (plugin.errorLog?.totalSize ?? 0) + (plugin.stacktrace?.totalSize ?? 0);
    }, 0);
  }, [availablePlugins]);



  return (
    <>
      {/* Fixed overlay backdrop */}
      <div className="fixed inset-0 z-50 bg-black/60" onClick={onClose} />
      <Card
        data-error-modal
        style={dragStyle}
        className="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-50 w-[95vw] max-w-5xl max-h-[90vh] overflow-y-auto border-2 border-border/70 bg-gradient-to-br from-background via-background to-muted/20 shadow-2xl rounded-xl"
      >
        {/* Draggable header */}
        <CardHeader
          className="cursor-grab active:cursor-grabbing select-none rounded-t-xl border-b border-border/60 bg-muted/20 transition-colors hover:bg-muted/30 py-3 px-4"
          onMouseDown={onDragMouseDown}
          onTouchStart={onDragTouchStart}
        >
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <FileText className="h-4 w-4 text-muted-foreground" />
              <CardTitle className="text-base font-semibold">Remote Logs</CardTitle>
              {siteName && <span className="text-sm text-muted-foreground">— {siteName}</span>}
            </div>
            <div className="flex items-center gap-2">
              {isDemoMode && (
                <Badge variant="outline" className="text-[10px] border-warning/40 bg-warning/15 text-warning">
                  <FlaskConical className="h-3 w-3 mr-1" /> Demo
                </Badge>
              )}
              {status && (
                <Badge variant="secondary" className="text-xs border-primary/20 bg-primary/10 text-primary">
                  {formatBytes(status.totalSizeBytes || totalLoadedBytes)}
                  {status.archiveCount > 0 && (
                    <span className="ml-1 text-muted-foreground">
                      · {status.archiveCount} archived
                    </span>
                  )}
                </Badge>
              )}
              {isDragged && (
                <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={resetPosition} title="Reset position">
                  <Move className="h-3 w-3" />
                </Button>
              )}
              {onClose && (
                <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={onClose}>
                  <X className="h-4 w-4" />
                </Button>
              )}
            </div>
          </div>
        </CardHeader>

        <CardContent className="pt-5">
            {/* Clear confirmation bar (shows when clear token is active) */}
            {clearToken && (
              <div className="flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 mb-4 text-xs">
                <AlertTriangle className="h-3.5 w-3.5 text-destructive shrink-0" />
                <span className="text-destructive font-medium">Confirm log deletion?</span>
                <Button size="sm" variant="destructive" onClick={handleClearConfirm} disabled={isConfirming} className="h-6 px-2 text-xs ml-auto">
                  {isConfirming ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : null}
                  Confirm ({clearExpiry}s)
                </Button>
                <Button size="sm" variant="ghost" onClick={handleClearCancel} className="h-6 px-2 text-xs">Cancel</Button>
              </div>
            )}

            {/* Inline Error Diagnostics */}
            {inlineErrors.length > 0 && (
              <div className="space-y-3 mb-5">
                {inlineErrors.map(({ diagnostic, originalError, endpoint, method }, idx) => (
                  <InlineErrorDiagnostic
                    key={`${diagnostic.timestamp}-${idx}`}
                    diagnostic={diagnostic}
                    onDismiss={() => dismissInlineError(idx)}
                    onOpenGlobalModal={() => openInGlobalModal(originalError, endpoint, method)}
                  />
                ))}
              </div>
            )}

            {/* Loading */}
            {isLoading && (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              </div>
            )}

            {/* Plugin Outdated Warning */}
            {!isLoading && status?.pluginOutdated && (
              <div className="flex items-center gap-2 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-3 text-sm">
                <AlertTriangle className="h-4 w-4 text-destructive shrink-0" />
                <div>
                  <p className="font-medium text-destructive">Plugin Outdated</p>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    {status.outdatedMessage || "The remote plugin does not support this endpoint. Please update using Deploy Uploader."}
                  </p>
                </div>
              </div>
            )}

            {/* Unified View — Toolbar + Content */}
            {!isLoading && status && !status.pluginOutdated && (
              <div className="space-y-4">
                {/* Single Toolbar */}
                <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border/60 bg-muted/20 px-3 py-2">
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant={retrieveData ? "outline" : "default"}
                      onClick={fetchLogContent}
                      disabled={isRetrieving || (!retrieveData && !hasFiles)}
                      className="h-8"
                    >
                      {isRetrieving ? (
                        <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                      ) : retrieveData ? (
                        <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                      ) : (
                        <Eye className="mr-1.5 h-3.5 w-3.5" />
                      )}
                      {retrieveData ? "Reload" : "Load Logs"}
                    </Button>
                    <Select value={String(maxLines)} onValueChange={(v) => setMaxLines(Number(v))}>
                      <SelectTrigger className="h-8 w-[110px] text-xs">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {[50, 100, 200, 500, 1000, 2000].map((n) => (
                          <SelectItem key={n} value={String(n)} className="text-xs">{n} lines</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="flex items-center gap-1.5">
                    {retrieveData && (
                      <Button size="sm" variant="outline" onClick={handleDownloadAll} disabled={availablePlugins.length === 0} className="h-8 text-xs">
                        <Download className="mr-1.5 h-3.5 w-3.5" /> Download
                      </Button>
                    )}
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button size="sm" variant="ghost" className="h-8 w-8 p-0">
                          <MoreVertical className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuItem onClick={fetchStatus} disabled={isLoading}>
                          <RefreshCw className="mr-2 h-3.5 w-3.5" /> Refresh Status
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setShowPayloadInspector(v => !v)} disabled={!retrieveData}>
                          <Code2 className="mr-2 h-3.5 w-3.5" /> {showPayloadInspector ? "Hide" : "Inspect"} Payload
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={() => setShowEmailDialog(true)} disabled={!hasFiles}>
                          <Mail className="mr-2 h-3.5 w-3.5" /> Email Logs
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={handleClearStep1} disabled={isClearing || !hasFiles} className="text-destructive focus:text-destructive">
                          <Trash2 className="mr-2 h-3.5 w-3.5" /> Clear Logs
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={handleClearAllPlugins} disabled={isClearingAll} className="text-destructive focus:text-destructive">
                          <Trash2 className="mr-2 h-3.5 w-3.5" /> Clear All Plugins
                        </DropdownMenuItem>
                        {isDemoMode && (
                          <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={deactivateDemo}>
                              <XCircle className="mr-2 h-3.5 w-3.5" /> Exit Demo
                            </DropdownMenuItem>
                          </>
                        )}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>

                {/* File overview (compact, before logs are loaded) */}
                {!retrieveData && !isRetrieving && hasFiles && (
                  <div className="flex items-center gap-2 rounded-xl border border-border/60 bg-muted/10 px-3 py-2.5 text-xs text-muted-foreground">
                    <FileText className="h-3.5 w-3.5 shrink-0" />
                    <span>{status.files.length} file{status.files.length !== 1 ? "s" : ""}</span>
                    <span className="opacity-30">·</span>
                    <span>{formatBytes(status.totalSizeBytes)}</span>
                    {status.archiveCount > 0 && (
                      <>
                        <span className="opacity-30">·</span>
                        <span>{status.archiveCount} archived</span>
                      </>
                    )}
                  </div>
                )}

                {!retrieveData && !isRetrieving && !hasFiles && (
                  <div className="flex flex-col items-center gap-2 rounded-xl border border-primary/20 bg-primary/10 py-6 text-sm text-muted-foreground">
                    <CheckCircle className="h-5 w-5 text-primary" />
                    No log files found
                  </div>
                )}

                {/* Loading skeleton */}
                {isRetrieving && !retrieveData && (
                  <div className="space-y-4 animate-pulse rounded-xl border border-border/40 bg-muted/10 p-4">
                    <div className="flex items-center gap-2">
                      <div className="h-5 w-24 rounded-full bg-muted" />
                      <div className="h-5 w-16 rounded-full bg-muted" />
                    </div>
                    <div className="h-10 w-full rounded-xl bg-muted/70" />
                    <div className="h-[400px] rounded-xl border border-border/50 bg-background/70 p-4 space-y-2">
                      {Array.from({ length: 12 }).map((_, i) => (
                        <div key={i} className="h-3 rounded bg-muted" style={{ width: `${60 + Math.random() * 40}%` }} />
                      ))}
                    </div>
                  </div>
                )}

                {/* Inline errors */}
                {!retrieveData && !isRetrieving && inlineErrors.length > 0 && (
                  <div className="space-y-3">
                    {inlineErrors.map(({ diagnostic, originalError, endpoint, method }, idx) => (
                      <InlineErrorDiagnostic
                        key={`viewer-${diagnostic.timestamp}-${idx}`}
                        diagnostic={diagnostic}
                        onDismiss={() => dismissInlineError(idx)}
                        onOpenGlobalModal={() => openInGlobalModal(originalError, endpoint, method)}
                      />
                    ))}
                  </div>
                )}

                {/* Loaded log content */}
                {retrieveData && (
                  <>
                    {/* Summary banner */}
                    {availablePlugins.length > 0 && (
                      <div className="flex flex-wrap items-center gap-2 rounded-xl border border-border/40 bg-muted/15 px-3 py-2">
                        <ScrollText className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                        {availablePlugins.map((p) => {
                          const info = p.infoLog?.lines ?? 0;
                          const err = p.errorLog?.lines ?? 0;
                          const stack = p.stacktrace?.lines ?? 0;
                          return (
                            <div key={p.namespace} className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                              {availablePlugins.length > 1 && (
                                <span className="font-medium text-foreground/70">{p.label}:</span>
                              )}
                              <span className={info > 0 ? "text-foreground" : "opacity-50"}>
                                Info {info > 0 ? `${info}` : "—"}
                              </span>
                              <span className="opacity-30">·</span>
                              <span className={err > 0 ? "text-destructive" : "opacity-50"}>
                                Error {err > 0 ? `${err}` : "—"}
                              </span>
                              <span className="opacity-30">·</span>
                              <span className={stack > 0 ? "text-foreground" : "opacity-50"}>
                                Trace {stack > 0 ? `${stack}` : "—"}
                              </span>
                              {availablePlugins.length > 1 && <span className="opacity-20 mx-1">|</span>}
                            </div>
                          );
                        })}

                      </div>
                    )}

                    {/* Raw Payload Inspector */}
                    {showPayloadInspector && (
                      <div className="rounded-xl border border-border/60 bg-muted/10 p-3 space-y-2">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <Code2 className="h-3.5 w-3.5 text-muted-foreground" />
                            <span className="text-xs font-medium uppercase tracking-[0.15em] text-muted-foreground">Raw Payload</span>
                            <Badge variant="outline" className="text-[10px] font-mono px-1.5 h-4">
                              {JSON.stringify(retrieveData).length.toLocaleString()} chars
                            </Badge>
                          </div>
                          <Button
                            size="sm"
                            variant="ghost"
                            className="h-6 px-2 text-[11px]"
                            onClick={() => {
                              navigator.clipboard.writeText(JSON.stringify(retrieveData, null, 2));
                              toast.success("Raw payload copied to clipboard");
                            }}
                          >
                            <Copy className="h-3 w-3 mr-1" /> Copy
                          </Button>
                        </div>
                        <ScrollArea className="h-[300px] rounded-lg border border-border/40 bg-background/80">
                          <pre className="p-3 text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all leading-relaxed">
                            {JSON.stringify(retrieveData, null, 2)}
                          </pre>
                        </ScrollArea>
                      </div>
                    )}

                    {/* Plugin tabs + log content */}
                    {availablePlugins.length > 1 ? (
                      <Tabs defaultValue={availablePlugins[0]?.namespace} className="w-full">
                        <TabsList className="w-full grid grid-cols-2 h-8 rounded-lg bg-muted/30 border border-border/40 p-0.5 gap-0.5">
                          {availablePlugins.map((p) => {
                            const total = (p.infoLog?.lines ?? 0) + (p.errorLog?.lines ?? 0) + (p.stacktrace?.lines ?? 0);
                            return (
                              <TabsTrigger
                                key={p.namespace}
                                value={p.namespace}
                                className="text-xs rounded-md gap-1.5 data-[state=active]:bg-background data-[state=active]:shadow-sm transition-all"
                              >
                                {p.label}
                                {total > 0 && (
                                  <span className="text-[10px] tabular-nums text-muted-foreground">{total.toLocaleString()}</span>
                                )}
                              </TabsTrigger>
                            );
                          })}
                        </TabsList>
                        {availablePlugins.map((p) => (
                          <TabsContent key={p.namespace} value={p.namespace} className="mt-3">
                            <PluginLogsTabs plugin={p} />
                          </TabsContent>
                        ))}
                      </Tabs>
                    ) : availablePlugins.length === 1 ? (
                      <PluginLogsTabs plugin={availablePlugins[0]} showLabel />
                    ) : (
                      <div className="flex flex-col items-center gap-2 rounded-xl border border-warning/30 bg-warning/10 py-8 text-muted-foreground">
                        <AlertTriangle className="h-5 w-5 text-warning" />
                        <p className="text-sm">No plugin log endpoints available on this site.</p>
                      </div>
                    )}
                  </>
                )}


              </div>
            )}
          </CardContent>
      </Card>

      {/* Email Dialog */}
      <Dialog open={showEmailDialog} onOpenChange={setShowEmailDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Email Log Files</DialogTitle>
            <DialogDescription>
              Send log files as attachments{siteName ? ` for ${siteName}` : ""}.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="email-recipient">Recipient (optional — defaults to admin)</Label>
              <Input
                id="email-recipient"
                type="email"
                placeholder="admin@example.com"
                value={emailRecipient}
                onChange={(e) => setEmailRecipient(e.target.value)}
              />
            </div>
            <div className="flex items-center gap-2">
              <Checkbox
                id="include-archives"
                checked={includeArchives}
                onCheckedChange={(checked) => setIncludeArchives(checked === true)}
              />
              <Label htmlFor="include-archives" className="text-sm">Include archived log rotations</Label>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowEmailDialog(false)}>Cancel</Button>
            <Button onClick={handleSendEmail} disabled={isSendingEmail}>
              {isSendingEmail ? (
                <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
              ) : (
                <Mail className="mr-1.5 h-3.5 w-3.5" />
              )}
              Send Email
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
