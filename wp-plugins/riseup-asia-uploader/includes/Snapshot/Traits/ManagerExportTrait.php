<?php
/**
 * ManagerExportTrait — Snapshot ZIP export operations.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Helpers\PathHelper;

trait ManagerExportTrait {

    public function exportSnapshot(int $snapshotId): array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null);

        if ($isProviderMissing) {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotProviderMissing->value);
        }

        $snapshot = $provider->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null || $snapshot === false);

        if ($isSnapshotMissing) {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotNotFound->value, ResponseKeyType::Code->value => SnapshotErrorType::NotFound->value);
        }

        $filepath = $snapshot['filepath'];
        if (PathHelper::isFileMissing($filepath)) {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::SnapshotFileMissing->value);
        }

        return $this->createSnapshotZip($snapshotId, $filepath, $snapshot);
    }

    private function createSnapshotZip(
        int $snapshotId,
        string $filepath,
        array $snapshot,
    ): array {
        $zipPath = preg_replace('/\.sqlite$/', '.zip', $filepath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->log(LogLevelType::Error->value, 'Failed to create ZIP file', array('path' => $zipPath));
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => ResponseMessageType::ZipCreateFailed->value);
        }

        $zip->addFile($filepath, basename($filepath));
        $manifest = $this->createExportManifest($snapshot);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        $size = filesize($zipPath);
        $this->log(LogLevelType::Info->value, 'Snapshot exported to ZIP', array(
            'snapshot_id' => $snapshotId, 'zip_path' => $zipPath, 'size' => PathHelper::formatBytes($size),
        ));

        return array(ResponseKeyType::Success->value => true, 'filepath' => $zipPath, 'filename' => basename($zipPath), 'size' => $size);
    }

    private function createExportManifest(array $snapshot): array {
        return array(
            'version' => PluginConfigType::Version->value,
            'format_version' => '1.0',
            'created_at' => date('c'),
            'exported_at' => date('c'),
            'snapshot' => array(
                'id' => $snapshot['id'],
                'sequence' => $snapshot['sequence'],
                'filename' => $snapshot['filename'],
                'scope' => $snapshot['scope'],
                'provider' => $snapshot['provider'],
                'tables' => json_decode($snapshot['tables_json'], true),
                'total_rows' => $snapshot['total_rows'],
                'file_size' => $snapshot['file_size'],
                'created_at' => $snapshot['created_at'],
            ),
            'source' => array(
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'site_url' => get_site_url(),
                'db_prefix' => $this->wpdb->prefix,
            ),
        );
    }
}
