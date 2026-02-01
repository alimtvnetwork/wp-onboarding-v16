# Message Queue Standards

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document establishes message queue patterns and standards for asynchronous communication, event-driven architectures, and distributed systems across PHP, TypeScript, and Python implementations.

## Message Structure

### Standard Message Envelope

```typescript
// src/lib/messaging/types.ts
export interface Message<T = unknown> {
  // Identification
  id: string;
  correlationId?: string;
  causationId?: string;
  
  // Routing
  type: string;
  version: string;
  source: string;
  destination?: string;
  
  // Payload
  data: T;
  
  // Metadata
  metadata: MessageMetadata;
}

export interface MessageMetadata {
  timestamp: number;
  contentType: string;
  encoding?: string;
  
  // Tracing
  traceId?: string;
  spanId?: string;
  
  // Retry handling
  attempt: number;
  maxAttempts: number;
  
  // TTL
  expiresAt?: number;
  
  // Custom headers
  headers: Record<string, string>;
}

// Message factory
export function createMessage<T>(
  type: string,
  data: T,
  options: Partial<Message<T>> = {}
): Message<T> {
  return {
    id: crypto.randomUUID(),
    type,
    version: '1.0.0',
    source: process.env.SERVICE_NAME ?? 'unknown',
    data,
    metadata: {
      timestamp: Date.now(),
      contentType: 'application/json',
      attempt: 1,
      maxAttempts: 3,
      headers: {},
      ...options.metadata,
    },
    ...options,
  };
}
```

### Message Types

```typescript
// Event: Something that happened (past tense, immutable)
export interface DomainEvent<T = unknown> extends Message<T> {
  type: `${string}.${string}ed` | `${string}.${string}Created` | `${string}.${string}Updated`;
}

// Command: Request to do something (imperative, can fail)
export interface Command<T = unknown> extends Message<T> {
  type: `${string}.${string}` | `Create${string}` | `Update${string}`;
  replyTo?: string;
}

// Query: Request for data (read-only)
export interface Query<T = unknown> extends Message<T> {
  type: `Get${string}` | `Find${string}` | `List${string}`;
  replyTo: string;
}

// Examples
const userCreatedEvent: DomainEvent<{ userId: string }> = {
  id: '123',
  type: 'user.created',
  version: '1.0.0',
  source: 'user-service',
  data: { userId: '456' },
  metadata: { /* ... */ },
};

const createOrderCommand: Command<{ items: string[] }> = {
  id: '789',
  type: 'CreateOrder',
  version: '1.0.0',
  source: 'api-gateway',
  replyTo: 'api-gateway.responses',
  data: { items: ['item1', 'item2'] },
  metadata: { /* ... */ },
};
```

## Producer Patterns

### Message Publisher

```typescript
// src/lib/messaging/publisher.ts
import { Message, createMessage } from './types';

export interface PublisherConfig {
  defaultExchange?: string;
  confirmPublish?: boolean;
  persistent?: boolean;
}

export abstract class MessagePublisher {
  protected config: Required<PublisherConfig>;
  
  constructor(config: PublisherConfig = {}) {
    this.config = {
      defaultExchange: '',
      confirmPublish: true,
      persistent: true,
      ...config,
    };
  }
  
  abstract connect(): Promise<void>;
  abstract disconnect(): Promise<void>;
  protected abstract doPublish(routingKey: string, message: Message): Promise<void>;
  
  async publish<T>(
    routingKey: string,
    type: string,
    data: T,
    options: Partial<Message<T>> = {}
  ): Promise<string> {
    const message = createMessage(type, data, options);
    
    console.log(`[Publisher] Publishing ${type} to ${routingKey}`, {
      messageId: message.id,
      correlationId: message.correlationId,
    });
    
    await this.doPublish(routingKey, message);
    
    return message.id;
  }
  
  async publishEvent<T>(
    eventType: string,
    data: T,
    options: Partial<Message<T>> = {}
  ): Promise<string> {
    const routingKey = eventType.replace('.', '/');
    return this.publish(routingKey, eventType, data, options);
  }
  
  async publishCommand<T>(
    commandType: string,
    data: T,
    replyTo?: string
  ): Promise<string> {
    return this.publish(`commands/${commandType}`, commandType, data, {
      ...(replyTo && { destination: replyTo }),
    });
  }
}

// Redis implementation
import { Redis } from 'ioredis';

export class RedisPublisher extends MessagePublisher {
  private redis: Redis;
  
  constructor(redisUrl: string, config?: PublisherConfig) {
    super(config);
    this.redis = new Redis(redisUrl);
  }
  
  async connect(): Promise<void> {
    // Redis connects automatically
  }
  
  async disconnect(): Promise<void> {
    await this.redis.quit();
  }
  
  protected async doPublish(routingKey: string, message: Message): Promise<void> {
    const serialized = JSON.stringify(message);
    
    if (this.config.persistent) {
      // Use streams for persistence
      await this.redis.xadd(
        routingKey,
        '*',
        'message',
        serialized
      );
    } else {
      // Use pub/sub for ephemeral messages
      await this.redis.publish(routingKey, serialized);
    }
  }
}
```

