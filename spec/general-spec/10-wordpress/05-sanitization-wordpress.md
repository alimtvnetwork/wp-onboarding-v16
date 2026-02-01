# 34. WordPress Sanitization & Validation

> **Version**: 1.0.0  
> **Last Updated**: 2025-01-26  
> **Status**: PRODUCTION-READY  
> **Applies To**: WordPress Plugin Development

---

## 34.1 Overview

This document establishes standardized patterns for input sanitization, output escaping, validation, nonce verification, and capability checks specific to WordPress plugin development. All user input MUST be sanitized, all output MUST be escaped, and all actions MUST be authorized.

---

## 34.2 The Security Triad

### Input → Process → Output

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   USER INPUT    │────▶│    PROCESS      │────▶│     OUTPUT      │
│                 │     │                 │     │                 │
│  ✅ Sanitize    │     │  ✅ Validate    │     │  ✅ Escape      │
│  ✅ Verify      │     │  ✅ Authorize   │     │  ✅ Encode      │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

### Key Principles

| Stage | Action | Purpose |
|-------|--------|---------|
| Input | Sanitize | Remove/encode dangerous characters |
| Input | Verify Nonce | Prevent CSRF attacks |
| Process | Validate | Ensure data meets requirements |
| Process | Authorize | Check user capabilities |
| Output | Escape | Prevent XSS attacks |

---

## 34.3 Input Sanitization Functions

### WordPress Sanitization Functions Reference

| Function | Use Case | Example |
|----------|----------|---------|
| `sanitize_text_field()` | Single-line text | Names, titles |
| `sanitize_textarea_field()` | Multi-line text | Descriptions |
| `sanitize_email()` | Email addresses | User emails |
| `sanitize_url()` | URLs | Links, redirects |
| `sanitize_title()` | Slugs | URL slugs |
| `sanitize_file_name()` | File names | Uploads |
| `sanitize_key()` | Keys/identifiers | Option names |
| `sanitize_html_class()` | CSS classes | Dynamic classes |
| `absint()` | Positive integers | IDs, counts |
| `intval()` | Any integer | Offsets |
| `wp_kses()` | Controlled HTML | Rich content |
| `wp_kses_post()` | Post-like HTML | User content |

### Centralized Sanitizer Class

