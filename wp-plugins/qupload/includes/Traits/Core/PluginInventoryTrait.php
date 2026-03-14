<?php
/**
 * PluginInventoryTrait — GET /plugins REST endpoint handler for QUpload.
 *
 * Returns a list of all installed plugins with their status, version, and file path.
 *
 * @package QUpload\Traits\Core
 * @since   2.12.0
 */

namespace QUpload\Traits\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use QUpload\Enums\EndpointType;
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\EnvelopeBuilder;

trait PluginInventoryTrait
{
    /** Handle GET /plugins endpoint. */
    public function handlePlugins(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Plugins inventory endpoint called');

        return $this->safeExecute(
            fn () => $this->executePluginInventory(),
            'handlePlugins',
            ['endpoint' => 'plugins'],
        );
    }

    private function executePluginInventory(): WP_REST_Response {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        $activeMap = array_flip($activePlugins);

        $plugins = [];

        foreach ($allPlugins as $file => $data) {
            $slug = dirname($file);

            // Single-file plugins have '.' as dirname
            if ($slug === '.') {
                $slug = basename($file, '.php');
            }

            $plugins[] = [
                ResponseKeyType::Slug->value          => $slug,
                'Name'                                 => $data['Name'] ?? '',
                ResponseKeyType::PluginVersion->value => $data['Version'] ?? '',
                'IsActive'                             => isset($activeMap[$file]),
                'File'                                 => $file,
            ];
        }

        // Sort by slug for deterministic output
        usort($plugins, fn ($a, $b) => strcasecmp($a[ResponseKeyType::Slug->value], $b[ResponseKeyType::Slug->value]));

        $this->fileLogger->info('Plugin inventory complete', ['count' => count($plugins)]);

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Plugins->route())
            ->setListResult($plugins)
            ->toResponse();
    }
}
