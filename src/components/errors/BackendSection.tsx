import { CapturedError } from '@/stores/errorStore';
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Copy, AlertCircle, Network, Globe, Server, Terminal, Download,
  Activity, FileText, Code2, Route, RefreshCw, Loader2, AlertTriangle
} from "lucide-react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { unescapeEmbeddedNewlines } from "@/lib/logText";
import { useSessionDiagnostics } from "@/hooks/useSessionDiagnostics";
import { SessionLogsTab } from "@/components/errors/SessionLogsTab";
import { RequestDetails } from "@/components/errors/RequestDetails";
import { TraversalDetails } from "@/components/errors/TraversalDetails";
import type { PHPStackFrame, SectionCommonProps } from "./ErrorModalTypes";

interface BackendSectionProps extends SectionCommonProps {
  error: CapturedError;
  phpStackFrames: PHPStackFrame[];
  errorLogContent: string | null;
  errorLogLoading: boolean;
  errorLogError: string | null;
  errorLogFetched: boolean;
  onRefreshLog: () => void;
}

export function BackendSection({
  error,
  phpStackFrames,
  errorLogContent,
  errorLogLoading,
  errorLogError,
  errorLogFetched,
  onRefreshLog,
  copySection,
  formatTs,
}: BackendSectionProps) {
  const { diagnostics: sessionDiag, loading: sessionLoading } = useSessionDiagnostics(error.sessionId);

  const envelopeBackendStack = error.envelopeErrors?.Backend;
  const envelopeDelegatedStack = error.envelopeErrors?.DelegatedServiceErrorStack;
  const envelopeMethodsBackend = error.envelopeMethodsStack?.Backend;

  const sessionGoFrames = sessionDiag?.stackTrace?.golang;
  const sessionPhpFrames = sessionDiag?.stackTrace?.php;

  const hasStackContent = phpStackFrames.length > 0 
    || !!error.backendStackTrace 
    || (envelopeBackendStack && envelopeBackendStack.length > 0)
    || (envelopeDelegatedStack && envelopeDelegatedStack.length > 0)
    || (sessionGoFrames && sessionGoFrames.length > 0)
    || (sessionPhpFrames && sessionPhpFrames.length > 0)
    || !!sessionDiag?.phpStackTraceLog;

  const hasExecutionContent = (error.backendLogs && error.backendLogs.length > 0)
    || (envelopeMethodsBackend && envelopeMethodsBackend.length > 0);

  return (
    <Tabs defaultValue="overview" className="w-full">
      <div className="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
        <TabsList className="mb-4 inline-flex h-auto gap-1 min-w-max sm:flex sm:flex-wrap">
          <TabsTrigger value="overview" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <AlertCircle className="h-3 w-3" />
            Overview
          </TabsTrigger>
          <TabsTrigger value="logs" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Terminal className="h-3 w-3" />
            Log
          </TabsTrigger>
          <TabsTrigger value="execution" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Activity className="h-3 w-3" />
            <span className="hidden sm:inline">Execution</span>
            <span className="sm:hidden">Exec</span>
          </TabsTrigger>
          <TabsTrigger value="stack" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Code2 className="h-3 w-3" />
            Stack
          </TabsTrigger>
          {error.sessionId && (
            <TabsTrigger value="session" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
              <FileText className="h-3 w-3" />
              Session
            </TabsTrigger>
          )}
          <TabsTrigger value="request" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Network className="h-3 w-3" />
            Request
          </TabsTrigger>
          {(error.envelopeErrors || error.envelopeMethodsStack || error.requestedAt) && (
            <TabsTrigger value="traversal" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
              <Route className="h-3 w-3" />
              Traversal
            </TabsTrigger>
          )}
        </TabsList>
      </div>

      {/* Overview Tab */}
      <TabsContent value="overview" className="space-y-4 m-0">
        <OverviewContent error={error} formatTs={formatTs} hasStackContent={hasStackContent} hasExecutionContent={hasExecutionContent} />
      </TabsContent>

      {/* Error Log Tab */}
      <TabsContent value="logs" className="space-y-4 m-0">
        <ErrorLogContent
          error={error}
          errorLogContent={errorLogContent}
          errorLogLoading={errorLogLoading}
          errorLogError={errorLogError}
          errorLogFetched={errorLogFetched}
          onRefreshLog={onRefreshLog}
          copySection={copySection}
        />
      </TabsContent>

      {/* Execution Logs Tab */}
      <TabsContent value="execution" className="space-y-4 m-0">
        <ExecutionContent
          error={error}
          envelopeMethodsBackend={envelopeMethodsBackend}
          hasExecutionContent={hasExecutionContent}
          copySection={copySection}
          formatTs={formatTs}
        />
      </TabsContent>

      {/* Stack Traces Tab */}
      <TabsContent value="stack" className="space-y-4 m-0">
        <StackContent
          error={error}
          phpStackFrames={phpStackFrames}
          envelopeBackendStack={envelopeBackendStack}
          envelopeDelegatedStack={envelopeDelegatedStack}
          sessionGoFrames={sessionGoFrames}
          sessionPhpFrames={sessionPhpFrames}
          sessionDiag={sessionDiag}
          sessionLoading={sessionLoading}
          hasStackContent={hasStackContent}
          copySection={copySection}
        />
      </TabsContent>

      {/* Session Tab */}
      {error.sessionId && (
        <TabsContent value="session" className="m-0">
          <SessionLogsTab sessionId={error.sessionId} sessionType={error.sessionType} />
        </TabsContent>
      )}

      {/* Request Tab */}
      <TabsContent value="request" className="space-y-4 m-0">
        <RequestDetails error={error} copySection={copySection} sessionDiagnostics={sessionDiag} />
      </TabsContent>

      {/* Traversal Tab */}
      {(error.envelopeErrors || error.envelopeMethodsStack || error.requestedAt) && (
        <TabsContent value="traversal" className="space-y-4 m-0">
          <TraversalDetails error={error} copySection={copySection} />
        </TabsContent>
      )}
    </Tabs>
  );
}

