<?php
/**
 * UpdateResolverWpHooksTrait — WordPress filter hooks and test connection.
 *
 * @package RiseupAsia\Update\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Update\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\BooleanHelpers;

trait UpdateResolverWpHooksTrait {

    public function checkForPluginUpdate(object $transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }

        $settings = $this->getSettings();
        $isUpdateDisabled = (empty($settings['enabled']) || empty($settings['master_url']));

        if ($isUpdateDisabled) {
            return $transient;
        }

        $this->fileLogger->debug('Checking for plugin update');
        $updateInfo = $this->fetchUpdateInfo();
        if (is_wp_error($updateInfo) || empty($updateInfo['version'])) {
            return $transient;
        }

        $pluginFile = PluginConfigType::Slug->value . '/' . PluginConfigType::Slug->value . '.php';

        if (version_compare($updateInfo['version'], PluginConfigType::Version->value, '>')) {
            $transient->response[$pluginFile] = $this->buildUpdateTransientEntry($updateInfo, $pluginFile);
        } else {
            unset($transient->response[$pluginFile]);
            $transient->no_update[$pluginFile] = $this->buildNoUpdateTransientEntry($pluginFile);
        }

        return $transient;
    }

    private function buildUpdateTransientEntry(array $updateInfo, string $pluginFile): object {
        $this->fileLogger->info('Update available', array('current' => PluginConfigType::Version->value, 'new' => $updateInfo['version']));

        return (object) array(
            'id' => PluginConfigType::Slug->value, 'slug' => PluginConfigType::Slug->value, 'plugin' => $pluginFile,
            'new_version' => $updateInfo['version'], 'url' => $updateInfo['url'] ?? '',
            'package' => $updateInfo['package'], 'icons' => array(), 'banners' => array(),
            'tested' => $updateInfo['tested'] ?? '', 'requires' => $updateInfo['requires'] ?? '',
            'requires_php' => $updateInfo['requires_php'] ?? '',
        );
    }

    private function buildNoUpdateTransientEntry(string $pluginFile): object {
        return (object) array(
            'id' => PluginConfigType::Slug->value, 'slug' => PluginConfigType::Slug->value,
            'plugin' => $pluginFile, 'new_version' => PluginConfigType::Version->value, 'url' => '', 'package' => '',
        );
    }

    public function pluginInfo(
        false|object|array $result,
        string $action,
        object $args,
    ): false|object {
        if ($action !== 'plugin_information') {
            return $result;
        }

        $isSlugMismatch = (BooleanHelpers::isPropertyMissing($args, 'slug') || $args->slug !== PluginConfigType::Slug->value); // WP hook provides raw slug string; enum property default constraint applies

        if ($isSlugMismatch) {
            return $result;
        }

        $settings = $this->getSettings();
        $updateInfo = $settings['update_info'];

        if (empty($updateInfo)) {
            return $result;
        }

        return $this->buildPluginInfoObject($updateInfo);
    }

    private function buildPluginInfoObject(array $updateInfo): object {
        return (object) array(
            'name' => PluginConfigType::Name->value, 'slug' => PluginConfigType::Slug->value,
            'version' => $updateInfo['version'] ?? PluginConfigType::Version->value,
            'author' => 'MD ALIM UL KARIM', 'homepage' => 'https://rasia.pro/alim-r-profile-v1',
            'requires' => $updateInfo['requires'] ?? PluginConfigType::MinWpVersion->value,
            'requires_php' => $updateInfo['requires_php'] ?? PluginConfigType::MinPhpVersion->value,
            'tested' => $updateInfo['tested'] ?? get_bloginfo('version'),
            'download_link' => $updateInfo['package'] ?? '',
            'sections' => array(
                'description' => 'Remote plugin management, blog post publishing, and audit logging via REST API.',
                'changelog' => $updateInfo['changelog'] ?? 'See plugin repository for changelog.',
            ),
        );
    }

    public function testConnection(): array {
        $settings = $this->getSettings();

        if (empty($settings['master_url'])) {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Message->value => 'No master URL configured');
        }

        $this->fileLogger->info('Testing update server connection');
        $resolved = $this->resolveUrl($settings['master_url']);

        if (is_wp_error($resolved)) {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Message->value => $resolved->get_error_message());
        }

        $this->saveSettings(array(
            'resolved_url' => $resolved, 'resolved_at' => current_time('mysql', true),
            'last_check' => current_time('mysql', true), 'last_error' => '',
        ));

        return array(ResponseKeyType::Success->value => true, ResponseKeyType::Message->value => ResponseMessageType::ConnectionSuccessful->value, ResponseKeyType::ResolvedUrl->value => $resolved);
    }
}
