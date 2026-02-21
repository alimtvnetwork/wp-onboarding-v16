<?php
/**
 * FilterKeyType — Standardized filter parameter keys for query operations.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

enum FilterKeyType: string
{
    case Status        = 'status';
    case Plugin        = 'plugin';
    case Action        = 'action';
    case User          = 'user';
    case TriggeredBy   = 'triggered_by';
    case UploadSource  = 'upload_source';
    case From          = 'from';
    case To            = 'to';
    case SourceMachine = 'source_machine';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