### Outbox Pattern

```typescript
// src/lib/messaging/outbox.ts
// Ensures messages are published even if the service crashes

export interface OutboxEntry {
  id: string;
  routingKey: string;
  message: string;
  status: 'pending' | 'published' | 'failed';
  attempts: number;
  createdAt: Date;
  publishedAt?: Date;
  error?: string;
}

export class OutboxPublisher {
  constructor(
    private db: Database,
    private publisher: MessagePublisher
  ) {}
  
  // Store message in outbox within same transaction as business data
  async storeInOutbox<T>(
    tx: Transaction,
    routingKey: string,
    type: string,
    data: T
  ): Promise<string> {
    const message = createMessage(type, data);
    
    await tx.execute(
      `INSERT INTO outbox (id, routing_key, message, status, attempts, created_at)
       VALUES (?, ?, ?, 'pending', 0, NOW())`,
      [message.id, routingKey, JSON.stringify(message)]
    );
    
    return message.id;
  }
  
  // Background processor publishes pending messages
  async processOutbox(): Promise<number> {
    const entries = await this.db.query<OutboxEntry>(
      `SELECT * FROM outbox 
       WHERE status = 'pending' 
       AND attempts < 5
       ORDER BY created_at ASC
       LIMIT 100
       FOR UPDATE SKIP LOCKED`
    );
    
    let published = 0;
    
    for (const entry of entries) {
      try {
        const message = JSON.parse(entry.message);
        await this.publisher.doPublish(entry.routingKey, message);
        
        await this.db.execute(
          `UPDATE outbox SET status = 'published', published_at = NOW() WHERE id = ?`,
          [entry.id]
        );
        
        published++;
      } catch (error) {
        await this.db.execute(
          `UPDATE outbox SET attempts = attempts + 1, error = ? WHERE id = ?`,
          [(error as Error).message, entry.id]
        );
      }
    }
    
    return published;
  }
  
  // Cleanup old entries
  async cleanup(olderThanDays: number): Promise<number> {
    const result = await this.db.execute(
      `DELETE FROM outbox 
       WHERE status = 'published' 
       AND published_at < DATE_SUB(NOW(), INTERVAL ? DAY)`,
      [olderThanDays]
    );
    
    return result.affectedRows;
  }
}
```

## Consumer Patterns

### Message Consumer

```typescript
// src/lib/messaging/consumer.ts
export interface ConsumerConfig {
  prefetch?: number;
  autoAck?: boolean;
  deadLetterQueue?: string;
  retryDelay?: number;
}

export type MessageHandler<T> = (
  message: Message<T>,
  context: ConsumerContext
) => Promise<void>;

export interface ConsumerContext {
  ack: () => Promise<void>;
  nack: (requeue?: boolean) => Promise<void>;
  reject: () => Promise<void>;
}

export abstract class MessageConsumer {
  protected handlers = new Map<string, MessageHandler<unknown>>();
  protected config: Required<ConsumerConfig>;
  
  constructor(config: ConsumerConfig = {}) {
    this.config = {
      prefetch: 10,
      autoAck: false,
      deadLetterQueue: 'dead-letter',
      retryDelay: 5000,
      ...config,
    };
  }
  
  abstract connect(): Promise<void>;
  abstract disconnect(): Promise<void>;
  abstract subscribe(queue: string): Promise<void>;
  
  on<T>(messageType: string, handler: MessageHandler<T>): void {
    this.handlers.set(messageType, handler as MessageHandler<unknown>);
  }
  
  protected async handleMessage(
    rawMessage: string,
    context: ConsumerContext
  ): Promise<void> {
    let message: Message;
    
    try {
      message = JSON.parse(rawMessage);
    } catch (error) {
      console.error('[Consumer] Failed to parse message:', error);
      await context.reject();
      return;
    }
    
    const handler = this.handlers.get(message.type);
    
    if (!handler) {
      console.warn(`[Consumer] No handler for message type: ${message.type}`);
      await context.reject();
      return;
    }
    
    try {
      console.log(`[Consumer] Processing ${message.type}`, {
        messageId: message.id,
        attempt: message.metadata.attempt,
      });
      
      await handler(message, context);
      
      if (!this.config.autoAck) {
        await context.ack();
      }
    } catch (error) {
      console.error(`[Consumer] Handler failed for ${message.type}:`, error);
      
      if (message.metadata.attempt < message.metadata.maxAttempts) {
        // Requeue with delay
        await this.scheduleRetry(message);
        await context.ack();
      } else {
        // Send to dead letter queue
        await this.sendToDeadLetter(message, error as Error);
        await context.ack();
      }
    }
  }
  
  protected abstract scheduleRetry(message: Message): Promise<void>;
  protected abstract sendToDeadLetter(message: Message, error: Error): Promise<void>;
}
```

