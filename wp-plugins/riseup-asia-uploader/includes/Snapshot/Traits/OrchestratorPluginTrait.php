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
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\PluginSelectionType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ResultHelper;

trait OrchestratorPluginTrait {
    private function snapshotPlugins(string $snapshotDir, string $selection = 'all'): array {
        $pluginsDir = $snapshotDir . '/plugins';
        $isDirCreationFailed = (PathHelper::makeDirectory($pluginsDir, true) === false);

        if ($isDirCreationFailed) {
            $this->log(LogLevelType::Error->value, 'Failed to create plugins directory');

            return array(
                ResponseKeyType::Count->value     => 0,
                ResponseKeyType::TotalSize->value  => 0,
                ResponseKeyType::Plugins->value    => array(),
            );
        }

        $pluginsToSnapshot = $this->collectPluginsToSnapshot($selection);
        $rootPdo = $this->openRootDbForPlugins($snapshotDir);

        $count = 0;
        $totalSize = 0;
        $pluginList = array();

        foreach ($pluginsToSnapshot as $pluginFile => $info) {
            $result = $this->archiveSinglePlugin($info, $pluginsDir, $rootPdo);

            if ($result === null) {
                continue;
            }

            if ($result[ResponseKeyType::Success->value]) {
                $totalSize += $result[ResponseKeyType::Size->value];
                $count++;
                $pluginList[] = $result[ResponseKeyType::Entry->value];
            }
        }

        $rootPdo = null;

        return array(
            ResponseKeyType::Count->value    => $count,
            ResponseKeyType::TotalSize->value => $totalSize,
            ResponseKeyType::Plugins->value  => $pluginList,
        );
    }

    private function collectPluginsToSnapshot(string $selection): array {
        if (BooleanHelpers::isFuncMissing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = get_option(OptionNameType::ActivePlugins->value, array());
        $pluginsToSnapshot = array();

        foreach ($allPlugins as $pluginFile => $pluginData) {
            $slug = dirname($pluginFile);

            if ($slug === '.') {
                $slug = basename($pluginFile, '.php');
            }

            if ($slug === PluginConfigType::Slug->value) {
                continue;
            }

            $isEligible = ($selection === PluginSelectionType::All->value || in_array($pluginFile, $activePlugins));
            $isIneligible = ($isEligible === false);

            if ($isIneligible) {
                continue;
            }

            $pluginsToSnapshot[$pluginFile] = array(
                'slug'    => $slug,
                'name'    => $pluginData['Name'] ?? $slug,
                'version' => $pluginData['Version'] ?? '0.0.0',
            );
        }

        $this->log(LogLevelType::Info->value, 'Snapshotting plugins', array(
            'total'     => count($allPlugins),
            'selected'  => count($pluginsToSnapshot),
            'selection' => $selection,
        ));

        return $pluginsToSnapshot;
    }

    private function archiveSinglePlugin(
        array $info,
        string $pluginsDir,
        ?PDO $rootPdo,
    ): ?array {
        $slug = $info['slug'];
        $pluginPath = WP_PLUGIN_DIR . '/' . $slug;

        if (PathHelper::isDirMissing($pluginPath)) {
            $this->log(LogLevelType::Info->value, 'Skipping single-file plugin: ' . $slug);

            return null;
        }

        $zipFilename = $slug . '.zip';
        $zipPath = $pluginsDir . '/' . $zipFilename;
        $zipResult = $this->createPluginZip($pluginPath, $zipPath, $slug);
        $isZipFailed = BooleanHelpers::isResultFailed($zipResult);

        if ($isZipFailed) {
            $this->log(LogLevelType::Warn->value, 'Failed to archive plugin: ' . $slug, array(ResponseKeyType::Error->value => $zipResult[ResponseKeyType::Error->value]));

            return ResultHelper::failed();
        }

        $entry = array(
            'slug'                        => $info['slug'],
            'name'                        => $info['name'],
            'version'                     => $info['version'],
            'zip'                         => $zipFilename,
            ResponseKeyType::Size->value  => filesize($zipPath),
        );

        if ($rootPdo) {
            $this->rootDb->registerPluginSnapshot($rootPdo, array(
                ResponseKeyType::PluginSlug->value    => $info['slug'],
                'pluginName'                          => $info['name'],
                ResponseKeyType::PluginVersion->value => $info['version'],
                'zipFile'                             => 'plugins/' . $zipFilename,
                'fileSizeBytes'                       => filesize($zipPath),
                'checksumMd5'                         => md5_file($zipPath),
            ));
        }

        $this->log(LogLevelType::Info->value, sprintf(
            'Plugin archived: %s (%s)',
            $info['name'],
            $this->formatBytes($entry[ResponseKeyType::Size->value]),
        ));

        return ResultHelper::ok(array(
            ResponseKeyType::Size->value  => $entry[ResponseKeyType::Size->value],
            ResponseKeyType::Entry->value => $entry,
        ));
    }

    private function createPluginZip(
        string $sourceDir,
        string $zipPath,
        string $slug,
    ): array {
        try {
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return ResultHelper::error('Failed to create ZIP');
            }

            $sourceDir = rtrim($sourceDir, '/\\');
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

                return ResultHelper::error('ZIP file is empty');
            }

            return ResultHelper::ok();
        } catch (Throwable $e) {
            return ResultHelper::errorFromException($e);
        }
    }

    private function openRootDbForPlugins(string $snapshotDir): ?PDO {
        $rootPath = $snapshotDir . PathDatabaseType::Root->value;

        if (PathHelper::isFileMissing($rootPath)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $rootPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $pdo;
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Could not open a-root.db for plugin registration', array(ResponseKeyType::Error->value => $e->getMessage()));

            return null;
        }
    }
}
