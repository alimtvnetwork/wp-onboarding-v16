<?php
/**
 * Plugin Name: Quick Upload
 * Plugin URI: https://rasia.pro/alim-r-profile-v1
 * Description: Minimal REST API plugin for remote plugin upload and activation with Application Password authentication.
 * Version: 1.0.0
 * Author: MD ALIM UL KARIM
 * Author URI: https://rasia.pro/alim-r-profile-v1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: qupload
 * Requires at least: 5.6
 * Requires PHP: 8.2
 *
 * @package QUpload
 */

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Core\Plugin;

// =============================================================================
// PSR-4 AUTOLOADER — all QUpload\ classes resolve automatically
// =============================================================================

require_once __DIR__ . '/includes/Autoloader.php';

/**
 * Initialize the plugin.
 */
function qupload_init(): void {
    try {
        Plugin::getInstance();
    } catch (\Throwable $e) {
        error_log('[QUpload] Plugin init failed: ' . $e->getMessage());
    }
}

add_action('plugins_loaded', 'qupload_init');
