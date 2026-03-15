import { useCallback } from 'react';
import { toast } from 'sonner';
import { useQueryClient } from '@tanstack/react-query';
import { api, Plugin } from '@/lib/api';
import { usePublishStore, initializePublishWebSocketListeners } from '@/stores/publishStore';
import { useExecutionLoggerStore } from '@/hooks/useExecutionLogger';

/**
 * Hook for bulk quick publish operations.
 * Publishes multiple selected plugins to all their mapped sites
 * via the server-side bulk publish endpoint for sequential processing.
 */
export function useBulkQuickPublish() {
  const queryClient = useQueryClient();
  const startOperation = usePublishStore((state) => state.startOperation);
  const completeOperation = usePublishStore((state) => state.completeOperation);
  const hasActiveOperation = usePublishStore((state) => state.hasActiveOperation);

  // Ensure WS listeners are initialized
  initializePublishWebSocketListeners();

  /**
   * Bulk quick publish: deploy multiple plugins to all their mapped sites
   * via the server-side bulk endpoint with sequential processing.
   */
  const bulkQuickPublish = useCallback(async (
    plugins: Plugin[],
    _options?: {
      concurrency?: number; // Kept for API compat but unused (server controls sequencing)
    }
  ) => {
    const execLogger = useExecutionLoggerStore.getState();
    const chainId = execLogger.startChain(`BulkQuickPublish → ${plugins.length} plugins`);
    execLogger.log({ type: 'handler', name: 'bulkQuickPublish', args: `${plugins.length} plugins` });

    // Filter plugins that have mappings and aren't already publishing
    const publishablePlugins = plugins.filter(
      p => p.mappings && p.mappings.length > 0 && !hasActiveOperation(p.id)
    );

    if (publishablePlugins.length === 0) {
      toast.warning("No plugins with site mappings to publish");
      return;
    }

    // Collect unique site IDs and create operation tracking entries
    const pluginIds: number[] = [];
    const siteIdSet = new Set<number>();

    for (const plugin of publishablePlugins) {
      pluginIds.push(plugin.id);
      for (const mapping of plugin.mappings) {
        siteIdSet.add(mapping.siteId);

        // Track each plugin-site pair in the store for UI progress
        startOperation({
          pluginId: plugin.id,
          pluginName: plugin.name,
          siteId: mapping.siteId,
          siteName: mapping.siteName,
          siteUrl: mapping.siteUrl,
        });
      }
    }

    const siteIds = Array.from(siteIdSet);
    const totalPairs = publishablePlugins.reduce((sum, p) => sum + p.mappings.length, 0);
    toast.info(`Publishing ${publishablePlugins.length} plugin(s) to ${totalPairs} site(s)...`);

    // Get user preferences
    let uploadMode: "file" | "zip" = "file";
    try {
      const saved = localStorage.getItem("wppp_upload_mode");
      if (saved === "zip") uploadMode = "zip";
    } catch { /* default */ }

    let keepZipFiles = false;
    try {
      const saved = localStorage.getItem("wppp_keep_zip_files");
      keepZipFiles = saved === "true";
    } catch { /* default */ }

    // Get cloud storage account IDs
    let cloudStorageAccountIds: number[] | undefined;
    try {
      const saved = localStorage.getItem("wppp_cloud_storage_accounts");
      if (saved) {
        const ids = JSON.parse(saved) as number[];
        if (ids.length > 0) cloudStorageAccountIds = ids;
      }
    } catch { /* default */ }

    const publishMode = uploadMode === "zip" ? "full" : "selected";

    try {
      const response = await api.bulkPublish({
        pluginIds,
        siteIds,
        mode: publishMode,
        createBackup: true,
        keepZipFiles,
        cloudStorageAccountIds,
      });

      if (response.success && response.data) {
        const result = response.data;

        // Complete each tracked operation based on server results
        for (const item of result.items) {
          const operations = usePublishStore.getState().operations;
          for (const [opId, op] of operations) {
            const isMatch = op.pluginId === item.pluginId && op.siteId === item.siteId;
            if (isMatch) {
              completeOperation(opId, item.isSuccess, item.errorMessage, 0);
              break;
            }
          }
        }

        // Summary toast
        if (result.failed === 0) {
          toast.success(`Published ${publishablePlugins.length} plugin(s) to ${result.succeeded} site(s)`);
        } else if (result.succeeded === 0) {
          toast.error(`Failed to publish to all ${result.totalOperations} sites`);
        } else {
          toast.warning(`Published to ${result.succeeded} sites, failed on ${result.failed}`);
        }
      } else {
        // Complete all operations as failed
        const errorMsg = response.error?.message || 'Bulk publish failed';
        completeAllOperationsAsFailed(publishablePlugins, errorMsg, completeOperation);
        toast.error(errorMsg);
      }
    } catch (error: unknown) {
      const errorMsg = error instanceof Error ? error.message : 'Unknown error';
      completeAllOperationsAsFailed(publishablePlugins, errorMsg, completeOperation);
      toast.error(`Bulk publish failed: ${errorMsg}`);
    }

    queryClient.invalidateQueries({ queryKey: ["plugins"] });
    execLogger.endChain(chainId);
  }, [hasActiveOperation, startOperation, completeOperation, queryClient]);

  return { bulkQuickPublish };
}

/**
 * Marks all tracked operations for the given plugins as failed.
 */
function completeAllOperationsAsFailed(
  plugins: Plugin[],
  errorMsg: string,
  completeOperation: (id: string, success: boolean, error?: string) => void,
) {
  const operations = usePublishStore.getState().operations;
  for (const plugin of plugins) {
    for (const [opId, op] of operations) {
      if (op.pluginId === plugin.id) {
        completeOperation(opId, false, errorMsg);
      }
    }
  }
}
