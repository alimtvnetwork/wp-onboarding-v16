# 57. Caching System

## Overview

The caching system provides multi-layer caching for PHP applications, utilizing session data, cookies, and optional backend caches (Memcached/Redis) to minimize database queries and improve response times. It includes page-level HTML caching for authenticated users.

---

## 1. Caching Architecture

### 1.1 Cache Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                      CACHE LAYER HIERARCHY                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Layer 1: Browser Cache (HTTP Headers)                          │
│  └── Static assets, theme CSS, JS bundles                       │
│  └── TTL: 1 year for versioned assets                           │
│                                                                  │
│  Layer 2: Page Cache (Full HTML)                                │
│  └── Pre-rendered pages for authenticated users                 │
│  └── TTL: 5 minutes (configurable)                              │
│  └── Keyed by: user_id + route + theme_hash                     │
│                                                                  │
│  Layer 3: Object Cache (Data)                                   │
│  └── Database query results, computed values                    │
│  └── TTL: Varies by data type                                   │
│  └── Backend: Memcached > Redis > APCu > File                   │
│                                                                  │
│  Layer 4: Session Cache                                         │
│  └── User-specific data, permissions, theme                     │
│  └── TTL: Session lifetime                                      │
│                                                                  │
│  Layer 5: Request Cache (Per-Request)                           │
│  └── Prevents duplicate queries in single request               │
│  └── TTL: Request lifetime only                                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Cache Backend Detection

```php
class CacheBackendFactory
{
    public function detectBestBackend(): CacheBackendInterface
    {
        // Priority order
        if ($this->isMemcachedAvailable()) {
            return new MemcachedBackend($this->getMemcachedConfig());
        }
        
        if ($this->isRedisAvailable()) {
            return new RedisBackend($this->getRedisConfig());
        }
        
        if ($this->isApcuAvailable()) {
            return new ApcuBackend();
        }
        
        // Fallback to file-based cache
        return new FileBackend($this->getCacheDir());
    }
    
    private function isMemcachedAvailable(): bool
    {
        return extension_loaded('memcached') 
            && is_not_empty(getenv('MEMCACHED_HOST'));
    }
    
    private function isRedisAvailable(): bool
    {
        return extension_loaded('redis') 
            && is_not_empty(getenv('REDIS_HOST'));
    }
}
```

---

## 2. Cache Key Patterns

---

### ⚠️ IMPLEMENTATION WARNING - MEDIUM RISK AREA: Cache Key Versioning

> **AI IMPLEMENTATION ALERT**: Cache keys MUST include a version suffix for safe schema changes.
> 
> **THE WRONG WAY**:
> ```php
> // ❌ WRONG: No version - old cached data may crash after code changes
> $key = "eqm:user:{$userId}:profile";
> $key = "eqm:exam:{$slug}:content";
> ```
> 
> **THE CORRECT WAY**:
> ```php
> // ✅ CORRECT: Version suffix allows safe cache invalidation on schema changes
> $key = "eqm:user:{$userId}:profile:v1";
> $key = "eqm:exam:{$slug}:content:v{$this->cacheSchemaVersion}";
> ```
> 
> **WHY THIS MATTERS**: After code changes, old cached data may have different structure. Versioned keys automatically expire old caches without manual intervention.

---

### Cache Key Pre-Implementation Checklist

- [ ] ✅ Cache key includes `:{version}` suffix (e.g., `:v1`, `:v2`)
- [ ] ✅ Page cache keys include `user_{id}` AND `theme_{hash}`
- [ ] ✅ Exam cache keys include exam slug, not exam ID
- [ ] ❌ You do NOT use unversioned cache keys
- [ ] ❌ You do NOT cache user-specific data with only route as key

---

### 2.1 Key Naming Convention (MANDATORY)

```
eqm:{scope}:{type}:{identifier}:{version}

Examples:
- eqm:user:123:profile:v1
- eqm:exam:certification-2024:content:v3
- eqm:page:dashboard:user_123:theme_abc123:v1
- eqm:query:participants:exam_45:page_1:v1
```

### 2.2 Key Components

