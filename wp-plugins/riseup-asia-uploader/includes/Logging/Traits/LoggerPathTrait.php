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

trait LoggerPathTrait {

    /**
     * Initialize log file paths (lazy initialization).
     *
     * @return bool True if successful.
     */
    private function initializePaths() {
        if ($this->isInitialized) {
            return true;
        }

        $this->baseDir = RiseupInitHelpers::resolveBaseDir();
        $this->logsDir        = rtrim($this->baseDir, '/') . '/' . LOGS_SUBDIR;
        $this->logFile        = $this->logsDir . '/' . LOG_FILENAME;
        $this->errorFile      = $this->logsDir . '/' . ERROR_LOG_FILENAME;
        $this->stacktraceFile = $this->logsDir . '/' . STACKTRACE_FILENAME;

        return $this->ensureDirectories();
    }

    /**
     * Ensure log directories exist.
     *
     * @return bool True if successful.
     */
    private function ensureDirectories() {
        if (!RiseupInitHelpers::ensureDirNative($this->baseDir, true)) {
            error_log('[Riseup Asia] Failed to create base directory: ' . $this->baseDir);
            return false;
        }

        if (!RiseupInitHelpers::ensureDirNative($this->logsDir, false)) {
            error_log('[Riseup Asia] Failed to create logs directory: ' . $this->logsDir);
            return false;
        }

        $this->isInitialized = true;
        return true;
    }

    /** @return string */
    public function getBaseDir() {
        if ($this->baseDir === null) {
            $this->initializePaths();
        }
        return $this->baseDir;
    }

    /** @return string */
    public function getLogsDir() {
        if ($this->logsDir === null) {
            $this->initializePaths();
        }
        return $this->logsDir;
    }

    /** @return string */
    public function getLogFile() {
        if ($this->logFile === null) {
            $this->initializePaths();
        }
        return $this->logFile;
    }

    /** @return string */
    public function getErrorFile() {
        if ($this->errorFile === null) {
            $this->initializePaths();
        }
        return $this->errorFile;
    }

    /** @return string */
    public function getStacktraceFile() {
        if ($this->stacktraceFile === null) {
            $this->initializePaths();
        }
        return $this->stacktraceFile;
    }
}
