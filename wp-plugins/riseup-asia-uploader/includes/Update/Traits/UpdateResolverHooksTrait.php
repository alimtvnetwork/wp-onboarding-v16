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

    /**
     * Fetch update information from the update server.
     *
     * @param bool $force_check Force a fresh check.
     * @return array|WP_Error Update info or error.
     */
    public function fetch_update_info($force_check = false) {
        $settings = $this->get_settings();
        if (!$settings['enabled']) {
            return new WP_Error('disabled', 'Auto-update is disabled');
        }

        $update_url = $this->resolveUpdateUrl($settings, $force_check);
        $response = $this->fetchUpdateResponse($update_url);

        if ($response instanceof WP_Error) {
            return $this->handleFetchFailure($settings, $force_check, $response);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return $this->handleNon200Response($settings, $force_check, $status_code);
        }

        $update_info = $this->parseUpdateResponseBody($response, $update_url);

        $this->save_settings(array(
            'update_info' => $update_info, 'new_version' => $update_info['version'],
            'package_url' => $update_info['package'], 'last_check' => current_time('mysql', true), 'last_error' => '',
        ));

        $this->file_logger->info('Update info fetched', $update_info);
        return $update_info;
    }

    /**
     * Resolve the update URL, falling back to master URL on error.
     *
     * @param array $settings    Current settings.
     * @param bool  $force_check Whether to force fresh resolution.
     * @return string Resolved URL.
     */
    private function resolveUpdateUrl(array $settings, bool $force_check): string {
        $update_url = $this->get_update_url($force_check);
        if (is_wp_error($update_url)) {
            $this->file_logger->warn('Falling back to master URL', array('error' => $update_url->get_error_message()));
            return $settings['master_url'];
        }
        return $update_url;
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
            $this->file_logger->error('Failed to fetch update info', array('error' => $response->get_error_message()));
        }
        return $response;
    }

    /**
     * Handle a fetch failure with retry logic.
     *
     * @param array    $settings    Current settings.
     * @param bool     $force_check Whether this was a forced check.
     * @param WP_Error $error       The fetch error.
     * @return array|WP_Error Retry result or error.
     */
    private function handleFetchFailure(array $settings, bool $force_check, WP_Error $error) {
        if (!$force_check && !empty($settings['resolved_url'])) {
            $this->file_logger->info('Cached URL failed, resolving fresh');
            $this->clear_cache();
            return $this->fetch_update_info(true);
        }

        $this->save_settings(array('last_error' => $error->get_error_message(), 'last_check' => current_time('mysql', true)));
        return $error;
    }

    /**
     * Handle a non-200 HTTP response with retry logic.
     *
     * @param array $settings    Current settings.
     * @param bool  $force_check Whether this was a forced check.
     * @param int   $status_code HTTP status code.
     * @return array|WP_Error Retry result or error.
     */
    private function handleNon200Response(array $settings, bool $force_check, int $status_code) {
        $error_msg = "HTTP $status_code from update server";
        $this->file_logger->error('Update server error', array('status' => $status_code));

        if (!$force_check && !empty($settings['resolved_url'])) {
            $this->file_logger->info('Cached URL returned error, resolving fresh');
            $this->clear_cache();
            return $this->fetch_update_info(true);
        }

        $this->save_settings(array('last_error' => $error_msg, 'last_check' => current_time('mysql', true)));
        return new WP_Error('http_error', $error_msg);
    }

    /**
     * Parse the update response body into structured update info.
     *
     * @param array  $response   HTTP response.
     * @param string $update_url The URL used for the request.
     * @return array Update info.
     */
    private function parseUpdateResponseBody($response, string $update_url): array {
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if (strpos($content_type, 'application/json') === false) {
            return array('version' => '', 'package' => $update_url);
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->file_logger->error('Invalid JSON from update server');
            return array('version' => '', 'package' => $update_url);
        }

        return array(
            'version' => $data['version'] ?? '', 'package' => $data['package'] ?? '',
            'tested' => $data['tested'] ?? '', 'requires' => $data['requires'] ?? '',
            'requires_php' => $data['requires_php'] ?? '', 'changelog' => $data['changelog'] ?? '',
        );
    }
}
