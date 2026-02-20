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
    /** Envelope keys — present in most response arrays. */
    case Success  = 'success';
    case Error    = 'error';
    case Message  = 'message';
    case Data     = 'data';
    case Code     = 'code';
    case Valid    = 'valid';

    /** Domain collection keys. */
    case Total     = 'total';
    case Agents    = 'agents';
    case Actions   = 'actions';
    case Logs      = 'logs';
    case Snapshots = 'snapshots';
    case Sql       = 'sql';
    case Params    = 'params';
    case Sets      = 'sets';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
}
