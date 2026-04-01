<?php
/**
 * NativeSnapshotRecordTrait — snapshot SQLite creation and record management.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotStatusType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;

trait NativeSnapshotRecordTrait {
    use NativeSnapshotCrudTrait;

    private function createSqliteDatabase(string $filepath): ?PDO {
        $guardResult = $this->guardSqlitePath($filepath);

        if ($guardResult !== null) {
            return $guardResult;
        }

        return $this->initializeSqlitePdo($filepath);
    }

    private function guardSqlitePath(string $filepath): ?PDO {
        $snapshotsDir = $this->getSnapshotsDir();

        if (PathHelper::isPathMissing($filepath, $snapshotsDir)) {
            $this->log(LogLevelType::Error->value, 'Unsafe path detected for SQLite database', [
                'filepath' => $filepath,
                'base'     => $snapshotsDir,
            ]);

            return null;
        }

        $parentDir = dirname($filepath);
        $isDirCreationFailed = (PathHelper::makeDirectory($parentDir, true) === false);

        if ($isDirCreationFailed) {
            $this->log(LogLevelType::Error->value, 'Failed to create parent directory for SQLite', [
                'parent' => $parentDir,
            ]);

            return null;
        }

        // Return non-null PDO only from initializeSqlitePdo; null here means "proceed"
        // Using a sentinel pattern: null means guards passed
        return null;
    }

    private function initializeSqlitePdo(string $filepath): ?PDO {
        try {
            $this->log(LogLevelType::Debug->value, 'Creating SQLite database', ['filepath' => $filepath]);
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('CREATE TABLE IF NOT EXISTS _snapshot_meta (key TEXT PRIMARY KEY, value TEXT)');

            $this->insertSnapshotMeta($pdo);

            return $pdo;
        } catch (Throwable $e) {
            $this->logSqliteCreationError($filepath, $e);

            return null;
        }
    }

    private function logSqliteCreationError(string $filepath, Throwable $e): void {
        $this->log(LogLevelType::Error->value, 'Failed to create SQLite database', [
            'filepath' => $filepath,
            'error'    => $e->getMessage(),
        ]);
    }

    private function insertSnapshotMeta(PDO $pdo): void {
        $meta = [
            'created_at'     => DateHelper::nowIso(),
            'wp_version'     => get_bloginfo('version'),
            'site_url'       => get_site_url(),
            'php_version'    => PHP_VERSION,
            'provider'       => $this->providerId,
            'plugin_version' => PluginConfigType::Version->value,
        ];

        $stmt = $pdo->prepare('INSERT INTO _snapshot_meta (key, value) VALUES (?, ?)');

        foreach ($meta as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }

    private function createSnapshotRecord(
        int $sequence,
        string $filename,
        string $filepath,
        string $scope,
        array $tables,
        string $trigger,
    ): int|false {
        $result = $this->db->insert(TableType::Snapshots->value, [
            'Sequence'      => $sequence,
            'Filename'      => $filename . '.sqlite',
            'Filepath'      => $filepath,
            'Provider'      => $this->providerId,
            'Scope'         => $scope,
            'TablesJson'    => json_encode($tables),
            'TriggerSource' => $trigger,
            'Status'        => SnapshotStatusType::Pending->value,
            'CreatedAt'     => DateHelper::nowIso(),
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    private function updateSnapshotStatus(
        int $snapshotId,
        string $status,
        ?string $error = null,
    ): void {
        $data = $this->buildStatusUpdateData($status, $error);

        $this->db->update(
            TableType::Snapshots->value,
            $data,
            ['Id' => $snapshotId],
        );
    }

    private function buildStatusUpdateData(string $status, ?string $error): array {
        $data = [
            'Status'    => $status,
            'UpdatedAt' => DateHelper::nowIso(),
        ];

        if ($error) {
            $data['ErrorMessage'] = $error;
        }

        if ($status === SnapshotStatusType::Running->value) {
            $data['StartedAt'] = DateHelper::nowIso();
        }

        return $data;
    }

    private function finalizeSnapshot(int $snapshotId, array $details): void {
        $this->db->update(TableType::Snapshots->value, [
            'Status'          => $details[ResponseKeyType::Status->value],
            'FileSize'        => $details[ResponseKeyType::FileSize->value],
            'TotalRows'       => $details[ResponseKeyType::TotalRows->value],
            'TableCountsJson' => json_encode($details[ResponseKeyType::TableCounts->value]),
            'DurationMs'      => $details[ResponseKeyType::DurationMs->value],
            'CompletedAt'     => DateHelper::nowIso(),
            'UpdatedAt'       => DateHelper::nowIso(),
        ], ['Id' => $snapshotId]);
    }
}