| Component | Description | Example | Required? |
|-----------|-------------|---------|-----------|
| `scope` | Cache scope | `user`, `exam`, `global`, `page` | ✅ Yes |
| `type` | Data type | `profile`, `content`, `theme`, `query` | ✅ Yes |
| `identifier` | Unique ID | `123`, `certification-2024` | ✅ Yes |
| `version` | Cache version | `v1`, content hash | ✅ Yes |

### 2.3 Cache Tags for Invalidation

```php
class CacheTags
{
    const USER = 'user:{id}';
    const EXAM = 'exam:{slug}';
    const THEME = 'theme:{slug}';
    const PARTICIPANT = 'participant:{id}';
    const GLOBAL = 'global';
    const SETTINGS = 'settings';
}

// Usage
$cache->set($key, $value, tags: ['user:123', 'exam:cert-2024']);
$cache->invalidateByTag('exam:cert-2024'); // Clears all related
```

---

## 3. Session-Based Caching

### 3.1 Session Data Structure

```php
$_SESSION['eqm'] = [
    'user' => [
        'id' => 123,
        'email' => 'user@example.com',
        'roles' => ['participant'],
        'permissions' => ['view_exams', 'submit_answers'],
        'cachedAt' => 1705312800
    ],
    'theme' => [
        'slug' => 'default',
        'hash' => 'abc123', // For cache invalidation
        'cssVariables' => ':root { ... }',
        'cachedAt' => 1705312800
    ],
    'exams' => [
        'certification-2024' => [
            'status' => 'IN_PROGRESS',
            'progress' => 45,
            'currentSection' => 3,
            'cachedAt' => 1705312800
        ]
    ],
    'lastActivity' => 1705312900
];
```

### 3.2 Session Cache Service

```php
class SessionCacheService
{
    private const TTL_USER = 3600;      // 1 hour
    private const TTL_THEME = 86400;    // 24 hours
    private const TTL_EXAM = 300;       // 5 minutes
    
    public function getUserFromSession(): ?array
    {
        $userData = $_SESSION['eqm']['user'] ?? null;
        
        if (is_null($userData)) {
            return null;
        }
        
        // Check if expired
        if ($this->isExpired($userData['cachedAt'], self::TTL_USER)) {
            return null;
        }
        
        return $userData;
    }
    
    public function cacheUser(array $user): void
    {
        $_SESSION['eqm']['user'] = array_merge($user, [
            'cachedAt' => time()
        ]);
    }
    
    public function invalidateUser(): void
    {
        unset($_SESSION['eqm']['user']);
    }
    
    public function getThemeFromSession(): ?array
    {
        $theme = $_SESSION['eqm']['theme'] ?? null;
        
        if (is_null($theme)) {
            return null;
        }
        
        // Also check if global theme has changed
        $currentHash = $this->themeService->getCurrentHash();
        if ($theme['hash'] !== $currentHash) {
            return null;
        }
        
        return $theme;
    }
}
```

---

## 4. Cookie-Based Cache Identification

### 4.1 Cache-Related Cookies

```php
class CacheCookies
{
    // User cache identifier (survives sessions)
    const CACHE_ID = 'eqm_cache_%s';        // %s = examSlug
    
    // Theme preference
    const THEME_PREF = 'eqm_theme_pref';
    
    // Page cache eligibility
    const PAGE_CACHE = 'eqm_page_cache';
    
    // Last modified tracker
    const LAST_MOD = 'eqm_last_mod_%s';     // %s = examSlug
}
```

### 4.2 Cache Cookie Management

```php
class CacheCookieManager
{
    /**
     * Set cache identifier for user
     * Used to quickly identify cached content
     */
    public function setCacheIdentifier(int $userId, string $examSlug): void
    {
        $cacheId = $this->generateCacheId($userId, $examSlug);
        
        setcookie(
            sprintf(CacheCookies::CACHE_ID, $examSlug),
            $cacheId,
            [
                'expires' => time() + (7 * 24 * 60 * 60), // 7 days
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }
    
    /**
     * Check if user has valid cache identifier
     */
    public function hasValidCacheId(string $examSlug): bool
    {
        $cookieName = sprintf(CacheCookies::CACHE_ID, $examSlug);
        $cacheId = $_COOKIE[$cookieName] ?? null;
        
        if (is_null($cacheId)) {
            return false;
        }
        
        return $this->validateCacheId($cacheId);
    }
}
```

