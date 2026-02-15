<?php
/**
 * Logger Write Trait — File writing, stack trace persistence, and error session persistence.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Logging\Traits;

use RiseupAsia\Enums\PluginConfigType;

trait LoggerWriteTrait {

    /** Write to log file. */
    private function write(string $entry, bool $isError = false): bool {
        if (!$this->isInitialized && !$this->initializePaths()) {
            error_log(PluginConfigType::LogPrefix->value . ' ' . trim($entry));

            return false;
        }

        $result = @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);

        if ($isError) {
            @file_put_contents($this->errorFile, $entry, FILE_APPEND | LOCK_EX);
        }

        return $result !== false;
    }

    /** Write a stack trace entry to the dedicated stacktrace.txt file. */
    private function writeStacktrace(string $message, string $file, int $line, string $stackTrace): void {
        if (empty($stackTrace)) {
            return;
        }

        if (!$this->isInitialized && !$this->initializePaths()) {
            return;
        }

        $timestamp = gmdate(self::TIMESTAMP_FORMAT);
        $separator = str_repeat('=', self::SEPARATOR_WIDTH);
        $divider   = str_repeat('-', self::SEPARATOR_WIDTH);

        $entry  = $separator . PHP_EOL;
        $entry .= sprintf("[%s] %s (%s:%d)", $timestamp, $message, basename($file), $line) . PHP_EOL;
        $entry .= $divider . PHP_EOL;
        $entry .= $stackTrace . PHP_EOL;
        $entry .= $separator . PHP_EOL . PHP_EOL;

        @file_put_contents($this->stacktraceFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /** Persist an error/warn entry to the error_sessions SQLite table. */
    private function persistToErrorSessions(string $level, string $message, string $file, int $line, array $context = array(), string $stackTrace = ''): void {
        try {
            $pdo = $this->getErrorSessionsPdo();
            if (!$pdo) {
                return;
            }

            $this->insertErrorSession($pdo, $level, $message, $file, $line, $context, $stackTrace);
        } catch (\Throwable $e) {
            // Silently ignore - we're in the logger, can't recurse
        }
    }

    /** Get a PDO connection with error_sessions table available. */
    private function getErrorSessionsPdo(): ?\PDO {
        if (\RiseupBooleanHelpers::isClassMissing('RiseupDatabase')) {
            return null;
        }

        $db  = \RiseupDatabase::getInstance();
        $pdo = $db->getPdo();
        if (!$pdo) {
            return null;
        }

        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . self::TABLE_ERROR_SESSIONS . "'");
        $isTableExists = $check && $check->fetchColumn();

        return $isTableExists ? $pdo : null;
    }

    /** Insert an error session record and set unseen flag. */
    private function insertErrorSession(\PDO $pdo, string $level, string $message, string $file, int $line, array $context, string $stackTrace): void {
        $now = gmdate(self::TIMESTAMP_FORMAT);
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::TABLE_ERROR_SESSIONS . ' (level, message, file, line, context_json, stack_trace, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array($level, $message, $file, $line, $contextJson, $stackTrace ?: null, $now));

        $pdo->exec("INSERT OR REPLACE INTO " . self::TABLE_FLASH_STATE . " (key, value, updated_at) VALUES ('" . self::KEY_HAS_UNSEEN_ERRORS . "', '1', '{$now}')");
    }
}
