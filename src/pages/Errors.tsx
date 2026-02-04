import { useErrors } from "@/hooks/useErrors";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/EmptyState";
import { Badge } from "@/components/ui/badge";
import { AlertCircle, Trash2, Copy, Loader2, CheckCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { useState } from "react";
import { toast } from "sonner";
import { useVersionInfo } from "@/hooks/useWhatsNew";

export default function Errors() {
  const { data: errors, isLoading } = useErrors();
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const { data: versionInfo } = useVersionInfo();

  const appName = versionInfo?.appName || "WP Plugin Publish";
  const appVersion = versionInfo?.version || "0.0.0";

  const copyToClipboard = (error: typeof errors extends (infer T)[] ? T : never) => {
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

  const levelColors = {
    error: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
    warn: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
    info: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
  };

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold">Error Console</h1>
          <p className="text-muted-foreground">
            View and debug application errors
          </p>
        </div>
        <Button variant="outline" disabled={!errors?.length}>
          <Trash2 className="h-4 w-4 mr-2" />
          Clear All
        </Button>
      </div>

      {!errors?.length ? (
        <EmptyState
          icon={CheckCircle}
          title="No errors"
          description="Your application is running smoothly with no errors."
        />
      ) : (
        <div className="space-y-3">
          {errors.map((error) => (
            <Card key={error.id}>
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
                        error.level === "error"
                          ? "text-red-500"
                          : error.level === "warn"
                          ? "text-yellow-500"
                          : "text-blue-500"
                      )}
                    />
                    <div>
                      <div className="flex items-center gap-2">
                        <Badge
                          variant="secondary"
                          className={
                            levelColors[
                              error.level as keyof typeof levelColors
                            ] || ""
                          }
                        >
                          {error.code}
                        </Badge>
                        <span className="text-sm text-muted-foreground">
                          {new Date(error.createdAt).toLocaleString()}
                        </span>
                      </div>
                      <CardTitle className="text-base mt-1">
                        {error.message}
                      </CardTitle>
                    </div>
                  </div>
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
                      <pre className="text-xs bg-muted p-3 rounded overflow-x-auto">
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
    </div>
  );
}