### Consumer Groups

```typescript
// src/lib/messaging/consumer-group.ts
// For Redis Streams consumer groups

export class RedisConsumerGroup extends MessageConsumer {
  private redis: Redis;
  private groupName: string;
  private consumerId: string;
  private isRunning = false;
  
  constructor(
    redisUrl: string,
    groupName: string,
    config?: ConsumerConfig
  ) {
    super(config);
    this.redis = new Redis(redisUrl);
    this.groupName = groupName;
    this.consumerId = `${groupName}-${process.pid}-${Date.now()}`;
  }
  
  async connect(): Promise<void> {
    // Ensure consumer group exists
  }
  
  async disconnect(): Promise<void> {
    this.isRunning = false;
    await this.redis.quit();
  }
  
  async subscribe(stream: string): Promise<void> {
    // Create group if not exists
    try {
      await this.redis.xgroup('CREATE', stream, this.groupName, '0', 'MKSTREAM');
    } catch (error) {
      // Group already exists
    }
    
    this.isRunning = true;
    this.consumeLoop(stream);
  }
  
  private async consumeLoop(stream: string): Promise<void> {
    while (this.isRunning) {
      try {
        const results = await this.redis.xreadgroup(
          'GROUP',
          this.groupName,
          this.consumerId,
          'COUNT',
          this.config.prefetch,
          'BLOCK',
          5000,
          'STREAMS',
          stream,
          '>'
        );
        
        if (!results) continue;
        
        for (const [streamName, messages] of results) {
          for (const [messageId, fields] of messages) {
            const rawMessage = fields[1]; // 'message' field value
            
            await this.handleMessage(rawMessage, {
              ack: async () => {
                await this.redis.xack(stream, this.groupName, messageId);
              },
              nack: async (requeue = true) => {
                if (!requeue) {
                  await this.redis.xack(stream, this.groupName, messageId);
                }
              },
              reject: async () => {
                await this.redis.xack(stream, this.groupName, messageId);
              },
            });
          }
        }
      } catch (error) {
        console.error('[Consumer] Error in consume loop:', error);
        await new Promise(r => setTimeout(r, 1000));
      }
    }
  }
  
  protected async scheduleRetry(message: Message): Promise<void> {
    message.metadata.attempt++;
    const delay = this.config.retryDelay * Math.pow(2, message.metadata.attempt - 1);
    
    // Use sorted set for delayed messages
    await this.redis.zadd(
      'delayed-messages',
      Date.now() + delay,
      JSON.stringify(message)
    );
  }
  
  protected async sendToDeadLetter(message: Message, error: Error): Promise<void> {
    const dlqMessage = {
      ...message,
      metadata: {
        ...message.metadata,
        error: error.message,
        failedAt: Date.now(),
      },
    };
    
    await this.redis.xadd(
      this.config.deadLetterQueue,
      '*',
      'message',
      JSON.stringify(dlqMessage)
    );
  }
}
```

## Routing Patterns

### Topic-Based Routing