// --- Internal sub-components (not exported — only used within BackendSection) ---

function OverviewContent({ error, formatTs, hasStackContent, hasExecutionContent }: {
  error: CapturedError; formatTs: (ts: string) => string;
  hasStackContent: boolean; hasExecutionContent: boolean;
}) {
  return (
    <>
      <div className="rounded-md border p-4 space-y-3">
        <div className="flex items-start gap-3">
          <AlertCircle className="h-5 w-5 text-destructive shrink-0 mt-0.5" />
          <div className="min-w-0 flex-1 space-y-1">
            <p className="text-sm font-medium">{error.message}</p>
            <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
              <Badge variant="secondary" className="text-xs">{error.code}</Badge>
              <span>{formatTs(error.createdAt)}</span>
              {error.responseStatus && (
                <Badge variant="outline" className="text-xs">HTTP {error.responseStatus}</Badge>
              )}
            </div>
          </div>
        </div>
      </div>

      {error.siteUrl && (
        <div className="flex items-center gap-2 p-3 bg-muted rounded-md">
          <Globe className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm text-muted-foreground">Target Site:</span>
          <a href={error.siteUrl} target="_blank" rel="noopener noreferrer"
            className="text-sm text-primary hover:underline flex items-center gap-1">
            {error.siteUrl}
          </a>
        </div>
      )}

      {(error.endpoint || error.method) && (
        <div className="rounded-md border p-3 space-y-2">
          <h4 className="text-xs font-medium text-muted-foreground uppercase tracking-wider">Request</h4>
          <div className="font-mono text-xs break-all">
            <span className="text-primary font-semibold">{error.method || 'GET'}</span>{' '}
            <span>{error.endpoint || 'unknown'}</span>
          </div>
        </div>
      )}

      {error.envelopeErrors?.BackendMessage && (
        <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 space-y-2">
          <h4 className="text-xs font-medium text-destructive uppercase tracking-wider flex items-center gap-1.5">
            <Server className="h-3 w-3" />
            Backend Error
          </h4>
          <p className="text-sm font-mono break-all">{error.envelopeErrors.BackendMessage}</p>
        </div>
      )}

      {(error.requestedAt || error.requestDelegatedAt) && (
        <div className="rounded-md border p-3 space-y-1">
          <h4 className="text-xs font-medium text-muted-foreground uppercase tracking-wider">Timing</h4>
          {error.requestedAt && (
            <div className="flex justify-between text-xs">
              <span className="text-muted-foreground">Requested At</span>
              <span className="font-mono">{formatTs(error.requestedAt)}</span>
            </div>
          )}
          {error.requestDelegatedAt && (
            <div className="flex justify-between text-xs">
              <span className="text-muted-foreground">Delegated At</span>
              <span className="font-mono">{formatTs(error.requestDelegatedAt)}</span>
            </div>
          )}
        </div>
      )}

      <div className="flex flex-wrap gap-2">
        {error.sessionId && (
          <Badge variant="outline" className="text-xs gap-1">
            <FileText className="h-3 w-3" />
            Session: {error.sessionId.slice(0, 8)}…
          </Badge>
        )}
        {hasStackContent && (
          <Badge variant="outline" className="text-xs gap-1">
            <Code2 className="h-3 w-3" />
            Stack traces available
          </Badge>
        )}
        {hasExecutionContent && (
          <Badge variant="outline" className="text-xs gap-1">
            <Activity className="h-3 w-3" />
            Execution logs available
          </Badge>
        )}
      </div>
    </>
  );
}

