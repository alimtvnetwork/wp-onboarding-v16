# 31. WordPress REST API

> **Version**: 1.0.0  
> **Last Updated**: 2025-01-26  
> **Status**: PRODUCTION-READY  
> **Applies To**: WordPress Plugin Development

---

## 31.1 Overview

This document establishes standardized patterns for WordPress REST API implementation, including namespace conventions, authentication, permission callbacks, response envelopes, and security best practices.

---

## 31.2 API Namespace Convention

### Standard Format

```
/wp-json/{plugin-slug}/v{version}/
```

### Examples

```
/wp-json/eqm/v1/exams
/wp-json/eqm/v1/participants
/wp-json/eqm/v1/settings
```

### Frontend Alias Pattern

For frontend documentation clarity, use shorthand:

| Frontend Alias | Actual Endpoint |
|----------------|-----------------|
| `/api/exams` | `/wp-json/eqm/v1/exams` |
| `/api/auth/login` | `/wp-json/eqm/v1/auth/login` |

---

## 31.3 Endpoint Registration

### Base Controller Class

```php
<?php
namespace PluginNamespace\API;

use PluginNamespace\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

abstract class RestController
{
    protected string $namespace = 'plugin-slug/v1';
    protected string $restBase = '';
    
    /**
     * Register routes - called on rest_api_init
     */
    abstract public function registerRoutes(): void;
    
    /**
     * Standard success response
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $meta = []
    ): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => array_merge([
                'requestId' => $this->generateRequestId(),
                'timestamp' => gmdate('c'),
                'version' => PLUGIN_SLUG_VERSION
            ], $meta)
        ], $status);
    }
    
    /**
     * Standard error response
     */
    protected function error(
        string $code,
        string $message,
        int $status = 400,
        array $details = []
    ): WP_REST_Response {
        Logger::warning('API error response', [
            'code' => $code,
            'message' => $message,
            'status' => $status,
            'details' => $details
        ]);
        
        return new WP_REST_Response([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ],
            'meta' => [
                'requestId' => $this->generateRequestId(),
                'timestamp' => gmdate('c'),
                'version' => PLUGIN_SLUG_VERSION
            ]
        ], $status);
    }
    
    /**
     * Generate unique request ID for tracing
     */
    protected function generateRequestId(): string
    {
        return bin2hex(random_bytes(8));
    }
    
    /**
     * Get current user ID or 0 for guests
     */
    protected function getCurrentUserId(): int
    {
        return get_current_user_id();
    }
}
```

### Endpoint Registration Pattern

```php
<?php
namespace PluginNamespace\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ExamEndpoints extends RestController
{
    protected string $restBase = 'exams';
    
    public function registerRoutes(): void
    {
        // GET /wp-json/plugin-slug/v1/exams
        register_rest_route($this->namespace, '/' . $this->restBase, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getExams'],
                'permission_callback' => [$this, 'canReadExams'],
                'args' => $this->getCollectionParams()
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createExam'],
                'permission_callback' => [$this, 'canCreateExam'],
                'args' => $this->getCreateParams()
            ]
        ]);
        
        // GET/PUT/DELETE /wp-json/plugin-slug/v1/exams/{id}
        register_rest_route($this->namespace, '/' . $this->restBase . '/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getExam'],
                'permission_callback' => [$this, 'canReadExam'],
                'args' => ['id' => $this->getIdParam()]
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateExam'],
                'permission_callback' => [$this, 'canUpdateExam'],
                'args' => $this->getUpdateParams()
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteExam'],
                'permission_callback' => [$this, 'canDeleteExam'],
                'args' => ['id' => $this->getIdParam()]
            ]
        ]);
    }
    
    /**
     * ID parameter schema
     */
    protected function getIdParam(): array
    {
        return [
            'description' => 'Unique identifier',
            'type' => 'integer',
            'required' => true,
            'minimum' => 1,
            'sanitize_callback' => 'absint',
            'validate_callback' => function ($value) {
                return is_numeric($value) && $value > 0;
            }
        ];
    }
    
    /**
     * Collection query parameters
     */
    protected function getCollectionParams(): array
    {
        return [
            'page' => [
                'description' => 'Page number',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'sanitize_callback' => 'absint'
            ],
            'per_page' => [
                'description' => 'Items per page',
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint'
            ],
            'search' => [
                'description' => 'Search term',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'orderby' => [
                'description' => 'Sort field',
                'type' => 'string',
                'default' => 'created_at',
                'enum' => ['id', 'title', 'created_at', 'updated_at']
            ],
            'order' => [
                'description' => 'Sort direction',
                'type' => 'string',
                'default' => 'desc',
                'enum' => ['asc', 'desc']
            ]
        ];
    }
}
```

