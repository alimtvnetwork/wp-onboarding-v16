import { useState, useEffect } from "react";
import { useErrorStore } from '@/stores/errorStore';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { AlertCircle, ChevronRight, ChevronLeft, CopyPlus, Server, Monitor, Globe, GripHorizontal, RotateCcw } from "lucide-react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import { formatDateTimeUtc, toClipboardText } from "@/lib/logText";
import { api } from "@/lib/api";
import type { PHPStackFrame } from "./ErrorModalTypes";
import { generateErrorReport, getSuggestedFixes } from "./errorReportGenerator";
import { BackendSection } from "./BackendSection";
import { FrontendSection } from "./FrontendSection";
import { DelegatedSection, hasDelegatedContent } from "./DelegatedSection";
import { DownloadDropdown, CopyDropdown } from "./ErrorModalActions";
import { useDraggable } from "@/hooks/useDraggable";

/**
 * Parse PHP stack trace frames from a raw remoteResponseBody string.
 * The body is typically a JSON string from WordPress containing error data
 * with stack traces like: #0 /path/file.php(42): Class->method()
 */
function parsePhpStackFromRemoteBody(raw: string): PHPStackFrame[] {
  try {
    // Try to extract the stack trace text — it may be in data.error.message or raw
    let traceText = raw;
    try {
      const parsed = JSON.parse(raw);
      const errMsg = parsed?.data?.error?.message;
      if (typeof errMsg === 'string') traceText = errMsg;
    } catch { /* use raw */ }

    // Match PHP stack trace lines: #N /path/to/file.php(line): Class->method() or function()
    const frameRegex = /#(\d+)\s+([^\s(]+?)(?:\((\d+)\))?:\s*(.+)/g;
    const frames: PHPStackFrame[] = [];
    let match: RegExpExecArray | null;

    while ((match = frameRegex.exec(traceText)) !== null) {
      const filePath = match[2];
      const line = match[3] ? parseInt(match[3], 10) : undefined;
      const call = match[4]?.trim() || 'unknown';

      // Split class::method or class->method
      let cls: string | undefined;
      let fn: string = call;
      const classMatch = call.match(/^(.+?)(?:::|->\s*)(.+?)(\(.*\))?$/);
      if (classMatch) {
        cls = classMatch[1];
        fn = classMatch[2].replace(/\(\)$/, '');
      } else {
        fn = call.replace(/\(\)$/, '').replace(/\(.*\)$/, '');
      }

      frames.push({
        file: filePath,
        fileBase: filePath.split('/').pop(),
        line,
        function: fn,
        class: cls,
      });
    }

    return frames;
  } catch {
    return [];
  }
}

