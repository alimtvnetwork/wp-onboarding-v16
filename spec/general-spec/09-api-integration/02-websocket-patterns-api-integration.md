# WebSocket Patterns

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document establishes WebSocket implementation patterns for real-time bidirectional communication across PHP, TypeScript, and Python applications.

## Connection Lifecycle

### State Machine

```
┌─────────────┐
│ CONNECTING  │
└──────┬──────┘
       │ onopen
       ▼
┌─────────────┐     onerror/onclose
│  CONNECTED  │◄────────────────────┐
└──────┬──────┘                     │
       │ onclose                    │
       ▼                            │
┌─────────────┐     reconnect      │
│   CLOSED    │─────────────────────┘
└─────────────┘
```

### TypeScript Client Implementation

```typescript
// src/lib/websocket/WebSocketClient.ts
export interface WebSocketConfig {
  url: string;
  protocols?: string[];
  reconnect?: boolean;
  maxReconnectAttempts?: number;
  reconnectInterval?: number;
  heartbeatInterval?: number;
  messageTimeout?: number;
}

export type ConnectionState = 'connecting' | 'connected' | 'disconnected' | 'reconnecting';

export class WebSocketClient {
  private ws: WebSocket | null = null;
  private state: ConnectionState = 'disconnected';
  private reconnectAttempts = 0;
  private heartbeatTimer: NodeJS.Timeout | null = null;
  private messageHandlers = new Map<string, Set<(data: unknown) => void>>();
  private pendingRequests = new Map<string, {
    resolve: (value: unknown) => void;
    reject: (error: Error) => void;
    timeout: NodeJS.Timeout;
  }>();
  
  private readonly config: Required<WebSocketConfig>;
  
  constructor(config: WebSocketConfig) {
    this.config = {
      reconnect: true,
      maxReconnectAttempts: 5,
      reconnectInterval: 1000,
      heartbeatInterval: 30000,
      messageTimeout: 10000,
      protocols: [],
      ...config,
    };
  }
  
  async connect(): Promise<void> {
    if (this.state === 'connected' || this.state === 'connecting') {
      return;
    }
    
    this.state = 'connecting';
    
    return new Promise((resolve, reject) => {
      try {
        this.ws = new WebSocket(this.config.url, this.config.protocols);
        
        this.ws.onopen = () => {
          this.state = 'connected';
          this.reconnectAttempts = 0;
          this.startHeartbeat();
          resolve();
        };
        
        this.ws.onclose = (event) => {
          this.handleClose(event);
        };
        
        this.ws.onerror = (error) => {
          console.error('[WebSocket] Connection error:', error);
          if (this.state === 'connecting') {
            reject(new Error('WebSocket connection failed'));
          }
        };
        
        this.ws.onmessage = (event) => {
          this.handleMessage(event);
        };
      } catch (error) {
        this.state = 'disconnected';
        reject(error);
      }
    });
  }
  
  disconnect(): void {
    this.config.reconnect = false;
    this.stopHeartbeat();
    this.ws?.close(1000, 'Client disconnect');
    this.state = 'disconnected';
  }
  
  private handleClose(event: CloseEvent): void {
    this.state = 'disconnected';
    this.stopHeartbeat();
    this.rejectPendingRequests(new Error('Connection closed'));
    
    if (this.config.reconnect && this.reconnectAttempts < this.config.maxReconnectAttempts) {
      this.scheduleReconnect();
    }
  }
  
  private scheduleReconnect(): void {
    this.state = 'reconnecting';
    const delay = this.config.reconnectInterval * Math.pow(2, this.reconnectAttempts);
    
    console.log(`[WebSocket] Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts + 1})`);
    
    setTimeout(() => {
      this.reconnectAttempts++;
      this.connect().catch(console.error);
    }, delay);
  }
  
  private startHeartbeat(): void {
    this.heartbeatTimer = setInterval(() => {
      this.send({ type: 'ping', timestamp: Date.now() });
    }, this.config.heartbeatInterval);
  }
  
  private stopHeartbeat(): void {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }
  
  // Message handling
  private handleMessage(event: MessageEvent): void {
    try {
      const message = JSON.parse(event.data);
      
      // Handle pong responses
      if (message.type === 'pong') {
        return;
      }
      
      // Handle request responses
      if (message.requestId && this.pendingRequests.has(message.requestId)) {
        const pending = this.pendingRequests.get(message.requestId)!;
        clearTimeout(pending.timeout);
        this.pendingRequests.delete(message.requestId);
        
        if (message.error) {
          pending.reject(new Error(message.error));
        } else {
          pending.resolve(message.data);
        }
        return;
      }
      
      // Handle subscribed events
      const handlers = this.messageHandlers.get(message.type);
      if (handlers) {
        handlers.forEach(handler => handler(message.data));
      }
    } catch (error) {
      console.error('[WebSocket] Failed to parse message:', error);
    }
  }
  
  // Public API
  send(data: Record<string, unknown>): void {
    if (this.ws?.readyState !== WebSocket.OPEN) {
      throw new Error('WebSocket is not connected');
    }
    
    this.ws.send(JSON.stringify(data));
  }
  
  async request<T>(type: string, data?: unknown): Promise<T> {
    const requestId = crypto.randomUUID();
    
    return new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        this.pendingRequests.delete(requestId);
        reject(new Error(`Request timeout: ${type}`));
      }, this.config.messageTimeout);
      
      this.pendingRequests.set(requestId, { resolve: resolve as (v: unknown) => void, reject, timeout });
      this.send({ type, requestId, data });
    });
  }
  
  subscribe(type: string, handler: (data: unknown) => void): () => void {
    if (!this.messageHandlers.has(type)) {
      this.messageHandlers.set(type, new Set());
    }
    
    this.messageHandlers.get(type)!.add(handler);
    
    // Return unsubscribe function
    return () => {
      this.messageHandlers.get(type)?.delete(handler);
    };
  }
  
  private rejectPendingRequests(error: Error): void {
    this.pendingRequests.forEach(({ reject, timeout }) => {
      clearTimeout(timeout);
      reject(error);
    });
    this.pendingRequests.clear();
  }
  
  get connectionState(): ConnectionState {
    return this.state;
  }
}
```

