import { useMemo } from "react";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { AlertCircle, CheckCircle2, ExternalLink, Activity, Server } from "lucide-react";
import { cn } from "@/lib/utils";
import { LogEntry } from "@/components/shared/LogViewer";

interface ActivationDiagnosticsProps {
  logs: LogEntry[];
  className?: string;
}

interface DiagnosticEntry {
  type: "request" | "response" | "info" | "error";
  label: string;
  value: string;
  details?: Record<string, unknown>;
}

/**
 * ActivationDiagnostics extracts and highlights activation-specific request/response
 * details from the publish logs to help users troubleshoot activation failures.
 */
export function ActivationDiagnostics({ logs, className }: ActivationDiagnosticsProps) {
  const diagnostics = useMemo(() => {
    const entries: DiagnosticEntry[] = [];
    
    // Find activation-related logs
    const activateLogs = logs.filter(
      (l) => l.step === "activate" || l.message.toLowerCase().includes("activat")
    );

    for (const log of activateLogs) {
      // Extract resolved identifier
      if (log.message.includes("Resolved plugin identifier")) {
        const details = log.details as Record<string, unknown> | undefined;
        if (details?.resolvedIdentifier) {
          entries.push({
            type: "info",
            label: "Resolved Plugin ID",
            value: String(details.resolvedIdentifier),
            details,
          });
        }
      }

      // Extract activation request details
      if (log.message.includes("Activating plugin:")) {
        const match = log.message.match(/Activating plugin:\s*(.+)/);
        if (match) {
          entries.push({
            type: "request",
            label: "Activation Target",
            value: match[1],
          });
        }
      }

      // Extract failed activation with API error details
      if (log.message.includes("Activation failed") || log.level === "error") {
        const details = log.details as Record<string, unknown> | undefined;
        
        entries.push({
          type: "error",
          label: "Error Message",
          value: log.message,
        });

        // Extract request details if available
        if (details?.request) {
          const req = details.request as Record<string, unknown>;
          if (req.url) {
            entries.push({
              type: "request",
              label: "Request URL",
              value: String(req.url),
            });
          }
          if (req.method) {
            entries.push({
              type: "request",
              label: "HTTP Method",
              value: String(req.method),
            });
          }
        }

        // Extract response details if available
        if (details?.response) {
          const resp = details.response as Record<string, unknown>;
          if (resp.status !== undefined) {
            entries.push({
              type: "response",
              label: "Response Status",
              value: String(resp.status),
            });
          }
          if (resp.body) {
            entries.push({
              type: "response",
              label: "Response Body",
              value: typeof resp.body === "string" 
                ? resp.body.slice(0, 500) 
                : JSON.stringify(resp.body).slice(0, 500),
            });
          }
        }
      }

      // Extract success
      if (log.message.includes("activated successfully")) {
        entries.push({
          type: "info",
          label: "Status",
          value: "✓ Plugin activated successfully",
        });
      }
    }

    // If no activation logs found, check for general errors
    if (entries.length === 0) {
      const errorLogs = logs.filter((l) => l.level === "error");
      for (const log of errorLogs.slice(0, 3)) {
        entries.push({
          type: "error",
          label: log.step || "Error",
          value: log.message,
        });
      }
    }

    return entries;
  }, [logs]);

  const hasErrors = diagnostics.some((d) => d.type === "error");
  const hasSuccess = diagnostics.some((d) => d.value.includes("successfully"));

  if (diagnostics.length === 0) {
    return (
      <div className={cn("text-center py-6 text-muted-foreground", className)}>
        <Activity className="h-8 w-8 mx-auto mb-2 opacity-50" />
        <p className="text-sm">No activation diagnostics available</p>
        <p className="text-xs mt-1">Diagnostics appear after the activate stage runs</p>
      </div>
    );
  }

  return (
    <div className={cn("space-y-3", className)}>
      {/* Status Banner */}
      <div
        className={cn(
          "flex items-center gap-2 p-3 rounded-lg border",
          hasErrors && "border-destructive/30 bg-destructive/5",
          hasSuccess && !hasErrors && "border-primary/30 bg-primary/5",
          !hasErrors && !hasSuccess && "border-border bg-muted/50"
        )}
      >
        {hasErrors ? (
          <>
            <AlertCircle className="h-5 w-5 text-destructive" />
            <span className="font-medium text-destructive">Activation Failed</span>
          </>
        ) : hasSuccess ? (
          <>
            <CheckCircle2 className="h-5 w-5 text-primary" />
            <span className="font-medium text-primary">Activation Successful</span>
          </>
        ) : (
          <>
            <Activity className="h-5 w-5 text-muted-foreground" />
            <span className="font-medium">Activation In Progress</span>
          </>
        )}
      </div>

      {/* Diagnostics List */}
      <ScrollArea className="h-48">
        <div className="space-y-2 pr-4">
          {diagnostics.map((entry, idx) => (
            <div
              key={idx}
              className={cn(
                "p-2 rounded-md border text-sm",
                entry.type === "error" && "border-destructive/30 bg-destructive/5",
                entry.type === "request" && "border-primary/30 bg-primary/5",
                entry.type === "response" && "border-accent/30 bg-accent/5",
                entry.type === "info" && "border-border bg-muted/50"
              )}
            >
              <div className="flex items-center gap-2 mb-1">
                {entry.type === "request" && <Server className="h-3 w-3 text-primary" />}
                {entry.type === "response" && <ExternalLink className="h-3 w-3 text-accent-foreground" />}
                {entry.type === "error" && <AlertCircle className="h-3 w-3 text-destructive" />}
                {entry.type === "info" && <Activity className="h-3 w-3 text-muted-foreground" />}
                <Badge
                  variant="outline"
                  className={cn(
                    "text-xs",
                    entry.type === "error" && "border-destructive/50 text-destructive",
                    entry.type === "request" && "border-primary/50 text-primary",
                    entry.type === "response" && "border-accent/50 text-accent-foreground"
                  )}
                >
                  {entry.label}
                </Badge>
              </div>
              <code className="text-xs font-mono break-all whitespace-pre-wrap">
                {entry.value}
              </code>
            </div>
          ))}
        </div>
      </ScrollArea>

      {/* Help text for 404 errors */}
      {diagnostics.some((d) => d.value.includes("404")) && (
        <div className="p-3 rounded-lg bg-muted/50 border border-border text-xs text-muted-foreground">
          <p className="font-medium mb-1">💡 404 Error Troubleshooting:</p>
          <ul className="list-disc list-inside space-y-1">
            <li>Ensure the plugin slug matches the folder name in wp-content/plugins/</li>
            <li>Check that the plugin is installed on the remote site</li>
            <li>Verify the plugin's main PHP file matches the expected format (slug/slug.php)</li>
            <li>Try re-uploading the plugin ZIP first</li>
          </ul>
        </div>
      )}
    </div>
  );
}
