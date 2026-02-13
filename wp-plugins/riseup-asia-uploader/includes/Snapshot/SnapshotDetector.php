<?php
/**
 * Riseup Asia Uploader - Snapshot Provider Detector
 *
 * Shell class delegating to DetectorProviderTrait and DetectorSettingsTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/Traits/DetectorProviderTrait.php';
require_once dirname(__FILE__) . '/Traits/DetectorSettingsTrait.php';

/**
 * Snapshot Provider Detector class.
 */
class RiseupSnapshotDetector {

    use DetectorProviderTrait;
    use DetectorSettingsTrait;

    /** @var RiseupFileLogger */
    private $logger;

    /** @var RiseupDatabase */
    private $db;

    /** @var array */
    private $provider_instances = array();

    /**
     * Constructor.
     *
     * @param RiseupFileLogger $logger Logger instance.
     * @param RiseupDatabase   $db     Database instance.
     */
    public function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
}