---

## 31.4 Response Envelope Standard

### Success Response Format

```json
{
    "success": true,
    "data": {
        "id": 123,
        "title": "Example Exam",
        "status": "active"
    },
    "error": null,
    "meta": {
        "requestId": "a1b2c3d4e5f6g7h8",
        "timestamp": "2025-01-26T10:30:00Z",
        "version": "1.0.0"
    }
}
```

### Collection Response Format

```json
{
    "success": true,
    "data": [
        { "id": 1, "title": "Exam 1" },
        { "id": 2, "title": "Exam 2" }
    ],
    "error": null,
    "meta": {
        "requestId": "a1b2c3d4e5f6g7h8",
        "timestamp": "2025-01-26T10:30:00Z",
        "version": "1.0.0",
        "pagination": {
            "page": 1,
            "perPage": 20,
            "total": 45,
            "totalPages": 3
        }
    }
}
```

### Error Response Format

```json
{
    "success": false,
    "data": null,
    "error": {
        "code": "ERR_1001",
        "message": "Validation failed",
        "details": {
            "field": "email",
            "reason": "Invalid email format"
        }
    },
    "meta": {
        "requestId": "a1b2c3d4e5f6g7h8",
        "timestamp": "2025-01-26T10:30:00Z",
        "version": "1.0.0"
    }
}
```

### Error Code Registry

| Range | Category | Example |
|-------|----------|---------|
| 1xxx | Validation | ERR_1001: Invalid input |
| 2xxx | Authentication | ERR_2001: Invalid credentials |
| 3xxx | Authorization | ERR_3001: Insufficient permissions |
| 4xxx | Not Found | ERR_4001: Resource not found |
| 5xxx | Database | ERR_5001: Query failed |
| 6xxx | External Service | ERR_6001: API timeout |
| 9xxx | Internal | ERR_9001: Unexpected error |

---

## 31.5 Authentication & Authorization

### Nonce Verification

```php
<?php
namespace PluginNamespace\API;

trait NonceVerification
{
    /**
     * Verify REST nonce from header or parameter
     */
    protected function verifyNonce(WP_REST_Request $request): bool
    {
        // Check X-WP-Nonce header (standard)
        $nonce = $request->get_header('X-WP-Nonce');
        
        // Fallback to _wpnonce parameter
        $hasHeaderNonce = !empty($nonce);
        if (!$hasHeaderNonce) {
            $nonce = $request->get_param('_wpnonce');
        }
        
        $hasNonce = !empty($nonce);
        if (!$hasNonce) {
            return false;
        }
        
        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }
    
    /**
     * Verify custom action nonce (for non-REST AJAX)
     */
    protected function verifyActionNonce(string $action, string $nonceField = 'nonce'): bool
    {
        $nonce = $_REQUEST[$nonceField] ?? '';
        
        $hasNonce = !empty($nonce);
        if (!$hasNonce) {
            return false;
        }
        
        return wp_verify_nonce($nonce, $action) !== false;
    }
}
```

### Permission Callback Patterns

```php
<?php
namespace PluginNamespace\API;

use WP_REST_Request;
use PluginNamespace\Services\RBACService;
use PluginNamespace\Utils\Logger;

trait PermissionCallbacks
{
    /**
     * Check if user is logged in
     */
    public function isAuthenticated(WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }
    
    /**
     * Check plugin-specific capability
     */
    public function hasCapability(string $capability): callable
    {
        return function (WP_REST_Request $request) use ($capability): bool {
            $userId = get_current_user_id();
            $hasUser = ($userId > 0);
            
            if (!$hasUser) {
                return false;
            }
            
            return RBACService::userCan($userId, $capability);
        };
    }
    
    /**
     * Check resource ownership
     */
    public function isOwner(string $resourceType): callable
    {
        return function (WP_REST_Request $request) use ($resourceType): bool {
            $userId = get_current_user_id();
            $resourceId = $request->get_param('id');
            
            $hasUser = ($userId > 0);
            $hasResourceId = !empty($resourceId);
            
            if (!$hasUser || !$hasResourceId) {
                return false;
            }
            
            return RBACService::isResourceOwner($userId, $resourceType, $resourceId);
        };
    }
    
    /**
     * Combined permission check with logging
     */
    public function checkPermission(WP_REST_Request $request, string $action): bool
    {
        $userId = get_current_user_id();
        $allowed = RBACService::userCan($userId, $action);
        
        if (!$allowed) {
            Logger::warning('Permission denied', [
                'user_id' => $userId,
                'action' => $action,
                'endpoint' => $request->get_route(),
                'method' => $request->get_method()
            ]);
        }
        
        return $allowed;
    }
}
```

