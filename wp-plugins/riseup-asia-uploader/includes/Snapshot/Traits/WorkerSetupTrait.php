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
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;

trait WorkerSetupTrait {

    private function prepareSnapshotDir(array $config): array {
        $title = $config['title'] ?? (SnapshotConfigType::DefaultTitle . ' ' . date('Y-m-d H:i'));
        $scope = $config['scope'] ?? SnapshotScopeType::WordPress->value;
        $type  = $config['type'] ?? SnapshotModeType::Full->value;

        $hasPoolSize = BooleanHelpers::hasValue($config['settings']['worker_pool_size'] ?? null);
        if ($hasPoolSize) {
            $this->setPoolSize($config['settings']['worker_pool_size']);
        }

        $this->log(LogLevelType::Info->value, 'Starting per-table snapshot', array(
            'title' => $title, 'scope' => $scope, 'type' => $type, 'pool_size' => $this->poolSize,
        ));

        $base_dir = $this->getSnapshotsBaseDir();
        $dir_name = date('Y-m-d') . '_' . $type . '_' . sanitize_title($title);
        $snapshot_dir = $base_dir . '/' . $dir_name;

        $isDirCreateFailed = (PathHelper::makeDirectory($snapshot_dir, true) === false);
        if ($isDirCreateFailed) {
            return array('success' => false, 'error' => 'Failed to create snapshot directory');
        }

        return array('success' => true, 'snapshot_dir' => $snapshot_dir, 'dir_name' => $dir_name, 'title' => $title, 'scope' => $scope, 'type' => $type);
    }

    private function initRootDb(string $snapshotDir, array $config): PDO {
        $rootPdo = $this->rootDb->create($snapshotDir . '/' . SnapshotConfigType::RootDbFilename);
        $this->rootDb->populateMetadata($rootPdo, array(
            'title' => $config['title'] ?? SnapshotConfigType::DefaultTitle, 'type' => $config['type'] ?? SnapshotModeType::Full->value, 'settings' => $config['settings'] ?? null,
        ));

        return $rootPdo;
    }

    private function populateAndGetSeedOrder(PDO $rootPdo, array $config): array {
        $analysis = $this->rootDb->populateDependencies($rootPdo, $config['scope'] ?? SnapshotScopeType::WordPress->value);
        $this->log(LogLevelType::Info->value, 'Export order determined', array('tables' => count($analysis['seed_order']), 'pool_size' => $this->poolSize));

        return $analysis['seed_order'];
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
