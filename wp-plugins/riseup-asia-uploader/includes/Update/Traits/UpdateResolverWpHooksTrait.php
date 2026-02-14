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
    public function checkForPluginUpdate($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $settings = $this->getSettings();
        if (!$settings['enabled'] || empty($settings['master_url'])) {
            return $transient;
        }

        $this->fileLogger->debug('Checking for plugin update');
        $updateInfo = $this->fetchUpdateInfo();
        if (is_wp_error($updateInfo) || empty($updateInfo['version'])) {
            return $transient;
        }

        $pluginFile = PLUGIN_SLUG . '/' . PLUGIN_SLUG . '.php';

        if (version_compare($updateInfo['version'], PLUGIN_VERSION, '>')) {
            $transient->response[$pluginFile] = $this->buildUpdateTransientEntry($updateInfo, $pluginFile);
        } else {
            unset($transient->response[$pluginFile]);
            $transient->no_update[$pluginFile] = $this->buildNoUpdateTransientEntry($pluginFile);
        }

        return $transient;
    }

    /**
     * Build a transient entry for an available update.
     *
     * @param array  $updateInfo Update info.
     * @param string $pluginFile Plugin file path.
     * @return object Transient entry.
     */
    private function buildUpdateTransientEntry(array $updateInfo, string $pluginFile): object {
        $this->fileLogger->info('Update available', array('current' => PLUGIN_VERSION, 'new' => $updateInfo['version']));

        return (object) array(
            'id' => PLUGIN_SLUG, 'slug' => PLUGIN_SLUG, 'plugin' => $pluginFile,
            'new_version' => $updateInfo['version'], 'url' => $updateInfo['url'] ?? '',
            'package' => $updateInfo['package'], 'icons' => array(), 'banners' => array(),
            'tested' => $updateInfo['tested'] ?? '', 'requires' => $updateInfo['requires'] ?? '',
            'requires_php' => $updateInfo['requires_php'] ?? '',
        );
    }

    /**
     * Build a transient entry indicating no update available.
     *
     * @param string $pluginFile Plugin file path.
     * @return object Transient entry.
     */
    private function buildNoUpdateTransientEntry(string $pluginFile): object {
        return (object) array(
            'id' => PLUGIN_SLUG, 'slug' => PLUGIN_SLUG,
            'plugin' => $pluginFile, 'new_version' => PLUGIN_VERSION, 'url' => '', 'package' => '',
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
    public function pluginInfo($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== PLUGIN_SLUG) {
            return $result;
        }

        $settings = $this->getSettings();
        $updateInfo = $settings['update_info'];

        if (empty($updateInfo)) {
            return $result;
        }

        return $this->buildPluginInfoObject($updateInfo);
    }

    /**
     * Build the plugin info object for the details modal.
     *
     * @param array $updateInfo Update metadata.
     * @return object Plugin info.
     */
    private function buildPluginInfoObject(array $updateInfo): object {
        return (object) array(
            'name' => PLUGIN_NAME, 'slug' => PLUGIN_SLUG,
            'version' => $updateInfo['version'] ?? PLUGIN_VERSION,
            'author' => 'MD ALIM UL KARIM', 'homepage' => 'https://rasia.pro/alim-r-profile-v1',
            'requires' => $updateInfo['requires'] ?? MIN_WP_VERSION,
            'requires_php' => $updateInfo['requires_php'] ?? MIN_PHP_VERSION,
            'tested' => $updateInfo['tested'] ?? get_bloginfo('version'),
            'download_link' => $updateInfo['package'] ?? '',
            'sections' => array(
                'description' => 'Remote plugin management, blog post publishing, and audit logging via REST API.',
                'changelog' => $updateInfo['changelog'] ?? 'See plugin repository for changelog.',
            ),
        );
    }

    /**
     * Test connection to update server.
     *
     * @return array Test result with status and message.
     */
    public function testConnection() {
        $settings = $this->getSettings();

        if (empty($settings['master_url'])) {
            return array('success' => false, 'message' => 'No master URL configured');
        }

        $this->fileLogger->info('Testing update server connection');
        $resolved = $this->resolveUrl($settings['master_url']);

        if (is_wp_error($resolved)) {
            return array('success' => false, 'message' => $resolved->get_error_message());
        }

        $this->saveSettings(array(
            'resolved_url' => $resolved, 'resolved_at' => current_time('mysql', true),
            'last_check' => current_time('mysql', true), 'last_error' => '',
        ));

        return array('success' => true, 'message' => 'Connection successful', 'resolved_url' => $resolved);
    }
}
