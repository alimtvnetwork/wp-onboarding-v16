<?php
/**
 * Riseup Asia Uploader - WP Reset Snapshot Provider
 *
 * Integrates with WP Reset plugin for full site snapshots.
 *
 * PHP class naming follows PascalCase convention without underscores.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/SnapshotProviderInterface.php';

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\TableType;

/**
 * WP Reset Snapshot Provider.
 * 
 * Leverages WP Reset's snapshot functionality for full site backups.
 * Only available when WP Reset plugin is installed and active.
 *
 * PHP class naming follows PascalCase convention without underscores.
 */
class RiseupSnapshotProviderWPReset extends RiseupSnapshotProviderInterface {

    /**
     * Provider ID.
     *
     * @var string
     */
    protected string $provider_id = SnapshotProviderType::WpReset->value;

    /**
     * Provider name.
     *
     * @var string
     */
    protected string $provider_name = 'WP Reset';

    private mixed $wp_reset = null;

    public function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        parent::__construct($logger, $db);
        
        // Get WP Reset instance if available
        if (class_exists('WP_Reset')) {
            global $wp_reset;
            $this->wp_reset = $wp_reset;
        }
    }

    /**
     * Check if provider is available.
     *
     * @return bool True if WP Reset is installed and active.
     */
    public function isAvailable(): bool {
        return class_exists('WP_Reset') || class_exists('WP_Reset_Pro');
    }

    /**
     * Get provider capabilities.
     *
     * @return array Capabilities array.
     */
    public function getCapabilities(): array {
        $is_pro = class_exists('WP_Reset_Pro');
        
        return array(
            'full_site' => true,
            'database_only' => true,
            'selective' => true,
            'scheduled' => $is_pro, // Only Pro has scheduling
            'restore' => true,
            'export' => true,
            'import' => true,
        );
    }

    /**
     * Create a snapshot using WP Reset.
     *
     * @param array $options Snapshot options.
     * @return array Snapshot result.
     */
    public function createSnapshot(array $options): array {
        if (!$this->isAvailable()) {
            return array(
                'success' => false,
                'error' => 'WP Reset is not available',
                'code' => SnapshotErrorType::ProviderNotAvail->value,
            );
        }

        $this->log(LogLevelType::Info->value, 'Creating snapshot via WP Reset', $options);

        try {
            // TODO: Implement WP Reset integration
            // This requires calling WP Reset's internal snapshot methods
            // which may vary between free and pro versions
            
            return array(
                'success' => false,
                'error' => 'WP Reset integration not yet implemented',
            );

        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'WP Reset snapshot failed', array(
                'error' => $e->getMessage(),
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Restore from a WP Reset snapshot.
     *
     * @param int   $snapshot_id Snapshot ID.
     * @param array $options     Restore options.
     * @return array Restore result.
     */
    public function restoreSnapshot(int $snapshotId, array $options): array {
        // TODO: Implement WP Reset restore
        return array(
            'success' => false,
            'error' => 'WP Reset restore not yet implemented',
        );
    }

    /**
     * Delete a snapshot.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Delete result.
     */
    public function deleteSnapshot(int $snapshotId): array {
        // TODO: Implement WP Reset delete
        return array(
            'success' => false,
            'error' => 'WP Reset delete not yet implemented',
        );
    }

    /**
     * Export snapshot to file.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array Export result.
     */
    public function exportSnapshot(int $snapshotId): array {
        // TODO: Implement WP Reset export
        return array(
            'success' => false,
            'error' => 'WP Reset export not yet implemented',
        );
    }

    /**
     * Import snapshot from file.
     *
     * @param string $filepath Path to file.
     * @return array Import result.
     */
    public function importSnapshot(string $filepath): array {
        // TODO: Implement WP Reset import
        return array(
            'success' => false,
            'error' => 'WP Reset import not yet implemented',
        );
    }

    /**
     * Get snapshot details.
     *
     * @param int $snapshot_id Snapshot ID.
     * @return array|null Snapshot or null.
     */
    public function getSnapshot(int $snapshotId): ?array {
        return $this->db->querySingle(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE id = ? AND provider = ?',
            array($snapshotId, $this->provider_id)
        );
    }

    /**
     * List snapshots.
     *
     * @param int $limit  Limit.
     * @param int $offset Offset.
     * @return array List result.
     */
    public function listSnapshots(int $limit = 50, int $offset = 0): array {
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE provider = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            array($this->provider_id, $limit, $offset)
        );

        $total = $this->db->querySingle(
            'SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value . ' WHERE provider = ?',
            array($this->provider_id)
        );

        return array(
            'snapshots' => $snapshots ?: array(),
            'total' => $total ? (int)$total['count'] : 0,
        );
    }

    /**
     * Get available tables.
     *
     * @return array Tables list.
     */
    public function getAvailableTables(): array {
        // WP Reset handles all tables internally
        global $wpdb;
        $tables = array();
        $all_tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        foreach ($all_tables as $table_info) {
            $tables[] = array(
                'name' => $table_info['Name'],
                'rows' => (int)$table_info['Rows'],
                'size' => (int)$table_info['Data_length'] + (int)$table_info['Index_length'],
                'is_core' => strpos($table_info['Name'], $wpdb->prefix) === 0,
            );
        }

        return $tables;
    }
}
