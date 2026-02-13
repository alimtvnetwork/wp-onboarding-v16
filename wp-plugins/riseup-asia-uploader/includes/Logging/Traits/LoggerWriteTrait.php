<?php
/**
 * Logger Write Trait
 *
 * File writing, stack trace persistence, and error session persistence.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait LoggerWriteTrait {

    /**
     * Write to log file.
     *
     * @param string $entry    Log entry.
     * @param bool   $is_error Whether this is an error.
     * @return bool True on success.
     */
    private function write($entry, $is_error = false) {
        if (!$this->initialized) {
            if (!$this->initialize_paths()) {
                error_log('[Riseup Asia] ' . trim($entry));
                return false;
            }
        }

        $result = @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);

        if ($is_error) {
            @file_put_contents($this->error_file, $entry, FILE_APPEND | LOCK_EX);
        }

        return $result !== false;
    }

    /**
     * Write a stack trace entry to the dedicated stacktrace.txt file.
     *
     * @param string $message     Error message.
     * @param string $file        Source file.
     * @param int    $line        Source line number.
     * @param string $stack_trace Stack trace string.
     */
    private function write_stacktrace($message, $file, $line, $stack_trace) {
        if (empty($stack_trace)) {
            return;
        }

        if (!$this->initialized) {
            if (!$this->initialize_paths()) {
                return;
            }
        }

        $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
        $separator = str_repeat('=', 80);
        $entry  = $separator . PHP_EOL;
        $entry .= sprintf("[%s] %s (%s:%d)", $timestamp, $message, basename($file), $line) . PHP_EOL;
        $entry .= str_repeat('-', 80) . PHP_EOL;
        $entry .= $stack_trace . PHP_EOL;
        $entry .= $separator . PHP_EOL . PHP_EOL;

        @file_put_contents($this->stacktrace_file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Persist an error/warn entry to the error_sessions SQLite table.
     *
     * @param string $level       Log level.
     * @param string $message     Error message.
     * @param string $file        Source file path.
     * @param int    $line        Source line number.
     * @param array  $context     Additional context data.
     * @param string $stack_trace Optional stack trace string.
     */
    private function persist_to_error_sessions($level, $message, $file, $line, $context = array(), $stack_trace = '') {
        try {
            $pdo = $this->getErrorSessionsPdo();
            if (!$pdo) {
                return;
            }

            $this->insertErrorSession($pdo, $level, $message, $file, $line, $context, $stack_trace);
        } catch (\Throwable $e) {
            // Silently ignore - we're in the logger, can't recurse
        }
    }

    /** Get a PDO connection with error_sessions table available. */
    private function getErrorSessionsPdo(): ?PDO {
        if (RiseupBooleanHelpers::is_class_missing('RiseupDatabase')) {
            return null;
        }

        $db = RiseupDatabase::get_instance();
        $pdo = $db->get_pdo();
        if (!$pdo) {
            return null;
        }

        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='error_sessions'");
        $tableExists = $check && $check->fetchColumn();

        return $tableExists ? $pdo : null;
    }

    /** Insert an error session record and set unseen flag. */
    private function insertErrorSession(PDO $pdo, string $level, string $message, string $file, int $line, array $context, string $stack_trace) {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $context_json = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO error_sessions (level, message, file, line, context_json, stack_trace, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array($level, $message, $file, $line, $context_json, $stack_trace ?: null, $now));

        $pdo->exec("INSERT OR REPLACE INTO flash_state (key, value, updated_at) VALUES ('has_unseen_errors', '1', '{$now}')");
    }
}
