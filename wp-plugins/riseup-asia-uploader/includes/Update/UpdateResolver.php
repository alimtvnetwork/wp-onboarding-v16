<?php
/**
 * Riseup Asia Uploader - Update Resolver
 *
 * Handles auto-update functionality with 301 redirect URL resolution and caching.
 *
 * @package RiseupAsiaUploader
 * @since   1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HookType;

/**
 * Class RiseupUpdateResolver
 *
 * Manages plugin auto-updates with 301 redirect resolution.
 */
class RiseupUpdateResolver {

    /**
     * Option name for update settings (stored in WordPress options).
     */
    const OPTION_NAME = 'riseup_update_settings';

    /**
     * Default cache duration in days.
     */
    const DEFAULT_CACHE_DAYS = 7;

    /**
     * File logger instance.
     *
     * @var RiseupFileLogger
     */
    private $file_logger;

    /**
     * Database instance.
     *
     * @var RiseupDatabase
     */
    private $db;

    /**
     * Singleton instance.
     *
     * @var RiseupUpdateResolver|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupUpdateResolver
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->file_logger = RiseupFileLogger::get_instance();
        $this->db = RiseupDatabase::get_instance();
        
        // Register WordPress update hooks if auto-update is enabled
        $settings = $this->get_settings();
        if (!empty($settings['enabled'])) {
            add_filter(HookType::PreSetSiteTransientUpdatePlugins->value, array($this, 'check_for_plugin_update'));
            add_filter(HookType::PluginsApi->value, array($this, 'plugin_info'), 10, 3);
            $this->file_logger->info('Auto-update hooks registered');
        }
    }

    /**
     * Get update settings.
     *
     * @return array Settings array with defaults.
     */
    public function get_settings() {
        $defaults = array(
            'enabled'        => false,
            'master_url'     => '',
            'resolved_url'   => '',
            'resolved_at'    => '',
            'cache_days'     => self::DEFAULT_CACHE_DAYS,
            'last_check'     => '',
            'last_error'     => '',
            'package_url'    => '',      // The actual ZIP download URL
            'new_version'    => '',      // Version from update server
            'update_info'    => array(), // Additional update metadata
        );
        
        $settings = get_option(self::OPTION_NAME, array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Save update settings.
     *
     * @param array $settings Settings to save.
     * @return bool True on success.
     */
    public function save_settings($settings) {
        $current = $this->get_settings();
        $merged = wp_parse_args($settings, $current);
        return update_option(self::OPTION_NAME, $merged);
    }

    /**
     * Resolve a URL through 301 redirects to get the final destination.
     *
     * @param string $url The URL to resolve.
     * @param int    $max_redirects Maximum redirects to follow.
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
        
        // Check if we have a valid cached URL
        if (!$force_resolve && $this->is_cache_valid($settings)) {
            $this->file_logger->debug('Using cached resolved URL', array(
                'url' => $settings['resolved_url'],
            ));
            return $settings['resolved_url'];
        }
        
        // Resolve the URL fresh
        $resolved = $this->resolve_url($settings['master_url']);
        
        if (is_wp_error($resolved)) {
            $this->save_settings(array(
                'last_error' => $resolved->get_error_message(),
                'last_check' => current_time('mysql', true),
            ));
            return $resolved;
        }
        
        // Cache the resolved URL
        $this->save_settings(array(
            'resolved_url' => $resolved,
            'resolved_at'  => current_time('mysql', true),
            'last_check'   => current_time('mysql', true),
            'last_error'   => '',
        ));
        
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
        $expiry = $resolved_at + ($cache_days * DAY_IN_SECONDS);
        
        return time() < $expiry;
    }

    /**
     * Clear the cached resolved URL.
     *
     * @return bool True on success.
     */
    public function clear_cache() {
        $this->file_logger->info('Clearing update URL cache');
        return $this->save_settings(array(
            'resolved_url' => '',
            'resolved_at'  => '',
        ));
    }

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
            'update_info'  => $update_info,
            'new_version'  => $update_info['version'],
            'package_url'  => $update_info['package'],
            'last_check'   => current_time('mysql', true),
            'last_error'   => '',
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
            $this->file_logger->warn('Falling back to master URL', array(
                'error' => $update_url->get_error_message(),
            ));

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

        $this->save_settings(array(
            'last_error' => $error->get_error_message(),
            'last_check' => current_time('mysql', true),
        ));

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

        $this->save_settings(array(
            'last_error' => $error_msg,
            'last_check' => current_time('mysql', true),
        ));

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
            'version'      => isset($data['version']) ? $data['version'] : '',
            'package'      => isset($data['package']) ? $data['package'] : '',
            'tested'       => isset($data['tested']) ? $data['tested'] : '',
            'requires'     => isset($data['requires']) ? $data['requires'] : '',
            'requires_php' => isset($data['requires_php']) ? $data['requires_php'] : '',
            'changelog'    => isset($data['changelog']) ? $data['changelog'] : '',
        );
    }

