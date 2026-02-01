import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { wsClient, WS_EVENTS } from "@/lib/ws";

export function useWebSocket() {
  const queryClient = useQueryClient();

  useEffect(() => {
    // Connect to WebSocket
    wsClient.connect();

    // File change events - invalidate file changes query
    const unsubFileChange = wsClient.on(WS_EVENTS.FILE_CHANGE, (data: unknown) => {
      const { pluginId } = data as { pluginId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId] });
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
    });

    // Sync complete events
    const unsubSyncComplete = wsClient.on(WS_EVENTS.SYNC_COMPLETE, (data: unknown) => {
      const { pluginId, siteId } = data as { pluginId: number; siteId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId, siteId] });
      queryClient.invalidateQueries({ queryKey: ["plugins", pluginId] });
    });

    // Publish complete events
    const unsubPublishComplete = wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
      const { pluginId, siteId } = data as { pluginId: number; siteId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId, siteId] });
      queryClient.invalidateQueries({ queryKey: ["backups", pluginId] });
    });

    // Error events
    const unsubError = wsClient.on(WS_EVENTS.ERROR, () => {
      queryClient.invalidateQueries({ queryKey: ["errors"] });
    });

    return () => {
      unsubFileChange();
      unsubSyncComplete();
      unsubPublishComplete();
      unsubError();
      wsClient.disconnect();
    };
  }, [queryClient]);
}
