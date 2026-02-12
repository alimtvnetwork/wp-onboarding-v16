import { useState, useEffect, useRef } from "react";
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
import { CheckCircle, XCircle, Loader2, Upload, Copy } from "lucide-react";
import { useWebSocket } from "@/hooks/useWebSocket";
import { toast } from "sonner";
import { DeployStatus } from "@/lib/constants";

interface DeploySiteResult {
  siteId: number;
  siteName: string;
  isSuccess: boolean;
  message: string;
  isActivated?: boolean;
  error?: string;
}

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
    }
  }, [lastMessage, status]);

  // Auto-scroll to bottom
  useEffect(() => {
    logsEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [logs]);

  // Reset state when dialog opens
  useEffect(() => {
    if (open) {
      setLogs([]);
      setResults([]);
      setStatus(DeployStatus.Idle);
      setCurrentTab("progress");
    }
  }, [open]);

  const handleDeploy = async () => {
    setStatus(DeployStatus.Deploying);
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
      
      const succeeded = deployResults.filter((r) => r.isSuccess).length;
      const failed = deployResults.length - succeeded;

      setLogs((prev) => [
        ...prev,
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
      }
    } catch (error) {
      setStatus(DeployStatus.Error);
      setLogs((prev) => [
        ...prev,
        {
          timestamp: new Date().toISOString(),
          level: "error",
          step: "error",
          message: error instanceof Error ? error.message : "Deployment failed",
        },
      ]);
      toast.error("Deployment failed");
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

  const getStatusText = () => {
    switch (status) {
      case DeployStatus.Deploying:
        return "Deploying...";
      case DeployStatus.Completed:
        return "Deployment Complete";
      case DeployStatus.Error:
        return "Deployment Failed";
      default:
        return "Ready to Deploy";
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
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="progress">Progress</TabsTrigger>
            <TabsTrigger value="logs">Logs ({logs.length})</TabsTrigger>
          </TabsList>

          <TabsContent value="progress" className="space-y-4">
            {/* Status */}
            <div className="flex items-center justify-between p-4 rounded-lg bg-muted/50">
              <div className="flex items-center gap-3">
                {getStatusIcon()}
                <div>
                  <p className="font-medium">{getStatusText()}</p>
                  <p className="text-sm text-muted-foreground">
                    {sites.length} site(s) selected
                  </p>
                </div>
              </div>
            </div>

            {/* Site list */}
            <div className="space-y-2">
              <h4 className="text-sm font-medium">Target Sites</h4>
              <div className="max-h-40 overflow-y-auto space-y-1">
                {sites.map((site) => {
                  const result = results.find((r) => r.siteId === site.id);
                  return (
                    <div
                      key={site.id}
                      className="flex items-center justify-between p-2 rounded bg-muted/30"
                    >
                      <span className="text-sm">{site.name}</span>
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

          <TabsContent value="logs" className="space-y-2">
            <div className="flex justify-end">
              <Button variant="ghost" size="sm" onClick={handleCopyLogs} disabled={logs.length === 0}>
                <Copy className="h-3 w-3 mr-1" />
                Copy
              </Button>
            </div>
            <LogViewer
              logs={logs}
              className="h-64"
            />
            <div ref={logsEndRef} />
          </TabsContent>
        </Tabs>

        <div className="flex justify-end gap-2 pt-4 border-t">
          {status === DeployStatus.Idle && (
            <Button onClick={handleDeploy} disabled={sites.length === 0}>
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
