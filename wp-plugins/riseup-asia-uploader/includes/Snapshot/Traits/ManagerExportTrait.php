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
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait ManagerExportTrait {
    public function exportSnapshot(int $snapshotId): array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null);

        if ($isProviderMissing) {
            return ResultHelper::error(ResponseMessageType::SnapshotProviderMissing->value);
        }

        $snapshot = $provider->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null || $snapshot === false);

        if ($isSnapshotMissing) {
            return ResultHelper::errorWithCode(
                ResponseMessageType::SnapshotNotFound->value,
                SnapshotErrorType::NotFound->value,
            );
        }

        $filepath = $snapshot['Filepath'];
        if (PathHelper::isFileMissing($filepath)) {
            return ResultHelper::error(ResponseMessageType::SnapshotFileMissing->value);
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
            $this->log(LogLevelType::Error->value, 'Failed to create ZIP file', array(ResponseKeyType::Path->value => $zipPath));

            return ResultHelper::error(ResponseMessageType::ZipCreateFailed->value);
        }

        $zip->addFile($filepath, basename($filepath));
        $manifest = $this->createExportManifest($snapshot);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        $size = filesize($zipPath);
        $this->log(LogLevelType::Info->value, 'Snapshot exported to ZIP', array(
            ResponseKeyType::SnapshotId->value => $snapshotId,
            'zip_path'                         => $zipPath,
            ResponseKeyType::Size->value       => PathHelper::formatBytes($size),
        ));

        return ResultHelper::ok(array(
            'filepath'                       => $zipPath,
            ResponseKeyType::Filename->value => basename($zipPath),
            ResponseKeyType::Size->value     => $size,
        ));
    }

    private function createExportManifest(array $snapshot): array {
        return array(
            'version' => PluginConfigType::Version->value,
            ResponseKeyType::FormatVersion->value => '1.0',
            ResponseKeyType::CreatedAt->value => DateHelper::nowIso(),
            ResponseKeyType::ExportedAt->value => DateHelper::nowIso(),
            'snapshot' => array(
                'id' => $snapshot['Id'],
                ResponseKeyType::Sequence->value => $snapshot['Sequence'],
                ResponseKeyType::Filename->value => $snapshot['Filename'],
                ResponseKeyType::Scope->value => $snapshot['Scope'],
                'provider' => $snapshot['Provider'],
                ResponseKeyType::Tables->value => json_decode($snapshot['TablesJson'], true),
                ResponseKeyType::TotalRows->value => $snapshot['TotalRows'],
                ResponseKeyType::FileSize->value => $snapshot['FileSize'],
                ResponseKeyType::CreatedAt->value => $snapshot['CreatedAt'],
            ),
            'source' => array(
                ResponseKeyType::WpVersion->value => get_bloginfo('version'),
                ResponseKeyType::PhpVersion->value => PHP_VERSION,
                ResponseKeyType::SiteUrl->value => get_site_url(),
                ResponseKeyType::DbPrefix->value => $this->wpdb->prefix,
            ),
        );
    }
}
