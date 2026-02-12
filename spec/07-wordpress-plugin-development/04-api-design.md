# WordPress REST API Design Standards

## Overview

WordPress plugins should use the REST API for external communication. This provides:
- Standardized HTTP endpoints
- Built-in authentication
- Permission callbacks
- JSON request/response handling

## API Namespace Convention

```php
// constants.php
define('MYPLUGIN_API_NAMESPACE', 'myplugin/v1');
define('MYPLUGIN_API_FULL_NAMESPACE', 'myplugin/v1');  // Without leading slash

// Endpoints
define('MYPLUGIN_ENDPOINT_HEALTH', 'health');
define('MYPLUGIN_ENDPOINT_UPLOAD', 'upload');
define('MYPLUGIN_ENDPOINT_STATUS', 'status');
```

## Route Registration Pattern

Register routes during `rest_api_init` hook:

```php
class My_Plugin {
    public function __construct() {
        add_action(HookEnum::REST_API_INIT, [$this, 'register_routes']);
    }
    
    public function register_routes() {
        $this->file_logger->log('Registering REST routes', __FILE__, __LINE__);
        
        // Health check - public
        register_rest_route(
            MYPLUGIN_API_NAMESPACE,
            '/' . MYPLUGIN_ENDPOINT_HEALTH,
            [
                'methods' => WP_REST_Server::READABLE,  // GET
                'callback' => [$this, 'handle_health'],
                'permission_callback' => '__return_true',
            ]
        );
        
        // Upload - requires authentication
        register_rest_route(
            MYPLUGIN_API_NAMESPACE,
            '/' . MYPLUGIN_ENDPOINT_UPLOAD,
            [
                'methods' => WP_REST_Server::CREATABLE,  // POST
                'callback' => [$this, 'handle_upload'],
                'permission_callback' => [$this, 'check_upload_permission'],
            ]
        );
        
        $this->file_logger->log('REST routes registered', __FILE__, __LINE__);
    }
}
```

## Endpoint Naming Standards

### DO
```
/myplugin/v1/health          # Health check
/myplugin/v1/upload          # Upload files
/myplugin/v1/plugins         # List plugins
/myplugin/v1/plugins/123     # Get single plugin
/myplugin/v1/sync            # Sync operation
```

### DON'T
```
/myplugin/v1/getHealth       # Don't prefix with HTTP verb
/myplugin/v1/plugin_list     # Don't use underscores
/myplugin/v1/doUpload        # Don't use action prefixes
```

## Plain String Endpoints (Avoid Regex)

**Critical**: Always use plain string endpoints, NOT regex patterns.

```php
// ❌ WRONG - Regex patterns are error-prone
register_rest_route(
    'myplugin/v1',
    '/plugins/(?P<id>\d+)',  // Regex can cause loading issues
    [...]
);

// ✅ CORRECT - Use WordPress's built-in parameter handling
register_rest_route(
    MYPLUGIN_API_NAMESPACE,
    '/' . MYPLUGIN_ENDPOINT_PLUGINS,  // '/plugins'
    [
        'methods' => 'GET',
        'callback' => [$this, 'handle_list_plugins'],
        'permission_callback' => [$this, 'check_permission'],
    ]
);

// For single item, use separate route
register_rest_route(
    MYPLUGIN_API_NAMESPACE,
    '/' . MYPLUGIN_ENDPOINT_PLUGIN,  // '/plugin'
    [
        'methods' => 'GET',
        'callback' => [$this, 'handle_get_plugin'],
        'permission_callback' => [$this, 'check_permission'],
        'args' => [
            'id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ],
    ]
);
```

## Permission Callbacks

### Public Endpoints
```php
'permission_callback' => '__return_true',
```

### Authenticated Endpoints (Application Passwords)
```php
public function check_permission($request) {
    // Check for Application Password authentication
    $user = wp_get_current_user();
    
    if (!$user || $user->ID === 0) {
        $this->file_logger->log('Permission denied: not authenticated', __FILE__, __LINE__);
        return new WP_Error(
            'rest_forbidden',
            'Authentication required',
            ['status' => 401]
        );
    }
    
    // Check for specific capability
    if (!current_user_can('manage_options')) {
        $this->file_logger->log(
            sprintf('Permission denied: user %d lacks capability', $user->ID),
            __FILE__,
            __LINE__
        );
        return new WP_Error(
            'rest_forbidden',
            'Insufficient permissions',
            ['status' => 403]
        );
    }
    
    $this->file_logger->log(
        sprintf('Permission granted for user %d', $user->ID),
        __FILE__,
        __LINE__
    );
    
    return true;
}
```