---

## 5. Page-Level HTML Caching

### 5.1 Page Cache Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    PAGE CACHE FLOW                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Request → [Check Auth Cookie] → [Check Page Cache]             │
│                                      ↓                           │
│                          ┌──────────┴──────────┐                 │
│                          │                     │                 │
│                       HIT ✓                 MISS ✗               │
│                          │                     │                 │
│                    Serve HTML           Render Page              │
│                    (< 5ms)                   │                   │
│                                              ↓                   │
│                                        Store in Cache            │
│                                              │                   │
│                                        Serve Response            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 Page Cache Implementation

```php
class PageCacheService
{
    private const DEFAULT_TTL = 300; // 5 minutes
    
    // Pages eligible for caching
    private const CACHEABLE_PAGES = [
        'dashboard',
        'exam-view',
        'section-view',
        'profile'
    ];
    
    // Pages never cached
    private const NEVER_CACHE = [
        'login',
        'signup',
        'submit-answer',
        'admin/*'
    ];
    
    /**
     * Check if current request can use cached page
     */
    public function canServeCached(): bool
    {
        // Only GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        
        // Check if user is authenticated
        if ($this->isGuest()) {
            return false; // Don't cache guest pages
        }
        
        // Check if page is cacheable
        $route = $this->getCurrentRoute();
        if (in_array($route, self::NEVER_CACHE)) {
            return false;
        }
        
        // Check for cache bypass headers
        if ($this->hasCacheBypass()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get cached page if available
     */
    public function getCachedPage(): ?string
    {
        $cacheKey = $this->getPageCacheKey();
        $cached = $this->cache->get($cacheKey);
        
        if (is_null($cached)) {
            return null;
        }
        
        // Validate freshness
        if ($this->isStale($cached)) {
            return null;
        }
        
        // Log cache hit
        $this->logger->debug('Page cache hit', ['key' => $cacheKey]);
        
        return $cached['html'];
    }
    
    /**
     * Store rendered page in cache
     */
    public function cachePage(string $html): void
    {
        $cacheKey = $this->getPageCacheKey();
        
        $this->cache->set($cacheKey, [
            'html' => $html,
            'cachedAt' => time(),
            'themeHash' => $this->getThemeHash(),
            'userHash' => $this->getUserHash()
        ], ttl: self::DEFAULT_TTL);
    }
    
    /**
     * Generate cache key for current page
     */
    private function getPageCacheKey(): string
    {
        $userId = $this->auth->getUserId();
        $route = $this->getCurrentRoute();
        $themeHash = $this->getThemeHash();
        $queryParams = $this->normalizeQueryParams();
        
        return sprintf(
            'eqm:page:%s:user_%d:theme_%s:%s',
            $route,
            $userId,
            substr($themeHash, 0, 8),
            md5($queryParams)
        );
    }
}
```

### 5.3 Cache Invalidation Triggers

```php
class CacheInvalidator
{
    /**
     * Events that trigger cache invalidation
     */
    private const INVALIDATION_MAP = [
        // User actions
        'user.login' => ['user:{id}', 'page:*:user_{id}:*'],
        'user.logout' => ['user:{id}', 'page:*:user_{id}:*'],
        'user.profile_update' => ['user:{id}'],
        
        // Exam actions
        'exam.updated' => ['exam:{slug}', 'page:exam-view:{slug}:*'],
        'exam.content_changed' => ['exam:{slug}:content'],
        
        // Participant actions
        'participant.progress' => ['participant:{id}', 'page:dashboard:user_{userId}:*'],
        'participant.submit' => ['participant:{id}'],
        
        // Admin actions
        'theme.updated' => ['theme:*', 'page:*:*:theme_*'],
        'settings.changed' => ['settings', 'page:*'],
        
        // Global
        'cache.clear_all' => ['*']
    ];
    
    public function invalidate(string $event, array $params = []): void
    {
        $patterns = self::INVALIDATION_MAP[$event] ?? [];
        
        foreach ($patterns as $pattern) {
            $tag = $this->interpolatePattern($pattern, $params);
            $this->cache->invalidateByTag($tag);
        }
        
        $this->logger->info('Cache invalidated', [
            'event' => $event,
            'patterns' => $patterns
        ]);
    }
}
```

