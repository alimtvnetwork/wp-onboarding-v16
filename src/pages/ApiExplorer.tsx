import { useState, useEffect, useCallback } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Loader2, Globe, Key, RefreshCw, ExternalLink, AlertCircle, CheckCircle2, Lock } from "lucide-react";
import { useSites } from "@/hooks/useSites";
import SwaggerUI from "swagger-ui-react";
import "swagger-ui-react/swagger-ui.css";

export default function ApiExplorer() {
  const { data: sites, isLoading: sitesLoading } = useSites();
  const [selectedSiteId, setSelectedSiteId] = useState<string>("");
  const [appPassword, setAppPassword] = useState<string>("");
  const [spec, setSpec] = useState<object | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [authenticated, setAuthenticated] = useState(false);

  const selectedSite = sites?.find((s) => s.id.toString() === selectedSiteId);

  useEffect(() => {
    setAppPassword("");
    setSpec(null);
    setAuthenticated(false);
    setError(null);
  }, [selectedSiteId]);

  const fetchOpenApiSpec = useCallback(async () => {
    if (!selectedSite || !appPassword) return;

    setLoading(true);
    setError(null);
    setSpec(null);
    setAuthenticated(false);

    try {
      const baseUrl = selectedSite.url.replace(/\/$/, "");
      const openApiUrl = `${baseUrl}/wp-json/riseup-asia-uploader/v1/openapi`;
      const credentials = btoa(`${selectedSite.username}:${appPassword}`);
      
      const response = await fetch(openApiUrl, {
        headers: {
          "Authorization": `Basic ${credentials}`,
        },
      });

      if (!response.ok) {
        if (response.status === 401) {
          throw new Error("Authentication failed. Check your username and application password.");
        } else if (response.status === 404) {
          throw new Error("OpenAPI endpoint not found. Ensure the Riseup Asia Uploader plugin is installed and updated.");
        }
        throw new Error(`Failed to fetch API spec: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      
      data.servers = [
        {
          url: `${baseUrl}/wp-json/riseup-asia-uploader/v1`,
          description: selectedSite.name,
        }
      ];

      setSpec(data);
      setAuthenticated(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to fetch OpenAPI specification");
    } finally {
      setLoading(false);
    }
  }, [selectedSite, appPassword]);

  const requestInterceptor = useCallback((req: { url: string; headers: Record<string, string> }) => {
    if (selectedSite && appPassword) {
      const credentials = btoa(`${selectedSite.username}:${appPassword}`);
      req.headers["Authorization"] = `Basic ${credentials}`;
    }
    return req;
  }, [selectedSite, appPassword]);

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

        <Card>
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <Key className="h-4 w-4" />
              Connect to WordPress Site
            </CardTitle>
            <CardDescription>
              Select a site and enter the application password to authenticate with the API.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
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
              <div>
                <Label htmlFor="app-password">Application Password</Label>
                <Input
                  id="app-password"
                  type="password"
                  placeholder="Enter application password..."
                  value={appPassword}
                  onChange={(e) => setAppPassword(e.target.value)}
                  className="mt-1.5"
                  disabled={!selectedSite}
                />
              </div>
            </div>
            <div className="flex flex-wrap gap-4 items-center">
              <Button
                onClick={fetchOpenApiSpec}
                disabled={!selectedSite || !appPassword || loading}
              >
                {loading ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />
                ) : (
                  <Key className="h-4 w-4 mr-2" />
                )}
                Connect & Load API
              </Button>
              {authenticated && (
                <Button
                  onClick={fetchOpenApiSpec}
                  variant="outline"
                  size="sm"
                >
                  <RefreshCw className="h-4 w-4 mr-2" />
                  Refresh
                </Button>
              )}
              {selectedSite && (
                <div className="flex items-center gap-4 text-sm">
                  <code className="bg-muted px-2 py-0.5 rounded text-xs">{selectedSite.url}</code>
                  <code className="bg-muted px-2 py-0.5 rounded text-xs">{selectedSite.username}</code>
                  {authenticated && (
                    <Badge variant="secondary" className="gap-1">
                      <CheckCircle2 className="h-3 w-3" />
                      Connected
                    </Badge>
                  )}
                </div>
              )}
            </div>
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
                    Choose a site from the dropdown and enter your application password
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
                    const baseUrl = selectedSite?.url.replace(/\/$/, "");
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