```php
<?php
namespace PluginNamespace\Utils;

use PluginNamespace\Utils\Logger;

class Sanitizer
{
    /**
     * Sanitize string with length limit
     */
    public static function string(?string $value, int $maxLength = 255): string
    {
        $sanitized = sanitize_text_field($value ?? '');
        return mb_substr($sanitized, 0, $maxLength);
    }
    
    /**
     * Sanitize textarea content
     */
    public static function textarea(?string $value, int $maxLength = 5000): string
    {
        $sanitized = sanitize_textarea_field($value ?? '');
        return mb_substr($sanitized, 0, $maxLength);
    }
    
    /**
     * Sanitize email with validation
     */
    public static function email(?string $value): string
    {
        $sanitized = sanitize_email($value ?? '');
        $isValid = is_email($sanitized);
        
        return $isValid ? $sanitized : '';
    }
    
    /**
     * Sanitize URL with validation
     */
    public static function url(?string $value, array $allowedProtocols = ['http', 'https']): string
    {
        $sanitized = esc_url_raw($value ?? '', $allowedProtocols);
        return $sanitized;
    }
    
    /**
     * Sanitize slug
     */
    public static function slug(?string $value, int $maxLength = 100): string
    {
        $sanitized = sanitize_title($value ?? '');
        return mb_substr($sanitized, 0, $maxLength);
    }
    
    /**
     * Sanitize positive integer
     */
    public static function positiveInt($value, int $min = 0, int $max = PHP_INT_MAX): int
    {
        $int = absint($value);
        return max($min, min($max, $int));
    }
    
    /**
     * Sanitize integer (can be negative)
     */
    public static function int($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $int = intval($value);
        return max($min, min($max, $int));
    }
    
    /**
     * Sanitize float
     */
    public static function float($value, float $min = PHP_FLOAT_MIN, float $max = PHP_FLOAT_MAX): float
    {
        $float = floatval($value);
        return max($min, min($max, $float));
    }
    
    /**
     * Sanitize boolean
     */
    public static function bool($value): bool
    {
        // Handle various truthy representations
        $truthyValues = ['1', 'true', 'yes', 'on', true, 1];
        return in_array($value, $truthyValues, true);
    }
    
    /**
     * Sanitize filename for uploads
     */
    public static function filename(?string $value): string
    {
        return sanitize_file_name($value ?? '');
    }
    
    /**
     * Sanitize CSS class name
     */
    public static function cssClass(?string $value): string
    {
        return sanitize_html_class($value ?? '');
    }
    
    /**
     * Sanitize multiple CSS classes
     */
    public static function cssClasses(?string $value): string
    {
        $classes = explode(' ', $value ?? '');
        $sanitized = array_map('sanitize_html_class', $classes);
        $filtered = array_filter($sanitized);
        
        return implode(' ', $filtered);
    }
    
    /**
     * Sanitize key/identifier
     */
    public static function key(?string $value): string
    {
        return sanitize_key($value ?? '');
    }
    
    /**
     * Sanitize HTML with allowed tags
     */
    public static function html(?string $value, string $context = 'post'): string
    {
        $raw = $value ?? '';
        
        return match ($context) {
            'post' => wp_kses_post($raw),
            'data' => wp_kses_data($raw),
            'strip' => wp_strip_all_tags($raw),
            default => wp_kses_post($raw)
        };
    }
    
    /**
     * Sanitize array of strings
     */
    public static function stringArray(?array $values, int $maxLength = 255): array
    {
        $values = $values ?? [];
        
        return array_map(
            fn($v) => self::string($v, $maxLength),
            $values
        );
    }
    
    /**
     * Sanitize array of integers
     */
    public static function intArray(?array $values): array
    {
        $values = $values ?? [];
        
        return array_map('absint', $values);
    }
    
    /**
     * Sanitize enum value (must be in allowed list)
     */
    public static function enum($value, array $allowed, $default = null)
    {
        $isAllowed = in_array($value, $allowed, true);
        
        return $isAllowed ? $value : $default;
    }
    
    /**
     * Sanitize JSON string
     */
    public static function json(?string $value): ?array
    {
        $raw = $value ?? '';
        $hasValue = !empty($raw);
        
        if (!$hasValue) {
            return null;
        }
        
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            Logger::warning('Invalid JSON input', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Sanitize date string
     */
    public static function date(?string $value, string $format = 'Y-m-d'): ?string
    {
        $raw = $value ?? '';
        $hasValue = !empty($raw);
        
        if (!$hasValue) {
            return null;
        }
        
        $date = \DateTime::createFromFormat($format, $raw);
        $isValid = ($date !== false && $date->format($format) === $raw);
        
        return $isValid ? $raw : null;
    }
    
    /**
     * Sanitize datetime string
     */
    public static function datetime(?string $value): ?string
    {
        return self::date($value, 'Y-m-d H:i:s');
    }
    
    /**
     * Bulk sanitize request data by type map
     */
    public static function request(array $data, array $typeMap): array
    {
        $sanitized = [];
        
        foreach ($typeMap as $key => $config) {
            $value = $data[$key] ?? null;
            
            // Handle simple type string or config array
            $type = is_array($config) ? $config['type'] : $config;
            $options = is_array($config) ? $config : [];
            
            $sanitized[$key] = match ($type) {
                'string' => self::string($value, $options['maxLength'] ?? 255),
                'textarea' => self::textarea($value, $options['maxLength'] ?? 5000),
                'email' => self::email($value),
                'url' => self::url($value),
                'slug' => self::slug($value),
                'int' => self::int($value, $options['min'] ?? PHP_INT_MIN, $options['max'] ?? PHP_INT_MAX),
                'positiveInt' => self::positiveInt($value, $options['min'] ?? 0, $options['max'] ?? PHP_INT_MAX),
                'float' => self::float($value),
                'bool' => self::bool($value),
                'filename' => self::filename($value),
                'key' => self::key($value),
                'html' => self::html($value, $options['context'] ?? 'post'),
                'json' => self::json($value),
                'date' => self::date($value, $options['format'] ?? 'Y-m-d'),
                'datetime' => self::datetime($value),
                'enum' => self::enum($value, $options['allowed'] ?? [], $options['default'] ?? null),
                'stringArray' => self::stringArray($value),
                'intArray' => self::intArray($value),
                default => $value
            };
        }
        
        return $sanitized;
    }
}
```

