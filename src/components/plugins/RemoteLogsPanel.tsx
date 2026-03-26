import { useState, useCallback, useEffect, useMemo, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
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
  FileText,
  Trash2,
  Mail,
  Loader2,
  ChevronDown,
  ChevronRight,
  RefreshCw,
  AlertTriangle,
  CheckCircle,
  Archive,
  Copy,
  Download,
  Eye,
  Settings,
  ScrollText,
  Zap,
  FlaskConical,
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

interface RemoteLogsPanelProps {
  siteId: number;
  siteName?: string;
  autoOpen?: boolean;
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
function PluginLogsTabs({ plugin }: { plugin: PluginLogsData }) {
  if (!plugin.available) {
    return (
      <div className="flex flex-col items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
        <AlertTriangle className="h-5 w-5 text-yellow-500" />
        <p>Plugin not available on this site</p>
      </div>
    );
  }

  const infoLines = plugin.infoLog?.Lines ?? 0;
  const errorLines = plugin.errorLog?.Lines ?? 0;
  const stackLines = plugin.stacktrace?.Lines ?? 0;

  // Auto-select the first non-empty log tab
  const defaultLogTab = infoLines > 0 ? "info" : errorLines > 0 ? "error" : stackLines > 0 ? "stacktrace" : "info";

  return (
    <Tabs defaultValue={defaultLogTab} className="w-full">
      <TabsList className="w-full grid grid-cols-3 h-9">
        <TabsTrigger value="info" className="text-xs gap-1.5">
          Info {infoLines > 0 && <Badge variant="secondary" className="text-[10px] px-1 h-4">{infoLines}</Badge>}
        </TabsTrigger>
        <TabsTrigger value="error" className="text-xs gap-1.5">
          Error {errorLines > 0 && <Badge variant="destructive" className="text-[10px] px-1 h-4">{errorLines}</Badge>}
        </TabsTrigger>
        <TabsTrigger value="stacktrace" className="text-xs gap-1.5">
          Trace {stackLines > 0 && <Badge variant="secondary" className="text-[10px] px-1 h-4">{stackLines}</Badge>}
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
  );
}

export function RemoteLogsPanel({ siteId, siteName, autoOpen = false }: RemoteLogsPanelProps) {
  const [isOpen, setIsOpen] = useState(autoOpen);
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState<RemoteLogsStatusResponse | null>(null);
  const [isDemoMode, setIsDemoMode] = useState(false);

  // Retrieve state
  const [retrieveData, setRetrieveData] = useState<LogsRetrieveResult | null>(null);
  const [isRetrieving, setIsRetrieving] = useState(false);
  const [maxLines, setMaxLines] = useState(200);

  // Active top-level tab
  const [activeTab, setActiveTab] = useState("overview");

  // Demo mode toggle
  const activateDemo = useCallback(async () => {
    const { createDemoLogsStatus, createDemoRetrieveResult } = await import("./demoRemoteLogsData");
    setStatus(createDemoLogsStatus());
    setRetrieveData(createDemoRetrieveResult());
    setIsDemoMode(true);
    setIsOpen(true);
    setActiveTab("viewer");
    toast.info("Demo mode activated — showing sample log data");
  }, []);

  const deactivateDemo = useCallback(() => {
    setStatus(null);
    setRetrieveData(null);
    setIsDemoMode(false);
    setActiveTab("overview");
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
          setIsOpen(true);
          setActiveTab("viewer");
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
  const [inlineErrors, setInlineErrors] = useState<InlineDiagnostic[]>([]);

  const captureInlineError = useCallback((err: unknown, endpoint: string, method: string) => {
    const diag = extractDiagnostic(err, endpoint, method);
    setInlineErrors(prev => [diag, ...prev].slice(0, 5)); // keep last 5
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
  }, [siteId]);

  const fetchLogContent = useCallback(async () => {
    if (isDemoMode) {
      toast.info("Demo mode — using sample data (no backend call)");
      return;
    }
    setIsRetrieving(true);
    try {
      const response = await api.retrieveRemoteLogs(siteId, { max_lines: maxLines });
      const data = requireSuccess(response, { endpoint: `/sites/${siteId}/remote-logs/retrieve`, method: "GET" });
      setRetrieveData(data);
      setActiveTab("viewer");

      const hasAnyContent = data.plugins?.some(p => p.available);
      if (!hasAnyContent) {
        toast.warning("No log retrieval endpoints available — the remote plugin may be outdated.");
      }
    } catch (err) {
      captureInlineError(err, `/sites/${siteId}/remote-logs/retrieve`, "GET");
      setActiveTab("viewer");
    } finally {
      setIsRetrieving(false);
    }
  }, [siteId, maxLines]);

  useEffect(() => {
    if (autoOpen && !status) {
      fetchStatus();
    }
  }, [autoOpen, fetchStatus, status]);

  const handleOpen = useCallback(
    (open: boolean) => {
      setIsOpen(open);
      if (open && !status) {
        fetchStatus();
      }
    },
    [status, fetchStatus]
  );

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
        if (f.data?.Exists && f.data.Content) {
          parts.push(`========== ${plugin.label} — ${f.label} ==========\n${f.data.Content}\n`);
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
  const availablePlugins = retrieveData?.plugins.filter(p => p.available) ?? [];
  const totalLoadedBytes = useMemo(() => {
    return availablePlugins.reduce((sum, plugin) => {
      return sum + (plugin.infoLog?.TotalSize ?? 0) + (plugin.errorLog?.TotalSize ?? 0) + (plugin.stacktrace?.TotalSize ?? 0);
    }, 0);
  }, [availablePlugins]);

  // Detect mismatch: status says files exist with content but retrieve returned all-empty
  const statusHasContent = status?.files?.some(f => f.lineCount > 0) ?? false;
  const retrieveHasContent = availablePlugins.some(p =>
    (p.infoLog?.Exists && (p.infoLog?.Lines ?? 0) > 0) ||
    (p.errorLog?.Exists && (p.errorLog?.Lines ?? 0) > 0) ||
    (p.stacktrace?.Exists && (p.stacktrace?.Lines ?? 0) > 0)
  );
  const hasMismatch = retrieveData && statusHasContent && !retrieveHasContent;

  return (
    <Collapsible open={isOpen} onOpenChange={handleOpen}>
      <Card className="border-2 border-border/70 bg-gradient-to-br from-background via-background to-muted/20 shadow-2xl">
        <CollapsibleTrigger asChild>
          <CardHeader className="cursor-pointer rounded-t-xl border-b border-border/60 bg-muted/20 transition-colors hover:bg-muted/30">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                {isOpen ? (
                  <ChevronDown className="h-4 w-4 text-muted-foreground" />
                ) : (
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                )}
                <FileText className="h-4 w-4 text-muted-foreground" />
                <CardTitle className="text-base font-semibold">Remote Logs</CardTitle>
                {siteName && <span className="text-sm text-muted-foreground">— {siteName}</span>}
              </div>
              <div className="flex items-center gap-2">
                {isDemoMode && (
                  <Badge variant="outline" className="text-[10px] border-amber-500/40 bg-amber-500/15 text-amber-400">
                    <FlaskConical className="h-3 w-3 mr-1" /> Demo
                  </Badge>
                )}
                <Button
                  size="sm"
                  variant="ghost"
                  className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                  onClick={(e) => {
                    e.stopPropagation();
                    isDemoMode ? deactivateDemo() : activateDemo();
                  }}
                >
                  <FlaskConical className="h-3.5 w-3.5 mr-1" />
                  {isDemoMode ? "Exit Demo" : "Demo"}
                </Button>
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
              </div>
            </div>
          </CardHeader>
        </CollapsibleTrigger>

        <CollapsibleContent>
          <CardContent className="pt-5">
            {/* Demo Mode Banner */}
            {isDemoMode && (
              <div className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 mb-4 text-xs text-amber-400">
                <FlaskConical className="h-3.5 w-3.5 shrink-0" />
                <span className="font-medium">Demo Mode</span>
                <span className="text-muted-foreground">— Showing sample data. No backend connection required.</span>
                <Button size="sm" variant="ghost" className="ml-auto h-6 px-2 text-xs text-amber-400 hover:text-amber-300" onClick={deactivateDemo}>
                  Exit Demo
                </Button>
              </div>
            )}

            {/* Inline Error Diagnostics */}
            {inlineErrors.length > 0 && (
              <div className="space-y-3 mb-5">
                {inlineErrors.map((diag, idx) => (
                  <InlineErrorDiagnostic
                    key={`${diag.timestamp}-${idx}`}
                    diagnostic={diag}
                    onDismiss={() => dismissInlineError(idx)}
                    onOpenGlobalModal={() => {
                      // Re-surface in the global modal for the full experience
                      const { captureException, openErrorModal } = useErrorStore.getState();
                      const captured = captureException(new Error(diag.message), {
                        source: "RemoteLogsPanel",
                        endpoint: diag.endpoint,
                        method: diag.method,
                      });
                      openErrorModal(captured);
                    }}
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

            {/* Main Tabbed Interface */}
            {!isLoading && status && !status.pluginOutdated && (
              <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full space-y-5">
                <TabsList className="grid w-full grid-cols-3 h-12 rounded-xl border border-border/60 bg-muted/30 p-1">
                  <TabsTrigger value="overview" className="text-xs gap-1.5">
                    <ScrollText className="h-3.5 w-3.5" />
                    Overview
                  </TabsTrigger>
                  <TabsTrigger value="viewer" className="text-xs gap-1.5">
                    <Eye className="h-3.5 w-3.5" />
                    Viewer
                    {retrieveData && <Badge variant="secondary" className="text-[10px] px-1 h-4 ml-1">✓</Badge>}
                  </TabsTrigger>
                  <TabsTrigger value="actions" className="text-xs gap-1.5">
                    <Zap className="h-3.5 w-3.5" />
                    Actions
                  </TabsTrigger>
                </TabsList>

                {/* ── OVERVIEW TAB ──────────────────────────────── */}
                <TabsContent value="overview" className="mt-0 space-y-5">
                  <div className="grid gap-3 md:grid-cols-3">
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                      <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Total Size</p>
                      <p className="mt-2 text-2xl font-semibold text-foreground">{formatBytes(status.totalSizeBytes)}</p>
                    </div>
                    <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                      <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Log Files</p>
                      <p className="mt-2 text-2xl font-semibold text-foreground">{status.files.length}</p>
                    </div>
                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                      <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Archives</p>
                      <p className="mt-2 text-2xl font-semibold text-foreground">{status.archiveCount}</p>
                    </div>
                  </div>

                  {hasFiles ? (
                    <div className="space-y-2 rounded-xl border border-border/60 bg-muted/10 p-3">
                      {status.files.map((file) => (
                        <div
                          key={file.name}
                          className="flex items-center justify-between rounded-xl border border-border/50 bg-background/70 px-4 py-3 text-sm shadow-sm"
                        >
                          <span className="font-mono text-xs text-foreground">{file.name}</span>
                          <div className="flex items-center gap-3 text-xs text-muted-foreground">
                            <span>{file.lineCount.toLocaleString()} lines</span>
                            <Badge variant="outline" className="text-xs font-mono">{formatBytes(file.sizeBytes)}</Badge>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-primary/20 bg-primary/10 py-8 text-sm text-muted-foreground">
                      <CheckCircle className="h-5 w-5 text-primary" />
                      No log files found
                    </div>
                  )}

                  {status.archiveCount > 0 && (
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                      <Archive className="h-3.5 w-3.5" />
                      {status.archiveCount} archived rotation{status.archiveCount !== 1 ? "s" : ""}
                    </div>
                  )}

                  {/* Quick actions in overview */}
                  <div className="flex flex-wrap items-center gap-2 border-t border-border/50 pt-3">
                    <Button size="sm" variant="outline" onClick={fetchStatus} disabled={isLoading}>
                      <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Refresh
                    </Button>
                    <Button
                      size="sm"
                      variant="default"
                      onClick={fetchLogContent}
                      disabled={isRetrieving || !hasFiles}
                    >
                      {isRetrieving ? (
                        <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                      ) : (
                        <Eye className="mr-1.5 h-3.5 w-3.5" />
                      )}
                      View Logs
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
                </TabsContent>

                {/* ── VIEWER TAB ────────────────────────────────── */}
                <TabsContent value="viewer" className="mt-0 space-y-4">
                  {isRetrieving && !retrieveData ? (
                    <div className="space-y-4 animate-pulse rounded-xl border border-amber-500/20 bg-gradient-to-br from-amber-500/10 via-muted/20 to-background p-4">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <div className="h-9 w-24 rounded-lg bg-muted" />
                          <div className="h-9 w-[120px] rounded-lg bg-muted" />
                        </div>
                        <div className="h-9 w-32 rounded-lg bg-muted" />
                      </div>
                      <div className="h-11 w-full rounded-xl bg-muted" />
                      <div className="h-10 w-full rounded-xl bg-muted/70" />
                      <div className="flex items-center gap-2">
                        <div className="h-5 w-24 rounded-full bg-muted" />
                        <div className="h-5 w-16 rounded-full bg-muted" />
                      </div>
                      <div className="h-[460px] rounded-xl border border-border/50 bg-background/70 p-4 space-y-2">
                        {Array.from({ length: 12 }).map((_, i) => (
                          <div key={i} className="h-3 rounded bg-muted" style={{ width: `${60 + Math.random() * 40}%` }} />
                        ))}
                      </div>
                    </div>
                  ) : !retrieveData ? (
                    <div className="space-y-4">
                      {/* Show inline errors directly in the viewer tab when fetch failed */}
                      {inlineErrors.length > 0 && (
                        <div className="space-y-3">
                          {inlineErrors.map((diag, idx) => (
                            <InlineErrorDiagnostic
                              key={`viewer-${diag.timestamp}-${idx}`}
                              diagnostic={diag}
                              onDismiss={() => dismissInlineError(idx)}
                              onOpenGlobalModal={() => {
                                const { captureException, openErrorModal } = useErrorStore.getState();
                                const captured = captureException(new Error(diag.message), {
                                  source: "RemoteLogsPanel",
                                  endpoint: diag.endpoint,
                                  method: diag.method,
                                });
                                openErrorModal(captured);
                              }}
                            />
                          ))}
                        </div>
                      )}
                      <div className="flex flex-col items-center gap-3 rounded-xl border border-border/60 bg-muted/10 py-12 text-muted-foreground">
                        <Eye className="h-10 w-10 opacity-30" />
                        <p className="text-sm">{inlineErrors.length > 0 ? "Log retrieval failed" : "No logs loaded yet"}</p>
                        <p className="max-w-md text-center text-xs text-muted-foreground">
                          {inlineErrors.length > 0
                            ? "The endpoint returned an error. Review the diagnostic above or retry."
                            : "Load the latest remote logs to inspect info, error, and stacktrace output in a larger viewer."}
                        </p>
                        <Button size="sm" variant="outline" onClick={fetchLogContent} disabled={isRetrieving || !hasFiles}>
                          <Eye className="mr-1.5 h-3.5 w-3.5" />
                          {inlineErrors.length > 0 ? "Retry" : "Load Logs"}
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <>
                      {/* Toolbar */}
                      <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-muted/20 p-3">
                        <div className="flex items-center gap-2">
                          <Button size="sm" variant="outline" onClick={fetchLogContent} disabled={isRetrieving}>
                            {isRetrieving ? (
                              <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                            ) : (
                              <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                            )}
                            Reload
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
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={handleDownloadAll}
                          disabled={availablePlugins.length === 0}
                        >
                          <Download className="mr-1.5 h-3.5 w-3.5" /> Download All
                        </Button>
                      </div>

                      {/* Mismatch Warning */}
                      {hasMismatch && (
                        <div className="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3">
                          <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
                          <div className="space-y-1">
                            <p className="text-sm font-medium text-amber-400">Data mismatch detected</p>
                            <p className="text-xs text-muted-foreground">
                              The Overview shows log files with content ({status?.files?.filter(f => f.lineCount > 0).map(f => `${f.name}: ${f.lineCount} lines`).join(", ")}),
                              but the retrieve endpoint returned no file data. This usually means the status and retrieve endpoints are hitting different plugin namespaces,
                              or the remote plugin needs updating.
                            </p>
                            <Button size="sm" variant="outline" className="mt-2 h-7 text-xs border-amber-500/30" onClick={fetchLogContent}>
                              <RefreshCw className="mr-1.5 h-3 w-3" /> Retry Retrieval
                            </Button>
                          </div>
                        </div>
                      )}

                      {/* Log Type Summary Banner */}
                      {availablePlugins.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 rounded-xl border border-border/40 bg-muted/15 px-3 py-2">
                          <ScrollText className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                          {availablePlugins.map((p) => {
                            const info = p.infoLog?.Lines ?? 0;
                            const err = p.errorLog?.Lines ?? 0;
                            const stack = p.stacktrace?.Lines ?? 0;
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

                      {availablePlugins.length > 1 ? (
                           <Tabs defaultValue={availablePlugins[0]?.namespace} className="w-full">
                           <TabsList className="grid w-full grid-cols-2 rounded-xl border border-border/60 bg-muted/25 p-1">
                            {availablePlugins.map((p) => (
                              <TabsTrigger key={p.namespace} value={p.namespace} className="text-xs flex-1">
                                {p.label}
                              </TabsTrigger>
                            ))}
                          </TabsList>
                          {availablePlugins.map((p) => (
                            <TabsContent key={p.namespace} value={p.namespace} className="mt-3">
                              <PluginLogsTabs plugin={p} />
                            </TabsContent>
                          ))}
                        </Tabs>
                      ) : availablePlugins.length === 1 ? (
                         <div className="rounded-xl border border-border/60 bg-muted/10 p-3">
                           <p className="mb-3 text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">{availablePlugins[0].label}</p>
                          <PluginLogsTabs plugin={availablePlugins[0]} />
                        </div>
                      ) : (
                         <div className="flex flex-col items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 py-8 text-muted-foreground">
                           <AlertTriangle className="h-5 w-5 text-amber-600" />
                          <p className="text-sm">No plugin log endpoints available on this site.</p>
                        </div>
                      )}
                    </>
                  )}
                </TabsContent>

                {/* ── ACTIONS TAB ───────────────────────────────── */}
                <TabsContent value="actions" className="mt-0 space-y-4">
                  {/* Clear Logs — Two-step */}
                  <div className="rounded-xl border border-border/60 bg-muted/10 p-4 space-y-3">
                    <h4 className="text-sm font-medium flex items-center gap-2">
                      <Trash2 className="h-4 w-4 text-destructive" />
                      Clear Logs
                    </h4>
                    <p className="text-xs text-muted-foreground">
                      Remove log files from the active plugin on this site. Requires two-step confirmation.
                    </p>
                    {!clearToken ? (
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={handleClearStep1}
                        disabled={isClearing || !hasFiles}
                      >
                        {isClearing ? (
                          <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                        ) : (
                          <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                        )}
                        Clear Logs
                      </Button>
                    ) : (
                      <div className="flex items-center gap-2">
                        <Button size="sm" variant="destructive" onClick={handleClearConfirm} disabled={isConfirming}>
                          {isConfirming ? (
                            <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                          ) : (
                            <AlertTriangle className="mr-1.5 h-3.5 w-3.5" />
                          )}
                          Confirm ({clearExpiry}s)
                        </Button>
                        <Button size="sm" variant="ghost" onClick={handleClearCancel}>Cancel</Button>
                      </div>
                    )}
                  </div>

                  {/* Clear All Plugins */}
                  <div className="rounded-xl border border-destructive/20 bg-destructive/5 p-4 space-y-3">
                    <h4 className="text-sm font-medium flex items-center gap-2 text-destructive">
                      <Trash2 className="h-4 w-4" />
                      Clear All Plugins
                    </h4>
                    <p className="text-xs text-muted-foreground">
                      Clear logs for <strong>both</strong> Riseup Asia and QUpload plugins simultaneously.
                    </p>
                    <Button
                      size="sm"
                      variant="destructive"
                      onClick={handleClearAllPlugins}
                      disabled={isClearingAll}
                    >
                      {isClearingAll ? (
                        <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                      ) : (
                        <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                      )}
                      Clear All Plugins
                    </Button>
                  </div>

                  {/* Email Logs */}
                  <div className="rounded-xl border border-border/60 bg-muted/10 p-4 space-y-3">
                    <h4 className="text-sm font-medium flex items-center gap-2">
                      <Mail className="h-4 w-4 text-muted-foreground" />
                      Email Logs
                    </h4>
                    <p className="text-xs text-muted-foreground">
                      Send log files as email attachments for external review.
                    </p>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => setShowEmailDialog(true)}
                      disabled={!hasFiles}
                    >
                      <Mail className="mr-1.5 h-3.5 w-3.5" /> Send Email
                    </Button>
                  </div>
                </TabsContent>
              </Tabs>
            )}
          </CardContent>
        </CollapsibleContent>
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
    </Collapsible>
  );
}
