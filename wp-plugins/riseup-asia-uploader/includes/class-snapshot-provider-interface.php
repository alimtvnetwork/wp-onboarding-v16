<?php
/**
 * Riseup Asia Uploader - Snapshot Provider Interface
 *
 * Defines the contract that all snapshot providers must implement.
 * Providers can be WP Reset, Updraft Plus, or the native SQLite engine.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for snapshot providers.
 * 
 * All snapshot providers must extend this class and implement
 * the abstract methods for creating, restoring, and managing snapshots.
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
abstract class RiseupSnapshotProviderInterface {

    /**
     * Provider identifier constant.
     *
     * @var string
     */
    protected $provider_id;

    /**
     * Provider display name.
     *
     * @var string
     */
    protected $provider_name;

    /**
     * Logger instance.
     *
     * @var RiseupFileLogger
     */
    protected $logger;

    /**
     * Database instance.
     *
     * @var RiseupDatabase
     */
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

    /**
     * Get the provider identifier.
     *
     * @return string Provider ID (e.g., 'native', 'wp_reset', 'updraft').
     */
    public function getProviderId() {
        return $this->provider_id;
    }

    /**
     * Get the provider display name.
     *
     * @return string Human-readable provider name.
     */
    public function getProviderName() {
        return $this->provider_name;
    }

    /**
     * Check if this provider is available for use.
     *
     * @return bool True if the provider can be used.
     */
    abstract public function isAvailable();

    /**
     * Get provider capabilities.
     *
     * @return array {
     *     Provider capabilities.
     *     @type bool $full_site      Supports full site backup (files + DB).
     *     @type bool $database_only  Supports database-only backup.
     *     @type bool $selective      Supports selective table backup.
     *     @type bool $scheduled      Supports scheduled backups.
     *     @type bool $restore        Supports restore from backup.
     *     @type bool $export         Supports export to file.
     *     @type bool $import         Supports import from file.
     * }
     */
    abstract public function getCapabilities();

    /**
     * Create a snapshot.
     *
     * @param array $options {
     *     Snapshot options.
     *     @type string   $scope   Scope: 'all', 'wordpress', 'content', 'custom'.
     *     @type string[] $tables  Tables to include (for 'custom' scope).
     *     @type string   $trigger Trigger source: 'api', 'cron', 'manual'.
     * }
     * @return array {
     *     Snapshot result.
     *     @type bool   $success     Whether snapshot was created.
     *     @type int    $snapshot_id Database ID of the snapshot record.
     *     @type string $filename    Snapshot filename.
     *     @type string $filepath    Full path to snapshot file.
     *     @type int    $size        File size in bytes.
     *     @type int    $tables      Number of tables included.
     *     @type int    $rows        Total rows exported.
     *     @type float  $duration    Duration in seconds.
     *     @type string $error       Error message if failed.
     * }
     */
    abstract public function createSnapshot($options);

    /**
     * Restore from a snapshot.
     *
     * @param int   $snapshot_id Snapshot database ID.
     * @param array $options {
     *     Restore options.
     *     @type string   $mode           Restore mode: 'full' or 'selective'.
     *     @type string[] $tables         Tables to restore (for 'selective' mode).
     *     @type bool     $create_backup  Create pre-restore backup (default true).
     *     @type bool     $confirm        Confirmation flag (required for safety).
     * }
     * @return array {
     *     Restore result.
     *     @type bool   $success       Whether restore was successful.
     *     @type int    $tables        Number of tables restored.
     *     @type int    $rows          Total rows restored.
     *     @type float  $duration      Duration in seconds.
     *     @type int    $backup_id     Pre-restore backup ID (if created).
     *     @type string $error         Error message if failed.
     * }
     */
    abstract public function restoreSnapshot($snapshot_id, $options);

    /**
     * Delete a snapshot.
     *
     * @param int $snapshot_id Snapshot database ID.
     * @return array {
     *     Delete result.
     *     @type bool   $success Whether deletion was successful.
     *     @type string $error   Error message if failed.
     * }
     */
    abstract public function deleteSnapshot($snapshot_id);

    /**
     * Export a snapshot to a downloadable file.
     *
     * @param int $snapshot_id Snapshot database ID.
     * @return array {
     *     Export result.
     *     @type bool   $success  Whether export was created.
     *     @type string $filepath Path to the export file (ZIP).
     *     @type string $filename Suggested download filename.
     *     @type int    $size     File size in bytes.
     *     @type string $error    Error message if failed.
     * }
     */
    abstract public function exportSnapshot($snapshot_id);

    /**
     * Import a snapshot from an uploaded file.
     *
     * @param string $filepath Path to the uploaded file.
     * @return array {
     *     Import result.
     *     @type bool   $success     Whether import was successful.
     *     @type int    $snapshot_id Database ID of the imported snapshot.
     *     @type string $filename    Imported snapshot filename.
     *     @type string $error       Error message if failed.
     * }
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
     * @param int $limit  Maximum number of snapshots to return.
     * @param int $offset Offset for pagination.
     * @return array {
     *     List result.
     *     @type array[] $snapshots Array of snapshot records.
     *     @type int     $total     Total number of snapshots.
     * }
     */
    abstract public function listSnapshots($limit = 50, $offset = 0);

    /**
     * Get list of available database tables.
     *
     * @return array {
     *     @type string $name     Table name.
     *     @type int    $rows     Approximate row count.
     *     @type int    $size     Approximate size in bytes.
     *     @type bool   $is_core  Whether this is a WordPress core table.
     * }[]
     */
    abstract public function getAvailableTables();

    /**
     * Log a message with snapshot context.
     *
     * @param string $level   Log level (DEBUG, INFO, WARN, ERROR).
     * @param string $message Log message.
     * @param array  $context Additional context data.
     */
    protected function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [' . strtoupper($this->provider_id) . ']';
        $full_message = $prefix . ' ' . $message;

        if (!empty($context)) {
            $full_message .= ' ' . json_encode($context);
        }

        if ($this->logger) {
            switch ($level) {
                case RISEUP_LOG_LEVEL_DEBUG:
                    $this->logger->debug($full_message);
                    break;
                case RISEUP_LOG_LEVEL_INFO:
                    $this->logger->info($full_message);
                    break;
                case RISEUP_LOG_LEVEL_WARN:
                    $this->logger->warn($full_message);
                    break;
                case RISEUP_LOG_LEVEL_ERROR:
                    $this->logger->error($full_message);
                    break;
                default:
                    $this->logger->info($full_message);
            }
        }
    }

    /**
     * Get the snapshots directory path.
     *
     * Uses RiseupPathUtils for consistent path handling.
     *
     * @return string Full path to snapshots directory.
     */
    protected function getSnapshotsDir() {
        return RiseupPathUtils::join(
            RiseupPathUtils::getBaseDir(),
            RISEUP_SNAPSHOTS_SUBDIR
        );
    }

    /**
     * Ensure snapshots directory exists with proper security.
     *
     * Uses RiseupPathUtils for directory creation and security.
     *
     * @return bool True if directory exists or was created.
     */
    protected function ensureSnapshotsDir() {
        $dir = RiseupPathUtils::ensurePath(
            true, // secure with .htaccess
            RiseupPathUtils::getBaseDir(),
            RISEUP_SNAPSHOTS_SUBDIR
        );

        if ($dir === false) {
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Failed to ensure snapshots directory');
            return false;
        }

        $this->log(RISEUP_LOG_LEVEL_DEBUG, 'Snapshots directory ensured', array('path' => $dir));
        return true;
    }

    /**
     * Generate a unique snapshot filename.
     *
     * @param int $sequence Sequence number.
     * @return string Filename without extension.
     */
    protected function generateSnapshotFilename($sequence) {
        $sequence_padded = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        $timestamp = date('Y-m-d_His');
        return sprintf('%s_%s', $sequence_padded, $timestamp);
    }

    /**
     * Get the next sequence number for snapshots.
     *
     * @return int Next sequence number.
     */
    protected function getNextSequence() {
        $result = $this->db->query_single(
            'SELECT MAX(sequence) as max_seq FROM ' . RISEUP_TABLE_SNAPSHOTS
        );
        return ($result && isset($result['max_seq'])) ? (int)$result['max_seq'] + 1 : 1;
    }

    /**
     * Check if a snapshot operation is currently in progress.
     *
     * @return bool True if locked.
     */
    protected function isLocked() {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');
        
        if (!RiseupPathUtils::fileExists($lock_file)) {
            return false;
        }

        // Check if lock is stale (older than 30 minutes)
        $lock_time = filemtime($lock_file);
        if (time() - $lock_time > 1800) {
            RiseupPathUtils::deleteFile($lock_file);
            $this->log(RISEUP_LOG_LEVEL_WARN, 'Removed stale lock file', array('age_minutes' => round((time() - $lock_time) / 60)));
            return false;
        }

        return true;
    }

    /**
     * Acquire a lock for snapshot operations.
     *
     * @return bool True if lock acquired.
     */
    protected function acquireLock() {
        if ($this->isLocked()) {
            return false;
        }

        // Ensure directory exists first
        if (!$this->ensureSnapshotsDir()) {
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Cannot acquire lock - directory creation failed');
            return false;
        }

        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');
        $lock_data = json_encode(array(
            'locked_at' => date('c'),
            'locked_by' => $this->provider_id,
            'pid' => getmypid()
        ));

        $result = @file_put_contents($lock_file, $lock_data);

        if ($result === false) {
            $error = error_get_last();
            $this->log(RISEUP_LOG_LEVEL_ERROR, 'Failed to acquire lock', array(
                'path' => $lock_file,
                'error' => $error ? $error['message'] : 'Unknown error',
            ));
            return false;
        }

        $this->log(RISEUP_LOG_LEVEL_DEBUG, 'Lock acquired', array('path' => $lock_file));
        return true;
    }

    /**
     * Release the snapshot lock.
     */
    protected function releaseLock() {
        $lock_file = RiseupPathUtils::join($this->getSnapshotsDir(), '.lock');
        
        if (RiseupPathUtils::fileExists($lock_file)) {
            RiseupPathUtils::deleteFile($lock_file);
            $this->log(RISEUP_LOG_LEVEL_DEBUG, 'Lock released');
        }
    }

    /**
     * Format bytes to human-readable string.
     *
     * Delegates to RiseupPathUtils for consistency.
     *
     * @param int $bytes    Bytes value.
     * @param int $decimals Number of decimal places.
     * @return string Formatted string (e.g., "15.7 MB").
     */
    protected function formatBytes($bytes, $decimals = 1) {
        return RiseupPathUtils::formatBytes($bytes, $decimals);
    }
}
