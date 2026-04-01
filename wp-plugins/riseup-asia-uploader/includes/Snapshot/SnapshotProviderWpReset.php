<?php
/**
 * Riseup Asia Uploader - WP Reset Snapshot Provider
 *
 * @package RiseupAsia\Snapshot
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\TableType;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class SnapshotProviderWpReset extends SnapshotProviderInterface {
    protected string $providerId = SnapshotProviderType::WpReset->value;
    protected string $providerName = 'WP Reset';
    private mixed $wpReset = null;

    public function __construct(FileLogger $logger, Database $db) {
        parent::__construct($logger, $db);

        if (class_exists('WP_Reset')) {
            global $wp_reset;
            $this->wpReset = $wp_reset;
        }
    }

    public function isAvailable(): bool {
        $hasFreeVersion = class_exists('WP_Reset');
        $hasProVersion  = class_exists('WP_Reset_Pro');

        return $hasFreeVersion || $hasProVersion;
    }

    public function getCapabilities(): array {
        $isPro = class_exists('WP_Reset_Pro');

        return [
            'fullSite' => true,
            'databaseOnly' => true,
            'selective' => true,
            'scheduled' => $isPro,
            'restore' => true,
            'export' => true,
            'import' => true,
        ];
    }

    public function createSnapshot(array $options): array {
        $isProviderUnavailable = ($this->isAvailable() === false);

        if ($isProviderUnavailable) {
            return [
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value => 'WP Reset is not available',
                ResponseKeyType::Code->value => SnapshotErrorType::ProviderNotAvail->value,
            ];
        }

        $this->log(LogLevelType::Info->value, 'Creating snapshot via WP Reset', $options);

        try {
            return [
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value => 'WP Reset integration not yet implemented',
            ];
        } catch (Throwable $e) {
            $this->log(
                LogLevelType::Error->value,
                'WP Reset snapshot failed',
                [ResponseKeyType::Error->value => $e->getMessage()],
            );

            return [
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value => $e->getMessage(),
            ];
        }
    }

    public function restoreSnapshot(int $snapshotId, array $options): array {
        return [
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value => 'WP Reset restore not yet implemented',
        ];
    }

    public function deleteSnapshot(int $snapshotId): array {
        return [
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value => 'WP Reset delete not yet implemented',
        ];
    }

    public function exportSnapshot(int $snapshotId): array {
        return [
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value => 'WP Reset export not yet implemented',
        ];
    }

    public function importSnapshot(string $filepath): array {
        return [
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value => 'WP Reset import not yet implemented',
        ];
    }

    public function getSnapshot(int $snapshotId): ?array {
        return $this->db->querySingle(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE Id = ? AND Provider = ?',
            [$snapshotId, $this->providerId],
        );
    }

    /** @param int $limit PaginationConfigType::DefaultLimit (PHP constraint: no enum in defaults) */
    public function listSnapshots(int $limit = 50, int $offset = 0): array {
        $snapshots = $this->db->queryAll(
            'SELECT * FROM ' . TableType::Snapshots->value . ' WHERE Provider = ? ORDER BY CreatedAt DESC LIMIT ? OFFSET ?',
            [$this->providerId, $limit, $offset],
        );

        $total = $this->db->querySingle(
            'SELECT COUNT(*) as count FROM ' . TableType::Snapshots->value . ' WHERE Provider = ?',
            [$this->providerId],
        );

        return [
            ResponseKeyType::Snapshots->value => $snapshots ?: array(),
            ResponseKeyType::Total->value => $total ? (int) $total[ResponseKeyType::Count->value] : 0,
        ];
    }

    public function getAvailableTables(): array {
        global $wpdb;
        $tables = [];
        $allTables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        foreach ($allTables as $tableInfo) {
            $isCoreTable = (strpos($tableInfo['Name'], $wpdb->prefix) === 0);

            $tables[] = [
                ResponseKeyType::Name->value   => $tableInfo['Name'],
                ResponseKeyType::Rows->value   => (int) $tableInfo['Rows'],
                ResponseKeyType::Size->value   => (int) $tableInfo['Data_length'] + (int) $tableInfo['Index_length'],
                ResponseKeyType::IsCore->value => $isCoreTable,
            ];
        }

        return $tables;
    }
}
