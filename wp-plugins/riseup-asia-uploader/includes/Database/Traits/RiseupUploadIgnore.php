<?php
/**
 * RiseupUploadIgnore — interface for ignore-pattern providers used by FileCache.
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.31.1
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

interface RiseupUploadIgnore {

    /**
     * Determine whether a relative path should be ignored.
     */
    public function shouldIgnore(string $relativePath): bool;

    /**
     * Whether ignore patterns have been loaded.
     */
    public function isLoaded(): bool;
}
