import { CapturedError } from '@/stores/errorStore';
import { SessionDiagnostics } from '@/lib/api';
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Copy, Network, Globe, ChevronRight, Route } from "lucide-react";
import { cn } from "@/lib/utils";

interface RequestDetailsProps {
  error: CapturedError;
  copySection: (label: string, content: string) => void;
  sessionDiagnostics?: SessionDiagnostics | null;
}

export function RequestDetails({ error, copySection, sessionDiagnostics }: RequestDetailsProps) {
  const ctx = (error.context || {}) as Record<string, unknown>;
  const requestUrl = typeof ctx.requestUrl === "string" ? ctx.requestUrl : undefined;
  const apiBase = typeof ctx.apiBase === "string" ? ctx.apiBase : undefined;
  const apiBaseAbsolute = typeof ctx.apiBaseAbsolute === "string" ? ctx.apiBaseAbsolute : undefined;
  const rawViteApiUrl = typeof ctx["VITE_API_URL (raw)"] === "string" ? ctx["VITE_API_URL (raw)"] : undefined;
  const rawViteWsUrl = typeof ctx["VITE_WS_URL (raw)"] === "string" ? ctx["VITE_WS_URL (raw)"] : undefined;
  const resolvedApiOrigin = typeof ctx.resolvedApiOrigin === "string" ? ctx.resolvedApiOrigin : undefined;
  const uiOrigin = typeof ctx.uiOrigin === "string" ? ctx.uiOrigin : undefined;

  const phpEndpointUrl = sessionDiagnostics?.response?.requestUrl;
  const phpResponseStatus = sessionDiagnostics?.response?.statusCode;
  const phpResponseBody = sessionDiagnostics?.response?.body;

  const hasRequestChain = error.requestedAt || error.requestDelegatedAt;
  const hasDelegation = error.requestDelegatedAt || phpEndpointUrl;

  return (
    <div className="space-y-4">
      {/* Request Chain Visualization */}
      {(hasRequestChain || hasDelegation) && (
        <div>
          <h4 className="text-sm font-medium text-muted-foreground mb-2 flex items-center gap-2">
            <Route className="h-4 w-4" />
            Request Chain
          </h4>
          <div className="space-y-0">
            {/* Node 1: React → Go */}
            <div className={cn("border p-3", hasDelegation ? "rounded-t-md" : "rounded-md", "bg-muted/50")}>
              <div className="flex items-center gap-2 mb-1">
                <div className="w-2 h-2 rounded-full bg-blue-500 shrink-0" />
                <Badge variant="outline" className="text-xs font-mono bg-blue-500/10 border-blue-500/30">React → Go</Badge>
                {error.method && <Badge variant="outline" className="font-mono text-xs">{error.method}</Badge>}
                {error.responseStatus && (
                  <Badge variant={error.responseStatus >= 400 ? "destructive" : "secondary"} className="text-xs">{error.responseStatus}</Badge>
                )}
              </div>
              <div className="ml-4 space-y-1">
                <p className="text-xs font-mono text-muted-foreground break-all">
                  {error.requestedAt || requestUrl || error.endpoint || "N/A"}
                </p>
                {error.requestBody && (
                  <div className="mt-2">
                    <div className="flex items-center justify-between">
                      <span className="text-xs text-muted-foreground font-medium">Request Body</span>
                      <Button variant="ghost" size="sm" className="h-5 px-1" onClick={() => copySection("Request body", JSON.stringify(error.requestBody, null, 2))}>
                        <Copy className="h-3 w-3" />
                      </Button>
                    </div>
                    <pre className="text-xs bg-background/60 p-2 rounded mt-1 overflow-x-auto font-mono max-h-32">
                      {JSON.stringify(error.requestBody, null, 2)}
                    </pre>
                  </div>
                )}
              </div>
            </div>

            {hasDelegation && (
              <div className="flex items-center pl-4">
                <div className="w-0.5 h-4 bg-border ml-[3px]" />
              </div>
            )}

            {hasDelegation && (
              <div className="border rounded-b-md bg-muted/50 p-3">
                <div className="flex items-center gap-2 mb-1">
                  <div className="w-2 h-2 rounded-full bg-orange-500 shrink-0" />
                  <Badge variant="outline" className="text-xs font-mono bg-orange-500/10 border-orange-500/30 text-orange-600 dark:text-orange-400">Go → PHP</Badge>
                  {phpResponseStatus && (
                    <Badge variant={phpResponseStatus >= 400 ? "destructive" : "secondary"} className="text-xs font-mono">{phpResponseStatus}</Badge>
                  )}
                </div>
                <div className="ml-4 space-y-2">
                  {phpEndpointUrl && (
                    <p className="text-xs font-mono text-orange-600 dark:text-orange-400 break-all">{phpEndpointUrl}</p>
                  )}
                  {!phpEndpointUrl && error.requestDelegatedAt && (
                    <p className="text-xs font-mono text-muted-foreground break-all">{error.requestDelegatedAt}</p>
                  )}
                  {phpResponseBody && (
                    <details className="group">
                      <summary className="text-xs text-muted-foreground font-medium cursor-pointer hover:text-foreground flex items-center gap-1">
                        <ChevronRight className="h-3 w-3 group-open:rotate-90 transition-transform" />
                        PHP Response Body
                      </summary>
                      <pre className="text-xs bg-background/60 p-2 rounded mt-1 overflow-x-auto font-mono max-h-48 whitespace-pre-wrap break-all">
                        {typeof phpResponseBody === "string" ? phpResponseBody : JSON.stringify(phpResponseBody, null, 2)}
                      </pre>
                    </details>
                  )}
                  {error.envelopeErrors?.DelegatedServiceErrorStack && error.envelopeErrors.DelegatedServiceErrorStack.length > 0 && (
                    <div>
                      <span className="text-xs text-muted-foreground font-medium">PHP Error Stack</span>
                      <pre className="text-xs bg-background/60 p-2 rounded mt-1 overflow-x-auto font-mono max-h-32 text-orange-600 dark:text-orange-400 whitespace-pre-wrap">
                        {error.envelopeErrors.DelegatedServiceErrorStack.join('\n')}
                      </pre>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {!hasRequestChain && (error.endpoint || error.method) && (
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
                <Badge variant={error.responseStatus >= 400 ? "destructive" : "secondary"}>{error.responseStatus}</Badge>
              </p>
            )}
          </div>
        </div>
      )}

      {!hasRequestChain && error.requestBody && (
        <div>
          <div className="flex items-center justify-between mb-1">
            <h4 className="text-sm font-medium text-muted-foreground flex items-center gap-1">
              <Network className="h-4 w-4" />
              Request Body
            </h4>
            <Button variant="ghost" size="sm" onClick={() => copySection("Request body", JSON.stringify(error.requestBody, null, 2))}>
              <Copy className="h-4 w-4" />
            </Button>
          </div>
          <pre className="text-xs bg-muted p-3 rounded-md overflow-x-auto font-mono max-h-40">
            {JSON.stringify(error.requestBody, null, 2)}
          </pre>
        </div>
      )}

      {(requestUrl || apiBase || rawViteApiUrl) && (
        <div className="border-t border-border/60 pt-3 space-y-1">
          <h4 className="text-xs text-muted-foreground font-medium mb-1">Environment</h4>
          {apiBase && <p className="text-xs"><span className="text-muted-foreground">API Base: </span><code className="bg-background/60 px-1 py-0.5 rounded break-all">{apiBase}</code></p>}
          {apiBaseAbsolute && <p className="text-xs"><span className="text-muted-foreground">API Base (absolute): </span><code className="bg-background/60 px-1 py-0.5 rounded break-all">{apiBaseAbsolute}</code></p>}
          {rawViteApiUrl && <p className="text-xs"><span className="text-muted-foreground">VITE_API_URL: </span><code className="bg-background/60 px-1 py-0.5 rounded">{rawViteApiUrl}</code></p>}
          {rawViteWsUrl && <p className="text-xs"><span className="text-muted-foreground">VITE_WS_URL: </span><code className="bg-background/60 px-1 py-0.5 rounded">{rawViteWsUrl}</code></p>}
          {uiOrigin && <p className="text-xs"><span className="text-muted-foreground">UI Origin: </span><code className="bg-background/60 px-1 py-0.5 rounded">{uiOrigin}</code></p>}
          {resolvedApiOrigin && <p className="text-xs"><span className="text-muted-foreground">Resolved API Origin: </span><code className="bg-background/60 px-1 py-0.5 rounded break-all">{resolvedApiOrigin}</code></p>}
        </div>
      )}

      {!error.endpoint && !error.requestBody && !hasRequestChain && (
        <div className="text-center py-8 text-muted-foreground">No request information available</div>
      )}
    </div>
  );
}