---

## 6. Object Cache

### 6.1 Cached Data Types

| Data Type | TTL | Tags | Description |
|-----------|-----|------|-------------|
| User Profile | 1 hour | `user:{id}` | Basic user data, roles |
| Exam Content | 1 hour | `exam:{slug}` | Markdown content, metadata |
| Exam List | 15 min | `exams` | Paginated exam listings |
| Theme Config | 24 hours | `theme:{slug}` | Full theme configuration |
| Settings | 1 hour | `settings` | Plugin settings |
| Participant | 5 min | `participant:{id}` | Progress, deadlines |
| Query Results | 5 min | varies | Database query results |

### 6.2 Object Cache Service

```php
class ObjectCacheService
{
    /**
     * Get or compute cached value
     */
    public function remember(
        string $key,
        int $ttl,
        callable $callback,
        array $tags = []
    ): mixed {
        // Check request-level cache first
        if ($this->requestCache->has($key)) {
            return $this->requestCache->get($key);
        }
        
        // Check persistent cache
        $cached = $this->backend->get($key);
        if (is_not_null($cached)) {
            $this->requestCache->set($key, $cached);
            return $cached;
        }
        
        // Compute value
        $value = $callback();
        
        // Store in both caches
        $this->backend->set($key, $value, $ttl, $tags);
        $this->requestCache->set($key, $value);
        
        return $value;
    }
    
    /**
     * Cache exam content with automatic invalidation
     */
    public function cacheExamContent(string $slug): ExamContent
    {
        return $this->remember(
            "eqm:exam:{$slug}:content:v1",
            3600,
            fn() => $this->examService->getContent($slug),
            ["exam:{$slug}", "exam:{$slug}:content"]
        );
    }
}
```

---

## 7. Memcached/Redis Configuration

### 7.1 Memcached Backend

```php
class MemcachedBackend implements CacheBackendInterface
{
    private \Memcached $client;
    
    public function __construct(array $config)
    {
        $this->client = new \Memcached();
        $this->client->addServer(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 11211
        );
        
        // Set options
        $this->client->setOptions([
            \Memcached::OPT_PREFIX_KEY => 'eqm:',
            \Memcached::OPT_BINARY_PROTOCOL => true,
            \Memcached::OPT_COMPRESSION => true
        ]);
    }
    
    public function get(string $key): mixed
    {
        $value = $this->client->get($key);
        return $this->client->getResultCode() === \Memcached::RES_SUCCESS
            ? $value
            : null;
    }
    
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->client->set($key, $value, $ttl);
    }
    
    public function delete(string $key): bool
    {
        return $this->client->delete($key);
    }
    
    public function flush(): bool
    {
        return $this->client->flush();
    }
}
```

### 7.2 Redis Backend

```php
class RedisBackend implements CacheBackendInterface
{
    private \Redis $client;
    
    public function __construct(array $config)
    {
        $this->client = new \Redis();
        $this->client->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379
        );
        
        if (is_not_empty($config['password'] ?? '')) {
            $this->client->auth($config['password']);
        }
        
        $this->client->setOption(\Redis::OPT_PREFIX, 'eqm:');
        $this->client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
    }
    
    /**
     * Redis supports tag-based invalidation natively via sets
     */
    public function setWithTags(
        string $key,
        mixed $value,
        int $ttl,
        array $tags
    ): bool {
        $pipe = $this->client->multi(\Redis::PIPELINE);
        
        // Set the value
        if ($ttl > 0) {
            $pipe->setex($key, $ttl, $value);
        } else {
            $pipe->set($key, $value);
        }
        
        // Add to tag sets
        foreach ($tags as $tag) {
            $pipe->sadd("tag:{$tag}", $key);
        }
        
        $pipe->exec();
        return true;
    }
    
    public function invalidateByTag(string $tag): int
    {
        $keys = $this->client->smembers("tag:{$tag}");
        
        if (is_empty($keys)) {
            return 0;
        }
        
        $pipe = $this->client->multi(\Redis::PIPELINE);
        foreach ($keys as $key) {
            $pipe->del($key);
        }
        $pipe->del("tag:{$tag}");
        $pipe->exec();
        
        return count($keys);
    }
}
```

