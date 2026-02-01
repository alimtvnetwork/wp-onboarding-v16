# Caching Patterns

> Version: 1.0.0 | Last Updated: 2025-01-26

## Overview

This document defines caching patterns for optimizing application performance while maintaining data consistency.

---

## 1. Cache Layer Architecture

### 1.1 Multi-Layer Cache Strategy

```
┌─────────────────────────────────────────────────────────────┐
│                      Request Flow                           │
├─────────────────────────────────────────────────────────────┤
│  Browser Cache (L1)                                         │
│  ├── HTTP Cache Headers                                     │
│  ├── Service Worker Cache                                   │
│  └── localStorage / sessionStorage                          │
├─────────────────────────────────────────────────────────────┤
│  CDN / Edge Cache (L2)                                      │
│  ├── Static Assets                                          │
│  └── API Response Cache                                     │
├─────────────────────────────────────────────────────────────┤
│  Application Cache (L3)                                     │
│  ├── In-Memory (Request-scoped)                             │
│  ├── Redis / Memcached (Distributed)                        │
│  └── OPcache (PHP)                                          │
├─────────────────────────────────────────────────────────────┤
│  Database Cache (L4)                                        │
│  ├── Query Cache                                            │
│  ├── Result Set Cache                                       │
│  └── Connection Pooling                                     │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Cache Decision Matrix

| Data Type | TTL | Invalidation | Layer |
|-----------|-----|--------------|-------|
| Static assets | 1 year | Version hash | Browser + CDN |
| User session | 1 hour | On logout | Application |
| API responses | 5-60 min | Tag-based | Application |
| Database queries | 1-5 min | On mutation | Database |
| Computed data | Varies | Explicit | Application |

---

## 2. Cache Key Design

### 2.1 Key Naming Convention

```
Format: {prefix}:{version}:{entity}:{identifier}:{variant}

Examples:
- app:v1:user:123:profile
- app:v1:post:456:comments:page:1
- app:v1:config:settings:theme
```

### 2.2 Key Generation

**TypeScript**
```typescript
interface CacheKeyOptions {
  prefix?: string;
  version?: string;
  entity: string;
  id?: string | number;
  variant?: Record<string, string | number>;
}

class CacheKeyBuilder {
  private prefix: string = 'app';
  private version: string = 'v1';
  
  build(options: CacheKeyOptions): string {
    const parts: string[] = [
      options.prefix ?? this.prefix,
      options.version ?? this.version,
      options.entity,
    ];
    
    if (options.id !== undefined) {
      parts.push(String(options.id));
    }
    
    if (options.variant) {
      const variantParts = Object.entries(options.variant)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([k, v]) => `${k}:${v}`);
      parts.push(...variantParts);
    }
    
    return parts.join(':');
  }
  
  buildPattern(entity: string, id?: string): string {
    return `${this.prefix}:${this.version}:${entity}:${id ?? '*'}:*`;
  }
}

// Usage
const keyBuilder = new CacheKeyBuilder();

const userKey = keyBuilder.build({ 
  entity: 'user', 
  id: 123, 
  variant: { include: 'posts' } 
});
// Result: app:v1:user:123:include:posts
```

**PHP**
```php
class CacheKeyBuilder {
    private string $prefix = 'app';
    private string $version = 'v1';
    
    public function build(array $options): string {
        $parts = [
            $options['prefix'] ?? $this->prefix,
            $options['version'] ?? $this->version,
            $options['entity'],
        ];
        
        if (isset($options['id'])) {
            $parts[] = (string) $options['id'];
        }
        
        if (isset($options['variant'])) {
            ksort($options['variant']);
            foreach ($options['variant'] as $key => $value) {
                $parts[] = "{$key}:{$value}";
            }
        }
        
        return implode(':', $parts);
    }
    
    public function buildPattern(string $entity, ?string $id = null): string {
        return sprintf(
            '%s:%s:%s:%s:*',
            $this->prefix,
            $this->version,
            $entity,
            $id ?? '*'
        );
    }
}
```

---

## 3. Cache Implementations

### 3.1 In-Memory Cache (Request-Scoped)

**TypeScript**
```typescript
class RequestCache {
  private cache = new Map<string, { value: unknown; expiresAt: number }>();
  
  get<T>(key: string): T | undefined {
    const entry = this.cache.get(key);
    
    if (!entry) {
      return undefined;
    }
    
    if (entry.expiresAt < Date.now()) {
      this.cache.delete(key);
      return undefined;
    }
    
    return entry.value as T;
  }
  