### Public vs Authenticated Endpoints

```php
<?php
// Public endpoint - anyone can access
register_rest_route($this->namespace, '/public-data', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => [$this, 'getPublicData'],
    'permission_callback' => '__return_true' // ⚠️ Use sparingly
]);

// Authenticated endpoint - logged-in users only
register_rest_route($this->namespace, '/user-data', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => [$this, 'getUserData'],
    'permission_callback' => [$this, 'isAuthenticated']
]);

// Admin endpoint - administrators only
register_rest_route($this->namespace, '/admin-data', [
    'methods' => WP_REST_Server::READABLE,
    'callback' => [$this, 'getAdminData'],
    'permission_callback' => [$this, 'isAdmin']
]);
```

---

## 31.6 Input Validation & Sanitization

### Parameter Validation Schema

```php
<?php
protected function getCreateParams(): array
{
    return [
        'title' => [
            'description' => 'Exam title',
            'type' => 'string',
            'required' => true,
            'minLength' => 3,
            'maxLength' => 200,
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => function ($value, $request, $key) {
                $trimmed = trim($value);
                $length = mb_strlen($trimmed);
                
                $isTooShort = ($length < 3);
                $isTooLong = ($length > 200);
                
                if ($isTooShort) {
                    return new WP_Error(
                        'ERR_1001',
                        'Title must be at least 3 characters'
                    );
                }
                
                if ($isTooLong) {
                    return new WP_Error(
                        'ERR_1001',
                        'Title must not exceed 200 characters'
                    );
                }
                
                return true;
            }
        ],
        'email' => [
            'description' => 'Contact email',
            'type' => 'string',
            'required' => true,
            'format' => 'email',
            'sanitize_callback' => 'sanitize_email',
            'validate_callback' => function ($value) {
                $isValid = is_email($value);
                
                if (!$isValid) {
                    return new WP_Error(
                        'ERR_1002',
                        'Invalid email format'
                    );
                }
                
                return true;
            }
        ],
        'status' => [
            'description' => 'Exam status',
            'type' => 'string',
            'required' => false,
            'default' => 'draft',
            'enum' => ['draft', 'active', 'archived'],
            'sanitize_callback' => 'sanitize_text_field'
        ],
        'settings' => [
            'description' => 'Exam settings object',
            'type' => 'object',
            'required' => false,
            'default' => [],
            'sanitize_callback' => function ($value) {
                return is_array($value) ? $value : [];
            }
        ]
    ];
}
```

### Sanitization Helper Class

```php
<?php
namespace PluginNamespace\Utils;

class Sanitizer
{
    /**
     * Sanitize string input
     */
    public static function string(?string $value, int $maxLength = 255): string
    {
        $sanitized = sanitize_text_field($value ?? '');
        return mb_substr($sanitized, 0, $maxLength);
    }
    
    /**
     * Sanitize email
     */
    public static function email(?string $value): string
    {
        return sanitize_email($value ?? '');
    }
    
    /**
     * Sanitize integer
     */
    public static function int(?string $value, int $min = 0, int $max = PHP_INT_MAX): int
    {
        $int = absint($value ?? 0);
        return max($min, min($max, $int));
    }
    
    /**
     * Sanitize boolean
     */
    public static function bool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Sanitize slug
     */
    public static function slug(?string $value): string
    {
        return sanitize_title($value ?? '');
    }
    
    /**
     * Sanitize HTML (allow safe tags)
     */
    public static function html(?string $value): string
    {
        return wp_kses_post($value ?? '');
    }
    
    /**
     * Sanitize URL
     */
    public static function url(?string $value): string
    {
        return esc_url_raw($value ?? '');
    }
    
    /**
     * Bulk sanitize request data by type map
     */
    public static function request(array $data, array $typeMap): array
    {
        $sanitized = [];
        
        foreach ($typeMap as $key => $type) {
            $value = $data[$key] ?? null;
            
            $sanitized[$key] = match ($type) {
                'string' => self::string($value),
                'email' => self::email($value),
                'int' => self::int($value),
                'bool' => self::bool($value),
                'slug' => self::slug($value),
                'html' => self::html($value),
                'url' => self::url($value),
                default => $value
            };
        }
        
        return $sanitized;
    }
}
```

