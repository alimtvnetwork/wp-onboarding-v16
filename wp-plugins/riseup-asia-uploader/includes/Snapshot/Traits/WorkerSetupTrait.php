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
use RiseupAsia\Helpers\PathHelper;

trait WorkerSetupTrait {

    private function prepareSnapshotDir(array $config): array {
        $title = $config['title'] ?? ('Snapshot ' . date('Y-m-d H:i'));
        $scope = $config['scope'] ?? 'wordpress';
        $type  = $config['type'] ?? 'full';

        if (!empty($config['settings']['worker_pool_size'])) {
            $this->setPoolSize($config['settings']['worker_pool_size']);
        }

        $this->log(LogLevelType::Info->value, 'Starting per-table snapshot', array(
            'title' => $title, 'scope' => $scope, 'type' => $type, 'pool_size' => $this->poolSize,
        ));

        $base_dir = $this->getSnapshotsBaseDir();
        $dir_name = date('Y-m-d') . '_' . $type . '_' . sanitize_title($title);
        $snapshot_dir = $base_dir . '/' . $dir_name;

        if (!PathHelper::ensureDir($snapshot_dir, true)) {
            return array('success' => false, 'error' => 'Failed to create snapshot directory');
        }

        return array('success' => true, 'snapshot_dir' => $snapshot_dir, 'dir_name' => $dir_name, 'title' => $title, 'scope' => $scope, 'type' => $type);
    }

    private function initRootDb(string $snapshotDir, array $config): PDO {
        $rootPdo = $this->rootDb->create($snapshotDir . '/a-root.db');
        $this->rootDb->populateMetadata($rootPdo, array(
            'title' => $config['title'] ?? 'Snapshot', 'type' => $config['type'] ?? 'full', 'settings' => $config['settings'] ?? null,
        ));
        return $rootPdo;
    }

    private function populateAndGetSeedOrder(PDO $rootPdo, array $config): array {
        $analysis = $this->rootDb->populateDependencies($rootPdo, $config['scope'] ?? 'wordpress');
        $this->log(LogLevelType::Info->value, 'Export order determined', array('tables' => count($analysis['seed_order']), 'pool_size' => $this->poolSize));
        return $analysis['seed_order'];
    }

    private function getSnapshotsBaseDir(): string {
        $base = PathHelper::getSnapshotsDir();
        PathHelper::ensureDir($base, true);
        return $base;
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $full = '[SNAPSHOT] [WORKER] ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if (!$this->logger) return;

        switch ($level) {
            case LogLevelType::Warn->value:  $this->logger->warn($full); break;
            case LogLevelType::Error->value: $this->logger->error($full); break;
            default:      $this->logger->info($full);
        }
    }
}
