import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, SnapshotRecord, SnapshotSettings, SnapshotProviderInfo, AvailableTable } from "@/lib/api";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";
import { useMemo } from "react";
import { toClipboardText } from "@/lib/logText";

const POLL_INTERVAL = 5000; // 5s when snapshots are running

function snapshotErrorToast(title: string, err: Error) {
  const errorText = `${title}: ${err.message}`;
  toast.error(title, {
    description: err.message,
    action: {
      label: "Check Logs",
      onClick: () => { window.location.href = "/errors"; },
    },
  });
  // Also copy error to clipboard for convenience
  navigator.clipboard?.writeText(toClipboardText(errorText)).catch(() => {});
}

export function useRemoteSnapshots(siteId: number, enabled = true) {
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const queryKey = ["sites", siteId, "snapshots"];

  const snapshotsQuery = useQuery({
    queryKey,
    queryFn: async () => {
      const res = await api.getRemoteSnapshots(siteId);
      if (!res.success) throw new Error(res.error?.message || "Failed to fetch snapshots");
      return (res.data || []) as SnapshotRecord[];
    },
    enabled,
    retry: 1,
    meta: { suppressGlobalError: true },
    refetchInterval: (query) => {
      const data = query.state.data as SnapshotRecord[] | undefined;
      if (!data) return false;
      const hasRunning = data.some((s) => s.status === "running" || s.status === "in_progress");
      return hasRunning ? POLL_INTERVAL : false;
    },
  });

  const settingsQuery = useQuery({
    queryKey: [...queryKey, "settings"],
    queryFn: async () => {
      const res = await api.getRemoteSnapshotSettings(siteId);
      if (!res.success) throw new Error(res.error?.message || "Failed to fetch settings");
      return res.data as SnapshotSettings;
    },
    enabled,
    retry: 1,
    meta: { suppressGlobalError: true },
  });

  const providersQuery = useQuery({
    queryKey: [...queryKey, "providers"],
    queryFn: async () => {
      const res = await api.getRemoteSnapshotProviders(siteId);
      if (!res.success) throw new Error(res.error?.message || "Failed to fetch providers");
      return (res.data || []) as SnapshotProviderInfo[];
    },
    enabled,
    retry: 1,
    meta: { suppressGlobalError: true },
  });

  const tablesQuery = useQuery({
    queryKey: [...queryKey, "tables"],
    queryFn: async () => {
      const res = await api.getRemoteAvailableTables(siteId);
      if (!res.success) throw new Error(res.error?.message || "Failed to fetch tables");
      return (res.data || []) as AvailableTable[];
    },
    enabled: false, // Only fetch on demand
  });
  const createMutation = useMutation({
    mutationFn: async (opts?: Record<string, unknown>) => {
      const res = await api.createRemoteSnapshot(siteId, opts);
      if (!res.success) throw new Error(res.error?.message || "Failed to create snapshot");
      return res.data;
    },
    onSuccess: () => {
      toast.success("Snapshot creation initiated");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Snapshot creation failed", err);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (snapshotId: number) => {
      const res = await api.deleteRemoteSnapshot(siteId, snapshotId);
      if (!res.success) throw new Error(res.error?.message || "Failed to delete snapshot");
      return res.data;
    },
    onSuccess: () => {
      toast.success("Snapshot deleted");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Delete failed", err);
    },
  });

  const restoreMutation = useMutation({
    mutationFn: async ({ snapshotId, opts }: { snapshotId: number; opts?: Record<string, unknown> }) => {
      const res = await api.restoreRemoteSnapshot(siteId, snapshotId, { confirm: true, ...opts });
      if (!res.success) throw new Error(res.error?.message || "Failed to restore snapshot");
      return res.data;
    },
    onSuccess: () => {
      toast.success("Snapshot restored successfully");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Restore failed", err);
    },
  });

  const updateSettingsMutation = useMutation({
    mutationFn: async (settings: Record<string, unknown>) => {
      const res = await api.updateRemoteSnapshotSettings(siteId, settings);
      if (!res.success) throw new Error(res.error?.message || "Failed to update settings");
      return res.data;
    },
    onSuccess: () => {
      toast.success("Snapshot settings updated");
      queryClient.invalidateQueries({ queryKey: [...queryKey, "settings"] });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Settings update failed", err);
    },
  });

  const fullBackupMutation = useMutation({
    mutationFn: async (opts?: Record<string, unknown>) => {
      const res = await api.fullBackupRemoteSnapshot(siteId, opts);
      if (!res.success) throw new Error(res.error?.message || "Failed to trigger full backup");
      return res.data;
    },
    onSuccess: () => {
      toast.success("Full backup initiated");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Full backup failed", err);
    },
  });

  const incrementalBackupMutation = useMutation({
    mutationFn: async (opts?: Record<string, unknown>) => {
      const res = await api.incrementalBackupRemoteSnapshot(siteId, opts);
      if (!res.success) throw new Error(res.error?.message || "Failed to trigger incremental backup");
      return res.data;
    },
    onSuccess: () => {
      toast.success("Incremental backup initiated");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Incremental backup failed", err);
    },
  });

  const importMutation = useMutation({
    mutationFn: async (file: File) => {
      const res = await api.importRemoteSnapshot(siteId, file);
      if (!res.success) throw new Error(res.error?.message || "Failed to import snapshot");
      return res.data;
    },
    onSuccess: () => {
      toast.success("Snapshot imported successfully");
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Import failed", err);
    },
  });

  const cleanupMutation = useMutation({
    mutationFn: async (opts?: Record<string, unknown>) => {
      const res = await api.cleanupRemoteSnapshots(siteId, opts);
      if (!res.success) throw new Error(res.error?.message || "Failed to run cleanup");
      return res.data;
    },
    onSuccess: (data) => {
      const d = data as Record<string, unknown> | undefined;
      const retention = (d?.retention as Record<string, number>) || {};
      const orphans = (d?.orphans as Record<string, number>) || {};
      const stuck = (d?.stuck as Record<string, number>) || {};
      toast.success("Snapshot cleanup completed", {
        description: `${retention.deleted || 0} expired, ${orphans.removed || 0} orphans, ${stuck.cleaned || 0} stuck`,
      });
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (err: Error) => {
      snapshotErrorToast("Cleanup failed", err);
    },
  });

  const hasRunningSnapshots = useMemo(() => {
    return (snapshotsQuery.data || []).some((s) => s.status === "running" || s.status === "in_progress");
  }, [snapshotsQuery.data]);

  return {
    snapshots: snapshotsQuery.data || [],
    isLoading: snapshotsQuery.isLoading,
    isError: snapshotsQuery.isError,
    error: snapshotsQuery.error,
    refetch: snapshotsQuery.refetch,
    hasRunningSnapshots,
    settings: settingsQuery.data,
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
