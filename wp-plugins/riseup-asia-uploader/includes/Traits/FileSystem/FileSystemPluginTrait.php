<?php
/**
 * FileSystemPluginTrait — plugin file detection and filesystem fallback.
 *
 * @package RiseupAsia\Traits\FileSystem
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\FileSystem;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

trait FileSystemPluginTrait {

    private function findPluginFile(string $slug): ?string {
        $this->ensurePluginFunctionsLoaded();
        $this->clearPluginCache();

        $allPlugins = $this->safeGetPlugins();
        if ($allPlugins === null) {
            return null;
        }

        if (empty($allPlugins)) {
            $this->fileLogger->warn('findPluginFile: get_plugins() returned empty — trying filesystem fallback', array(
                'requested_slug' => $slug,
            ));
            return $this->findPluginFileFromFilesystem($slug);
        }

        return $this->matchPluginBySlug($slug, $allPlugins);
    }

    private function ensurePluginFunctionsLoaded(): void {
        try {
            if (RiseupBooleanHelpers::isFuncMissing('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'findPluginFile: Failed to load plugin.php');
        }
    }

    private function clearPluginCache(): void {
        try {
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache(true);
            } else {
                wp_cache_delete('plugins', 'plugins');
            }
        } catch (Throwable $e) {
            $this->fileLogger->warn('findPluginFile: Failed to clear plugin cache', array(
                'error' => $e->getMessage(),
            ));
        }
    }

    private function safeGetPlugins(): ?array {
        try {
            return get_plugins();
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'findPluginFile: get_plugins() threw an exception');
            return null;
        }
    }

    private function matchPluginBySlug(string $slug, array $allPlugins): ?string {
        $availableSlugs = array();
        foreach ($allPlugins as $pluginFile => $pluginData) {
            $pluginSlug = dirname($pluginFile);
            if ($pluginSlug === '.') {
                $pluginSlug = basename($pluginFile, '.php');
            }
            if ($pluginSlug === $slug) {
                return $pluginFile;
            }
            $availableSlugs[] = $pluginSlug;
        }

        $this->fileLogger->warn('Plugin slug not found via get_plugins(), trying filesystem fallback', array(
            'requested_slug'  => $slug,
            'available_slugs' => $availableSlugs,
            'total_plugins'   => count($allPlugins),
        ));

        return $this->findPluginFileFromFilesystem($slug);
    }

    private function findPluginFileFromFilesystem(string $slug): ?string {
        try {
            $dirResult = $this->findDirPlugin($slug);
            if ($dirResult !== null) {
                return $dirResult;
            }

            return $this->findSingleFilePlugin($slug);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'findPluginFileFromFilesystem: Filesystem scan failed');
        }

        return null;
    }

    private function findDirPlugin(string $slug): ?string {
        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

        if (RiseupBooleanHelpers::isDirMissing($pluginDir)) {
            return null;
        }

        $mainFile = $pluginDir . '/' . $slug . '.php';
        if (file_exists($mainFile)) {
            $this->fileLogger->info('findDirPlugin: Found directory plugin', array(
                'plugin_file' => $slug . '/' . $slug . '.php',
            ));
            return $slug . '/' . $slug . '.php';
        }

        return $this->scanDirForPluginHeader($slug, $pluginDir);
    }

    private function scanDirForPluginHeader(string $slug, string $pluginDir): ?string {
        $phpFiles = glob($pluginDir . '/*.php');
        if (!$phpFiles) {
            return null;
        }

        foreach ($phpFiles as $file) {
            $header = @file_get_contents($file, false, null, 0, 8192);
            if ($header !== false && stripos($header, 'Plugin Name:') !== false) {
                $relative = $slug . '/' . basename($file);
                $this->fileLogger->info('scanDirForPluginHeader: Found plugin via header scan', array(
                    'plugin_file' => $relative,
                ));
                return $relative;
            }
        }

        return null;
    }

    private function findSingleFilePlugin(string $slug): ?string {
        $singleFile = WP_PLUGIN_DIR . '/' . $slug . '.php';
        if (file_exists($singleFile)) {
            $this->fileLogger->info('findSingleFilePlugin: Found single-file plugin', array(
                'plugin_file' => $slug . '.php',
            ));
            return $slug . '.php';
        }

        $this->fileLogger->warn('findSingleFilePlugin: Plugin not found on filesystem', array(
            'requested_slug' => $slug,
        ));

        return null;
    }
}
