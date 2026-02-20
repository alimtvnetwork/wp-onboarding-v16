<?php
/**
 * ResponseKeyType — Standardized response array keys.
 *
 * @package RiseupAsia\Enums
 */

namespace RiseupAsia\Enums;

/**
 * Keys used in structured response arrays returned by services.
 */
enum ResponseKeyType: string
{
    case Total   = 'total';
    case Agents  = 'agents';
    case Actions = 'actions';
    case Sql     = 'sql';
    case Params  = 'params';
    case Sets    = 'sets';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
}
