import { resolveWsUrl } from "@/lib/endpoints";
import { logger } from "@/lib/logger";
import { transformKeys } from "@/lib/api/keyTransform";

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
  private hasConnectedBefore: boolean = false;
  private disconnectedAt: number | null = null;

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
        const wasReconnect = this.hasConnectedBefore;
        const downtime = this.disconnectedAt ? Date.now() - this.disconnectedAt : 0;
        
        logger.info('[WS] Connected', { url: this.url, wasReconnect, downtimeMs: downtime });
        
        // Reset reconnect state on successful connection
        const previousAttempts = this.reconnectAttempts;
        this.reconnectAttempts = 0;
        this.currentReconnectDelay = this.reconnectDelay;
        this.hasConnectedBefore = true;
        this.disconnectedAt = null;
        
        if (this.reconnectTimer) {
          clearTimeout(this.reconnectTimer);
          this.reconnectTimer = null;
        }
        
        // Emit reconnected event so consumers can reconcile state
        if (wasReconnect) {
          logger.info('[WS] Reconnected — triggering state reconciliation', {
            previousAttempts,
            downtimeMs: downtime,
          });
          const reconnectHandlers = this.handlers.get('__reconnected');
          if (reconnectHandlers) {
            reconnectHandlers.forEach((handler) => handler({ 
              downtimeMs: downtime, 
              reconnectAttempts: previousAttempts 
            }));
          }
        }
      };

      this.ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data);
          // WS messages use PascalCase keys from Go — transform the data payload
          const type = message.type || message.Type;
          const rawData = message.data || message.Data;
          const data = transformKeys(rawData);
          
          logger.debug('[WS] Message received', { type });

          const typeHandlers = this.handlers.get(type);
          if (typeHandlers) {
            typeHandlers.forEach((handler) => handler(data));
          }
        } catch (error: unknown) {
          logger.error('[WS] Failed to parse message', error);
        }
      };

      this.ws.onclose = () => {
        logger.info('[WS] Disconnected', { willReconnect: this.isReconnectEnabled });
        if (this.disconnectedAt === null) {
          this.disconnectedAt = Date.now();
        }
        if (this.isReconnectEnabled) {
          this.scheduleReconnect();
        }
      };

      this.ws.onerror = (error) => {
        logger.error('[WS] Error', error, { url: this.url, attempt: this.reconnectAttempts });
      };
    } catch (error: unknown) {
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

  /**
   * Register a handler for reconnection events.
   * Called after a successful reconnect (not the initial connect).
   * Use this to invalidate caches and reconcile stale state.
   */
  onReconnect(handler: (info: { downtimeMs: number; reconnectAttempts: number }) => void): () => void {
    return this.on('__reconnected', handler as EventHandler);
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
  FILE_CHANGE: "fileChange",
  SYNC_STARTED: "syncStarted",
  SYNC_PROGRESS: "syncProgress",
  SYNC_COMPLETE: "syncComplete",
  PUBLISH_STARTED: "publishStarted",
  PUBLISH_PROGRESS: "publishProgress",
  PUBLISH_COMPLETE: "publishComplete",
  AUTO_PUBLISH_TRIGGERED: "autoPublishTriggered",
  AUTO_PUBLISH_COMPLETE: "autoPublishComplete",
  AUTO_PUBLISH_FAILED: "autoPublishFailed",
  CONNECTION_TEST_STARTED: "connectionTestStarted",
  CONNECTION_TEST_PROGRESS: "connectionTestProgress",
  CONNECTION_TEST_COMPLETE: "connectionTestComplete",
  // Remote plugin actions (enable/disable/delete on WordPress sites)
  REMOTE_PLUGIN_ACTION_STARTED: "remotePluginActionStarted",
  REMOTE_PLUGIN_ACTION_COMPLETE: "remotePluginActionComplete",
  // E2E test events
  E2E_TEST_STARTED: "e2eTestStarted",
  E2E_TEST_RESULT: "e2eTestResult",
  E2E_TEST_COMPLETE: "e2eTestComplete",
  // Snapshot events
  SNAPSHOT_STARTED: "snapshotStarted",
  SNAPSHOT_PROGRESS: "snapshotProgress",
  SNAPSHOT_TABLE_COMPLETE: "snapshotTableComplete",
  SNAPSHOT_COMPLETE: "snapshotComplete",
  ERROR: "error",
  CONNECTION: "connection",
  LOG: "log",
} as const;
