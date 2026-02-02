import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { EmptyState } from "@/components/shared/EmptyState";
import {
  Play,
  StopCircle,
  Trash2,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Clock,
  Loader2,
  ChevronDown,
  ChevronRight,
  FlaskConical,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api } from "@/lib/api";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { toast } from "sonner";
import { ErrorDetailModal } from "@/components/errors/ErrorDetailModal";

interface TestSuite {
  id: string;
  name: string;
  category: string;
  enabled: boolean;
  timeoutSeconds: number;
  caseCount: number;
}

interface TestCase {
  id: string;
  suiteId: string;
  name: string;
  description: string;
  steps: string[];
  expectedResult: string;
}

interface TestRun {
  id: string;
  startedAt: string;
  completedAt?: string;
  status: "running" | "passed" | "failed" | "aborted";
  totalTests: number;
  passedTests: number;
  failedTests: number;
  skippedTests: number;
  durationMs: number;
}

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

interface RunSummary {
  run: TestRun;
  results: TestResult[];
}

export default function Tests() {
  const queryClient = useQueryClient();
  const [selectedSuites, setSelectedSuites] = useState<string[]>([]);
  const [expandedRun, setExpandedRun] = useState<string | null>(null);
  const [selectedError, setSelectedError] = useState<TestResult | null>(null);

  // Fetch test suites
  const { data: suites, isLoading: suitesLoading } = useQuery({
    queryKey: ["e2e", "suites"],
    queryFn: async () => {
      const response = await api.getE2ESuites();
      if (!response.success) throw new Error(response.error?.message);
      return response.data as TestSuite[];
    },
  });

  // Fetch past runs
  const { data: runs, isLoading: runsLoading } = useQuery({
    queryKey: ["e2e", "runs"],
    queryFn: async () => {
      const response = await api.getE2ERuns();
      if (!response.success) throw new Error(response.error?.message);
      return response.data as TestRun[];
    },
  });

  // Start test run mutation
  const startRun = useMutation({
    mutationFn: async (suites: string[]) => {
      const response = await api.startE2ERun({ suites, parallel: false, stopOnFailure: false });
      if (!response.success) throw new Error(response.error?.message);
      return response.data;
    },
    onSuccess: () => {
      toast.success("Test run started");
      queryClient.invalidateQueries({ queryKey: ["e2e", "runs"] });
    },
    onError: (error) => {
      toast.error(`Failed to start: ${error.message}`);
    },
  });

  // Abort test run mutation
  const abortRun = useMutation({
    mutationFn: async (runId: string) => {
      const response = await api.abortE2ERun(runId);
      if (!response.success) throw new Error(response.error?.message);
      return response.data;
    },
    onSuccess: () => {
      toast.success("Test run aborted");
      queryClient.invalidateQueries({ queryKey: ["e2e", "runs"] });
    },
  });

  // Delete run mutation
  const deleteRun = useMutation({
    mutationFn: async (runId: string) => {
      const response = await api.deleteE2ERun(runId);
      if (!response.success) throw new Error(response.error?.message);
    },
    onSuccess: () => {
      toast.success("Run deleted");
      queryClient.invalidateQueries({ queryKey: ["e2e", "runs"] });
    },
  });

  // Fetch run details
  const { data: runDetails } = useQuery({
    queryKey: ["e2e", "runs", expandedRun],
    queryFn: async () => {
      if (!expandedRun) return null;
      const response = await api.getE2ERun(expandedRun);
      if (!response.success) throw new Error(response.error?.message);
      return response.data as RunSummary;
    },
    enabled: !!expandedRun,
  });

  const toggleSuite = (id: string) => {
    setSelectedSuites((prev) =>
      prev.includes(id) ? prev.filter((s) => s !== id) : [...prev, id]
    );
  };

  const runningRun = runs?.find((r) => r.status === "running");

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "passed":
        return <CheckCircle2 className="h-4 w-4 text-green-500" />;
      case "failed":
        return <XCircle className="h-4 w-4 text-red-500" />;
      case "running":
        return <Loader2 className="h-4 w-4 text-blue-500 animate-spin" />;
      case "aborted":
        return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
      case "skipped":
        return <Clock className="h-4 w-4 text-muted-foreground" />;
      default:
        return null;
    }
  };

  const getStatusBadge = (status: string) => {
    const variants: Record<string, string> = {
      passed: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
      failed: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
      running: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
      aborted: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
    };
    return (
      <Badge variant="secondary" className={variants[status] || ""}>
        {status}
      </Badge>
    );
  };

  if (suitesLoading || runsLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold">E2E Tests</h1>
          <p className="text-muted-foreground">
            Run end-to-end tests against real WordPress sites
          </p>
        </div>
        <div className="flex gap-2">
          {runningRun ? (
            <Button
              variant="destructive"
              onClick={() => abortRun.mutate(runningRun.id)}
              disabled={abortRun.isPending}
            >
              <StopCircle className="h-4 w-4 mr-2" />
              Abort Run
            </Button>
          ) : (
            <Button
              onClick={() => startRun.mutate(selectedSuites)}
              disabled={startRun.isPending}
            >
              <Play className="h-4 w-4 mr-2" />
              {selectedSuites.length > 0
                ? `Run ${selectedSuites.length} Suite(s)`
                : "Run All Tests"}
            </Button>
          )}
        </div>
      </div>

      {/* Test Suites */}
      <Card>
        <CardHeader>
          <CardTitle>Test Suites</CardTitle>
          <CardDescription>
            Select suites to run, or run all with no selection
          </CardDescription>
        </CardHeader>
        <CardContent>
          {!suites?.length ? (
            <EmptyState
              icon={FlaskConical}
              title="No test suites"
              description="Test suites will appear here when configured"
            />
          ) : (
            <div className="grid gap-3 md:grid-cols-2">
              {suites.map((suite) => (
                <div
                  key={suite.id}
                  onClick={() => toggleSuite(suite.id)}
                  className={cn(
                    "p-4 rounded-lg border cursor-pointer transition-colors",
                    selectedSuites.includes(suite.id)
                      ? "border-primary bg-primary/5"
                      : "border-border hover:border-primary/50"
                  )}
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="font-medium">{suite.name}</h3>
                      <p className="text-sm text-muted-foreground">
                        {suite.caseCount} test cases
                      </p>
                    </div>
                    <Badge variant="outline">{suite.category}</Badge>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Running Test Progress */}
      {runningRun && (
        <Card className="border-blue-500/50">
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center gap-2">
                <Loader2 className="h-5 w-5 animate-spin text-blue-500" />
                Test Run in Progress
              </CardTitle>
              <span className="text-sm text-muted-foreground">
                {runningRun.passedTests + runningRun.failedTests} / {runningRun.totalTests}
              </span>
            </div>
          </CardHeader>
          <CardContent>
            <Progress
              value={
                ((runningRun.passedTests + runningRun.failedTests) /
                  runningRun.totalTests) *
                100
              }
              className="h-2"
            />
            <div className="flex gap-4 mt-3 text-sm">
              <span className="text-green-600">✓ {runningRun.passedTests} passed</span>
              <span className="text-red-600">✗ {runningRun.failedTests} failed</span>
              <span className="text-muted-foreground">
                ○ {runningRun.skippedTests} skipped
              </span>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Past Runs */}
      <Card>
        <CardHeader>
          <CardTitle>Test History</CardTitle>
          <CardDescription>View past test runs and results</CardDescription>
        </CardHeader>
        <CardContent>
          {!runs?.length ? (
            <EmptyState
              icon={FlaskConical}
              title="No test runs"
              description="Run your first test to see results here"
            />
          ) : (
            <div className="space-y-2">
              {runs
                .filter((r) => r.status !== "running")
                .map((run) => (
                  <Collapsible
                    key={run.id}
                    open={expandedRun === run.id}
                    onOpenChange={() =>
                      setExpandedRun(expandedRun === run.id ? null : run.id)
                    }
                  >
                    <CollapsibleTrigger asChild>
                      <div className="flex items-center justify-between p-3 rounded-lg border cursor-pointer hover:bg-muted/50">
                        <div className="flex items-center gap-3">
                          {expandedRun === run.id ? (
                            <ChevronDown className="h-4 w-4" />
                          ) : (
                            <ChevronRight className="h-4 w-4" />
                          )}
                          {getStatusIcon(run.status)}
                          <div>
                            <span className="font-medium">{run.id}</span>
                            <span className="text-sm text-muted-foreground ml-2">
                              {new Date(run.startedAt).toLocaleString()}
                            </span>
                          </div>
                        </div>
                        <div className="flex items-center gap-3">
                          <div className="text-sm">
                            <span className="text-green-600">{run.passedTests}</span>
                            <span className="text-muted-foreground"> / </span>
                            <span className="text-red-600">{run.failedTests}</span>
                            <span className="text-muted-foreground"> / </span>
                            <span>{run.totalTests}</span>
                          </div>
                          {getStatusBadge(run.status)}
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                              e.stopPropagation();
                              deleteRun.mutate(run.id);
                            }}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      {expandedRun === run.id && runDetails && (
                        <div className="mt-2 ml-8 space-y-1">
                          {runDetails.results.map((result) => (
                            <div
                              key={result.id}
                              className={cn(
                                "flex items-center justify-between p-2 rounded text-sm",
                                result.status === "failed" &&
                                  "bg-red-50 dark:bg-red-900/20 cursor-pointer"
                              )}
                              onClick={() => {
                                if (result.status === "failed") {
                                  setSelectedError(result);
                                }
                              }}
                            >
                              <div className="flex items-center gap-2">
                                {getStatusIcon(result.status)}
                                <span className="font-mono text-xs text-muted-foreground">
                                  {result.caseId}
                                </span>
                                <span>{result.caseName}</span>
                              </div>
                              <span className="text-muted-foreground">
                                {result.durationMs}ms
                              </span>
                            </div>
                          ))}
                        </div>
                      )}
                    </CollapsibleContent>
                  </Collapsible>
                ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Error Detail Modal */}
      {selectedError && (
        <ErrorDetailModal
          open={!!selectedError}
          onOpenChange={() => setSelectedError(null)}
          error={{
            id: parseInt(selectedError.id) || 0,
            code: selectedError.caseId,
            level: "error",
            message: selectedError.errorMessage || "Test failed",
            details: selectedError.errorDetails,
            context: {
              requestData: selectedError.requestData,
              responseData: selectedError.responseData,
            },
            stackTrace: selectedError.logs,
            createdAt: new Date().toISOString(),
          }}
        />
      )}
    </div>
  );
}
