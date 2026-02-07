import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
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
  Plug,
  Globe,
  RefreshCw,
  Upload,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api } from "@/lib/api";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { toast } from "sonner";
import { ErrorDetailModal } from "@/components/errors/ErrorDetailModal";
import { TestCaseCard } from "@/components/tests/TestCaseCard";
import { TestResultRow } from "@/components/tests/TestResultRow";
import { LiveTestProgress } from "@/components/tests/LiveTestProgress";
import { useE2ETestStream } from "@/hooks/useE2ETestStream";

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

// Hardcoded test cases from spec for display
const testCasesData: Record<string, TestCase[]> = {
  "plugin-crud": [
    { id: "TC-PLUGIN-001", suiteId: "plugin-crud", name: "Register Plugin", description: "Register a new plugin from local directory", steps: ["Call POST /plugins with valid path", "Verify response contains plugin data", "Verify plugin appears in GET /plugins"], expectedResult: "Plugin created successfully" },
    { id: "TC-PLUGIN-002", suiteId: "plugin-crud", name: "Register Invalid Path", description: "Attempt to register non-existent path", steps: ["Call POST /plugins with invalid path", "Verify error response with E3002"], expectedResult: "Error E3002 returned" },
    { id: "TC-PLUGIN-003", suiteId: "plugin-crud", name: "Update Plugin", description: "Update plugin settings", steps: ["Create plugin", "Call PUT /plugins/{id}", "Verify updated fields"], expectedResult: "Plugin updated" },
    { id: "TC-PLUGIN-004", suiteId: "plugin-crud", name: "Delete Plugin", description: "Delete plugin registration", steps: ["Create plugin", "Call DELETE /plugins/{id}", "Verify 404 on GET /plugins/{id}"], expectedResult: "Plugin deleted" },
    { id: "TC-PLUGIN-005", suiteId: "plugin-crud", name: "Scan Plugin Files", description: "Scan local plugin directory", steps: ["Create plugin", "Call POST /watcher/scan/{id}", "Verify file count returned"], expectedResult: "Files scanned" },
  ],
  "site-connections": [
    { id: "TC-SITE-001", suiteId: "site-connections", name: "Register Site", description: "Register a WordPress site", steps: ["Call POST /sites with valid credentials", "Verify response contains site data", "Verify site appears in GET /sites"], expectedResult: "Site created" },
    { id: "TC-SITE-002", suiteId: "site-connections", name: "Test Connection", description: "Test WP REST API connectivity", steps: ["Create site", "Call POST /sites/{id}/test", "Verify success response with WP version"], expectedResult: "Connection verified" },
    { id: "TC-SITE-003", suiteId: "site-connections", name: "Invalid Credentials", description: "Test with bad credentials", steps: ["Create site with invalid password", "Call POST /sites/{id}/test", "Verify error response"], expectedResult: "Auth error returned" },
    { id: "TC-SITE-004", suiteId: "site-connections", name: "Create Plugin Mapping", description: "Map plugin to site", steps: ["Create plugin", "Create site", "Call POST /plugins/{id}/mappings", "Verify mapping created"], expectedResult: "Mapping created" },
  ],
  "sync-operations": [
    { id: "TC-SYNC-001", suiteId: "sync-operations", name: "Detect New Files", description: "Detect newly added files", steps: ["Create plugin", "Add file to plugin directory", "Trigger scan", "Verify 'added' status in changes"], expectedResult: "New files detected" },
    { id: "TC-SYNC-002", suiteId: "sync-operations", name: "Detect Modified Files", description: "Detect file modifications", steps: ["Create plugin with existing file", "Modify file content", "Trigger scan", "Verify 'modified' status"], expectedResult: "Modified files detected" },
    { id: "TC-SYNC-003", suiteId: "sync-operations", name: "Detect Deleted Files", description: "Detect removed files", steps: ["Create plugin with file", "Delete file", "Trigger scan", "Verify 'deleted' status"], expectedResult: "Deleted files detected" },
    { id: "TC-SYNC-004", suiteId: "sync-operations", name: "Compare Local/Remote", description: "Compare hashes with remote", steps: ["Create plugin and site mapping", "Call POST /plugins/{id}/sites/{siteId}/sync", "Verify changedFiles count"], expectedResult: "Differences listed" },
    { id: "TC-SYNC-005", suiteId: "sync-operations", name: "Git Pull Detection", description: "Detect changes after git pull", steps: ["Create git-enabled plugin", "Call POST /git/pull/{id}", "Verify scan triggered automatically"], expectedResult: "Scan triggered" },
    { id: "TC-SYNC-006", suiteId: "sync-operations", name: "Batch Scan All", description: "Scan all plugins at once", steps: ["Create multiple plugins", "Call POST /watcher/scan-all", "Verify results for each plugin"], expectedResult: "All plugins scanned" },
  ],
  "publish-flow": [
    { id: "TC-PUBLISH-001", suiteId: "publish-flow", name: "Full ZIP Upload", description: "Upload complete plugin as ZIP", steps: ["Create plugin and mapping", "Call POST /plugins/{id}/sites/{siteId}/publish with mode=full", "Verify filesUpdated count"], expectedResult: "Full upload success" },
    { id: "TC-PUBLISH-002", suiteId: "publish-flow", name: "Selected Files Patch", description: "Upload only changed files", steps: ["Create plugin with changes", "Call publish with mode=selected, files=[...]", "Verify only selected files updated"], expectedResult: "Partial update success" },
    { id: "TC-PUBLISH-003", suiteId: "publish-flow", name: "Backup Before Publish", description: "Create backup before publishing", steps: ["Call publish with createBackup=true", "Verify backupId in response", "Verify backup file exists"], expectedResult: "Backup created" },
    { id: "TC-PUBLISH-004", suiteId: "publish-flow", name: "Restore From Backup", description: "Restore plugin from backup", steps: ["Create backup", "Call POST /backups/{id}/restore", "Verify restore success"], expectedResult: "Restore completed" },
    { id: "TC-PUBLISH-005", suiteId: "publish-flow", name: "Publish All Sites", description: "Publish to all mapped sites", steps: ["Create plugin with multiple mappings", "Call batch publish endpoint", "Verify all sites updated"], expectedResult: "All sites published" },
  ],
};

