import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
  AlertTriangle,
  ChevronDown,
  ChevronRight,
  Copy,
  Globe,
  Server,
  X,
  ExternalLink,
} from "lucide-react";
import { toast } from "sonner";
import { isApiClientError } from "@/lib/api";
import type { ApiError, DelegatedRequestServer } from "@/lib/api/types";
import { cn } from "@/lib/utils";

// ── Extracted diagnostic info from an error ────────────────────
export interface InlineDiagnostic {
  code: string;
  message: string;
  details?: string;
  endpoint: string;
  method: string;
  statusCode?: number;
  timestamp: string;
  // Delegated (remote WordPress) context
  delegatedEndpoint?: string;
  delegatedStatusCode?: number;
  delegatedMethod?: string;
  delegatedStackTrace?: string[];
  delegatedAdditionalMessages?: string;
  remoteResponseBody?: string;
  // Backend trace
  backendTrace?: string[];
  delegatedServiceErrorStack?: string[];
  // Raw for copy
  rawJson?: string;
}

/** Extract a structured diagnostic from a caught error. */
export function extractDiagnostic(err: unknown, fallbackEndpoint: string, fallbackMethod: string): InlineDiagnostic {
  if (isApiClientError(err)) {
    const apiErr = err.apiError;
    const ctx = apiErr.context ?? {};
    const delegated = ctx.delegatedRequestServer as DelegatedRequestServer | undefined;

    return {
      code: apiErr.code,
      message: apiErr.message,
      details: apiErr.details,
      endpoint: err.meta.requestUrl,
      method: err.meta.method ?? fallbackMethod,
      statusCode: (ctx.responseStatus as number | undefined) ?? undefined,
      timestamp: apiErr.timestamp,
      delegatedEndpoint: delegated?.DelegatedEndpoint,
      delegatedStatusCode: delegated?.StatusCode,
      delegatedMethod: delegated?.Method,
      delegatedStackTrace: delegated?.StackTrace,
      delegatedAdditionalMessages: delegated?.AdditionalMessages,
      remoteResponseBody: ctx.remoteResponseBody as string | undefined,
      backendTrace: ctx.backendTrace as string[] | undefined,
      delegatedServiceErrorStack: ctx.delegatedServiceErrorStack as string[] | undefined,
      rawJson: JSON.stringify(apiErr, null, 2),
    };
  }

  const msg = err instanceof Error ? err.message : String(err);
  return {
    code: "UNKNOWN",
    message: msg,
    endpoint: fallbackEndpoint,
    method: fallbackMethod,
    timestamp: new Date().toISOString(),
    rawJson: msg,
  };
}

// ── Inline Error Diagnostic Component ──────────────────────────
interface InlineErrorDiagnosticProps {
  diagnostic: InlineDiagnostic;
  onDismiss?: () => void;
  onOpenGlobalModal?: () => void;
  className?: string;
}

