<?php
/**
 * OrchestratorPluginTrait — Plugin snapshot creation and archival.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use ZipArchive;
use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\PluginSelectionType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;

trait OrchestratorPluginTrait {

    private function snapshotPlugins(string $snapshotDir, string $selection = 'all'): array {
        $plugins_dir = $snapshotDir . '/plugins';
        if (!PathHelper::ensureDir($plugins_dir, true)) {
            $this->log(LogLevelType::Error->value, 'Failed to create plugins directory');
            return array('count' => 0, 'total_size' => 0, 'plugins' => array());
        }

        $plugins_to_snapshot = $this->collectPluginsToSnapshot($selection);
        $rootPdo = $this->openRootDbForPlugins($snapshotDir);

        $count = 0;
        $total_size = 0;
        $plugin_list = array();

        foreach ($plugins_to_snapshot as $plugin_file => $info) {
            $result = $this->archiveSinglePlugin($info, $plugins_dir, $rootPdo);
            if ($result === null) continue;
            if ($result['success']) {
                $total_size += $result['size'];
                $count++;
                $plugin_list[] = $result['entry'];
            }
        }

        $rootPdo = null;
        return array('count' => $count, 'total_size' => $total_size, 'plugins' => $plugin_list);
    }

    private function collectPluginsToSnapshot(string $selection): array {
        if (BooleanHelpers::isFuncMissing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option(\RiseupAsia\Enums\OptionNameType::ActivePlugins->value, array());
        $plugins_to_snapshot = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }
            if ($slug === \RiseupAsia\Enums\PluginConfigType::Slug->value) continue;

            $isEligible = ($selection === \RiseupAsia\Enums\PluginSelectionType::All->value || in_array($plugin_file, $active_plugins));
            if (!$isEligible) continue;

            $plugins_to_snapshot[$plugin_file] = array(
                'slug' => $slug, 'name' => $plugin_data['Name'] ?? $slug, 'version' => $plugin_data['Version'] ?? '0.0.0',
            );
        }

        $this->log(LogLevelType::Info->value, 'Snapshotting plugins', array('total' => count($all_plugins), 'selected' => count($plugins_to_snapshot), 'selection' => $selection));
        return $plugins_to_snapshot;
    }

    private function archiveSinglePlugin(array $info, string $pluginsDir, ?PDO $rootPdo): ?array {
        $slug = $info['slug'];
        $plugin_path = WP_PLUGIN_DIR . '/' . $slug;

        if (PathHelper::isDirMissing($plugin_path)) {
            $this->log(LogLevelType::Info->value, 'Skipping single-file plugin: ' . $slug);
            return null;
        }

        $zip_filename = $slug . '.zip';
        $zip_path = $pluginsDir . '/' . $zip_filename;
        $zip_result = $this->createPluginZip($plugin_path, $zip_path, $slug);

        if (!$zip_result['success']) {
            $this->log(LogLevelType::Warn->value, 'Failed to archive plugin: ' . $slug, array('error' => $zip_result['error']));
            return array('success' => false);
        }

        $entry = array('slug' => $info['slug'], 'name' => $info['name'], 'version' => $info['version'], 'zip' => $zip_filename, 'size' => filesize($zip_path));

        if ($rootPdo) {
            $this->rootDb->registerPluginSnapshot($rootPdo, array(
                'plugin_slug' => $info['slug'], 'plugin_name' => $info['name'], 'plugin_version' => $info['version'],
                'zip_file' => 'plugins/' . $zip_filename, 'file_size_bytes' => filesize($zip_path), 'checksum_md5' => md5_file($zip_path),
            ));
        }

        $this->log(LogLevelType::Info->value, sprintf('Plugin archived: %s (%s)', $info['name'], $this->formatBytes($entry['size'])));
        return array('success' => true, 'size' => $entry['size'], 'entry' => $entry);
    }

    private function createPluginZip(string $sourceDir, string $zipPath, string $slug): array {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array('success' => false, 'error' => 'Failed to create ZIP');
            }

            $sourceDir = rtrim($sourceDir, '/\\\\');
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relative = $slug . '/' . substr($item->getPathname(), strlen($sourceDir) + 1);
                $relative = str_replace('\\', '/', $relative);
                if ($item->isDir()) {
                    $zip->addEmptyDir($relative);
                } else {
                    $zip->addFile($item->getPathname(), $relative);
                }
            }

            $zip->close();

            $size = filesize($zipPath);
            if ($size === 0) {
                @unlink($zipPath);
                return array('success' => false, 'error' => 'ZIP file is empty');
            }

            return array('success' => true);
        } catch (Throwable $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    private function openRootDbForPlugins(string $snapshotDir): ?PDO {
        $root_path = $snapshotDir . '/a-root.db';
        if (PathHelper::isFileMissing($root_path)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $root_path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Could not open a-root.db for plugin registration', array('error' => $e->getMessage()));
            return null;
        }
    }
}
