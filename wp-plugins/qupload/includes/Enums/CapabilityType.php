<?php
/**
 * CapabilityType — WordPress capability strings.
 *
 * @package QUpload\Enums
 * @since   1.0.0
 */

namespace QUpload\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum CapabilityType: string
{
    case ActivatePlugins = 'activate_plugins';
    case ManageOptions   = 'manage_options';
}
