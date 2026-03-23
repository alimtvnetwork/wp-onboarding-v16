import { CapturedError } from '@/stores/errorStore';
import type { SessionStackFrame, SessionDiagnostics, DelegatedRequestServer } from '@/lib/api';
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Copy, Globe, Network, AlertTriangle, RefreshCw
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useSessionDiagnostics } from "@/hooks/useSessionDiagnostics";
import type { PHPStackFrame } from "./ErrorModalTypes";

interface DelegatedSectionProps {
  error: CapturedError;
  phpStackFrames: PHPStackFrame[];
  copySection: (label: string, content: string) => void;
}

export function DelegatedSection({ error, phpStackFrames, copySection }: DelegatedSectionProps) {
  const { diagnostics: sessionDiag, loading: sessionLoading } = useSessionDiagnostics(error.sessionId);

  const envelopeDelegatedStack = error.envelopeErrors?.DelegatedServiceErrorStack;
  const delegatedServer = error.envelopeErrors?.DelegatedRequestServer;
  const delegatedStackTrace = delegatedServer?.StackTrace;
  const sessionPhpFrames = sessionDiag?.stackTrace?.php;

  return (
    <div className="space-y-4">
      {/* Delegated Request Info */}
      {delegatedServer && (
        <div className="rounded-md border border-orange-500/30 bg-orange-500/5 p-3 space-y-2">
          <h4 className="text-xs font-medium text-orange-600 dark:text-orange-400 uppercase tracking-wider flex items-center gap-1.5">
            <Globe className="h-3 w-3" />
            Delegated Server Request
          </h4>
          <div className="font-mono text-xs break-all">
            <Badge variant={delegatedServer.StatusCode >= 400 ? "destructive" : "secondary"} className="text-xs mr-2">
              {delegatedServer.Method} {delegatedServer.StatusCode}
            </Badge>
            <span>{delegatedServer.DelegatedEndpoint}</span>
          </div>
          {delegatedServer.AdditionalMessages && (
            <p className="text-xs text-muted-foreground">{delegatedServer.AdditionalMessages}</p>
          )}
        </div>
      )}

      {/* PHP Stack Trace (structured frames) */}
      {phpStackFrames.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              PHP Stack Trace ({phpStackFrames.length} frames)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => {
              const text = phpStackFrames.map((f, i) => {
                const fn = f.class ? `${f.class}::${f.function}` : f.function || 'unknown';
                return `#${i} ${fn}() at ${f.file || f.fileBase || 'unknown'}:${f.line || '?'}`;
              }).join('\n');
              copySection("PHP stack trace", text);
            }}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <div className="border rounded-md overflow-hidden">
            <table className="w-full text-xs">
              <thead className="bg-orange-500/10">
                <tr>
                  <th className="text-left p-2 font-medium text-muted-foreground">#</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">Function</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">File</th>
                  <th className="text-right p-2 font-medium text-muted-foreground">Line</th>
                </tr>
              </thead>
              <tbody>
                {phpStackFrames.map((frame, index) => (
                  <tr key={index} className={cn("border-t border-border/50", index === 0 && "bg-orange-500/5")}>
                    <td className="p-2 font-mono text-muted-foreground">{index}</td>
                    <td className="p-2 font-mono">
                      <span className={cn(index === 0 && "text-orange-600 dark:text-orange-400 font-semibold")}>
                        {frame.class ? `${frame.class}::${frame.function}` : frame.function || 'unknown'}()
                      </span>
                    </td>
                    <td className="p-2 font-mono text-muted-foreground truncate max-w-[200px]" title={frame.file}>
                      {frame.fileBase || frame.file || 'unknown'}
                    </td>
                    <td className="p-2 font-mono text-right">{frame.line || '?'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* DelegatedRequestServer.StackTrace */}
      {delegatedStackTrace && delegatedStackTrace.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Server Stack Trace ({delegatedStackTrace.length} frames)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Delegated stack", delegatedStackTrace.join('\n'))}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <ScrollArea className="h-[200px] rounded-md border bg-orange-500/5">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all text-orange-700 dark:text-orange-300">
              {delegatedStackTrace.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {/* DelegatedServiceErrorStack */}
      {envelopeDelegatedStack && envelopeDelegatedStack.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Error Stack ({envelopeDelegatedStack.length} lines)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Delegated error stack", envelopeDelegatedStack.join('\n'))}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <ScrollArea className="h-[200px] rounded-md border bg-orange-500/5">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all text-orange-700 dark:text-orange-300">
              {envelopeDelegatedStack.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {/* Response Body */}
      {delegatedServer?.Response && (
        <div>
          <details>
            <summary className="text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground flex items-center gap-2">
              <Globe className="h-4 w-4" />
              Response Body
            </summary>
            <div className="mt-2 relative">
              <Button variant="ghost" size="sm" className="absolute top-1 right-1" onClick={() => {
                const text = typeof delegatedServer.Response === 'string'
                  ? delegatedServer.Response
                  : JSON.stringify(delegatedServer.Response, null, 2);
                copySection("Response body", text);
              }}>
                <Copy className="h-4 w-4" />
              </Button>
              <ScrollArea className="h-[200px] rounded-md border bg-muted">
                <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">
                  {typeof delegatedServer.Response === 'string'
                    ? delegatedServer.Response
                    : JSON.stringify(delegatedServer.Response, null, 2)}
                </pre>
              </ScrollArea>
            </div>
          </details>
        </div>
      )}

      {/* Request Body sent to delegated server */}
      {delegatedServer?.RequestBody && (
        <div>
          <details>
            <summary className="text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground flex items-center gap-2">
              <Network className="h-4 w-4" />
              Request Body (sent to delegated server)
            </summary>
            <div className="mt-2 relative">
              <Button variant="ghost" size="sm" className="absolute top-1 right-1" onClick={() => {
                const text = typeof delegatedServer.RequestBody === 'string'
                  ? delegatedServer.RequestBody
                  : JSON.stringify(delegatedServer.RequestBody, null, 2);
                copySection("Request body", text);
              }}>
                <Copy className="h-4 w-4" />
              </Button>
              <ScrollArea className="h-[200px] rounded-md border bg-muted">
                <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">
                  {typeof delegatedServer.RequestBody === 'string'
                    ? delegatedServer.RequestBody
                    : JSON.stringify(delegatedServer.RequestBody, null, 2)}
                </pre>
              </ScrollArea>
            </div>
          </details>
        </div>
      )}

      {/* Session PHP Stack */}
      {sessionPhpFrames && sessionPhpFrames.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Stack (Session) ({sessionPhpFrames.length} frames)
            </h4>
          </div>
          <ScrollArea className="h-[200px] rounded-md border bg-orange-500/5">
            <div className="p-3 space-y-1">
              {sessionPhpFrames.map((frame, i) => (
                <div key={i} className="text-xs font-mono leading-relaxed">
                  <span className="text-muted-foreground mr-1">#{i}</span>
                  <span className="font-semibold text-orange-500 dark:text-orange-400">
                    {frame.class ? `${frame.class}::${frame.function}` : frame.function}()
                  </span>
                  {frame.file && (
                    <span className="text-muted-foreground ml-1">
                      at {frame.file}{frame.line ? `:${frame.line}` : ''}
                    </span>
                  )}
                </div>
              ))}
            </div>
          </ScrollArea>
        </div>
      )}

      {/* PHP Log (stacktrace.txt) */}
      {sessionDiag?.phpStackTraceLog && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Log (stacktrace.txt)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Delegated stacktrace.txt", sessionDiag.phpStackTraceLog!)}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <ScrollArea className="h-[200px] rounded-md border bg-orange-500/5">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all text-orange-700 dark:text-orange-300">
              {sessionDiag.phpStackTraceLog}
            </pre>
          </ScrollArea>
        </div>
      )}

      {/* Raw remoteResponseBody fallback */}
      {!delegatedServer?.Response && typeof error.context?.remoteResponseBody === 'string' && error.context.remoteResponseBody.length > 0 && (
        <div>
          <details open={phpStackFrames.length === 0}>
            <summary className="text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground flex items-center gap-2">
              <Globe className="h-4 w-4 text-orange-500" />
              Remote Response Body (raw)
            </summary>
            <div className="mt-2 relative">
              <Button variant="ghost" size="sm" className="absolute top-1 right-1 z-10" onClick={() => copySection("Remote response body", error.context!.remoteResponseBody as string)}>
                <Copy className="h-4 w-4" />
              </Button>
              <ScrollArea className="h-[250px] rounded-md border bg-orange-500/5">
                <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all text-orange-700 dark:text-orange-300">
                  {(() => {
                    try {
                      return JSON.stringify(JSON.parse(error.context!.remoteResponseBody as string), null, 2);
                    } catch {
                      return error.context!.remoteResponseBody as string;
                    }
                  })()}
                </pre>
              </ScrollArea>
            </div>
          </details>
        </div>
      )}

      {sessionLoading && (
        <div className="text-center py-4 text-muted-foreground">
          <RefreshCw className="h-5 w-5 mx-auto mb-1 animate-spin" />
          <p className="text-xs">Loading delegated session data...</p>
        </div>
      )}

      {/* Empty state */}
      {!delegatedServer && phpStackFrames.length === 0 && !envelopeDelegatedStack?.length && !sessionPhpFrames?.length && !sessionDiag?.phpStackTraceLog && !(typeof error.context?.remoteResponseBody === 'string' && error.context.remoteResponseBody.length > 0) && !sessionLoading && (
        <div className="text-center py-8 text-muted-foreground">
          <Globe className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No delegated server data available</p>
          <p className="text-xs mt-1">Delegated logs appear when the backend proxies requests to downstream services</p>
        </div>
      )}
    </div>
  );
}

/** Check if delegated content exists for an error (used to show/hide the tab). */
export function hasDelegatedContent(error: CapturedError, phpStackFrames: PHPStackFrame[]): boolean {
  return phpStackFrames.length > 0
    || !!(error.envelopeErrors?.DelegatedServiceErrorStack && error.envelopeErrors.DelegatedServiceErrorStack.length > 0)
    || !!(error.envelopeErrors?.DelegatedRequestServer?.StackTrace && error.envelopeErrors.DelegatedRequestServer.StackTrace.length > 0)
    || !!error.envelopeErrors?.DelegatedRequestServer
    || !!(typeof error.context?.remoteResponseBody === 'string' && error.context.remoteResponseBody.length > 0);
}