## Message Protocol

### Standard Message Format

```typescript
// src/lib/websocket/types.ts
export interface WebSocketMessage<T = unknown> {
  type: string;
  requestId?: string;
  data?: T;
  error?: string;
  timestamp: number;
}

// Event types
export interface ServerEvent<T = unknown> {
  type: string;
  payload: T;
  metadata: {
    timestamp: number;
    source: string;
    correlationId?: string;
  };
}

// Client commands
export interface ClientCommand<T = unknown> {
  type: string;
  payload: T;
  requestId: string;
}

// Standard event types
export const EventTypes = {
  // Connection
  PING: 'ping',
  PONG: 'pong',
  
  // Authentication
  AUTH_REQUEST: 'auth.request',
  AUTH_SUCCESS: 'auth.success',
  AUTH_FAILURE: 'auth.failure',
  
  // Subscriptions
  SUBSCRIBE: 'subscribe',
  UNSUBSCRIBE: 'unsubscribe',
  SUBSCRIBED: 'subscribed',
  UNSUBSCRIBED: 'unsubscribed',
  
  // Errors
  ERROR: 'error',
} as const;
```

### Authentication Flow

```typescript
// src/lib/websocket/auth.ts
export class AuthenticatedWebSocket extends WebSocketClient {
  private authToken: string | null = null;
  
  async authenticate(token: string): Promise<void> {
    this.authToken = token;
    
    const response = await this.request<{ userId: string }>('auth.request', {
      token,
    });
    
    console.log('[WebSocket] Authenticated as user:', response.userId);
  }
  
  protected override async connect(): Promise<void> {
    await super.connect();
    
    // Re-authenticate on reconnect
    if (this.authToken) {
      await this.authenticate(this.authToken);
    }
  }
}
```

## Room/Channel Pattern

### Channel Management

```typescript
// src/lib/websocket/channels.ts
export class ChannelManager {
  private channels = new Map<string, Set<(data: unknown) => void>>();
  private ws: WebSocketClient;
  
  constructor(ws: WebSocketClient) {
    this.ws = ws;
  }
  
  async join(channelName: string): Promise<void> {
    if (this.channels.has(channelName)) {
      return; // Already joined
    }
    
    await this.ws.request('subscribe', { channel: channelName });
    this.channels.set(channelName, new Set());
    
    console.log(`[Channel] Joined: ${channelName}`);
  }
  
  async leave(channelName: string): Promise<void> {
    if (!this.channels.has(channelName)) {
      return;
    }
    
    await this.ws.request('unsubscribe', { channel: channelName });
    this.channels.delete(channelName);
    
    console.log(`[Channel] Left: ${channelName}`);
  }
  
  on<T>(channelName: string, handler: (data: T) => void): () => void {
    const handlers = this.channels.get(channelName);
    if (!handlers) {
      throw new Error(`Not subscribed to channel: ${channelName}`);
    }
    
    handlers.add(handler as (data: unknown) => void);
    
    return () => {
      handlers.delete(handler as (data: unknown) => void);
    };
  }
  
  broadcast(channelName: string, data: unknown): void {
    this.ws.send({
      type: 'channel.message',
      channel: channelName,
      data,
    });
  }
}
```

