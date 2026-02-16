<?php
/**
 * UploadInstallActivateTrait — OPcache reset, activation, and version detection.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Response;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UploadInstallActivateTrait
{
    /** Reset OPcache and locate the plugin's main file. */
    private function resetOpcacheAndFindPlugin(string $slug): string|WP_REST_Response {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $plugin_file = $this->findPluginFile($slug);
        if (!empty($plugin_file)) {
            $this->invalidatePluginCache($plugin_file, $slug);
        }

        if (!$plugin_file) {
            $this->logger->logUploadFailed($slug, 'Could not find plugin file after extraction');
            return $this->errorResponse('Could not find plugin file after extraction', HttpStatusType::ServerError->value);
        }

        return $plugin_file;
    }

    /** Invalidate OPcache entries and WP plugin cache for the given plugin. */
    private function invalidatePluginCache(string $plugin_file, string $slug): void {
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
    private function activateIfNeeded(string $pluginFile, string $slug, bool $activate, bool $wasActive, bool $isUpdate): array|WP_REST_Response {
        if (!$activate && !$wasActive) {
            return array('activated' => false);
        }

        $result = activate_plugin($pluginFile);
        if (is_wp_error($result)) {
            return $this->buildActivationFailureResponse($slug, $isUpdate, $result->get_error_message());
        }

        return array('activated' => true);
    }

    /** Build response for failed activation after upload. */
    private function buildActivationFailureResponse(string $slug, bool $is_update, string $error_msg): WP_REST_Response {
        $this->logger->logUploadFailed($slug, ResponseMessageType::ActivationFailed->value . ': ' . $error_msg);
        return EnvelopeBuilder::success('Plugin uploaded but activation failed', HttpStatusType::Ok->value)
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::Upload->value)
            ->setSingleResult(array(
                'plugin_slug' => $slug, 'is_update' => $is_update,
                'activated' => false, 'activation_error' => $error_msg,
            ))
            ->toResponse();
    }

    /** Detect the installed plugin version from disk. */
    private function detectInstalledVersion(string $pluginFile, string $slug, bool $isSelfUpdate, string $clientVersion): array {
        $installed_version = $this->readVersionFromFile($pluginFile);

        if (empty($installed_version)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
            $installed_version = $plugin_data['Version'] ?? '';
        }

        $version = $this->resolveEffectiveVersion($installed_version, $clientVersion, $isSelfUpdate);
        return array('version' => $version);
    }

    /** Read the version header from a plugin's main PHP file. */
    private function readVersionFromFile(string $plugin_file): string {
        $full_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        clearstatcache(true, $full_path);

        if (BooleanHelpers::isFileMissing($full_path)) {
            return '';
        }

        $file_contents = file_get_contents($full_path, false, null, 0, 8192);
        if ($file_contents !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $file_contents, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /** Resolve the effective version based on self-update status and available sources. */
    private function resolveEffectiveVersion(string $installed, string $client, bool $is_self_update): string {
        if ($is_self_update) {
            return $client ?: ($installed ?: PluginConfigType::Version->value);
        }
        return $installed ?: ($client ?: PluginConfigType::Version->value);
    }
}