import { CapturedError } from '@/stores/errorStore';
import type { SessionStackFrame, SessionDiagnostics, DelegatedRequestServer } from '@/lib/api';
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Copy, Globe, Network, AlertTriangle, RefreshCw, FileText
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useSessionDiagnostics } from "@/hooks/useSessionDiagnostics";
import type { PHPStackFrame } from "./ErrorModalTypes";
import { buildDelegatedErrorLogSection } from "./delegatedLogFormatter";

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
  const delegatedLogContent = buildDelegatedErrorLogSection(error, sessionDiag);

  return (
    <div className="space-y-4">
      {delegatedLogContent && (
        <div className="rounded-xl border border-orange-500/30 bg-orange-500/5 shadow-sm overflow-hidden">
          <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-orange-500/20 bg-orange-500/10">
            <h4 className="text-sm font-semibold flex items-center gap-2 text-orange-700 dark:text-orange-300">
              <FileText className="h-4 w-4" />
              Delegated Server Log
              <Badge variant="outline" className="text-[10px] border-orange-500/40 text-orange-600 dark:text-orange-300">delegated.log</Badge>
              <Badge variant="outline" className="text-[10px] border-orange-500/40 text-orange-600 dark:text-orange-300">Synthesized</Badge>
            </h4>
            <Button variant="outline" size="sm" className="h-9 border-orange-500/30 bg-background/70" onClick={() => copySection("Delegated server log", delegatedLogContent)}>
              <Copy className="h-4 w-4" />
              <span className="ml-2">Copy</span>
            </Button>
          </div>
          <ScrollArea className="h-[320px] bg-muted">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all leading-6 text-orange-700 dark:text-orange-300">{delegatedLogContent}</pre>
          </ScrollArea>
        </div>
      )}

      {delegatedServer && (
        <div className="rounded-xl border border-orange-500/30 bg-orange-500/5 p-4 space-y-2 shadow-sm">
          <h4 className="text-xs font-medium text-orange-600 dark:text-orange-400 uppercase tracking-wider flex items-center gap-1.5">
            <Globe className="h-3 w-3" />
            Delegated Server Request
          </h4>
          <div className="font-mono text-sm break-all flex flex-wrap items-center gap-2">
            <Badge variant={delegatedServer.StatusCode >= 400 ? "destructive" : "secondary"} className="text-xs">
              {delegatedServer.Method} {delegatedServer.StatusCode}
            </Badge>
            {delegatedServer.Namespace && (
              <Badge variant="outline" className="text-[10px] border-orange-500/40 text-orange-600 dark:text-orange-300 font-mono">
                {delegatedServer.Namespace}
              </Badge>
            )}
            <span>{delegatedServer.DelegatedEndpoint}</span>
          </div>
          {delegatedServer.AdditionalMessages && (
            <p className="text-xs text-muted-foreground">{delegatedServer.AdditionalMessages}</p>
          )}
        </div>
      )}

      {phpStackFrames.length > 0 && (
        <div className="rounded-xl border border-border/60 overflow-hidden shadow-sm">
          <div className="flex items-center justify-between px-4 py-2 bg-muted">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              PHP Stack Trace ({phpStackFrames.length} frames)
            </h4>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-xs min-w-[480px]">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-2 font-medium text-muted-foreground w-8">#</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">Function</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">File</th>
                  <th className="text-right p-2 font-medium text-muted-foreground w-12">Line</th>
                </tr>
              </thead>
              <tbody>
                {phpStackFrames.map((frame, index) => (
                  <tr key={index} className={cn("border-t border-border/50", index === 0 && "bg-primary/5")}>
                    <td className="p-2 font-mono text-muted-foreground">{index + 1}</td>
                    <td className="p-2 font-mono">
                      <span className={cn(index === 0 ? "text-primary font-semibold" : "text-foreground")}>
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
          <div className="flex justify-end p-2 bg-muted/50 border-t">
            <Button variant="ghost" size="sm" onClick={() => {
              const text = phpStackFrames.map((f, i) => {
                const fn = f.class ? `${f.class}::${f.function}` : f.function || 'unknown';
                return `#${i} ${fn}() at ${f.file || f.fileBase || 'unknown'}:${f.line || '?'}`;
              }).join('\n');
              copySection("PHP stack trace", text);
            }}>
              <Copy className="h-4 w-4 mr-1" />
              Copy
            </Button>
          </div>
        </div>
      )}

      {delegatedStackTrace && delegatedStackTrace.length > 0 && (
        <div className="rounded-xl border border-orange-500/20 bg-muted/10 p-4 shadow-sm">
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Server Stack Trace ({delegatedStackTrace.length} frames)
            </h4>
            <Button variant="outline" size="sm" className="h-9 border-orange-500/30 bg-background/70" onClick={() => copySection("Delegated stack", delegatedStackTrace.join('\n'))}>
              <Copy className="h-4 w-4" />
              <span className="ml-2">Copy</span>
            </Button>
          </div>
          <ScrollArea className="h-[220px] rounded-xl border border-orange-500/20 bg-background/80">
            <pre className="text-sm p-4 font-mono whitespace-pre-wrap break-all leading-6 text-orange-700 dark:text-orange-300">
              {delegatedStackTrace.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {envelopeDelegatedStack && envelopeDelegatedStack.length > 0 && (
        <div className="rounded-xl border border-orange-500/20 bg-muted/10 p-4 shadow-sm">
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Error Stack ({envelopeDelegatedStack.length} lines)
            </h4>
            <Button variant="outline" size="sm" className="h-9 border-orange-500/30 bg-background/70" onClick={() => copySection("Delegated error stack", envelopeDelegatedStack.join('\n'))}>
              <Copy className="h-4 w-4" />
              <span className="ml-2">Copy</span>
            </Button>
          </div>
          <ScrollArea className="h-[220px] rounded-xl border border-orange-500/20 bg-background/80">
            <pre className="text-sm p-4 font-mono whitespace-pre-wrap break-all leading-6 text-orange-700 dark:text-orange-300">
              {envelopeDelegatedStack.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {delegatedServer?.Response && (
        <div className="rounded-xl border border-border/60 bg-muted/10 p-4 shadow-sm">
          <details open>
            <summary className="text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground flex items-center gap-2">
              <Globe className="h-4 w-4" />
              Response Body
            </summary>
            <div className="mt-2 relative">
              <Button variant="outline" size="sm" className="absolute top-2 right-2 z-10 h-8 border-border/60 bg-background/70" onClick={() => {
                const text = typeof delegatedServer.Response === 'string'
                  ? delegatedServer.Response
                  : JSON.stringify(delegatedServer.Response, null, 2);
                copySection("Response body", text);
              }}>
                <Copy className="h-4 w-4" />
              </Button>
              <ScrollArea className="h-[220px] rounded-xl border border-border/60 bg-background/80">
                <pre className="text-sm p-4 font-mono whitespace-pre-wrap break-all leading-6">
                  {typeof delegatedServer.Response === 'string'
                    ? delegatedServer.Response
                    : JSON.stringify(delegatedServer.Response, null, 2)}
                </pre>
              </ScrollArea>
            </div>
          </details>
        </div>
      )}

      {delegatedServer?.RequestBody && (
        <div className="rounded-xl border border-border/60 bg-muted/10 p-4 shadow-sm">
          <details>
            <summary className="text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground flex items-center gap-2">
              <Network className="h-4 w-4" />
              Request Body (sent to delegated server)
            </summary>
            <div className="mt-2 relative">
              <Button variant="outline" size="sm" className="absolute top-2 right-2 z-10 h-8 border-border/60 bg-background/70" onClick={() => {
                const text = typeof delegatedServer.RequestBody === 'string'
                  ? delegatedServer.RequestBody
                  : JSON.stringify(delegatedServer.RequestBody, null, 2);
                copySection("Request body", text);
              }}>
                <Copy className="h-4 w-4" />
              </Button>
              <ScrollArea className="h-[220px] rounded-xl border border-border/60 bg-background/80">
                <pre className="text-sm p-4 font-mono whitespace-pre-wrap break-all leading-6">
                  {typeof delegatedServer.RequestBody === 'string'
                    ? delegatedServer.RequestBody
                    : JSON.stringify(delegatedServer.RequestBody, null, 2)}
                </pre>
              </ScrollArea>
            </div>
          </details>
        </div>
      )}

      {sessionPhpFrames && sessionPhpFrames.length > 0 && (
        <div className="rounded-xl border border-orange-500/20 bg-muted/10 p-4 shadow-sm">
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Stack (Session) ({sessionPhpFrames.length} frames)
            </h4>
          </div>
          <ScrollArea className="h-[220px] rounded-xl border border-orange-500/20 bg-background/80">
            <div className="p-4 space-y-1">
              {sessionPhpFrames.map((frame, i) => (
                <div key={i} className="text-sm font-mono leading-6">
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

      {sessionDiag?.phpStackTraceLog && (
        <div className="rounded-xl border border-orange-500/20 bg-muted/10 p-4 shadow-sm">
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              Delegated Log (stacktrace.txt)
            </h4>
            <Button variant="outline" size="sm" className="h-9 border-orange-500/30 bg-background/70" onClick={() => copySection("Delegated stacktrace.txt", sessionDiag.phpStackTraceLog!)}>
              <Copy className="h-4 w-4" />
              <span className="ml-2">Copy</span>
            </Button>
          </div>
          <ScrollArea className="h-[220px] rounded-xl border border-orange-500/20 bg-background/80">
            <pre className="text-sm p-4 font-mono whitespace-pre-wrap break-all leading-6 text-orange-700 dark:text-orange-300">
              {sessionDiag.phpStackTraceLog}
            </pre>
          </ScrollArea>
        </div>
      )}

      {!delegatedServer?.Response && typeof error.context?.remoteResponseBody === 'string' && error.context.remoteResponseBody.length > 0 && (
        <div className="rounded-xl border border-orange-500/20 bg-muted/10 p-4 shadow-sm">
          <details open={phpStackFrames.length === 0}>
            <summary className="text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground flex items-center gap-2">
              <Globe className="h-4 w-4 text-orange-500" />
              Remote Response Body (raw)
            </summary>
            <div className="mt-2 relative">
              <Button variant="outline" size="sm" className="absolute top-2 right-2 z-10 h-8 border-orange-500/30 bg-background/70" onClick={() => copySection("Remote response body", error.context!.remoteResponseBody as string)}>
                <Copy className="h-4 w-4" />
              </Button>
              <ScrollArea className="h-[250px] rounded-xl border border-orange-500/20 bg-background/80">
                <pre className="text-sm p-4 font-mono whitespace-pre-wrap break-all leading-6 text-orange-700 dark:text-orange-300">
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
          <p className="text-sm">Loading delegated session data...</p>
        </div>
      )}

      {!delegatedLogContent && !delegatedServer && phpStackFrames.length === 0 && !envelopeDelegatedStack?.length && !sessionPhpFrames?.length && !sessionDiag?.phpStackTraceLog && !(typeof error.context?.remoteResponseBody === 'string' && error.context.remoteResponseBody.length > 0) && !sessionLoading && (
        <div className="text-center py-8 text-muted-foreground rounded-xl border border-border/60 bg-muted/10">
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