### Presence Tracking

```typescript
// src/lib/websocket/presence.ts
export interface PresenceState {
  id: string;
  userId: string;
  status: 'online' | 'away' | 'offline';
  lastSeen: number;
  metadata?: Record<string, unknown>;
}

export class PresenceChannel {
  private members = new Map<string, PresenceState>();
  private onJoin?: (member: PresenceState) => void;
  private onLeave?: (member: PresenceState) => void;
  private onUpdate?: (member: PresenceState) => void;
  
  constructor(
    private ws: WebSocketClient,
    private channelName: string
  ) {
    this.setupListeners();
  }
  
  private setupListeners(): void {
    this.ws.subscribe(`presence.${this.channelName}.join`, (data) => {
      const member = data as PresenceState;
      this.members.set(member.id, member);
      this.onJoin?.(member);
    });
    
    this.ws.subscribe(`presence.${this.channelName}.leave`, (data) => {
      const member = data as PresenceState;
      this.members.delete(member.id);
      this.onLeave?.(member);
    });
    
    this.ws.subscribe(`presence.${this.channelName}.update`, (data) => {
      const member = data as PresenceState;
      this.members.set(member.id, member);
      this.onUpdate?.(member);
    });
  }
  
  async track(metadata?: Record<string, unknown>): Promise<void> {
    await this.ws.request('presence.track', {
      channel: this.channelName,
      metadata,
    });
  }
  
  async updateStatus(status: PresenceState['status']): Promise<void> {
    await this.ws.request('presence.update', {
      channel: this.channelName,
      status,
    });
  }
  
  getMembers(): PresenceState[] {
    return Array.from(this.members.values());
  }
  
  getMember(id: string): PresenceState | undefined {
    return this.members.get(id);
  }
}
```

## Server Implementation

### Node.js WebSocket Server

