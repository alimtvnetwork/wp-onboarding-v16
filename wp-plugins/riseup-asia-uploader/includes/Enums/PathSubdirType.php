<?php
/**
 * PathSubdirType — Plugin Subdirectory Path Fragments
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin subdirectory path fragments.
 */
enum PathSubdirType: string
{
    case Logs      = '/logs';
    case Temp      = '/temp';
    case Snapshots = '/snapshots';
    case Exports   = '/exports';
}