### 7.3 File-Based Fallback

```php
class FileBackend implements CacheBackendInterface
{
    private string $cacheDir;
    
    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        
        if (is_not_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get(string $key): mixed
    {
        $path = $this->getPath($key);
        
        if (is_not_file($path)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($path));
        
        // Check expiration
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            unlink($path);
            return null;
        }
        
        return $data['value'];
    }
    
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $path = $this->getPath($key);
        $dir = dirname($path);
        
        if (is_not_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'tags' => []
        ];
        
        return file_put_contents($path, serialize($data)) !== false;
    }
    
    private function getPath(string $key): string
    {
        $hash = md5($key);
        // Use subdirectories to avoid too many files in one dir
        return sprintf(
            '%s/%s/%s/%s.cache',
            $this->cacheDir,
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash
        );
    }
}
```

---

## 8. Cache Warming

### 8.1 Cache Warm Strategy

```php
class CacheWarmer
{
    /**
     * Warm cache for critical data after cold start
     */
    public function warmCritical(): void
    {
        // Warm settings
        $this->warmSettings();
        
        // Warm active themes
        $this->warmThemes();
        
        // Warm popular exams
        $this->warmPopularExams(limit: 10);
    }
    
    /**
     * Warm user-specific cache on login
     */
    public function warmUserCache(int $userId): void
    {
        // User profile
        $this->objectCache->cacheUserProfile($userId);
        
        // User's enrolled exams
        $this->objectCache->cacheUserExams($userId);
        
        // Theme preference
        $this->objectCache->cacheUserTheme($userId);
    }
    
    /**
     * Pre-render dashboard HTML for user
     */
    public function warmDashboard(int $userId): void
    {
        $html = $this->renderDashboard($userId);
        $this->pageCache->cachePage('dashboard', $userId, $html);
    }
}
```

### 8.2 Background Cache Refresh

```php
// Cron job to refresh stale cache
class CacheRefreshJob
{
    public function run(): void
    {
        // Find cache entries expiring in next 5 minutes
        $expiringKeys = $this->findExpiringSoon(300);
        
        foreach ($expiringKeys as $key) {
            $this->refreshInBackground($key);
        }
    }
}
```

---

## 9. Cache Headers

### 9.1 HTTP Cache Control

```php
class CacheHeadersMiddleware
{
    public function handle(Request $request, Response $response): Response
    {
        $route = $request->getRoute();
        
        // Static assets - long cache
        if ($this->isStaticAsset($route)) {
            return $response->withHeaders([
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Vary' => 'Accept-Encoding'
            ]);
        }
        
        // API responses - no cache
        if ($this->isApiRoute($route)) {
            return $response->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache'
            ]);
        }
        
        // Dynamic pages - short cache with revalidation
        return $response->withHeaders([
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'ETag' => $this->generateETag($response),
            'Vary' => 'Cookie, Accept-Encoding'
        ]);
    }
}
```

---

## 10. Monitoring & Metrics

### 10.1 Cache Metrics

```php
class CacheMetrics
{
    public function recordHit(string $type): void
    {
        $this->increment("cache.{$type}.hit");
    }
    
    public function recordMiss(string $type): void
    {
        $this->increment("cache.{$type}.miss");
    }
    
    public function getHitRatio(string $type): float
    {
        $hits = $this->get("cache.{$type}.hit");
        $misses = $this->get("cache.{$type}.miss");
        $total = $hits + $misses;
        
        return $total > 0 ? $hits / $total : 0;
    }
    
    public function getStats(): array
    {
        return [
            'page_cache' => [
                'hits' => $this->get('cache.page.hit'),
                'misses' => $this->get('cache.page.miss'),
                'ratio' => $this->getHitRatio('page')
            ],
            'object_cache' => [
                'hits' => $this->get('cache.object.hit'),
                'misses' => $this->get('cache.object.miss'),
                'ratio' => $this->getHitRatio('object')
            ],
            'session_cache' => [
                'hits' => $this->get('cache.session.hit'),
                'misses' => $this->get('cache.session.miss'),
                'ratio' => $this->getHitRatio('session')
            ],
            'backend' => $this->backend->getStats()
        ];
    }
}
```

