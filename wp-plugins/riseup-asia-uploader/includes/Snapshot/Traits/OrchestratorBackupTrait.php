<?php
/**
 * OrchestratorBackupTrait — full and incremental backup execution.
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
use RiseupAsia\Enums\PluginSelectionType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Snapshot\IncrementalBackup;

trait OrchestratorBackupTrait {
    public function executeFullBackup(array $options = array()): array {
        $resolved = $this->resolveBackupOptions($options);
        $this->log(LogLevelType::Info->value, 'Starting full backup orchestration', $resolved);

        return ($options['async'] ?? true)
            ? $this->executeAsyncBackup($resolved)
            : $this->executeSyncBackup($resolved);
    }

    private function resolveBackupOptions(array $options): array {
        $settings = $this->manager->getSettings();
        $this->worker->setPoolSize($settings['worker_pool_size'] ?? SnapshotConfigType::WorkerPoolDefault->value);

        return array(
            'title'            => $options['title'] ?? ('Full Backup ' . DateHelper::nowCompactDatetime()),
            ResponseKeyType::Scope->value => $options[ResponseKeyType::Scope->value] ?? $settings[ResponseKeyType::Scope->value] ?? SnapshotScopeType::WordPress->value,
            'include_plugins'  => $options['include_plugins'] ?? $settings['include_plugins'] ?? true,
            'plugin_selection' => $options['plugin_selection'] ?? $settings['plugin_selection'] ?? PluginSelectionType::All->value,
            'compression'      => $options['compression'] ?? $settings['compression'] ?? true,
            'settings'         => $settings,
        );
    }

    private function executeAsyncBackup(array $resolved): array {
        try {
            $workerResult = $this->runWorkerExport($resolved, true);
            $isExportFailed = BooleanHelpers::isResultFailed($workerResult);

            if ($isExportFailed) {
                return $this->buildPhaseError('table_export', $workerResult);
            }

            $this->log(LogLevelType::Info->value, 'Async backup job created', array(
                ResponseKeyType::JobId->value       => $workerResult[ResponseKeyType::JobId->value] ?? null,
                ResponseKeyType::TotalTables->value  => $workerResult[ResponseKeyType::TotalTables->value] ?? null,
                ResponseKeyType::PoolSize->value     => $workerResult[ResponseKeyType::PoolSize->value] ?? null,
                ResponseKeyType::Directory->value    => $workerResult[ResponseKeyType::Directory->value] ?? null,
            ));

            $snapshotId = $this->registerSnapshot(
                $resolved['title'],
                $resolved[ResponseKeyType::Scope->value],
                $workerResult,
                array(
                    ResponseKeyType::Count->value     => 0,
                    ResponseKeyType::TotalSize->value  => 0,
                ),
                $workerResult[ResponseKeyType::Path->value],
            );

            return array(
                ResponseKeyType::Success->value     => true,
                'async'                             => true,
                ResponseKeyType::JobId->value       => $workerResult[ResponseKeyType::JobId->value] ?? null,
                ResponseKeyType::SnapshotId->value  => $snapshotId,
                ResponseKeyType::Directory->value   => $workerResult[ResponseKeyType::Directory->value] ?? null,
                ResponseKeyType::Path->value        => $workerResult[ResponseKeyType::Path->value],
                ResponseKeyType::TotalTables->value => $workerResult[ResponseKeyType::TotalTables->value] ?? null,
                ResponseKeyType::PoolSize->value    => $workerResult[ResponseKeyType::PoolSize->value] ?? null,
                'status'                            => $workerResult['status'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->buildExceptionResult($e, 'async_orchestration');
        }
    }

    private function executeSyncBackup(array $resolved): array {
        $startTime = microtime(true);

        try {
            $workerResult = $this->runWorkerExport($resolved, false);
            $isExportFailed = BooleanHelpers::isResultFailed($workerResult);

            if ($isExportFailed) {
                return $this->buildPhaseError('table_export', $workerResult);
            }

            $context = $this->finalizeSyncExport($resolved, $workerResult);
            $context[ResponseKeyType::Duration->value] = microtime(true) - $startTime;
            $context[ResponseKeyType::Errors->value] = $workerResult[ResponseKeyType::Errors->value] ?? array();

            return $context;
        } catch (Throwable $e) {
            return $this->buildExceptionResult($e, 'sync_orchestration');
        }
    }

    /** Register snapshot, snapshot plugins, and create ZIP for sync backup. */
    private function finalizeSyncExport(array $resolved, array $workerResult): array {
        $snapshotDir = $workerResult[ResponseKeyType::Path->value];

        $pluginStats = $resolved['include_plugins']
            ? $this->snapshotPlugins($snapshotDir, $resolved['plugin_selection'])
            : array(
                ResponseKeyType::Count->value    => 0,
                'total_size'                     => 0,
            );

        $snapshotId = $this->registerSnapshot(
            $resolved['title'],
            $resolved[ResponseKeyType::Scope->value],
            $workerResult,
            $pluginStats,
            $snapshotDir,
        );

        $zipResult = $resolved['compression']
            ? $this->executeZipPhase($snapshotDir, $resolved)
            : array(
                ResponseKeyType::Path->value      => null,
                ResponseKeyType::Size->value      => 0,
                ResponseKeyType::ZipFailed->value => false,
            );

        return array(
            ResponseKeyType::Success->value    => true,
            ResponseKeyType::SnapshotId->value => $snapshotId,
            ResponseKeyType::Directory->value  => $workerResult[ResponseKeyType::Directory->value],
            ResponseKeyType::Path->value       => $workerResult[ResponseKeyType::Path->value],
            ResponseKeyType::Tables->value     => $workerResult[ResponseKeyType::Tables->value],
            ResponseKeyType::TotalRows->value  => $workerResult[ResponseKeyType::TotalRows->value],
            ResponseKeyType::Plugins->value    => $pluginStats[ResponseKeyType::Count->value],
            'zip_path'                         => $zipResult[ResponseKeyType::Path->value],
            ResponseKeyType::ZipSize->value    => $zipResult[ResponseKeyType::Size->value],
            ResponseKeyType::ZipFailed->value  => $zipResult[ResponseKeyType::ZipFailed->value] ?? false,
        );
    }

    private function runWorkerExport(array $resolved, bool $async): array {
        $config = array(
            'title'                       => $resolved['title'],
            ResponseKeyType::Scope->value => $resolved[ResponseKeyType::Scope->value],
            'type'                        => SnapshotModeType::Full->value,
            'settings'                    => $resolved['settings'],
        );

        return $async ? $this->worker->execute($config) : $this->worker->executeSynchronous($config);
    }

    private function executeZipPhase(string $snapshotDir, array $resolved): array {
        $zipResult = $this->createZipExport($snapshotDir, $resolved['title']);

        if ($zipResult[ResponseKeyType::Success->value]) {
            return array(
                ResponseKeyType::Path->value      => $zipResult[ResponseKeyType::Path->value],
                ResponseKeyType::Size->value      => $zipResult[ResponseKeyType::Size->value],
                ResponseKeyType::ZipFailed->value => false,
            );
        }

        $this->log(LogLevelType::Warn->value, 'ZIP export failed (non-fatal)', array(
            ResponseKeyType::Error->value => $zipResult[ResponseKeyType::Error->value],
        ));

        return array(
            ResponseKeyType::Path->value      => null,
            ResponseKeyType::Size->value      => 0,
            ResponseKeyType::ZipFailed->value => true,
        );
    }

    /**
     * Execute an incremental backup against the latest full snapshot.
     */
    public function executeIncrementalBackup(array $options = array()): array {
        $this->log(LogLevelType::Info->value, 'Starting incremental backup orchestration', $options);

        try {
            $incremental = IncrementalBackup::getInstance($this->logger, $this->db, $this->rootDb);
            $masterDir = $this->resolveMasterDir($options, $incremental);
            $isMasterDirMissing = ($masterDir === null);

            if ($isMasterDirMissing) {
                return array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'No full snapshot found. A full backup is required before creating an incremental.',
                    ResponseKeyType::Phase->value   => 'incremental_lookup',
                );
            }

            $result = $incremental->execute($masterDir, $options);
            $this->log(LogLevelType::Info->value, 'Incremental backup orchestration ' . ($result[ResponseKeyType::Success->value] ? 'complete' : 'failed'), array(
                'master'                              => basename($masterDir),
                ResponseKeyType::TablesChanged->value => $result[ResponseKeyType::TablesChanged->value] ?? 0,
                ResponseKeyType::TotalNewRows->value  => $result[ResponseKeyType::TotalNewRows->value] ?? 0,
            ));

            return $result;
        } catch (Throwable $e) {
            return $this->buildExceptionResult($e, 'incremental_orchestration');
        }
    }

    private function resolveMasterDir(array $options, object $incremental): ?string {
        $hasMasterSnapshotId = BooleanHelpers::hasValue($options['master_snapshot_id'] ?? null);

        if ($hasMasterSnapshotId) {
            $pdo = $this->db->getPdo();

            if ($pdo) {
                $stmt = $pdo->prepare("SELECT Filepath FROM " . TableType::Snapshots->value . " WHERE Id = ?");
                $stmt->execute(array($options['master_snapshot_id']));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && is_dir($row['Filepath'])) {
                    return $row['Filepath'];
                }
            }
        }

        return $incremental->findLatestMasterSnapshot();
    }
}
