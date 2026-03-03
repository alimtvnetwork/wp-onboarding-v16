<?php
/**
 * UpdraftCrudTrait — CRUD operations for UpdraftPlus snapshot provider.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\TableType;

trait UpdraftCrudTrait {
    public function createSnapshot(array $options): array {
        $isUnavailable = ($this->isAvailable() === false);

        if ($isUnavailable) {
            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value => 'UpdraftPlus is not available',
                ResponseKeyType::Code->value => SnapshotErrorType::ProviderNotAvail->value,
            );
        }

        $this->log(LogLevelType::Info->value, 'Creating snapshot via UpdraftPlus', $options);

        try {
            return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'UpdraftPlus integration not yet implemented');
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'UpdraftPlus snapshot failed', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));

            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value => $e->getMessage(),
            );
        }
    }

    public function restoreSnapshot(int $snapshotId, array $options): array {
        return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'UpdraftPlus restore not yet implemented');
    }

    public function deleteSnapshot(int $snapshotId): array {
        return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'UpdraftPlus delete not yet implemented');
    }

    public function exportSnapshot(int $snapshotId): array {
        return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'UpdraftPlus export not yet implemented');
    }

    public function importSnapshot(string $filepath): array {
        return array(ResponseKeyType::Success->value => false, ResponseKeyType::Error->value => 'UpdraftPlus import not yet implemented');
    }

    public function getSnapshot(int $snapshotId): ?array {
        return $this->db->querySingle(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE Id = ? AND Provider = ?',
            array($snapshotId, $this->providerId)
        );
    }

    public function listSnapshots(int $limit = 50, int $offset = 0): array { // PaginationConfigType::DefaultLimit
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE Provider = ? ORDER BY CreatedAt DESC LIMIT ? OFFSET ?',
            array(
                $this->providerId,
                $limit,
                $offset,
            )
        );

        $total = $this->db->querySingle(
            'SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value . ' WHERE Provider = ?',
            array($this->providerId)
        );

        return array(
            ResponseKeyType::Snapshots->value => $snapshots ?: array(),
            ResponseKeyType::Total->value => $total ? (int)$total['count'] : 0,
        );
    }

    public function getAvailableTables(): array {
        global $wpdb;
        $allTables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        return array_map(function($info) use ($wpdb) {
            $isCoreTable = (strpos($info['Name'], $wpdb->prefix) === 0);

            return array(
                ResponseKeyType::Name->value   => $info['Name'],
                ResponseKeyType::Rows->value   => (int)$info['Rows'],
                ResponseKeyType::Size->value   => (int)$info['Data_length'] + (int)$info['Index_length'],
                ResponseKeyType::IsCore->value => $isCoreTable,
            );
        }, $allTables);
    }
}