export function GlobalErrorModal() {
  const { selectedError, isModalOpen, closeErrorModal, errorQueue, currentQueueIndex, navigateQueue, getQueuedErrorsMarkdown } = useErrorStore();
  const { style: dragStyle, onMouseDown: onDragMouseDown, onTouchStart: onDragTouchStart, resetPosition, isDragged } = useDraggable();
  const { data: versionInfo } = useVersionInfo();
  const appName = versionInfo?.appName || "WP Plugin Publish";
  const appVersion = versionInfo?.version || "0.0.0";
  const gitCommit = versionInfo?.gitCommit;
  const buildTime = versionInfo?.buildTime;
  
  const [activeSection, setActiveSection] = useState<"backend" | "frontend" | "delegated">("backend");
  const [showRawStack, setShowRawStack] = useState(false);
  const [showInternalFrames, setShowInternalFrames] = useState(false);
  
  // Backend error log state
  const [errorLogContent, setErrorLogContent] = useState<string | null>(null);
  const [errorLogLoading, setErrorLogLoading] = useState(false);
  const [errorLogError, setErrorLogError] = useState<string | null>(null);
  const [errorLogFetched, setErrorLogFetched] = useState(false);
  
  // Extract PHP stack trace frames from error context
  const phpStackFrames: PHPStackFrame[] = (() => {
    const ctx = selectedError?.context;
    if (!ctx) return [];
    // 1. Pre-parsed frames from backend
    if (Array.isArray(ctx.stackTraceFrames)) return ctx.stackTraceFrames as PHPStackFrame[];
    const errorDetails = ctx.errorDetails;
    if (errorDetails && Array.isArray(errorDetails.stackTraceFrames)) return errorDetails.stackTraceFrames as PHPStackFrame[];
    // 2. Parse from remoteResponseBody (raw PHP error JSON embedded in delegated response)
    const rawBody = ctx.remoteResponseBody;
    if (typeof rawBody === 'string' && rawBody.length > 0) {
      return parsePhpStackFromRemoteBody(rawBody);
    }
    return [];
  })();
  
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
    } catch (err: unknown) {
      setErrorLogError(err instanceof Error ? err.message : "Failed to fetch error log");
    } finally {
      setErrorLogLoading(false);
      setErrorLogFetched(true);
    }
  };
  
  useEffect(() => {
    if (isModalOpen && activeSection === "backend" && !errorLogFetched) {
      fetchErrorLog();
    }
  }, [isModalOpen, activeSection, errorLogFetched]);
  
  useEffect(() => {
    if (!isModalOpen) {
      setErrorLogContent(null);
      setErrorLogFetched(false);
      setErrorLogError(null);
      setActiveSection("backend");
      resetPosition();
    }
  }, [isModalOpen, selectedError?.id, resetPosition]);
  
  if (!selectedError) return null;

  const copyFullError = async () => {
    let text = generateErrorReport(selectedError, { appName, appVersion, gitCommit, buildTime });
    try {
      const resp = await api.getBackendErrorLog();
      if (resp.success && resp.data?.content) {
        text += `\n### Backend error.log.txt\n\`\`\`\n${resp.data.content}\n\`\`\`\n`;
      }
    } catch { /* skip */ }
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

  // Determine error source classification
  const hasDelegatedData = !!(
    selectedError.requestDelegatedAt
    || selectedError.envelopeErrors?.DelegatedRequestServer
    || selectedError.envelopeErrors?.DelegatedServiceErrorStack?.length
    || selectedError.phpStackFrames?.length
    || (typeof selectedError.context?.remoteResponseBody === 'string' && selectedError.context.remoteResponseBody.length > 0)
    || phpStackFrames.length > 0
  );
  const hasFrontendOnly = !selectedError.endpoint && !selectedError.envelopeErrors && selectedError.parsedFrames && selectedError.parsedFrames.length > 0;
  const errorSource = hasDelegatedData
    ? { label: "Delegated Remote", icon: Globe, className: "border-orange-500/40 bg-orange-500/10 text-orange-600 dark:text-orange-400" }
    : hasFrontendOnly
      ? { label: "Frontend", icon: Monitor, className: "border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400" }
      : { label: "Local Backend", icon: Server, className: "border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400" };

  return (
    <Dialog open={isModalOpen} onOpenChange={closeErrorModal}>
      <DialogContent
        data-error-modal
        style={dragStyle}
        className={cn(
          "flex flex-col p-0 gap-0 overflow-hidden",
          "w-full h-full max-w-full max-h-full rounded-none",
          "sm:max-w-[95vw] sm:w-[95vw] sm:max-h-[95vh] sm:h-[95vh] sm:rounded-lg",
          "lg:max-w-6xl"
        )}
      >
        {/* Header — draggable */}
        <DialogHeader
          className="px-4 py-3 sm:px-6 sm:py-4 border-b shrink-0 cursor-grab active:cursor-grabbing select-none"
          onMouseDown={onDragMouseDown}
        >
          <div className="flex items-center justify-between gap-2 sm:gap-3">
            <div className="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
              <GripHorizontal className="h-4 w-4 text-muted-foreground/50 shrink-0 hidden sm:block" />
              <AlertCircle className={cn(
                "h-5 w-5 sm:h-6 sm:w-6 shrink-0",
                selectedError.level === "error" ? "text-destructive"
                  : selectedError.level === "warn" ? "text-warning" : "text-muted-foreground"
              )} />
              <div className="min-w-0 flex-1">
                <DialogTitle className="flex items-center gap-2 flex-wrap text-base sm:text-lg">
                  <span className="hidden sm:inline">Error Details</span>
                  <span className="sm:hidden">Error</span>
                  <Badge variant="secondary" className={cn("text-xs", levelColors[selectedError.level] || "")}>
                    {selectedError.code}
                  </Badge>
                  <Badge variant="outline" className={cn("text-xs gap-1 font-medium", errorSource.className)}>
                    <errorSource.icon className="h-3 w-3" />
                    {errorSource.label}
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
            
            <div className="flex items-center gap-1 shrink-0">
              {isDragged && (
                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={resetPosition} title="Reset position">
                  <RotateCcw className="h-3 w-3" />
                </Button>
              )}
              {hasMultipleErrors && (
                <>
                  <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateQueue('prev')} title="Previous error">
                    <ChevronLeft className="h-4 w-4" />
                  </Button>
                  <Badge variant="secondary" className="px-2 py-1 font-mono text-xs">
                    {currentQueueIndex + 1}/{errorQueue.length}
                  </Badge>
                  <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateQueue('next')} title="Next error">
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                  <Button variant="outline" size="icon" className="h-7 w-7 sm:hidden" onClick={copyAllErrors} title="Copy all errors">
                    <CopyPlus className="h-3 w-3" />
                  </Button>
                  <Button variant="outline" size="sm" className="h-7 ml-1 hidden sm:flex" onClick={copyAllErrors} title="Copy all errors">
                    <CopyPlus className="h-3 w-3 mr-1" />
                    All
                  </Button>
                </>
              )}
            </div>
          </div>
        </DialogHeader>

        {/* Main Content */}
        <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
          <div className="px-4 pt-3 pb-2 sm:px-6 sm:pt-4 border-b bg-muted/30 shrink-0">
            <div className="flex items-center gap-2">
              <Button variant={activeSection === "backend" ? "default" : "outline"} size="sm"
                onClick={() => setActiveSection("backend")} className="gap-1.5 sm:gap-2 text-xs sm:text-sm flex-1 sm:flex-none">
                <Server className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                Backend
              </Button>
              <Button variant={activeSection === "frontend" ? "default" : "outline"} size="sm"
                onClick={() => setActiveSection("frontend")} className="gap-1.5 sm:gap-2 text-xs sm:text-sm flex-1 sm:flex-none">
                <Monitor className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                Frontend
              </Button>
              <Button variant={activeSection === "frontend" ? "default" : "outline"} size="sm"
                onClick={() => setActiveSection("frontend")} className="gap-1.5 sm:gap-2 text-xs sm:text-sm flex-1 sm:flex-none">
                <Monitor className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                Frontend
              </Button>
              <Button variant={activeSection === "delegated" ? "default" : "outline"} size="sm"
                onClick={() => setActiveSection("delegated")} className="gap-1.5 sm:gap-2 text-xs sm:text-sm flex-1 sm:flex-none border-orange-500/30">
                <Globe className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-orange-500" />
                <span className="hidden sm:inline">Delegated Logs</span>
                <span className="sm:hidden">Delegated</span>
              </Button>
            </div>
          </div>

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
                  onRefreshLog={() => { setErrorLogFetched(false); setErrorLogError(null); fetchErrorLog(); }}
                  copySection={copySection}
                  formatTs={formatTs}
                />
              ) : activeSection === "delegated" ? (
                <DelegatedSection
                  error={selectedError}
                  phpStackFrames={phpStackFrames}
                  copySection={copySection}
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

        {/* Footer */}
        <div className="flex flex-wrap justify-end gap-2 px-4 py-3 sm:px-6 sm:py-4 border-t shrink-0 bg-background">
          <div className="flex gap-2 w-full sm:w-auto justify-end">
            <DownloadDropdown error={selectedError} appName={appName} appVersion={appVersion} gitCommit={gitCommit} buildTime={buildTime} />
            <Button variant="outline" onClick={closeErrorModal} className="text-xs sm:text-sm">Close</Button>
            <CopyDropdown error={selectedError} appName={appName} appVersion={appVersion} gitCommit={gitCommit} buildTime={buildTime} copyFullError={copyFullError} />
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
