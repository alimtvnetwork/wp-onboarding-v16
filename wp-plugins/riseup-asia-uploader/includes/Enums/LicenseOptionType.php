<?php
/**
 * LicenseOptionType — WordPress option keys for license data.
 *
 * @package RiseupAsia\Enums
 * @since   2.7.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum LicenseOptionType: string
{
    case LicenseKey      = 'riseup_license_key';
    case LicenseStatus   = 'riseup_license_status';
    case LicenseData     = 'riseup_license_data';
    case LicenseCheckedAt = 'riseup_license_checked_at';

    public function isEqual(self $other): bool { return $this === $other; }
}
