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
    private function initialize_paths() {
        if ($this->initialized) {
            return true;
        }

        $this->base_dir = RiseupInitHelpers::resolveBaseDir();
        $this->logs_dir        = rtrim($this->base_dir, '/') . '/' . LOGS_SUBDIR;
        $this->log_file        = $this->logs_dir . '/' . LOG_FILENAME;
        $this->error_file      = $this->logs_dir . '/' . ERROR_LOG_FILENAME;
        $this->stacktrace_file = $this->logs_dir . '/' . STACKTRACE_FILENAME;

        return $this->ensure_directories();
    }

    /**
     * Ensure log directories exist.
     *
     * @return bool True if successful.
     */
    private function ensure_directories() {
        if (!RiseupInitHelpers::ensureDirNative($this->base_dir, true)) {
            error_log('[Riseup Asia] Failed to create base directory: ' . $this->base_dir);
            return false;
        }

        if (!RiseupInitHelpers::ensureDirNative($this->logs_dir, false)) {
            error_log('[Riseup Asia] Failed to create logs directory: ' . $this->logs_dir);
            return false;
        }

        $this->initialized = true;
        return true;
    }

    /** @return string */
    public function get_base_dir() {
        if ($this->base_dir === null) {
            $this->initialize_paths();
        }
        return $this->base_dir;
    }

    /** @return string */
    public function get_logs_dir() {
        if ($this->logs_dir === null) {
            $this->initialize_paths();
        }
        return $this->logs_dir;
    }

    /** @return string */
    public function get_log_file() {
        if ($this->log_file === null) {
            $this->initialize_paths();
        }
        return $this->log_file;
    }

    /** @return string */
    public function get_error_file() {
        if ($this->error_file === null) {
            $this->initialize_paths();
        }
        return $this->error_file;
    }

    /** @return string */
    public function get_stacktrace_file() {
        if ($this->stacktrace_file === null) {
            $this->initialize_paths();
        }
        return $this->stacktrace_file;
    }
}