```typescript
// src/server/websocket/WebSocketServer.ts
import { WebSocketServer as WSServer, WebSocket } from 'ws';
import { IncomingMessage } from 'http';

interface Client {
  id: string;
  socket: WebSocket;
  userId?: string;
  channels: Set<string>;
  lastPing: number;
}

export class WebSocketServer {
  private wss: WSServer;
  private clients = new Map<string, Client>();
  private channels = new Map<string, Set<string>>();
  
  constructor(port: number) {
    this.wss = new WSServer({ port });
    this.setupServer();
    this.startHeartbeatCheck();
  }
  
  private setupServer(): void {
    this.wss.on('connection', (socket: WebSocket, request: IncomingMessage) => {
      const clientId = crypto.randomUUID();
      const client: Client = {
        id: clientId,
        socket,
        channels: new Set(),
        lastPing: Date.now(),
      };
      
      this.clients.set(clientId, client);
      console.log(`[WS] Client connected: ${clientId}`);
      
      socket.on('message', (data) => {
        this.handleMessage(client, data.toString());
      });
      
      socket.on('close', () => {
        this.handleDisconnect(client);
      });
      
      socket.on('error', (error) => {
        console.error(`[WS] Client error (${clientId}):`, error);
      });
    });
  }
  
  private handleMessage(client: Client, raw: string): void {
    try {
      const message = JSON.parse(raw);
      
      switch (message.type) {
        case 'ping':
          client.lastPing = Date.now();
          this.send(client, { type: 'pong', timestamp: Date.now() });
          break;
          
        case 'auth.request':
          this.handleAuth(client, message);
          break;
          
        case 'subscribe':
          this.handleSubscribe(client, message);
          break;
          
        case 'unsubscribe':
          this.handleUnsubscribe(client, message);
          break;
          
        case 'channel.message':
          this.handleChannelMessage(client, message);
          break;
          
        default:
          this.handleCustomMessage(client, message);
      }
    } catch (error) {
      console.error('[WS] Failed to handle message:', error);
      this.send(client, {
        type: 'error',
        error: 'Invalid message format',
        requestId: undefined,
      });
    }
  }
  
  private async handleAuth(client: Client, message: any): Promise<void> {
    try {
      // Verify token (implement your auth logic)
      const userId = await this.verifyToken(message.data.token);
      client.userId = userId;
      
      this.send(client, {
        type: 'auth.success',
        requestId: message.requestId,
        data: { userId },
      });
    } catch (error) {
      this.send(client, {
        type: 'auth.failure',
        requestId: message.requestId,
        error: 'Authentication failed',
      });
    }
  }
  
  private handleSubscribe(client: Client, message: any): void {
    const channel = message.data.channel;
    
    // Add client to channel
    if (!this.channels.has(channel)) {
      this.channels.set(channel, new Set());
    }
    this.channels.get(channel)!.add(client.id);
    client.channels.add(channel);
    
    this.send(client, {
      type: 'subscribed',
      requestId: message.requestId,
      data: { channel },
    });
  }
  
  private handleUnsubscribe(client: Client, message: any): void {
    const channel = message.data.channel;
    
    this.channels.get(channel)?.delete(client.id);
    client.channels.delete(channel);
    
    this.send(client, {
      type: 'unsubscribed',
      requestId: message.requestId,
      data: { channel },
    });
  }
  
  private handleChannelMessage(client: Client, message: any): void {
    const channel = message.channel;
    
    if (!client.channels.has(channel)) {
      this.send(client, {
        type: 'error',
        error: 'Not subscribed to channel',
      });
      return;
    }
    
    this.broadcastToChannel(channel, {
      type: 'channel.message',
      channel,
      data: message.data,
      sender: client.userId,
    }, client.id);
  }
  
  private handleDisconnect(client: Client): void {
    // Remove from all channels
    client.channels.forEach(channel => {
      this.channels.get(channel)?.delete(client.id);
    });
    
    this.clients.delete(client.id);
    console.log(`[WS] Client disconnected: ${client.id}`);
  }
  
  // Broadcasting
  broadcast(message: unknown, excludeClient?: string): void {
    this.clients.forEach((client, id) => {
      if (id !== excludeClient) {
        this.send(client, message);
      }
    });
  }
  
  broadcastToChannel(channel: string, message: unknown, excludeClient?: string): void {
    const clientIds = this.channels.get(channel);
    if (!clientIds) return;
    
    clientIds.forEach(clientId => {
      if (clientId !== excludeClient) {
        const client = this.clients.get(clientId);
        if (client) {
          this.send(client, message);
        }
      }
    });
  }
  
  private send(client: Client, message: unknown): void {
    if (client.socket.readyState === WebSocket.OPEN) {
      client.socket.send(JSON.stringify(message));
    }
  }
  
  // Heartbeat check
  private startHeartbeatCheck(): void {
    setInterval(() => {
      const now = Date.now();
      const timeout = 60000; // 1 minute
      
      this.clients.forEach((client, id) => {
        if (now - client.lastPing > timeout) {
          console.log(`[WS] Client timed out: ${id}`);
          client.socket.terminate();
          this.handleDisconnect(client);
        }
      });
    }, 30000);
  }
  
  private async verifyToken(token: string): Promise<string> {
    // Implement your token verification
    throw new Error('Not implemented');
  }
  
  private handleCustomMessage(client: Client, message: any): void {
    // Override for custom message handling
  }
}
```

## React Integration

### WebSocket Hook

```typescript
// src/hooks/useWebSocket.ts
import { useEffect, useRef, useState, useCallback } from 'react';
import { WebSocketClient, ConnectionState } from '@/lib/websocket/WebSocketClient';

interface UseWebSocketOptions {
  url: string;
  autoConnect?: boolean;
  onConnect?: () => void;
  onDisconnect?: () => void;
  onError?: (error: Error) => void;
}

export function useWebSocket(options: UseWebSocketOptions) {
  const { url, autoConnect = true, onConnect, onDisconnect, onError } = options;
  
  const clientRef = useRef<WebSocketClient | null>(null);
  const [connectionState, setConnectionState] = useState<ConnectionState>('disconnected');
  
  useEffect(() => {
    const client = new WebSocketClient({ url });
    clientRef.current = client;
    
    // Track state changes
    const stateInterval = setInterval(() => {
      setConnectionState(client.connectionState);
    }, 100);
    
    if (autoConnect) {
      client.connect()
        .then(() => onConnect?.())
        .catch((error) => onError?.(error));
    }
    
    return () => {
      clearInterval(stateInterval);
      client.disconnect();
    };
  }, [url]);
  
  const send = useCallback((data: Record<string, unknown>) => {
    clientRef.current?.send(data);
  }, []);
  
  const request = useCallback(<T>(type: string, data?: unknown) => {
    return clientRef.current?.request<T>(type, data);
  }, []);
  
  const subscribe = useCallback((type: string, handler: (data: unknown) => void) => {
    return clientRef.current?.subscribe(type, handler) ?? (() => {});
  }, []);
  
  return {
    connectionState,
    isConnected: connectionState === 'connected',
    send,
    request,
    subscribe,
    client: clientRef.current,
  };
}
```

