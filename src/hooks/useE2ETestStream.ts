import { useEffect, useState, useCallback } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { E2ECaseStatusValues } from "@/lib/constants";
import type { E2ECaseStatus } from "@/lib/api/types";

export interface LiveTestResult {
  caseId: string;
  caseName: string;
  suiteId: string;
  status: E2ECaseStatus | "Error";
  durationMs: number;
  errorMessage?: string;
}

export interface E2ERunProgress {
  runId: string;
  totalTests: number;
  completedTests: number;
  passedTests: number;
  failedTests: number;
  skippedTests: number;
  currentTest?: string;
}

export function useE2ETestStream() {
  const queryClient = useQueryClient();
  const [liveResults, setLiveResults] = useState<LiveTestResult[]>([]);
  const [progress, setProgress] = useState<E2ERunProgress | null>(null);
  const [isStreaming, setIsStreaming] = useState(false);

  const reset = useCallback(() => {
    setLiveResults([]);
    setProgress(null);
    setIsStreaming(false);
  }, []);

  useEffect(() => {
    const unsubStarted = wsClient.on(WS_EVENTS.E2E_TEST_STARTED, (data: unknown) => {
      const d = data as E2ERunProgress;
      setIsStreaming(true);
      setLiveResults([]);
      setProgress(d);
    });

    const unsubResult = wsClient.on(WS_EVENTS.E2E_TEST_RESULT, (data: unknown) => {
      const result = data as LiveTestResult;
      setLiveResults((prev) => {
        const existing = prev.findIndex((r) => r.caseId === result.caseId);
        if (existing >= 0) {
          const updated = [...prev];
          updated[existing] = result;
          return updated;
        }
        return [...prev, result];
      });
      setProgress((prev) =>
        prev
          ? {
              ...prev,
              completedTests: (prev.completedTests || 0) + 1,
              passedTests: prev.passedTests + (result.status === E2ECaseStatusValues.Passed ? 1 : 0),
              failedTests: prev.failedTests + (result.status === E2ECaseStatusValues.Failed || result.status === "Error" ? 1 : 0),
              skippedTests: prev.skippedTests + (result.status === E2ECaseStatusValues.Skipped ? 1 : 0),
              currentTest: result.caseName,
            }
          : prev
      );
    });

    const unsubComplete = wsClient.on(WS_EVENTS.E2E_TEST_COMPLETE, () => {
      setIsStreaming(false);
      queryClient.invalidateQueries({ queryKey: ["e2e", "runs"] });
    });

    return () => {
      unsubStarted();
      unsubResult();
      unsubComplete();
    };
  }, [queryClient]);

  return { liveResults, progress, isStreaming, reset };
}