### Usage Example

```php
<?php
// Single value sanitization
$title = Sanitizer::string($_POST['title'], 200);
$email = Sanitizer::email($_POST['email']);
$status = Sanitizer::enum($_POST['status'], ['draft', 'active', 'archived'], 'draft');

// Bulk sanitization
$data = Sanitizer::request($_POST, [
    'title' => ['type' => 'string', 'maxLength' => 200],
    'description' => ['type' => 'textarea', 'maxLength' => 5000],
    'email' => 'email',
    'status' => ['type' => 'enum', 'allowed' => ['draft', 'active'], 'default' => 'draft'],
    'priority' => ['type' => 'int', 'min' => 1, 'max' => 10],
    'is_featured' => 'bool',
    'tags' => 'stringArray'
]);
```

---

## 34.4 Output Escaping Functions

### WordPress Escape Functions Reference

| Function | Use Case | Context |
|----------|----------|---------|
| `esc_html()` | HTML content | Between tags |
| `esc_attr()` | Attributes | Inside quotes |
| `esc_url()` | URLs | href, src |
| `esc_js()` | JavaScript | Inline JS |
| `esc_textarea()` | Textarea content | Inside textarea |
| `wp_kses_post()` | Rich HTML | Post content |
| `wp_json_encode()` | JSON data | Script tags |

### Escape Context Rules

```php
<?php
// ❌ WRONG - No escaping
echo "<a href='$url'>$title</a>";

// ✅ CORRECT - Proper escaping
echo "<a href='" . esc_url($url) . "'>" . esc_html($title) . "</a>";

// ✅ CORRECT - Alternative with sprintf
printf(
    '<a href="%s">%s</a>',
    esc_url($url),
    esc_html($title)
);
```

### Escaping in Different Contexts

```php
<?php
class EscapeExamples
{
    /**
     * HTML content context
     */
    public static function renderTitle(string $title): void
    {
        ?>
        <h1><?php echo esc_html($title); ?></h1>
        <?php
    }
    
    /**
     * HTML attribute context
     */
    public static function renderInput(string $name, string $value): void
    {
        ?>
        <input 
            type="text" 
            name="<?php echo esc_attr($name); ?>" 
            value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }
    
    /**
     * URL context
     */
    public static function renderLink(string $url, string $text): void
    {
        ?>
        <a href="<?php echo esc_url($url); ?>">
            <?php echo esc_html($text); ?>
        </a>
        <?php
    }
    
    /**
     * JavaScript context
     */
    public static function renderJsVariable(string $name, $value): void
    {
        ?>
        <script>
            var <?php echo esc_js($name); ?> = <?php echo wp_json_encode($value); ?>;
        </script>
        <?php
    }
    
    /**
     * Textarea context
     */
    public static function renderTextarea(string $name, string $content): void
    {
        ?>
        <textarea name="<?php echo esc_attr($name); ?>">
            <?php echo esc_textarea($content); ?>
        </textarea>
        <?php
    }
    
    /**
     * Rich HTML context (with allowed tags)
     */
    public static function renderRichContent(string $content): void
    {
        ?>
        <div class="rich-content">
            <?php echo wp_kses_post($content); ?>
        </div>
        <?php
    }
    
    /**
     * CSS context
     */
    public static function renderStyle(string $property, string $value): void
    {
        // Only allow known-safe values
        $safeValue = preg_replace('/[^a-zA-Z0-9#\.\-\s]/', '', $value);
        ?>
        <div style="<?php echo esc_attr($property); ?>: <?php echo esc_attr($safeValue); ?>">
        <?php
    }
    
    /**
     * SQL context - use $wpdb->prepare()
     */
    public static function queryExample(string $status, int $limit): array
    {
        global $wpdb;
        
        // ✅ CORRECT - Always use prepare()
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}items WHERE status = %s LIMIT %d",
                $status,
                $limit
            )
        );
        
        return $results;
    }
}
```

