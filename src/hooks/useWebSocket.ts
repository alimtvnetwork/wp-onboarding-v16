import { useEffect, useState, useCallback } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { wsClient, WS_EVENTS } from "@/lib/ws";

export interface WebSocketMessage {
  type: string;
  data: unknown;
}

export function useWebSocket() {
  const queryClient = useQueryClient();
  const [lastMessage, setLastMessage] = useState<WebSocketMessage | null>(null);
  const [isConnected, setIsConnected] = useState(false);

  useEffect(() => {
    // Connect to WebSocket
    wsClient.connect();

    // File change events - invalidate file changes query
    const unsubFileChange = wsClient.on(WS_EVENTS.FILE_CHANGE, (data: unknown) => {
      const { pluginId } = data as { pluginId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId] });
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      setLastMessage({ type: WS_EVENTS.FILE_CHANGE, data });
    });

    // Sync complete events
    const unsubSyncComplete = wsClient.on(WS_EVENTS.SYNC_COMPLETE, (data: unknown) => {
      const { pluginId, siteId } = data as { pluginId: number; siteId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId, siteId] });
      queryClient.invalidateQueries({ queryKey: ["plugins", pluginId] });
      setLastMessage({ type: WS_EVENTS.SYNC_COMPLETE, data });
    });

    // Publish complete events
    const unsubPublishComplete = wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
      const { pluginId, siteId } = data as { pluginId: number; siteId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId, siteId] });
      queryClient.invalidateQueries({ queryKey: ["backups", pluginId] });
      setLastMessage({ type: WS_EVENTS.PUBLISH_COMPLETE, data });
    });

    // Error events
    const unsubError = wsClient.on(WS_EVENTS.ERROR, (data: unknown) => {
      queryClient.invalidateQueries({ queryKey: ["errors"] });
      setLastMessage({ type: WS_EVENTS.ERROR, data });
    });

    // Log events
    const unsubLog = wsClient.on("log", (data: unknown) => {
      setLastMessage({ type: "log", data });
    });

    return () => {
      unsubFileChange();
      unsubSyncComplete();
      unsubPublishComplete();
      unsubError();
      unsubLog();
      wsClient.disconnect();
    };
  }, [queryClient]);

  return { lastMessage, isConnected };
}
