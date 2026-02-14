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

    /** @var string */
    protected string $provider_id = SnapshotProviderType::Native->value;

    /** @var string */
    protected $provider_name = 'Native SQLite';

    /** @var wpdb */
    private $wpdb;

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     */
    public function __construct($logger, $db) {
        parent::__construct($logger, $db);
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Check if provider is available.
     *
     * @return bool True if SQLite extension is loaded.
     */
    public function isAvailable() {
        return extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
    }

    /**
     * Get provider capabilities.
     *
     * @return array Capabilities array.
     */
    public function getCapabilities() {
        return array(
            'full_site' => false, 'database_only' => true, 'selective' => true,
            'scheduled' => true, 'restore' => true, 'export' => true, 'import' => true,
        );
    }
}
