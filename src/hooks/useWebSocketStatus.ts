import { useState, useEffect, useCallback, useRef } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { ConnectionStatus } from "@/lib/constants";
import { toast } from "sonner";

export interface WebSocketState {
  isConnected: boolean;
  reconnectAttempts: number;
  maxReconnectAttempts: number;
  isReconnectEnabled: boolean;
}

interface UseWebSocketStatusOptions {
  /** When true, show toast notifications for connect/disconnect events. Default: false */
  showToasts?: boolean;
}

/**
 * Global hook to track WebSocket connection state.
 * Auto-connects on mount and provides real-time status updates.
 * Toast notifications are opt-in via showToasts option.
 */
export function useWebSocketStatus(options: UseWebSocketStatusOptions = {}) {
  const { showToasts = false } = options;
  const showToastsRef = useRef(showToasts);
  showToastsRef.current = showToasts;

  const [state, setState] = useState<WebSocketState>(() => {
    const reconnectState = wsClient.getReconnectState();
    return {
      isConnected: reconnectState.isConnected,
      reconnectAttempts: reconnectState.attempts,
      maxReconnectAttempts: reconnectState.maxAttempts,
      isReconnectEnabled: reconnectState.isReconnectEnabled,
    };
  });

  const wasConnectedRef = useRef<boolean | null>(null);
  const hasShownDisconnectRef = useRef(false);

  const updateState = useCallback(() => {
    const reconnectState = wsClient.getReconnectState();
    const newIsConnected = reconnectState.isConnected;
    
    setState((prev) => {
      // Detect reconnection
      if (wasConnectedRef.current === false && newIsConnected && hasShownDisconnectRef.current) {
        if (showToastsRef.current) {
          toast.success("WebSocket reconnected", {
            description: "Real-time updates are now active",
            duration: 3000,
          });
        }
        hasShownDisconnectRef.current = false;
      }
      
      // Detect disconnection
      if (wasConnectedRef.current === true && !newIsConnected) {
        hasShownDisconnectRef.current = true;
        if (showToastsRef.current) {
          toast.warning("WebSocket disconnected", {
            description: "Attempting to reconnect...",
            duration: 3000,
          });
        }
      }
      
      wasConnectedRef.current = newIsConnected;
      
      if (
        prev.isConnected === newIsConnected &&
        prev.reconnectAttempts === reconnectState.attempts &&
        prev.maxReconnectAttempts === reconnectState.maxAttempts &&
        prev.isReconnectEnabled === reconnectState.isReconnectEnabled
      ) {
        return prev;
      }
      
      return {
        isConnected: newIsConnected,
        reconnectAttempts: reconnectState.attempts,
        maxReconnectAttempts: reconnectState.maxAttempts,
        isReconnectEnabled: reconnectState.isReconnectEnabled,
      };
    });
  }, []);

  const reconnect = useCallback(() => {
    wsClient.resetReconnect();
    wsClient.connect();
    toast.info("Reconnecting to WebSocket...");
    setTimeout(updateState, 500);
  }, [updateState]);

  useEffect(() => {
    wasConnectedRef.current = wsClient.isConnected();
    
    if (!wsClient.isConnected()) {
      wsClient.connect();
    }

    const unsubConnection = wsClient.on(WS_EVENTS.CONNECTION, (data) => {
      const payload = data as { status: string };
      const newIsConnected = payload.status === ConnectionStatus.Connected;
      
      if (wasConnectedRef.current === false && newIsConnected && hasShownDisconnectRef.current) {
        if (showToastsRef.current) {
          toast.success("WebSocket reconnected", {
            description: "Real-time updates are now active",
            duration: 3000,
          });
        }
        hasShownDisconnectRef.current = false;
      }
      
      wasConnectedRef.current = newIsConnected;
      
      setState((prev) => ({
        ...prev,
        isConnected: newIsConnected,
        reconnectAttempts: 0,
      }));
    });

    const interval = setInterval(updateState, 2000);
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
