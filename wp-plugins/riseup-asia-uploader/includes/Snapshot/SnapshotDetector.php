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

    private RiseupFileLogger $logger;
    private RiseupDatabase $db;
    private array $provider_instances = array();

    public function __construct(RiseupFileLogger $logger, RiseupDatabase $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
}