---

## 34.5 Nonce Verification

### Nonce Generation

```php
<?php
namespace PluginNamespace\Utils;

class NonceManager
{
    /**
     * Nonce lifetime in seconds (default: 24 hours)
     */
    private const NONCE_LIFETIME = DAY_IN_SECONDS;
    
    /**
     * Action prefixes
     */
    private const ACTION_PREFIX = 'plugin_slug_';
    
    /**
     * Generate nonce for action
     */
    public static function create(string $action): string
    {
        return wp_create_nonce(self::ACTION_PREFIX . $action);
    }
    
    /**
     * Verify nonce for action
     */
    public static function verify(string $nonce, string $action): bool
    {
        $result = wp_verify_nonce($nonce, self::ACTION_PREFIX . $action);
        
        // wp_verify_nonce returns:
        // 1 = valid, generated 0-12 hours ago
        // 2 = valid, generated 12-24 hours ago
        // false = invalid
        
        return $result !== false;
    }
    
    /**
     * Verify and die if invalid
     */
    public static function verifyOrDie(string $nonce, string $action): void
    {
        $isValid = self::verify($nonce, $action);
        
        if (!$isValid) {
            Logger::warning('Nonce verification failed', [
                'action' => $action,
                'user_id' => get_current_user_id()
            ]);
            
            wp_die(
                __('Security check failed. Please try again.', 'plugin-slug'),
                __('Security Error', 'plugin-slug'),
                ['response' => 403]
            );
        }
    }
    
    /**
     * Render hidden nonce field
     */
    public static function field(string $action, bool $referer = true): void
    {
        wp_nonce_field(
            self::ACTION_PREFIX . $action,
            self::ACTION_PREFIX . 'nonce',
            $referer
        );
    }
    
    /**
     * Get nonce field name
     */
    public static function fieldName(): string
    {
        return self::ACTION_PREFIX . 'nonce';
    }
    
    /**
     * Verify from POST request
     */
    public static function verifyPost(string $action): bool
    {
        $nonce = $_POST[self::fieldName()] ?? '';
        return self::verify($nonce, $action);
    }
    
    /**
     * Verify from GET request
     */
    public static function verifyGet(string $action): bool
    {
        $nonce = $_GET[self::fieldName()] ?? $_GET['_wpnonce'] ?? '';
        return self::verify($nonce, $action);
    }
    
    /**
     * Verify AJAX request
     */
    public static function verifyAjax(string $action): bool
    {
        return check_ajax_referer(self::ACTION_PREFIX . $action, 'nonce', false) !== false;
    }
    
    /**
     * Create URL with nonce
     */
    public static function url(string $url, string $action): string
    {
        return wp_nonce_url($url, self::ACTION_PREFIX . $action);
    }
}
```

### Nonce Usage Patterns

```php
<?php
// Form with nonce
function render_form(): void
{
    ?>
    <form method="post" action="">
        <?php NonceManager::field('save_settings'); ?>
        
        <input type="text" name="title" value="">
        <button type="submit">Save</button>
    </form>
    <?php
}

// Form handler with verification
function handle_form(): void
{
    // Verify nonce first
    $isValidNonce = NonceManager::verifyPost('save_settings');
    
    if (!$isValidNonce) {
        AdminNotices::error(__('Security check failed.', 'plugin-slug'));
        return;
    }
    
    // Process form...
}

// Link with nonce
function render_action_link(int $itemId): void
{
    $url = admin_url('admin.php?page=plugin-slug&action=delete&id=' . $itemId);
    $nonceUrl = NonceManager::url($url, 'delete_item_' . $itemId);
    
    echo '<a href="' . esc_url($nonceUrl) . '">Delete</a>';
}

// Action handler with verification
function handle_delete_action(): void
{
    $itemId = absint($_GET['id'] ?? 0);
    $isValid = NonceManager::verifyGet('delete_item_' . $itemId);
    
    if (!$isValid) {
        wp_die(__('Invalid security token.', 'plugin-slug'));
    }
    
    // Process deletion...
}
```

---

## 34.6 Capability Checks

### Capability Manager

