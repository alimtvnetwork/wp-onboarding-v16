<?php
/**
 * ResponseKeyType — Standardized response array keys (PascalCase values).
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum ResponseKeyType: string
{
    case Success       = 'Success';
    case Error         = 'Error';
    case Message       = 'Message';
    case Slug          = 'Slug';
    case PluginSlug    = 'PluginSlug';
    case IsUpdate      = 'IsUpdate';
    case Activated     = 'Activated';
    case Deactivated   = 'Deactivated';
    case PluginVersion = 'PluginVersion';
    case TempFile      = 'TempFile';
    case Version       = 'Version';
    case Plugin        = 'Plugin';
    case Timestamp     = 'Timestamp';
    case PhpVersion    = 'PhpVersion';
    case WpVersion     = 'WpVersion';
    case ActivationError  = 'ActivationError';
    case RolledBack       = 'RolledBack';
    case PreviousVersion  = 'PreviousVersion';
    case RestoredVersion  = 'RestoredVersion';

    /** Log/diagnostic keys. */
    case InfoLog       = 'InfoLog';
    case ErrorLog      = 'ErrorLog';
    case StacktraceLog = 'StacktraceLog';
    case Exists        = 'Exists';
    case Content       = 'Content';
    case Lines         = 'Lines';
    case TotalLines    = 'TotalLines';
    case TotalSize     = 'TotalSize';
    case Truncated     = 'Truncated';
    case Path          = 'Path';
    case Settings      = 'Settings';
    case RequestedAt   = 'RequestedAt';

    /** Log-clearing token keys. */
    case ConfirmationRequired = 'ConfirmationRequired';
    case ConfirmEndpoint      = 'ConfirmEndpoint';
    case ExpiresIn            = 'ExpiresIn';

    /** Dedup registry keys. */
    case DedupRegistry = 'DedupRegistry';
    case EntryCount    = 'EntryCount';
    case Entries       = 'Entries';
    case FileSizeBytes = 'FileSizeBytes';

    /** Server/environment keys. */
    case UploadMaxFilesize = 'UploadMaxFilesize';
    case PostMaxSize       = 'PostMaxSize';
    case MemoryLimit       = 'MemoryLimit';
    case UploadMaxFilesizeBytes = 'UploadMaxFilesizeBytes';
    case PostMaxSizeBytes       = 'PostMaxSizeBytes';
    case Api            = 'Api';
    case SiteUrl        = 'SiteUrl';
    case DbAvailable    = 'DbAvailable';
    case ServerTime     = 'ServerTime';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
