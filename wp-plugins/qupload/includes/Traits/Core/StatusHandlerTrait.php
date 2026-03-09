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
        $this->fileLogger->info('Status endpoint called');

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Status->route())
            ->setSingleResult([
                ResponseKeyType::Plugin->value      => PluginConfigType::Name->value,
                ResponseKeyType::Version->value     => PluginConfigType::Version->value,
                ResponseKeyType::PhpVersion->value  => PHP_VERSION,
                ResponseKeyType::WpVersion->value   => get_bloginfo('version'),
                ResponseKeyType::Timestamp->value   => DateHelper::nowIso(),
            ])
            ->toResponse();
    }
}
