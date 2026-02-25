<?php
/**
 * UploadSourceType — Upload origin identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum UploadSourceType: string
{
    case Script  = 'Script';
    case RestApi = 'RestApi';
    case AdminUi = 'AdminUi';
    case WpCli   = 'WpCli';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public static function validValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $source): bool
    {
        return self::tryFrom($source) !== null;
    }
}