---

## 31.7 Error Handling in Endpoints

### Try-Catch Pattern for Endpoints

```php
<?php
public function createExam(WP_REST_Request $request): WP_REST_Response
{
    try {
        // 1. Extract and sanitize input
        $data = Sanitizer::request($request->get_params(), [
            'title' => 'string',
            'description' => 'html',
            'status' => 'string'
        ]);
        
        // 2. Business logic
        $exam = $this->examService->create($data);
        
        // 3. Success response
        return $this->success($exam, 'Exam created', 201);
        
    } catch (ValidationException $e) {
        return $this->error(
            $e->getCode(),
            $e->getMessage(),
            422,
            $e->getDetails()
        );
        
    } catch (DatabaseException $e) {
        Logger::error('Database error in createExam', [
            'file' => __FILE__,
            'action' => 'createExam',
            'error' => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString()
        ]);
        
        return $this->error(
            'ERR_5001',
            'Failed to create exam',
            500
        );
        
    } catch (\Throwable $e) {
        Logger::error('Unexpected error in createExam', [
            'file' => __FILE__,
            'action' => 'createExam',
            'error' => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString()
        ]);
        
        return $this->error(
            'ERR_9001',
            'An unexpected error occurred',
            500
        );
    }
}
```

---

## 31.8 Rate Limiting

### Rate Limit Implementation

```php
<?php
namespace PluginNamespace\API;

use PluginNamespace\Utils\Logger;

trait RateLimiting
{
    /**
     * Check rate limit for endpoint
     */
    protected function checkRateLimit(
        string $category,
        string $identifier,
        int $maxRequests = 60,
        int $windowSeconds = 60
    ): bool {
        $key = "rate_limit_{$category}_{$identifier}";
        $current = get_transient($key);
        
        $hasExisting = ($current !== false);
        
        if (!$hasExisting) {
            set_transient($key, 1, $windowSeconds);
            return true;
        }
        
        $isWithinLimit = ($current < $maxRequests);
        
        if ($isWithinLimit) {
            set_transient($key, $current + 1, $windowSeconds);
            return true;
        }
        
        Logger::warning('Rate limit exceeded', [
            'category' => $category,
            'identifier' => $identifier,
            'requests' => $current,
            'limit' => $maxRequests
        ]);
        
        return false;
    }
    
    /**
     * Get client IP for rate limiting
     */
    protected function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR'                // Direct
        ];
        
        foreach ($headers as $header) {
            $ip = $_SERVER[$header] ?? '';
            $hasIp = !empty($ip);
            
            if ($hasIp) {
                // Handle comma-separated IPs (X-Forwarded-For)
                $ips = explode(',', $ip);
                return trim($ips[0]);
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(
        WP_REST_Response $response,
        int $remaining,
        int $limit,
        int $resetTime
    ): WP_REST_Response {
        $response->header('X-RateLimit-Limit', $limit);
        $response->header('X-RateLimit-Remaining', max(0, $remaining));
        $response->header('X-RateLimit-Reset', $resetTime);
        
        return $response;
    }
}
```

### Rate Limit Categories

| Category | Limit | Window | Authenticated Multiplier |
|----------|-------|--------|--------------------------|
| auth | 5 | 60s | 1x |
| api_read | 100 | 60s | 2x |
| api_write | 30 | 60s | 2x |
| file_upload | 10 | 60s | 2x |
| search | 30 | 60s | 2x |

---

## 31.9 CORS Configuration

### CORS Headers for REST API

