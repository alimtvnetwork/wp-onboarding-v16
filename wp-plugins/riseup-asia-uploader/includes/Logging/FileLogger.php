<?php
/**
 * Riseup Asia Uploader - File Logger
 *
 * Logs all operations to file with file path and line numbers.
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Logging
 * @since   1.4.0
 */

namespace RiseupAsia\Logging;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Logging\Traits\LoggerPathTrait;
use RiseupAsia\Logging\Traits\LoggerFormatTrait;
use RiseupAsia\Logging\Traits\LoggerWriteTrait;
use RiseupAsia\Logging\Traits\LoggerDedupTrait;
use RiseupAsia\Logging\Traits\LoggerLevelMethodsTrait;
use RiseupAsia\Helpers\DateHelper;

/**
 * Class FileLogger
 *
 * Provides file-based logging with detailed context and MD5 deduplication.
 */
class FileLogger {
    use LoggerPathTrait;
    use LoggerFormatTrait;
    use LoggerWriteTrait;
    use LoggerDedupTrait;
    use LoggerLevelMethodsTrait;

    private const SEPARATOR_WIDTH = 80;
    private const TRACE_LABEL_INTERNAL = '<internal>';
    private const TRACE_LABEL_UNKNOWN = '<unknown>';
    private const DEFAULT_LINE_NUMBER = 0;
    private const TABLE_ERROR_SESSIONS = 'ErrorSessions'; // TableType::ErrorSessions
    private const TABLE_FLASH_STATE = 'FlashState'; // TableType::FlashState
    private const KEY_HAS_UNSEEN_ERRORS = 'has_unseen_errors';
    private const USER_AGENT_MAX_LENGTH = 200;
    private const DEFAULT_MAX_LOG_SIZE_BYTES = 524288; // 512 KB
    private const TRACE_LABEL_UNKNOWN = '<unknown>';
    private const DEFAULT_LINE_NUMBER = 0;
    private const TABLE_ERROR_SESSIONS = 'ErrorSessions'; // TableType::ErrorSessions
    private const TABLE_FLASH_STATE = 'FlashState'; // TableType::FlashState
    private const KEY_HAS_UNSEEN_ERRORS = 'has_unseen_errors';
    private const USER_AGENT_MAX_LENGTH = 200;

    private static ?self $instance = null;
    private ?string $baseDir = null;
    private ?string $logsDir = null;
    private ?string $logFile = null;
    private ?string $errorFile = null;
    private ?string $stacktraceFile = null;
    private bool $isInitialized = false;
    private int $maxLogSizeBytes = self::DEFAULT_MAX_LOG_SIZE_BYTES;

    /**
     * Maximum stack frames to capture in debug_backtrace().
     * 0 = unlimited (PHP default). Seeded from config: logging.phpStackTraceDepth.
     */
    private int $stackTraceDepth = 0;

    /** @var array<string, bool> */
    private array $dedupHashes = array();
    private ?array $requestMetadataCache = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    /** Set the maximum stack trace depth for debug_backtrace() calls. */
    public function setStackTraceDepth(int $depth): void {
        $this->stackTraceDepth = $depth;
    }

    /** Get the configured stack trace depth (0 = unlimited). */
    public function getStackTraceDepth(): int {
        return $this->stackTraceDepth;
    }
}
