import { resolveWsUrl } from "@/lib/endpoints";
import { logger } from "@/lib/logger";

type EventHandler = (data: unknown) => void;

class WebSocketClient {
  private ws: WebSocket | null = null;
  private handlers: Map<string, Set<EventHandler>> = new Map();
  private reconnectTimer: number | null = null;
  private reconnectDelay: number = 3000;
  private maxReconnectDelay: number = 60000;
  private currentReconnectDelay: number = 3000;
  private reconnectAttempts: number = 0;
  private maxReconnectAttempts: number = 10;
  private url: string = resolveWsUrl();
  private isReconnectEnabled: boolean = true;

  connect() {
    if (this.ws?.readyState === WebSocket.OPEN) {
      return;
    }

    // Ensure reconnect is enabled when explicitly connecting
    this.isReconnectEnabled = true;
    
    logger.trace('WebSocketClient.connect', 'enter', { url: this.url, attempt: this.reconnectAttempts });

    try {
      this.ws = new WebSocket(this.url);

      this.ws.onopen = () => {
        logger.info('[WS] Connected', { url: this.url });
        // Reset reconnect state on successful connection
        this.reconnectAttempts = 0;
        this.currentReconnectDelay = this.reconnectDelay;
        
        if (this.reconnectTimer) {
          clearTimeout(this.reconnectTimer);
          this.reconnectTimer = null;
        }
      };

      this.ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data);
          const { type, data } = message;
          
          logger.debug('[WS] Message received', { type });

          const typeHandlers = this.handlers.get(type);
          if (typeHandlers) {
            typeHandlers.forEach((handler) => handler(data));
          }
        } catch (error) {
          logger.error('[WS] Failed to parse message', error);
        }
      };

      this.ws.onclose = () => {
        logger.info('[WS] Disconnected', { willReconnect: this.isReconnectEnabled });
        if (this.isReconnectEnabled) {
          this.scheduleReconnect();
        }
      };

      this.ws.onerror = (error) => {
        logger.error('[WS] Error', error, { url: this.url, attempt: this.reconnectAttempts });
      };
    } catch (error) {
      logger.error('[WS] Failed to connect', error, { url: this.url });
      if (this.isReconnectEnabled) {
        this.scheduleReconnect();
      }
    }
    
    logger.trace('WebSocketClient.connect', 'exit');
  }

  private scheduleReconnect() {
    if (this.reconnectTimer || !this.isReconnectEnabled) {
      return;
    }
    
    // Check if max attempts reached
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      logger.warn('[WS] Max reconnect attempts reached, stopping reconnection', {
        attempts: this.reconnectAttempts,
        maxAttempts: this.maxReconnectAttempts,
      });
      return;
    }
    
    this.reconnectAttempts++;
    
    // Exponential backoff with cap
    this.currentReconnectDelay = Math.min(
      this.reconnectDelay * Math.pow(1.5, this.reconnectAttempts - 1),
      this.maxReconnectDelay
    );
    
    logger.debug('[WS] Scheduling reconnect', {
      attempt: this.reconnectAttempts,
      delayMs: this.currentReconnectDelay,
    });
    
    this.reconnectTimer = window.setTimeout(() => {
      this.reconnectTimer = null;
      if (this.isReconnectEnabled) {
        this.connect();
      }
    }, this.currentReconnectDelay);
  }

  on(event: string, handler: EventHandler): () => void {
    if (!this.handlers.has(event)) {
      this.handlers.set(event, new Set());
    }
    this.handlers.get(event)!.add(handler);

    return () => {
      this.handlers.get(event)?.delete(handler);
    };
  }

  off(event: string, handler: EventHandler) {
    this.handlers.get(event)?.delete(handler);
  }

  disconnect() {
    logger.info('[WS] Disconnecting (manual)');
    this.isReconnectEnabled = false;
    this.reconnectAttempts = 0;
    this.currentReconnectDelay = this.reconnectDelay;
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
    this.ws?.close();
    this.ws = null;
  }

  // Check if WebSocket is connected
  isConnected(): boolean {
    return this.ws?.readyState === WebSocket.OPEN;
  }
  
  // Reset reconnect attempts (call after manual reconnect request)
  resetReconnect() {
    this.reconnectAttempts = 0;
    this.currentReconnectDelay = this.reconnectDelay;
    this.isReconnectEnabled = true;
    logger.debug('[WS] Reconnect state reset');
  }
  
  // Get current reconnect state for diagnostics
  getReconnectState() {
    return {
      attempts: this.reconnectAttempts,
      maxAttempts: this.maxReconnectAttempts,
      currentDelay: this.currentReconnectDelay,
      isReconnectEnabled: this.isReconnectEnabled,
      isConnected: this.isConnected(),
    };
  }
}

export const wsClient = new WebSocketClient();

// Event types
export const WS_EVENTS = {
  FILE_CHANGE: "file_change",
  SYNC_STARTED: "sync_started",
  SYNC_PROGRESS: "sync_progress",
  SYNC_COMPLETE: "sync_complete",
  PUBLISH_STARTED: "publish_started",
  PUBLISH_PROGRESS: "publish_progress",
  PUBLISH_COMPLETE: "publish_complete",
  AUTO_PUBLISH_TRIGGERED: "auto_publish_triggered",
  AUTO_PUBLISH_COMPLETE: "auto_publish_complete",
  AUTO_PUBLISH_FAILED: "auto_publish_failed",
  CONNECTION_TEST_STARTED: "connection_test_started",
  CONNECTION_TEST_PROGRESS: "connection_test_progress",
  CONNECTION_TEST_COMPLETE: "connection_test_complete",
  // Remote plugin actions (enable/disable/delete on WordPress sites)
  REMOTE_PLUGIN_ACTION_STARTED: "remote_plugin_action_started",
  REMOTE_PLUGIN_ACTION_COMPLETE: "remote_plugin_action_complete",
  // E2E test events
  E2E_TEST_STARTED: "e2e_test_started",
  E2E_TEST_RESULT: "e2e_test_result",
  E2E_TEST_COMPLETE: "e2e_test_complete",
  ERROR: "error",
  CONNECTION: "connection",
  LOG: "log",
} as const;
