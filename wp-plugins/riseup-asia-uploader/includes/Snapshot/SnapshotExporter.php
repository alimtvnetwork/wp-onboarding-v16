<?php
/**
 * Riseup Asia Uploader - Snapshot ZIP Exporter
 *
 * Shell class delegating to ExporterPublicApiTrait, ExporterBuildTrait,
 * and ExporterHelpersTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/Traits/ExporterPublicApiTrait.php';
require_once dirname(__FILE__) . '/Traits/ExporterBuildTrait.php';
require_once dirname(__FILE__) . '/Traits/ExporterHelpersTrait.php';

/**
 * Snapshot ZIP Exporter.
 */
class RiseupSnapshotExporter {

    use ExporterPublicApiTrait;
    use ExporterBuildTrait;
    use ExporterHelpersTrait;

    private RiseupFileLogger $logger;
    private RiseupDatabase $db;
    private static ?RiseupSnapshotExporter $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance(?RiseupFileLogger $logger = null, ?RiseupDatabase $db = null): ?static {
        if (self::$instance === null && $logger && $db) {
            self::$instance = new self($logger, $db);
        }

        return self::$instance;
    }

    private function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        $this->logger = $logger;
        $this->db     = $db;
    }
}