  set<T>(key: string, value: T, ttlMs: number = 60000): void {
    this.cache.set(key, {
      value,
      expiresAt: Date.now() + ttlMs,
    });
  }
  
  delete(key: string): boolean {
    return this.cache.delete(key);
  }
  
  clear(): void {
    this.cache.clear();
  }
  
  async getOrSet<T>(
    key: string,
    factory: () => Promise<T>,
    ttlMs?: number
  ): Promise<T> {
    const cached = this.get<T>(key);
    if (cached !== undefined) {
      return cached;
    }
    
    const value = await factory();
    this.set(key, value, ttlMs);
    return value;
  }
}
```

### 3.2 Redis Cache

**TypeScript**
```typescript
import { Redis } from 'ioredis';

interface CacheOptions {
  ttl?: number;
  tags?: string[];
}

class RedisCache {
  constructor(private redis: Redis) {}
  
  async get<T>(key: string): Promise<T | null> {
    const data = await this.redis.get(key);
    if (!data) return null;
    
    try {
      return JSON.parse(data) as T;
    } catch {
      return null;
    }
  }
  
  async set<T>(key: string, value: T, options: CacheOptions = {}): Promise<void> {
    const serialized = JSON.stringify(value);
    const ttl = options.ttl ?? 3600; // Default 1 hour
    
    const pipeline = this.redis.pipeline();
    pipeline.setex(key, ttl, serialized);
    
    // Tag-based tracking
    if (options.tags?.length) {
      for (const tag of options.tags) {
        pipeline.sadd(`tag:${tag}`, key);
        pipeline.expire(`tag:${tag}`, ttl + 60);
      }
    }
    
    await pipeline.exec();
  }
  
  async delete(key: string): Promise<boolean> {
    const result = await this.redis.del(key);
    return result > 0;
  }
  
  async deleteByTag(tag: string): Promise<number> {
    const tagKey = `tag:${tag}`;
    const keys = await this.redis.smembers(tagKey);
    
    if (keys.length === 0) return 0;
    
    const pipeline = this.redis.pipeline();
    pipeline.del(...keys);
    pipeline.del(tagKey);
    
    await pipeline.exec();
    return keys.length;
  }
  
  async getOrSet<T>(
    key: string,
    factory: () => Promise<T>,
    options: CacheOptions = {}
  ): Promise<T> {
    const cached = await this.get<T>(key);
    if (cached !== null) {
      return cached;
    }
    
    const value = await factory();
    await this.set(key, value, options);
    return value;
  }
}
```

**PHP**
```php
class RedisCache implements CacheInterface {
    private Redis $redis;
    private CacheKeyBuilder $keyBuilder;
    
    public function __construct(Redis $redis) {
        $this->redis = $redis;
        $this->keyBuilder = new CacheKeyBuilder();
    }
    
    public function get(string $key): mixed {
        $data = $this->redis->get($key);
        
        if ($data === false) {
            return null;
        }
        
        return json_decode($data, true);
    }
    
    public function set(string $key, mixed $value, array $options = []): void {
        $serialized = json_encode($value);
        $ttl = $options['ttl'] ?? 3600;
        
        $this->redis->setex($key, $ttl, $serialized);
        
        // Tag-based tracking
        if (!empty($options['tags'])) {
            foreach ($options['tags'] as $tag) {
                $tagKey = "tag:{$tag}";
                $this->redis->sadd($tagKey, $key);
                $this->redis->expire($tagKey, $ttl + 60);
            }
        }
    }
    
    public function deleteByTag(string $tag): int {
        $tagKey = "tag:{$tag}";
        $keys = $this->redis->smembers($tagKey);
        
        if (empty($keys)) {
            return 0;
        }
        
        $this->redis->del(...$keys);
        $this->redis->del($tagKey);
        
        return count($keys);
    }
    
    public function getOrSet(string $key, callable $factory, array $options = []): mixed {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $factory();
        $this->set($key, $value, $options);
        
        return $value;
    }
}
```

---

## 4. Caching Patterns

### 4.1 Cache-Aside (Lazy Loading)

```typescript
class UserService {
  constructor(
    private cache: RedisCache,
    private repository: UserRepository
  ) {}
  
