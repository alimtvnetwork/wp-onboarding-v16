import { useState, useEffect, useCallback, useRef } from "react";
import { useSearchParams } from "react-router-dom";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Loader2, Globe, Key, RefreshCw, ExternalLink, AlertCircle, CheckCircle2, Lock, History, Trash2, Clock, ChevronDown, Copy, Check } from "lucide-react";
import { useSites } from "@/hooks/useSites";
import { api, requireSuccess } from "@/lib/api";
import SwaggerUI from "swagger-ui-react";
import "swagger-ui-react/swagger-ui.css";

interface RequestHistoryItem {
  id: string;
  method: string;
  url: string;
  status: number;
  duration: number;
  timestamp: Date;
  requestBody?: string;
  responseBody?: string;
}

export default function ApiExplorer() {
  const [searchParams] = useSearchParams();
  const { data: sites, isLoading: sitesLoading } = useSites();
  const [selectedSiteId, setSelectedSiteId] = useState<string>("");
  const [credentials, setCredentials] = useState<{ url: string; username: string; appPassword: string } | null>(null);
  const [spec, setSpec] = useState<object | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingCredentials, setLoadingCredentials] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [authenticated, setAuthenticated] = useState(false);
  const [requestHistory, setRequestHistory] = useState<RequestHistoryItem[]>([]);
  const [expandedItems, setExpandedItems] = useState<Set<string>>(new Set());
  const [copiedId, setCopiedId] = useState<string | null>(null);
  const historyIdRef = useRef(0);
  const initializedFromUrl = useRef(false);
  const selectedSite = sites?.find((s) => s.id.toString() === selectedSiteId);

  // Auto-select site from URL query param
  useEffect(() => {
    if (!initializedFromUrl.current && sites && sites.length > 0) {
      const siteIdParam = searchParams.get("siteId");
      if (siteIdParam && sites.some(s => s.id.toString() === siteIdParam)) {
        setSelectedSiteId(siteIdParam);
      }
      initializedFromUrl.current = true;
    }
  }, [sites, searchParams]);

  // Fetch credentials when site changes
  useEffect(() => {
    if (!selectedSiteId) {
      setCredentials(null);
      setSpec(null);
      setAuthenticated(false);
      setError(null);
      return;
    }

    const fetchCredentials = async () => {
      setLoadingCredentials(true);
      setError(null);
      try {
        const response = await api.getSiteCredentials(parseInt(selectedSiteId));
        const creds = requireSuccess(response, { endpoint: `/sites/${selectedSiteId}/credentials`, method: "GET" });
        setCredentials(creds);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Failed to fetch site credentials");
        setCredentials(null);
      } finally {
        setLoadingCredentials(false);
      }
    };

    fetchCredentials();
  }, [selectedSiteId]);

  // Auto-fetch OpenAPI spec when credentials are loaded
  useEffect(() => {
    if (credentials) {
      fetchOpenApiSpec();
    }
  }, [credentials]);

  const fetchOpenApiSpec = useCallback(async () => {
    if (!credentials) return;

    setLoading(true);
    setError(null);
    setSpec(null);
    setAuthenticated(false);

    try {
      const baseUrl = credentials.url.replace(/\/$/, "");
      const openApiUrl = `${baseUrl}/wp-json/riseup-asia-uploader/v1/openapi`;
      const authCredentials = btoa(`${credentials.username}:${credentials.appPassword}`);
      
      const startTime = performance.now();
      const response = await fetch(openApiUrl, {
        headers: {
          "Authorization": `Basic ${authCredentials}`,
        },
      });
      const duration = Math.round(performance.now() - startTime);

      // Record in history
      const historyItem: RequestHistoryItem = {
        id: `req-${++historyIdRef.current}`,
        method: "GET",
        url: openApiUrl,
        status: response.status,
        duration,
        timestamp: new Date(),
      };

      if (!response.ok) {
        const errorText = await response.text();
        historyItem.responseBody = errorText;
        setRequestHistory(prev => [historyItem, ...prev].slice(0, 50));

        if (response.status === 401) {
          throw new Error("Authentication failed. Check your credentials or update the site configuration.");
        } else if (response.status === 404) {
          throw new Error("OpenAPI endpoint not found. Ensure the Riseup Asia Uploader plugin is installed and updated to v1.4.0+.");
        }
        throw new Error(`Failed to fetch API spec: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      historyItem.responseBody = JSON.stringify(data, null, 2).slice(0, 500) + "...";
      setRequestHistory(prev => [historyItem, ...prev].slice(0, 50));
      
      data.servers = [
        {
          url: `${baseUrl}/wp-json/riseup-asia-uploader/v1`,
          description: selectedSite?.name || "WordPress Site",
        }
      ];

      setSpec(data);
      setAuthenticated(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to fetch OpenAPI specification");
    } finally {
      setLoading(false);
    }
  }, [credentials, selectedSite?.name]);

  // Request interceptor to add auth header and track requests
  const requestInterceptor = useCallback((req: { url: string; headers: Record<string, string>; body?: string; method?: string }) => {
    if (credentials) {
      const authCredentials = btoa(`${credentials.username}:${credentials.appPassword}`);
      req.headers["Authorization"] = `Basic ${authCredentials}`;
    }

    // Track the request
    const startTime = performance.now();
    const originalFetch = window.fetch;
    
    // We can't easily intercept the response from Swagger UI's internal fetch,
    // but we can at least log the request
    const historyItem: RequestHistoryItem = {
      id: `req-${++historyIdRef.current}`,
      method: req.method || "GET",
      url: req.url,
      status: 0, // Will be updated
      duration: 0,
      timestamp: new Date(),
      requestBody: req.body,
    };

    // Add to history immediately with pending status
    setRequestHistory(prev => [historyItem, ...prev].slice(0, 50));

    return req;
  }, [credentials]);

  // Response interceptor to track responses
  const responseInterceptor = useCallback((res: Response) => {
    // Update the most recent request with the response
    setRequestHistory(prev => {
      if (prev.length === 0) return prev;
      const updated = [...prev];
      const lastReq = updated[0];
      if (lastReq.status === 0) {
        updated[0] = {
          ...lastReq,
          status: res.status,
          duration: Math.round(performance.now() - lastReq.timestamp.getTime()),
        };
      }
      return updated;
    });
    return res;
  }, []);

  const clearHistory = () => {
    setRequestHistory([]);
    setExpandedItems(new Set());
  };

  const toggleExpand = (id: string) => {
    setExpandedItems(prev => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const copyToClipboard = async (text: string, id: string) => {
    await navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };
  const getStatusColor = (status: number) => {
    if (status === 0) return "text-muted-foreground";
    if (status >= 200 && status < 300) return "text-emerald-600 dark:text-emerald-400";
    if (status >= 400 && status < 500) return "text-amber-600 dark:text-amber-400";
    return "text-destructive";
  };

  const getMethodColor = (method: string) => {
    switch (method.toUpperCase()) {
      case "GET": return "bg-primary/20 text-primary";
      case "POST": return "bg-emerald-500/20 text-emerald-700 dark:text-emerald-400";
      case "PUT": return "bg-amber-500/20 text-amber-700 dark:text-amber-400";
      case "DELETE": return "bg-destructive/20 text-destructive";
      default: return "bg-muted text-muted-foreground";
    }
  };

  return (
    <>
      <div className="p-6 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold flex items-center gap-2">
              <Globe className="h-6 w-6" />
              API Explorer
            </h1>
            <p className="text-muted-foreground mt-1">
              Browse and test WordPress REST API endpoints with Swagger UI
            </p>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          {/* Main content */}
          <div className="lg:col-span-2 space-y-6">
            {/* Site Selection */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <Key className="h-4 w-4" />
                  Select WordPress Site
                </CardTitle>
                <CardDescription>
                  Credentials are automatically loaded from the database.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex gap-4 items-end">
                  <div className="flex-1">
                    <Label htmlFor="site-select">WordPress Site</Label>
                    <Select
                      value={selectedSiteId}
                      onValueChange={setSelectedSiteId}
                      disabled={sitesLoading}
                    >
                      <SelectTrigger id="site-select" className="mt-1.5">
                        <SelectValue placeholder="Select a site..." />
                      </SelectTrigger>
                      <SelectContent>
                        {sites?.map((site) => (
                          <SelectItem key={site.id} value={site.id.toString()}>
                            {site.name} ({site.url})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <Button
                    onClick={fetchOpenApiSpec}
                    disabled={!credentials || loading}
                    variant="outline"
                  >
                    {loading ? (
                      <Loader2 className="h-4 w-4 animate-spin mr-2" />
                    ) : (
                      <RefreshCw className="h-4 w-4 mr-2" />
                    )}
                    Refresh
                  </Button>
                </div>

                {loadingCredentials && (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Loading credentials...
                  </div>
                )}

                {credentials && (
                  <div className="flex flex-wrap items-center gap-4 text-sm">
                    <code className="bg-muted px-2 py-0.5 rounded text-xs">{credentials.url}</code>
                    <code className="bg-muted px-2 py-0.5 rounded text-xs">{credentials.username}</code>
                    <code className="bg-muted px-2 py-0.5 rounded text-xs">••••••••</code>
                    {authenticated && (
                      <Badge variant="secondary" className="gap-1">
                        <CheckCircle2 className="h-3 w-3" />
                        Connected
                      </Badge>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>

            {error && (
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}

            {loading && (
              <Card>
                <CardContent className="flex items-center justify-center py-12">
                  <div className="text-center space-y-3">
                    <Loader2 className="h-8 w-8 animate-spin mx-auto text-muted-foreground" />
                    <p className="text-muted-foreground">Loading API specification...</p>
                  </div>
                </CardContent>
              </Card>
            )}

            {!selectedSite && !loading && (
              <Card>
                <CardContent className="flex items-center justify-center py-12">
                  <div className="text-center space-y-3">
                    <Lock className="h-12 w-12 mx-auto text-muted-foreground/50" />
                    <div>
                      <p className="font-medium">Select a WordPress Site</p>
                      <p className="text-sm text-muted-foreground">
                        Choose a site to automatically connect using stored credentials
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}

            {spec && !loading && (
              <Card className="swagger-card overflow-hidden">
                <CardHeader className="bg-muted/30 border-b">
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-lg">Riseup Asia Uploader API</CardTitle>
                      <CardDescription>
                        Interactive API documentation - expand endpoints to test them
                      </CardDescription>
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        const baseUrl = credentials?.url.replace(/\/$/, "");
                        window.open(`${baseUrl}/wp-json/riseup-asia-uploader/v1/openapi`, "_blank");
                      }}
                    >
                      <ExternalLink className="h-4 w-4 mr-2" />
                      Raw JSON
                    </Button>
                  </div>
                </CardHeader>
                <CardContent className="p-0">
                  <div className="swagger-ui-wrapper">
                    <SwaggerUI
                      spec={spec}
                      requestInterceptor={requestInterceptor}
                      responseInterceptor={responseInterceptor}
                      docExpansion="list"
                      defaultModelsExpandDepth={-1}
                      displayOperationId={false}
                      filter={true}
                      showExtensions={false}
                      showCommonExtensions={false}
                      tryItOutEnabled={true}
                    />
                  </div>
                </CardContent>
              </Card>
            )}
          </div>

          {/* Request History Panel */}
          <div className="space-y-6">
            <Card>
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <CardTitle className="text-lg flex items-center gap-2">
                    <History className="h-4 w-4" />
                    Request History
                  </CardTitle>
                  {requestHistory.length > 0 && (
                    <Button variant="ghost" size="sm" onClick={clearHistory}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <ScrollArea className="h-[500px]">
                  {requestHistory.length === 0 ? (
                    <div className="p-4 text-center text-muted-foreground text-sm">
                      <History className="h-8 w-8 mx-auto mb-2 opacity-50" />
                      <p>No requests yet</p>
                      <p className="text-xs mt-1">Requests will appear here as you test the API</p>
                    </div>
                  ) : (
                    <div className="divide-y">
                      {requestHistory.map((item) => {
                        const isExpanded = expandedItems.has(item.id);
                        const hasDetails = item.requestBody || item.responseBody;
                        
                        return (
                          <Collapsible
                            key={item.id}
                            open={isExpanded}
                            onOpenChange={() => hasDetails && toggleExpand(item.id)}
                          >
                            <CollapsibleTrigger asChild disabled={!hasDetails}>
                              <div className={`p-3 transition-colors ${hasDetails ? 'cursor-pointer hover:bg-muted/50' : ''}`}>
                                <div className="flex items-center gap-2 mb-1">
                                  <Badge variant="outline" className={`text-xs font-mono ${getMethodColor(item.method)}`}>
                                    {item.method}
                                  </Badge>
                                  <span className={`text-sm font-medium ${getStatusColor(item.status)}`}>
                                    {item.status === 0 ? "..." : item.status}
                                  </span>
                                  <span className="text-xs text-muted-foreground flex items-center gap-1">
                                    <Clock className="h-3 w-3" />
                                    {item.duration}ms
                                  </span>
                                  {hasDetails && (
                                    <ChevronDown className={`h-3 w-3 ml-auto text-muted-foreground transition-transform ${isExpanded ? 'rotate-180' : ''}`} />
                                  )}
                                </div>
                                <p className="text-xs text-muted-foreground truncate font-mono" title={item.url}>
                                  {item.url.replace(/^https?:\/\/[^/]+/, "")}
                                </p>
                                <p className="text-xs text-muted-foreground mt-1">
                                  {item.timestamp.toLocaleTimeString()}
                                </p>
                              </div>
                            </CollapsibleTrigger>
                            
                            <CollapsibleContent>
                              <div className="px-3 pb-3 space-y-2">
                                {item.requestBody && (
                                  <div className="space-y-1">
                                    <div className="flex items-center justify-between">
                                      <span className="text-xs font-medium text-muted-foreground">Request Body</span>
                                      <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 px-2"
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          copyToClipboard(item.requestBody!, `req-${item.id}`);
                                        }}
                                      >
                                        {copiedId === `req-${item.id}` ? (
                                          <Check className="h-3 w-3" />
                                        ) : (
                                          <Copy className="h-3 w-3" />
                                        )}
                                      </Button>
                                    </div>
                                    <pre className="text-xs bg-muted/50 p-2 rounded overflow-x-auto max-h-32 whitespace-pre-wrap break-all">
                                      {item.requestBody}
                                    </pre>
                                  </div>
                                )}
                                {item.responseBody && (
                                  <div className="space-y-1">
                                    <div className="flex items-center justify-between">
                                      <span className="text-xs font-medium text-muted-foreground">Response Body</span>
                                      <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 px-2"
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          copyToClipboard(item.responseBody!, `res-${item.id}`);
                                        }}
                                      >
                                        {copiedId === `res-${item.id}` ? (
                                          <Check className="h-3 w-3" />
                                        ) : (
                                          <Copy className="h-3 w-3" />
                                        )}
                                      </Button>
                                    </div>
                                    <pre className="text-xs bg-muted/50 p-2 rounded overflow-x-auto max-h-48 whitespace-pre-wrap break-all">
                                      {item.responseBody}
                                    </pre>
                                  </div>
                                )}
                              </div>
                            </CollapsibleContent>
                          </Collapsible>
                        );
                      })}
                    </div>
                  )}
                </ScrollArea>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <style>{`
        .swagger-ui-wrapper {
          padding: 1rem;
        }
        .swagger-ui-wrapper .swagger-ui {
          font-family: inherit;
        }
        .swagger-ui-wrapper .swagger-ui .info {
          margin: 0 0 1rem 0;
        }
        .swagger-ui-wrapper .swagger-ui .info .title {
          font-size: 1.5rem;
        }
        .swagger-ui-wrapper .swagger-ui .scheme-container {
          background: transparent;
          padding: 0;
          box-shadow: none;
        }
        .swagger-ui-wrapper .swagger-ui .opblock {
          border-radius: 0.5rem;
          margin-bottom: 0.5rem;
        }
        .swagger-ui-wrapper .swagger-ui .opblock .opblock-summary {
          border-radius: 0.5rem;
        }
        .swagger-ui-wrapper .swagger-ui .opblock.opblock-get {
          background: hsl(var(--primary) / 0.1);
          border-color: hsl(var(--primary));
        }
        .swagger-ui-wrapper .swagger-ui .opblock.opblock-post {
          background: hsl(142 76% 36% / 0.1);
          border-color: hsl(142 76% 36%);
        }
        .swagger-ui-wrapper .swagger-ui .opblock.opblock-delete {
          background: hsl(var(--destructive) / 0.1);
          border-color: hsl(var(--destructive));
        }
        .swagger-ui-wrapper .swagger-ui .btn {
          border-radius: 0.375rem;
        }
        .swagger-ui-wrapper .swagger-ui select {
          border-radius: 0.375rem;
        }
        .swagger-ui-wrapper .swagger-ui input[type=text],
        .swagger-ui-wrapper .swagger-ui textarea {
          border-radius: 0.375rem;
        }
        .swagger-ui-wrapper .swagger-ui .model-box {
          border-radius: 0.5rem;
        }
        .swagger-ui-wrapper .swagger-ui .topbar {
          display: none;
        }
        .dark .swagger-ui-wrapper .swagger-ui,
        .dark .swagger-ui-wrapper .swagger-ui .info .title,
        .dark .swagger-ui-wrapper .swagger-ui .info p,
        .dark .swagger-ui-wrapper .swagger-ui .info li,
        .dark .swagger-ui-wrapper .swagger-ui table thead tr th,
        .dark .swagger-ui-wrapper .swagger-ui table tbody tr td,
        .dark .swagger-ui-wrapper .swagger-ui .parameter__name,
        .dark .swagger-ui-wrapper .swagger-ui .parameter__type,
        .dark .swagger-ui-wrapper .swagger-ui .response-col_status,
        .dark .swagger-ui-wrapper .swagger-ui .response-col_description,
        .dark .swagger-ui-wrapper .swagger-ui .opblock .opblock-summary-description,
        .dark .swagger-ui-wrapper .swagger-ui .opblock-description-wrapper p {
          color: hsl(var(--foreground));
        }
        .dark .swagger-ui-wrapper .swagger-ui .opblock .opblock-section-header {
          background: hsl(var(--muted));
        }
        .dark .swagger-ui-wrapper .swagger-ui .opblock .opblock-section-header h4 {
          color: hsl(var(--foreground));
        }
        .dark .swagger-ui-wrapper .swagger-ui .model-box,
        .dark .swagger-ui-wrapper .swagger-ui .models {
          background: hsl(var(--muted));
        }
        .dark .swagger-ui-wrapper .swagger-ui .model {
          color: hsl(var(--foreground));
        }
      `}</style>
    </>
  );
}
