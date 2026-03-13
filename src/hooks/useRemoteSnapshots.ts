import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, ApiResponse, SnapshotRecord, SnapshotSettings, SnapshotProviderInfo, AvailableTable, CreateSnapshotOptions, ErrorDiagnosticContext } from "@/lib/api";
import { SnapshotRunStatus, POLL_INTERVAL_RUNNING_SNAPSHOT_MS } from "@/lib/constants";
import { toast } from "sonner";
import { useErrorStore, CapturedError } from "@/stores/errorStore";
import { useMemo, useCallback, useEffect } from "react";

const POLL_INTERVAL = POLL_INTERVAL_RUNNING_SNAPSHOT_MS;

/**
 * Custom error that preserves the full API error response for rich error capture.
 */
class SnapshotApiError extends Error {
  readonly apiResponse: ApiResponse<unknown>;
  constructor(message: string, apiResponse: ApiResponse<unknown>) {
    super(message);
    this.name = "SnapshotApiError";
    this.apiResponse = apiResponse;
  }
}

/** Throw a SnapshotApiError if the response is not successful */
function throwIfFailed<T>(res: ApiResponse<T>, fallbackMsg: string): T {
  if (!res.success) {
    throw new SnapshotApiError(res.error?.message || fallbackMsg, res as ApiResponse<unknown>);
  }
  return res.data as T;
}