    /**
     * WordPress filter: Check for plugin updates.
     *
     * @param object $transient Update transient data.
     * @return object Modified transient.
     */
    public function check_for_plugin_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $settings = $this->get_settings();
        if (!$settings['enabled'] || empty($settings['master_url'])) {
            return $transient;
        }

        $this->file_logger->debug('Checking for plugin update');
        $update_info = $this->fetch_update_info();
        if (is_wp_error($update_info) || empty($update_info['version'])) {
            return $transient;
        }

        $plugin_file = PLUGIN_SLUG . '/' . PLUGIN_SLUG . '.php';

        if (version_compare($update_info['version'], PLUGIN_VERSION, '>')) {
            $transient->response[$plugin_file] = $this->buildUpdateTransientEntry($update_info, $plugin_file);
        } else {
            unset($transient->response[$plugin_file]);
            $transient->no_update[$plugin_file] = $this->buildNoUpdateTransientEntry($plugin_file);
        }

        return $transient;
    }

    /**
     * Build a transient entry for an available update.
     *
     * @param array  $update_info Update info.
     * @param string $plugin_file Plugin file path.
     * @return object Transient entry.
     */
    private function buildUpdateTransientEntry(array $update_info, string $plugin_file): object {
        $this->file_logger->info('Update available', array(
            'current' => PLUGIN_VERSION, 'new' => $update_info['version'],
        ));

        return (object) array(
            'id'           => PLUGIN_SLUG,
            'slug'         => PLUGIN_SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => $update_info['version'],
            'url'          => isset($update_info['url']) ? $update_info['url'] : '',
            'package'      => $update_info['package'],
            'icons'        => array(),
            'banners'      => array(),
            'tested'       => isset($update_info['tested']) ? $update_info['tested'] : '',
            'requires'     => isset($update_info['requires']) ? $update_info['requires'] : '',
            'requires_php' => isset($update_info['requires_php']) ? $update_info['requires_php'] : '',
        );
    }

    /**
     * Build a transient entry indicating no update available.
     *
     * @param string $plugin_file Plugin file path.
     * @return object Transient entry.
     */
    private function buildNoUpdateTransientEntry(string $plugin_file): object {
        return (object) array(
            'id'          => PLUGIN_SLUG,
            'slug'        => PLUGIN_SLUG,
            'plugin'      => $plugin_file,
            'new_version' => PLUGIN_VERSION,
            'url'         => '',
            'package'     => '',
        );
    }

    /**
     * WordPress filter: Plugin information for "View Details" modal.
     *
     * @param false|object|array $result The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin information or false.
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== PLUGIN_SLUG) {
            return $result;
        }

        $settings = $this->get_settings();
        $update_info = $settings['update_info'];

        if (empty($update_info)) {
            return $result;
        }

        return $this->buildPluginInfoObject($update_info);
    }

    /**
     * Build the plugin info object for the details modal.
     *
     * @param array $update_info Update metadata.
     * @return object Plugin info.
     */
    private function buildPluginInfoObject(array $update_info): object {
        return (object) array(
            'name'          => PLUGIN_NAME,
            'slug'          => PLUGIN_SLUG,
            'version'       => isset($update_info['version']) ? $update_info['version'] : PLUGIN_VERSION,
            'author'        => 'MD ALIM UL KARIM',
            'homepage'      => 'https://rasia.pro/alim-r-profile-v1',
            'requires'      => isset($update_info['requires']) ? $update_info['requires'] : MIN_WP_VERSION,
            'requires_php'  => isset($update_info['requires_php']) ? $update_info['requires_php'] : MIN_PHP_VERSION,
            'tested'        => isset($update_info['tested']) ? $update_info['tested'] : get_bloginfo('version'),
            'download_link' => isset($update_info['package']) ? $update_info['package'] : '',
            'sections'      => array(
                'description' => 'Remote plugin management, blog post publishing, and audit logging via REST API.',
                'changelog'   => isset($update_info['changelog']) ? $update_info['changelog'] : 'See plugin repository for changelog.',
            ),
        );
    }

    /**
     * Test connection to update server.
     *
     * @return array Test result with status and message.
     */
    public function test_connection() {
        $settings = $this->get_settings();
        
        if (empty($settings['master_url'])) {
            return array(
                'success' => false,
                'message' => 'No master URL configured',
            );
        }
        
        $this->file_logger->info('Testing update server connection');
        
        // Force fresh resolution
        $resolved = $this->resolve_url($settings['master_url']);
        
        if (is_wp_error($resolved)) {
            return array(
                'success' => false,
                'message' => $resolved->get_error_message(),
            );
        }
        
        // Update cache with new resolved URL
        $this->save_settings(array(
            'resolved_url' => $resolved,
            'resolved_at'  => current_time('mysql', true),
            'last_check'   => current_time('mysql', true),
            'last_error'   => '',
        ));
        
        return array(
            'success'      => true,
            'message'      => 'Connection successful',
            'resolved_url' => $resolved,
        );
    }
}
