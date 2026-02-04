import { useState, useEffect, useCallback } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";

export interface WebSocketState {
  isConnected: boolean;
  reconnectAttempts: number;
  maxReconnectAttempts: number;
  isReconnectEnabled: boolean;
}

/**
 * Global hook to track WebSocket connection state.
 * Auto-connects on mount and provides real-time status updates.
 */
export function useWebSocketStatus() {
  const [state, setState] = useState<WebSocketState>(() => {
    const reconnectState = wsClient.getReconnectState();
    return {
      isConnected: reconnectState.isConnected,
      reconnectAttempts: reconnectState.attempts,
      maxReconnectAttempts: reconnectState.maxAttempts,
      isReconnectEnabled: reconnectState.isReconnectEnabled,
    };
  });

  // Update state from wsClient
  const updateState = useCallback(() => {
    const reconnectState = wsClient.getReconnectState();
    setState({
      isConnected: reconnectState.isConnected,
      reconnectAttempts: reconnectState.attempts,
      maxReconnectAttempts: reconnectState.maxAttempts,
      isReconnectEnabled: reconnectState.isReconnectEnabled,
    });
  }, []);

  // Manual reconnect
  const reconnect = useCallback(() => {
    wsClient.resetReconnect();
    wsClient.connect();
    // Update state after a brief delay to allow connection attempt
    setTimeout(updateState, 100);
  }, [updateState]);

  useEffect(() => {
    // Connect on mount if not already connected
    if (!wsClient.isConnected()) {
      wsClient.connect();
    }

    // Listen for connection events
    const unsubConnection = wsClient.on(WS_EVENTS.CONNECTION, (data) => {
      const payload = data as { status: string };
      setState((prev) => ({
        ...prev,
        isConnected: payload.status === "connected",
        reconnectAttempts: 0,
      }));
    });

    // Poll for connection state changes (WebSocket doesn't have a native "disconnect" event we subscribe to)
    const interval = setInterval(updateState, 2000);

    // Initial state update
    updateState();

    return () => {
      unsubConnection();
      clearInterval(interval);
    };
  }, [updateState]);

  return {
    ...state,
    reconnect,
  };
}
