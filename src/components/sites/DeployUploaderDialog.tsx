import { useState, useEffect, useRef, useCallback } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LogViewer, LogEntry } from "@/components/shared/LogViewer";
import type { LogEntryDetails } from "@/lib/api";
import { isApiClientError } from "@/lib/api";
import { CheckCircle, XCircle, Loader2, Upload, Copy, Shield, AlertTriangle } from "lucide-react";
import { useWebSocket } from "@/hooks/useWebSocket";
import { toast } from "sonner";
import { DeployStatus } from "@/lib/constants";
import { useErrorStore } from "@/stores/errorStore";
import type { ApiError } from "@/lib/api/types";
import { Progress } from "@/components/ui/progress";
import { api } from "@/lib/api";

interface DeploySiteResult {
  siteId: number;
  siteName: string;
  isSuccess: boolean;
  message: string;
  isActivated?: boolean;
  error?: string;
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
  const logsEndRef = useRef<HTMLDivElement>(null);
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

      // Detect phase from log messages
      const msg = logEntry.message.toLowerCase();
      if (msg.includes("creating plugin zip") || msg.includes("zip archive created")) {
        setDeployPhase("zipping");
      } else if (msg.includes("uploading") || msg.includes("cross-upload") || msg.includes("endpoint")) {
        setDeployPhase("uploading");
      }
    }
  }, [lastMessage, status]);

  // Auto-scroll to bottom
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
      runPreflight();
    }
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  const runPreflight = useCallback(async () => {
    setPreflightLoading(true);
    try {
      const siteIds = sites.map((s) => s.id);
      const response = await api.deployPreflight(siteIds);
      setPreflightResults(response.results);
    } catch {
      // Pre-flight failure is non-blocking
      toast.error("Pre-flight check failed — you can still deploy");
    } finally {
      setPreflightLoading(false);
    }
  }, [sites]);

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
    const modalError: ApiError = {
      code: "E3009",
      message: "Bulk uploader deployment failed on one or more sites",
      details: summaryLines.join("\n"),
      timestamp: new Date().toISOString(),
      context: {
        source: "DeployUploaderDialog",
        failedSites: failedResults.map((r) => ({
          siteId: r.siteId, siteName: r.siteName, error: r.error || r.message,
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
      context: { source: "DeployUploaderDialog", triggerAction: "bulk-bootstrap-uploader" },
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

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
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
            </TabsTrigger>
            <TabsTrigger value="logs">Logs ({logs.length})</TabsTrigger>
          </TabsList>

          <TabsContent value="progress" className="space-y-4">
            {/* Phase progress */}
            {status === DeployStatus.Deploying && (
              <div className="space-y-2">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">{getPhaseLabel()}</span>
                  <span className="font-mono text-xs">{getPhaseProgress()}%</span>
                </div>
                <Progress value={getPhaseProgress()} className="h-2" />
              </div>
            )}

            {/* Status */}
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

            {/* Site list with live status */}
            <div className="space-y-2">
              <h4 className="text-sm font-medium">Target Sites</h4>
              <div className="max-h-48 overflow-y-auto space-y-1">
                {sites.map((site) => {
                  const result = results.find((r) => r.siteId === site.id);
                  const preflight = preflightResults.find((p) => p.siteId === site.id);
                  return (
                    <div
                      key={site.id}
                      className="flex items-center justify-between p-2.5 rounded bg-muted/30"
                    >
                      <div className="flex-1 min-w-0">
                        <span className="text-sm font-medium">{site.name}</span>
                        {preflight && (
                          <div className="flex items-center gap-2 mt-0.5">
                            <EndpointBadge label="QUpload" available={preflight.qUploadAvailable} namespace={preflight.qUploadNamespace} />
                            <EndpointBadge label="Riseup" available={preflight.riseupAsiaAvailable} namespace={preflight.riseupAsiaNamespace} />
                          </div>
                        )}
                      </div>
                      <div className="flex items-center gap-1">
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
                        {status === DeployStatus.Idle && preflightLoading && (
                          <Loader2 className="h-3 w-3 animate-spin text-muted-foreground" />
                        )}
                      </div>
                    </div>
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

          <TabsContent value="preflight" className="space-y-4">
            <div className="flex items-center justify-between">
              <h4 className="text-sm font-medium">Endpoint Availability</h4>
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
              <div className="space-y-2">
                {preflightResults.map((pf) => (
                  <div key={pf.siteId} className="p-3 rounded-lg border bg-card">
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-medium text-sm">{pf.siteName}</span>
                      {pf.isReachable ? (
                        <span className="text-xs text-primary flex items-center gap-1">
                          <CheckCircle className="h-3 w-3" /> Reachable
                        </span>
                      ) : (
                        <span className="text-xs text-destructive flex items-center gap-1">
                          <XCircle className="h-3 w-3" /> Unreachable
                        </span>
                      )}
                    </div>
                    <div className="text-xs text-muted-foreground mb-2 truncate">{pf.siteUrl}</div>
                    {pf.isReachable && (
                      <div className="grid grid-cols-2 gap-2">
                        <EndpointCard
                          label="QUpload (cross-upload)"
                          available={pf.qUploadAvailable}
                          namespace={pf.qUploadNamespace}
                          preferred
                        />
                        <EndpointCard
                          label="Riseup Asia (fallback)"
                          available={pf.riseupAsiaAvailable}
                          namespace={pf.riseupAsiaNamespace}
                        />
                      </div>
                    )}
                    {pf.error && (
                      <p className="text-xs text-destructive mt-1">{pf.error}</p>
                    )}
                    {pf.isReachable && !pf.qUploadAvailable && !pf.riseupAsiaAvailable && (
                      <div className="flex items-center gap-1 mt-2 text-xs text-destructive">
                        <AlertTriangle className="h-3 w-3" />
                        No upload endpoint available — deploy will fail
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </TabsContent>

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

function EndpointCard({ label, available, namespace, preferred }: { label: string; available: boolean; namespace?: string; preferred?: boolean }) {
  return (
    <div className={`p-2 rounded border text-xs ${
      available ? "border-primary/30 bg-primary/5" : "border-border bg-muted/30"
    }`}>
      <div className="flex items-center gap-1 mb-1">
        {available ? (
          <CheckCircle className="h-3 w-3 text-primary" />
        ) : (
          <XCircle className="h-3 w-3 text-muted-foreground" />
        )}
        <span className="font-medium">{label}</span>
      </div>
      {available && namespace && (
        <span className="text-muted-foreground font-mono">{namespace}</span>
      )}
      {!available && <span className="text-muted-foreground">Not installed</span>}
      {preferred && available && (
        <div className="mt-1 text-[10px] text-primary font-medium">★ Preferred</div>
      )}
    </div>
  );
}
