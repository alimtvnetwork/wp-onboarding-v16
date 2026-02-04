import { useEffect, useState, useCallback } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { wsClient, WS_EVENTS } from "@/lib/ws";

export interface WebSocketMessage {
  type: string;
  data: unknown;
  timestamp: string;
}

export function useWebSocket() {
  const queryClient = useQueryClient();
  const [lastMessage, setLastMessage] = useState<WebSocketMessage | null>(null);
  const [isConnected, setIsConnected] = useState(false);

  const createMessage = (type: string, data: unknown): WebSocketMessage => ({
    type,
    data,
    timestamp: new Date().toISOString(),
  });

  useEffect(() => {
    // Connect to WebSocket
    wsClient.connect();

    // Track connection status
    const handleConnect = () => setIsConnected(true);
    const handleDisconnect = () => setIsConnected(false);
    
    // Listen for connection events
    const unsubConnection = wsClient.on(WS_EVENTS.CONNECTION, (data: unknown) => {
      const { status } = data as { status: string };
      setIsConnected(status === "connected");
      setLastMessage(createMessage(WS_EVENTS.CONNECTION, data));
    });

    // File change events - invalidate file changes query
    const unsubFileChange = wsClient.on(WS_EVENTS.FILE_CHANGE, (data: unknown) => {
      const { pluginId } = data as { pluginId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId] });
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      setLastMessage(createMessage(WS_EVENTS.FILE_CHANGE, data));
    });

    // Sync events
    const unsubSyncStarted = wsClient.on(WS_EVENTS.SYNC_STARTED, (data: unknown) => {
      setLastMessage(createMessage(WS_EVENTS.SYNC_STARTED, data));
    });
    const unsubSyncProgress = wsClient.on(WS_EVENTS.SYNC_PROGRESS, (data: unknown) => {
      setLastMessage(createMessage(WS_EVENTS.SYNC_PROGRESS, data));
    });
    const unsubSyncComplete = wsClient.on(WS_EVENTS.SYNC_COMPLETE, (data: unknown) => {
      const { pluginId, siteId } = data as { pluginId: number; siteId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId, siteId] });
      queryClient.invalidateQueries({ queryKey: ["plugins", pluginId] });
      setLastMessage(createMessage(WS_EVENTS.SYNC_COMPLETE, data));
    });

    // Publish events
    const unsubPublishStarted = wsClient.on(WS_EVENTS.PUBLISH_STARTED, (data: unknown) => {
      setLastMessage(createMessage(WS_EVENTS.PUBLISH_STARTED, data));
    });
    const unsubPublishProgress = wsClient.on(WS_EVENTS.PUBLISH_PROGRESS, (data: unknown) => {
      setLastMessage(createMessage(WS_EVENTS.PUBLISH_PROGRESS, data));
    });
    const unsubPublishComplete = wsClient.on(WS_EVENTS.PUBLISH_COMPLETE, (data: unknown) => {
      const { pluginId, siteId } = data as { pluginId: number; siteId: number };
      queryClient.invalidateQueries({ queryKey: ["fileChanges", pluginId, siteId] });
      queryClient.invalidateQueries({ queryKey: ["backups", pluginId] });
      setLastMessage(createMessage(WS_EVENTS.PUBLISH_COMPLETE, data));
    });

    // Git events
    const unsubGitPullStarted = wsClient.on("git_pull_started", (data: unknown) => {
      setLastMessage(createMessage("git_pull_started", data));
    });
    const unsubGitPullComplete = wsClient.on("git_pull_complete", (data: unknown) => {
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      setLastMessage(createMessage("git_pull_complete", data));
    });
    const unsubGitPullFailed = wsClient.on("git_pull_failed", (data: unknown) => {
      setLastMessage(createMessage("git_pull_failed", data));
    });
    const unsubGitCommit = wsClient.on("git_commit_complete", (data: unknown) => {
      setLastMessage(createMessage("git_commit_complete", data));
    });
    const unsubGitPush = wsClient.on("git_push_complete", (data: unknown) => {
      setLastMessage(createMessage("git_push_complete", data));
    });

    // Auto-publish events
    const unsubAutoPublishTriggered = wsClient.on("auto_publish_triggered", (data: unknown) => {
      setLastMessage(createMessage("auto_publish_triggered", data));
    });
    const unsubAutoPublishComplete = wsClient.on("auto_publish_complete", (data: unknown) => {
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      setLastMessage(createMessage("auto_publish_complete", data));
    });
    const unsubAutoPublishFailed = wsClient.on("auto_publish_failed", (data: unknown) => {
      setLastMessage(createMessage("auto_publish_failed", data));
    });

    // Connection test events
    const unsubConnectionTest = wsClient.on(WS_EVENTS.CONNECTION_TEST_PROGRESS, (data: unknown) => {
      setLastMessage(createMessage(WS_EVENTS.CONNECTION_TEST_PROGRESS, data));
    });

    // Scan events
    const unsubScanStarted = wsClient.on("scan_started", (data: unknown) => {
      setLastMessage(createMessage("scan_started", data));
    });
    const unsubScanProgress = wsClient.on("scan_progress", (data: unknown) => {
      setLastMessage(createMessage("scan_progress", data));
    });
    const unsubScanComplete = wsClient.on("scan_complete", (data: unknown) => {
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      setLastMessage(createMessage("scan_complete", data));
    });

    // Error events
    const unsubError = wsClient.on(WS_EVENTS.ERROR, (data: unknown) => {
      queryClient.invalidateQueries({ queryKey: ["errors"] });
      setLastMessage(createMessage(WS_EVENTS.ERROR, data));
    });

    // Log events
    const unsubLog = wsClient.on("log", (data: unknown) => {
      setLastMessage(createMessage("log", data));
    });

    return () => {
      unsubConnection();
      unsubFileChange();
      unsubSyncStarted();
      unsubSyncProgress();
      unsubSyncComplete();
      unsubPublishStarted();
      unsubPublishProgress();
      unsubPublishComplete();
      unsubGitPullStarted();
      unsubGitPullComplete();
      unsubGitPullFailed();
      unsubGitCommit();
      unsubGitPush();
      unsubAutoPublishTriggered();
      unsubAutoPublishComplete();
      unsubAutoPublishFailed();
      unsubConnectionTest();
      unsubScanStarted();
      unsubScanProgress();
      unsubScanComplete();
      unsubError();
      unsubLog();
      wsClient.disconnect();
    };
  }, [queryClient]);

  return { lastMessage, isConnected };
}
