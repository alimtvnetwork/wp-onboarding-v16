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

    private const FALLBACK_IP = '0.0.0.0';
    private const ANONYMOUS_LOGIN = 'anonymous';
    private const ANONYMOUS_USER_ID = 0;
    private const SOURCE_MACHINE_HEADER = 'HTTP_X_RISEUP_SOURCE_MACHINE';
    private const USER_AGENT_MAX_LENGTH = 200;

    /** @var RiseupDatabase|null */
    private $db = null;

    /** @var RiseupFileLogger */
    private $fileLogger;

    /** @var RiseupLogger|null */
    private static $instance = null;

    /** @return RiseupLogger */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Private constructor. */
    private function __construct() {
        $this->fileLogger = RiseupFileLogger::getInstance();
        $this->fileLogger->info('Transaction logger initialized');
    }
}
