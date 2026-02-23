<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use RiseupAsia\Snapshot\Traits\SnapshotProviderHelpersTrait;
use RiseupAsia\Snapshot\Traits\SnapshotProviderLockTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

abstract class SnapshotProviderInterface {
    use SnapshotProviderHelpersTrait;
    use SnapshotProviderLockTrait;

    protected string $providerId;
    protected string $providerName;
    protected FileLogger $logger;
    protected Database $db;

    public function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    public function getProviderId(): string { return $this->providerId; }
    public function getProviderName(): string { return $this->providerName; }

    abstract public function isAvailable(): bool;
    abstract public function getCapabilities(): array;
    abstract public function createSnapshot(array $options): array;
    abstract public function restoreSnapshot(int $snapshotId, array $options): array;
    abstract public function deleteSnapshot(int $snapshotId): array;
    abstract public function exportSnapshot(int $snapshotId): array;
    abstract public function importSnapshot(string $filepath): array;
    abstract public function getSnapshot(int $snapshotId): ?array;
    /** @param int $limit PaginationConfigType::DefaultLimit (PHP constraint: no enum in defaults) */
    abstract public function listSnapshots(int $limit = 50, int $offset = 0): array;
    abstract public function getAvailableTables(): array;
}
