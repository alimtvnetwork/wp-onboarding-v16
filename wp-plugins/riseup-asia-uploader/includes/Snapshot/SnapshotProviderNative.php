<?php
/**
 * Riseup Asia Uploader - Native SQLite Snapshot Provider
 *
 * Shell class delegating to NativeSnapshotCreateTrait, NativeTableExportTrait,
 * and NativeSnapshotRecordTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotProviderType;

require_once dirname(__FILE__) . '/SnapshotProviderInterface.php';
require_once dirname(__FILE__) . '/Traits/NativeSnapshotCreateTrait.php';
require_once dirname(__FILE__) . '/Traits/NativeTableExportTrait.php';
require_once dirname(__FILE__) . '/Traits/NativeSnapshotRecordTrait.php';

/**
 * Native SQLite Snapshot Provider.
 */
class RiseupSnapshotProviderNative extends RiseupSnapshotProviderInterface {

    use NativeSnapshotCreateTrait;
    use NativeTableExportTrait;
    use NativeSnapshotRecordTrait;

    protected string $provider_id = SnapshotProviderType::Native->value;
    protected string $provider_name = 'Native SQLite';
    private \wpdb $wpdb;

    public function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
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
