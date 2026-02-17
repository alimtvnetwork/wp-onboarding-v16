# PHP and WordPress Compatibility

## PHP Version Requirements

### Minimum: PHP 8.2

Both plugins (`riseup-asia-uploader` and `plugins-onboard`) target **PHP 8.2+** exclusively. No backward-compatibility shims, polyfills, or PHP 7.x/8.0/8.1 fallback code should be written.

```php
/**
 * Plugin Name: My Plugin
 * Requires PHP: 8.2
 */
```

### Version Check in Plugin

```php
if (version_compare(PHP_VERSION, '8.2', '<')) {
    add_action('admin_notices', function(): void {
        echo '<div class="notice notice-error">';
        echo '<p><strong>My Plugin</strong> requires PHP 8.2 or higher. ';
        echo 'Current version: ' . PHP_VERSION . '</p>';
        echo '</div>';
    });

    return;
}
```

## PHP 8.2 Features in Active Use

All of these are available and **expected** throughout the codebase:

```php
// Backed enums (PHP 8.1+)
enum EndpointType: string {
    case Upload = 'upload';
    case Status = 'status';

    public function route(): string {
        return '/' . $this->value;
    }
}

// Constructor property promotion (PHP 8.0+)
public function __construct(
    private readonly string $name,
    private readonly int $version,
) {}

// Readonly properties and classes (PHP 8.1+/8.2+)
readonly class Config {
    public function __construct(
        public string $pluginDir,
        public string $pluginUrl,
    ) {}
}

// Named arguments (PHP 8.0+)
$response = new WP_REST_Response(
    data: $payload,
    status: 200,
);

// Match expressions (PHP 8.0+)
$label = match($status) {
    'active' => 'Running',
    'paused' => 'On Hold',
    default  => 'Unknown',
};

// Nullsafe operator (PHP 8.0+)
$name = $user?->profile()?->displayName();

// Union and intersection types (PHP 8.0+/8.1+)
public function resolve(string $url): string|WP_Error {}

// Fibers (PHP 8.1+)
// Disjunctive Normal Form types (PHP 8.2+)
// True/false/null standalone types (PHP 8.2+)
// Constants in traits (PHP 8.2+)
```

## WordPress Version Requirements

### Minimum: WordPress 5.6

```php
/**
 * Plugin Name: My Plugin
 * Requires at least: 5.6
 */
```

### WordPress 5.6+ Features

- **Application Passwords** — Native REST API authentication
- **Auto-updates for plugins/themes**
- **Block Editor improvements**

## Early Loading Compatibility

### Functions NOT Available During Plugin Load

These functions require WordPress to be fully loaded:

```php
// ❌ NOT AVAILABLE during plugin file include
wp_upload_dir()        // Needs WordPress fully loaded
get_option()           // Needs database connected
is_admin()             // May not be set yet
current_user_can()     // User not authenticated yet
plugin_dir_url()       // May fail on some servers

// ✅ AVAILABLE during plugin file include
plugin_dir_path()      // Safe — just path manipulation
defined('ABSPATH')     // Constants are set
```

### Safe Alternatives During Load

Use lazy initialization via class methods:

```php
class AssetLocator {
    private ?array $uploadDir = null;

    private function getUploadDir(): array {
        $this->uploadDir ??= wp_upload_dir();

        return $this->uploadDir;
    }
}
```

## Filesystem Compatibility

### Cross-Platform Paths

```php
// Use WordPress constants
$wpContent = WP_CONTENT_DIR;
$plugins   = WP_PLUGIN_DIR;

// Forward slashes work on all platforms
$path = PathHelper::pluginDir() . 'includes/Core/Plugin.php';
```

### Use Native PHP for Critical Operations

During plugin initialization, prefer native PHP over WordPress wrappers:

```php
if (PathHelper::isDirMissing($path)) {
    @mkdir($path, 0755, true);
}

@file_put_contents($path, $content, LOCK_EX);
```

## SQLite Compatibility

### PDO SQLite

```php
use PDO;
use PDOException;

if (!extension_loaded('pdo_sqlite')) {
    throw new RuntimeException('PDO SQLite extension is required');
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Handle missing SQLite support
}
```

### DateTime Handling in SQLite

SQLite has no native DATETIME type — store as TEXT in ISO 8601:

```php
$now = microtime(true);
$seconds = (int) floor($now);
$milliseconds = (int) round(($now - $seconds) * 1000);
$createdAt = gmdate('Y-m-d\TH:i:s', $seconds) . sprintf('.%03dZ', $milliseconds);
```

## Testing Across Versions

### Minimum Viable Testing

| Environment | Purpose |
|---|---|
| PHP 8.2 + WordPress 5.6 | Minimum supported |
| PHP 8.3 + Latest WordPress | Current production |

```bash
docker run -v $(pwd):/app -w /app php:8.2-cli php -l my-plugin.php
docker run -v $(pwd):/app -w /app php:8.3-cli php -l my-plugin.php
```
