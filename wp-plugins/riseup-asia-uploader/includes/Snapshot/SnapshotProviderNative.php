<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

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

    protected string $provider_id = SnapshotProviderType::Native->value;
    protected string $provider_name = 'Native SQLite';
    private \wpdb $wpdb;

    public function __construct(FileLogger $logger, Database $db) {
        parent::__construct($logger, $db);
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function isAvailable(): bool {
        return extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
    }

    public function getCapabilities(): array {
        return array(
            'full_site' => false, 'database_only' => true, 'selective' => true,
            'scheduled' => true, 'restore' => true, 'export' => true, 'import' => true,
        );
    }
}

class_alias(SnapshotProviderNative::class, 'RiseupSnapshotProviderNative');
