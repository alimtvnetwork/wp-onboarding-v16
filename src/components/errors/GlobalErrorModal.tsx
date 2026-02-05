import { useState } from "react";
import { useErrorStore, CapturedError, StackFrame } from '@/stores/errorStore';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Copy, ExternalLink, AlertCircle, FileCode2, Network, Lightbulb, Globe, ChevronRight, Layers, Server, Terminal, Download, Activity, FileText } from "lucide-react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import { JsonHighlighter } from "@/components/shared/JsonHighlighter";
import { SessionLogsTab } from "@/components/errors/SessionLogsTab";
import { formatDateTimeUtc, toClipboardText, unescapeEmbeddedNewlines } from "@/lib/logText";

export function GlobalErrorModal() {
  const { selectedError, isModalOpen, closeErrorModal } = useErrorStore();
  const { data: versionInfo } = useVersionInfo();
  const appName = versionInfo?.appName || "WP Plugin Publish";
  const appVersion = versionInfo?.version || "0.0.0";
  const gitCommit = versionInfo?.gitCommit;
  const buildTime = versionInfo?.buildTime;
  const [showRawStack, setShowRawStack] = useState(false);
  const [showInternalFrames, setShowInternalFrames] = useState(false);
  
  if (!selectedError) return null;

  const copyFullError = () => {
    const text = generateErrorReport(selectedError, { appName, appVersion, gitCommit, buildTime });
    navigator.clipboard.writeText(toClipboardText(text));
    toast.success("Full error report copied to clipboard");
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
  
  // Get frames to display (filter internal if needed)
  const displayFrames = showInternalFrames 
    ? selectedError.parsedFrames 
    : selectedError.parsedFrames?.filter(f => !f.isInternal);

  return (
    <Dialog open={isModalOpen} onOpenChange={closeErrorModal}>
      <DialogContent className="max-w-3xl max-h-[90vh] flex flex-col">
        <DialogHeader>
          <div className="flex items-center gap-3">
            <AlertCircle className={cn(
              "h-6 w-6",
              selectedError.level === "error"
                ? "text-destructive"
                : selectedError.level === "warn"
                  ? "text-warning"
                  : "text-muted-foreground"
            )} />
            <div>
              <DialogTitle className="flex items-center gap-2">
                Error Details
                <Badge 
                  variant="secondary" 
                  className={levelColors[selectedError.level] || ""}
                >
                  {selectedError.code}
                </Badge>
              </DialogTitle>
              <DialogDescription>
                <span>
                  {formatTs(selectedError.createdAt)}
                </span>
                <span className="mx-2">•</span>
                <span className="font-mono">
                  {appName} v{appVersion}
                </span>
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="flex-1 overflow-hidden">
          <Tabs defaultValue="overview" className="h-full flex flex-col">
            <TabsList className="grid w-full grid-cols-7">
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="session" disabled={!selectedError.sessionId}>
                <FileText className="h-3 w-3 mr-1" />
                Session
              </TabsTrigger>
              <TabsTrigger value="backend" disabled={!selectedError.backendLogs?.length && !selectedError.backendStackTrace}>
                <Server className="h-3 w-3 mr-1" />
                Backend
              </TabsTrigger>
              <TabsTrigger value="request">Request</TabsTrigger>
              <TabsTrigger value="stack">Stack</TabsTrigger>
              <TabsTrigger value="context">Context</TabsTrigger>
              <TabsTrigger value="fixes">Fixes</TabsTrigger>
            </TabsList>

            <ScrollArea className="flex-1 mt-4 pr-4">
              {/* Overview Tab - Compact summary with Call Chain */}
              <TabsContent value="overview" className="space-y-3 m-0">
                {/* Trigger Context Badge */}
                {(selectedError.triggerComponent || selectedError.triggerAction) && (
                  <div className="flex items-center gap-2 flex-wrap">
                    <Badge variant="outline" className="bg-primary/5 border-primary/20">
                      <Layers className="h-3 w-3 mr-1" />
                      {selectedError.triggerComponent || "Unknown"}
                      {selectedError.triggerAction && (
                        <>
                          <ChevronRight className="h-3 w-3 mx-1" />
                          {selectedError.triggerAction}
                        </>
                      )}
                    </Badge>
                    {selectedError.context?.source && (
                      <Badge variant="secondary" className="font-mono text-xs">
                        {String(selectedError.context.source)}
                      </Badge>
                    )}
                  </div>
                )}

                <div>
                  <h4 className="text-sm font-medium text-muted-foreground mb-1">Message</h4>
                  <p className="text-sm bg-muted p-3 rounded-md">{selectedError.message}</p>
                </div>

                {selectedError.details && (
                  <div>
                    <h4 className="text-sm font-medium text-muted-foreground mb-1">Details</h4>
                    <p className="text-sm bg-muted p-3 rounded-md whitespace-pre-wrap">
                      {selectedError.details}
                    </p>
                  </div>
                )}

                {/* Invocation Chain - Visual Tree */}
                {selectedError.invocationChain && selectedError.invocationChain.length > 0 && (
                  <div>
                    <h4 className="text-sm font-medium text-muted-foreground mb-2 flex items-center gap-2">
                      <Layers className="h-4 w-4" />
                      Call Chain
                    </h4>
                    <div className="bg-muted p-3 rounded-md">
                      <div className="space-y-1">
                        {selectedError.invocationChain.map((call, index) => (
                          <div 
                            key={index}
                            className="flex items-center gap-1 text-xs font-mono"
                            style={{ marginLeft: `${index * 12}px` }}
                          >
                            {index > 0 && (
                              <span className="text-muted-foreground">└─</span>
                            )}
                            <span className={cn(
                              index === 0 ? "text-primary font-semibold" : "text-foreground"
                            )}>
                              {call}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                )}

                {/* Quick location summary */}
                {selectedError.file && (
                  <div className="flex items-center gap-2 text-sm">
                    <FileCode2 className="h-4 w-4 text-muted-foreground" />
                    <code className="bg-muted px-2 py-1 rounded text-xs">
                      {selectedError.file}:{selectedError.line}
                    </code>
                    {selectedError.function && (
                      <span className="text-muted-foreground">
                        → <code className="bg-muted px-1 rounded text-xs">{selectedError.function}</code>
                      </span>
                    )}
                  </div>
                )}
              </TabsContent>

              {/* Session Logs Tab - Fetch from backend */}
              <TabsContent value="session" className="m-0">
                <SessionLogsTab 
                  sessionId={selectedError.sessionId}
                  sessionType={selectedError.sessionType}
                />
              </TabsContent>

              {/* Stack Trace Tab - Enhanced with Parsed Table */}
              <TabsContent value="stack" className="m-0 space-y-4">
                {/* View Toggle */}
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Button
                      variant={showRawStack ? "outline" : "default"}
                      size="sm"
                      onClick={() => setShowRawStack(false)}
                    >
                      Parsed
                    </Button>
                    <Button
                      variant={showRawStack ? "default" : "outline"}
                      size="sm"
                      onClick={() => setShowRawStack(true)}
                    >
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

                {showRawStack ? (
                  // Raw Stack Trace View
                  <>
                    {selectedError.stackTrace ? (
                      <>
                        {(() => {
                          const stack = selectedError.stackTrace || "";
                          const isBackendStack = stack.includes("goroutine") || stack.includes(".go:");
                          const isFrontendStack = stack.includes("at ") || stack.includes(".tsx:") || stack.includes(".ts:");
                          
                          return (
                            <Tabs defaultValue={isBackendStack ? "backend" : "frontend"} className="w-full">
                              <TabsList className="grid w-full grid-cols-2 mb-3">
                                <TabsTrigger value="frontend" disabled={!isFrontendStack && isBackendStack}>
                                  Frontend
                                </TabsTrigger>
                                <TabsTrigger value="backend" disabled={!isBackendStack && isFrontendStack}>
                                  Backend
                                </TabsTrigger>
                              </TabsList>
                              
                              <TabsContent value="frontend" className="m-0">
                                <div className="flex items-center justify-between mb-2">
                                  <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                                    <FileCode2 className="h-4 w-4" />
                                    Frontend Stack Trace
                                  </h4>
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => copySection("Frontend stack trace", stack)}
                                  >
                                    <Copy className="h-4 w-4" />
                                  </Button>
                                </div>
                                <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto whitespace-pre-wrap font-mono max-h-60">
                                  {isFrontendStack ? stack : "No frontend stack trace available"}
                                </pre>
                              </TabsContent>
                              
                              <TabsContent value="backend" className="m-0">
                                <div className="flex items-center justify-between mb-2">
                                  <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                                    <Network className="h-4 w-4" />
                                    Backend Stack Trace
                                  </h4>
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => copySection("Backend stack trace", stack)}
                                  >
                                    <Copy className="h-4 w-4" />
                                  </Button>
                                </div>
                                <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto whitespace-pre-wrap font-mono max-h-60">
                                  {isBackendStack ? stack : "No backend stack trace available"}
                                </pre>
                              </TabsContent>
                            </Tabs>
                          );
                        })()}
                      </>
                    ) : (
                      <div className="text-center py-8 text-muted-foreground">
                        <FileCode2 className="h-8 w-8 mx-auto mb-2 opacity-50" />
                        <p className="text-sm">No stack trace available</p>
                      </div>
                    )}
                  </>
                ) : (
                  // Parsed Stack Frames Table View
                  <>
                    {displayFrames && displayFrames.length > 0 ? (
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
                                  <span className={cn(index === 0 && "text-primary font-semibold")}>
                                    {frame.function}
                                  </span>
                                </td>
                                <td className="p-2 font-mono text-muted-foreground truncate max-w-[200px]">
                                  {frame.file}
                                </td>
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
                              const tableText = displayFrames
                                .map((f, i) => `${i + 1}. ${f.function} (${f.file}:${f.line})`)
                                .join('\n');
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
                        <Button
                          variant="link"
                          size="sm"
                          onClick={() => setShowRawStack(true)}
                          className="mt-2"
                        >
                          View raw stack trace
                        </Button>
                      </div>
                    )}
                  </>
                )}

                {/* Location Details */}
                {selectedError.file && (
                  <div className="pt-3 border-t">
                    <h4 className="text-sm font-medium text-muted-foreground mb-2">Error Location</h4>
                    <div className="bg-muted p-3 rounded-md space-y-1">
                      <p className="text-sm flex items-center gap-2">
                        <span className="text-muted-foreground">File:</span>
                        <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{selectedError.file}</code>
                      </p>
                      {selectedError.line && (
                        <p className="text-sm flex items-center gap-2">
                          <span className="text-muted-foreground">Line:</span>
                          <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{selectedError.line}</code>
                        </p>
                      )}
                      {selectedError.function && (
                        <p className="text-sm flex items-center gap-2">
                          <span className="text-muted-foreground">Function:</span>
                          <code className="text-xs bg-background/60 px-1 py-0.5 rounded">{selectedError.function}</code>
                        </p>
                      )}
                    </div>
                  </div>
                )}
              </TabsContent>

              {/* Backend Logs & Stack Tab */}
              <TabsContent value="backend" className="m-0 space-y-4">
                {/* Site URL if available */}
                {selectedError.siteUrl && (
                  <div className="flex items-center gap-2 p-3 bg-muted rounded-md">
                    <Globe className="h-4 w-4 text-muted-foreground" />
                    <span className="text-sm text-muted-foreground">Target Site:</span>
                    <a 
                      href={selectedError.siteUrl} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="text-sm text-primary hover:underline flex items-center gap-1"
                    >
                      {selectedError.siteUrl}
                      <ExternalLink className="h-3 w-3" />
                    </a>
                  </div>
                )}

                {/* Backend Execution Logs */}
                {selectedError.backendLogs && selectedError.backendLogs.length > 0 && (
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                        <Terminal className="h-4 w-4" />
                        Execution Logs ({selectedError.backendLogs.length} entries)
                      </h4>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          const logText = selectedError.backendLogs!
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
                    <ScrollArea className="h-48 rounded-md border">
                      <div className="p-3 space-y-1">
                        {selectedError.backendLogs.map((log, idx) => (
                          <div 
                            key={idx}
                            className={cn(
                              "text-xs font-mono py-1 px-2 rounded",
                              log.level === 'error' && "bg-destructive/10 text-destructive",
                              log.level === 'warn' && "bg-warning/10 text-warning",
                              log.level === 'info' && "bg-primary/10 text-primary",
                              log.level === 'debug' && "bg-muted text-muted-foreground"
                            )}
                          >
                            <span className="text-muted-foreground">[{formatTs(log.timestamp)}]</span>
                            {log.step && <span className="text-primary ml-1">[{log.step}]</span>}
                            <span className="ml-1 whitespace-pre-wrap break-words">{unescapeEmbeddedNewlines(log.message)}</span>

                            {(() => {
                              const details = log.details as Record<string, unknown> | undefined;
                              if (!details || Object.keys(details).length === 0) return null;

                              const request = (details.request && typeof details.request === "object")
                                ? (details.request as Record<string, unknown>)
                                : undefined;
                              const response = (details.response && typeof details.response === "object")
                                ? (details.response as Record<string, unknown>)
                                : undefined;
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
                                        <Badge variant={status >= 400 ? "destructive" : "secondary"}>
                                          {status}
                                        </Badge>
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
                        ))}
                      </div>
                    </ScrollArea>
                  </div>
                )}

                {/* Backend Stack Trace */}
                {selectedError.backendStackTrace && (
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                        <Server className="h-4 w-4" />
                        Go Stack Trace
                      </h4>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => copySection("Backend stack trace", selectedError.backendStackTrace!)}
                      >
                        <Copy className="h-4 w-4" />
                      </Button>
                    </div>
                    <ScrollArea className="h-48 rounded-md border bg-muted">
                      <pre className="text-xs p-3 font-mono whitespace-pre-wrap">
                        {selectedError.backendStackTrace}
                      </pre>
                    </ScrollArea>
                  </div>
                )}

                {!selectedError.backendLogs?.length && !selectedError.backendStackTrace && (
                  <div className="text-center py-8 text-muted-foreground">
                    <Server className="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p className="text-sm">No backend logs captured</p>
                    <p className="text-xs mt-1">Backend logs are captured during publish, sync, and test operations</p>
                  </div>
                )}
              </TabsContent>

              {/* Request Info Tab */}
              <TabsContent value="request" className="m-0 space-y-4">
                {(selectedError.endpoint || selectedError.method) && (
                  <div>
                    <h4 className="text-sm font-medium text-muted-foreground mb-1 flex items-center gap-2">
                      <Globe className="h-4 w-4" />
                      API Request
                    </h4>
                    <div className="bg-muted p-3 rounded-md space-y-2">
                      {selectedError.method && selectedError.endpoint && (
                        <div className="flex items-center gap-2">
                          <Badge variant="outline" className="font-mono">{selectedError.method}</Badge>
                          <code className="text-sm">{selectedError.endpoint}</code>
                        </div>
                      )}
                      {selectedError.responseStatus && (
                        <p className="text-sm">
                          <span className="text-muted-foreground">Status: </span>
                          <Badge variant={selectedError.responseStatus >= 400 ? "destructive" : "secondary"}>
                            {selectedError.responseStatus}
                          </Badge>
                        </p>
                      )}

                      {/* Extra diagnostics (API base + resolved URL) */}
                      {(() => {
                        const ctx = (selectedError.context || {}) as Record<string, unknown>;
                        const requestUrl = typeof ctx.requestUrl === "string" ? ctx.requestUrl : undefined;
                        const apiBase = typeof ctx.apiBase === "string" ? ctx.apiBase : undefined;
                        const apiBaseAbsolute = typeof ctx.apiBaseAbsolute === "string" ? ctx.apiBaseAbsolute : undefined;
                        const rawViteApiUrl = typeof ctx["VITE_API_URL (raw)"] === "string" ? ctx["VITE_API_URL (raw)"] : undefined;
                        const rawViteWsUrl = typeof ctx["VITE_WS_URL (raw)"] === "string" ? ctx["VITE_WS_URL (raw)"] : undefined;
                        const resolvedApiOrigin = typeof ctx.resolvedApiOrigin === "string" ? ctx.resolvedApiOrigin : undefined;
                        const uiOrigin = typeof ctx.uiOrigin === "string" ? ctx.uiOrigin : undefined;

                        if (!requestUrl && !apiBase && !rawViteApiUrl) return null;

                        return (
                          <div className="pt-2 border-t border-border/60 space-y-1">
                            {requestUrl && (
                              <p className="text-sm">
                                <span className="text-muted-foreground">Requested URL: </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {requestUrl}
                                </code>
                              </p>
                            )}
                            {apiBase && (
                              <p className="text-sm">
                                <span className="text-muted-foreground">API Base (relative): </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {apiBase}
                                </code>
                              </p>
                            )}
                            {apiBaseAbsolute && (
                              <p className="text-sm">
                                <span className="text-muted-foreground">API Base (absolute): </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {apiBaseAbsolute}
                                </code>
                              </p>
                            )}
                            {uiOrigin && (
                              <p className="text-sm">
                                <span className="text-muted-foreground">UI Origin: </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {uiOrigin}
                                </code>
                              </p>
                            )}
                            <div className="pt-1 mt-1 border-t border-border/40">
                              <p className="text-xs text-muted-foreground mb-1">Environment Variables (raw):</p>
                              <p className="text-sm">
                                <span className="text-muted-foreground">VITE_API_URL: </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {rawViteApiUrl || "(not set)"}
                                </code>
                              </p>
                              <p className="text-sm">
                                <span className="text-muted-foreground">VITE_WS_URL: </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {rawViteWsUrl || "(not set)"}
                                </code>
                              </p>
                            </div>
                            {resolvedApiOrigin && (
                              <p className="text-sm">
                                <span className="text-muted-foreground">Resolved API Origin: </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {resolvedApiOrigin}
                                </code>
                              </p>
                            )}
                          </div>
                        );
                      })()}
                    </div>
                  </div>
                )}

                {selectedError.requestBody && (
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-1">
                        <Network className="h-4 w-4" />
                        Request Body
                      </h4>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => copySection("Request body", 
                          JSON.stringify(selectedError.requestBody, null, 2)
                        )}
                      >
                        <Copy className="h-4 w-4" />
                      </Button>
                    </div>
                    <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto font-mono max-h-40">
                      {JSON.stringify(selectedError.requestBody, null, 2)}
                    </pre>
                  </div>
                )}

                {!selectedError.endpoint && !selectedError.requestBody && (
                  <div className="text-center py-8 text-muted-foreground">
                    No request information available
                  </div>
                )}

                {/* Remote WordPress endpoint details (from backend log details.request) */}
                {(() => {
                  const logs = selectedError.backendLogs || [];
                  for (const l of logs) {
                    const details = l.details as Record<string, unknown> | undefined;
                    const req = details && typeof details.request === "object" && details.request
                      ? (details.request as Record<string, unknown>)
                      : undefined;
                    const url = req && typeof req.url === "string" ? req.url : undefined;
                    if (!url) continue;

                    const method = req && typeof req.method === "string" ? req.method : undefined;
                    const endpoint = req && typeof req.endpoint === "string" ? req.endpoint : undefined;
                    const resp = details && typeof details.response === "object" && details.response
                      ? (details.response as Record<string, unknown>)
                      : undefined;
                    const status = resp && typeof resp.status === "number" ? resp.status : undefined;

                    return (
                      <div>
                        <h4 className="text-sm font-medium text-muted-foreground mb-1 flex items-center gap-2">
                          <Globe className="h-4 w-4" />
                          WordPress Request (remote)
                        </h4>
                        <div className="bg-muted p-3 rounded-md space-y-2">
                          {(method || endpoint) && (
                            <div className="flex items-center gap-2">
                              {method && <Badge variant="outline" className="font-mono">{method}</Badge>}
                              {endpoint && <code className="text-sm break-all">{endpoint}</code>}
                              {typeof status === "number" && (
                                <Badge variant={status >= 400 ? "destructive" : "secondary"}>
                                  {status}
                                </Badge>
                              )}
                            </div>
                          )}
                          <p className="text-sm">
                            <span className="text-muted-foreground">URL: </span>
                            <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">{url}</code>
                          </p>
                        </div>
                      </div>
                    );
                  }
                  return null;
                })()}
              </TabsContent>

              {/* Full Context Tab - with JSON Highlighting */}
              <TabsContent value="context" className="m-0 space-y-4">
                {selectedError.context && Object.keys(selectedError.context).length > 0 ? (
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <h4 className="text-sm font-medium text-muted-foreground">Full Error Context</h4>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => copySection("Context", 
                          JSON.stringify(selectedError.context, null, 2)
                        )}
                      >
                        <Copy className="h-4 w-4" />
                      </Button>
                    </div>
                    <ScrollArea className="h-64 rounded-md border bg-muted">
                      <div className="p-3">
                        <JsonHighlighter json={selectedError.context} />
                      </div>
                    </ScrollArea>
                  </div>
                ) : (
                  <div className="text-center py-8 text-muted-foreground">
                    No additional context available
                  </div>
                )}
              </TabsContent>

              {/* Suggested Fixes Tab */}
              <TabsContent value="fixes" className="m-0">
                <div className="space-y-3">
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Lightbulb className="h-4 w-4" />
                    <span>Suggested fixes for error code <code className="bg-muted px-1 rounded">{selectedError.code}</code></span>
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
            </ScrollArea>
          </Tabs>
        </div>

        <div className="flex justify-end gap-2 pt-4 border-t">
          <Button 
            variant="outline" 
            onClick={async () => {
              try {
                const report = generateErrorReport(selectedError, { appName, appVersion, gitCommit, buildTime });

                const resp = await fetch("/api/v1/errors/bundle", {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/json",
                  },
                  body: JSON.stringify({ report }),
                });

                if (!resp.ok) {
                  throw new Error(`bundle download failed: ${resp.status}`);
                }

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
            }}
          >
            <Download className="h-4 w-4 mr-2" />
            Download Bundle
          </Button>
          <Button variant="outline" onClick={closeErrorModal}>
            Close
          </Button>
          <Button onClick={copyFullError}>
            <Copy className="h-4 w-4 mr-2" />
            Copy Full Report
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

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

  // Build trigger context section
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

  // Build invocation chain section
  const chainSection = error.invocationChain && error.invocationChain.length > 0
    ? `### Invocation Chain\n\`\`\`\n${error.invocationChain.map((call, i) => 
        `${'  '.repeat(i)}${i > 0 ? '└─ ' : ''}${call}`
      ).join('\n')}\n\`\`\`\n`
    : "";

  // Build parsed stack frames table
  const framesSection = error.parsedFrames && error.parsedFrames.length > 0
    ? `### Parsed Stack Frames\n| # | Function | File | Line |\n|---|----------|------|------|\n${
        error.parsedFrames.slice(0, 10).map((f, i) => 
          `| ${i + 1} | ${f.function} | ${f.file} | ${f.line} |`
        ).join('\n')
      }\n`
    : "";

  // Build backend logs section
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

  // Build backend stack trace section
  const backendStackSection = error.backendStackTrace
    ? `### Backend Stack Trace (Go)\n\`\`\`\n${error.backendStackTrace}\n\`\`\`\n`
    : "";

  // Build site URL section
  const siteUrlSection = error.siteUrl
    ? `### Target Site\n${error.siteUrl}\n`
    : "";

  // Build session info section
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
    E1001: ["Check JSON syntax in request body", "Ensure Content-Type is application/json"],
    E1002: ["Verify the ID is a valid number", "Check the URL path for typos"],
    E2001: ["Verify WordPress REST API is enabled", "Check site URL ends with /wp-json/wp/v2"],
    E2002: ["Verify application password is correct", "Regenerate application password in WordPress"],
    E3001: ["Ensure database is accessible", "Check disk space availability"],
    E3002: ["Verify the plugin path exists", "Check file system permissions"],
    E3003: ["Plugin may have been deleted", "Refresh the plugin list"],
    E4001: ["Verify both local and remote files exist", "Check network connectivity to WordPress site"],
    E5001: ["Ensure git is installed and accessible", "Verify repository URL is correct"],
    E5002: ["Check for uncommitted local changes", "Verify branch exists on remote"],
    E6001: ["Ensure plugin directory is accessible", "Check exclude patterns aren't too broad"],
    E7001: ["Test suite may not be configured", "Check E2E test configuration"],
    E9001: ["Service may still be initializing", "Restart the backend server"],
    E9003: ["Check network connectivity", "Verify backend server is running on correct port"],
    E9005: [
      "Set VITE_API_URL to your backend origin (e.g. http://localhost:8080)",
      "If you are using the hosted preview, it cannot reach your localhost backend—open the UI from your local backend URL instead",
    ],
    E9006: ["Verify the endpoint returns JSON", "Check response content-type and server error logs"],
    E9004: ["This feature is not yet implemented", "Check documentation for available features"],
  };
  return fixes[code] || [
    "Check the error details for more information",
    "Verify backend server is running and accessible",
    "Review recent changes to your configuration",
    "Contact support if the issue persists",
  ];
}
