import { useErrorStore, CapturedError } from '@/stores/errorStore';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Copy, ExternalLink, AlertCircle, FileCode2, Network, Lightbulb, Globe } from "lucide-react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { useVersionInfo } from "@/hooks/useWhatsNew";

export function GlobalErrorModal() {
  const { selectedError, isModalOpen, closeErrorModal } = useErrorStore();
  const { data: versionInfo } = useVersionInfo();
  const appName = versionInfo?.appName || "WP Plugin Publish";
  const appVersion = versionInfo?.version || "0.0.0";
  const gitCommit = versionInfo?.gitCommit;
  const buildTime = versionInfo?.buildTime;
  
  if (!selectedError) return null;

  const copyFullError = () => {
    const text = generateErrorReport(selectedError, { appName, appVersion, gitCommit, buildTime });
    navigator.clipboard.writeText(text);
    toast.success("Full error report copied to clipboard");
  };

  const copySection = (label: string, content: string) => {
    navigator.clipboard.writeText(content);
    toast.success(`${label} copied`);
  };

  const levelColors = {
    error: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
    warn: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
    info: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
  };

  const suggestedFixes = getSuggestedFixes(selectedError.code);

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
                  {new Date(selectedError.createdAt).toLocaleString()}
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
            <TabsList className="grid w-full grid-cols-5">
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="request">Request</TabsTrigger>
              <TabsTrigger value="stack">Stack Trace</TabsTrigger>
              <TabsTrigger value="context">Context</TabsTrigger>
              <TabsTrigger value="fixes">Fixes</TabsTrigger>
            </TabsList>

            <ScrollArea className="flex-1 mt-4 pr-4">
              {/* Overview Tab - Compact summary */}
              <TabsContent value="overview" className="space-y-3 m-0">
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

              {/* Stack Trace Tab - Split for Frontend/Backend */}
              <TabsContent value="stack" className="m-0 space-y-4">
                {selectedError.stackTrace ? (
                  <>
                    {/* Determine if we have backend stack trace (Go-style) */}
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
              </TabsContent>

              {/* Full Context Tab */}
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
                    <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto font-mono">
                      {JSON.stringify(selectedError.context, null, 2)}
                    </pre>
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

  return `## Error Report

${appInfo.join("\n")}

**ID:** ${error.id}
**Code:** ${error.code}
**Level:** ${error.level}
**Timestamp:** ${error.createdAt}

### Message
${error.message}

${error.details ? `### Details\n${error.details}\n` : ""}
${error.endpoint ? `### Request\n**${error.method || "GET"}** ${error.endpoint}\n${error.responseStatus ? `**Status:** ${error.responseStatus}\n` : ""}` : ""}
${error.requestBody ? `### Request Body\n\`\`\`json\n${JSON.stringify(error.requestBody, null, 2)}\n\`\`\`\n` : ""}
${error.file ? `### Location\n\`${error.file}:${error.line}\` (${error.function})\n` : ""}
${error.context && Object.keys(error.context).length > 0 ? `### Context\n\`\`\`json\n${JSON.stringify(error.context, null, 2)}\n\`\`\`\n` : ""}
${error.stackTrace ? `### Stack Trace\n\`\`\`\n${error.stackTrace}\n\`\`\`` : ""}

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

