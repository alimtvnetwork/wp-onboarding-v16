<?php
/**
 * OPcache Reset Utility
 *
 * Standalone script to force-reset PHP OPcache after self-updates.
 * Called by the upload script after deploying a new plugin version.
 *
 * Security: Requires a valid WordPress application password via Basic Auth
 * (same credentials used for REST API access). Without valid auth, returns 403.
 *
 * Usage: GET /wp-content/plugins/riseup-asia-uploader/opcache-reset.php
 *
 * @package RiseupAsiaUploader
 * @since   1.45.0
 */

// Prevent direct access without auth header
if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'error' => 'Authentication required'));
    exit;
}

// Bootstrap WordPress to validate credentials
$wp_load_paths = array(
    dirname(__FILE__) . '/../../../wp-load.php',           // Standard: wp-content/plugins/plugin/
    dirname(__FILE__) . '/../../../../wp-load.php',        // Alternate depth
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        define('SHORTINIT', false); // Need full WP for user auth
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'error' => 'WordPress not found'));
    exit;
}

// Validate the user has admin privileges
$user = wp_authenticate_application_password(null, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
if (is_wp_error($user) || !$user) {
    // Fallback: try standard wp_authenticate
    $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
}

if (is_wp_error($user) || !$user || !user_can($user, 'manage_options')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'error' => 'Insufficient privileges'));
    exit;
}

// Reset OPcache
header('Content-Type: application/json');

$result = array(
    'success'          => true,
    'opcache_available' => function_exists('opcache_reset'),
    'opcache_reset'    => false,
    'files_invalidated' => 0,
    'timestamp'        => gmdate('c'),
);

if (function_exists('opcache_reset')) {
    $result['opcache_reset'] = opcache_reset();
}

// Also invalidate specific plugin files
$plugin_dir = dirname(__FILE__);
$invalidated = 0;
if (function_exists('opcache_invalidate')) {
    $files_to_invalidate = array(
        $plugin_dir . '/riseup-asia-uploader.php',
        $plugin_dir . '/includes/constants.php',
    );
    foreach ($files_to_invalidate as $file) {
        if (file_exists($file)) {
            clearstatcache(true, $file);
            opcache_invalidate($file, true);
            $invalidated++;
        }
    }
}
$result['files_invalidated'] = $invalidated;

echo json_encode($result);
