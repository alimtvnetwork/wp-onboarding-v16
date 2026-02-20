<?php
/**
 * FilterKeyType — Standardized filter parameter keys for query operations.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Filter parameter keys used in query building and API filtering.
 */
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
}
