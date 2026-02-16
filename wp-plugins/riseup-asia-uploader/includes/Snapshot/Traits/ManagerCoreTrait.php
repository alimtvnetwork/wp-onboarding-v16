<?php
/**
 * ManagerCoreTrait — snapshot CRUD, provider delegation, and logging.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\TableType;

trait ManagerCoreTrait {

    public function getProvider(): ?\RiseupSnapshotProviderInterface {
        $providerId = $this->detector->getActiveProvider();
        return $this->detector->getProviderInstance($providerId, $this->logger, $this->db);
    }

    public function createSnapshot(array $options = array()): array {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No snapshot provider available', 'code' => SnapshotErrorType::ProviderNotAvail->value);
        }

        $this->log(LogLevelType::Info->value, 'Creating snapshot', array(
            'provider' => $provider->getProviderId(),
            'scope' => $options['scope'] ?? 'default',
        ));

        return $provider->createSnapshot($options);
    }

    public function deleteSnapshot(int $snapshotId): array {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No provider available');
        }

        return $provider->deleteSnapshot($snapshotId);
    }

    public function getSnapshot(int $snapshotId): ?array {
        $provider = $this->getProvider();
        if (!$provider) {
            return null;
        }

        return $provider->getSnapshot($snapshotId);
    }

    public function getSnapshotById(int $snapshotId): ?array {
        return $this->getSnapshot($snapshotId);
    }

    public function listSnapshots(int $limit = 50, int $offset = 0): array {
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        );

        $total = $this->db->querySingle('SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value);

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    public function getProviders(): array {
        return $this->detector->getAvailableProviders();
    }

    public function getAvailableTables(): array {
        $provider = $this->getProvider();
        if (!$provider) {
            return array();
        }

        return $provider->getAvailableTables();
    }

    private function log(string $level, string $message, array $context = array()): void {
        $full = '[SNAPSHOT] [MANAGER] ' . $message;
        if (!empty($context)) {
            $full .= ' ' . json_encode($context);
        }

        if (!$this->logger) {
            return;
        }

        $levelEnum = LogLevelType::from($level);
        $method = strtolower($levelEnum->value);
        $this->logger->$method($full);
    }
}