```php
<?php
namespace PluginNamespace\Utils;

class CapabilityManager
{
    /**
     * Plugin-specific capabilities
     */
    public const CAP_MANAGE_PLUGIN = 'manage_plugin_slug';
    public const CAP_EDIT_ITEMS = 'edit_plugin_slug_items';
    public const CAP_DELETE_ITEMS = 'delete_plugin_slug_items';
    public const CAP_VIEW_REPORTS = 'view_plugin_slug_reports';
    public const CAP_MANAGE_SETTINGS = 'manage_plugin_slug_settings';
    
    /**
     * Role capability mappings
     */
    private const ROLE_CAPABILITIES = [
        'administrator' => [
            self::CAP_MANAGE_PLUGIN,
            self::CAP_EDIT_ITEMS,
            self::CAP_DELETE_ITEMS,
            self::CAP_VIEW_REPORTS,
            self::CAP_MANAGE_SETTINGS
        ],
        'editor' => [
            self::CAP_EDIT_ITEMS,
            self::CAP_VIEW_REPORTS
        ],
        'author' => [
            self::CAP_EDIT_ITEMS
        ]
    ];
    
    /**
     * Add capabilities on activation
     */
    public static function addCapabilities(): void
    {
        foreach (self::ROLE_CAPABILITIES as $roleName => $capabilities) {
            $role = get_role($roleName);
            $hasRole = ($role !== null);
            
            if (!$hasRole) {
                continue;
            }
            
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
        
        Logger::info('Plugin capabilities added');
    }
    
    /**
     * Remove capabilities on uninstall
     */
    public static function removeCapabilities(): void
    {
        foreach (self::ROLE_CAPABILITIES as $roleName => $capabilities) {
            $role = get_role($roleName);
            $hasRole = ($role !== null);
            
            if (!$hasRole) {
                continue;
            }
            
            foreach ($capabilities as $cap) {
                $role->remove_cap($cap);
            }
        }
        
        Logger::info('Plugin capabilities removed');
    }
    
    /**
     * Check if current user has capability
     */
    public static function userCan(string $capability): bool
    {
        return current_user_can($capability);
    }
    
    /**
     * Check and die if user lacks capability
     */
    public static function requireCapability(string $capability): void
    {
        $hasCap = self::userCan($capability);
        
        if (!$hasCap) {
            Logger::warning('Capability check failed', [
                'capability' => $capability,
                'user_id' => get_current_user_id()
            ]);
            
            wp_die(
                __('You do not have permission to perform this action.', 'plugin-slug'),
                __('Permission Denied', 'plugin-slug'),
                ['response' => 403]
            );
        }
    }
    
    /**
     * Check if user can edit specific item
     */
    public static function canEditItem(int $itemId, ?int $userId = null): bool
    {
        $userId = $userId ?? get_current_user_id();
        
        // Admins can edit anything
        $isAdmin = user_can($userId, 'manage_options');
        if ($isAdmin) {
            return true;
        }
        
        // Check if user owns the item
        $item = ItemService::getById($itemId);
        $hasItem = ($item !== null);
        
        if (!$hasItem) {
            return false;
        }
        
        $isOwner = ($item->userId === $userId);
        $canEdit = user_can($userId, self::CAP_EDIT_ITEMS);
        
        return $isOwner && $canEdit;
    }
}
```

### Authorization Patterns

```php
<?php
// Admin page authorization
function render_admin_page(): void
{
    CapabilityManager::requireCapability(CapabilityManager::CAP_MANAGE_PLUGIN);
    
    // Render page content...
}

// Action authorization
function handle_item_delete(): void
{
    $itemId = absint($_GET['id'] ?? 0);
    
    // Verify nonce
    NonceManager::verifyOrDie($_GET['_wpnonce'] ?? '', 'delete_item_' . $itemId);
    
    // Verify capability
    $canDelete = CapabilityManager::userCan(CapabilityManager::CAP_DELETE_ITEMS);
    
    if (!$canDelete) {
        wp_die(__('You cannot delete items.', 'plugin-slug'));
    }
    
    // Verify ownership (optional)
    $canEditItem = CapabilityManager::canEditItem($itemId);
    
    if (!$canEditItem) {
        wp_die(__('You cannot delete this item.', 'plugin-slug'));
    }
    
    // Process deletion...
}

// REST API authorization
function permission_callback_edit(\WP_REST_Request $request): bool
{
    $itemId = $request->get_param('id');
    
    return CapabilityManager::canEditItem($itemId);
}
```

