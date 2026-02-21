<?php
/**
 * TableType — SQLite Table Name Constants
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SQLite table names used across the plugin.
 *
 * Organized by domain: Core, Agent, Snapshot, Sync, Error, Cache.
 * All values are PascalCase per the database naming convention.
 */
enum TableType: string
{
    // ── Core ─────────────────────────────────────────────────────────
    case Transactions       = 'Transactions';

    // ── Agent ────────────────────────────────────────────────────────
    case AgentSites         = 'AgentSites';
    case AgentActions       = 'AgentActions';

    // ── Snapshot ─────────────────────────────────────────────────────
    case Snapshots          = 'Snapshots';
    case SnapshotProgress   = 'SnapshotProgress';
    case SnapshotJobs       = 'SnapshotJobs';
    case SnapshotSettings   = 'SnapshotSettings';
    case SnapshotExports    = 'SnapshotExports';

    // ── Sync ─────────────────────────────────────────────────────────
    case FileCache          = 'FileCache';

    // ── Cache ────────────────────────────────────────────────────────
    case RemotePluginsCache = 'RemotePluginsCache';

    // ── Error ────────────────────────────────────────────────────────
    case ErrorSessions      = 'ErrorSessions';
    case FlashState         = 'FlashState';

    /** Check if this enum case equals the given case. */
    public function isEqual(self $other): bool
    {
        return $this === $other;
    }

    /** Check if this enum case differs from the given case. */
    public function isOtherThan(self $other): bool
    {
        return $this !== $other;
    }

    /** Check if the receiver matches any of the given cases. */
    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }

    /** Check if this table belongs to the snapshot domain. */
    public function isSnapshot(): bool
    {
        return str_starts_with($this->value, 'Snapshot');
    }

    /** Check if this table belongs to the agent domain. */
    public function isAgent(): bool
    {
        return str_starts_with($this->value, 'Agent');
    }
}
