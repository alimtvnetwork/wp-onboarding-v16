import { useState, useCallback, useEffect } from "react";
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

// ── Log Content Viewer ─────────────────────────────────────────
function LogContentViewer({ file, label }: { file?: LogRetrieveFileData; label: string }) {
  if (!file) return <p className="text-sm text-muted-foreground py-2">Not requested</p>;
  if (!file.Exists) return <p className="text-sm text-muted-foreground py-2">No {label} file found.</p>;

  const handleCopy = () => {
    navigator.clipboard.writeText(file.Content);
    toast.success(`${label} copied to clipboard`);
  };

  return (
    <div className="space-y-2">
      {/* Metadata */}
      <div className="flex items-center gap-2 flex-wrap text-xs text-muted-foreground">
        <Badge variant="outline" className="text-xs">{file.Lines} / {file.TotalLines} lines</Badge>
        <Badge variant="outline" className="text-xs">{formatBytes(file.TotalSize)}</Badge>
        {file.Truncated && (
          <Badge variant="destructive" className="text-xs">Truncated</Badge>
        )}
        <Button size="sm" variant="ghost" className="h-6 px-2 ml-auto" onClick={handleCopy}>
          <Copy className="h-3 w-3 mr-1" /> Copy
        </Button>
      </div>

      {file.Truncated && (
        <div className="flex items-center gap-2 rounded-md border border-yellow-500/30 bg-yellow-500/5 px-3 py-2 text-xs text-muted-foreground">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
          Showing last {file.Lines} of {file.TotalLines} lines. Increase max lines to see more.
        </div>
      )}

      {/* Content */}
      <ScrollArea className="h-[400px] rounded-md border bg-background p-3">
        <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all leading-relaxed">
          {file.Content || "(empty)"}
        </pre>
      </ScrollArea>
    </div>
  );
}

