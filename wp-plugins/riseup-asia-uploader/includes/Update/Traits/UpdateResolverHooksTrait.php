<?php
/**
 * UpdateResolverFetchTrait — update info fetching and retry logic.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UpdateResolverFetchTrait {

    /** Fetch update information from the update server. */
    public function fetchUpdateInfo(bool $forceCheck = false): array|WP_Error {
        $settings = $this->getSettings();
        if (!$settings['enabled']) {
            return new WP_Error('disabled', 'Auto-update is disabled');
        }

        $updateUrl = $this->resolveUpdateUrl($settings, $forceCheck);
        $response = $this->fetchUpdateResponse($updateUrl);

        if ($response instanceof WP_Error) {
            return $this->handleFetchFailure($settings, $forceCheck, $response);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
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

    /**
     * Resolve the update URL, falling back to master URL on error.
     *
     * @param array $settings   Current settings.
     * @param bool  $forceCheck Whether to force fresh resolution.
     * @return string Resolved URL.
     */
    private function resolveUpdateUrl(array $settings, bool $forceCheck): string {
        $updateUrl = $this->getUpdateUrl($forceCheck);
        if (is_wp_error($updateUrl)) {
            $this->fileLogger->warn('Falling back to master URL', array('error' => $updateUrl->get_error_message()));
            return $settings['master_url'];
        }
        return $updateUrl;
    }

    /**
     * Fetch the update response from the server.
     *
     * @param string $url Update URL.
     * @return array|WP_Error HTTP response or error.
     */
    private function fetchUpdateResponse(string $url) {
        $response = wp_remote_get($url, array('timeout' => 30, 'sslverify' => true));
        if (is_wp_error($response)) {
            $this->fileLogger->error('Failed to fetch update info', array('error' => $response->get_error_message()));
        }
        return $response;
    }

    /**
     * Handle a fetch failure with retry logic.
     *
     * @param array    $settings   Current settings.
     * @param bool     $forceCheck Whether this was a forced check.
     * @param WP_Error $error      The fetch error.
     * @return array|WP_Error Retry result or error.
     */
    private function handleFetchFailure(array $settings, bool $forceCheck, WP_Error $error) {
        if (!$forceCheck && !empty($settings['resolved_url'])) {
            $this->fileLogger->info('Cached URL failed, resolving fresh');
            $this->clearCache();
            return $this->fetchUpdateInfo(true);
        }

        $this->saveSettings(array('last_error' => $error->get_error_message(), 'last_check' => current_time('mysql', true)));
        return $error;
    }

    /**
     * Handle a non-200 HTTP response with retry logic.
     *
     * @param array $settings   Current settings.
     * @param bool  $forceCheck Whether this was a forced check.
     * @param int   $statusCode HTTP status code.
     * @return array|WP_Error Retry result or error.
     */
    private function handleNon200Response(array $settings, bool $forceCheck, int $statusCode) {
        $errorMsg = "HTTP $statusCode from update server";
        $this->fileLogger->error('Update server error', array('status' => $statusCode));

        if (!$forceCheck && !empty($settings['resolved_url'])) {
            $this->fileLogger->info('Cached URL returned error, resolving fresh');
            $this->clearCache();
            return $this->fetchUpdateInfo(true);
        }

        $this->saveSettings(array('last_error' => $errorMsg, 'last_check' => current_time('mysql', true)));
        return new WP_Error('http_error', $errorMsg);
    }

    /** Parse the update response body into structured update info. */
    private function parseUpdateResponseBody(array|WP_Error $response, string $updateUrl): array {
        $body = wp_remote_retrieve_body($response);
        $contentType = wp_remote_retrieve_header($response, 'content-type');

        if (strpos($contentType, 'application/json') === false) {
            return array('version' => '', 'package' => $updateUrl);
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fileLogger->error('Invalid JSON from update server');
            return array('version' => '', 'package' => $updateUrl);
        }

        return array(
            'version' => $data['version'] ?? '', 'package' => $data['package'] ?? '',
            'tested' => $data['tested'] ?? '', 'requires' => $data['requires'] ?? '',
            'requires_php' => $data['requires_php'] ?? '', 'changelog' => $data['changelog'] ?? '',
        );
    }
}
