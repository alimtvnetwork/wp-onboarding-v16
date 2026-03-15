<?php
/**
 * TableType — SQLite table name constants (PascalCase per database naming convention).
 *
 * @package RiseupAsia\Enums
 * @since   1.58.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

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

    // ── Cloud Storage ───────────────────────────────────────────────
    case CloudStorageAccounts  = 'CloudStorageAccounts';
    case CloudStorageSettings  = 'CloudStorageSettings';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isSnapshot(): bool     { return str_starts_with($this->value, 'Snapshot'); }
    public function isAgent(): bool        { return str_starts_with($this->value, 'Agent'); }
    public function isCloudStorage(): bool { return str_starts_with($this->value, 'CloudStorage'); }
}
