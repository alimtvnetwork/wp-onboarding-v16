import { useState, useEffect } from "react";
import { useErrorStore, CapturedError, StackFrame } from '@/stores/errorStore';
import { EnvelopeErrors, EnvelopeMethodsStack } from '@/lib/api';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { 
  Copy, ExternalLink, AlertCircle, FileCode2, Network, Lightbulb, Globe, 
  ChevronRight, Layers, Server, Terminal, Download, Activity, FileText, 
  ChevronDown, FileDown, RefreshCw, Loader2, AlertTriangle, MousePointerClick, 
  ChevronLeft, CopyPlus, Monitor, Code2, Route
} from "lucide-react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import { JsonHighlighter } from "@/components/shared/JsonHighlighter";
import { SessionLogsTab } from "@/components/errors/SessionLogsTab";
import { formatDateTimeUtc, toClipboardText, unescapeEmbeddedNewlines } from "@/lib/logText";
import { api } from "@/lib/api";

// Type for PHP stack trace frame
interface PHPStackFrame {
  file?: string;
  fileBase?: string;
  line?: number;
  function?: string;
  class?: string;
}

export function GlobalErrorModal() {
  const { selectedError, isModalOpen, closeErrorModal, errorQueue, currentQueueIndex, navigateQueue, getQueuedErrorsMarkdown } = useErrorStore();
  const { data: versionInfo } = useVersionInfo();
  const appName = versionInfo?.appName || "WP Plugin Publish";
  const appVersion = versionInfo?.version || "0.0.0";
  const gitCommit = versionInfo?.gitCommit;
  const buildTime = versionInfo?.buildTime;
  
  // Top-level section: "backend" (default) or "frontend"
  const [activeSection, setActiveSection] = useState<"backend" | "frontend">("backend");
  const [showRawStack, setShowRawStack] = useState(false);
  const [showInternalFrames, setShowInternalFrames] = useState(false);
  
  // Backend error log state
  const [errorLogContent, setErrorLogContent] = useState<string | null>(null);
  const [errorLogLoading, setErrorLogLoading] = useState(false);
  const [errorLogError, setErrorLogError] = useState<string | null>(null);
  const [errorLogFetched, setErrorLogFetched] = useState(false);
  
  // Extract PHP stack trace frames from error context if available
  const phpStackFrames: PHPStackFrame[] = (() => {
    const ctx = selectedError?.context as Record<string, unknown> | undefined;
    if (!ctx) return [];
    if (Array.isArray(ctx.stackTraceFrames)) {
      return ctx.stackTraceFrames as PHPStackFrame[];
    }
    const errorDetails = ctx.errorDetails as Record<string, unknown> | undefined;
    if (errorDetails && Array.isArray(errorDetails.stackTraceFrames)) {
      return errorDetails.stackTraceFrames as PHPStackFrame[];
    }
    return [];
  })();
  
  // Fetch error log when Backend section is active
  const fetchErrorLog = async () => {
    if (errorLogFetched) return;
    setErrorLogLoading(true);
    setErrorLogError(null);
    try {
      const resp = await api.getBackendErrorLog();
      if (resp.success && resp.data) {
        setErrorLogContent(resp.data.content);
      } else {
        setErrorLogError(resp.error?.message || "No error log available");
      }
    } catch (err) {
      setErrorLogError(err instanceof Error ? err.message : "Failed to fetch error log");
    } finally {
      setErrorLogLoading(false);
      setErrorLogFetched(true);
    }
  };
  
  // Auto-fetch when backend section is selected
  useEffect(() => {
    if (isModalOpen && activeSection === "backend" && !errorLogFetched) {
      fetchErrorLog();
    }
  }, [isModalOpen, activeSection, errorLogFetched]);
  
  // Reset state when modal closes or error changes
  useEffect(() => {
    if (!isModalOpen) {
      setErrorLogContent(null);
      setErrorLogFetched(false);
      setErrorLogError(null);
      setActiveSection("backend"); // Reset to backend on close
    }
  }, [isModalOpen, selectedError?.id]);
  
  if (!selectedError) return null;

  const copyFullError = async () => {
    // Build base report
    let text = generateErrorReport(selectedError, { appName, appVersion, gitCommit, buildTime });
    
    // Fetch error.log.txt async and append
    try {
      const resp = await api.getBackendErrorLog();
      if (resp.success && resp.data?.content) {
        text += `\n### Backend error.log.txt\n\`\`\`\n${resp.data.content}\n\`\`\`\n`;
      }
    } catch {
      // Silently skip if unavailable
    }
    
    navigator.clipboard.writeText(toClipboardText(text));
    toast.success("Full error report copied to clipboard");
  };

  const copyAllErrors = () => {
    const text = getQueuedErrorsMarkdown();
    navigator.clipboard.writeText(toClipboardText(text));
    toast.success(`Copied ${errorQueue.length} error(s) to clipboard`);
  };

  const copySection = (label: string, content: string) => {
    navigator.clipboard.writeText(toClipboardText(content));
    toast.success(`${label} copied`);
  };

  const formatTs = (ts: string) => formatDateTimeUtc(ts);

  const levelColors = {
    error: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
    warn: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
    info: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
  };

  const suggestedFixes = getSuggestedFixes(selectedError.code);
  
  const displayFrames = showInternalFrames 
    ? selectedError.parsedFrames 
    : selectedError.parsedFrames?.filter(f => !f.isInternal);

  const hasMultipleErrors = errorQueue.length > 1;

  return (
    <Dialog open={isModalOpen} onOpenChange={closeErrorModal}>
      <DialogContent className={cn(
        "flex flex-col p-0 gap-0 overflow-hidden",
        // Mobile: full screen
        "w-full h-full max-w-full max-h-full rounded-none",
        // Tablet and up: 95% viewport with rounded corners
        "sm:max-w-[95vw] sm:w-[95vw] sm:max-h-[95vh] sm:h-[95vh] sm:rounded-lg",
        // Large screens: cap at reasonable max width
        "lg:max-w-6xl"
      )}>
        {/* Header - Fixed at top, responsive padding */}
        <DialogHeader className="px-4 py-3 sm:px-6 sm:py-4 border-b shrink-0">
          <div className="flex items-center justify-between gap-2 sm:gap-3">
            <div className="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
              <AlertCircle className={cn(
                "h-5 w-5 sm:h-6 sm:w-6 shrink-0",
                selectedError.level === "error"
                  ? "text-destructive"
                  : selectedError.level === "warn"
                    ? "text-warning"
                    : "text-muted-foreground"
              )} />
              <div className="min-w-0 flex-1">
                <DialogTitle className="flex items-center gap-2 flex-wrap text-base sm:text-lg">
                  <span className="hidden sm:inline">Error Details</span>
                  <span className="sm:hidden">Error</span>
                  <Badge 
                    variant="secondary" 
                    className={cn("text-xs", levelColors[selectedError.level] || "")}
                  >
                    {selectedError.code}
                  </Badge>
                </DialogTitle>
                <DialogDescription className="truncate text-xs sm:text-sm">
                  <span>{formatTs(selectedError.createdAt)}</span>
                  <span className="hidden sm:inline">
                    <span className="mx-2">•</span>
                    <span className="font-mono">{appName} v{appVersion}</span>
                  </span>
                </DialogDescription>
              </div>
            </div>
            
            {/* Queue Navigation - Compact on mobile */}
            {hasMultipleErrors && (
              <div className="flex items-center gap-1 shrink-0">
                <Button
                  variant="outline"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => navigateQueue('prev')}
                  title="Previous error"
                >
                  <ChevronLeft className="h-4 w-4" />
                </Button>
                <Badge variant="secondary" className="px-2 py-1 font-mono text-xs">
                  {currentQueueIndex + 1}/{errorQueue.length}
                </Badge>
                <Button
                  variant="outline"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => navigateQueue('next')}
                  title="Next error"
                >
                  <ChevronRight className="h-4 w-4" />
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  className="h-7 w-7 sm:hidden"
                  onClick={copyAllErrors}
                  title="Copy all errors"
                >
                  <CopyPlus className="h-3 w-3" />
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="h-7 ml-1 hidden sm:flex"
                  onClick={copyAllErrors}
                  title="Copy all errors"
                >
                  <CopyPlus className="h-3 w-3 mr-1" />
                  All
                </Button>
              </div>
            )}
          </div>
        </DialogHeader>

        {/* Main Content - Two-level tab structure */}
        <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
          {/* Top-level section tabs: Backend | Frontend - Responsive */}
          <div className="px-4 pt-3 pb-2 sm:px-6 sm:pt-4 border-b bg-muted/30 shrink-0">
            <div className="flex items-center gap-2">
              <Button
                variant={activeSection === "backend" ? "default" : "outline"}
                size="sm"
                onClick={() => setActiveSection("backend")}
                className="gap-1.5 sm:gap-2 text-xs sm:text-sm flex-1 sm:flex-none"
              >
                <Server className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                Backend
              </Button>
              <Button
                variant={activeSection === "frontend" ? "default" : "outline"}
                size="sm"
                onClick={() => setActiveSection("frontend")}
                className="gap-1.5 sm:gap-2 text-xs sm:text-sm flex-1 sm:flex-none"
              >
                <Monitor className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                Frontend
              </Button>
            </div>
          </div>

          {/* Scrollable content area - Touch-friendly */}
          <ScrollArea className="flex-1 min-h-0 touch-pan-y">
            <div className="p-4 sm:p-6">
              {activeSection === "backend" ? (
                <BackendSection 
                  error={selectedError}
                  phpStackFrames={phpStackFrames}
                  errorLogContent={errorLogContent}
                  errorLogLoading={errorLogLoading}
                  errorLogError={errorLogError}
                  errorLogFetched={errorLogFetched}
                  onRefreshLog={() => {
                    setErrorLogFetched(false);
                    setErrorLogError(null);
                    fetchErrorLog();
                  }}
                  copySection={copySection}
                  formatTs={formatTs}
                />
              ) : (
                <FrontendSection 
                  error={selectedError}
                  showRawStack={showRawStack}
                  setShowRawStack={setShowRawStack}
                  showInternalFrames={showInternalFrames}
                  setShowInternalFrames={setShowInternalFrames}
                  displayFrames={displayFrames}
                  suggestedFixes={suggestedFixes}
                  copySection={copySection}
                  formatTs={formatTs}
                />
              )}
            </div>
          </ScrollArea>
        </div>

        {/* Footer - Fixed at bottom, responsive */}
        <div className="flex flex-wrap justify-end gap-2 px-4 py-3 sm:px-6 sm:py-4 border-t shrink-0 bg-background">
          {/* On mobile: stack buttons, hide labels */}
          <div className="flex gap-2 w-full sm:w-auto justify-end">
            <DownloadDropdown 
              error={selectedError} 
              appName={appName} 
              appVersion={appVersion}
              gitCommit={gitCommit}
              buildTime={buildTime}
            />
            <Button variant="outline" onClick={closeErrorModal} className="text-xs sm:text-sm">
              Close
            </Button>
            <CopyDropdown 
              error={selectedError}
              appName={appName}
              appVersion={appVersion}
              gitCommit={gitCommit}
              buildTime={buildTime}
              copyFullError={copyFullError}
            />
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