---

## 34.7 Validation Patterns

### Validator Class

```php
<?php
namespace PluginNamespace\Utils;

use PluginNamespace\Exceptions\ValidationException;

class Validator
{
    private array $errors = [];
    private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    /**
     * Validate required field
     */
    public function required(string $field, string $message = ''): self
    {
        $value = $this->data[$field] ?? null;
        $isEmpty = empty($value) && $value !== '0' && $value !== 0;
        
        if ($isEmpty) {
            $this->addError($field, $message ?: "{$field} is required");
        }
        
        return $this;
    }
    
    /**
     * Validate email format
     */
    public function email(string $field, string $message = ''): self
    {
        $value = $this->data[$field] ?? '';
        $hasValue = !empty($value);
        
        if ($hasValue && !is_email($value)) {
            $this->addError($field, $message ?: 'Invalid email format');
        }
        
        return $this;
    }
    
    /**
     * Validate URL format
     */
    public function url(string $field, string $message = ''): self
    {
        $value = $this->data[$field] ?? '';
        $hasValue = !empty($value);
        
        if ($hasValue && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, $message ?: 'Invalid URL format');
        }
        
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength(string $field, int $min, string $message = ''): self
    {
        $value = $this->data[$field] ?? '';
        $length = mb_strlen($value);
        
        if ($length < $min) {
            $this->addError($field, $message ?: "{$field} must be at least {$min} characters");
        }
        
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength(string $field, int $max, string $message = ''): self
    {
        $value = $this->data[$field] ?? '';
        $length = mb_strlen($value);
        
        if ($length > $max) {
            $this->addError($field, $message ?: "{$field} must not exceed {$max} characters");
        }
        
        return $this;
    }
    
    /**
     * Validate numeric range
     */
    public function between(string $field, int $min, int $max, string $message = ''): self
    {
        $value = $this->data[$field] ?? 0;
        $num = is_numeric($value) ? (float) $value : 0;
        
        $isBelowMin = ($num < $min);
        $isAboveMax = ($num > $max);
        
        if ($isBelowMin || $isAboveMax) {
            $this->addError($field, $message ?: "{$field} must be between {$min} and {$max}");
        }
        
        return $this;
    }
    
    /**
     * Validate value is in allowed list
     */
    public function in(string $field, array $allowed, string $message = ''): self
    {
        $value = $this->data[$field] ?? null;
        $hasValue = ($value !== null && $value !== '');
        
        if ($hasValue && !in_array($value, $allowed, true)) {
            $this->addError($field, $message ?: "{$field} contains an invalid value");
        }
        
        return $this;
    }
    
    /**
     * Validate regex pattern
     */
    public function pattern(string $field, string $regex, string $message = ''): self
    {
        $value = $this->data[$field] ?? '';
        $hasValue = !empty($value);
        
        if ($hasValue && !preg_match($regex, $value)) {
            $this->addError($field, $message ?: "{$field} format is invalid");
        }
        
        return $this;
    }
    
    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d', string $message = ''): self
    {
        $value = $this->data[$field] ?? '';
        $hasValue = !empty($value);
        
        if ($hasValue) {
            $date = \DateTime::createFromFormat($format, $value);
            $isValid = ($date !== false && $date->format($format) === $value);
            
            if (!$isValid) {
                $this->addError($field, $message ?: "{$field} must be a valid date");
            }
        }
        
        return $this;
    }
    
    /**
     * Validate file upload
     */
    public function file(string $field, array $allowedTypes = [], int $maxSize = 5242880): self
    {
        $file = $_FILES[$field] ?? null;
        $hasFile = ($file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE);
        
        if (!$hasFile) {
            return $this;
        }
        
        // Check for upload errors
        $hasError = ($file['error'] !== UPLOAD_ERR_OK);
        if ($hasError) {
            $this->addError($field, 'File upload failed');
            return $this;
        }
        
        // Check file size
        $isTooLarge = ($file['size'] > $maxSize);
        if ($isTooLarge) {
            $maxMb = round($maxSize / 1048576, 1);
            $this->addError($field, "File must not exceed {$maxMb}MB");
        }
        
        // Check file type
        $hasTypeRestriction = !empty($allowedTypes);
        if ($hasTypeRestriction) {
            $fileType = wp_check_filetype($file['name']);
            $isAllowedType = in_array($fileType['ext'], $allowedTypes, true);
            
            if (!$isAllowedType) {
                $this->addError($field, 'File type not allowed');
            }
        }
        
        return $this;
    }
    
    /**
     * Custom validation callback
     */
    public function custom(string $field, callable $callback, string $message = ''): self
    {
        $value = $this->data[$field] ?? null;
        $isValid = $callback($value, $this->data);
        
        if (!$isValid) {
            $this->addError($field, $message ?: "{$field} validation failed");
        }
        
        return $this;
    }
    
    /**
     * Add error for field
     */
    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !$this->passes();
    }
    
    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get first error for field
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
    
    /**
     * Throw exception if validation fails
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException(
                'Validation failed',
                'ERR_1001',
                $this->errors
            );
        }
        
        return $this->data;
    }
}
```

