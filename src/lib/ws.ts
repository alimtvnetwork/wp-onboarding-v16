// WebSocket client for real-time updates

type EventHandler = (data: unknown) => void;

function resolveWsUrl(): string {
  const envUrl = import.meta.env.VITE_WS_URL as string | undefined;
  if (envUrl) return envUrl;

  // During tests / SSR-like environments
  if (typeof window === "undefined") {
    return "ws://localhost:8080/ws";
  }

  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  return `${protocol}//${window.location.host}/ws`;
}

class WebSocketClient {
  private ws: WebSocket | null = null;
  private handlers: Map<string, Set<EventHandler>> = new Map();
  private reconnectTimer: number | null = null;
  private reconnectDelay: number = 3000;
  private url: string = resolveWsUrl();

  connect() {
    if (this.ws?.readyState === WebSocket.OPEN) {
      return;
    }

    try {
      this.ws = new WebSocket(this.url);

      this.ws.onopen = () => {
        console.log("[WS] Connected");
        if (this.reconnectTimer) {
          clearTimeout(this.reconnectTimer);
          this.reconnectTimer = null;
        }
      };

      this.ws.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data);
          const { type, data } = message;

          const typeHandlers = this.handlers.get(type);
          if (typeHandlers) {
            typeHandlers.forEach((handler) => handler(data));
          }
        } catch (error) {
          console.error("[WS] Failed to parse message:", error);
        }
      };

      this.ws.onclose = () => {
        console.log("[WS] Disconnected, reconnecting...");
        this.scheduleReconnect();
      };

      this.ws.onerror = (error) => {
        console.error("[WS] Error:", error);
      };
    } catch (error) {
      console.error("[WS] Failed to connect:", error);
      this.scheduleReconnect();
    }
  }

  private scheduleReconnect() {
    if (this.reconnectTimer) {
      return;
    }
    this.reconnectTimer = window.setTimeout(() => {
      this.reconnectTimer = null;
      this.connect();
    }, this.reconnectDelay);
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
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
    this.ws?.close();
    this.ws = null;
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
  PUBLISH_COMPLETE: "publish_complete",
  ERROR: "error",
  CONNECTION: "connection",
} as const;
