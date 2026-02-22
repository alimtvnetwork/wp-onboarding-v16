<?php
/**
 * Logger Write Trait — File writing, stack trace persistence, and error session persistence.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

namespace RiseupAsia\Logging\Traits;

use PDO;
use Throwable;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Database\Database;

trait LoggerWriteTrait {
    /** Write to log file. */
    private function write(string $entry, bool $isError = false): bool {
        $isUninitialized = ($this->isInitialized === false);
        $isInitFailed = $isUninitialized && ($this->initializePaths() === false);

        if ($isInitFailed) {
            InitHelpers::errorLogWithPrefix(trim($entry));

            return false;
        }

        $result = @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);

        if ($isError) {
            @file_put_contents($this->errorFile, $entry, FILE_APPEND | LOCK_EX);
        }

        return $result !== false;
    }

    /** Write a stack trace entry to the dedicated stacktrace.txt file. */
    private function writeStacktrace(
        string $message,
        string $file,
        int $line,
        string $stackTrace,
    ): void {
        if (empty($stackTrace)) {
            return;
        }

        $isUninitialized = ($this->isInitialized === false);
        $isInitFailed = $isUninitialized && ($this->initializePaths() === false);

        if ($isInitFailed) {
            return;
        }

        $timestamp = DateHelper::nowUtc();
        $separator = str_repeat('=', self::SEPARATOR_WIDTH);
        $divider   = str_repeat('-', self::SEPARATOR_WIDTH);

        $entry  = $separator . PHP_EOL;
        $entry .= sprintf(
            "[%s] %s (%s:%d)",
            $timestamp,
            $message,
            basename($file),
            $line,
        ) . PHP_EOL;
        $entry .= $divider . PHP_EOL;
        $entry .= $stackTrace . PHP_EOL;
        $entry .= $separator . PHP_EOL . PHP_EOL;

        @file_put_contents($this->stacktraceFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /** Persist an error/warn entry to the error_sessions SQLite table. */
    private function persistToErrorSessions(
        string $level,
        string $message,
        string $file,
        int $line,
        array $context = array(),
        string $stackTrace = '',
    ): void {
        try {
            $pdo = $this->getErrorSessionsPdo();
            $isPdoMissing = ($pdo === null);

            if ($isPdoMissing) {
                return;
            }

            $this->insertErrorSession(
                $pdo,
                $level,
                $message,
                $file,
                $line,
                $context,
                $stackTrace,
            );
        } catch (Throwable $e) {
            // Silently ignore - we're in the logger, can't recurse
        }
    }

    /** Get a PDO connection with error_sessions table available. */
    private function getErrorSessionsPdo(): ?PDO {
        if (BooleanHelpers::isClassMissing(Database::class)) {
            return null;
        }

        $db  = Database::getInstance();
        $pdo = $db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return null;
        }

        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . self::TABLE_ERROR_SESSIONS . "'");
        $isTableExists = $check && $check->fetchColumn();

        return $isTableExists ? $pdo : null;
    }

    /** Insert an error session record and set unseen flag. */
    private function insertErrorSession(
        PDO $pdo,
        string $level,
        string $message,
        string $file,
        int $line,
        array $context,
        string $stackTrace,
    ): void {
        $now = DateHelper::nowUtc();
        $hasContext = BooleanHelpers::hasValue($context);
        $contextJson = $hasContext ? json_encode($context, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::TABLE_ERROR_SESSIONS . ' (Level, Message, File, Line, ContextJson, StackTrace, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $level,
            $message,
            $file,
            $line,
            $contextJson,
            $stackTrace ?: null,
            $now,
        ));

        $pdo->exec("INSERT OR REPLACE INTO " . self::TABLE_FLASH_STATE . " (Key, Value, UpdatedAt) VALUES ('" . self::KEY_HAS_UNSEEN_ERRORS . "', '1', '{$now}')");
    }
}