### Validation Usage

```php
<?php
// In a controller or service
function createItem(array $input): Item
{
    // Sanitize first
    $data = Sanitizer::request($input, [
        'title' => ['type' => 'string', 'maxLength' => 200],
        'email' => 'email',
        'status' => ['type' => 'enum', 'allowed' => ['draft', 'active']],
        'priority' => ['type' => 'int', 'min' => 1, 'max' => 10]
    ]);
    
    // Then validate
    $validator = new Validator($data);
    $validator
        ->required('title', 'Title is required')
        ->minLength('title', 3, 'Title must be at least 3 characters')
        ->maxLength('title', 200, 'Title must not exceed 200 characters')
        ->required('email', 'Email is required')
        ->email('email', 'Please enter a valid email address')
        ->in('status', ['draft', 'active'], 'Invalid status')
        ->between('priority', 1, 10, 'Priority must be between 1 and 10')
        ->validate(); // Throws ValidationException on failure
    
    // Create item with sanitized and validated data
    return ItemService::create($data);
}
```

---

## 34.8 Checklist

### Input Sanitization
- [ ] All user input sanitized before use
- [ ] Centralized `Sanitizer` class used consistently
- [ ] Type-appropriate sanitization applied (string, int, email, etc.)
- [ ] Length limits enforced for text fields
- [ ] Enum values validated against allowed list

### Output Escaping
- [ ] All output escaped for context (HTML, attribute, URL, JS)
- [ ] `esc_html()` used for text content
- [ ] `esc_attr()` used for attributes
- [ ] `esc_url()` used for URLs
- [ ] `wp_kses_post()` used for rich HTML
- [ ] `wp_json_encode()` used for JS data

### Nonce Verification
- [ ] Nonces generated with unique action names
- [ ] Nonces verified before processing forms
- [ ] Nonces verified before processing AJAX
- [ ] Failed nonce verification logged

### Capability Checks
- [ ] Capabilities checked before admin pages
- [ ] Capabilities checked before actions
- [ ] Ownership verified for user-specific resources
- [ ] Permission denied logged

### Validation
- [ ] Required fields validated
- [ ] Format validation (email, URL, date)
- [ ] Range validation (min/max length, numeric range)
- [ ] File upload validation (size, type)
- [ ] Validation errors returned with clear messages

### Database
- [ ] All queries use `$wpdb->prepare()`
- [ ] No raw user input in SQL
- [ ] Parameterized queries for all dynamic values

---

## Cross-References

- [01-coding-standards-foundation.md](../01-foundation/01-coding-standards-foundation.md) - Naming conventions
- [02-error-management-foundation.md](../01-foundation/02-error-management-foundation.md) - ValidationException
- [01-security-patterns-advanced.md](../04-advanced/01-security-patterns-advanced.md) - OWASP mitigations
- [02-rest-api-wordpress.md](./02-rest-api-wordpress.md) - API input validation
- [04-admin-ui-wordpress.md](./04-admin-ui-wordpress.md) - Form handling
