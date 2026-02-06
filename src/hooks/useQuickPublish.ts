import { useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api, Plugin } from '@/lib/api';
import { usePublishStore, initializePublishWebSocketListeners } from '@/stores/publishStore';
import { useErrorStore } from '@/stores/errorStore';
import { useExecutionLoggerStore } from '@/hooks/useExecutionLogger';

/**
 * Hook for quick publish operations.
 * Publishes a plugin to all mapped sites without opening dialogs.
 */
export function useQuickPublish() {
  const queryClient = useQueryClient();
  const { captureError, openErrorModal } = useErrorStore();
  const startOperation = usePublishStore((state) => state.startOperation);
  const hasActiveOperation = usePublishStore((state) => state.hasActiveOperation);
  const completeOperation = usePublishStore((state) => state.completeOperation);
  
  // Ensure WS listeners are initialized
  initializePublishWebSocketListeners();
  
  /**
   * Publish a plugin to a single site
   */
  const publishToSite = useCallback(async (
    plugin: Plugin,
    siteId: number,
    siteName: string,
    siteUrl: string
  ): Promise<{ success: boolean; error?: string; filesUpdated?: number }> => {
    const execLogger = useExecutionLoggerStore.getState();
    const chainId = execLogger.startChain(`QuickPublish → ${plugin.name} → ${siteName}`);
    execLogger.log({ type: 'handler', name: 'publishToSite', args: `plugin=${plugin.name}, site=${siteName}` });

    // Get upload mode from localStorage
    let uploadMode: "file" | "zip" = "file";
    try {
      const saved = localStorage.getItem("wppp_upload_mode");
      if (saved === "zip") uploadMode = "zip";
    } catch {
      // Default to file mode
    }

    // Get keep ZIP files setting
    let keepZipFiles = false;
    try {
      const saved = localStorage.getItem("wppp_keep_zip_files");
      keepZipFiles = saved === "true";
    } catch {
      // Default to false
    }

    const publishMode = uploadMode === "zip" ? "full" : "selected";

    try {
      const response = await api.publishPlugin(plugin.id, siteId, {
        mode: publishMode,
        createBackup: true,
        keepZipFiles,
      });

      if (response.success) {
        execLogger.log({ type: 'function', name: 'publishToSite.success', result: `${response.data?.filesUpdated || 0} files updated` });
        execLogger.endChain(chainId);
        return { 
          success: true, 
          filesUpdated: response.data?.filesUpdated || 0 
        };
      } else if (response.error) {
        execLogger.endChain(chainId, response.error.message);
        return { 
          success: false, 
          error: response.error.message 
        };
      }
      execLogger.endChain(chainId, 'Unknown error');
      return { success: false, error: 'Unknown error' };
    } catch (error) {
      const errMsg = error instanceof Error ? error.message : 'Unknown error';
      execLogger.endChain(chainId, errMsg);
      return { 
        success: false, 
        error: errMsg 
      };
    }
  }, []);

  /**
   * Quick publish a plugin to all mapped sites
   */
  const quickPublishAll = useCallback(async (plugin: Plugin) => {
    if (!plugin.mappings || plugin.mappings.length === 0) {
      toast.warning("No sites mapped - add a site first");
      return;
    }

    // Check if already publishing this plugin
    if (hasActiveOperation(plugin.id)) {
      toast.info("Publish already in progress for this plugin");
      return;
    }

    // Start operations for all sites
    const operationIds: string[] = [];
    for (const mapping of plugin.mappings) {
      // Find the site info from mapping
      const operationId = startOperation({
        pluginId: plugin.id,
        pluginName: plugin.name,
        siteId: mapping.siteId,
        siteName: mapping.siteName,
        siteUrl: mapping.siteUrl,
      });
      operationIds.push(operationId);
    }

    toast.info(`Publishing ${plugin.name} to ${plugin.mappings.length} site(s)...`);

    // Execute publishes in parallel
    const results = await Promise.all(
      plugin.mappings.map(async (mapping, index) => {
        const operationId = operationIds[index];
        const result = await publishToSite(
          plugin,
          mapping.siteId,
          mapping.siteName,
          mapping.siteUrl
        );
        
        // Complete the operation (WebSocket should handle this, but as fallback)
        completeOperation(operationId, result.success, result.error, result.filesUpdated);
        
        return { ...result, siteName: mapping.siteName };
      })
    );

    // Summarize results
    const successCount = results.filter(r => r.success).length;
    const failCount = results.filter(r => !r.success).length;

    if (failCount === 0) {
      toast.success(`Published ${plugin.name} to ${successCount} site(s)`);
    } else if (successCount === 0) {
      toast.error(`Failed to publish ${plugin.name} to all sites`);
    } else {
      toast.warning(`Published to ${successCount} sites, failed on ${failCount}`);
    }

    // Refresh plugins data
    queryClient.invalidateQueries({ queryKey: ["plugins"] });
  }, [hasActiveOperation, startOperation, publishToSite, completeOperation, queryClient]);

  /**
   * Quick publish a plugin to a single site
   */
  const quickPublishToSite = useCallback(async (
    plugin: Plugin,
    siteId: number,
    siteName: string,
    siteUrl: string
  ) => {
    // Check if already publishing to this site
    if (hasActiveOperation(plugin.id, siteId)) {
      toast.info("Publish already in progress for this site");
      return;
    }

    // Start operation
    const operationId = startOperation({
      pluginId: plugin.id,
      pluginName: plugin.name,
      siteId,
      siteName,
      siteUrl,
    });

    toast.info(`Publishing ${plugin.name} to ${siteName}...`);

    const result = await publishToSite(plugin, siteId, siteName, siteUrl);
    
    // Complete the operation
    completeOperation(operationId, result.success, result.error, result.filesUpdated);

    if (result.success) {
      toast.success(`Published ${plugin.name} to ${siteName}`);
    } else {
      toast.error(`Failed to publish to ${siteName}: ${result.error}`);
    }

    // Refresh plugins data
    queryClient.invalidateQueries({ queryKey: ["plugins"] });
    
    return result;
  }, [hasActiveOperation, startOperation, publishToSite, completeOperation, queryClient]);

  return {
    quickPublishAll,
    quickPublishToSite,
    hasActiveOperation,
  };
}
