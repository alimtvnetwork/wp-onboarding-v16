<?php
/**
 * FileLogger — File-based logging with stack traces for QUpload.
 *
 * @package QUpload\Logging
 * @since   1.0.0
 */

namespace QUpload\Logging;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

use QUpload\Enums\LogLevelType;
use QUpload\Enums\PathLogFileType;
use QUpload\Enums\PluginConfigType;
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\PathHelper;

class FileLogger {
    private const SEPARATOR_WIDTH = 80;

    private static ?self $instance = null;
    private ?string $logsDir = null;
    private ?string $logFile = null;
    private ?string $errorFile = null;
    private ?string $stacktraceFile = null;
    private bool $isInitialized = false;

    /** @var array<string, bool> */
    private array $dedupHashes = [];

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
    }

    // ── Path Initialization ─────────────────────────────────────────

    private function initializePaths(): bool {
        if ($this->isInitialized) {
            return true;
        }

        $baseDir = PathHelper::getBaseDir();
        $this->logsDir        = PathHelper::getLogsDir();
        $this->logFile        = $this->logsDir . PathLogFileType::Log->value;
        $this->errorFile      = $this->logsDir . PathLogFileType::Error->value;
        $this->stacktraceFile = $this->logsDir . PathLogFileType::Stacktrace->value;

        $isBaseDirCreated = PathHelper::ensureDirectory($baseDir);

        if ($isBaseDirCreated === false) {
            error_log(PluginConfigType::LogPrefix->value . ' Failed to create base directory: ' . $baseDir);

            return false;
        }

        $isLogsDirCreated = PathHelper::ensureDirectory($this->logsDir);

        if ($isLogsDirCreated === false) {
            error_log(PluginConfigType::LogPrefix->value . ' Failed to create logs directory: ' . $this->logsDir);

            return false;
        }

        $isLogParentReady = PathHelper::ensureFileParentDirectory($this->logFile)
            && PathHelper::ensureFileParentDirectory($this->errorFile)
            && PathHelper::ensureFileParentDirectory($this->stacktraceFile);

        if ($isLogParentReady === false) {
            error_log(PluginConfigType::LogPrefix->value . ' Failed to create log file parent directories.');

            return false;
        }

        $this->isInitialized = true;

        return true;
    }

    // ── File Path Getters ──────────────────────────────────────────

    public function getLogFile(): ?string {
        if ($this->isInitialized === false) {
            $this->initializePaths();
        }

        return $this->logFile;
    }

    public function getErrorFile(): ?string {
        if ($this->isInitialized === false) {
            $this->initializePaths();
        }

        return $this->errorFile;
    }

    public function getStacktraceFile(): ?string {
        if ($this->isInitialized === false) {
            $this->initializePaths();
        }

        return $this->stacktraceFile;
    }

    // ── Log Cleanup ───────────────────────────────────────────────────

    /**
     * Clear all log files (log, error, stacktrace).
     * Used during plugin activation to start with a clean slate.
     */
    public function clearAllLogFiles(): void {
        if ($this->isInitialized === false) {
            $this->initializePaths();
        }

        $files = [$this->logFile, $this->errorFile, $this->stacktraceFile];

        foreach ($files as $file) {
            if ($file === null) {
                continue;
            }

            $isFileExists = file_exists($file);

            if ($isFileExists) {
                @unlink($file);
            }
        }

        $this->dedupHashes = [];
    }

    // ── Public Level Methods ────────────────────────────────────────

    public function debug(string $message, array $context = []): bool {
        return $this->logAtLevel(LogLevelType::Debug, $message, $context);
    }

    public function info(string $message, array $context = []): bool {
        return $this->logAtLevel(LogLevelType::Info, $message, $context);
    }

    public function warn(string $message, array $context = []): bool {
        return $this->logAtLevel(LogLevelType::Warn, $message, $context, true);
    }

    public function error(string $message, array $context = []): bool {
        return $this->logAtLevel(LogLevelType::Error, $message, $context, true);
    }

    public function logException(Throwable $e, string $context = ''): bool {
        $message = $context ? $context . ': ' . $e->getMessage() : $e->getMessage();

        $entry = $this->formatEntry(
            LogLevelType::Error->value,
            $message,
            $e->getFile(),
            $e->getLine(),
            ['trace' => $e->getTraceAsString()],
        );

        $this->writeStacktrace($message, $e->getFile(), $e->getLine(), $e->getTraceAsString());

        return $this->write($entry, true);
    }

    /**
     * Log an exception and re-throw it.
     *
     * Use this in boot, route registration, enum priming, and infrastructure catch blocks
     * where silent failure causes cascading breakage. The throw happens internally —
     * call sites do not need a separate `throw $e;` statement.
     *
     * @throws Throwable Always re-throws the original exception after logging.
     */
    public function logCriticalException(Throwable $e, string $context = ''): never {
        $this->logException($e, $context);

        throw $e;
    }

    // ── Internal ────────────────────────────────────────────────────

    private function logAtLevel(
        LogLevelType $level,
        string $message,
        array $context,
        bool $includeStacktrace = false,
    ): bool {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[1] ?? $trace[0];
        $file = $caller['file'] ?? __FILE__;
        $line = $caller['line'] ?? __LINE__;

        $isDuplicate = $this->isDuplicate($level->value, $message, $file, $line);

        if ($isDuplicate) {
            return true;
        }

        $entry = $this->formatEntry($level->value, $message, $file, $line, $context);
        $isError = $level->isError();

        if ($includeStacktrace) {
            $formattedTrace = $this->formatBacktrace($trace);
            $this->writeStacktrace($message, $file, $line, $formattedTrace);
        }

        return $this->write($entry, $isError);
    }

    private function formatEntry(
        string $level,
        string $message,
        string $file,
        int $line,
        array $context = [],
    ): string {
        $timestamp = DateHelper::nowLogDisplay();
        $basename = basename($file);

        $entry = sprintf("[%s] [%s] %s (%s:%d)", $timestamp, $level, $message, $basename, $line);

        if (!empty($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return $entry . PHP_EOL;
    }

    private function formatBacktrace(array $trace): string {
        $lines = [];

        foreach ($trace as $i => $frame) {
            $file  = isset($frame['file']) ? basename($frame['file']) : '<internal>';
            $fline = isset($frame['line']) ? $frame['line'] : 0;
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $func  = isset($frame['function']) ? $frame['function'] : '<unknown>';
            $lines[] = sprintf('#%d %s(%d): %s%s()', $i, $file, $fline, $class, $func);
        }

        return implode(PHP_EOL, $lines);
    }

    private function write(string $entry, bool $isError = false): bool {
        $isInitFailed = ($this->isInitialized === false) && ($this->initializePaths() === false);

        if ($isInitFailed) {
            error_log(PluginConfigType::LogPrefix->value . ' ' . trim($entry));

            return false;
        }

        $result = @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);

        if ($isError) {
            @file_put_contents($this->errorFile, $entry, FILE_APPEND | LOCK_EX);
        }

        return $result !== false;
    }

    private function writeStacktrace(
        string $message,
        string $file,
        int $line,
        string $stackTrace,
    ): void {
        if (empty($stackTrace)) {
            return;
        }

        $isInitFailed = ($this->isInitialized === false) && ($this->initializePaths() === false);

        if ($isInitFailed) {
            return;
        }

        $timestamp = DateHelper::nowLogDisplay();
        $separator = str_repeat('=', self::SEPARATOR_WIDTH);
        $divider   = str_repeat('-', self::SEPARATOR_WIDTH);

        $entry  = $separator . PHP_EOL;
        $entry .= sprintf("[%s] %s (%s:%d)", $timestamp, $message, basename($file), $line) . PHP_EOL;
        $entry .= $divider . PHP_EOL;
        $entry .= $stackTrace . PHP_EOL;
        $entry .= $separator . PHP_EOL . PHP_EOL;

        @file_put_contents($this->stacktraceFile, $entry, FILE_APPEND | LOCK_EX);
    }

    private function isDuplicate(string $level, string $message, string $file, int $line): bool {
        $hash = md5($level . '|' . $message . '|' . basename($file) . '|' . $line);

        if (isset($this->dedupHashes[$hash])) {
            return true;
        }

        $this->dedupHashes[$hash] = true;

        return false;
    }
}