// ── Plugin Logs Tab Content ────────────────────────────────────
function PluginLogsTabs({ plugin }: { plugin: PluginLogsData }) {
  if (!plugin.available) {
    return (
      <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
        <AlertTriangle className="h-4 w-4 text-yellow-500" />
        Plugin not available on this site
      </div>
    );
  }

  const infoLines = plugin.infoLog?.Lines ?? 0;
  const errorLines = plugin.errorLog?.Lines ?? 0;
  const stackLines = plugin.stacktrace?.Lines ?? 0;

  return (
    <Tabs defaultValue="info" className="w-full">
      <TabsList className="w-full grid grid-cols-3">
        <TabsTrigger value="info" className="text-xs">
          Info {infoLines > 0 && <Badge variant="secondary" className="ml-1 text-[10px] px-1">{infoLines}</Badge>}
        </TabsTrigger>
        <TabsTrigger value="error" className="text-xs">
          Error {errorLines > 0 && <Badge variant="destructive" className="ml-1 text-[10px] px-1">{errorLines}</Badge>}
        </TabsTrigger>
        <TabsTrigger value="stacktrace" className="text-xs">
          Stacktrace {stackLines > 0 && <Badge variant="secondary" className="ml-1 text-[10px] px-1">{stackLines}</Badge>}
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

  // Retrieve state (content viewer)
  const [retrieveData, setRetrieveData] = useState<LogsRetrieveResult | null>(null);
  const [isRetrieving, setIsRetrieving] = useState(false);
  const [maxLines, setMaxLines] = useState(200);
  const [showViewer, setShowViewer] = useState(false);

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

  const fetchStatus = useCallback(async () => {
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
        surfaceError(err, `/sites/${siteId}/remote-logs`, "GET");
      }
    } finally {
      setIsLoading(false);
    }
  }, [siteId]);

  const fetchLogContent = useCallback(async () => {
    setIsRetrieving(true);
    try {
      const response = await api.retrieveRemoteLogs(siteId, { max_lines: maxLines });
      const data = requireSuccess(response, { endpoint: `/sites/${siteId}/remote-logs/retrieve`, method: "GET" });
      setRetrieveData(data);
      setShowViewer(true);

      // Check if any plugin returned data
      const hasAnyContent = data.plugins?.some(p => p.available);
      if (!hasAnyContent) {
        toast.warning("No log retrieval endpoints available — the remote plugin may be outdated or missing the /logs/retrieve endpoint.");
      }
    } catch (err) {
      surfaceError(err, `/sites/${siteId}/remote-logs/retrieve`, "GET");
      // Do NOT silently fail — the error modal will show
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
      surfaceError(err, `/sites/${siteId}/remote-logs/clear`, "DELETE");
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
      surfaceError(err, `/sites/${siteId}/remote-logs/clear/confirm`, "POST");
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
      surfaceError(err, `/sites/${siteId}/remote-logs/clear-all`, "POST");
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
      surfaceError(err, `/sites/${siteId}/remote-logs/email`, "POST");
    } finally {
      setIsSendingEmail(false);
    }
  };

  const hasFiles = status?.files && status.files.length > 0;
  const availablePlugins = retrieveData?.plugins.filter(p => p.available) ?? [];

  return (
    <Collapsible open={isOpen} onOpenChange={handleOpen}>
      <Card>
        <CollapsibleTrigger asChild>
          <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                {isOpen ? (
                  <ChevronDown className="h-4 w-4 text-muted-foreground" />
                ) : (
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                )}
                <FileText className="h-4 w-4 text-muted-foreground" />
                <CardTitle className="text-sm font-medium">Remote Logs</CardTitle>
              </div>
              {status && (
                <Badge variant="secondary" className="text-xs">
                  {formatBytes(status.totalSizeBytes)}
                  {status.archiveCount > 0 && (
                    <span className="ml-1 text-muted-foreground">
                      · {status.archiveCount} archived
                    </span>
                  )}
                </Badge>
              )}
            </div>
          </CardHeader>
        </CollapsibleTrigger>

        <CollapsibleContent>
          <CardContent className="space-y-4 pt-0">
            {/* Loading */}
            {isLoading && (
              <div className="flex items-center justify-center py-6">
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

            {/* File List */}
            {!isLoading && status && !status.pluginOutdated && (
              <>
                {hasFiles ? (
                  <div className="space-y-1.5">
                    {status.files.map((file) => (
                      <div
                        key={file.name}
                        className="flex items-center justify-between rounded-md bg-muted/40 px-3 py-2 text-sm"
                      >
                        <span className="font-mono text-xs text-foreground">{file.name}</span>
                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                          <span>{file.lineCount.toLocaleString()} lines</span>
                          <Badge variant="outline" className="text-xs">{formatBytes(file.sizeBytes)}</Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                    <CheckCircle className="h-4 w-4 text-success" />
                    No log files found
                  </div>
                )}

                {status.archiveCount > 0 && (
                  <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Archive className="h-3.5 w-3.5" />
                    {status.archiveCount} archived rotation{status.archiveCount !== 1 ? "s" : ""}
                  </div>
                )}

                {/* Actions */}
                <div className="flex items-center gap-2 border-t pt-3 flex-wrap">
                  <Button size="sm" variant="outline" onClick={fetchStatus} disabled={isLoading}>
                    <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Refresh
                  </Button>

                  {/* View Logs (retrieve content) */}
                  <Button
                    size="sm"
                    variant="outline"
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

                  {/* Max Lines Selector */}
                  <Select value={String(maxLines)} onValueChange={(v) => setMaxLines(Number(v))}>
                    <SelectTrigger className="h-8 w-[100px] text-xs">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {[50, 100, 200, 500, 1000, 2000].map((n) => (
                        <SelectItem key={n} value={String(n)} className="text-xs">{n} lines</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>

                  {/* Clear — two-step */}
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
                    <div className="flex items-center gap-1.5">
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

                  {/* Clear All (both plugins) */}
                  <Button
                    size="sm"
                    variant="destructive"
                    onClick={handleClearAllPlugins}
                    disabled={isClearingAll}
                    title="Clear logs for both Riseup Asia and QUpload plugins"
                  >
                    {isClearingAll ? (
                      <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                    )}
                    Clear All Plugins
                  </Button>

                  {/* Email */}
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setShowEmailDialog(true)}
                    disabled={!hasFiles}
                  >
                    <Mail className="mr-1.5 h-3.5 w-3.5" /> Email Logs
                  </Button>
                </div>

                {/* ── Log Content Viewer ─────────────────────────────── */}
                {showViewer && retrieveData && (
                  <div className="border-t pt-4 space-y-3">
                    <div className="flex items-center justify-between">
                      <h4 className="text-sm font-medium">Log Content</h4>
                      <div className="flex items-center gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={handleDownloadAll}
                          disabled={availablePlugins.length === 0}
                        >
                          <Download className="mr-1.5 h-3.5 w-3.5" /> Download All
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => setShowViewer(false)}
                          className="text-xs"
                        >
                          Hide
                        </Button>
                      </div>
                    </div>

                    {availablePlugins.length > 1 ? (
                      <Tabs defaultValue={availablePlugins[0]?.namespace} className="w-full">
                        <TabsList className="w-full">
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
                      <div>
                        <p className="text-xs text-muted-foreground mb-2">{availablePlugins[0].label}</p>
                        <PluginLogsTabs plugin={availablePlugins[0]} />
                      </div>
                    ) : (
                      <p className="text-sm text-muted-foreground py-4">
                        No plugin log endpoints available on this site.
                      </p>
                    )}
                  </div>
                )}
              </>
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
