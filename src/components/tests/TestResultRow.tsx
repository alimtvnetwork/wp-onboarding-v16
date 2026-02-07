import { Button } from "@/components/ui/button";
import {
  CheckCircle2,
  XCircle,
  Loader2,
  Clock,
  AlertTriangle,
  Play,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface TestResult {
  id: string;
  runId: string;
  suiteId: string;
  caseId: string;
  caseName: string;
  status: "passed" | "failed" | "skipped" | "error";
  durationMs: number;
  errorMessage?: string;
  errorDetails?: string;
  requestData?: string;
  responseData?: string;
  logs?: string;
}

interface TestResultRowProps {
  result: TestResult;
  onViewError?: () => void;
  onRerun?: () => void;
  isRerunning?: boolean;
}

function getStatusIcon(status: string) {
  switch (status) {
    case "passed":
      return <CheckCircle2 className="h-4 w-4 text-emerald-500 dark:text-emerald-400" />;
    case "failed":
      return <XCircle className="h-4 w-4 text-destructive" />;
    case "running":
      return <Loader2 className="h-4 w-4 text-primary animate-spin" />;
    case "skipped":
      return <Clock className="h-4 w-4 text-muted-foreground" />;
    case "error":
      return <AlertTriangle className="h-4 w-4 text-amber-500 dark:text-amber-400" />;
    default:
      return null;
  }
}

export function TestResultRow({ result, onViewError, onRerun, isRerunning }: TestResultRowProps) {
  const isFailed = result.status === "failed" || result.status === "error";

  return (
    <div
      className={cn(
        "flex items-center justify-between p-2 rounded text-sm group",
        isFailed && "bg-destructive/10 cursor-pointer"
      )}
      onClick={() => {
        if (isFailed && onViewError) onViewError();
      }}
    >
      <div className="flex items-center gap-2 min-w-0">
        {getStatusIcon(result.status)}
        <span className="font-mono text-xs text-muted-foreground">
          {result.caseId}
        </span>
        <span className="truncate">{result.caseName}</span>
        {isFailed && result.errorMessage && (
          <span className="text-xs text-destructive truncate max-w-[200px] hidden sm:inline">
            — {result.errorMessage}
          </span>
        )}
      </div>
      <div className="flex items-center gap-2">
        <span className="text-muted-foreground">{result.durationMs}ms</span>
        {onRerun && (
          <Button
            variant="ghost"
            size="icon"
            className="h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
            disabled={isRerunning}
            onClick={(e) => {
              e.stopPropagation();
              onRerun();
            }}
            title="Rerun this test"
          >
            {isRerunning ? (
              <Loader2 className="h-3 w-3 animate-spin" />
            ) : (
              <Play className="h-3 w-3" />
            )}
          </Button>
        )}
      </div>
    </div>
  );
}
