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

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var RiseupSnapshotExporter|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @param RiseupFileLogger|null $logger Logger.
     * @param RiseupDatabase|null   $db     Database.
     * @return RiseupSnapshotExporter
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
     * @param RiseupFileLogger $logger Logger.
     * @param RiseupDatabase   $db     Database.
     */
    private function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db     = $db;
    }
}
