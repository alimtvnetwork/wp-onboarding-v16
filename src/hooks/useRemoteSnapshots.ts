import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, SnapshotRecord, SnapshotSettings, SnapshotProviderInfo } from "@/lib/api";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";
import { useMemo } from "react";

const POLL_INTERVAL = 5000; // 5s when snapshots are running

export function useRemoteSnapshots(siteId: number) {
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
  });

  const providersQuery = useQuery({
    queryKey: [...queryKey, "providers"],
    queryFn: async () => {
      const res = await api.getRemoteSnapshotProviders(siteId);
      if (!res.success) throw new Error(res.error?.message || "Failed to fetch providers");
      return (res.data || []) as SnapshotProviderInfo[];
    },
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
      toast.error("Snapshot creation failed", { description: err.message });
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
      toast.error("Delete failed", { description: err.message });
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
      toast.error("Restore failed", { description: err.message });
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
      toast.error("Settings update failed", { description: err.message });
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
    createSnapshot: createMutation.mutate,
    isCreating: createMutation.isPending,
    deleteSnapshot: deleteMutation.mutate,
    isDeleting: deleteMutation.isPending,
    restoreSnapshot: restoreMutation.mutate,
    isRestoring: restoreMutation.isPending,
    updateSettings: updateSettingsMutation.mutate,
    isUpdatingSettings: updateSettingsMutation.isPending,
  };
}
