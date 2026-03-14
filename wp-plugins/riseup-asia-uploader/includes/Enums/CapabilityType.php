<?php
/**
 * CapabilityType — WordPress capability strings.
 *
 * @package RiseupAsia\Enums
 * @since   1.57.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum CapabilityType: string
{
    case ManageOptions   = 'manage_options';
    case ActivatePlugins = 'activate_plugins';
    case PublishPosts    = 'publish_posts';
    case UploadFiles     = 'upload_files';
    case EditPosts       = 'edit_posts';
    case DeletePlugins   = 'delete_plugins';
    case InstallPlugins  = 'install_plugins';
    case UpdatePlugins   = 'update_plugins';
    case SwitchThemes    = 'switch_themes';
    case ManageUsers     = 'manage_users';
    case ManageNetwork   = 'manage_network';
    case CreateUsers     = 'create_users';
    case EditUsers       = 'edit_users';
    case DeleteUsers     = 'delete_users';
    case ListUsers       = 'list_users';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }
}
