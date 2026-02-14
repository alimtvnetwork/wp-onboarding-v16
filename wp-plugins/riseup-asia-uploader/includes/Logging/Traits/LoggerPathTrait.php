<?php
/**
 * Logger Path Trait
 *
 * Path initialization, directory creation, and path accessors.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PathLogFileType;

trait LoggerPathTrait {

    /** Initialize log file paths (lazy initialization). */
    private function initializePaths(): bool {
        if ($this->isInitialized) {
            return true;
        }

        $this->baseDir = RiseupInitHelpers::resolveBaseDir();
        $this->logsDir        = rtrim($this->baseDir, '/') . PathSubdirType::Logs->value;
        $this->logFile        = $this->logsDir . PathLogFileType::Log->value;
        $this->errorFile      = $this->logsDir . PathLogFileType::Error->value;
        $this->stacktraceFile = $this->logsDir . PathLogFileType::Stacktrace->value;

        return $this->ensureDirectories();
    }

    /** Ensure log directories exist. */
    private function ensureDirectories(): bool {
        if (!RiseupInitHelpers::ensureDirNative($this->baseDir, true)) {
            error_log(LOG_PREFIX . ' Failed to create base directory: ' . $this->baseDir);

            return false;
        }

        if (!RiseupInitHelpers::ensureDirNative($this->logsDir, false)) {
            error_log(LOG_PREFIX . ' Failed to create logs directory: ' . $this->logsDir);

            return false;
        }

        $this->isInitialized = true;

        return true;
    }

    public function getBaseDir(): string {
        if ($this->baseDir === null) {
            $this->initializePaths();
        }

        return $this->baseDir;
    }

    public function getLogsDir(): string {
        if ($this->logsDir === null) {
            $this->initializePaths();
        }

        return $this->logsDir;
    }

    public function getLogFile(): string {
        if ($this->logFile === null) {
            $this->initializePaths();
        }

        return $this->logFile;
    }

    public function getErrorFile(): string {
        if ($this->errorFile === null) {
            $this->initializePaths();
        }

        return $this->errorFile;
    }

    public function getStacktraceFile(): string {
        if ($this->stacktraceFile === null) {
            $this->initializePaths();
        }

        return $this->stacktraceFile;
    }
}
