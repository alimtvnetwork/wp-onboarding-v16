import { useState, useEffect, useCallback, useRef } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { toast } from "sonner";

export interface WebSocketState {
  isConnected: boolean;
  reconnectAttempts: number;
  maxReconnectAttempts: number;
  isReconnectEnabled: boolean;
}

/**
 * Global hook to track WebSocket connection state.
 * Auto-connects on mount and provides real-time status updates.
 * Shows toast notification when reconnecting after disconnection.
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

  // Track previous connection state for reconnect detection
  const wasConnectedRef = useRef<boolean | null>(null);
  const hasShownDisconnectRef = useRef(false);

  // Update state from wsClient - only if values actually changed
  const updateState = useCallback(() => {
    const reconnectState = wsClient.getReconnectState();
    const newIsConnected = reconnectState.isConnected;
    
    setState((prev) => {
      // Detect reconnection: was disconnected, now connected
      if (wasConnectedRef.current === false && newIsConnected && hasShownDisconnectRef.current) {
        toast.success("WebSocket reconnected", {
          description: "Real-time updates are now active",
          duration: 3000,
        });
        hasShownDisconnectRef.current = false;
      }
      
      // Detect disconnection: was connected, now disconnected
      if (wasConnectedRef.current === true && !newIsConnected) {
        hasShownDisconnectRef.current = true;
        toast.warning("WebSocket disconnected", {
          description: "Attempting to reconnect...",
          duration: 3000,
        });
      }
      
      wasConnectedRef.current = newIsConnected;
      
      // Only return new state object if values actually changed
      if (
        prev.isConnected === newIsConnected &&
        prev.reconnectAttempts === reconnectState.attempts &&
        prev.maxReconnectAttempts === reconnectState.maxAttempts &&
        prev.isReconnectEnabled === reconnectState.isReconnectEnabled
      ) {
        return prev; // Return same reference to prevent re-render
      }
      
      return {
        isConnected: newIsConnected,
        reconnectAttempts: reconnectState.attempts,
        maxReconnectAttempts: reconnectState.maxAttempts,
        isReconnectEnabled: reconnectState.isReconnectEnabled,
      };
    });
  }, []);

  // Manual reconnect
  const reconnect = useCallback(() => {
    wsClient.resetReconnect();
    wsClient.connect();
    toast.info("Reconnecting to WebSocket...");
    // Update state after a brief delay to allow connection attempt
    setTimeout(updateState, 500);
  }, [updateState]);

  useEffect(() => {
    // Initialize wasConnectedRef
    wasConnectedRef.current = wsClient.isConnected();
    
    // Connect on mount if not already connected
    if (!wsClient.isConnected()) {
      wsClient.connect();
    }

    // Listen for connection events
    const unsubConnection = wsClient.on(WS_EVENTS.CONNECTION, (data) => {
      const payload = data as { status: string };
      const newIsConnected = payload.status === "connected";
      
      // Show reconnect toast if we were disconnected and now connected
      if (wasConnectedRef.current === false && newIsConnected && hasShownDisconnectRef.current) {
        toast.success("WebSocket reconnected", {
          description: "Real-time updates are now active",
          duration: 3000,
        });
        hasShownDisconnectRef.current = false;
      }
      
      wasConnectedRef.current = newIsConnected;
      
      setState((prev) => ({
        ...prev,
        isConnected: newIsConnected,
        reconnectAttempts: 0,
      }));
    });

    // Poll for connection state changes
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
