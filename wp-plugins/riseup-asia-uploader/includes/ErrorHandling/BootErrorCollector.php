<?php
/**
 * BootErrorCollector — Collects boot-time errors and flushes them via email on shutdown.
 *
 * Singleton that captures errors during plugin initialization (autoloader failures,
 * component init failures) and sends a throttled email report to the site admin.
 *
 * @package RiseupAsia\ErrorHandling
 * @since   2.2.0
 */

namespace RiseupAsia\ErrorHandling;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Notification\AdminMailer;
use RiseupAsia\Helpers\DateHelper;

class BootErrorCollector {

    private const LOG_PREFIX = '[Riseup Asia] BootErrorCollector: ';

    /** @var array<int, array{context: string, message: string, timestamp: string}> */
    private array $errors = [];

    private bool $shutdownRegistered = false;

    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    /**
     * Record a boot-time error.
     *
     * @param string $context Error context identifier (e.g., 'autoloader', 'plugin_init').
     * @param string $message Human-readable error description.
     */
    public function addError(string $context, string $message): void {
        $this->errors[] = [
            'context'   => $context,
            'message'   => $message,
            'timestamp' => DateHelper::nowUtc(),
        ];

        error_log(self::LOG_PREFIX . '[' . $context . '] ' . $message);

        $this->ensureShutdownHook();
    }

    /**
     * Returns all collected errors.
     *
     * @return array<int, array{context: string, message: string, timestamp: string}>
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Check whether any errors have been collected.
     */
    public function hasErrors(): bool {
        return count($this->errors) > 0;
    }

    /**
     * Flush collected errors by sending an email report.
     * Called automatically on PHP shutdown if errors were collected.
     */
    public function flush(): void {
        $hasNoErrors = (count($this->errors) === 0);
        if ($hasNoErrors) {
            return;
        }

        try {
            $mailer = new AdminMailer();
            $wasSent = $mailer->sendBootErrorReport($this->errors);

            if ($wasSent) {
                error_log(self::LOG_PREFIX . 'boot error report sent (' . count($this->errors) . ' error(s))');
            } else {
                error_log(self::LOG_PREFIX . 'boot error report skipped (throttled or disabled)');
            }
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . 'failed to send boot error report — ' . $e->getMessage());
        }

        $this->errors = [];
    }

    /**
     * Register the shutdown hook exactly once.
     */
    private function ensureShutdownHook(): void {
        if ($this->shutdownRegistered) {
            return;
        }

        register_shutdown_function([$this, 'flush']);
        $this->shutdownRegistered = true;
    }
}
