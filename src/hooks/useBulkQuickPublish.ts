import { useCallback } from 'react';
import { toast } from 'sonner';
import { useQueryClient } from '@tanstack/react-query';
import { api, Plugin } from '@/lib/api';
import { usePublishStore, initializePublishWebSocketListeners } from '@/stores/publishStore';
import { useExecutionLoggerStore } from '@/hooks/useExecutionLogger';

/**
 * Hook for bulk quick publish operations.
 * Publishes multiple selected plugins to all their mapped sites.
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
   * with configurable concurrency to prevent WordPress overload
   */
  const bulkQuickPublish = useCallback(async (
    plugins: Plugin[],
    options?: {
      concurrency?: number; // Max simultaneous publishes (default: 2)
    }
  ) => {
    const concurrency = options?.concurrency ?? 2;
    const execLogger = useExecutionLoggerStore.getState();
    const chainId = execLogger.startChain(`BulkQuickPublish → ${plugins.length} plugins`);
    execLogger.log({ type: 'handler', name: 'bulkQuickPublish', args: `${plugins.length} plugins, concurrency=${concurrency}` });

    // Filter plugins that have mappings and aren't already publishing
    const publishablePlugins = plugins.filter(
      p => p.mappings && p.mappings.length > 0 && !hasActiveOperation(p.id)
    );

    if (publishablePlugins.length === 0) {
      toast.warning("No plugins with site mappings to publish");
      return;
    }

    // Build all publish tasks
    const tasks: Array<{
      plugin: Plugin;
      siteId: number;
      siteName: string;
      siteUrl: string;
      operationId: string;
    }> = [];

    for (const plugin of publishablePlugins) {
      for (const mapping of plugin.mappings) {
        const operationId = startOperation({
          pluginId: plugin.id,
          pluginName: plugin.name,
          siteId: mapping.siteId,
          siteName: mapping.siteName,
          siteUrl: mapping.siteUrl,
        });
        tasks.push({
          plugin,
          siteId: mapping.siteId,
          siteName: mapping.siteName,
          siteUrl: mapping.siteUrl,
          operationId,
        });
      }
    }

    const totalSites = tasks.length;
    toast.info(`Publishing ${publishablePlugins.length} plugin(s) to ${totalSites} site(s)...`);

    // Execute with concurrency limit
    let completed = 0;
    let succeeded = 0;
    let failed = 0;

    const executeTask = async (task: typeof tasks[0]) => {
      // Get upload mode from localStorage
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

      const publishMode = uploadMode === "zip" ? "full" : "selected";

      try {
        const response = await api.publishPlugin(task.plugin.id, task.siteId, {
          mode: publishMode,
          createBackup: true,
          keepZipFiles,
        });

        if (response.success) {
          succeeded++;
          completeOperation(task.operationId, true, undefined, response.data?.filesUpdated || 0);
        } else {
          failed++;
          completeOperation(task.operationId, false, response.error?.message);
        }
      } catch (error: unknown) {
        failed++;
        completeOperation(
          task.operationId,
          false,
          error instanceof Error ? error.message : 'Unknown error'
        );
      }
      completed++;
    };

    // Process tasks with concurrency limit
    const executing = new Set<Promise<void>>();
    for (const task of tasks) {
      const promise = executeTask(task).then(() => {
        executing.delete(promise);
      });
      executing.add(promise);

      if (executing.size >= concurrency) {
        await Promise.race(executing);
      }
    }
    await Promise.all(executing);

    // Summary toast
    if (failed === 0) {
      toast.success(`Published ${publishablePlugins.length} plugin(s) to ${succeeded} site(s)`);
    } else if (succeeded === 0) {
      toast.error(`Failed to publish to all ${totalSites} sites`);
    } else {
      toast.warning(`Published to ${succeeded} sites, failed on ${failed}`);
    }

    queryClient.invalidateQueries({ queryKey: ["plugins"] });
  }, [hasActiveOperation, startOperation, completeOperation, queryClient]);

  return { bulkQuickPublish };
}