### Channel Hook

```typescript
// src/hooks/useChannel.ts
import { useEffect, useState, useCallback } from 'react';
import { useWebSocket } from './useWebSocket';

export function useChannel<T = unknown>(channelName: string) {
  const { subscribe, send, isConnected, request } = useWebSocket({
    url: process.env.NEXT_PUBLIC_WS_URL!,
  });
  
  const [messages, setMessages] = useState<T[]>([]);
  const [isJoined, setIsJoined] = useState(false);
  
  useEffect(() => {
    if (!isConnected) return;
    
    // Join channel
    request('subscribe', { channel: channelName })
      .then(() => setIsJoined(true))
      .catch(console.error);
    
    // Subscribe to messages
    const unsubscribe = subscribe(`channel.${channelName}`, (data) => {
      setMessages(prev => [...prev, data as T]);
    });
    
    return () => {
      unsubscribe();
      request('unsubscribe', { channel: channelName });
      setIsJoined(false);
    };
  }, [channelName, isConnected]);
  
  const sendMessage = useCallback((data: T) => {
    send({
      type: 'channel.message',
      channel: channelName,
      data,
    });
  }, [channelName, send]);
  
  return {
    messages,
    sendMessage,
    isJoined,
    isConnected,
  };
}
```

## Error Handling

### Retry Strategies

```typescript
// src/lib/websocket/retry.ts
export interface RetryConfig {
  maxAttempts: number;
  baseDelay: number;
  maxDelay: number;
  backoffMultiplier: number;
}

export const defaultRetryConfig: RetryConfig = {
  maxAttempts: 5,
  baseDelay: 1000,
  maxDelay: 30000,
  backoffMultiplier: 2,
};

export function calculateDelay(attempt: number, config: RetryConfig): number {
  const delay = config.baseDelay * Math.pow(config.backoffMultiplier, attempt);
  const jitter = Math.random() * 1000;
  return Math.min(delay + jitter, config.maxDelay);
}

export function shouldRetry(attempt: number, error: Error, config: RetryConfig): boolean {
  if (attempt >= config.maxAttempts) {
    return false;
  }
  
  // Don't retry auth errors
  if (error.message.includes('auth') || error.message.includes('unauthorized')) {
    return false;
  }
  
  return true;
}
```

## Testing

### WebSocket Mocking

```typescript
// src/test/mocks/WebSocketMock.ts
export class WebSocketMock {
  static instances: WebSocketMock[] = [];
  
  readyState = WebSocket.CONNECTING;
  onopen?: () => void;
  onclose?: (event: CloseEvent) => void;
  onmessage?: (event: MessageEvent) => void;
  onerror?: (event: Event) => void;
  
  private sentMessages: string[] = [];
  
  constructor(public url: string) {
    WebSocketMock.instances.push(this);
    
    // Simulate connection
    setTimeout(() => {
      this.readyState = WebSocket.OPEN;
      this.onopen?.();
    }, 0);
  }
  
  send(data: string): void {
    this.sentMessages.push(data);
  }
  
  close(code?: number, reason?: string): void {
    this.readyState = WebSocket.CLOSED;
    this.onclose?.({ code: code ?? 1000, reason: reason ?? '' } as CloseEvent);
  }
  
  // Test helpers
  simulateMessage(data: unknown): void {
    this.onmessage?.({ data: JSON.stringify(data) } as MessageEvent);
  }
  
  simulateError(): void {
    this.onerror?.({} as Event);
  }
  
  getSentMessages(): unknown[] {
    return this.sentMessages.map(m => JSON.parse(m));
  }
  
  static reset(): void {
    WebSocketMock.instances = [];
  }
}

// Usage in tests
beforeEach(() => {
  (global as any).WebSocket = WebSocketMock;
  WebSocketMock.reset();
});
```

## Summary

| Pattern | Use Case |
|---------|----------|
| Request/Response | RPC-style operations with expected replies |
| Pub/Sub | Broadcasting events to multiple subscribers |
| Channels | Grouping connections by topic/room |
| Presence | Tracking online users in real-time |
| Heartbeat | Detecting stale connections |
| Reconnection | Automatic recovery from disconnects |
