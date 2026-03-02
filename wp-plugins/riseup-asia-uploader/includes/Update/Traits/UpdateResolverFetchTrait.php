<?php
/**
 * UpdateResolverFetchTrait — update info fetching and retry logic.
 *
 * @package RiseupAsia\Update\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Update\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;
use RiseupAsia\Enums\ContentTypeValueType;
use RiseupAsia\Enums\HttpConfigType;
use RiseupAsia\Enums\HttpHeaderType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\WpErrorCodeType;


trait UpdateResolverFetchTrait {

    public function fetchUpdateInfo(bool $forceCheck = false): array|WP_Error {
        $settings = $this->getSettings();
        $isDisabled = ($settings['enabled'] === false);

        if ($isDisabled) {
            return new WP_Error(WpErrorCodeType::Disabled->value, 'Auto-update is disabled');
        }

        $updateUrl = $this->resolveUpdateUrl($settings, $forceCheck);
        $response = $this->fetchUpdateResponse($updateUrl);

        if ($response instanceof WP_Error) {
            return $this->handleFetchFailure($settings, $forceCheck, $response);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $httpStatus = HttpStatusType::tryFrom($statusCode);
        $isStatusNotOk = ($httpStatus === null || $httpStatus->isOtherThan(HttpStatusType::Ok));

        if ($isStatusNotOk) {
            return $this->handleNon200Response($settings, $forceCheck, $statusCode);
        }

        $updateInfo = $this->parseUpdateResponseBody($response, $updateUrl);

        $this->saveSettings(array(
            'update_info' => $updateInfo, 'new_version' => $updateInfo['version'],
            'package_url' => $updateInfo['package'], 'last_check' => current_time('mysql', true), 'last_error' => '',
        ));

        $this->fileLogger->info('Update info fetched', $updateInfo);

        return $updateInfo;
    }

    private function resolveUpdateUrl(array $settings, bool $forceCheck): string {
        $updateUrl = $this->getUpdateUrl($forceCheck);
        if (is_wp_error($updateUrl)) {
            $this->fileLogger->warn('Falling back to master URL', array('error' => $updateUrl->get_error_message()));

            return $settings['master_url'];
        }

        return $updateUrl;
    }

    private function fetchUpdateResponse(string $url) {
        $response = wp_remote_get($url, HttpConfigType::defaultGetOptions());
        if (is_wp_error($response)) {
            $this->fileLogger->error('Failed to fetch update info', array('error' => $response->get_error_message()));
        }

        return $response;
    }

    private function handleFetchFailure(
        array $settings,
        bool $forceCheck,
        WP_Error $error,
    ) {
        $isRetryable = ($forceCheck === false) && !empty($settings['resolved_url'] ?? null);

        if ($isRetryable) {
            $this->fileLogger->info('Cached URL failed, resolving fresh');
            $this->clearCache();

            return $this->fetchUpdateInfo(true);
        }

        $this->saveSettings(array('last_error' => $error->get_error_message(), 'last_check' => current_time('mysql', true)));

        return $error;
    }

    private function handleNon200Response(
        array $settings,
        bool $forceCheck,
        int $statusCode,
    ) {
        $errorMsg = "HTTP $statusCode from update server";
        $this->fileLogger->error('Update server error', array('status' => $statusCode));

        $isRetryable = ($forceCheck === false) && !empty($settings['resolved_url'] ?? null);

        if ($isRetryable) {
            $this->fileLogger->info('Cached URL returned error, resolving fresh');
            $this->clearCache();

            return $this->fetchUpdateInfo(true);
        }

        $this->saveSettings(array('last_error' => $errorMsg, 'last_check' => current_time('mysql', true)));

        return new WP_Error(WpErrorCodeType::HttpError->value, $errorMsg);
    }

    private function parseUpdateResponseBody(array|WP_Error $response, string $updateUrl): array {
        $body = wp_remote_retrieve_body($response);
        $contentType = wp_remote_retrieve_header($response, HttpHeaderType::ContentType->value);
        $isJsonResponse = (strpos($contentType, ContentTypeValueType::Json->value) !== false);

        if (!$isJsonResponse) {
            return array('version' => '', 'package' => $updateUrl);
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fileLogger->error('Invalid JSON from update server');

            return array('version' => '', 'package' => $updateUrl);
        }

        return array(
            'version' => $data['version'] ?? '', 'package' => $data['package'] ?? '',
            'sha256' => $data['sha256'] ?? '',
            'tested' => $data['tested'] ?? '', 'requires' => $data['requires'] ?? '',
            'requires_php' => $data['requires_php'] ?? '', 'changelog' => $data['changelog'] ?? '',
        );
    }
}
