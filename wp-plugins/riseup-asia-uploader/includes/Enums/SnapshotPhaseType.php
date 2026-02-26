<?php
/**
 * SnapshotPhaseType — Typed phase/step identifiers for snapshot operations.
 *
 * @package RiseupAsia\Enums
 * @since   2.1.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum SnapshotPhaseType: string
{
    case TableExport               = 'TableExport';
    case AsyncOrchestration        = 'AsyncOrchestration';
    case SyncOrchestration         = 'SyncOrchestration';
    case IncrementalOrchestration  = 'IncrementalOrchestration';
    case IncrementalLookup         = 'IncrementalLookup';
    case FullBackup                = 'FullBackup';
    case IncrementalBackup         = 'IncrementalBackup';
    case ExportPertable            = 'ExportPertable';
    case SnapshotCleanup           = 'SnapshotCleanup';
    case SnapshotProgress          = 'SnapshotProgress';
    case Initiated                 = 'Initiated';
    case Complete                  = 'Complete';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
}
