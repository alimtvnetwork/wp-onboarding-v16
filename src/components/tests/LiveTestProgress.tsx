import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Button } from "@/components/ui/button";
import {
  CheckCircle2,
  XCircle,
  Loader2,
  Clock,
  StopCircle,
  AlertTriangle,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { E2ECaseStatusValues } from "@/lib/constants";
import type { E2ERunProgress, LiveTestResult } from "@/hooks/useE2ETestStream";

interface LiveTestProgressProps {
  progress: E2ERunProgress;
  liveResults: LiveTestResult[];
  onAbort?: () => void;
  isAborting?: boolean;
}

function getStatusIcon(status: string) {
  switch (status) {
    case "Passed":
      return <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500 dark:text-emerald-400" />;
    case "Failed":
    case "Error":
      return <XCircle className="h-3.5 w-3.5 text-destructive" />;
    case "Running":
      return <Loader2 className="h-3.5 w-3.5 text-primary animate-spin" />;
    case "Skipped":
      return <Clock className="h-3.5 w-3.5 text-muted-foreground" />;
    default:
      return <AlertTriangle className="h-3.5 w-3.5 text-amber-500" />;
  }
}

export function LiveTestProgress({
  progress,
  liveResults,
  onAbort,
  isAborting,
}: LiveTestProgressProps) {
  const completed = progress.completedTests || 0;
  const total = progress.totalTests || 1;
  const percent = Math.round((completed / total) * 100);

  return (
    <Card className="border-primary/50">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2">
            <Loader2 className="h-5 w-5 animate-spin text-primary" />
            Test Run in Progress
          </CardTitle>
          <div className="flex items-center gap-3">
            <span className="text-sm text-muted-foreground">
              {completed} / {total}
            </span>
            {onAbort && (
              <Button
                variant="destructive"
                size="sm"
                onClick={onAbort}
                disabled={isAborting}
              >
                <StopCircle className="h-4 w-4 mr-1" />
                Abort
              </Button>
            )}
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        <Progress value={percent} className="h-2" />
        <div className="flex gap-4 text-sm">
          <span className="text-emerald-600 dark:text-emerald-400">
            ✓ {progress.passedTests} passed
          </span>
          <span className="text-destructive">✗ {progress.failedTests} failed</span>
          <span className="text-muted-foreground">○ {progress.skippedTests} skipped</span>
        </div>

        {/* Live result stream */}
        {liveResults.length > 0 && (
          <div className="mt-2 max-h-48 overflow-y-auto space-y-1 border-t pt-2">
            {liveResults.map((r) => (
              <div
                key={r.caseId}
                className={cn(
                  "flex items-center justify-between px-2 py-1 rounded text-sm",
                  r.status === E2ECaseStatusValues.Failed || r.status === "Error"
                    ? "bg-destructive/10"
                    : r.status === E2ECaseStatusValues.Passed
                    ? "bg-emerald-500/5"
                    : ""
                )}
              >
                <div className="flex items-center gap-2 min-w-0">
                  {getStatusIcon(r.status)}
                  <span className="font-mono text-xs text-muted-foreground">
                    {r.caseId}
                  </span>
                  <span className="truncate">{r.caseName}</span>
                </div>
                {r.status !== "Running" && (
                  <span className="text-xs text-muted-foreground">{r.durationMs}ms</span>
                )}
              </div>
            ))}
          </div>
        )}

        {progress.currentTest && (
          <p className="text-xs text-muted-foreground animate-pulse">
            Running: {progress.currentTest}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
