import { useState, useEffect } from "react";
import { api, SessionDiagnostics } from "@/lib/api";

interface SessionDiagnosticsState {
  diagnostics: SessionDiagnostics | null;
  logs: string | null;
  loading: boolean;
  error: string | null;
}

/**
 * Hook to fetch session diagnostics and logs in parallel.
 * Returns null data when no sessionId is provided.
 */
export function useSessionDiagnostics(sessionId?: string) {
  const [state, setState] = useState<SessionDiagnosticsState>({
    diagnostics: null,
    logs: null,
    loading: false,
    error: null,
  });

  const fetchData = async () => {
    if (!sessionId) return;
    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const [logsRes, diagRes] = await Promise.all([
        api.getSessionLogs(sessionId),
        api.getSessionDiagnostics(sessionId),
      ]);

      setState({
        logs: logsRes.success ? logsRes.data?.logs ?? null : null,
        diagnostics: diagRes.success ? diagRes.data ?? null : null,
        loading: false,
        error: (!logsRes.success && !diagRes.success)
          ? (logsRes.error?.message || "Failed to fetch session data")
          : null,
      });
    } catch (err: unknown) {
      setState({
        logs: null,
        diagnostics: null,
        loading: false,
        error: err instanceof Error ? err.message : "Failed to fetch session data",
      });
    }
  };

  useEffect(() => {
    if (sessionId) fetchData();
  }, [sessionId]);

  return { ...state, refetch: fetchData };
}
