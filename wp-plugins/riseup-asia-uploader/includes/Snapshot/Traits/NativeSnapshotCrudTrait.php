<?php
/**
 * NativeSnapshotCrudTrait — Snapshot delete, export, import, list, and get operations.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
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
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;
use RiseupAsia\Snapshot\SnapshotManager;

trait NativeSnapshotCrudTrait {
    public function deleteSnapshot(int $snapshotId): array {
        $snapshot = $this->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null);

        if ($isSnapshotMissing) {
            return ResultHelper::error(ResponseMessageType::SnapshotNotFound->value);
        }

        $filepath = $snapshot['Filepath'];

        if (PathHelper::fileExists($filepath)) {
            $isDeleteFailed = (PathHelper::deleteFile($filepath) === false);
            if ($isDeleteFailed) {
                $this->log(LogLevelType::Error->value, 'Failed to delete snapshot file', ['filepath' => $filepath]);

                return ResultHelper::error('Failed to delete snapshot file');
            }
        }

        $zipPath = str_replace('.sqlite', '.zip', $filepath);

        if (PathHelper::fileExists($zipPath)) {
            PathHelper::deleteFile($zipPath);
        }

        $this->db->delete(
            TableType::Snapshots->value,
            ['Id' => $snapshotId],
        );
        $this->log(LogLevelType::Info->value, 'Snapshot deleted', [ResponseKeyType::SnapshotId->value => $snapshotId, ResponseKeyType::Filename->value => $snapshot['Filename']]);

        return ResultHelper::ok();
    }

    public function exportSnapshot(int $snapshotId): array {
        $snapshot = $this->getSnapshot($snapshotId);
        $isSnapshotMissing = ($snapshot === null);

        if ($isSnapshotMissing) {
            return ResultHelper::error(ResponseMessageType::SnapshotNotFound->value);
        }

        $filepath = $snapshot['Filepath'];

        if (PathHelper::isFileMissing($filepath)) {
            return ResultHelper::error(ResponseMessageType::SnapshotFileMissing->value);
        }

        return $this->createExportZip($snapshotId, $filepath, $snapshot);
    }

    private function createExportZip(
        int $snapshotId,
        string $filepath,
        array $snapshot,
    ): array {
        $zipPath = str_replace('.sqlite', '.zip', $filepath);

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ResultHelper::error(ResponseMessageType::ZipCreateFailed->value);
        }

        $zip->addFile($filepath, basename($filepath));
        $zip->addFromString(
            'manifest.json',
            json_encode($this->buildExportManifest($snapshotId, $snapshot), JSON_PRETTY_PRINT),
        );
        $zip->close();

        return ResultHelper::ok([
            ResponseKeyType::FilePath->value  => $zipPath,
            ResponseKeyType::Filename->value => basename($zipPath),
            ResponseKeyType::Size->value     => filesize($zipPath),
        ]);
    }

    private function buildExportManifest(int $snapshotId, array $snapshot): array {
        return [
            ResponseKeyType::Version->value     => PluginConfigType::Version->value,
            ResponseKeyType::CreatedAt->value   => DateHelper::nowIso(),
            ResponseKeyType::SnapshotId->value  => $snapshotId,
            ResponseKeyType::Filename->value    => $snapshot['Filename'],
            ResponseKeyType::Scope->value       => $snapshot['Scope'],
            ResponseKeyType::Tables->value      => json_decode($snapshot['TablesJson'], true),
            ResponseKeyType::TotalRows->value   => $snapshot['TotalRows'],
            ResponseKeyType::FileSize->value    => $snapshot['FileSize'],
        ];
    }

    public function importSnapshot(string $filepath): array {
        $manager = SnapshotManager::getInstance($this->logger, $this->db);

        return $manager->importSnapshot($filepath);
    }

    public function restoreSnapshot(int $snapshotId, array $options): array {
        $manager = SnapshotManager::getInstance($this->logger, $this->db);

        return $manager->restoreSnapshot($snapshotId, $options);
    }

    public function getSnapshot(int $snapshotId): ?array {
        return $this->db->querySingle('SELECT * FROM ' . TableType::Snapshots->value . ' WHERE Id = ?', [$snapshotId]);
    }

    public function listSnapshots(int $limit = 50, int $offset = 0): array { // PaginationConfigType::DefaultLimit
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE Provider = ? ORDER BY CreatedAt DESC LIMIT ? OFFSET ?',
            [
                $this->providerId,
                $limit,
                $offset,
            ]
        );
        $total = $this->db->querySingle(
            'SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value . ' WHERE Provider = ?',
            [$this->providerId],
        );

        return [
            ResponseKeyType::Snapshots->value => $snapshots ?: [],
            ResponseKeyType::Total->value     => $total ? (int)$total[ResponseKeyType::Count->value] : 0,
        ];
    }

    public function getAvailableTables(): array {
        $tables = [];
        $allTables = $this->wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        foreach ($allTables as $tableInfo) {
            $isCoreTable = (strpos($tableInfo['Name'], $this->wpdb->prefix) === 0);

            $tables[] = [
                ResponseKeyType::Name->value     => $tableInfo['Name'],
                ResponseKeyType::Rows->value     => (int)$tableInfo['Rows'],
                ResponseKeyType::Size->value     => (int)$tableInfo['Data_length'] + (int)$tableInfo['Index_length'],
                ResponseKeyType::IsCore->value   => $isCoreTable,
            ];
        }

        return $tables;
    }
}
