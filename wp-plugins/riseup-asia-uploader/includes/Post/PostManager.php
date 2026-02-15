<?php
/**
 * Riseup Asia Uploader - Post Manager
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PostStatusType;

require_once dirname(__FILE__) . '/Traits/PostCrudTrait.php';
require_once dirname(__FILE__) . '/Traits/PostQueryTrait.php';
require_once dirname(__FILE__) . '/Traits/CategoryTrait.php';

/**
 * Class RiseupPostManager
 *
 * Provides methods for creating and updating posts and categories.
 */
class RiseupPostManager {

    use PostCrudTrait;
    use PostQueryTrait;
    use CategoryTrait;

    private RiseupLogger $logger;
    private RiseupFileLogger $fileLogger;
    private static ?RiseupPostManager $instance = null;

    public static function getInstance(): RiseupPostManager {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Constructor. */
    private function __construct() {
        $this->fileLogger = RiseupFileLogger::getInstance();
        $this->logger = RiseupLogger::getInstance();
        $this->fileLogger->info('Post manager initialized');
    }

    /**
     * Validate post status.
     *
     * @param string $status Input status.
     * @return string Valid status.
     */
    private function validatePostStatus(string $status): string {
        $validStatuses = PostStatusType::validValues();
        return in_array($status, $validStatuses, true) ? $status : PostStatusType::Draft->value;
    }
}