### 10.2 Admin Cache Panel

```
WordPress Admin → EQM → Settings → Cache

┌─────────────────────────────────────────────────────────────────┐
│ Cache Management                                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Backend: Memcached (127.0.0.1:11211)         Status: ● Online  │
│                                                                  │
│  ┌─────────────┬────────────┬────────────┬────────────────────┐ │
│  │ Cache Type  │ Hit Rate   │ Entries    │ Memory             │ │
│  ├─────────────┼────────────┼────────────┼────────────────────┤ │
│  │ Page Cache  │ 94.2%      │ 1,234      │ 45.2 MB            │ │
│  │ Object Cache│ 87.6%      │ 5,678      │ 12.8 MB            │ │
│  │ Session     │ 99.1%      │ 234        │ 2.1 MB             │ │
│  └─────────────┴────────────┴────────────┴────────────────────┘ │
│                                                                  │
│  Page Cache TTL:   [───●──────────] 5 minutes                   │
│  Object Cache TTL: [─────────●────] 1 hour                      │
│                                                                  │
│  [Clear Page Cache] [Clear Object Cache] [Clear All Caches]     │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  Cache Warming                                                   │
│  □ Enable background cache warming                              │
│  □ Pre-warm on theme changes                                    │
│  □ Pre-warm user dashboard on login                             │
│                                                                  │
│  [Save Settings]                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 11. Common Pitfalls

### ❌ WRONG: Cache Without Invalidation

```php
// WRONG - Never invalidates
$this->cache->set('user:123', $userData);
```

### ✅ CORRECT: Cache With Tags

```php
// CORRECT - Can be invalidated by tag
$this->cache->setWithTags('user:123', $userData, 3600, ['user:123']);
```

### ❌ WRONG: Caching Sensitive Data

```php
// WRONG - Don't cache passwords/tokens
$this->cache->set('user:123', ['password' => $hash]);
```

### ✅ CORRECT: Exclude Sensitive Fields

```php
// CORRECT
$safeData = array_diff_key($userData, ['password' => 1, 'token' => 1]);
$this->cache->set('user:123:profile', $safeData);
```

### ❌ WRONG: No Cache Key Versioning

```php
// WRONG - Can't invalidate on schema change
$key = "exam:{$slug}";
```

### ✅ CORRECT: Versioned Keys

```php
// CORRECT - Include version for schema migrations
$key = "exam:{$slug}:v{$this->schemaVersion}";
```

---

## 12. Configuration Seeding

### 12.1 Cache Config in defaults.json

```json
{
  "cache": {
    "enabled": true,
    "backend": "auto",
    "page": {
      "enabled": true,
      "ttl": 300,
      "excludeRoutes": ["login", "signup", "admin/*"]
    },
    "object": {
      "enabled": true,
      "ttl": {
        "default": 3600,
        "user": 3600,
        "exam": 3600,
        "settings": 3600,
        "participant": 300
      }
    },
    "session": {
      "enabled": true,
      "ttl": {
        "user": 3600,
        "theme": 86400,
        "exam": 300
      }
    },
    "warming": {
      "enabled": true,
      "onLogin": true,
      "onThemeChange": true,
      "popularExamsCount": 10
    },
    "memcached": {
      "host": "127.0.0.1",
      "port": 11211
    },
    "redis": {
      "host": "127.0.0.1",
      "port": 6379,
      "password": ""
    }
  }
}
```

---

## 13. Cross-References

- **Session Management**: `02-frontend/split-spec/13-session-management.md`
- **Cookie Standards**: `SHARED-CONSTANTS.md`
- **Theme System**: `56-theming-system.md`
- **Performance Targets**: `02-frontend/split-spec/27-performance-targets.md`
- **Monitoring**: `53-monitoring-alerting.md`