function ErrorLogContent({ error, errorLogContent, errorLogLoading, errorLogError, errorLogFetched, onRefreshLog, copySection }: {
  error: CapturedError;
  errorLogContent: string | null;
  errorLogLoading: boolean;
  errorLogError: string | null;
  errorLogFetched: boolean;
  onRefreshLog: () => void;
  copySection: (label: string, content: string) => void;
}) {
  return (
    <>
      {error.siteUrl && (
        <div className="flex items-center gap-2 p-3 bg-muted rounded-md">
          <Globe className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm text-muted-foreground">Target Site:</span>
          <a href={error.siteUrl} target="_blank" rel="noopener noreferrer"
            className="text-sm text-primary hover:underline flex items-center gap-1">
            {error.siteUrl}
          </a>
        </div>
      )}

      <div>
        <div className="flex items-center justify-between mb-2">
          <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
            <Terminal className="h-4 w-4" />
            Backend Error Log (error.log.txt)
          </h4>
          <div className="flex items-center gap-1">
            <Button variant="ghost" size="sm" onClick={onRefreshLog} disabled={errorLogLoading}>
              {errorLogLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
            </Button>
            {errorLogContent && (
              <>
                <Button variant="ghost" size="sm" onClick={() => copySection("Backend error log", errorLogContent)}>
                  <Copy className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="sm" onClick={() => {
                  const blob = new Blob([errorLogContent], { type: "text/plain" });
                  const url = window.URL.createObjectURL(blob);
                  const link = document.createElement("a");
                  link.href = url;
                  link.download = "error.log.txt";
                  link.click();
                  window.URL.revokeObjectURL(url);
                  toast.success("Downloaded error.log.txt");
                }}>
                  <Download className="h-4 w-4" />
                </Button>
              </>
            )}
          </div>
        </div>

        {errorLogLoading && !errorLogContent && (
          <div className="flex items-center justify-center py-6 text-muted-foreground">
            <Loader2 className="h-5 w-5 animate-spin mr-2" />
            <span className="text-sm">Loading error log...</span>
          </div>
        )}
        {errorLogError && !errorLogContent && (
          <div className="text-center py-6 text-muted-foreground">
            <AlertCircle className="h-6 w-6 mx-auto mb-2 opacity-50" />
            <p className="text-sm">{errorLogError}</p>
            <Button variant="link" size="sm" onClick={onRefreshLog} className="mt-1">Retry</Button>
          </div>
        )}
        {errorLogContent && (
          <ScrollArea className="h-[400px] rounded-md border bg-muted">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">{errorLogContent}</pre>
          </ScrollArea>
        )}
        {!errorLogLoading && !errorLogError && !errorLogContent && errorLogFetched && (
          <div className="text-center py-6 text-muted-foreground">
            <Terminal className="h-6 w-6 mx-auto mb-2 opacity-50" />
            <p className="text-sm">No error log content available</p>
          </div>
        )}
      </div>
    </>
  );
}

function ExecutionContent({ error, envelopeMethodsBackend, hasExecutionContent, copySection, formatTs }: {
  error: CapturedError;
  envelopeMethodsBackend: CapturedError['envelopeMethodsStack'] extends { Backend: infer B } ? B : any;
  hasExecutionContent: boolean;
  copySection: (label: string, content: string) => void;
  formatTs: (ts: string) => string;
}) {
  return (
    <>
      {envelopeMethodsBackend && envelopeMethodsBackend.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Route className="h-4 w-4" />
              Go Call Chain ({envelopeMethodsBackend.length} frames)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => {
              const text = envelopeMethodsBackend.map((f: any, i: number) => 
                `#${i} ${f.Method} at ${f.File}:${f.LineNumber}`
              ).join('\n');
              copySection("Go call chain", text);
            }}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <div className="border rounded-md overflow-hidden">
            <table className="w-full text-xs">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-2 font-medium text-muted-foreground w-8">#</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">Method</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">File</th>
                  <th className="text-right p-2 font-medium text-muted-foreground">Line</th>
                </tr>
              </thead>
              <tbody>
                {envelopeMethodsBackend.map((frame: any, index: number) => (
                  <tr key={index} className={cn("border-t border-border/50", index === 0 && "bg-primary/5")}>
                    <td className="p-2 font-mono text-muted-foreground">{index}</td>
                    <td className="p-2 font-mono font-semibold">{frame.Method}</td>
                    <td className="p-2 font-mono text-muted-foreground truncate max-w-[200px]">{frame.File}</td>
                    <td className="p-2 font-mono text-right">{frame.LineNumber}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {error.backendLogs && error.backendLogs.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Session Execution Logs ({error.backendLogs.length} entries)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => {
              const logText = error.backendLogs!
                .map(l => {
                  const base = `[${formatTs(l.timestamp)}] [${l.level.toUpperCase()}]${l.step ? ` [${l.step}]` : ''} ${unescapeEmbeddedNewlines(l.message)}`;
                  if (l.details && Object.keys(l.details).length > 0) {
                    return `${base}\n${unescapeEmbeddedNewlines(JSON.stringify(l.details, null, 2))}`;
                  }
                  return base;
                })
                .join('\n\n');
              copySection("Backend logs", logText);
            }}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <ScrollArea className="h-[400px] rounded-md border">
            <div className="p-3 space-y-1">
              {error.backendLogs.map((log, idx) => (
                <BackendLogEntry key={idx} log={log} formatTs={formatTs} />
              ))}
            </div>
          </ScrollArea>
        </div>
      )}

      {!hasExecutionContent && (
        <div className="text-center py-8 text-muted-foreground">
          <Activity className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No execution logs captured</p>
          <p className="text-xs mt-1">Enable <strong>includeMethodsStack</strong> in Settings → Developer for Go call chains</p>
          <p className="text-xs">Session logs appear during publish, sync, and test operations</p>
        </div>
      )}
    </>
  );
}

function StackContent({ error, phpStackFrames, envelopeBackendStack, envelopeDelegatedStack, sessionGoFrames, sessionPhpFrames, sessionDiag, sessionLoading, hasStackContent, copySection }: {
  error: CapturedError;
  phpStackFrames: PHPStackFrame[];
  envelopeBackendStack: string[] | undefined;
  envelopeDelegatedStack: string[] | undefined;
  sessionGoFrames: any[] | undefined;
  sessionPhpFrames: any[] | undefined;
  sessionDiag: any;
  sessionLoading: boolean;
  hasStackContent: boolean;
  copySection: (label: string, content: string) => void;
}) {
  return (
    <>
      {envelopeDelegatedStack && envelopeDelegatedStack.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              PHP Delegated Error Stack ({envelopeDelegatedStack.length} lines)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("PHP delegated stack", envelopeDelegatedStack.join('\n'))}>
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

      {envelopeBackendStack && envelopeBackendStack.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Server className="h-4 w-4" />
              Go Backend Stack ({envelopeBackendStack.length} lines)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Go backend stack", envelopeBackendStack.join('\n'))}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <ScrollArea className="h-[200px] rounded-md border bg-muted">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">{envelopeBackendStack.join('\n')}</pre>
          </ScrollArea>
        </div>
      )}

      {error.backendStackTrace && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Server className="h-4 w-4" />
              Go Stack Trace (raw)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Go stack trace", error.backendStackTrace!)}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <ScrollArea className="h-[300px] rounded-md border bg-muted">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">{error.backendStackTrace}</pre>
          </ScrollArea>
        </div>
      )}

      {sessionGoFrames && sessionGoFrames.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Server className="h-4 w-4" />
              Go Stack (Session) ({sessionGoFrames.length} frames)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => {
              const text = sessionGoFrames.map((f: any, i: number) => 
                `#${i} ${f.class ? `${f.class}::` : ''}${f.function} at ${f.file || 'unknown'}:${f.line || '?'}`
              ).join('\n');
              copySection("Session Go stack", text);
            }}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <ScrollArea className="h-[200px] rounded-md border bg-muted">
            <div className="p-3 space-y-1">
              {sessionGoFrames.map((frame: any, i: number) => (
                <div key={i} className="text-xs font-mono leading-relaxed">
                  <span className="text-muted-foreground mr-1">#{i}</span>
                  <span className="font-semibold text-blue-500 dark:text-blue-400">
                    {frame.class ? `${frame.class}::${frame.function}` : frame.function}
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

      {sessionPhpFrames && sessionPhpFrames.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              PHP Stack (Session) ({sessionPhpFrames.length} frames)
            </h4>
          </div>
          <ScrollArea className="h-[200px] rounded-md border bg-orange-500/5">
            <div className="p-3 space-y-1">
              {sessionPhpFrames.map((frame: any, i: number) => (
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

      {sessionDiag?.phpStackTraceLog && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              PHP Log (stacktrace.txt)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("PHP stacktrace.txt", sessionDiag.phpStackTraceLog!)}>
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

      {sessionLoading && !hasStackContent && (
        <div className="text-center py-4 text-muted-foreground">
          <RefreshCw className="h-5 w-5 mx-auto mb-1 animate-spin" />
          <p className="text-xs">Loading session stack traces...</p>
        </div>
      )}

      {!hasStackContent && !sessionLoading && (
        <div className="text-center py-8 text-muted-foreground">
          <Code2 className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No backend stack traces available</p>
          <p className="text-xs mt-1">Enable <strong>includeStackTrace</strong> in Settings → Developer for Go/PHP stacks</p>
        </div>
      )}
    </>
  );
}

function BackendLogEntry({ log, formatTs }: { log: CapturedError['backendLogs'][0]; formatTs: (ts: string) => string }) {
  const details = log.details as Record<string, unknown> | undefined;
  const hasDetails = details && Object.keys(details).length > 0;
  const request = hasDetails && typeof details.request === "object" ? (details.request as Record<string, unknown>) : undefined;
  const response = hasDetails && typeof details.response === "object" ? (details.response as Record<string, unknown>) : undefined;
  const method = request && typeof request.method === "string" ? request.method : undefined;
  const endpoint = request && typeof request.endpoint === "string" ? request.endpoint : undefined;
  const url = request && typeof request.url === "string" ? request.url : undefined;
  const status = response && typeof response.status === "number" ? response.status : undefined;
  const zipPath = hasDetails && typeof details.zipPath === "string" ? details.zipPath : undefined;
  const remoteSlug = hasDetails && typeof details.remoteSlug === "string" ? details.remoteSlug : undefined;

  return (
    <div className={cn(
      "text-xs font-mono py-1 px-2 rounded",
      log.level === 'error' && "bg-destructive/10 text-destructive",
      log.level === 'warn' && "bg-warning/10 text-warning",
      log.level === 'info' && "bg-primary/10 text-primary",
      log.level === 'debug' && "bg-muted text-muted-foreground"
    )}>
      <span className="text-muted-foreground">[{formatTs(log.timestamp)}]</span>
      {log.step && <span className="text-primary ml-1">[{log.step}]</span>}
      <span className="ml-1 whitespace-pre-wrap break-words">{unescapeEmbeddedNewlines(log.message)}</span>

      {hasDetails && (!method && !endpoint && !url && !zipPath && !remoteSlug && !status) && (
        <pre className="mt-1 ml-4 text-muted-foreground whitespace-pre-wrap break-words">
          {unescapeEmbeddedNewlines(JSON.stringify(details, null, 2))}
        </pre>
      )}

      {hasDetails && (method || endpoint || url || zipPath || remoteSlug || status) && (
        <div className="mt-1 ml-4 space-y-1 text-muted-foreground whitespace-pre-wrap break-words">
          {(method || endpoint) && (
            <div className="flex flex-wrap items-center gap-2">
              <span>Endpoint:</span>
              {method && <Badge variant="outline" className="font-mono">{method}</Badge>}
              {endpoint && <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{endpoint}</code>}
              {typeof status === "number" && (
                <Badge variant={status >= 400 ? "destructive" : "secondary"}>{status}</Badge>
              )}
            </div>
          )}
          {url && (
            <div>
              <span>URL: </span>
              <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{url}</code>
            </div>
          )}
          {remoteSlug && (
            <div>
              <span>Plugin slug: </span>
              <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{remoteSlug}</code>
            </div>
          )}
          {zipPath && (
            <div>
              <span>ZIP: </span>
              <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{zipPath}</code>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