// ============================================================================
// BACKEND SECTION
// ============================================================================
interface BackendSectionProps {
  error: CapturedError;
  phpStackFrames: PHPStackFrame[];
  errorLogContent: string | null;
  errorLogLoading: boolean;
  errorLogError: string | null;
  errorLogFetched: boolean;
  onRefreshLog: () => void;
  copySection: (label: string, content: string) => void;
  formatTs: (ts: string) => string;
}

function BackendSection({
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
  return (
    <Tabs defaultValue="logs" className="w-full">
      {/* Horizontally scrollable tabs on mobile */}
      <div className="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
        <TabsList className="mb-4 inline-flex h-auto gap-1 min-w-max sm:flex sm:flex-wrap">
          <TabsTrigger value="logs" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Terminal className="h-3 w-3" />
            <span className="hidden xs:inline">Error</span> Log
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

      {/* Error Log Tab */}
      <TabsContent value="logs" className="space-y-4 m-0">
        {/* Site URL if available */}
        {error.siteUrl && (
          <div className="flex items-center gap-2 p-3 bg-muted rounded-md">
            <Globe className="h-4 w-4 text-muted-foreground" />
            <span className="text-sm text-muted-foreground">Target Site:</span>
            <a 
              href={error.siteUrl} 
              target="_blank" 
              rel="noopener noreferrer"
              className="text-sm text-primary hover:underline flex items-center gap-1"
            >
              {error.siteUrl}
              <ExternalLink className="h-3 w-3" />
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
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      const blob = new Blob([errorLogContent], { type: "text/plain" });
                      const url = window.URL.createObjectURL(blob);
                      const link = document.createElement("a");
                      link.href = url;
                      link.download = "error.log.txt";
                      link.click();
                      window.URL.revokeObjectURL(url);
                      toast.success("Downloaded error.log.txt");
                    }}
                  >
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
              <Button variant="link" size="sm" onClick={onRefreshLog} className="mt-1">
                Retry
              </Button>
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
      </TabsContent>

      {/* Execution Logs Tab */}
      <TabsContent value="execution" className="space-y-4 m-0">
        {error.backendLogs && error.backendLogs.length > 0 ? (
          <div>
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <Activity className="h-4 w-4" />
                Execution Logs ({error.backendLogs.length} entries)
              </h4>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
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
                }}
              >
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
        ) : (
          <div className="text-center py-8 text-muted-foreground">
            <Activity className="h-8 w-8 mx-auto mb-2 opacity-50" />
            <p className="text-sm">No execution logs captured</p>
            <p className="text-xs mt-1">Logs are captured during publish, sync, and test operations</p>
          </div>
        )}
      </TabsContent>

      {/* Stack Traces Tab */}
      <TabsContent value="stack" className="space-y-4 m-0">
        {/* PHP Stack Trace */}
        {phpStackFrames.length > 0 && (
          <div>
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 text-orange-500" />
                PHP Stack Trace ({phpStackFrames.length} frames)
              </h4>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  const text = phpStackFrames.map((f, i) => {
                    const fn = f.class ? `${f.class}::${f.function}` : f.function || 'unknown';
                    return `#${i} ${fn}() at ${f.file || f.fileBase || 'unknown'}:${f.line || '?'}`;
                  }).join('\n');
                  copySection("PHP stack trace", text);
                }}
              >
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

        {/* Go Stack Trace */}
        {error.backendStackTrace && (
          <div>
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <Server className="h-4 w-4" />
                Go Stack Trace
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

        {phpStackFrames.length === 0 && !error.backendStackTrace && (
          <div className="text-center py-8 text-muted-foreground">
            <Server className="h-8 w-8 mx-auto mb-2 opacity-50" />
            <p className="text-sm">No backend stack traces available</p>
          </div>
        )}
      </TabsContent>

      {/* Session Tab */}
      {error.sessionId && (
        <TabsContent value="session" className="m-0">
          <SessionLogsTab sessionId={error.sessionId} sessionType={error.sessionType} />
        </TabsContent>
      )}

      {/* Request Tab */}
      <TabsContent value="request" className="space-y-4 m-0">
        <RequestDetails error={error} copySection={copySection} />
      </TabsContent>

      {/* Traversal Tab — Request flow diagnostics */}
      {(error.envelopeErrors || error.envelopeMethodsStack || error.requestedAt) && (
        <TabsContent value="traversal" className="space-y-4 m-0">
          <TraversalDetails error={error} copySection={copySection} />
        </TabsContent>
      )}
    </Tabs>
  );
}

// ============================================================================
// FRONTEND SECTION
// ============================================================================
interface FrontendSectionProps {
  error: CapturedError;
  showRawStack: boolean;
  setShowRawStack: (v: boolean) => void;
  showInternalFrames: boolean;
  setShowInternalFrames: (v: boolean) => void;
  displayFrames: StackFrame[] | undefined;
  suggestedFixes: string[];
  copySection: (label: string, content: string) => void;
  formatTs: (ts: string) => string;
}

function FrontendSection({
  error,
  showRawStack,
  setShowRawStack,
  showInternalFrames,
  setShowInternalFrames,
  displayFrames,
  suggestedFixes,
  copySection,
  formatTs,
}: FrontendSectionProps) {
  return (
    <Tabs defaultValue="overview" className="w-full">
      {/* Horizontally scrollable tabs on mobile */}
      <div className="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
        <TabsList className="mb-4 inline-flex h-auto gap-1 min-w-max sm:flex sm:flex-wrap">
          <TabsTrigger value="overview" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <AlertCircle className="h-3 w-3" />
            Overview
          </TabsTrigger>
          <TabsTrigger value="stack" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <FileCode2 className="h-3 w-3" />
            Stack
          </TabsTrigger>
          <TabsTrigger value="context" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Layers className="h-3 w-3" />
            Context
          </TabsTrigger>
          <TabsTrigger value="fixes" className="gap-1 text-xs sm:text-sm px-2 sm:px-3">
            <Lightbulb className="h-3 w-3" />
            Fixes
          </TabsTrigger>
        </TabsList>
      </div>

      {/* Overview Tab */}
      <TabsContent value="overview" className="space-y-4 m-0">
        {/* Trigger Context */}
        {(error.triggerComponent || error.triggerAction) && (
          <div className="flex items-center gap-2 flex-wrap">
            <Badge variant="outline" className="bg-primary/5 border-primary/20">
              <Layers className="h-3 w-3 mr-1" />
              {error.triggerComponent || "Unknown"}
              {error.triggerAction && (
                <>
                  <ChevronRight className="h-3 w-3 mx-1" />
                  {error.triggerAction}
                </>
              )}
            </Badge>
            {error.context?.source && (
              <Badge variant="secondary" className="font-mono text-xs">
                {String(error.context.source)}
              </Badge>
            )}
          </div>
        )}

        <div>
          <h4 className="text-sm font-medium text-muted-foreground mb-1">Message</h4>
          <p className="text-sm bg-muted p-3 rounded-md">{error.message}</p>
        </div>

        {error.details && (
          <div>
            <h4 className="text-sm font-medium text-muted-foreground mb-1">Details</h4>
            <p className="text-sm bg-muted p-3 rounded-md whitespace-pre-wrap">{error.details}</p>
          </div>
        )}

        {/* Invocation Chain */}
        {error.invocationChain && error.invocationChain.length > 0 && (
          <div>
            <h4 className="text-sm font-medium text-muted-foreground mb-2 flex items-center gap-2">
              <Layers className="h-4 w-4" />
              Call Chain
            </h4>
            <div className="bg-muted p-3 rounded-md">
              <div className="space-y-1">
                {error.invocationChain.map((call, index) => (
                  <div 
                    key={index}
                    className="flex items-center gap-1 text-xs font-mono"
                    style={{ marginLeft: `${index * 12}px` }}
                  >
                    {index > 0 && <span className="text-muted-foreground">└─</span>}
                    <span className={cn(index === 0 ? "text-primary font-semibold" : "text-foreground")}>
                      {call}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* UI Click Path */}
        {error.uiClickPath && error.uiClickPath.length > 0 && (
          <div>
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <MousePointerClick className="h-4 w-4" />
                User Interaction Path ({error.uiClickPath.length} steps)
              </h4>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => copySection("Click path", error.uiClickPathString || '')}
              >
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <div className="bg-muted p-3 rounded-md">
              <div className="space-y-1">
                {error.uiClickPath.slice(-10).map((click, index) => (
                  <div key={click.id} className="flex items-start gap-2 text-xs">
                    <span className="text-muted-foreground font-mono w-4 text-right shrink-0">
                      {index + 1}.
                    </span>
                    <div className="flex-1">
                      <span className={cn("font-medium", index === error.uiClickPath!.length - 1 && "text-primary")}>
                        {click.componentName || click.element}
                      </span>
                      {click.text && (
                        <span className="text-muted-foreground ml-1">
                          "{click.text.slice(0, 25)}{click.text.length > 25 ? '...' : ''}"
                        </span>
                      )}
                      {click.action !== 'click' && (
                        <Badge variant="outline" className="ml-1 text-[10px] px-1 py-0">
                          {click.action}
                        </Badge>
                      )}
                      {click.route && (
                        <span className="text-muted-foreground ml-1 font-mono text-[10px]">
                          @ {click.route}
                        </span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Location */}
        {error.file && (
          <div className="flex items-center gap-2 text-sm">
            <FileCode2 className="h-4 w-4 text-muted-foreground" />
            <code className="bg-muted px-2 py-1 rounded text-xs">
              {error.file}:{error.line}
            </code>
            {error.function && (
              <span className="text-muted-foreground">
                → <code className="bg-muted px-1 rounded text-xs">{error.function}</code>
              </span>
            )}
          </div>
        )}
      </TabsContent>

      {/* Stack Trace Tab */}
      <TabsContent value="stack" className="space-y-4 m-0">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Button variant={showRawStack ? "outline" : "default"} size="sm" onClick={() => setShowRawStack(false)}>
              Parsed
            </Button>
            <Button variant={showRawStack ? "default" : "outline"} size="sm" onClick={() => setShowRawStack(true)}>
              Raw
            </Button>
          </div>
          {!showRawStack && (
            <label className="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer">
              <input
                type="checkbox"
                checked={showInternalFrames}
                onChange={(e) => setShowInternalFrames(e.target.checked)}
                className="rounded"
              />
              Show internal frames
            </label>
          )}
        </div>

        {/* Execution logs (debug mode) */}
        {error.executionLogs && error.executionLogs.length > 0 && (
          <div>
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                <Activity className="h-4 w-4 text-blue-500" />
                React Execution Chain ({error.executionLogs.length} calls)
              </h4>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => copySection("React execution logs", error.executionLogsFormatted || "")}
              >
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <ScrollArea className="h-32 rounded-md border bg-blue-500/5">
              <pre className="text-xs p-3 font-mono whitespace-pre-wrap">
                {error.executionLogsFormatted || "(no logs captured)"}
              </pre>
            </ScrollArea>
          </div>
        )}

        {!error.executionLogs && error.executionLogsEnabled === false && (
          <div className="p-3 rounded-md bg-muted text-xs text-muted-foreground">
            <span className="font-medium">Tip:</span> Enable Debug Mode in settings to capture React execution chains.
          </div>
        )}

        {showRawStack ? (
          error.stackTrace ? (
            <>
              <div className="flex items-center justify-between mb-2">
                <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                  <FileCode2 className="h-4 w-4" />
                  Raw Stack Trace
                </h4>
                <Button variant="ghost" size="sm" onClick={() => copySection("Stack trace", error.stackTrace!)}>
                  <Copy className="h-4 w-4" />
                </Button>
              </div>
              <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto whitespace-pre-wrap font-mono max-h-64">
                {error.stackTrace}
              </pre>
            </>
          ) : (
            <div className="text-center py-8 text-muted-foreground">
              <FileCode2 className="h-8 w-8 mx-auto mb-2 opacity-50" />
              <p className="text-sm">No stack trace available</p>
            </div>
          )
        ) : displayFrames && displayFrames.length > 0 ? (
          <div className="border rounded-md overflow-hidden">
            <table className="w-full text-xs">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-2 font-medium text-muted-foreground">#</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">Function</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">File</th>
                  <th className="text-right p-2 font-medium text-muted-foreground">Line</th>
                </tr>
              </thead>
              <tbody>
                {displayFrames.map((frame, index) => (
                  <tr 
                    key={index} 
                    className={cn(
                      "border-t border-border/50",
                      index === 0 && "bg-primary/5",
                      frame.isInternal && "opacity-50"
                    )}
                  >
                    <td className="p-2 font-mono text-muted-foreground">{index + 1}</td>
                    <td className="p-2 font-mono">
                      <span className={cn(index === 0 && "text-primary font-semibold")}>{frame.function}</span>
                    </td>
                    <td className="p-2 font-mono text-muted-foreground truncate max-w-[200px]">{frame.file}</td>
                    <td className="p-2 font-mono text-right">{frame.line}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="flex justify-end p-2 bg-muted/50 border-t">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  const tableText = displayFrames.map((f, i) => `${i + 1}. ${f.function} (${f.file}:${f.line})`).join('\n');
                  copySection("Stack frames", tableText);
                }}
              >
                <Copy className="h-4 w-4 mr-1" />
                Copy
              </Button>
            </div>
          </div>
        ) : (
          <div className="text-center py-8 text-muted-foreground">
            <FileCode2 className="h-8 w-8 mx-auto mb-2 opacity-50" />
            <p className="text-sm">No parsed stack frames available</p>
            <Button variant="link" size="sm" onClick={() => setShowRawStack(true)} className="mt-2">
              View raw stack trace
            </Button>
          </div>
        )}

        {/* Location Details */}
        {error.file && (
          <div className="pt-3 border-t">
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Error Location</h4>
            <div className="bg-muted p-3 rounded-md space-y-1">
              <p className="text-sm flex items-center gap-2">
                <span className="text-muted-foreground">File:</span>
                <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{error.file}</code>
              </p>
              {error.line && (
                <p className="text-sm flex items-center gap-2">
                  <span className="text-muted-foreground">Line:</span>
                  <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{error.line}</code>
                </p>
              )}
              {error.function && (
                <p className="text-sm flex items-center gap-2">
                  <span className="text-muted-foreground">Function:</span>
                  <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{error.function}</code>
                </p>
              )}
            </div>
          </div>
        )}
      </TabsContent>

      {/* Context Tab */}
      <TabsContent value="context" className="space-y-4 m-0">
        {error.context && Object.keys(error.context).length > 0 ? (
          <div>
            <div className="flex items-center justify-between mb-1">
              <h4 className="text-sm font-medium text-muted-foreground">Full Error Context</h4>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => copySection("Context", JSON.stringify(error.context, null, 2))}
              >
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <ScrollArea className="h-64 rounded-md border bg-muted">
              <div className="p-3">
                <JsonHighlighter json={error.context} />
              </div>
            </ScrollArea>
          </div>
        ) : (
          <div className="text-center py-8 text-muted-foreground">
            No additional context available
          </div>
        )}
      </TabsContent>

      {/* Fixes Tab */}
      <TabsContent value="fixes" className="m-0">
        <div className="space-y-3">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Lightbulb className="h-4 w-4" />
            <span>Suggested fixes for error code <code className="bg-muted px-1 rounded">{error.code}</code></span>
          </div>
          <ul className="space-y-2">
            {suggestedFixes.map((fix, index) => (
              <li key={index} className="flex items-start gap-2">
                <span className="flex-shrink-0 w-5 h-5 rounded-full bg-primary/10 text-primary text-xs flex items-center justify-center">
                  {index + 1}
                </span>
                <span className="text-sm">{fix}</span>
              </li>
            ))}
          </ul>
        </div>
      </TabsContent>
    </Tabs>
  );
}

// ============================================================================
// HELPER COMPONENTS
// ============================================================================

function BackendLogEntry({ log, formatTs }: { log: CapturedError['backendLogs'][0]; formatTs: (ts: string) => string }) {
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

      {(() => {
        const details = log.details as Record<string, unknown> | undefined;
        if (!details || Object.keys(details).length === 0) return null;

        const request = (details.request && typeof details.request === "object") ? (details.request as Record<string, unknown>) : undefined;
        const response = (details.response && typeof details.response === "object") ? (details.response as Record<string, unknown>) : undefined;
        const method = request && typeof request.method === "string" ? request.method : undefined;
        const endpoint = request && typeof request.endpoint === "string" ? request.endpoint : undefined;
        const url = request && typeof request.url === "string" ? request.url : undefined;
        const status = response && typeof response.status === "number" ? response.status : undefined;
        const zipPath = typeof details.zipPath === "string" ? details.zipPath : undefined;
        const remoteSlug = typeof details.remoteSlug === "string" ? details.remoteSlug : undefined;

        if (!method && !endpoint && !url && !zipPath && !remoteSlug && !status) {
          return (
            <pre className="mt-1 ml-4 text-muted-foreground whitespace-pre-wrap break-words">
              {unescapeEmbeddedNewlines(JSON.stringify(details, null, 2))}
            </pre>
          );
        }

        return (
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
        );
      })()}
    </div>
  );
}

function RequestDetails({ error, copySection }: { error: CapturedError; copySection: (label: string, content: string) => void }) {
  const ctx = (error.context || {}) as Record<string, unknown>;
  const requestUrl = typeof ctx.requestUrl === "string" ? ctx.requestUrl : undefined;
  const apiBase = typeof ctx.apiBase === "string" ? ctx.apiBase : undefined;
  const apiBaseAbsolute = typeof ctx.apiBaseAbsolute === "string" ? ctx.apiBaseAbsolute : undefined;
  const rawViteApiUrl = typeof ctx["VITE_API_URL (raw)"] === "string" ? ctx["VITE_API_URL (raw)"] : undefined;
  const rawViteWsUrl = typeof ctx["VITE_WS_URL (raw)"] === "string" ? ctx["VITE_WS_URL (raw)"] : undefined;
  const resolvedApiOrigin = typeof ctx.resolvedApiOrigin === "string" ? ctx.resolvedApiOrigin : undefined;
  const uiOrigin = typeof ctx.uiOrigin === "string" ? ctx.uiOrigin : undefined;

  return (
    <div className="space-y-4">
      {(error.endpoint || error.method) && (
        <div>
          <h4 className="text-sm font-medium text-muted-foreground mb-1 flex items-center gap-2">
            <Globe className="h-4 w-4" />
            API Request
          </h4>
          <div className="bg-muted p-3 rounded-md space-y-2">
            {error.method && error.endpoint && (
              <div className="flex items-center gap-2">
                <Badge variant="outline" className="font-mono">{error.method}</Badge>
                <code className="text-sm">{error.endpoint}</code>
              </div>
            )}
            {error.responseStatus && (
              <p className="text-sm">
                <span className="text-muted-foreground">Status: </span>
                <Badge variant={error.responseStatus >= 400 ? "destructive" : "secondary"}>
                  {error.responseStatus}
                </Badge>
              </p>
            )}

            {(requestUrl || apiBase || rawViteApiUrl) && (
              <div className="pt-2 border-t border-border/60 space-y-1">
                {requestUrl && (
                  <p className="text-sm">
                    <span className="text-muted-foreground">Requested URL: </span>
                    <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{requestUrl}</code>
                  </p>
                )}
                {apiBase && (
                  <p className="text-sm">
                    <span className="text-muted-foreground">API Base: </span>
                    <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{apiBase}</code>
                  </p>
                )}
                {apiBaseAbsolute && (
                  <p className="text-sm">
                    <span className="text-muted-foreground">API Base (absolute): </span>
                    <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{apiBaseAbsolute}</code>
                  </p>
                )}
                {(rawViteApiUrl || rawViteWsUrl || uiOrigin) && (
                  <div className="mt-2 pt-2 border-t border-border/60">
                    <p className="text-xs text-muted-foreground font-medium mb-1">Environment Variables</p>
                    {rawViteApiUrl && (
                      <p className="text-sm">
                        <span className="text-muted-foreground">VITE_API_URL: </span>
                        <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{rawViteApiUrl || "(not set)"}</code>
                      </p>
                    )}
                    {rawViteWsUrl && (
                      <p className="text-sm">
                        <span className="text-muted-foreground">VITE_WS_URL: </span>
                        <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{rawViteWsUrl || "(not set)"}</code>
                      </p>
                    )}
                    {uiOrigin && (
                      <p className="text-sm">
                        <span className="text-muted-foreground">UI Origin: </span>
                        <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{uiOrigin}</code>
                      </p>
                    )}
                  </div>
                )}
                {resolvedApiOrigin && (
                  <p className="text-sm">
                    <span className="text-muted-foreground">Resolved API Origin: </span>
                    <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{resolvedApiOrigin}</code>
                  </p>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {error.requestBody && (
        <div>
          <div className="flex items-center justify-between mb-1">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-1">
              <Network className="h-4 w-4" />
              Request Body
            </h4>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => copySection("Request body", JSON.stringify(error.requestBody, null, 2))}
            >
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto font-mono max-h-40">
            {JSON.stringify(error.requestBody, null, 2)}
          </pre>
        </div>
      )}

      {!error.endpoint && !error.requestBody && (
        <div className="text-center py-8 text-muted-foreground">
          No request information available
        </div>
      )}
    </div>
  );
}

// ============================================================================
// TRAVERSAL DETAILS — Request flow, endpoint tracking, delegated errors
// ============================================================================

function TraversalDetails({ error, copySection }: { error: CapturedError; copySection: (label: string, content: string) => void }) {
  const hasEndpoints = error.requestedAt || error.requestDelegatedAt;
  const hasMethodsStack = error.envelopeMethodsStack?.Backend && error.envelopeMethodsStack.Backend.length > 0;
  const hasDelegatedStack = error.envelopeErrors?.DelegatedServiceErrorStack && error.envelopeErrors.DelegatedServiceErrorStack.length > 0;
  const hasBackendTrace = error.envelopeErrors?.Backend && error.envelopeErrors.Backend.length > 0;

  const copyAll = () => {
    const parts: string[] = [];
    if (error.requestedAt) parts.push(`Requested At: ${error.requestedAt}`);
    if (error.requestDelegatedAt) parts.push(`Delegated At: ${error.requestDelegatedAt}`);
    if (hasMethodsStack) {
      parts.push(`\nMethods Stack:\n${error.envelopeMethodsStack!.Backend.map((f, i) => `  #${i} ${f.Method} at ${f.File}:${f.LineNumber}`).join('\n')}`);
    }
    if (hasDelegatedStack) {
      parts.push(`\nDelegated Service Error Stack:\n${error.envelopeErrors!.DelegatedServiceErrorStack!.map(l => `  ${l}`).join('\n')}`);
    }
    if (hasBackendTrace) {
      parts.push(`\nBackend Trace:\n${error.envelopeErrors!.Backend!.map(l => `  ${l}`).join('\n')}`);
    }
    copySection("Traversal details", parts.join('\n'));
  };

  return (
    <div className="space-y-4">
      {/* Endpoint Tracking */}
      {hasEndpoints && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Route className="h-4 w-4" />
              Endpoint Flow
            </h4>
            <Button variant="ghost" size="sm" onClick={copyAll}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <div className="bg-muted p-3 rounded-md space-y-2">
            {error.requestedAt && (
              <div className="flex items-center gap-2 text-sm">
                <Badge variant="outline" className="shrink-0 text-xs">Go</Badge>
                <code className="text-xs bg-background/60 px-1.5 py-0.5 rounded break-all">{error.requestedAt}</code>
              </div>
            )}
            {error.requestDelegatedAt && (
              <>
                <div className="flex items-center justify-center">
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <Badge variant="outline" className="shrink-0 text-xs bg-orange-500/10 border-orange-500/30 text-orange-600 dark:text-orange-400">PHP</Badge>
                  <code className="text-xs bg-background/60 px-1.5 py-0.5 rounded break-all">{error.requestDelegatedAt}</code>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* Methods Stack (Backend call chain) */}
      {hasMethodsStack && (
        <div>
          <h4 className="text-sm font-medium text-muted-foreground mb-2 flex items-center gap-2">
            <Layers className="h-4 w-4" />
            Methods Stack ({error.envelopeMethodsStack!.Backend.length})
          </h4>
          <div className="border rounded-md overflow-hidden">
            <table className="w-full text-xs">
              <thead className="bg-muted">
                <tr>
                  <th className="text-left p-2 font-medium text-muted-foreground">#</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">Method</th>
                  <th className="text-left p-2 font-medium text-muted-foreground">File</th>
                  <th className="text-right p-2 font-medium text-muted-foreground">Line</th>
                </tr>
              </thead>
              <tbody>
                {error.envelopeMethodsStack!.Backend.map((frame, index) => (
                  <tr key={index} className={cn("border-t border-border/50", index === 0 && "bg-primary/5")}>
                    <td className="p-2 font-mono text-muted-foreground">{index}</td>
                    <td className="p-2 font-mono">
                      <span className={cn(index === 0 && "text-primary font-semibold")}>
                        {frame.Method || 'unknown'}
                      </span>
                    </td>
                    <td className="p-2 font-mono text-muted-foreground truncate max-w-[200px]" title={frame.File}>
                      {frame.File || 'unknown'}
                    </td>
                    <td className="p-2 font-mono text-right">{frame.LineNumber || '?'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Delegated Service Error Stack */}
      {hasDelegatedStack && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-orange-500" />
              <span className="text-orange-600 dark:text-orange-400">Delegated Service Error Stack</span>
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Delegated error stack", error.envelopeErrors!.DelegatedServiceErrorStack!.join('\n'))}>
              <Copy className="h-3 w-3" />
            </Button>
          </div>
          <ScrollArea className="h-[200px] rounded-md border border-orange-500/30 bg-orange-500/5">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">
              {error.envelopeErrors!.DelegatedServiceErrorStack!.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {/* Backend Trace */}
      {hasBackendTrace && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
              <Server className="h-4 w-4" />
              Backend Trace ({error.envelopeErrors!.Backend!.length} lines)
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Backend trace", error.envelopeErrors!.Backend!.join('\n'))}>
              <Copy className="h-3 w-3" />
            </Button>
          </div>
          <ScrollArea className="h-[150px] rounded-md border bg-muted">
            <pre className="text-xs p-3 font-mono whitespace-pre-wrap break-all">
              {error.envelopeErrors!.Backend!.join('\n')}
            </pre>
          </ScrollArea>
        </div>
      )}

      {!hasEndpoints && !hasMethodsStack && !hasDelegatedStack && !hasBackendTrace && (
        <div className="text-center py-8 text-muted-foreground">
          <Route className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No traversal data available</p>
        </div>
      )}
    </div>
  );
}

function DownloadDropdown({ 
  error, 
  appName, 
  appVersion,
  gitCommit,
  buildTime 
}: { 
  error: CapturedError; 
  appName: string; 
  appVersion: string;
  gitCommit?: string;
  buildTime?: string;
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline">
          <Download className="h-4 w-4 mr-2" />
          Download
          <ChevronDown className="h-4 w-4 ml-1" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="bg-popover">
        <DropdownMenuItem onClick={async () => {
          try {
            const report = generateErrorReport(error, { appName, appVersion, gitCommit, buildTime });
            const resp = await fetch("/api/v1/errors/bundle", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ report }),
            });
            if (!resp.ok) throw new Error(`bundle download failed: ${resp.status}`);
            const blob = await resp.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = `error-bundle-${new Date().toISOString().slice(0, 10)}.zip`;
            link.click();
            window.URL.revokeObjectURL(url);
            toast.success("Downloading error bundle...");
          } catch (err) {
            console.error(err);
            toast.error("Failed to download error bundle");
          }
        }}>
          <FileDown className="h-4 w-4 mr-2" />
          Full Bundle (ZIP)
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={async () => {
          try {
            const resp = await api.getBackendErrorLog();
            if (resp.success && resp.data) {
              const blob = new Blob([resp.data.content], { type: "text/plain" });
              const url = window.URL.createObjectURL(blob);
              const link = document.createElement("a");
              link.href = url;
              link.download = "error.log.txt";
              link.click();
              window.URL.revokeObjectURL(url);
              toast.success("Downloaded error.log.txt");
            } else {
              toast.error(resp.error?.message || "No error log found");
            }
          } catch (err) {
            toast.error("Failed to download error log");
          }
        }}>
          <FileText className="h-4 w-4 mr-2" />
          error.log.txt
        </DropdownMenuItem>
        <DropdownMenuItem onClick={async () => {
          try {
            const resp = await api.getBackendFullLog();
            if (resp.success && resp.data) {
              const blob = new Blob([resp.data.content], { type: "text/plain" });
              const url = window.URL.createObjectURL(blob);
              const link = document.createElement("a");
              link.href = url;
              link.download = "log.txt";
              link.click();
              window.URL.revokeObjectURL(url);
              toast.success("Downloaded log.txt");
            } else {
              toast.error(resp.error?.message || "No full log found");
            }
          } catch (err) {
            toast.error("Failed to download log file");
          }
        }}>
          <Terminal className="h-4 w-4 mr-2" />
          log.txt (Full)
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={() => {
          const report = generateErrorReport(error, { appName, appVersion, gitCommit, buildTime });
          const blob = new Blob([report], { type: "text/markdown" });
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement("a");
          link.href = url;
          link.download = `error-report-${new Date().toISOString().slice(0, 10)}.md`;
          link.click();
          window.URL.revokeObjectURL(url);
          toast.success("Downloaded report as Markdown");
        }}>
          <FileCode2 className="h-4 w-4 mr-2" />
          Report (.md)
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function CopyDropdown({ 
  error, 
  appName, 
  appVersion,
  gitCommit,
  buildTime,
  copyFullError 
}: { 
  error: CapturedError; 
  appName: string; 
  appVersion: string;
  gitCommit?: string;
  buildTime?: string;
  copyFullError: () => void;
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button>
          <Copy className="h-4 w-4 mr-2" />
          Copy
          <ChevronDown className="h-4 w-4 ml-1" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="bg-popover">
        <DropdownMenuItem onClick={copyFullError}>
          <Copy className="h-4 w-4 mr-2" />
          Copy Full Report
        </DropdownMenuItem>
        <DropdownMenuItem onClick={async () => {
          try {
            const resp = await api.getBackendErrorLog();
            if (resp.success && resp.data) {
              const report = generateErrorReport(error, { appName, appVersion, gitCommit, buildTime });
              const fullReport = `${report}\n\n---\n\n## Backend Error Log (error.log.txt)\n\n\`\`\`\n${resp.data.content}\n\`\`\`\n`;
              navigator.clipboard.writeText(toClipboardText(fullReport));
              toast.success("Copied report with backend logs");
            } else {
              copyFullError();
              toast.info("Backend logs not available, copied standard report");
            }
          } catch (err) {
            copyFullError();
          }
        }}>
          <Server className="h-4 w-4 mr-2" />
          Copy with Backend Logs
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={async () => {
          try {
            const resp = await api.getBackendErrorLog();
            if (resp.success && resp.data) {
              navigator.clipboard.writeText(toClipboardText(resp.data.content));
              toast.success("Copied error.log.txt to clipboard");
            } else {
              toast.error(resp.error?.message || "No error log available");
            }
          } catch (err) {
            toast.error("Failed to copy error log");
          }
        }}>
          <Terminal className="h-4 w-4 mr-2" />
          Copy error.log.txt
        </DropdownMenuItem>
        <DropdownMenuItem onClick={async () => {
          try {
            const resp = await api.getBackendFullLog();
            if (resp.success && resp.data) {
              navigator.clipboard.writeText(toClipboardText(resp.data.content));
              toast.success("Copied log.txt to clipboard");
            } else {
              toast.error(resp.error?.message || "No full log available");
            }
          } catch (err) {
            toast.error("Failed to copy full log");
          }
        }}>
          <FileText className="h-4 w-4 mr-2" />
          Copy log.txt
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function generateErrorReport(
  error: CapturedError,
  app?: { appName: string; appVersion: string; gitCommit?: string; buildTime?: string }
): string {
  const appInfo = [
    `**App:** ${app?.appName || "WP Plugin Publish"} v${app?.appVersion || "0.0.0"}`,
  ];
  if (app?.gitCommit) {
    appInfo.push(`**Git Commit:** ${app.gitCommit.substring(0, 7)}`);
  }
  if (app?.buildTime) {
    appInfo.push(`**Build Time:** ${app.buildTime}`);
  }

  const triggerContext = [];
  if (error.triggerComponent) {
    triggerContext.push(`**Component:** ${error.triggerComponent}`);
  }
  if (error.triggerAction) {
    triggerContext.push(`**Action:** ${error.triggerAction}`);
  }
  if (error.context?.source) {
    triggerContext.push(`**Source:** ${error.context.source}`);
  }
  const triggerSection = triggerContext.length > 0 
    ? `### Trigger Context\n${triggerContext.join("\n")}\n` 
    : "";

  const chainSection = error.invocationChain && error.invocationChain.length > 0
    ? `### Invocation Chain\n\`\`\`\n${error.invocationChain.map((call, i) => 
        `${'  '.repeat(i)}${i > 0 ? '└─ ' : ''}${call}`
      ).join('\n')}\n\`\`\`\n`
    : "";

  const framesSection = error.parsedFrames && error.parsedFrames.length > 0
    ? `### Parsed Stack Frames\n| # | Function | File | Line |\n|---|----------|------|------|\n${
        error.parsedFrames.slice(0, 10).map((f, i) => 
          `| ${i + 1} | ${f.function} | ${f.file} | ${f.line} |`
        ).join('\n')
      }\n`
    : "";

  const backendLogsSection = error.backendLogs && error.backendLogs.length > 0
    ? `### Backend Execution Logs\n\`\`\`\n${
        error.backendLogs.map(l => {
          const base = `[${formatDateTimeUtc(l.timestamp)}] [${l.level.toUpperCase()}]${l.step ? ` [${l.step}]` : ''} ${unescapeEmbeddedNewlines(l.message)}`;
          if (l.details && Object.keys(l.details).length > 0) {
            return `${base}\n${unescapeEmbeddedNewlines(JSON.stringify(l.details, null, 2))}`;
          }
          return base;
        }).join('\n\n')
      }\n\`\`\`\n`
    : "";

  const backendStackSection = error.backendStackTrace
    ? `### Backend Stack Trace (Go)\n\`\`\`\n${error.backendStackTrace}\n\`\`\`\n`
    : "";

  // PHP stack frames from WordPress/PHP call stack
  const phpStackFramesSection = error.phpStackFrames && error.phpStackFrames.length > 0
    ? `### PHP Stack Trace\n| # | Function | File | Line |\n|---|----------|------|------|\n${
        error.phpStackFrames.map((f: { class?: string; function?: string; file?: string; fileBase?: string; line?: number }, i: number) => {
          const fn = f.class ? `${f.class}::${f.function}` : f.function || 'unknown';
          return `| ${i} | ${fn}() | ${f.fileBase || f.file || 'unknown'} | ${f.line || '?'} |`;
        }).join('\n')
      }\n`
    : "";

  // User interaction click path
  const uiClickPathSection = error.uiClickPathString
    ? `### User Interaction Path\n\`\`\`\n${error.uiClickPathString}\n\`\`\`\n`
    : "";

  // Frontend React execution chain
  const executionChainSection = error.executionLogsFormatted
    ? `### Frontend Execution Chain\n\`\`\`\n${error.executionLogsFormatted}\n\`\`\`\n`
    : "";

  const siteUrlSection = error.siteUrl
    ? `### Target Site\n${error.siteUrl}\n`
    : "";

  const sessionSection = error.sessionId
    ? `### Session Info\n**Session ID:** ${error.sessionId}\n${error.sessionType ? `**Type:** ${error.sessionType}\n` : ""}*Fetch full logs via: GET /api/v1/sessions/${error.sessionId}/logs*\n`
    : "";

  return `## Error Report

${appInfo.join("\n")}

**ID:** ${error.id}
**Code:** ${error.code}
**Level:** ${error.level}
**Timestamp:** ${error.createdAt}

${triggerSection}
${chainSection}
${siteUrlSection}
${sessionSection}
### Message
${error.message}

${error.details ? `### Details\n${error.details}\n` : ""}
${error.endpoint ? `### Request\n**${error.method || "GET"}** ${error.endpoint}\n${error.responseStatus ? `**Status:** ${error.responseStatus}\n` : ""}` : ""}
${error.requestBody ? `### Request Body\n\`\`\`json\n${JSON.stringify(error.requestBody, null, 2)}\n\`\`\`\n` : ""}
${backendLogsSection}
${backendStackSection}
${phpStackFramesSection}
${uiClickPathSection}
${executionChainSection}
${framesSection}
${error.file ? `### Location\n\`${error.file}:${error.line}\` (${error.function})\n` : ""}
${error.context && Object.keys(error.context).length > 0 ? `### Context\n\`\`\`json\n${JSON.stringify(error.context, null, 2)}\n\`\`\`\n` : ""}
${error.stackTrace ? `### Frontend Stack Trace\n\`\`\`\n${error.stackTrace}\n\`\`\`` : ""}

---
*Generated by WP Plugin Publish Error Reporter*
`;
}

function getSuggestedFixes(code: string): string[] {
  const fixes: Record<string, string[]> = {
    E1001: [
      "Check that the backend server is running on the correct port",
      "Verify VITE_API_URL environment variable is correctly set",
      "Ensure no firewall is blocking the connection",
      "Try refreshing the page",
    ],
    E2001: [
      "Check site credentials (username and application password)",
      "Verify the WordPress site is accessible",
      "Ensure REST API is enabled on the WordPress site",
      "Check if Riseup Asia Uploader plugin is installed and activated",
    ],
    E2002: [
      "The remote site returned an unexpected response format",
      "Check if the WordPress site has any caching plugins that might interfere",
      "Verify the Riseup Asia Uploader plugin version is compatible",
    ],
    E3001: [
      "Check if the plugin files exist in the local directory",
      "Verify file permissions allow reading the plugin folder",
      "Ensure the plugin has a valid main PHP file with headers",
    ],
    E4001: [
      "Check available disk space on the WordPress server",
      "Verify PHP upload limits (upload_max_filesize, post_max_size)",
      "Try uploading a smaller plugin first to test",
    ],
    E5001: [
      "Check that the plugin has no fatal errors in its code",
      "Verify plugin dependencies are met",
      "Check WordPress debug.log for activation errors",
      "Try activating the plugin manually in WordPress admin",
    ],
    E9005: [
      "The API returned HTML instead of JSON - this usually means a routing issue",
      "Check if the backend server is running",
      "Verify VITE_API_URL points to the correct backend URL",
      "Look at the browser network tab for the actual response",
    ],
  };

  return fixes[code] || [
    "Check the error details for more context",
    "Review the stack trace for the error source",
    "Check backend logs for additional information",
    "Try the operation again - it may be a temporary issue",
  ];
}
