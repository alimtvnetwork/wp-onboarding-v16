<?php
/**
 * UploadInstallActivateTrait — OPcache reset, activation, and version detection.
 *
 * @package RiseupAsia\Traits
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UploadInstallActivateTrait
{
    /** Reset OPcache and locate the plugin's main file. */
    private function reset_opcache_and_find_plugin($slug) {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $plugin_file = $this->find_plugin_file($slug);
        if (!empty($plugin_file)) {
            $this->invalidatePluginCache($plugin_file, $slug);
        }

        if (!$plugin_file) {
            $this->logger->log_upload_failed($slug, 'Could not find plugin file after extraction');

            return $this->error_response('Could not find plugin file after extraction', HTTP_SERVER_ERROR);
        }

        return $plugin_file;
    }

    /** Invalidate OPcache entries and WP plugin cache for the given plugin. */
    private function invalidatePluginCache(string $plugin_file, string $slug) {
        $full_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($full_plugin_path, true);
        }

        $constants_file = WP_PLUGIN_DIR . '/' . $slug . '/includes/constants.php';
        $shouldInvalidateConstants = file_exists($constants_file) && function_exists('opcache_invalidate');
        if ($shouldInvalidateConstants) {
            opcache_invalidate($constants_file, true);
        }

        wp_cache_delete('plugins', 'plugins');
    }

    /** Activate the plugin if requested or if it was previously active. */
    private function activate_if_needed($plugin_file, $slug, $activate, $was_active, $is_update) {
        if (!$activate && !$was_active) {
            return array('activated' => false);
        }

        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            $this->logger->log_upload_failed($slug, MSG_ACTIVATION_FAILED . ': ' . $error_msg);
            return RiseupEnvelopeBuilder::success('Plugin uploaded but activation failed', HTTP_OK)
                ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_UPLOAD)
                ->setSingleResult(array(
                    'plugin_slug' => $slug, 'is_update' => $is_update,
                    'activated' => false, 'activation_error' => $error_msg,
                ))
                ->toResponse();
        }

        return array('activated' => true);
    }

    /** Detect the installed plugin version from disk. */
    private function detect_installed_version($plugin_file, $slug, $is_self_update, $client_version) {
        $installed_version = '';
        $full_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        clearstatcache(true, $full_path);
        if (file_exists($full_path)) {
            $file_contents = file_get_contents($full_path, false, null, 0, 8192);
            if ($file_contents !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $file_contents, $matches)) {
                $installed_version = $matches[1];
            }
        }

        if (empty($installed_version)) {
            $plugin_data = get_plugin_data($full_path, false, false);
            if (!empty($plugin_data['Version'])) {
                $installed_version = $plugin_data['Version'];
            }
        }

        if ($is_self_update) {
            $version = $client_version ?: ($installed_version ?: PLUGIN_VERSION);
        } else {
            $version = $installed_version ?: ($client_version ?: PLUGIN_VERSION);
        }

        return array('version' => $version);
    }
}
