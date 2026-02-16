<?php
/**
 * Riseup Asia Uploader - Snapshot Provider Detector
 *
 * Shell class delegating to DetectorProviderTrait and DetectorSettingsTrait.
 *
 * @package RiseupAsia\Snapshot
 * @since   1.9.0
 */

namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Snapshot\Traits\DetectorProviderTrait;
use RiseupAsia\Snapshot\Traits\DetectorSettingsTrait;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\FileLogger;

/**
 * Snapshot Provider Detector class.
 */
class SnapshotDetector {

    use DetectorProviderTrait;
    use DetectorSettingsTrait;

    private FileLogger $logger;
    private Database $db;
    private array $provider_instances = array();

    public function __construct(FileLogger $logger, Database $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
}
