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
use RiseupAsia\Helpers\PathHelper;

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
     * Collect known log files plus any extra files in the logs directory.
     *
     * @return array<int, string>
     */
    private function collectLogFiles(): array {
        $knownFiles = array(
            $this->logFile,
            $this->errorFile,
            $this->stacktraceFile,
            $this->logsDir . PathLogFileType::FatalError->value,
        );

        $directoryFiles = $this->collectDirectoryLogFiles();
        $allFiles = array_merge($knownFiles, $directoryFiles);
        $filteredFiles = array_filter($allFiles, fn($file) => is_string($file) && $file !== '');

        return array_values(array_unique($filteredFiles));
    }

    /**
     * @return array<int, string>
     */
    private function collectDirectoryLogFiles(): array {
        $isLogsDirMissing = ($this->logsDir === null || $this->logsDir === '' || is_dir($this->logsDir) === false);

        if ($isLogsDirMissing) {
            return array();
        }

        $entries = @scandir($this->logsDir);
        $isReadFailed = ($entries === false);

        if ($isReadFailed) {
            InitHelpers::errorLogWithPrefix('Failed to read logs directory: ' . $this->logsDir);

            return array();
        }

        $files = array();

        foreach ($entries as $entry) {
            $isDotEntry = ($entry === '.' || $entry === '..');

            if ($isDotEntry) {
                continue;
            }

            $candidatePath = $this->logsDir . '/' . $entry;
            $isRegularFile = is_file($candidatePath);

            if ($isRegularFile) {
                $files[] = $candidatePath;
            }
        }

        return $files;
    }

    /** Delete a single log file from disk with post-delete verification. */
    private function clearLogFile(string $filePath): bool {
        clearstatcache(true, $filePath);
        $isFileMissing = (file_exists($filePath) === false);

        if ($isFileMissing) {
            return true;
        }

        $resolvedPath = realpath($filePath);
        $targetPath = ($resolvedPath === false) ? $filePath : $resolvedPath;
        $isDeleted = PathHelper::deleteFile($targetPath);

        clearstatcache(true, $targetPath);
        $isStillExists = file_exists($targetPath);

        if ($isDeleted && $isStillExists === false) {
            return true;
        }

        $error = error_get_last();
        $errorSuffix = ($error && isset($error['message'])) ? ' | ' . $error['message'] : '';
        InitHelpers::errorLogWithPrefix('Failed to clear log file: ' . $targetPath . $errorSuffix);

        return false;
    }

    /**
     * Clear all log files from disk (log/error/stacktrace/fatal and discovered files).
     *
     * @return array{deleted: array<int, string>, failed: array<int, string>}
     */
    public function clearAllLogFiles(): array {
        $isInitFailed = ($this->isInitialized === false && $this->initializePaths() === false);

        if ($isInitFailed) {
            return array('deleted' => array(), 'failed' => array());
        }

        $files = $this->collectLogFiles();
        $deletedFiles = array();
        $failedFiles = array();

        foreach ($files as $file) {
            $isDeleted = $this->clearLogFile($file);

            if ($isDeleted) {
                $deletedFiles[] = $file;
            } else {
                $failedFiles[] = $file;
            }
        }

        $this->dedupHashes = array();

        return array('deleted' => $deletedFiles, 'failed' => $failedFiles);
    }
}
