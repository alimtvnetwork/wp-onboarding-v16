<?php
/**
 * UpdateResolverWpHooksTrait — WordPress filter hooks and test connection.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UpdateResolverWpHooksTrait {

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
        $this->file_logger->info('Update available', array('current' => PLUGIN_VERSION, 'new' => $update_info['version']));

        return (object) array(
            'id' => PLUGIN_SLUG, 'slug' => PLUGIN_SLUG, 'plugin' => $plugin_file,
            'new_version' => $update_info['version'], 'url' => $update_info['url'] ?? '',
            'package' => $update_info['package'], 'icons' => array(), 'banners' => array(),
            'tested' => $update_info['tested'] ?? '', 'requires' => $update_info['requires'] ?? '',
            'requires_php' => $update_info['requires_php'] ?? '',
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
            'id' => PLUGIN_SLUG, 'slug' => PLUGIN_SLUG,
            'plugin' => $plugin_file, 'new_version' => PLUGIN_VERSION, 'url' => '', 'package' => '',
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
            'name' => PLUGIN_NAME, 'slug' => PLUGIN_SLUG,
            'version' => $update_info['version'] ?? PLUGIN_VERSION,
            'author' => 'MD ALIM UL KARIM', 'homepage' => 'https://rasia.pro/alim-r-profile-v1',
            'requires' => $update_info['requires'] ?? MIN_WP_VERSION,
            'requires_php' => $update_info['requires_php'] ?? MIN_PHP_VERSION,
            'tested' => $update_info['tested'] ?? get_bloginfo('version'),
            'download_link' => $update_info['package'] ?? '',
            'sections' => array(
                'description' => 'Remote plugin management, blog post publishing, and audit logging via REST API.',
                'changelog' => $update_info['changelog'] ?? 'See plugin repository for changelog.',
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
            return array('success' => false, 'message' => 'No master URL configured');
        }

        $this->file_logger->info('Testing update server connection');
        $resolved = $this->resolve_url($settings['master_url']);

        if (is_wp_error($resolved)) {
            return array('success' => false, 'message' => $resolved->get_error_message());
        }

        $this->save_settings(array(
            'resolved_url' => $resolved, 'resolved_at' => current_time('mysql', true),
            'last_check' => current_time('mysql', true), 'last_error' => '',
        ));

        return array('success' => true, 'message' => 'Connection successful', 'resolved_url' => $resolved);
    }
}
