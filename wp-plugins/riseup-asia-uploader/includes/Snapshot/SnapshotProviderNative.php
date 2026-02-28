<?php
/**
 * Riseup Asia Uploader - Native Snapshot Provider
 *
 * @package RiseupAsia\Snapshot
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use wpdb;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Snapshot\Traits\NativeSnapshotCreateTrait;
use RiseupAsia\Snapshot\Traits\NativeTableExportTrait;
use RiseupAsia\Snapshot\Traits\NativeSnapshotRecordTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

class SnapshotProviderNative extends SnapshotProviderInterface {
    use NativeSnapshotCreateTrait;
    use NativeTableExportTrait;
    use NativeSnapshotRecordTrait;

    protected string $providerId = SnapshotProviderType::Native->value;
    protected string $providerName = 'Native SQLite';
    private wpdb $wpdb;

    public function __construct(FileLogger $logger, Database $db) {
        parent::__construct($logger, $db);
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function isAvailable(): bool {
        $hasSqlite3    = extension_loaded('sqlite3');
        $hasPdoSqlite  = extension_loaded('pdo_sqlite');

        return $hasSqlite3 || $hasPdoSqlite;
    }

    public function getCapabilities(): array {
        return array(
            'fullSite' => false,
            'databaseOnly' => true,
            'selective' => true,
            'scheduled' => true,
            'restore' => true,
            'export' => true,
            'import' => true,
        );
    }
}
