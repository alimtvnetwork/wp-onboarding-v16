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

    /** @var RiseupLogger */
    private $logger;

    /** @var RiseupFileLogger */
    private $fileLogger;

    /** @var RiseupPostManager|null */
    private static $instance = null;

    /** @return RiseupPostManager */
    public static function getInstance() {
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
    private function validatePostStatus($status) {
        $valid_statuses = array(POST_STATUS_PUBLISH, POST_STATUS_DRAFT, POST_STATUS_PENDING);
        return in_array($status, $valid_statuses, true) ? $status : POST_STATUS_DRAFT;
    }
}
