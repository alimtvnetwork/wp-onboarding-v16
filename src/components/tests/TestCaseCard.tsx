import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  CheckCircle2,
  XCircle,
  Loader2,
  Clock,
  Play,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface TestCase {
  id: string;
  suiteId: string;
  name: string;
  description: string;
  steps: string[];
  expectedResult: string;
}

interface TestCaseCardProps {
  testCase: TestCase;
  selected: boolean;
  onToggle: () => void;
  lastStatus?: "passed" | "failed" | "skipped" | "error" | "running";
  lastDurationMs?: number;
  onRerun?: () => void;
  isRerunning?: boolean;
}

function getStatusIndicator(status?: string) {
  switch (status) {
    case "passed":
      return <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500 dark:text-emerald-400" />;
    case "failed":
    case "error":
      return <XCircle className="h-3.5 w-3.5 text-destructive" />;
    case "running":
      return <Loader2 className="h-3.5 w-3.5 text-primary animate-spin" />;
    case "skipped":
      return <Clock className="h-3.5 w-3.5 text-muted-foreground" />;
    default:
      return null;
  }
}

export function TestCaseCard({
  testCase,
  selected,
  onToggle,
  lastStatus,
  lastDurationMs,
  onRerun,
  isRerunning,
}: TestCaseCardProps) {
  return (
    <Card
      className={cn(
        "cursor-pointer transition-colors hover:bg-secondary/30 relative",
        selected
          ? "border-primary ring-2 ring-primary/20"
          : "border-border hover:border-primary/50"
      )}
      onClick={onToggle}
    >
      <CardHeader className="pb-2">
        <div className="flex items-start justify-between gap-2">
          <div className="flex items-start gap-3">
            <Checkbox
              checked={selected}
              onClick={(e) => e.stopPropagation()}
              onCheckedChange={onToggle}
              className="mt-0.5"
            />
            <div>
              <CardTitle className="text-sm font-medium flex items-center gap-2">
                {testCase.name}
                {getStatusIndicator(lastStatus)}
              </CardTitle>
              <Badge variant="outline" className="text-xs mt-1 font-mono">
                {testCase.id}
              </Badge>
            </div>
          </div>
          {onRerun && (
            <Button
              variant="ghost"
              size="icon"
              className="h-7 w-7 shrink-0"
              disabled={isRerunning}
              onClick={(e) => {
                e.stopPropagation();
                onRerun();
              }}
              title="Rerun this test"
            >
              {isRerunning ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
              ) : (
                <Play className="h-3.5 w-3.5" />
              )}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        <p className="text-xs text-muted-foreground line-clamp-2">
          {testCase.description}
        </p>
        <div className="flex items-center justify-between mt-2 text-xs text-muted-foreground">
          <span>
            {testCase.steps.length} step{testCase.steps.length !== 1 ? "s" : ""}
          </span>
          {lastDurationMs !== undefined && lastStatus && lastStatus !== "running" && (
            <span>{lastDurationMs}ms</span>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
