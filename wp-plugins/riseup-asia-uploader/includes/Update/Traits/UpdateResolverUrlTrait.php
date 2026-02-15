<?php
/**
 * UpdateResolverUrlTrait — URL resolution with redirect following and caching.
 *
 * @package RiseupAsia\Update\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Update\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Helpers\BooleanHelpers;

trait UpdateResolverUrlTrait {

    public function resolveUrl(string $url, int $maxRedirects = 5): string|\WP_Error {
        $this->fileLogger->info('Resolving URL through redirects', array('url' => $url));

        $currentUrl = $url;

        for ($i = 0; $i < $maxRedirects; $i++) {
            $result = $this->followSingleRedirect($currentUrl);
            if (is_wp_error($result)) {
                return $result;
            }
            if ($result === null) {
                return $this->logResolvedUrl($url, $currentUrl, $i);
            }
            $currentUrl = $result;
        }

        $this->fileLogger->error('Max redirects exceeded', array('url' => $url, 'redirects' => $maxRedirects));
        return new \WP_Error('max_redirects', 'Maximum redirect limit exceeded');
    }

    private function followSingleRedirect(string $url): string|\WP_Error|null {
        $response = wp_remote_head($url, array('timeout' => 15, 'redirection' => 0, 'sslverify' => true));

        if (is_wp_error($response)) {
            $this->fileLogger->error('URL resolution failed', array('url' => $url, 'error' => $response->get_error_message()));
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $this->fileLogger->debug('Redirect check', array('url' => $url, 'status' => $status));

        if (BooleanHelpers::isNotInList($status, array(301, 302, 303, 307, 308))) {
            return null;
        }

        $location = wp_remote_retrieve_header($response, 'location');
        if (empty($location)) {
            $this->fileLogger->error('Redirect without Location header', array('url' => $url));
            return new \WP_Error('no_location', 'Redirect response missing Location header');
        }

        if (strpos($location, 'http') !== 0) {
            $parsed = parse_url($url);
            $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
        }

        $this->fileLogger->debug('Following redirect', array('from' => $url, 'to' => $location));
        return $location;
    }

    private function logResolvedUrl(string $original, string $final, int $hops): string {
        $this->fileLogger->info('URL resolved', array('original' => $original, 'final' => $final, 'hops' => $hops));
        return $final;
    }

    public function getUpdateUrl(bool $forceResolve = false): string|\WP_Error {
        $settings = $this->getSettings();

        if (empty($settings['master_url'])) {
            return new \WP_Error('no_master_url', 'No master update URL configured');
        }

        if (!$forceResolve && $this->isCacheValid($settings)) {
            $this->fileLogger->debug('Using cached resolved URL', array('url' => $settings['resolved_url']));
            return $settings['resolved_url'];
        }

        $resolved = $this->resolveUrl($settings['master_url']);
        if (is_wp_error($resolved)) {
            $this->saveSettings(array('last_error' => $resolved->get_error_message(), 'last_check' => current_time('mysql', true)));
            return $resolved;
        }

        $this->saveSettings(array('resolved_url' => $resolved, 'resolved_at' => current_time('mysql', true), 'last_check' => current_time('mysql', true), 'last_error' => ''));
        return $resolved;
    }

    private function isCacheValid(array $settings): bool {
        if (empty($settings['resolved_url']) || empty($settings['resolved_at'])) {
            return false;
        }
        $cacheDays = max(1, (int) $settings['cache_days']);
        $resolvedAt = strtotime($settings['resolved_at']);
        return time() < ($resolvedAt + ($cacheDays * DAY_IN_SECONDS));
    }

    public function clearCache(): bool {
        $this->fileLogger->info('Clearing update URL cache');
        return $this->saveSettings(array('resolved_url' => '', 'resolved_at' => ''));
    }
}
