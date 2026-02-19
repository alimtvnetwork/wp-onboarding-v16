<?php
/**
 * SnapshotConfigType — Numeric defaults for snapshot operations.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SnapshotConfigType: int {
    case BatchSize             = 1000;
    case MaxSizeMb             = 500;
    case RetentionDaysDefault  = 30;
    case RetentionCountDefault = 10;
    case WorkerPoolMin         = 1;
    case WorkerPoolMax         = 10;
    case WorkerPoolDefault     = 5;
    case StuckHours            = 24;

    /** Default snapshot title prefix. */
    public const DefaultTitle = 'Snapshot';

    /** Root database filename used in per-table snapshots. */
    public const RootDbFilename = 'a-root.db';
}