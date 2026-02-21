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
 * Organized by domain: Core, Agent, Snapshot, Sync.
 */
enum TableType: string
{
    // ── Core ─────────────────────────────────────────────────────────
    case Transactions     = 'transactions';

    // ── Agent ────────────────────────────────────────────────────────
    case AgentSites       = 'agent_sites';
    case AgentActions     = 'agent_actions';

    // ── Snapshot ─────────────────────────────────────────────────────
    case Snapshots        = 'snapshots';
    case SnapshotProgress = 'snapshot_progress';
    case SnapshotJobs     = 'snapshot_jobs';
    case SnapshotSettings = 'snapshot_settings';
    case SnapshotExports  = 'snapshot_exports';

    // ── Sync ─────────────────────────────────────────────────────────
    case FileCache        = 'file_cache';

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
        return str_starts_with($this->value, 'snapshot');
    }

    /** Check if this table belongs to the agent domain. */
    public function isAgent(): bool
    {
        return str_starts_with($this->value, 'agent');
    }
}