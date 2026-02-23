<?php
/**
 * WorkerSetupTrait — snapshot directory preparation, root DB init, and helpers.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\ResultHelper;

trait WorkerSetupTrait {
    private function prepareSnapshotDir(array $config): array {
        $title = $config[ResponseKeyType::Title->value] ?? (SnapshotConfigType::DefaultTitle . ' ' . date('Y-m-d H:i'));
        $scope = $config[ResponseKeyType::Scope->value] ?? SnapshotScopeType::WordPress->value;
        $type  = $config[ResponseKeyType::Type->value] ?? SnapshotModeType::Full->value;

        $hasPoolSize = BooleanHelpers::hasValue($config[ResponseKeyType::Settings->value]['worker_pool_size'] ?? null);

        if ($hasPoolSize) {
            $this->setPoolSize($config[ResponseKeyType::Settings->value]['worker_pool_size']);
        }

        $this->log(LogLevelType::Info->value, 'Starting per-table snapshot', array(
            ResponseKeyType::Title->value         => $title,
            ResponseKeyType::Scope->value         => $scope,
            ResponseKeyType::Type->value          => $type,
            ResponseKeyType::PoolSize->value      => $this->poolSize,
        ));

        $base_dir = $this->getSnapshotsBaseDir();
        $dir_name = date('Y-m-d') . '_' . $type . '_' . sanitize_title($title);
        $snapshot_dir = $base_dir . '/' . $dir_name;

        $isDirCreateFailed = (PathHelper::makeDirectory($snapshot_dir, true) === false);

        if ($isDirCreateFailed) {
            return ResultHelper::error('Failed to create snapshot directory');
        }

        return ResultHelper::ok(array(
            ResponseKeyType::SnapshotDir->value => $snapshot_dir,
            ResponseKeyType::DirName->value     => $dir_name,
            ResponseKeyType::Title->value       => $title,
            ResponseKeyType::Scope->value       => $scope,
            ResponseKeyType::Type->value        => $type,
        ));
    }

    private function initRootDb(string $snapshotDir, array $config): PDO {
        $rootPdo = $this->rootDb->create($snapshotDir . '/' . SnapshotConfigType::RootDbFilename);
        $this->rootDb->populateMetadata($rootPdo, array(
            ResponseKeyType::Title->value    => $config[ResponseKeyType::Title->value] ?? SnapshotConfigType::DefaultTitle,
            ResponseKeyType::Type->value     => $config[ResponseKeyType::Type->value] ?? SnapshotModeType::Full->value,
            ResponseKeyType::Settings->value => $config[ResponseKeyType::Settings->value] ?? null,
        ));

        return $rootPdo;
    }

    private function populateAndGetSeedOrder(PDO $rootPdo, array $config): array {
        $analysis = $this->rootDb->populateDependencies($rootPdo, $config[ResponseKeyType::Scope->value] ?? SnapshotScopeType::WordPress->value);
        $this->log(LogLevelType::Info->value, 'Export order determined', array(
            ResponseKeyType::Tables->value => count($analysis[ResponseKeyType::SeedOrder->value]),
            ResponseKeyType::PoolSize->value => $this->poolSize,
        ));

        return $analysis[ResponseKeyType::SeedOrder->value];
    }

    private function getSnapshotsBaseDir(): string {
        $base = PathHelper::getSnapshotsDir();
        PathHelper::makeDirectory($base, true);

        return $base;
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $full = '[SNAPSHOT] [WORKER] ' . $message;
        $hasContext = BooleanHelpers::hasValue($context);

        if ($hasContext) {
            $full .= ' ' . json_encode($context);
        }

        $isLoggerMissing = ($this->logger === null);

        if ($isLoggerMissing) {
            return;
        }

        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($full); break;
            case LogLevelType::Error->value: $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
