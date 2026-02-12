# WordPress Plugin Security Best Practices

## Authentication

### Application Passwords (WordPress 5.6+)

The recommended method for REST API authentication:

```php
// Client sends Basic Auth header
// Authorization: Basic base64(username:application_password)

public function check_permission($request) {
    // WordPress handles Application Password auth automatically
    $user = wp_get_current_user();
    $is_unauthenticated = !$user || $user->ID === 0;

    if ($is_unauthenticated) {
        $this->file_logger->log('Permission denied: not authenticated', __FILE__, __LINE__);

        return new WP_Error('not_authenticated', 'Authentication required', ['status' => 401]);
    }
    
    // Check specific capability
    if (!current_user_can(CapabilityEnum::MANAGE_OPTIONS)) {
        return new WP_Error('insufficient_permissions', 'Admin access required', ['status' => 403]);
    }
    
    return true;
}
```

### IP Whitelisting

For additional security on sensitive endpoints:

```php
class Riseup_IP_Whitelist {
    private $allowed_ips = [];
    
    public function __construct() {
        $this->allowed_ips = get_option(RISEUP_OPTION_ALLOWED_IPS, []);
    }
    
    public function is_allowed($ip) {
        // Empty whitelist = allow all
        if (empty($this->allowed_ips)) {
            return true;
        }
        
        return in_array($ip, $this->allowed_ips);
    }
    
    public function get_client_ip() {
        // Check various headers for proxy scenarios
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Standard proxy header
            'HTTP_X_REAL_IP',         // Nginx
            'REMOTE_ADDR',            // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return filter_var($ip, FILTER_VALIDATE_IP);
            }
        }
        
        return '';
    }
}
```

## Input Sanitization

### Always Sanitize User Input

```php
// Text fields
$name = sanitize_text_field($request->get_param('name'));

// Email
$email = sanitize_email($request->get_param('email'));

// URLs
$url = esc_url_raw($request->get_param('url'));

// Integers
$id = absint($request->get_param('id'));

// File names
$filename = sanitize_file_name($request->get_param('filename'));

// HTML (if allowed)
$content = wp_kses_post($request->get_param('content'));
```

### Validation Before Use

```php
public function validate_upload_request($request) {
    $errors = [];
    
    // Required fields
    $slug = $request->get_param('slug');
    if (empty($slug)) {
        $errors[] = 'Slug is required';
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $errors[] = 'Slug must contain only lowercase letters, numbers, and hyphens';
    }
    
    // File validation
    $files = $request->get_file_params();
    if (empty($files['plugin'])) {
        $errors[] = 'Plugin file is required';
    }
    
    if (!empty($errors)) {
        return new WP_Error('validation_failed', implode('. ', $errors), ['status' => 400]);
    }
    
    return true;
}
```

## Output Escaping

### Always Escape Output

```php
// HTML context
echo esc_html($variable);

// HTML attributes
echo '<input value="' . esc_attr($variable) . '">';

// URLs
echo '<a href="' . esc_url($url) . '">';

// JavaScript
echo '<script>var data = ' . wp_json_encode($data) . ';</script>';

// SQL (use prepared statements instead)
$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id);
```

## SQL Injection Prevention

### Always Use Prepared Statements

```php
// ❌ NEVER DO THIS
$sql = "SELECT * FROM users WHERE id = {$_GET['id']}";

// ✅ With PDO
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);

// ✅ With $wpdb
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}users WHERE id = %d",
        $id
    )
);
```

## File Upload Security

### Validate File Uploads

```php
class Riseup_Upload_Validator {
    private $allowed_extensions = ['php', 'css', 'js', 'json', 'txt', 'md', 'pot'];
    private $max_file_size = 52428800; // 50MB
    
    public function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $this->get_upload_error($file['error']));
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            throw new Exception('File too large. Maximum: 50MB');
        }
        
        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            throw new Exception('File type not allowed: ' . $extension);
        }
        
        // Verify it's an actual uploaded file
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid upload');
        }
        
        return true;
    }
    
    private function get_upload_error($code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];
        return $errors[$code] ?? 'Unknown error';
    }
}
```

## Directory Security

### Protect Plugin Data Directories

```php
private function secure_directory($path) {
    // .htaccess for Apache
    $htaccess = $path . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    
    // index.php as fallback
    $index = $path . '/index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php // Silence is golden\n");
    }
}
```

### Validate File Paths

```php
public function validate_path($requested_path, $base_path) {
    // Resolve to absolute path
    $real_path = realpath($requested_path);
    $real_base = realpath($base_path);
    
    // Check it's within allowed directory
    if ($real_path === false || strpos($real_path, $real_base) !== 0) {
        $this->file_logger->error(
            sprintf('Path traversal attempt: %s', $requested_path),
            __FILE__,
            __LINE__
        );
        return false;
    }
    
    return $real_path;
}
```

## CSRF Protection

### Use Nonces for Admin Actions

```php
// Generate nonce
$nonce = wp_create_nonce('riseup_upload_action');

// In form
echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';

// Verify nonce
public function handle_admin_action() {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'riseup_upload_action')) {
        wp_die('Security check failed');
    }
    
    // Process action...
}
```

## Rate Limiting

### Prevent Abuse

```php
class Riseup_Rate_Limiter {
    private $limit;
    private $window;
    private $prefix = 'riseup_rate_';
    
    public function __construct($limit = 60, $window = 60) {
        $this->limit = $limit;    // requests
        $this->window = $window;  // seconds
    }
    
    public function check($identifier) {
        $key = $this->prefix . md5($identifier);
        $count = get_transient($key);
        
        if ($count === false) {
            set_transient($key, 1, $this->window);
            return true;
        }
        
        if ($count >= $this->limit) {
            $this->log_rate_limit($identifier);
            return false;
        }
        
        set_transient($key, $count + 1, $this->window);
        return true;
    }
    
    private function log_rate_limit($identifier) {
        $logger = new Riseup_File_Logger();
        $logger->log(
            sprintf('Rate limit exceeded for: %s', $identifier),
            __FILE__,
            __LINE__
        );
    }
}
```

## Secure Token Generation

### API Tokens

```php
class Riseup_Token_Manager {
    public function generate_token() {
        // Use cryptographically secure random bytes
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));  // 64 character hex string
        }
        
        // Fallback (less secure but acceptable)
        return wp_generate_password(64, false);
    }
    
    public function hash_token($token) {
        return hash('sha256', $token);
    }
    
    public function verify_token($provided, $stored_hash) {
        $provided_hash = $this->hash_token($provided);
        return hash_equals($stored_hash, $provided_hash);
    }
}
```

## Logging Security Events

### Audit Trail

```php
public function log_security_event($event, $details = []) {
    $data = [
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'user_id' => get_current_user_id(),
        'timestamp' => gmdate('c'),
        'details' => $details,
    ];
    
    $this->file_logger->log(
        sprintf('SECURITY: %s | %s', $event, wp_json_encode($details)),
        __FILE__,
        __LINE__
    );
}

// Usage
$this->log_security_event('login_failed', ['username' => $username]);
$this->log_security_event('permission_denied', ['endpoint' => $route]);
$this->log_security_event('rate_limit_hit', ['ip' => $ip]);
```

## Security Checklist

Before deploying, verify:

- [ ] All user input is sanitized
- [ ] All output is escaped
- [ ] SQL queries use prepared statements
- [ ] File uploads are validated
- [ ] Directory traversal is prevented
- [ ] Authentication is required for sensitive endpoints
- [ ] Rate limiting is implemented
- [ ] Security events are logged
- [ ] Nonces are used for admin actions
- [ ] Data directories are protected
