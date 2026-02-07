import { useState, useEffect, useCallback, useRef } from "react";
import { useSearchParams } from "react-router-dom";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { Loader2, Globe, Key, RefreshCw, ExternalLink, AlertCircle, CheckCircle2, Lock, Server, Timer, TimerOff } from "lucide-react";
import { useSites } from "@/hooks/useSites";
import { api, requireSuccess } from "@/lib/api";
import { resolveApiBase } from "@/lib/endpoints";
import { RequestHistoryPanel, type RequestHistoryItem } from "@/components/api-explorer/RequestHistoryPanel";
import SwaggerUI from "swagger-ui-react";
import "swagger-ui-react/swagger-ui.css";

const AUTO_REFRESH_INTERVAL = 60_000; // 60 seconds

type ApiMode = "wordpress" | "backend";

export default function ApiExplorer() {
  const [searchParams] = useSearchParams();
  const { data: sites, isLoading: sitesLoading } = useSites();
  const [apiMode, setApiMode] = useState<ApiMode>("backend");
  const [selectedSiteId, setSelectedSiteId] = useState<string>("");
  const [credentials, setCredentials] = useState<{ url: string; username: string; appPassword: string } | null>(null);
  const [spec, setSpec] = useState<object | null>(null);
  const [backendSpec, setBackendSpec] = useState<object | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingCredentials, setLoadingCredentials] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [authenticated, setAuthenticated] = useState(false);
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [lastRefreshed, setLastRefreshed] = useState<Date | null>(null);
  const [requestHistory, setRequestHistory] = useState<RequestHistoryItem[]>([]);
  const historyIdRef = useRef(0);
  const initializedFromUrl = useRef(false);
  const autoRefreshRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const selectedSite = sites?.find((s) => s.id.toString() === selectedSiteId);

  const addHistoryItem = useCallback((item: Omit<RequestHistoryItem, "id">) => {
    const entry: RequestHistoryItem = { ...item, id: `req-${++historyIdRef.current}` };
    setRequestHistory(prev => [entry, ...prev].slice(0, 50));
    return entry.id;
  }, []);

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
        headers: { "Authorization": `Basic ${authCredentials}` },
      });
      const duration = Math.round(performance.now() - startTime);

      const historyBase = {
        method: "GET",
        url: openApiUrl,
        status: response.status,
        duration,
        timestamp: new Date(),
      };

      if (!response.ok) {
        const errorText = await response.text();
        addHistoryItem({ ...historyBase, responseBody: errorText });
        if (response.status === 401) throw new Error("Authentication failed. Check your credentials or update the site configuration.");
        if (response.status === 404) throw new Error("OpenAPI endpoint not found. Ensure the Riseup Asia Uploader plugin is installed and updated to v1.4.0+.");
        throw new Error(`Failed to fetch API spec: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      addHistoryItem({ ...historyBase, responseBody: JSON.stringify(data, null, 2).slice(0, 500) + "..." });

      data.servers = [{
        url: `${baseUrl}/wp-json/riseup-asia-uploader/v1`,
        description: selectedSite?.name || "WordPress Site",
      }];

      setSpec(data);
      setAuthenticated(true);
      setLastRefreshed(new Date());
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to fetch OpenAPI specification");
    } finally {
      setLoading(false);
    }
  }, [credentials, selectedSite?.name, addHistoryItem]);

  // Fetch backend OpenAPI spec with server injection
  const fetchBackendSpec = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const apiBase = resolveApiBase();
      const url = `${apiBase}/openapi`;
      const startTime = performance.now();
      const response = await fetch(url);
      const duration = Math.round(performance.now() - startTime);

      const historyBase = {
        method: "GET",
        url,
        status: response.status,
        duration,
        timestamp: new Date(),
      };

      if (!response.ok) {
        addHistoryItem({ ...historyBase, responseBody: await response.text() });
        throw new Error(`Failed to fetch backend spec: ${response.status}`);
      }

      const data = await response.json();
      addHistoryItem({ ...historyBase, responseBody: JSON.stringify(data, null, 2).slice(0, 500) + "..." });

      // Inject the correct server base URL so "Try it out" hits the right host
      data.servers = [{
        url: apiBase,
        description: "Local Backend",
      }];

      setBackendSpec(data);
      setLastRefreshed(new Date());
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to fetch backend OpenAPI spec");
    } finally {
      setLoading(false);
    }
  }, [addHistoryItem]);

  // Auto-load backend spec when switching to backend mode
  useEffect(() => {
    if (apiMode === "backend" && !backendSpec) {
      fetchBackendSpec();
    }
  }, [apiMode, backendSpec, fetchBackendSpec]);

  // Auto-refresh interval
  useEffect(() => {
    if (autoRefreshRef.current) {
      clearInterval(autoRefreshRef.current);
      autoRefreshRef.current = null;
    }

    if (autoRefresh) {
      const refreshFn = apiMode === "backend" ? fetchBackendSpec : fetchOpenApiSpec;
      autoRefreshRef.current = setInterval(refreshFn, AUTO_REFRESH_INTERVAL);
    }

    return () => {
      if (autoRefreshRef.current) clearInterval(autoRefreshRef.current);
    };
  }, [autoRefresh, apiMode, fetchBackendSpec, fetchOpenApiSpec]);

  // Request interceptor to add auth header and track requests
  const requestInterceptor = useCallback((req: { url: string; headers: Record<string, string>; body?: string; method?: string }) => {
    if (credentials) {
      const authCredentials = btoa(`${credentials.username}:${credentials.appPassword}`);
      req.headers["Authorization"] = `Basic ${authCredentials}`;
    }

    addHistoryItem({
      method: req.method || "GET",
      url: req.url,
      status: 0,
      duration: 0,
      timestamp: new Date(),
      requestBody: req.body,
    });

    return req;
  }, [credentials, addHistoryItem]);

  // Response interceptor to track responses
  const responseInterceptor = useCallback((res: Response) => {
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

  const handleRefresh = () => {
    if (apiMode === "backend") {
      fetchBackendSpec();
    } else {
      fetchOpenApiSpec();
    }
  };

  return (
    <>
      <div className="space-y-4 sm:space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold flex items-center gap-2">
              <Globe className="h-5 w-5 sm:h-6 sm:w-6" />
              API Explorer
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
              Browse and test API endpoints with Swagger UI
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 sm:gap-3">
            {/* Auto-refresh toggle */}
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant={autoRefresh ? "secondary" : "outline"}
                    size="sm"
                    onClick={() => setAutoRefresh(prev => !prev)}
                    className="gap-1.5"
                  >
                    {autoRefresh ? <Timer className="h-3.5 w-3.5" /> : <TimerOff className="h-3.5 w-3.5" />}
                    Auto
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  {autoRefresh ? "Auto-refresh every 60s (on)" : "Enable auto-refresh"}
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>

            {lastRefreshed && (
              <span className="text-xs text-muted-foreground hidden sm:inline">
                Updated {lastRefreshed.toLocaleTimeString()}
              </span>
            )}

            <Tabs value={apiMode} onValueChange={(v) => setApiMode(v as ApiMode)}>
              <TabsList>
                <TabsTrigger value="backend" className="gap-1.5">
                  <Server className="h-3.5 w-3.5" />
                  <span className="hidden xs:inline">Backend</span> API
                </TabsTrigger>
                <TabsTrigger value="wordpress" className="gap-1.5">
                  <Globe className="h-3.5 w-3.5" />
                  <span className="hidden xs:inline">WordPress</span> API
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </div>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          {/* Main content */}
          <div className="lg:col-span-2 space-y-6">
            {/* Backend API mode */}
            {apiMode === "backend" && (
              <>
                {error && (
                  <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{error}</AlertDescription>
                  </Alert>
                )}

                {loading && !backendSpec && (
                  <Card>
                    <CardContent className="flex items-center justify-center py-12">
                      <div className="text-center space-y-3">
                        <Loader2 className="h-8 w-8 animate-spin mx-auto text-muted-foreground" />
                        <p className="text-muted-foreground">Loading backend API specification...</p>
                      </div>
                    </CardContent>
                  </Card>
                )}

                {backendSpec && (
                  <Card className="swagger-card overflow-hidden">
                    <CardHeader className="bg-muted/30 border-b">
                      <div className="flex items-center justify-between">
                        <div>
                          <CardTitle className="text-lg flex items-center gap-2">
                            <Server className="h-4 w-4" />
                            Backend API
                          </CardTitle>
                          <CardDescription>
                            Go backend REST API — auto-generated from handler registrations
                          </CardDescription>
                        </div>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={handleRefresh} disabled={loading}>
                            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.open(`${resolveApiBase()}/openapi`, "_blank")}
                          >
                            <ExternalLink className="h-4 w-4 mr-2" />
                            Raw JSON
                          </Button>
                        </div>
                      </div>
                    </CardHeader>
                    <CardContent className="p-0">
                      <div className="swagger-ui-wrapper">
                        <SwaggerUI
                          spec={backendSpec}
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
              </>
            )}

            {/* WordPress API mode */}
            {apiMode === "wordpress" && (
              <>
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
              </>
            )}
          </div>

          {/* Request History Panel */}
          <div className="space-y-6">
            <RequestHistoryPanel
              history={requestHistory}
              onClear={() => setRequestHistory([])}
            />
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