export function useRemoteSnapshots(siteId: number, enabled = true) {
  const queryClient = useQueryClient();
  const { captureException, openErrorModal } = useErrorStore();
  const queryKey = ["sites", siteId, "snapshots"];

  /**
   * Capture a snapshot error, show a toast with "View Error" to open the modal.
   */
  const handleSnapshotError = useCallback((title: string, err: Error) => {
    // Extract rich context from SnapshotApiError
    const apiErr = err instanceof SnapshotApiError ? err.apiResponse.error : undefined;
    const ctx = apiErr?.context;

    // Extract PHP stack frames from delegated server response
    const delegated = ctx?.delegatedRequestServer as { StackTrace?: Array<{ File?: string; FileBase?: string; Line?: number; Function?: string; Class?: string }> } | undefined;
    const phpStackFrames = delegated?.StackTrace?.map(f => ({
      file: f.File,
      fileBase: f.FileBase,
      line: f.Line,
      function: f.Function,
      class: f.Class,
    }));

    const captured = captureException(err, {
      source: `useRemoteSnapshots.${title.replace(/\s+/g, '_')}`,
      endpoint: `/sites/${siteId}/snapshots`,
      method: 'POST',
      context: ctx,
      phpStackFrames: phpStackFrames || undefined,
      sessionId: typeof ctx?.sessionId === 'string' ? ctx.sessionId : undefined,
    });

    toast.error(title, {
      description: err.message?.substring(0, 120),
      action: {
        label: "View Error",
        onClick: () => openErrorModal(captured),
      },
    });
  }, [captureException, openErrorModal, siteId]);

  const snapshotsQuery = useQuery({
    queryKey,
    queryFn: async () => {
      const res = await api.getRemoteSnapshots(siteId);
      return throwIfFailed(res, "Failed to fetch snapshots") as SnapshotRecord[];
    },
    enabled,
    retry: false,
    refetchOnWindowFocus: false,
    meta: { suppressGlobalError: true },
    refetchInterval: (query) => {
      const data = query.state.data as SnapshotRecord[] | undefined;
      if (!data) return false;
      const hasRunning = data.some((s) => s.status === SnapshotRunStatus.Running || s.status === SnapshotRunStatus.InProgress);
      return hasRunning ? POLL_INTERVAL : false;
    },
  });

  const settingsQuery = useQuery({
    queryKey: [...queryKey, "settings"],
    queryFn: async () => {
      const res = await api.getRemoteSnapshotSettings(siteId);
      return throwIfFailed(res, "Failed to fetch settings") as SnapshotSettings;
    },
    enabled,
    retry: false,
    refetchOnWindowFocus: false,
    meta: { suppressGlobalError: true },
  });

  const providersQuery = useQuery({
    queryKey: [...queryKey, "providers"],
    queryFn: async () => {
      const res = await api.getRemoteSnapshotProviders(siteId);
      return throwIfFailed(res, "Failed to fetch providers") as SnapshotProviderInfo[];
    },
    enabled,
    retry: false,
    refetchOnWindowFocus: false,
    meta: { suppressGlobalError: true },
  });

  // Capture query errors for persistence
  useEffect(() => {
    const queries = [
      { q: snapshotsQuery, label: "snapshots", ep: `/sites/${siteId}/snapshots` },
      { q: settingsQuery, label: "settings", ep: `/sites/${siteId}/snapshots/settings` },
      { q: providersQuery, label: "providers", ep: `/sites/${siteId}/snapshots/providers` },
    ];
    for (const { q, label, ep } of queries) {
      if (q.isError && q.error) {
        captureException(q.error, {
          source: `useRemoteSnapshots.${label}`,
          endpoint: ep,
          method: "GET",
          triggerComponent: "RemoteSnapshots",
        });
      }
    }
  }, [
    snapshotsQuery.isError, snapshotsQuery.error,
    settingsQuery.isError, settingsQuery.error,
    providersQuery.isError, providersQuery.error,
    captureException, siteId,
  ]);

  const tablesQuery = useQuery({
    queryKey: [...queryKey, "tables"],
    queryFn: async () => {
      const res = await api.getRemoteAvailableTables(siteId);
      return throwIfFailed(res, "Failed to fetch tables") as AvailableTable[];
    },
    enabled: false,
  });

  const createMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (opts?: CreateSnapshotOptions) => {
      const res = await api.createRemoteSnapshot(siteId, opts);
      return throwIfFailed(res, "Failed to create snapshot");
    },
    onSuccess: () => {
      toast.success("Snapshot creation initiated");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => handleSnapshotError("Snapshot creation failed", err),
  });

  const deleteMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (snapshotId: number) => {
      const res = await api.deleteRemoteSnapshot(siteId, snapshotId);
      return throwIfFailed(res, "Failed to delete snapshot");
    },
    onSuccess: () => {
      toast.success("Snapshot deleted");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => handleSnapshotError("Delete failed", err),
  });

  const restoreMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async ({ snapshotId, opts }: { snapshotId: number; opts?: Omit<import("@/lib/api").RestoreSnapshotOptions, "confirm"> }) => {
      const res = await api.restoreRemoteSnapshot(siteId, snapshotId, { confirm: true, ...opts });
      return throwIfFailed(res, "Failed to restore snapshot");
    },
    onSuccess: () => {
      toast.success("Snapshot restored successfully");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => handleSnapshotError("Restore failed", err),
  });

  const updateSettingsMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (settings: Partial<SnapshotSettings>) => {
      const res = await api.updateRemoteSnapshotSettings(siteId, settings);
      return throwIfFailed(res, "Failed to update settings");
    },
    onSuccess: async () => {
      toast.success("Snapshot settings updated");
      // Invalidate both site-specific and global settings to keep them in sync
      queryClient.invalidateQueries({ queryKey: [...queryKey, "settings"] });
      queryClient.invalidateQueries({ queryKey: ["settings"] });
      queryClient.invalidateQueries({ queryKey: ["snapshot-cron-jobs"] });
      // Sync cron jobs to reflect schedule changes
      try {
        await api.syncSnapshotCronJobs(siteId);
        queryClient.invalidateQueries({ queryKey: ["snapshot-cron-jobs"] });
      } catch {
        // silent — cron sync is best-effort
      }
    },
    onError: (err: Error) => handleSnapshotError("Settings update failed", err),
  });

  const fullBackupMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (opts?: CreateSnapshotOptions) => {
      const res = await api.fullBackupRemoteSnapshot(siteId, opts);
      return throwIfFailed(res, "Failed to trigger full backup");
    },
    onSuccess: () => {
      toast.success("Full backup initiated");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => handleSnapshotError("Full backup failed", err),
  });

  const incrementalBackupMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (opts?: CreateSnapshotOptions) => {
      const res = await api.incrementalBackupRemoteSnapshot(siteId, opts);
      return throwIfFailed(res, "Failed to trigger incremental backup");
    },
    onSuccess: () => {
      toast.success("Incremental backup initiated");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => handleSnapshotError("Incremental backup failed", err),
  });

  const importMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (file: File) => {
      const res = await api.importRemoteSnapshot(siteId, file);
      return throwIfFailed(res, "Failed to import snapshot");
    },
    onSuccess: () => {
      toast.success("Snapshot imported successfully");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => handleSnapshotError("Import failed", err),
  });

  const cleanupMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (opts?: import("@/lib/api").CleanupSnapshotOptions) => {
      const res = await api.cleanupRemoteSnapshots(siteId, opts);
      return throwIfFailed(res, "Failed to run cleanup");
    },
    onSuccess: (data) => {
      const d = data as import("@/lib/api").CleanupSnapshotResult | undefined;
      const retention = d?.retention || { deleted: 0 };
      const orphans = d?.orphans || { removed: 0 };
      const stuck = d?.stuck || { cleaned: 0 };
      toast.success("Snapshot cleanup completed", {
        description: `${retention.deleted || 0} expired, ${orphans.removed || 0} orphans, ${stuck.cleaned || 0} stuck`,
      });
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => handleSnapshotError("Cleanup failed", err),
  });

  const hasRunningSnapshots = useMemo(() => {
    return (snapshotsQuery.data || []).some((s) => s.status === SnapshotRunStatus.Running || s.status === SnapshotRunStatus.InProgress);
  }, [snapshotsQuery.data]);

  return {
    snapshots: snapshotsQuery.data || [],
    isLoading: snapshotsQuery.isLoading,
    isError: snapshotsQuery.isError,
    error: snapshotsQuery.error,
    refetch: snapshotsQuery.refetch,
    hasRunningSnapshots,
    settings: settingsQuery.data,
    settingsDataUpdatedAt: settingsQuery.dataUpdatedAt,
    isLoadingSettings: settingsQuery.isLoading,
    providers: providersQuery.data || [],
    isLoadingProviders: providersQuery.isLoading,
    availableTables: tablesQuery.data || [],
    isLoadingTables: tablesQuery.isLoading || tablesQuery.isFetching,
    fetchTables: tablesQuery.refetch,
    createSnapshot: createMutation.mutate,
    isCreating: createMutation.isPending,
    deleteSnapshot: deleteMutation.mutate,
    isDeleting: deleteMutation.isPending,
    restoreSnapshot: restoreMutation.mutate,
    isRestoring: restoreMutation.isPending,
    updateSettings: updateSettingsMutation.mutate,
    isUpdatingSettings: updateSettingsMutation.isPending,
    fullBackup: fullBackupMutation.mutate,
    isFullBackupPending: fullBackupMutation.isPending,
    incrementalBackup: incrementalBackupMutation.mutate,
    isIncrementalPending: incrementalBackupMutation.isPending,
    importSnapshot: importMutation.mutate,
    isImporting: importMutation.isPending,
    cleanupSnapshots: cleanupMutation.mutate,
    isCleaningUp: cleanupMutation.isPending,
  };
}
