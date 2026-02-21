<?php
/**
 * Riseup Asia Uploader - Transaction Logger
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsia\Logging
 * @since   1.4.0
 */

namespace RiseupAsia\Logging;

use RiseupAsia\Database\Database;
use RiseupAsia\Logging\Traits\LoggerContextTrait;
use RiseupAsia\Logging\Traits\LoggerActionsTrait;

/**
 * Class Logger
 *
 * Provides convenient methods for logging transactions.
 */
class Logger {

    use LoggerContextTrait;
    use LoggerActionsTrait;

    private const FALLBACK_IP = '0.0.0.0';
    private const ANONYMOUS_LOGIN = 'anonymous';
    private const ANONYMOUS_USER_ID = 0;
    private const SOURCE_MACHINE_HEADER = 'HTTP_X_RISEUP_SOURCE_MACHINE';
    private const USER_AGENT_MAX_LENGTH = 200;

    private ?Database $db = null;
    private FileLogger $fileLogger;
    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->fileLogger->info('Transaction logger initialized');
    }
}
