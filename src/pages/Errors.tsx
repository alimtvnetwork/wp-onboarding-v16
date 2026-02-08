import { useErrorsPaginated } from "@/hooks/useErrors";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/EmptyState";
import { Badge } from "@/components/ui/badge";
import { AlertCircle, Trash2, Copy, Loader2, CheckCircle, Eye } from "lucide-react";
import { cn } from "@/lib/utils";
import { useState, useMemo } from "react";
import { toast } from "sonner";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import { EnvelopePagination } from "@/components/shared/EnvelopePagination";
import { useErrorStore, type CapturedError } from "@/stores/errorStore";
import { useNotificationStore } from "@/stores/notificationStore";

// Unified error shape for display
interface DisplayError {
  id: number | string;
  code: string;
  level: string;
  message: string;
  details?: string;
  file?: string;
  line?: number;
  function?: string;
  stackTrace?: string;
  createdAt: string;
  source: "api" | "store" | "notification";
  original?: CapturedError;
}

export default function Errors() {
  const [currentPage, setCurrentPage] = useState(1);
  const { data: paginatedResult, isLoading } = useErrorsPaginated(currentPage);
  const apiErrors = paginatedResult?.data;
  const envelopeMeta = paginatedResult?.envelope;
  const [expandedId, setExpandedId] = useState<number | string | null>(null);
  const { data: versionInfo } = useVersionInfo();

  // Zustand stores as fallback sources
  const recentErrors = useErrorStore((s) => s.recentErrors);
  const openErrorModal = useErrorStore((s) => s.openErrorModal);
  const notifications = useNotificationStore((s) => s.notifications);

  const appName = versionInfo?.appName || "WP Plugin Publish";
  const appVersion = versionInfo?.version || "0.0.0";

  // Merge all error sources into a unified list
  const displayErrors = useMemo<DisplayError[]>(() => {
    // 1. API errors (primary)
    if (apiErrors && apiErrors.length > 0) {
      return apiErrors.map((e) => ({
        ...e,
        source: "api" as const,
      }));
    }

    // 2. Fallback: Zustand error store + error notifications
    const storeErrors: DisplayError[] = recentErrors.map((e) => ({
      id: e.id,
      code: e.code,
      level: e.level,
      message: e.message,
      details: e.details,
      file: e.file,
      line: e.line,
      function: e.function,
      stackTrace: e.stackTrace,
      createdAt: e.createdAt,
      source: "store" as const,
      original: e,
    }));

    const errorNotifications: DisplayError[] = notifications
      .filter((n) => n.type === "error")
      .map((n) => ({
        id: n.id,
        code: "NOTIF",
        level: "error",
        message: n.title,
        details: n.description,
        createdAt: n.timestamp,
        source: "notification" as const,
      }));

    // Deduplicate by message+timestamp proximity
    const all = [...storeErrors, ...errorNotifications];
    const seen = new Set<string>();
    return all.filter((e) => {
      const key = `${e.message}-${e.createdAt.slice(0, 16)}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    }).sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());
  }, [apiErrors, recentErrors, notifications]);

  const hasApiErrors = apiErrors && apiErrors.length > 0;

  const levelColors: Record<string, string> = {
    error: "bg-destructive/10 text-destructive border-destructive/20",
    warn: "bg-warning/10 text-warning border-warning/20",
    info: "bg-info/10 text-info border-info/20",
  };

  const levelIconColors: Record<string, string> = {
    error: "text-destructive",
    warn: "text-warning",
    info: "text-info",
  };

  const copyToClipboard = (error: DisplayError) => {
    const text = `## Error Report

**App:** ${appName} v${appVersion}

**Code:** ${error.code}
**Level:** ${error.level}
**Message:** ${error.message}
${error.details ? `**Details:** ${error.details}` : ""}
${error.file ? `**Location:** ${error.file}:${error.line} (${error.function})` : ""}
${error.stackTrace ? `\n**Stack Trace:**\n\`\`\`\n${error.stackTrace}\n\`\`\`` : ""}
`;
    navigator.clipboard.writeText(text);
    toast.success("Copied to clipboard");
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold">Error Console</h1>
          <p className="text-muted-foreground">
            View and debug application errors
          </p>
        </div>
        <Button variant="outline" disabled={!displayErrors.length}>
          <Trash2 className="h-4 w-4 mr-2" />
          Clear All
        </Button>
      </div>

      {/* Source indicator when using fallback */}
      {!hasApiErrors && displayErrors.length > 0 && (
        <div className="flex items-center gap-2 text-xs text-muted-foreground bg-muted/50 px-3 py-2 rounded-md">
          <AlertCircle className="h-3.5 w-3.5" />
          Showing {displayErrors.length} error(s) from local session — backend API unavailable
        </div>
      )}

      {displayErrors.length === 0 ? (
        <EmptyState
          icon={CheckCircle}
          title="No errors"
          description="Your application is running smoothly with no errors."
        />
      ) : (
        <div className="space-y-3">
          {displayErrors.map((error) => (
            <Card key={error.id} className="transition-colors hover:border-primary/30">
              <CardHeader
                className="pb-2 cursor-pointer"
                onClick={() =>
                  setExpandedId(expandedId === error.id ? null : error.id)
                }
              >
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <AlertCircle
                      className={cn(
                        "h-5 w-5",
                        levelIconColors[error.level] || "text-muted-foreground"
                      )}
                    />
                    <div>
                      <div className="flex items-center gap-2">
                        <Badge
                          variant="outline"
                          className={levelColors[error.level] || ""}
                        >
                          {error.code}
                        </Badge>
                        {error.source !== "api" && (
                          <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-muted-foreground border-muted-foreground/30">
                            {error.source === "store" ? "captured" : "notification"}
                          </Badge>
                        )}
                        <span className="text-sm text-muted-foreground">
                          {new Date(error.createdAt).toLocaleString()}
                        </span>
                      </div>
                      <CardTitle className="text-base mt-1">
                        {error.message}
                      </CardTitle>
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    {error.original && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                          e.stopPropagation();
                          openErrorModal(error.original!);
                        }}
                        title="Open in Error Modal"
                      >
                        <Eye className="h-4 w-4" />
                      </Button>
                    )}
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={(e) => {
                        e.stopPropagation();
                        copyToClipboard(error);
                      }}
                    >
                      <Copy className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </CardHeader>

              {expandedId === error.id && (
                <CardContent className="pt-0 space-y-3">
                  {error.details && (
                    <div>
                      <p className="text-xs font-medium text-muted-foreground mb-1">
                        Details
                      </p>
                      <p className="text-sm">{error.details}</p>
                    </div>
                  )}

                  {error.file && (
                    <div>
                      <p className="text-xs font-medium text-muted-foreground mb-1">
                        Location
                      </p>
                      <code className="text-sm bg-muted px-2 py-1 rounded">
                        {error.file}:{error.line} ({error.function})
                      </code>
                    </div>
                  )}

                  {error.stackTrace && (
                    <div>
                      <p className="text-xs font-medium text-muted-foreground mb-1">
                        Stack Trace
                      </p>
                      <pre className="text-xs bg-muted p-3 rounded overflow-x-auto whitespace-pre-wrap">
                        {error.stackTrace}
                      </pre>
                    </div>
                  )}
                </CardContent>
              )}
            </Card>
          ))}
        </div>
      )}

      {/* Envelope Pagination — only when API data is available */}
      {hasApiErrors && (
        <EnvelopePagination
          meta={envelopeMeta ? { attributes: envelopeMeta.attributes, navigation: envelopeMeta.navigation } : null}
          onPageChange={setCurrentPage}
        />
      )}
    </div>
  );
}