```php
<?php
namespace PluginNamespace\Core;

class CorsHandler
{
    /**
     * Register CORS headers
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'addCorsHeaders']);
        add_filter('rest_pre_serve_request', [self::class, 'handlePreflight'], 10, 4);
    }
    
    /**
     * Add CORS headers to responses
     */
    public static function addCorsHeaders(): void
    {
        // Only for plugin endpoints
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isPluginEndpoint = strpos($requestUri, '/wp-json/plugin-slug/') !== false;
        
        if (!$isPluginEndpoint) {
            return;
        }
        
        $allowedOrigins = self::getAllowedOrigins();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $isAllowed = in_array($origin, $allowedOrigins, true);
        
        if ($isAllowed) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            header('Access-Control-Max-Age: 86400');
        }
    }
    
    /**
     * Handle preflight OPTIONS requests
     */
    public static function handlePreflight($served, $result, $request, $server): bool
    {
        $isOptions = ($request->get_method() === 'OPTIONS');
        
        if ($isOptions) {
            self::addCorsHeaders();
            exit;
        }
        
        return $served;
    }
    
    /**
     * Get allowed origins from settings
     */
    private static function getAllowedOrigins(): array
    {
        $siteUrl = get_site_url();
        $customOrigins = get_option('plugin_slug_cors_origins', []);
        
        return array_merge([$siteUrl], $customOrigins);
    }
}
```

---

## 31.10 Frontend JavaScript Integration

### Nonce Generation in PHP

```php
<?php
// In AdminAssets.php or similar
public function enqueueScripts(): void
{
    wp_enqueue_script(
        'plugin-slug-admin',
        PLUGIN_SLUG_URL . 'assets/js/admin.js',
        ['jquery'],
        PLUGIN_SLUG_VERSION,
        true
    );
    
    wp_localize_script('plugin-slug-admin', 'pluginSlugApi', [
        'root' => esc_url_raw(rest_url('plugin-slug/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'userId' => get_current_user_id()
    ]);
}
```

### JavaScript API Client

```javascript
/**
 * Plugin REST API Client
 */
const PluginApi = {
    /**
     * Make authenticated API request
     */
    async request(endpoint, options = {}) {
        const url = `${pluginSlugApi.root}${endpoint}`;
        
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': pluginSlugApi.nonce
            },
            credentials: 'same-origin',
            ...options
        };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error?.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    // GET request
    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },
    
    // POST request
    post(endpoint, body) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(body)
        });
    },
    
    // PUT request
    put(endpoint, body) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(body)
        });
    },
    
    // DELETE request
    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

// Usage examples
// const exams = await PluginApi.get('exams');
// const newExam = await PluginApi.post('exams', { title: 'New Exam' });
```

---

## 31.11 Checklist

### Endpoint Registration
- [ ] All endpoints use standard namespace `plugin-slug/v1`
- [ ] Routes registered on `rest_api_init` hook
- [ ] Permission callbacks defined for all endpoints
- [ ] Parameter schemas with validation/sanitization

### Response Format
- [ ] All responses use standard envelope `{success, data, error, meta}`
- [ ] Meta includes `requestId`, `timestamp`, `version`
- [ ] Pagination included for collection endpoints
- [ ] Error codes follow ERR_xxxx registry

### Authentication
- [ ] Nonce verification for authenticated endpoints
- [ ] Permission callbacks check capabilities
- [ ] Public endpoints explicitly marked with `__return_true`
- [ ] Rate limiting applied to sensitive endpoints

### Security
- [ ] All inputs sanitized using WordPress functions
- [ ] SQL queries use `$wpdb->prepare()`
- [ ] Output escaped appropriately
- [ ] CORS configured for allowed origins only

### Error Handling
- [ ] All endpoint logic wrapped in try-catch
- [ ] Errors logged with file, action, message, stack trace
- [ ] User-friendly error messages returned
- [ ] Sensitive data not exposed in error responses

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Naming conventions
- [02-error-management-foundation.md](../01-foundation/02-error-management-foundation.md) - Error code registry
- [03-api-conventions-quality.md](../03-quality/03-api-conventions-quality.md) - General REST patterns
- [01-security-patterns-advanced.md](../04-advanced/01-security-patterns-advanced.md) - OWASP mitigations
- [01-plugin-structure-wordpress.md](./01-plugin-structure-wordpress.md) - Plugin lifecycle
- [05-sanitization-wordpress.md](./05-sanitization-wordpress.md) - WP sanitization
