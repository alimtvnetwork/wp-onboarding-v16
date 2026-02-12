<?php
/**
 * PathConst — File Name Constants
 *
 * NOT a backed enum because its values are path fragments composed with
 * directory methods — they don't form a discrete, finite set of choices.
 *
 * Path accessors in RiseupPathUtils compose: directory method + PathConst::CONSTANT.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * File name constants for all plugin data files.
 */
final class PathConst
{
    // ── Subdirectories ─────────────────────────────────────────
    public const LOGS_SUBDIR      = '/logs';
    public const TEMP_SUBDIR      = '/temp';
    public const SNAPSHOTS_SUBDIR = '/snapshots';
    public const EXPORTS_SUBDIR   = '/exports';

    // ── Databases ───────────────────────────────────────────────
    public const ROOT_DB     = '/a-root.db';
    public const ACTIVITY_DB = '/activity.db';
    public const SNAPSHOT_DB = '/snapshots.db';
    public const PLUGIN_DB   = '/riseup-asia-uploader.db';

    // ── Log Files ───────────────────────────────────────────────
    public const LOG_FILE        = '/log.txt';
    public const FATAL_ERROR_LOG = '/fatal-errors.log';
    public const STACKTRACE_FILE = '/stacktrace.txt';
    public const ERROR_FILE      = '/error.txt';

    // ── Config Files ────────────────────────────────────────────
    public const DETECTION_FILE = '/wp-plugin-detected.json';
}
