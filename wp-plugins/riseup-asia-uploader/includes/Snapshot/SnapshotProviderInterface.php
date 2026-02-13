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

    /** @var string */
    protected $provider_id;

    /** @var string */
    protected $provider_name;

    /** @var RiseupFileLogger */
    protected $logger;

    /** @var RiseupDatabase */
    protected $db;

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     */
    public function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }

    /** @return string Provider ID. */
    public function getProviderId() {
        return $this->provider_id;
    }

    /** @return string Provider display name. */
    public function getProviderName() {
        return $this->provider_name;
    }

    /** @return bool True if the provider can be used. */
    abstract public function isAvailable();

    /**
     * Get provider capabilities.
     *
     * @return array Capabilities: full_site, database_only, selective, scheduled, restore, export, import.
     */
    abstract public function getCapabilities();

    /**
     * Create a snapshot.
     *
     * @param array $options Snapshot options (scope, tables, trigger).
     * @return array Snapshot result.
     */
    abstract public function createSnapshot($options);

    /**
     * Restore from a snapshot.
     *
     * @param int   $snapshot_id Snapshot database ID.
     * @param array $options     Restore options (mode, tables, create_backup, confirm).
     * @return array Restore result.
     */
    abstract public function restoreSnapshot($snapshot_id, $options);

    /**
     * Delete a snapshot.
     *
     * @param int $snapshot_id Snapshot database ID.
     * @return array Delete result.
     */
    abstract public function deleteSnapshot($snapshot_id);

    /**
     * Export a snapshot to a downloadable file.
     *
     * @param int $snapshot_id Snapshot database ID.
     * @return array Export result.
     */
    abstract public function exportSnapshot($snapshot_id);

    /**
     * Import a snapshot from an uploaded file.
     *
     * @param string $filepath Path to the uploaded file.
     * @return array Import result.
     */
    abstract public function importSnapshot($filepath);

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot database ID.
     * @return array|null Snapshot details or null if not found.
     */
    abstract public function getSnapshot($snapshot_id);

    /**
     * List all snapshots.
     *
     * @param int $limit  Maximum number to return.
     * @param int $offset Offset for pagination.
     * @return array List result with snapshots and total.
     */
    abstract public function listSnapshots($limit = 50, $offset = 0);

    /**
     * Get list of available database tables.
     *
     * @return array Table info: name, rows, size, is_core.
     */
    abstract public function getAvailableTables();
}
