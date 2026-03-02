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
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Helpers\ResultHelper;

trait ManagerCoreTrait {
    public function getProvider(): ?\RiseupSnapshotProviderInterface {
        $providerId = $this->detector->getActiveProvider();

        return $this->detector->getProviderInstance($providerId, $this->logger, $this->db);
    }

    public function createSnapshot(array $options = array()): array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null);

        if ($isProviderMissing) {
            return ResultHelper::errorWithCode(
                ResponseMessageType::SnapshotProviderMissing->value,
                SnapshotErrorType::ProviderNotAvail->value,
            );
        }

        $this->log(LogLevelType::Info->value, 'Creating snapshot', array(
            'provider' => $provider->getProviderId(),
            ResponseKeyType::Scope->value => $options[ResponseKeyType::Scope->value] ?? 'default',
        ));

        return $provider->createSnapshot($options);
    }

    public function deleteSnapshot(int $snapshotId): array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null);

        if ($isProviderMissing) {
            return ResultHelper::error(ResponseMessageType::ProviderMissing->value);
        }

        return $provider->deleteSnapshot($snapshotId);
    }

    public function getSnapshot(int $snapshotId): ?array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null);

        if ($isProviderMissing) {
            return null;
        }

        return $provider->getSnapshot($snapshotId);
    }

    public function getSnapshotById(int $snapshotId): ?array {
        return $this->getSnapshot($snapshotId);
    }

    public function listSnapshots(int $limit = 50, int $offset = 0): array {
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' ORDER BY CreatedAt DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        );

        $total = $this->db->querySingle('SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value);

        return array(
            ResponseKeyType::Snapshots->value => $snapshots ?: array(),
            ResponseKeyType::Total->value     => $total ? (int)$total[ResponseKeyType::Count->value] : 0,
        );
    }

    public function getProviders(): array {
        return $this->detector->getAvailableProviders();
    }

    public function getAvailableTables(): array {
        $provider = $this->getProvider();
        $isProviderMissing = ($provider === null);

        if ($isProviderMissing) {
            return array();
        }

        return $provider->getAvailableTables();
    }

    private function log(
        string $level,
        string $message,
        array $context = array(),
    ): void {
        $full = '[SNAPSHOT] [MANAGER] ' . $message;
        $hasContext = !empty($context);

        if ($hasContext) {
            $full .= ' ' . json_encode($context);
        }

        $isLoggerMissing = ($this->logger === null);

        if ($isLoggerMissing) {
            return;
        }

        $levelEnum = LogLevelType::from($level);
        $method = strtolower($levelEnum->value);
        $this->logger->$method($full);
    }
}
