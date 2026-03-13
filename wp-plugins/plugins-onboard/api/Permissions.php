<?php
/**
 * Permissions helper class.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardPermissions
 *
 * Helper class for permission checks.
 */
class OnboardPermissions {

    /**
     * Check if current user can manage plugins.
     *
     * @return bool
     */
    public static function can_manage_plugins() {
        return current_user_can('activate_plugins');
    }

    /**
     * Check if current user can manage options.
     *
     * @return bool
     */
    public static function can_manage_options() {
        return current_user_can('manage_options');
    }

    /**
     * Check if current user can view audit logs.
     *
     * @return bool
     */
    public static function can_view_audit_logs() {
        return current_user_can('manage_options');
    }

    /**
     * Check if current user can clear audit logs.
     *
     * @return bool
     */
    public static function can_clear_audit_logs() {
        return current_user_can('manage_options');
    }

    /**
     * Check if current user can manage applications.
     *
     * @return bool
     */
    public static function can_manage_applications() {
        return current_user_can('manage_options');
    }

    /**
     * Check if current user can approve IP addresses.
     *
     * @return bool
     */
    public static function can_approve_ips() {
        return current_user_can('manage_options');
    }

    /**
     * Check if current user can manage backups.
     *
     * @return bool
     */
    public static function can_manage_backups() {
        return current_user_can('activate_plugins');
    }

    /**
     * Check if current user can run tests.
     *
     * @return bool
     */
    public static function can_run_tests() {
        return current_user_can('manage_options');
    }

    /**
     * Permission callback for admin pages.
     *
     * @return bool
     */
    public static function admin_permission_callback() {
        return self::can_manage_plugins();
    }
}
