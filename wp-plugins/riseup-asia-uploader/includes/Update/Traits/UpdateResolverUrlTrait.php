<?php
/**
 * UpdateResolverUrlTrait — URL resolution with redirect following and caching.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UpdateResolverUrlTrait {

    /**
     * Resolve a URL through 301 redirects to get the final destination.
     *
     * @param string $url            The URL to resolve.
     * @param int    $max_redirects  Maximum redirects to follow.
     * @return string|WP_Error Final URL or error.
     */
    public function resolve_url($url, $max_redirects = 5) {
        $this->file_logger->info('Resolving URL through redirects', array('url' => $url));

        $current_url = $url;

        for ($i = 0; $i < $max_redirects; $i++) {
            $result = $this->followSingleRedirect($current_url);
            if (is_wp_error($result)) {
                return $result;
            }
            if ($result === null) {
                return $this->logResolvedUrl($url, $current_url, $i);
            }
            $current_url = $result;
        }

        $this->file_logger->error('Max redirects exceeded', array('url' => $url, 'redirects' => $max_redirects));
        return new WP_Error('max_redirects', 'Maximum redirect limit exceeded');
    }

    /**
     * Follow a single HTTP redirect and return the target URL.
     *
     * @param string $url URL to check.
     * @return string|WP_Error|null Redirect target, WP_Error on failure, null if no redirect.
     */
    private function followSingleRedirect(string $url) {
        $response = wp_remote_head($url, array('timeout' => 15, 'redirection' => 0, 'sslverify' => true));

        if (is_wp_error($response)) {
            $this->file_logger->error('URL resolution failed', array('url' => $url, 'error' => $response->get_error_message()));
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $this->file_logger->debug('Redirect check', array('url' => $url, 'status' => $status));

        if (!in_array($status, array(301, 302, 303, 307, 308))) {
            return null;
        }

        $location = wp_remote_retrieve_header($response, 'location');
        if (empty($location)) {
            $this->file_logger->error('Redirect without Location header', array('url' => $url));
            return new WP_Error('no_location', 'Redirect response missing Location header');
        }

        if (strpos($location, 'http') !== 0) {
            $parsed = parse_url($url);
            $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
        }

        $this->file_logger->debug('Following redirect', array('from' => $url, 'to' => $location));
        return $location;
    }

    /**
     * Log a successful URL resolution.
     *
     * @param string $original Original URL.
     * @param string $final    Final resolved URL.
     * @param int    $hops     Number of redirects followed.
     * @return string The final URL.
     */
    private function logResolvedUrl(string $original, string $final, int $hops): string {
        $this->file_logger->info('URL resolved', array('original' => $original, 'final' => $final, 'hops' => $hops));
        return $final;
    }

    /**
     * Get the update URL, using cache if valid or resolving fresh.
     *
     * @param bool $force_resolve Force fresh resolution, ignoring cache.
     * @return string|WP_Error Resolved URL or error.
     */
    public function get_update_url($force_resolve = false) {
        $settings = $this->get_settings();

        if (empty($settings['master_url'])) {
            return new WP_Error('no_master_url', 'No master update URL configured');
        }

        if (!$force_resolve && $this->is_cache_valid($settings)) {
            $this->file_logger->debug('Using cached resolved URL', array('url' => $settings['resolved_url']));
            return $settings['resolved_url'];
        }

        $resolved = $this->resolve_url($settings['master_url']);
        if (is_wp_error($resolved)) {
            $this->save_settings(array('last_error' => $resolved->get_error_message(), 'last_check' => current_time('mysql', true)));
            return $resolved;
        }

        $this->save_settings(array('resolved_url' => $resolved, 'resolved_at' => current_time('mysql', true), 'last_check' => current_time('mysql', true), 'last_error' => ''));
        return $resolved;
    }

    /**
     * Check if the cached URL is still valid.
     *
     * @param array $settings Settings array.
     * @return bool True if cache is valid.
     */
    private function is_cache_valid($settings) {
        if (empty($settings['resolved_url']) || empty($settings['resolved_at'])) {
            return false;
        }
        $cache_days = max(1, (int) $settings['cache_days']);
        $resolved_at = strtotime($settings['resolved_at']);
        return time() < ($resolved_at + ($cache_days * DAY_IN_SECONDS));
    }

    /**
     * Clear the cached resolved URL.
     *
     * @return bool True on success.
     */
    public function clear_cache() {
        $this->file_logger->info('Clearing update URL cache');
        return $this->save_settings(array('resolved_url' => '', 'resolved_at' => ''));
    }
}
