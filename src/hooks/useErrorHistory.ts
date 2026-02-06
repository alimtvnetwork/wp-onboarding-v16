import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, ErrorHistoryRecord, ErrorHistoryInput, ApiResponse } from "@/lib/api";
import { useErrorStore, CapturedError } from "@/stores/errorStore";
import { useCallback, useEffect, useRef } from "react";

/**
 * Hook for managing error history persistence
 */
export function useErrorHistory() {
  const queryClient = useQueryClient();
  
  // Fetch error history from backend
  const { data: response, isLoading, error, refetch } = useQuery({
    queryKey: ["error-history"],
    queryFn: () => api.listErrorHistory({ limit: 100 }),
    staleTime: 30000, // 30 seconds
  });
  
  // Extract data from API response
  const data = response?.success ? response.data : undefined;

  // Save error to backend
  const saveMutation = useMutation({
    mutationFn: (input: ErrorHistoryInput) => api.saveErrorHistory(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["error-history"] });
    },
  });

  // Delete error from backend
  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.deleteErrorHistory(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["error-history"] });
    },
  });

  // Clear all errors
  const clearMutation = useMutation({
    mutationFn: () => api.clearErrorHistory(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["error-history"] });
    },
  });

  // Bulk export errors
  const exportMutation = useMutation({
    mutationFn: (ids: number[]) => api.bulkExportErrorHistory(ids),
  });

  // Convert CapturedError to ErrorHistoryInput
  const capturedToInput = useCallback((captured: CapturedError): ErrorHistoryInput => {
    return {
      errorId: captured.id,
      code: captured.code,
      level: captured.level,
      message: captured.message,
      details: captured.details,
      context: captured.context,
      stackTrace: captured.stackTrace,
      endpoint: captured.endpoint,
      method: captured.method,
      requestBody: captured.requestBody as Record<string, unknown> | undefined,
      responseStatus: captured.responseStatus,
      sessionId: captured.sessionId,
      sessionType: captured.sessionType,
      phpStackFrames: captured.phpStackFrames,
      backendLogs: captured.backendLogs?.map(l => `[${l.timestamp}] [${l.level}] ${l.message}`),
      backendStackTrace: captured.backendStackTrace,
      siteUrl: captured.siteUrl,
      triggerComponent: captured.triggerComponent,
      triggerAction: captured.triggerAction,
      invocationChain: captured.invocationChain,
    };
  }, []);

  // Save a captured error to backend
  const saveError = useCallback(async (captured: CapturedError) => {
    try {
      const input = capturedToInput(captured);
      await saveMutation.mutateAsync(input);
    } catch (err) {
      // Silently fail - don't want error saving to cause more errors
      console.warn("Failed to save error to history:", err);
    }
  }, [capturedToInput, saveMutation]);

  return {
    errors: data?.errors || [],
    total: data?.total || 0,
    isLoading,
    error,
    refetch,
    saveError,
    deleteError: deleteMutation.mutate,
    clearErrors: clearMutation.mutate,
    exportErrors: exportMutation.mutateAsync,
    isExporting: exportMutation.isPending,
  };
}

/**
 * Hook to auto-save errors to backend when captured
 * Should be used in App.tsx or a top-level component
 */
export function useErrorHistorySync() {
  const { getPendingSyncErrors, markErrorSynced } = useErrorStore();
  const queryClient = useQueryClient();
  const syncingRef = useRef<Set<string>>(new Set());
  
  // Save error to backend
  const saveMutation = useMutation({
    mutationFn: (input: ErrorHistoryInput) => api.saveErrorHistory(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["error-history"] });
    },
  });
  
  // Convert CapturedError to ErrorHistoryInput
  const capturedToInput = useCallback((captured: CapturedError): ErrorHistoryInput => {
    return {
      errorId: captured.id,
      code: captured.code,
      level: captured.level,
      message: captured.message,
      details: captured.details,
      context: captured.context,
      stackTrace: captured.stackTrace,
      endpoint: captured.endpoint,
      method: captured.method,
      requestBody: captured.requestBody as Record<string, unknown> | undefined,
      responseStatus: captured.responseStatus,
      sessionId: captured.sessionId,
      sessionType: captured.sessionType,
      phpStackFrames: captured.phpStackFrames,
      backendLogs: captured.backendLogs?.map(l => `[${l.timestamp}] [${l.level}] ${l.message}`),
      backendStackTrace: captured.backendStackTrace,
      siteUrl: captured.siteUrl,
      triggerComponent: captured.triggerComponent,
      triggerAction: captured.triggerAction,
      invocationChain: captured.invocationChain,
    };
  }, []);
  
  // Sync pending errors to backend
  useEffect(() => {
    const pendingErrors = getPendingSyncErrors();
    
    for (const error of pendingErrors) {
      // Skip if already syncing
      if (syncingRef.current.has(error.id)) continue;
      
      syncingRef.current.add(error.id);
      
      const input = capturedToInput(error);
      saveMutation.mutate(input, {
        onSuccess: () => {
          markErrorSynced(error.id);
          syncingRef.current.delete(error.id);
        },
        onError: () => {
          syncingRef.current.delete(error.id);
          // Will retry on next render cycle
        },
      });
    }
  }, [getPendingSyncErrors, markErrorSynced, capturedToInput, saveMutation]);
}

/**
 * Convert ErrorHistoryRecord back to CapturedError for display
 */
export function recordToCapturedError(record: ErrorHistoryRecord): CapturedError {
  return {
    id: record.errorId,
    code: record.code,
    level: record.level as "error" | "warn" | "info",
    message: record.message,
    details: record.details,
    context: record.context,
    stackTrace: record.stackTrace,
    endpoint: record.endpoint,
    method: record.method,
    requestBody: record.requestBody,
    responseStatus: record.responseStatus,
    sessionId: record.sessionId,
    sessionType: record.sessionType,
    phpStackFrames: record.phpStackFrames,
    backendStackTrace: record.backendStackTrace,
    siteUrl: record.siteUrl,
    triggerComponent: record.triggerComponent,
    triggerAction: record.triggerAction,
    invocationChain: record.invocationChain,
    createdAt: record.createdAt,
  };
}