```typescript
// src/lib/messaging/router.ts
export class MessageRouter {
  private routes = new Map<string, string[]>();
  
  // Pattern matching: user.* matches user.created, user.updated
  addRoute(pattern: string, queue: string): void {
    if (!this.routes.has(pattern)) {
      this.routes.set(pattern, []);
    }
    this.routes.get(pattern)!.push(queue);
  }
  
  getQueues(routingKey: string): string[] {
    const queues: string[] = [];
    
    for (const [pattern, patternQueues] of this.routes) {
      if (this.matches(pattern, routingKey)) {
        queues.push(...patternQueues);
      }
    }
    
    return [...new Set(queues)];
  }
  
  private matches(pattern: string, routingKey: string): boolean {
    const patternParts = pattern.split('.');
    const keyParts = routingKey.split('.');
    
    for (let i = 0; i < patternParts.length; i++) {
      const patternPart = patternParts[i];
      const keyPart = keyParts[i];
      
      if (patternPart === '#') {
        return true; // Match remainder
      }
      
      if (patternPart === '*') {
        continue; // Match single word
      }
      
      if (patternPart !== keyPart) {
        return false;
      }
    }
    
    return patternParts.length === keyParts.length;
  }
}

// Usage
const router = new MessageRouter();
router.addRoute('user.*', 'user-events');
router.addRoute('order.created', 'order-processing');
router.addRoute('order.#', 'order-analytics');

router.getQueues('user.created'); // ['user-events']
router.getQueues('order.created'); // ['order-processing', 'order-analytics']
router.getQueues('order.item.added'); // ['order-analytics']
```

## Saga Pattern

### Distributed Transactions

```typescript
// src/lib/messaging/saga.ts
export interface SagaStep<T = unknown> {
  name: string;
  execute: (context: T) => Promise<void>;
  compensate: (context: T) => Promise<void>;
}

export class SagaOrchestrator<T> {
  private steps: SagaStep<T>[] = [];
  private executedSteps: SagaStep<T>[] = [];
  
  addStep(step: SagaStep<T>): this {
    this.steps.push(step);
    return this;
  }
  
  async execute(context: T): Promise<void> {
    this.executedSteps = [];
    
    for (const step of this.steps) {
      try {
        console.log(`[Saga] Executing step: ${step.name}`);
        await step.execute(context);
        this.executedSteps.push(step);
      } catch (error) {
        console.error(`[Saga] Step failed: ${step.name}`, error);
        await this.compensate(context);
        throw error;
      }
    }
  }
  
  private async compensate(context: T): Promise<void> {
    // Execute compensations in reverse order
    for (const step of this.executedSteps.reverse()) {
      try {
        console.log(`[Saga] Compensating step: ${step.name}`);
        await step.compensate(context);
      } catch (error) {
        console.error(`[Saga] Compensation failed: ${step.name}`, error);
        // Log but continue compensating other steps
      }
    }
  }
}

// Usage: Order creation saga
interface OrderContext {
  orderId: string;
  userId: string;
  items: string[];
  paymentId?: string;
  inventoryReserved?: boolean;
}

const orderSaga = new SagaOrchestrator<OrderContext>()
  .addStep({
    name: 'reserve-inventory',
    execute: async (ctx) => {
      await inventoryService.reserve(ctx.orderId, ctx.items);
      ctx.inventoryReserved = true;
    },
    compensate: async (ctx) => {
      if (ctx.inventoryReserved) {
        await inventoryService.release(ctx.orderId);
      }
    },
  })
  .addStep({
    name: 'process-payment',
    execute: async (ctx) => {
      ctx.paymentId = await paymentService.charge(ctx.userId, ctx.orderId);
    },
    compensate: async (ctx) => {
      if (ctx.paymentId) {
        await paymentService.refund(ctx.paymentId);
      }
    },
  })
  .addStep({
    name: 'create-shipment',
    execute: async (ctx) => {
      await shippingService.createShipment(ctx.orderId);
    },
    compensate: async (ctx) => {
      await shippingService.cancelShipment(ctx.orderId);
    },
  });

// Execute saga
await orderSaga.execute({
  orderId: '123',
  userId: '456',
  items: ['item1', 'item2'],
});
```

## Dead Letter Handling

### DLQ Processor

```typescript
// src/lib/messaging/dlq.ts
export interface DeadLetterEntry {
  message: Message;
  error: string;
  failedAt: number;
  originalQueue: string;
}

export class DeadLetterProcessor {
  constructor(
    private consumer: MessageConsumer,
    private publisher: MessagePublisher
  ) {}
  
  async processDeadLetters(
    handler: (entry: DeadLetterEntry) => Promise<'retry' | 'discard' | 'archive'>
  ): Promise<void> {
    await this.consumer.subscribe('dead-letter');
    
    this.consumer.on('*', async (message: Message<DeadLetterEntry>, ctx) => {
      const entry = message.data;
      
      console.log(`[DLQ] Processing dead letter: ${entry.message.id}`);
      
      const action = await handler(entry);
      
      switch (action) {
        case 'retry':
          // Reset attempt count and republish
          entry.message.metadata.attempt = 1;
          await this.publisher.publish(
            entry.originalQueue,
            entry.message.type,
            entry.message.data
          );
          break;
          
        case 'archive':
          // Store for later analysis
          await this.archiveMessage(entry);
          break;
          
        case 'discard':
          // Just acknowledge
          break;
      }
      
      await ctx.ack();
    });
  }
  
  private async archiveMessage(entry: DeadLetterEntry): Promise<void> {
    // Store in archive queue or database
  }
}
```

