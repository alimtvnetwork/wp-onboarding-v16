<?php
/**
 * PingHandlerTrait — Lightweight /ping endpoint returning author, company, and version.
 *
 * Supports both authorized and public modes via permission callback.
 * Spec: spec/18-how-to-write-wordpress-plugin/14-rest-api-conventions.md
 *
 * @package QUpload\Traits\Core
 * @since   2.32.0
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
use QUpload\Helpers\DateHelper;

trait PingHandlerTrait
{
    /**
     * Handle GET /ping — returns author, company, and version.
     */
    public function handlePing(WP_REST_Request $request): WP_REST_Response
    {
        return $this->safeExecute(function () {
            $this->fileLogger->info('Ping endpoint called', [
                'endpoint'  => 'GET ' . EndpointType::Ping->route(),
                'namespace' => PluginConfigType::apiFullNamespace(),
                'version'   => PluginConfigType::Version->value,
            ]);

            return EnvelopeBuilder::success()
                ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Ping->route())
                ->setSingleResult([
                    ResponseKeyType::Author->value  => PluginConfigType::Author->value,
                    ResponseKeyType::Company->value  => PluginConfigType::Company->value,
                    ResponseKeyType::Version->value => PluginConfigType::Version->value,
                ])
                ->toResponse();
        }, 'handlePing');
    }
}