const categoryLabels: Record<string, string> = {
  "plugin-crud": "Plugin CRUD",
  "site-connections": "Site Connections",
  "sync-operations": "Sync Operations",
  "publish-flow": "Publish Flow",
};

export default function Tests() {
  const queryClient = useQueryClient();
  const [selectedCases, setSelectedCases] = useState<string[]>([]);
  const [expandedRun, setExpandedRun] = useState<string | null>(null);
  const [selectedError, setSelectedError] = useState<TestResult | null>(null);
  const [activeTab, setActiveTab] = useState("plugin-crud");
  const [rerunningCase, setRerunningCase] = useState<string | null>(null);

  const { liveResults, progress, isStreaming } = useE2ETestStream();

  // Build a map of caseId -> last known status from live results
  const liveStatusMap = new Map(
    liveResults.map((r) => [r.caseId, { status: r.status, durationMs: r.durationMs }])
  );

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
    mutationFn: async (cases: string[]) => {
      const response = await api.startE2ERun({ suites: [], cases, parallel: false, stopOnFailure: false });
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

  // Rerun single test case
  const rerunCase = useMutation({
    mutationFn: async (caseId: string) => {
      setRerunningCase(caseId);
      const response = await api.rerunE2ECase(caseId);
      if (!response.success) throw new Error(response.error?.message);
      return response.data;
    },
    onSuccess: () => {
      toast.success("Test rerun started");
      queryClient.invalidateQueries({ queryKey: ["e2e", "runs"] });
    },
    onError: (error) => {
      toast.error(`Rerun failed: ${error.message}`);
    },
    onSettled: () => {
      setRerunningCase(null);
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

  // Build last-result map from most recent completed run
  const lastRunResults = new Map<string, { status: string; durationMs: number }>();
  if (runs?.length) {
    const lastCompleted = runs.find((r) => r.status !== "running");
    if (lastCompleted && expandedRun === lastCompleted.id && runDetails?.results) {
      runDetails.results.forEach((r) => {
        lastRunResults.set(r.caseId, { status: r.status, durationMs: r.durationMs });
      });
    }
  }

  const toggleCase = (id: string) => {
    setSelectedCases((prev) =>
      prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id]
    );
  };

  const toggleAllInCategory = (category: string, checked: boolean) => {
    const categoryTests = testCasesData[category] || [];
    if (checked) {
      setSelectedCases((prev) => [...new Set([...prev, ...categoryTests.map(t => t.id)])]);
    } else {
      const categoryIds = categoryTests.map(t => t.id);
      setSelectedCases((prev) => prev.filter(id => !categoryIds.includes(id)));
    }
  };

  const runningRun = runs?.find((r) => r.status === "running");

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "passed":
        return <CheckCircle2 className="h-4 w-4 text-emerald-500 dark:text-emerald-400" />;
      case "failed":
        return <XCircle className="h-4 w-4 text-destructive" />;
      case "running":
        return <Loader2 className="h-4 w-4 text-primary animate-spin" />;
      case "aborted":
        return <AlertTriangle className="h-4 w-4 text-amber-500 dark:text-amber-400" />;
      case "skipped":
        return <Clock className="h-4 w-4 text-muted-foreground" />;
      default:
        return null;
    }
  };

  const getStatusBadge = (status: string) => {
    const variants: Record<string, string> = {
      passed: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400",
      failed: "bg-destructive/10 text-destructive",
      running: "bg-primary/10 text-primary",
      aborted: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
    };
    return (
      <Badge variant="secondary" className={variants[status] || ""}>
        {status}
      </Badge>
    );
  };

  const currentCategoryTests = testCasesData[activeTab] || [];
  const selectedInCategory = currentCategoryTests.filter(t => selectedCases.includes(t.id)).length;
  const allSelectedInCategory = selectedInCategory === currentCategoryTests.length;

  if (runsLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Action Bar */}
      <div className="flex justify-between items-center">
        <div className="flex items-center gap-2">
          <FlaskConical className="h-5 w-5 text-muted-foreground" />
          <span className="text-muted-foreground">
            {selectedCases.length} test{selectedCases.length !== 1 ? "s" : ""} selected
          </span>
        </div>
        <div className="flex gap-2">
          {runningRun || isStreaming ? (
            <Button
              variant="destructive"
              onClick={() => runningRun && abortRun.mutate(runningRun.id)}
              disabled={abortRun.isPending || !runningRun}
            >
              <StopCircle className="h-4 w-4 mr-2" />
              Abort Run
            </Button>
          ) : (
            <Button
              onClick={() => startRun.mutate(selectedCases)}
              disabled={startRun.isPending}
            >
              <Play className="h-4 w-4 mr-2" />
              {selectedCases.length > 0
                ? `Run ${selectedCases.length} Test(s)`
                : "Run All Tests"}
            </Button>
          )}
        </div>
      </div>

      {/* Live Test Progress (WebSocket-streamed) */}
      {isStreaming && progress && (
        <LiveTestProgress
          progress={progress}
          liveResults={liveResults}
          onAbort={runningRun ? () => abortRun.mutate(runningRun.id) : undefined}
          isAborting={abortRun.isPending}
        />
      )}

      {/* Fallback: Running Test Progress (polling-based) */}
      {!isStreaming && runningRun && (
        <Card className="border-primary/50">
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center gap-2">
                <Loader2 className="h-5 w-5 animate-spin text-primary" />
                Test Run in Progress
              </CardTitle>
              <span className="text-sm text-muted-foreground">
                {runningRun.passedTests + runningRun.failedTests} / {runningRun.totalTests}
              </span>
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex gap-4 mt-1 text-sm">
              <span className="text-emerald-600 dark:text-emerald-400">✓ {runningRun.passedTests} passed</span>
              <span className="text-destructive">✗ {runningRun.failedTests} failed</span>
              <span className="text-muted-foreground">○ {runningRun.skippedTests} skipped</span>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Test Suites Tabs with Cards */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="plugin-crud" className="flex items-center gap-2">
            <Plug className="h-4 w-4" />
            <span className="hidden sm:inline">Plugin CRUD</span>
          </TabsTrigger>
          <TabsTrigger value="site-connections" className="flex items-center gap-2">
            <Globe className="h-4 w-4" />
            <span className="hidden sm:inline">Site Connections</span>
          </TabsTrigger>
          <TabsTrigger value="sync-operations" className="flex items-center gap-2">
            <RefreshCw className="h-4 w-4" />
            <span className="hidden sm:inline">Sync Operations</span>
          </TabsTrigger>
          <TabsTrigger value="publish-flow" className="flex items-center gap-2">
            <Upload className="h-4 w-4" />
            <span className="hidden sm:inline">Publish Flow</span>
          </TabsTrigger>
        </TabsList>

        {Object.entries(testCasesData).map(([category, tests]) => (
          <TabsContent key={category} value={category} className="mt-4">
            {/* Select All for Category */}
            <div className="flex items-center gap-2 mb-4 p-3 bg-muted/50 rounded-lg">
              <Checkbox
                id={`select-all-${category}`}
                checked={allSelectedInCategory}
                onCheckedChange={(checked) => toggleAllInCategory(category, !!checked)}
              />
              <label htmlFor={`select-all-${category}`} className="text-sm font-medium cursor-pointer">
                Select all {categoryLabels[category]} tests ({tests.length})
              </label>
            </div>

            {/* Test Case Cards */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {tests.map((testCase) => {
                const liveStatus = liveStatusMap.get(testCase.id);
                const lastResult = lastRunResults.get(testCase.id);
                const displayStatus = liveStatus?.status || lastResult?.status;
                const displayDuration = liveStatus?.durationMs ?? lastResult?.durationMs;

                return (
                  <TestCaseCard
                    key={testCase.id}
                    testCase={testCase}
                    selected={selectedCases.includes(testCase.id)}
                    onToggle={() => toggleCase(testCase.id)}
                    lastStatus={displayStatus as TestResult["status"] | undefined}
                    lastDurationMs={displayDuration}
                    onRerun={() => rerunCase.mutate(testCase.id)}
                    isRerunning={rerunningCase === testCase.id}
                  />
                );
              })}
            </div>
          </TabsContent>
        ))}
      </Tabs>

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
                            <span className="text-emerald-600 dark:text-emerald-400">{run.passedTests}</span>
                            <span className="text-muted-foreground"> / </span>
                            <span className="text-destructive">{run.failedTests}</span>
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
                            <TestResultRow
                              key={result.id}
                              result={result}
                              onViewError={
                                result.status === "failed" || result.status === "error"
                                  ? () => setSelectedError(result)
                                  : undefined
                              }
                              onRerun={() => rerunCase.mutate(result.caseId)}
                              isRerunning={rerunningCase === result.caseId}
                            />
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
