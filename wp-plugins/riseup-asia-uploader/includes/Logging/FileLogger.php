<?php
/**
 * Riseup Asia Uploader - File Logger
 *
 * Logs all operations to file with file path and line numbers.
 * Shell class — logic delegated to domain-specific traits.
 *
 * Implements MD5-based deduplication: identical log entries are written once
 * and subsequent occurrences are silently suppressed.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load traits
require_once dirname(__FILE__) . '/Traits/LoggerPathTrait.php';
require_once dirname(__FILE__) . '/Traits/LoggerFormatTrait.php';
require_once dirname(__FILE__) . '/Traits/LoggerWriteTrait.php';
require_once dirname(__FILE__) . '/Traits/LoggerDedupTrait.php';
require_once dirname(__FILE__) . '/Traits/LoggerLevelMethodsTrait.php';

/**
 * Class RiseupFileLogger
 *
 * Provides file-based logging with detailed context and MD5 deduplication.
 */
class RiseupFileLogger {

    use LoggerPathTrait;
    use LoggerFormatTrait;
    use LoggerWriteTrait;
    use LoggerDedupTrait;
    use LoggerLevelMethodsTrait;

    /** @var RiseupFileLogger|null */
    private static $instance = null;

    /** @var string|null */
    private $baseDir = null;

    /** @var string|null */
    private $logsDir = null;

    /** @var string|null */
    private $logFile = null;

    /** @var string|null */
    private $errorFile = null;

    /** @var string|null */
    private $stacktraceFile = null;

    /** @var bool */
    private $isInitialized = false;

    /** @var array<string, bool> */
    private $dedupHashes = array();

    /** @var array|null */
    private $requestMetadataCache = null;

    /** @return RiseupFileLogger */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Private constructor. */
    private function __construct() {
    }
}
