<?php
/**
 * Riseup Asia Uploader - Snapshot Manager
 *
 * Central manager for database snapshot operations including
 * import, export, and restore functionality with ZIP handling.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevel;

// Load trait files
require_once __DIR__ . '/Traits/ManagerRestoreTrait.php';
require_once __DIR__ . '/Traits/ManagerTableRestoreTrait.php';
require_once __DIR__ . '/Traits/ManagerExportTrait.php';
require_once __DIR__ . '/Traits/ManagerImportTrait.php';
require_once __DIR__ . '/Traits/ManagerSettingsTrait.php';

/**
 * Snapshot Manager class.
 *
 * Coordinates snapshot operations across providers and handles
 * file-based operations (ZIP import/export, manifest validation).
 */
class RiseupSnapshotManager {

    use ManagerRestoreTrait;
    use ManagerTableRestoreTrait;
    use ManagerExportTrait;
    use ManagerImportTrait;
    use ManagerSettingsTrait;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotDetector */
    private $detector;

    /** @var wpdb */
    private $wpdb;

    /** @var RiseupSnapshotManager|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     * @return RiseupSnapshotManager
     */
    public static function getInstance($logger = null, $db = null) {
        if (self::$instance === null && $logger && $db) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     */
    private function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
        require_once dirname(__FILE__) . '/SnapshotFactory.php';
        $this->detector = RiseupSnapshotFactory::detector($logger, $db);

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the active snapshot provider.
     *
     * @return RiseupSnapshotProviderInterface|null
     */
    public function getProvider() {
        $provider_id = $this->detector->getActiveProvider();
        return $this->detector->getProviderInstance($provider_id, $this->logger, $this->db);
    }

    /**
     * Create a new snapshot.
     *
     * @param array $options Snapshot options.
     * @return array Result with success status.
     */
    public function createSnapshot($options = array()) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No snapshot provider available', 'code' => ERR_PROVIDER_NOT_AVAILABLE);
        }

        $this->log(LOG_LEVEL_INFO, 'Creating snapshot', array(
            'provider' => $provider->getProviderId(),
            'scope' => isset($options['scope']) ? $options['scope'] : 'default',
        ));

        return $provider->createSnapshot($options);
    }

    /**
     * Delete a snapshot.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Result.
     */
    public function deleteSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return array('success' => false, 'error' => 'No provider available');
        }

        return $provider->deleteSnapshot($snapshot_id);
    }

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null Snapshot or null.
     */
    public function getSnapshot($snapshot_id) {
        $provider = $this->getProvider();
        if (!$provider) {
            return null;
        }

        return $provider->getSnapshot($snapshot_id);
    }

    /**
     * List all snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array List result.
     */
    public function listSnapshots($limit = 50, $offset = 0) {
        $snapshots = $this->db->query_all(
            'SELECT * FROM ' . TABLE_SNAPSHOTS . ' ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($limit, $offset)
        );

        $total = $this->db->query_single('SELECT COUNT(*) as count FROM ' . TABLE_SNAPSHOTS);

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    /**
     * Get available providers and their status.
     *
     * @return array Providers list.
     */
    public function getProviders() {
        return $this->detector->getAvailableProviders();
    }

    /**
     * Get available database tables.
     *
     * @return array Tables list.
     */
    public function getAvailableTables() {
        $provider = $this->getProvider();
        if (!$provider) {
            return array();
        }

        return $provider->getAvailableTables();
    }

    /**
     * Log a message with manager context.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     */
    private function log($level, $message, $context = array()) {
        $prefix = '[SNAPSHOT] [MANAGER]';
        $full_message = $prefix . ' ' . $message;

        if (!empty($context)) {
            $full_message .= ' ' . json_encode($context);
        }

        if (!$this->logger) {
            return;
        }

        switch ($level) {
            case LogLevel::Debug->value:
                $this->logger->debug($full_message);
                break;
            case LogLevel::Info->value:
                $this->logger->info($full_message);
                break;
            case LogLevel::Warn->value:
                $this->logger->warn($full_message);
                break;
            case LogLevel::Error->value:
                $this->logger->error($full_message);
                break;
            default:
                $this->logger->info($full_message);
        }
    }
}