  async getUser(id: string): Promise<User | null> {
    const cacheKey = `user:${id}`;
    
    // 1. Check cache
    const cached = await this.cache.get<User>(cacheKey);
    if (cached) {
      return cached;
    }
    
    // 2. Cache miss - load from database
    const user = await this.repository.findById(id);
    if (!user) {
      return null;
    }
    
    // 3. Populate cache
    await this.cache.set(cacheKey, user, {
      ttl: 3600,
      tags: [`user:${id}`],
    });
    
    return user;
  }
  
  async updateUser(id: string, data: UpdateUserDto): Promise<User> {
    // 1. Update database
    const user = await this.repository.update(id, data);
    
    // 2. Invalidate cache
    await this.cache.deleteByTag(`user:${id}`);
    
    return user;
  }
}
```

### 4.2 Write-Through

```typescript
class WriteThoughCache<T> {
  constructor(
    private cache: RedisCache,
    private repository: Repository<T>
  ) {}
  
  async save(entity: T): Promise<T> {
    // 1. Write to database
    const saved = await this.repository.save(entity);
    
    // 2. Write to cache immediately
    const cacheKey = this.buildKey(saved);
    await this.cache.set(cacheKey, saved, { ttl: 3600 });
    
    return saved;
  }
  
  async get(id: string): Promise<T | null> {
    const cacheKey = `entity:${id}`;
    
    // Cache should always be populated via write-through
    const cached = await this.cache.get<T>(cacheKey);
    if (cached) {
      return cached;
    }
    
    // Fallback for cache miss (cold start, eviction)
    const entity = await this.repository.findById(id);
    if (entity) {
      await this.cache.set(cacheKey, entity, { ttl: 3600 });
    }
    
    return entity;
  }
}
```

### 4.3 Read-Through with Stale-While-Revalidate

```typescript
interface CacheEntry<T> {
  data: T;
  staleAt: number;
  expiresAt: number;
}

class StaleWhileRevalidateCache {
  private revalidating = new Set<string>();
  
  async get<T>(
    key: string,
    fetcher: () => Promise<T>,
    options: { freshFor: number; staleFor: number }
  ): Promise<T> {
    const now = Date.now();
    const entry = await this.cache.get<CacheEntry<T>>(key);
    
    // Cache hit - check freshness
    if (entry) {
      const isFresh = entry.staleAt > now;
      const isStale = entry.staleAt <= now && entry.expiresAt > now;
      
      if (isFresh) {
        return entry.data;
      }
      
      if (isStale) {
        // Return stale data, revalidate in background
        this.revalidateInBackground(key, fetcher, options);
        return entry.data;
      }
    }
    
    // Cache miss or expired - fetch synchronously
    return this.fetchAndCache(key, fetcher, options);
  }
  
  private async revalidateInBackground<T>(
    key: string,
    fetcher: () => Promise<T>,
    options: { freshFor: number; staleFor: number }
  ): Promise<void> {
    if (this.revalidating.has(key)) {
      return; // Already revalidating
    }
    
    this.revalidating.add(key);
    
    try {
      await this.fetchAndCache(key, fetcher, options);
    } finally {
      this.revalidating.delete(key);
    }
  }
  
  private async fetchAndCache<T>(
    key: string,
    fetcher: () => Promise<T>,
    options: { freshFor: number; staleFor: number }
  ): Promise<T> {
    const data = await fetcher();
    const now = Date.now();
    
    const entry: CacheEntry<T> = {
      data,
      staleAt: now + options.freshFor,
      expiresAt: now + options.freshFor + options.staleFor,
    };
    
    await this.cache.set(key, entry, {
      ttl: Math.ceil((options.freshFor + options.staleFor) / 1000),
    });
    
    return data;
  }
}
```

---

## 5. Cache Invalidation

### 5.1 Tag-Based Invalidation

```typescript
class TaggedCache {
  constructor(private redis: Redis) {}
  
  async invalidateTags(tags: string[]): Promise<void> {
    const pipeline = this.redis.pipeline();
    
    for (const tag of tags) {
      const tagKey = `tag:${tag}`;
      const keys = await this.redis.smembers(tagKey);
      
      if (keys.length > 0) {
        pipeline.del(...keys);
      }
      pipeline.del(tagKey);
    }
    
    await pipeline.exec();
  }
}

