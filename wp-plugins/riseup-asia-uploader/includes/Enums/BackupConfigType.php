<?php
/**
 * BackupConfigType — Numeric defaults for plugin backup operations.
 *
 * @package RiseupAsia\Enums
 * @since   1.64.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum BackupConfigType: int
{
    /** Maximum number of backups retained per plugin. */
    case RetentionPerPlugin = 5;

    /** Maximum plugin size in MB for backup (skip if larger). */
    case MaxPluginSizeMb = 200;
}
