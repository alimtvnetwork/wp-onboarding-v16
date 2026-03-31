import { useDedupRegistry } from "@/hooks/useDedupRegistry";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { RefreshCw, Trash2, FileJson, AlertCircle } from "lucide-react";

interface DedupRegistryPanelProps {
  siteId: number | null;
}

export function DedupRegistryPanel({ siteId }: DedupRegistryPanelProps) {
  const {
    data,
    isLoading,
    isError,
    error,
    refetch,
    clearRegistry,
    isClearing,
  } = useDedupRegistry(siteId);

  if (!siteId) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <FileJson className="h-4 w-4" />
            Dedup Registry
          </CardTitle>
          <CardDescription>Select a site to view the log deduplication registry.</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2 text-base">
            <FileJson className="h-4 w-4" />
            Dedup Registry
          </CardTitle>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => refetch()}
              disabled={isLoading}
              className="h-8 w-8 p-0"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? "animate-spin" : ""}`} />
            </Button>
            <Button
              variant="destructive"
              size="sm"
              onClick={() => clearRegistry()}
              disabled={isClearing || isLoading}
              className="h-8"
            >
              <Trash2 className="h-3.5 w-3.5 mr-1" />
              {isClearing ? "Clearing…" : "Clear All"}
            </Button>
          </div>
        </div>
        <CardDescription>
          Persistent info-level log deduplication — resets on each deployment.
        </CardDescription>
      </CardHeader>
      <CardContent>
        {isError && (
          <div className="flex items-center gap-2 text-destructive text-sm mb-3">
            <AlertCircle className="h-4 w-4 shrink-0" />
            <span>Failed to load dedup registry{error instanceof Error ? `: ${error.message}` : ""}</span>
            <Button variant="ghost" size="sm" onClick={() => refetch()} className="h-6 text-xs">
              Try again
            </Button>
          </div>
        )}

        {isLoading && (
          <div className="text-muted-foreground text-sm">Loading registry…</div>
        )}

        {data?.data?.plugins && data.data.plugins.length > 0 && (
          <div className="space-y-3">
            {data.data.plugins.map((plugin) => (
              <div
                key={plugin.namespace}
                className="rounded-md border border-border bg-muted/30 p-3"
              >
                <div className="flex items-center justify-between mb-2">
                  <span className="font-medium text-sm">{plugin.label}</span>
                  {!plugin.available ? (
                    <Badge variant="outline" className="text-xs text-muted-foreground">
                      Unavailable
                    </Badge>
                  ) : plugin.dedupRegistry?.Exists ? (
                    <Badge variant="secondary" className="text-xs">
                      {plugin.dedupRegistry.EntryCount} entries
                    </Badge>
                  ) : (
                    <Badge variant="outline" className="text-xs text-muted-foreground">
                      Empty
                    </Badge>
                  )}
                </div>

                {plugin.available && plugin.dedupRegistry?.Exists && (
                  <div className="grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                    <div>
                      <span className="block font-medium text-foreground">Version</span>
                      {plugin.dedupRegistry.Version || "—"}
                    </div>
                    <div>
                      <span className="block font-medium text-foreground">Entries</span>
                      {plugin.dedupRegistry.EntryCount}
                    </div>
                    <div>
                      <span className="block font-medium text-foreground">Size</span>
                      {formatBytes(plugin.dedupRegistry.FileSizeBytes)}
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}

        {data?.plugins && data.plugins.length === 0 && !isLoading && (
          <div className="text-muted-foreground text-sm">No plugin namespaces responded.</div>
        )}
      </CardContent>
    </Card>
  );
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1048576).toFixed(2)} MB`;
}