// Usage with domain events
class PostService {
  async createPost(data: CreatePostDto): Promise<Post> {
    const post = await this.repository.create(data);
    
    // Invalidate related caches
    await this.cache.invalidateTags([
      `user:${post.authorId}:posts`,
      'posts:feed',
      'posts:recent',
    ]);
    
    return post;
  }
}
```

### 5.2 Time-Based Expiration Strategies

```typescript
const TTL_STRATEGIES = {
  // Short-lived: Frequently changing data
  realtime: 30,           // 30 seconds
  shortLived: 300,        // 5 minutes
  
  // Medium-lived: Moderately changing data
  standard: 3600,         // 1 hour
  session: 7200,          // 2 hours
  
  // Long-lived: Rarely changing data
  config: 86400,          // 24 hours
  static: 604800,         // 7 days
  permanent: 2592000,     // 30 days
};

function selectTTL(dataType: keyof typeof TTL_STRATEGIES): number {
  return TTL_STRATEGIES[dataType];
}
```

---

## 6. HTTP Caching

### 6.1 Cache Headers

```typescript
interface CacheControlOptions {
  public?: boolean;
  private?: boolean;
  maxAge?: number;
  sMaxAge?: number;
  staleWhileRevalidate?: number;
  staleIfError?: number;
  noCache?: boolean;
  noStore?: boolean;
  mustRevalidate?: boolean;
  immutable?: boolean;
}

function buildCacheControl(options: CacheControlOptions): string {
  const directives: string[] = [];
  
  if (options.public) directives.push('public');
  if (options.private) directives.push('private');
  if (options.maxAge !== undefined) directives.push(`max-age=${options.maxAge}`);
  if (options.sMaxAge !== undefined) directives.push(`s-maxage=${options.sMaxAge}`);
  if (options.staleWhileRevalidate !== undefined) {
    directives.push(`stale-while-revalidate=${options.staleWhileRevalidate}`);
  }
  if (options.staleIfError !== undefined) {
    directives.push(`stale-if-error=${options.staleIfError}`);
  }
  if (options.noCache) directives.push('no-cache');
  if (options.noStore) directives.push('no-store');
  if (options.mustRevalidate) directives.push('must-revalidate');
  if (options.immutable) directives.push('immutable');
  
  return directives.join(', ');
}

// Common patterns
const CACHE_POLICIES = {
  noCache: buildCacheControl({ noStore: true, noCache: true }),
  
  privateShort: buildCacheControl({
    private: true,
    maxAge: 300,
    mustRevalidate: true,
  }),
  
  publicLong: buildCacheControl({
    public: true,
    maxAge: 86400,
    sMaxAge: 604800,
    staleWhileRevalidate: 86400,
  }),
  
  immutableAsset: buildCacheControl({
    public: true,
    maxAge: 31536000,
    immutable: true,
  }),
};
```

### 6.2 ETag Implementation

```typescript
import crypto from 'crypto';

class ETagGenerator {
  generate(content: string | Buffer): string {
    const hash = crypto
      .createHash('md5')
      .update(content)
      .digest('hex');
    return `"${hash}"`;
  }
  
  generateWeak(lastModified: Date, size: number): string {
    const timestamp = lastModified.getTime().toString(16);
    return `W/"${timestamp}-${size.toString(16)}"`;
  }
}

// Middleware
function conditionalGetMiddleware(
  req: Request,
  res: Response,
  next: NextFunction
) {
  const originalJson = res.json.bind(res);
  
  res.json = (data: unknown) => {
    const body = JSON.stringify(data);
    const etag = etagGenerator.generate(body);
    
    res.setHeader('ETag', etag);
    
    const clientEtag = req.headers['if-none-match'];
    if (clientEtag === etag) {
      return res.status(304).end();
    }
    
    return originalJson(data);
  };
  
  next();
}
```

---

## 7. React Query Integration

### 7.1 Query Configuration

```typescript
import { QueryClient, useQuery, useMutation } from '@tanstack/react-query';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000,        // 5 minutes
      gcTime: 30 * 60 * 1000,          // 30 minutes (formerly cacheTime)
      retry: 3,
      refetchOnWindowFocus: false,
      refetchOnReconnect: true,
    },
  },
});

// Query key factory
const queryKeys = {
  users: {
    all: ['users'] as const,
    lists: () => [...queryKeys.users.all, 'list'] as const,
    list: (filters: UserFilters) => [...queryKeys.users.lists(), filters] as const,
    details: () => [...queryKeys.users.all, 'detail'] as const,
    detail: (id: string) => [...queryKeys.users.details(), id] as const,
  },
  posts: {
    all: ['posts'] as const,
    byUser: (userId: string) => [...queryKeys.posts.all, 'user', userId] as const,
  },
};

