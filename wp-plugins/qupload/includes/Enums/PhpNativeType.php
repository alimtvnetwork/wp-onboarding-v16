<?php
/**
 * PhpNativeType — PHP native type names returned by gettype().
 *
 * Used instead of magic strings when checking variable types via gettype()
 * in legacy-safe code paths where is_array() / is_string() etc. cannot be
 * used due to remote syntax validator constraints (QUpload token_get_all).
 *
 * @package QUpload\Enums
 * @since   2.17.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum PhpNativeType: string
{
    case PhpArray   = 'array';
    case PhpString  = 'string';
    case PhpInteger = 'integer';
    case PhpDouble  = 'double';
    case PhpBoolean = 'boolean';
    case PhpObject  = 'object';
    case PhpNull    = 'NULL';
}