### IP Whitelist + Auth
```php
public function check_permission_with_ip($request) {
    // First check IP whitelist
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed_ips = get_option('myplugin_allowed_ips', []);
    
    if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
        $this->file_logger->log(
            sprintf('Permission denied: IP %s not whitelisted', $client_ip),
            __FILE__,
            __LINE__
        );
        return new WP_Error(
            'rest_forbidden',
            'IP not allowed',
            ['status' => 403]
        );
    }
    
    // Then check authentication
    return $this->check_permission($request);
}
```

## Request Handlers

### Standard Response Format

```php
public function handle_health($request) {
    $this->file_logger->log('Health check requested', __FILE__, __LINE__);
    
    return new WP_REST_Response([
        'status' => 'healthy',
        'version' => MYPLUGIN_VERSION,
        'timestamp' => gmdate('c'),
    ], 200);
}
```

### Error Response Format

```php
public function handle_upload($request) {
    $this->file_logger->log('Upload requested', __FILE__, __LINE__);
    
    try {
        // Process upload...
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'filename' => $filename,
                'size' => $size,
            ],
        ], 200);
        
    } catch (Exception $e) {
        $this->file_logger->error(
            sprintf('Upload failed: %s', $e->getMessage()),
            __FILE__,
            __LINE__
        );
        
        return new WP_REST_Response([
            'success' => false,
            'error' => [
                'code' => 'upload_failed',
                'message' => $e->getMessage(),
            ],
        ], 500);
    }
}
```

### Input Validation

```php
register_rest_route(
    MYPLUGIN_API_NAMESPACE,
    '/update',
    [
        'methods' => 'POST',
        'callback' => [$this, 'handle_update'],
        'permission_callback' => [$this, 'check_permission'],
        'args' => [
            'name' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($value) {
                    return strlen($value) >= 3 && strlen($value) <= 100;
                },
            ],
            'status' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['active', 'inactive', 'pending'],
                'default' => 'pending',
            ],
            'priority' => [
                'required' => false,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 50,
            ],
        ],
    ]
);
```

## Logging API Requests

```php
public function handle_upload($request) {
    // Log request details
    $this->file_logger->log(
        sprintf(
            'API Request: %s %s from %s',
            $request->get_method(),
            $request->get_route(),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ),
        __FILE__,
        __LINE__
    );
    
    // Process request...
    
    // Log response
    $this->file_logger->log(
        sprintf('API Response: %d', $status_code),
        __FILE__,
        __LINE__
    );
    
    return $response;
}
```

## Authentication Methods

### 1. Application Passwords (Recommended)

WordPress 5.6+ supports Application Passwords natively:

```php
// Client sends Basic Auth header
// Authorization: Basic base64(username:application_password)

// WordPress handles authentication automatically
// Just check if user is logged in
public function check_permission($request) {
    return is_user_logged_in() && current_user_can('manage_options');
}
```

### 2. Custom Token (for backward compatibility)

```php
// constants.php
define('MYPLUGIN_OPTION_API_TOKEN', 'myplugin_api_token');

public function check_token_permission($request) {
    $token = $request->get_header('X-API-Token');
    
    if (empty($token)) {
        return new WP_Error('no_token', 'API token required', ['status' => 401]);
    }
    
    $stored_token = get_option(MYPLUGIN_OPTION_API_TOKEN);
    
    if (!hash_equals($stored_token, $token)) {
        return new WP_Error('invalid_token', 'Invalid API token', ['status' => 403]);
    }
    
    return true;
}
```

## Rate Limiting

```php
class Rate_Limiter {
    private $prefix = 'myplugin_rate_';
    private $limit = 60;      // requests
    private $window = 60;     // seconds
    
    public function check($identifier) {
        $key = $this->prefix . md5($identifier);
        $count = get_transient($key) ?: 0;
        
        if ($count >= $this->limit) {
            return false;  // Rate limited
        }
        
        set_transient($key, $count + 1, $this->window);
        return true;
    }
}

// In permission callback
public function check_permission($request) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!$this->rate_limiter->check($ip)) {
        return new WP_Error(
            'rate_limited',
            'Too many requests',
            ['status' => 429]
        );
    }
    
    return $this->check_auth($request);
}
```

## Complete Route Registration Example

```php
public function register_routes() {
    $this->file_logger->log('Registering REST routes', __FILE__, __LINE__);
    
    // Health check - public
    register_rest_route(
        RISEUP_API_NAMESPACE,
        '/' . RISEUP_ENDPOINT_HEALTH,
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_health'],
            'permission_callback' => '__return_true',
        ]
    );
    
    // Upload - authenticated
    register_rest_route(
        RISEUP_API_NAMESPACE,
        '/' . RISEUP_ENDPOINT_UPLOAD,
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_upload'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]
    );
    
    // Status - authenticated
    register_rest_route(
        RISEUP_API_NAMESPACE,
        '/' . RISEUP_ENDPOINT_STATUS,
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_status'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]
    );
    
    $this->file_logger->log(
        sprintf('Registered %d REST routes', 3),
        __FILE__,
        __LINE__
    );
}
```