// Usage
function useUser(id: string) {
  return useQuery({
    queryKey: queryKeys.users.detail(id),
    queryFn: () => fetchUser(id),
    staleTime: 10 * 60 * 1000,  // Override for user data
  });
}

function useUpdateUser() {
  return useMutation({
    mutationFn: updateUser,
    onSuccess: (data, variables) => {
      // Update cache directly
      queryClient.setQueryData(
        queryKeys.users.detail(variables.id),
        data
      );
      
      // Invalidate related queries
      queryClient.invalidateQueries({
        queryKey: queryKeys.users.lists(),
      });
    },
  });
}
```

---

## 8. Cache Warming

### 8.1 Preloading Strategy

```typescript
class CacheWarmer {
  constructor(
    private cache: RedisCache,
    private userService: UserService,
    private configService: ConfigService
  ) {}
  
  async warmOnStartup(): Promise<void> {
    console.log('Starting cache warm-up...');
    
    await Promise.all([
      this.warmConfig(),
      this.warmActiveUsers(),
      this.warmPopularContent(),
    ]);
    
    console.log('Cache warm-up complete');
  }
  
  private async warmConfig(): Promise<void> {
    const config = await this.configService.loadAll();
    await this.cache.set('config:all', config, {
      ttl: 86400,
      tags: ['config'],
    });
  }
  
  private async warmActiveUsers(): Promise<void> {
    const activeUsers = await this.userService.getRecentlyActive(100);
    
    await Promise.all(
      activeUsers.map((user) =>
        this.cache.set(`user:${user.id}`, user, {
          ttl: 3600,
          tags: [`user:${user.id}`],
        })
      )
    );
  }
  
  private async warmPopularContent(): Promise<void> {
    // Warm popular/frequently accessed content
    const popular = await this.contentService.getPopular(50);
    
    for (const item of popular) {
      await this.cache.set(`content:${item.id}`, item, {
        ttl: 1800,
        tags: ['content', `content:${item.id}`],
      });
    }
  }
}
```

---

## 9. Cache Monitoring

### 9.1 Metrics Collection

```typescript
interface CacheMetrics {
  hits: number;
  misses: number;
  hitRate: number;
  avgLatency: number;
  evictions: number;
  memoryUsage: number;
}

class CacheMetricsCollector {
  private hits = 0;
  private misses = 0;
  private latencies: number[] = [];
  
  recordHit(latencyMs: number): void {
    this.hits++;
    this.latencies.push(latencyMs);
  }
  
  recordMiss(latencyMs: number): void {
    this.misses++;
    this.latencies.push(latencyMs);
  }
  
  getMetrics(): CacheMetrics {
    const total = this.hits + this.misses;
    
    return {
      hits: this.hits,
      misses: this.misses,
      hitRate: total > 0 ? this.hits / total : 0,
      avgLatency: this.latencies.length > 0
        ? this.latencies.reduce((a, b) => a + b, 0) / this.latencies.length
        : 0,
      evictions: 0, // Get from Redis INFO
      memoryUsage: 0, // Get from Redis INFO
    };
  }
  
  reset(): void {
    this.hits = 0;
    this.misses = 0;
    this.latencies = [];
  }
}
```

---

## Cache Strategy Checklist

| Aspect | Consideration | Action |
|--------|---------------|--------|
| Key Design | Consistent naming | Use key builder pattern |
| TTL | Match data volatility | Short for dynamic, long for static |
| Invalidation | Choose strategy | Tag-based or event-driven |
| Serialization | Performance | JSON for simplicity, MessagePack for perf |
| Memory | Size limits | Set maxmemory policy |
| Monitoring | Hit rate tracking | Log metrics, alert on low hit rate |
| Warming | Cold start mitigation | Preload critical data |
| Fallback | Cache unavailable | Graceful degradation to database |

---

## Cross-References

- [01-logging-system-systems.md](../02-systems/01-logging-system-systems.md) - Cache metrics logging
- [03-api-conventions-quality.md](../03-quality/03-api-conventions-quality.md) - HTTP cache headers
- [01-security-patterns-advanced.md](./01-security-patterns-advanced.md) - Secure cache data
