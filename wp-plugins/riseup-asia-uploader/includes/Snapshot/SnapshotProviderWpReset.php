<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class SnapshotProviderWPReset extends SnapshotProviderInterface {
    protected string $provider_id = SnapshotProviderType::WpReset->value;
    protected string $provider_name = 'WP Reset';
    private mixed $wp_reset = null;

    public function __construct(FileLogger $logger, Database $db) {
        parent::__construct($logger, $db);
        if (class_exists('WP_Reset')) { global $wp_reset; $this->wp_reset = $wp_reset; }
    }

    public function isAvailable(): bool { return class_exists('WP_Reset') || class_exists('WP_Reset_Pro'); }

    public function getCapabilities(): array {
        $is_pro = class_exists('WP_Reset_Pro');

        return array('full_site' => true, 'database_only' => true, 'selective' => true, 'scheduled' => $is_pro, 'restore' => true, 'export' => true, 'import' => true);
    }

    public function createSnapshot(array $options): array {
        $isProviderUnavailable = ($this->isAvailable() === false);
        if ($isProviderUnavailable) {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'WP Reset is not available', ResponseKeyType::Code->value => SnapshotErrorType::ProviderNotAvail->value);
        }
        $this->log(LogLevelType::Info->value, 'Creating snapshot via WP Reset', $options);
        try {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'WP Reset integration not yet implemented');
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'WP Reset snapshot failed', array('error' => $e->getMessage()));

            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => $e->getMessage());
        }
    }

    public function restoreSnapshot(int $snapshotId, array $options): array { return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'WP Reset restore not yet implemented'); }
    public function deleteSnapshot(int $snapshotId): array { return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'WP Reset delete not yet implemented'); }
    public function exportSnapshot(int $snapshotId): array { return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'WP Reset export not yet implemented'); }
    public function importSnapshot(string $filepath): array { return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'WP Reset import not yet implemented'); }

    public function getSnapshot(int $snapshotId): ?array {
        return $this->db->querySingle('SELECT * FROM ' . TableType::Snapshots->value . ' WHERE id = ? AND provider = ?', array($snapshotId, $this->provider_id));
    }

    public function listSnapshots(int $limit = 50, int $offset = 0): array { // PaginationConfigType::DefaultLimit
        $snapshots = $this->db->queryAll('SELECT * FROM ' . TableType::Snapshots->value . ' WHERE provider = ? ORDER BY created_at DESC LIMIT ? OFFSET ?', array($this->provider_id, $limit, $offset));
        $total = $this->db->querySingle('SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value . ' WHERE provider = ?', array($this->provider_id));

        return array('snapshots' => $snapshots ?: array(), 'total' => $total ? (int)$total['count'] : 0);
    }

    public function getAvailableTables(): array {
        global $wpdb;
        $tables = array();
        $all_tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        foreach ($all_tables as $table_info) {
            $tables[] = array('name' => $table_info['Name'], 'rows' => (int)$table_info['Rows'], 'size' => (int)$table_info['Data_length'] + (int)$table_info['Index_length'], 'is_core' => strpos($table_info['Name'], $wpdb->prefix) === 0);
        }

        return $tables;
    }
}
