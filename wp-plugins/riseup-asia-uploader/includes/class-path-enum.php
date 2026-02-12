<?php
/**
 * PathEnum — File Name Constants
 *
 * Centralizes all file name segments that are appended to directory paths.
 * The enum holds the filename portion only — directory resolution is
 * handled by RiseupPathUtils.
 *
 * A typed accessor in RiseupPathUtils composes: get_data_dir() + PathEnum::CONSTANT.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * File name constants for all plugin data files.
 *
 * Every file the plugin reads or writes MUST have an entry here.
 * Path accessors in RiseupPathUtils compose: directory method + PathEnum::CONSTANT.
 *
 * WHY: If a filename changes (e.g., 'a-root.db' → 'primary.db'), you update
 * ONE constant here. Every accessor automatically picks up the change.
 */
class PathEnum {

    // ── Subdirectories ─────────────────────────────────────────

    /** Logs subdirectory name */
    public const LOGS_SUBDIR     = '/logs';

    /** Temp working directory name */
    public const TEMP_SUBDIR     = '/temp';

    /** Snapshots subdirectory name */
    public const SNAPSHOTS_SUBDIR = '/snapshots';

    /** Exports subdirectory within snapshots folder */
    public const EXPORTS_SUBDIR  = '/exports';

    // ── Databases ───────────────────────────────────────────────

    /** Root SQLite database file */
    public const ROOT_DB         = '/a-root.db';

    /** Activity/audit log database */
    public const ACTIVITY_DB     = '/activity.db';

    /** Snapshot tracking database */
    public const SNAPSHOT_DB     = '/snapshots.db';

    /** Main plugin database (riseup-asia-uploader) */
    public const PLUGIN_DB       = '/riseup-asia-uploader.db';

    // ── Log Files ───────────────────────────────────────────────

    /** General diagnostic log */
    public const LOG_FILE        = '/log.txt';

    /** Fatal PHP error log (written by shutdown handler) */
    public const FATAL_ERROR_LOG = '/fatal-errors.log';

    /** Raw PHP stack trace dump */
    public const STACKTRACE_FILE = '/stacktrace.txt';

    /** Structured error entries */
    public const ERROR_FILE      = '/error.txt';

    // ── Config Files ────────────────────────────────────────────

    /** Plugin detection marker */
    public const DETECTION_FILE  = '/wp-plugin-detected.json';
}
