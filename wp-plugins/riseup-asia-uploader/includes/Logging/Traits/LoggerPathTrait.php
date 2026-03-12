<?php
/**
 * Logger Path Trait — Path initialization, directory creation, and path accessors.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Logging\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PathSubdirType;
use RiseupAsia\Enums\PathLogFileType;
use RiseupAsia\Helpers\InitHelpers;

trait LoggerPathTrait {
    /** Initialize log file paths (lazy initialization). */
    private function initializePaths(): bool {
        if ($this->isInitialized) {
            return true;
        }

        $this->baseDir = InitHelpers::resolveBaseDir();
        $this->logsDir        = rtrim($this->baseDir, '/') . PathSubdirType::Logs->value;
        $this->logFile        = $this->logsDir . PathLogFileType::Log->value;
        $this->errorFile      = $this->logsDir . PathLogFileType::Error->value;
        $this->stacktraceFile = $this->logsDir . PathLogFileType::Stacktrace->value;

        return $this->ensureDirectories();
    }

    /** Ensure log directories exist. */
    private function ensureDirectories(): bool {
        $isBaseDirFailed = (InitHelpers::makeDirectoryNative($this->baseDir, true) === false);

        if ($isBaseDirFailed) {
            InitHelpers::errorLogWithPrefix('Failed to create base directory: ' . $this->baseDir);

            return false;
        }

        $isLogsDirFailed = (InitHelpers::makeDirectoryNative($this->logsDir, false) === false);

        if ($isLogsDirFailed) {
            InitHelpers::errorLogWithPrefix('Failed to create logs directory: ' . $this->logsDir);

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

    /**
     * Clear all log files (log, error, stacktrace).
     * Used during version updates to start with a clean slate.
     */
    public function clearAllLogFiles(): void {
        if ($this->isInitialized === false) {
            $this->initializePaths();
        }

        $files = array($this->logFile, $this->errorFile, $this->stacktraceFile);

        foreach ($files as $file) {
            if ($file === null) {
                continue;
            }

            $isFileExists = file_exists($file);

            if ($isFileExists === false) {
                continue;
            }

            $isDeleteFailed = (unlink($file) === false);

            if ($isDeleteFailed) {
                InitHelpers::errorLogWithPrefix('Failed to clear log file during version update: ' . $file);
            }
        }

        $this->dedupHashes = array();
    }
}
