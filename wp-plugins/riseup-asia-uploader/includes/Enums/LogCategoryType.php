<?php
/**
 * LogCategoryType — Transaction log category identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.2.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transaction log category values for logPluginAction() context.
 */
enum LogCategoryType: string
{
    case Snapshot = 'snapshot';
    case Agent    = 'agent';
    case Sync     = 'sync';
    case Plugin   = 'plugin';
    case Update   = 'update';
    case Post     = 'post';
    case Media    = 'media';
    case Auth     = 'auth';
    case Export   = 'export';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
}