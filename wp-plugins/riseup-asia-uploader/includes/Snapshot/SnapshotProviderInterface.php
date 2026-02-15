<?php
/**
 * Riseup Asia Uploader - Snapshot Provider Interface
 *
 * Defines the contract that all snapshot providers must implement.
 * Providers can be WP Reset, Updraft Plus, or the native SQLite engine.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/Traits/SnapshotProviderHelpersTrait.php';
require_once dirname(__FILE__) . '/Traits/SnapshotProviderLockTrait.php';

/**
 * Abstract base class for snapshot providers.
 */
abstract class RiseupSnapshotProviderInterface {

    use SnapshotProviderHelpersTrait;
    use SnapshotProviderLockTrait;

    protected string $provider_id;
    protected string $provider_name;
    protected RiseupFileLogger $logger;
    protected RiseupDatabase $db;

    public function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    public function getProviderId(): string {
        return $this->provider_id;
    }

    public function getProviderName(): string {
        return $this->provider_name;
    }

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
