<?php
/**
 * PluginLifecycleHelpersTrait — Shared helpers for plugin lifecycle operations.
 *
 * Provides resolvePluginFromRequest, loadPluginFunctions, and logPluginLifecycle
 * used by PluginLifecycleEnableTrait and PluginLifecycleDeleteTrait.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\BooleanHelpers;

trait PluginLifecycleHelpersTrait
{
    /**
     * Resolve the target plugin slug and file path from a REST request.
     *
     * @return array{slug: string, plugin_file: string}|WP_REST_Response
     */
    private function resolvePluginFromRequest(WP_REST_Request $request): array|WP_REST_Response
    {
        $slug = sanitize_text_field($request->get_param('plugin_slug') ?? '');
        if (empty($slug)) {
            return $this->errorResponse(
                ResponseMessageType::MissingPluginSlug->value,
                HttpStatusType::BadRequest->value
            );
        }

        if ($slug === PluginConfigType::Slug->value) {
            return $this->errorResponse(
                ResponseMessageType::SelfActionProhibited->value,
                HttpStatusType::Forbidden->value
            );
        }

        $plugins = get_plugins();
        $pluginFile = $this->findPluginFileBySlug($slug, $plugins);

        if ($pluginFile === null) {
            return $this->errorResponse(
                ResponseMessageType::PluginNotFound->value . ': ' . $slug,
                HttpStatusType::NotFound->value
            );
        }

        return ['slug' => $slug, 'plugin_file' => $pluginFile];
    }

    /**
     * Find the main plugin file matching a given slug.
     *
     * @param string                $slug    Plugin directory slug.
     * @param array<string, mixed>  $plugins Result of get_plugins().
     */
    private function findPluginFileBySlug(string $slug, array $plugins): ?string
    {
        foreach ($plugins as $file => $data) {
            if (str_starts_with($file, $slug . '/')) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Ensure required WordPress plugin functions are loaded.
     *
     * @param bool $includeFileSystem Also load filesystem functions for delete operations.
     * @return WP_REST_Response|null Error response on failure, null on success.
     */
    private function loadPluginFunctions(bool $includeFileSystem = false): ?WP_REST_Response
    {
        if (BooleanHelpers::isFuncMissing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $isFileSystemNeeded = $includeFileSystem && BooleanHelpers::isFuncMissing('delete_plugins');
        if ($isFileSystemNeeded) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return null;
    }

    /**
     * Log a plugin lifecycle event via the activity logger.
     *
     * @param string       $action  The lifecycle action (enable, disable, delete).
     * @param string       $slug    The target plugin slug.
     * @param string       $status  The result status (success, failed).
     * @param array<string, mixed> $context Optional extra context.
     */
    private function logPluginLifecycle(
        string $action,
        string $slug,
        string $status,
        array $context = [],
    ): void
    {
        $entry = array_merge(
            [
                'action'      => $action,
                'plugin_slug' => $slug,
                'status'      => $status,
            ],
            $context
        );

        $this->logActivity(PluginConfigType::LogPrefix->value . ' Plugin lifecycle', $entry);
    }
}
