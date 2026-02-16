<?php
/**
 * Riseup Asia Uploader - Plugin Constants
 *
 * This file is now empty — all legacy constants have been migrated
 * to typed enums in includes/Enums/:
 *   - SnapshotConfigType  (batch size, retention, worker pool, etc.)
 *   - UpdateConfigType    (cache days, max redirects)
 *   - PaginationConfigType (default/max limits, log retrieval)
 *   - InitHelpers::DB_WAL_MODE (class constant)
 *
 * Retained as a require target for backward compatibility.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 * @see     SnapshotConfigType, UpdateConfigType, PaginationConfigType
 */

if (!defined('ABSPATH')) {
    exit;
}