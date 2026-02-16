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

    protected string $provider_id;
    protected string $provider_name;
    protected FileLogger $logger;
    protected Database $db;

    public function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    public function getProviderId(): string { return $this->provider_id; }
    public function getProviderName(): string { return $this->provider_name; }

    abstract public function isAvailable(): bool;
    abstract public function getCapabilities(): array;
    abstract public function createSnapshot(array $options): array;
    abstract public function restoreSnapshot(int $snapshotId, array $options): array;
    abstract public function deleteSnapshot(int $snapshotId): array;
    abstract public function exportSnapshot(int $snapshotId): array;
    abstract public function importSnapshot(string $filepath): array;
    abstract public function getSnapshot(int $snapshotId): ?array;
    abstract public function listSnapshots(int $limit = 50, int $offset = 0): array;
    abstract public function getAvailableTables(): array;
}

class_alias(SnapshotProviderInterface::class, 'RiseupSnapshotProviderInterface');
