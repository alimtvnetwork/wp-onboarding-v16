<?php
/**
 * CapabilityEnum — WordPress Capability Constants
 *
 * Centralizes all WordPress capability strings used in permission checks.
 * Every current_user_can() call MUST reference a constant from this class.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress capability constants.
 *
 * Replaces magic strings like 'manage_options' and 'activate_plugins'
 * with self-documenting constants.
 */
class CapabilityEnum {

    /** Full site administration */
    public const MANAGE_OPTIONS   = 'manage_options';

    /** Activate/deactivate plugins */
    public const ACTIVATE_PLUGINS = 'activate_plugins';

    /** Publish posts */
    public const PUBLISH_POSTS    = 'publish_posts';

    /** Upload files/media */
    public const UPLOAD_FILES     = 'upload_files';

    /** Edit posts */
    public const EDIT_POSTS       = 'edit_posts';

    /** Delete plugins */
    public const DELETE_PLUGINS   = 'delete_plugins';

    /** Install plugins */
    public const INSTALL_PLUGINS  = 'install_plugins';

    /** Update plugins */
    public const UPDATE_PLUGINS   = 'update_plugins';
}