export function InlineErrorDiagnostic({ diagnostic, onDismiss, onOpenGlobalModal, className }: InlineErrorDiagnosticProps) {
  const [showDetails, setShowDetails] = useState(false);
  const d = diagnostic;

  const hasDelegated = !!(d.delegatedEndpoint || d.delegatedServiceErrorStack?.length);
  const hasBackend = !!(d.backendTrace?.length);
  const hasRemoteBody = !!d.remoteResponseBody;

  const copyAll = () => {
    navigator.clipboard.writeText(d.rawJson || d.message);
    toast.success("Error diagnostics copied");
  };

  const statusBadge = (code: number | undefined, label: string) => {
    if (!code) return null;
    const isError = code >= 400;
    return (
      <Badge variant={isError ? "destructive" : "secondary"} className="text-[10px] font-mono px-1.5">
        {label} {code}
      </Badge>
    );
  };

  return (
    <div className={cn("rounded-xl border-2 border-destructive/40 bg-destructive/5 shadow-lg overflow-hidden", className)}>
      {/* Header */}
      <div className="flex items-center justify-between gap-2 border-b border-destructive/20 bg-destructive/10 px-4 py-2.5">
        <div className="flex items-center gap-2 min-w-0">
          <AlertTriangle className="h-4 w-4 text-destructive shrink-0" />
          <span className="text-sm font-semibold text-destructive truncate">API Error</span>
          <Badge variant="outline" className="text-[10px] font-mono border-destructive/30 text-destructive shrink-0">
            {d.code}
          </Badge>
          {statusBadge(d.statusCode, "")}
        </div>
        <div className="flex items-center gap-1 shrink-0">
          <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={copyAll} title="Copy diagnostics">
            <Copy className="h-3 w-3" />
          </Button>
          {onOpenGlobalModal && (
            <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={onOpenGlobalModal} title="Open in error modal">
              <ExternalLink className="h-3 w-3" />
            </Button>
          )}
          {onDismiss && (
            <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={onDismiss} title="Dismiss">
              <X className="h-3 w-3" />
            </Button>
          )}
        </div>
      </div>

      {/* Message */}
      <div className="px-4 py-3 space-y-2">
        <p className="text-sm text-foreground">{d.message}</p>
        {d.details && <p className="text-xs text-muted-foreground">{d.details}</p>}

        {/* Request summary */}
        <div className="flex items-center gap-2 flex-wrap text-xs text-muted-foreground font-mono">
          <Badge variant="outline" className="text-[10px] font-mono">{d.method}</Badge>
          <span className="truncate max-w-[300px]">{d.endpoint}</span>
        </div>

        {/* Delegated header (orange theme matching DelegatedSection) */}
        {hasDelegated && (
          <div className="rounded-lg border border-orange-500/30 bg-orange-500/5 p-3 space-y-2 mt-2">
            <h4 className="text-xs font-medium text-orange-600 dark:text-orange-400 uppercase tracking-wider flex items-center gap-1.5">
              <Globe className="h-3 w-3" />
              Remote WordPress Error
            </h4>
            {d.delegatedEndpoint && (
              <div className="font-mono text-xs break-all flex items-center gap-2 flex-wrap">
                {statusBadge(d.delegatedStatusCode, d.delegatedMethod || "GET")}
                <span className="opacity-80">{d.delegatedEndpoint}</span>
              </div>
            )}
            {d.delegatedAdditionalMessages && (
              <p className="text-xs text-muted-foreground">{d.delegatedAdditionalMessages}</p>
            )}
          </div>
        )}
      </div>

      {/* Expandable details */}
      {(hasBackend || hasDelegated || hasRemoteBody) && (
        <Collapsible open={showDetails} onOpenChange={setShowDetails}>
          <CollapsibleTrigger asChild>
            <button className="w-full flex items-center gap-2 px-4 py-2 border-t border-destructive/15 text-xs text-muted-foreground hover:bg-destructive/5 transition-colors">
              {showDetails ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
              <span>Diagnostic Details</span>
              {hasBackend && <Badge variant="outline" className="text-[9px] h-4">Backend</Badge>}
              {hasDelegated && d.delegatedServiceErrorStack?.length && (
                <Badge variant="outline" className="text-[9px] h-4 border-orange-500/30 text-orange-500">
                  {d.delegatedServiceErrorStack.length} PHP frames
                </Badge>
              )}
              {hasRemoteBody && <Badge variant="outline" className="text-[9px] h-4">Response Body</Badge>}
            </button>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className="px-4 pb-3 space-y-3">
              {/* Delegated error stack (PHP) */}
              {d.delegatedServiceErrorStack && d.delegatedServiceErrorStack.length > 0 && (
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <h5 className="text-xs font-medium text-orange-500 flex items-center gap-1.5">
                      <Globe className="h-3 w-3" />
                      PHP Error Stack ({d.delegatedServiceErrorStack.length} frames)
                    </h5>
                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-5 px-1.5"
                      onClick={() => {
                        navigator.clipboard.writeText(d.delegatedServiceErrorStack!.join("\n"));
                        toast.success("PHP stack copied");
                      }}
                    >
                      <Copy className="h-3 w-3" />
                    </Button>
                  </div>
                  <ScrollArea className="max-h-40 rounded-lg border border-orange-500/20 bg-orange-500/5 p-2">
                    <pre className="text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all leading-relaxed">
                      {d.delegatedServiceErrorStack.join("\n")}
                    </pre>
                  </ScrollArea>
                </div>
              )}

              {/* Delegated stack trace */}
              {d.delegatedStackTrace && d.delegatedStackTrace.length > 0 && (
                <div className="space-y-1.5">
                  <h5 className="text-xs font-medium text-orange-500 flex items-center gap-1.5">
                    <Globe className="h-3 w-3" />
                    Remote Stack Trace
                  </h5>
                  <ScrollArea className="max-h-32 rounded-lg border border-orange-500/20 bg-orange-500/5 p-2">
                    <pre className="text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all">
                      {d.delegatedStackTrace.join("\n")}
                    </pre>
                  </ScrollArea>
                </div>
              )}

              {/* Remote response body */}
              {hasRemoteBody && (
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <h5 className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
                      <Server className="h-3 w-3" />
                      Remote Response Body
                    </h5>
                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-5 px-1.5"
                      onClick={() => {
                        navigator.clipboard.writeText(d.remoteResponseBody!);
                        toast.success("Response body copied");
                      }}
                    >
                      <Copy className="h-3 w-3" />
                    </Button>
                  </div>
                  <ScrollArea className="max-h-32 rounded-lg border border-border/60 bg-muted/20 p-2">
                    <pre className="text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all">
                      {d.remoteResponseBody}
                    </pre>
                  </ScrollArea>
                </div>
              )}

              {/* Backend trace */}
              {hasBackend && (
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <h5 className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
                      <Server className="h-3 w-3" />
                      Go Backend Trace
                    </h5>
                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-5 px-1.5"
                      onClick={() => {
                        navigator.clipboard.writeText(d.backendTrace!.join("\n"));
                        toast.success("Backend trace copied");
                      }}
                    >
                      <Copy className="h-3 w-3" />
                    </Button>
                  </div>
                  <ScrollArea className="max-h-32 rounded-lg border border-border/60 bg-muted/20 p-2">
                    <pre className="text-[11px] font-mono text-muted-foreground whitespace-pre-wrap break-all">
                      {d.backendTrace!.join("\n")}
                    </pre>
                  </ScrollArea>
                </div>
              )}
            </div>
          </CollapsibleContent>
        </Collapsible>
      )}

      {/* Timestamp */}
      <div className="px-4 py-1.5 border-t border-destructive/10 text-[10px] text-muted-foreground/60 font-mono">
        {d.timestamp}
      </div>
    </div>
  );
}