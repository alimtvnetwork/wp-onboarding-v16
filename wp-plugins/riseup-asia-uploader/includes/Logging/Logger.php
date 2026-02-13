<?php
/**
 * Riseup Asia Uploader - Transaction Logger
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/Traits/LoggerContextTrait.php';
require_once dirname(__FILE__) . '/Traits/LoggerActionsTrait.php';

/**
 * Class RiseupLogger
 *
 * Provides convenient methods for logging transactions.
 */
class RiseupLogger {

    use LoggerContextTrait;
    use LoggerActionsTrait;

    /** @var RiseupDatabase|null */
    private $db = null;

    /** @var RiseupFileLogger */
    private $fileLogger;

    /** @var RiseupLogger|null */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return RiseupLogger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Constructor. */
    private function __construct() {
        $this->fileLogger = RiseupFileLogger::getInstance();
        $this->fileLogger->info('Transaction logger initialized');
    }
}