## Monitoring

### Message Metrics

```typescript
// src/lib/messaging/metrics.ts
export class MessageMetrics {
  private metrics = {
    published: new Map<string, number>(),
    consumed: new Map<string, number>(),
    failed: new Map<string, number>(),
    latency: new Map<string, number[]>(),
  };
  
  recordPublished(messageType: string): void {
    this.increment(this.metrics.published, messageType);
  }
  
  recordConsumed(messageType: string, latencyMs: number): void {
    this.increment(this.metrics.consumed, messageType);
    this.recordLatency(messageType, latencyMs);
  }
  
  recordFailed(messageType: string): void {
    this.increment(this.metrics.failed, messageType);
  }
  
  private increment(map: Map<string, number>, key: string): void {
    map.set(key, (map.get(key) ?? 0) + 1);
  }
  
  private recordLatency(messageType: string, latencyMs: number): void {
    if (!this.metrics.latency.has(messageType)) {
      this.metrics.latency.set(messageType, []);
    }
    const latencies = this.metrics.latency.get(messageType)!;
    latencies.push(latencyMs);
    
    // Keep last 1000 samples
    if (latencies.length > 1000) {
      latencies.shift();
    }
  }
  
  getStats(messageType: string): {
    published: number;
    consumed: number;
    failed: number;
    avgLatency: number;
    p95Latency: number;
  } {
    const latencies = this.metrics.latency.get(messageType) ?? [];
    const sorted = [...latencies].sort((a, b) => a - b);
    
    return {
      published: this.metrics.published.get(messageType) ?? 0,
      consumed: this.metrics.consumed.get(messageType) ?? 0,
      failed: this.metrics.failed.get(messageType) ?? 0,
      avgLatency: latencies.length > 0
        ? latencies.reduce((a, b) => a + b, 0) / latencies.length
        : 0,
      p95Latency: sorted[Math.floor(sorted.length * 0.95)] ?? 0,
    };
  }
}
```

## Testing

### Message Testing Utilities

```typescript
// src/test/messaging/test-utils.ts
export class InMemoryMessageBroker {
  private queues = new Map<string, Message[]>();
  private handlers = new Map<string, MessageHandler<unknown>[]>();
  
  async publish<T>(queue: string, message: Message<T>): Promise<void> {
    if (!this.queues.has(queue)) {
      this.queues.set(queue, []);
    }
    this.queues.get(queue)!.push(message);
    
    // Immediately invoke handlers
    const handlers = this.handlers.get(queue) ?? [];
    for (const handler of handlers) {
      await handler(message, {
        ack: async () => {},
        nack: async () => {},
        reject: async () => {},
      });
    }
  }
  
  subscribe<T>(queue: string, handler: MessageHandler<T>): void {
    if (!this.handlers.has(queue)) {
      this.handlers.set(queue, []);
    }
    this.handlers.get(queue)!.push(handler as MessageHandler<unknown>);
  }
  
  getMessages(queue: string): Message[] {
    return this.queues.get(queue) ?? [];
  }
  
  clear(): void {
    this.queues.clear();
    this.handlers.clear();
  }
}

// Usage in tests
describe('Order Service', () => {
  let broker: InMemoryMessageBroker;
  
  beforeEach(() => {
    broker = new InMemoryMessageBroker();
  });
  
  it('should publish order.created event', async () => {
    const orderService = new OrderService(broker);
    
    await orderService.createOrder({ items: ['item1'] });
    
    const messages = broker.getMessages('order-events');
    expect(messages).toHaveLength(1);
    expect(messages[0].type).toBe('order.created');
  });
});
```

## Summary

| Pattern | Use Case |
|---------|----------|
| Pub/Sub | Broadcast events to multiple consumers |
| Work Queue | Distribute tasks among workers |
| Request/Reply | Synchronous-like RPC over queues |
| Outbox | Guarantee delivery with transactions |
| Saga | Distributed transaction coordination |
| Dead Letter | Handle failed message processing |
| Consumer Groups | Horizontal scaling of consumers |
