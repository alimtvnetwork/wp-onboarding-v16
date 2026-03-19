<?php
/**
 * StatusHandlerTrait — Status endpoint handler for QUpload.
 *
 * @package QUpload\Traits\Core
 * @since   1.0.0
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
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\EnvelopeBuilder;

trait StatusHandlerTrait
{
    public function handleStatus(WP_REST_Request $request): WP_REST_Response {
        $dbAvailable = property_exists($this, 'db') && $this->db !== null;

        $this->fileLogger->info('Status endpoint called', [
            'endpoint' => 'GET /' . EndpointType::Status->route(),
            'namespace' => PluginConfigType::apiFullNamespace(),
            'version' => PluginConfigType::Version->value,
            'dbAvailable' => $dbAvailable,
            'requestedAt' => DateHelper::nowIso(),
        ]);

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Status->route())
            ->setSingleResult([
                ResponseKeyType::Plugin->value      => PluginConfigType::Name->value,
                ResponseKeyType::Version->value     => PluginConfigType::Version->value,
                'Slug'                              => PluginConfigType::Slug->value,
                'Api'                               => PluginConfigType::apiFullNamespace(),
                'SiteUrl'                           => get_site_url(),
                ResponseKeyType::PhpVersion->value  => PHP_VERSION,
                ResponseKeyType::WpVersion->value   => get_bloginfo('version'),
                'DbAvailable'                       => $dbAvailable,
                'ServerTime'                        => DateHelper::nowIso(),
                ResponseKeyType::Timestamp->value   => DateHelper::nowIso(),
                ResponseKeyType::UploadMaxFilesize->value      => ini_get('upload_max_filesize'),
                ResponseKeyType::PostMaxSize->value             => ini_get('post_max_size'),
                ResponseKeyType::MemoryLimit->value             => ini_get('memory_limit'),
                ResponseKeyType::UploadMaxFilesizeBytes->value => self::phpSizeToBytes(ini_get('upload_max_filesize')),
                ResponseKeyType::PostMaxSizeBytes->value        => self::phpSizeToBytes(ini_get('post_max_size')),
            ])
            ->toResponse();
    }

    /**
     * Convert PHP ini shorthand size (e.g. '128M', '2G') to bytes.
     */
    private static function phpSizeToBytes(string $size): int {
        $size = trim($size);
        $value = (int) $size;
        $unit = strtolower(substr($size, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
