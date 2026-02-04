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
  
  if (!selectedError) return null;

  const copyFullError = () => {
    const text = generateErrorReport(selectedError, { appName, appVersion });
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
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="request">Request Info</TabsTrigger>
              <TabsTrigger value="context">Full Context</TabsTrigger>
              <TabsTrigger value="fixes">Suggested Fixes</TabsTrigger>
            </TabsList>

            <ScrollArea className="flex-1 mt-4 pr-4">
              {/* Overview Tab */}
              <TabsContent value="overview" className="space-y-4 m-0">
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

                {selectedError.file && (
                  <div>
                    <h4 className="text-sm font-medium text-muted-foreground mb-1">Location</h4>
                    <div className="flex items-center gap-2">
                      <FileCode2 className="h-4 w-4 text-muted-foreground" />
                      <code className="text-sm bg-muted px-2 py-1 rounded">
                        {selectedError.file}:{selectedError.line}
                      </code>
                      {selectedError.function && (
                        <span className="text-sm text-muted-foreground">
                          in <code className="bg-muted px-1 rounded">{selectedError.function}</code>
                        </span>
                      )}
                    </div>
                  </div>
                )}

                {selectedError.stackTrace && (
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <h4 className="text-sm font-medium text-muted-foreground">Stack Trace</h4>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => copySection("Stack trace", selectedError.stackTrace!)}
                      >
                        <Copy className="h-4 w-4" />
                      </Button>
                    </div>
                    <pre className="text-xs bg-muted p-4 rounded-md overflow-x-auto whitespace-pre-wrap font-mono max-h-40">
                      {selectedError.stackTrace}
                    </pre>
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
                        const apiOrigin = typeof ctx.apiOrigin === "string" ? ctx.apiOrigin : undefined;

                        if (!requestUrl && !apiBase && !apiOrigin) return null;

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
                                <span className="text-muted-foreground">Configured API base: </span>
                                <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                  {apiBase}
                                </code>
                              </p>
                            )}
                            <p className="text-sm">
                              <span className="text-muted-foreground">VITE_API_URL: </span>
                              <code className="text-xs bg-background/60 px-1 py-0.5 rounded break-all">
                                {apiOrigin || "(not set)"}
                              </code>
                            </p>
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
  app?: { appName: string; appVersion: string }
): string {
  return `## Error Report

**App:** ${app?.appName || "WP Plugin Publish"} v${app?.appVersion || "0.0.0"}

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

