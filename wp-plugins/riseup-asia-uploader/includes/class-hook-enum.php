<?php
/**
 * HookEnum — WordPress Hook Name Constants
 *
 * Centralizes all WordPress action and filter hook names.
 * Every add_action() or add_filter() call MUST reference a constant
 * from this class instead of a string literal.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress hook name constants.
 *
 * When WordPress adds new hooks your plugin needs, add the constant
 * here FIRST, then use it in the registration call.
 */
class HookEnum {

    // ── Core Lifecycle ──────────────────────────────────────────

    /** Fires after WordPress finishes loading but before headers are sent */
    public const INIT             = 'init';

    /** Fires after all active plugins are loaded */
    public const PLUGINS_LOADED   = 'plugins_loaded';

    /** Fires when the REST API is fully initialized */
    public const REST_API_INIT    = 'rest_api_init';

    /** Fires at the beginning of every admin page */
    public const ADMIN_INIT       = 'admin_init';

    /** Fires just before PHP shuts down */
    public const SHUTDOWN         = 'shutdown';

    // ── Plugin Lifecycle ────────────────────────────────────────

    /** Fires after a plugin is activated */
    public const ACTIVATED_PLUGIN   = 'activated_plugin';

    /** Fires after a plugin is deactivated */
    public const DEACTIVATED_PLUGIN = 'deactivated_plugin';

    /** Fires after a plugin is deleted */
    public const DELETED_PLUGIN     = 'deleted_plugin';

    // ── Admin UI ────────────────────────────────────────────────

    /** Fires after core admin notices are printed */
    public const ADMIN_NOTICES    = 'admin_notices';

    /** Fires to enqueue admin scripts and styles */
    public const ADMIN_ENQUEUE    = 'admin_enqueue_scripts';

    /** Fires to register admin menu pages */
    public const ADMIN_MENU       = 'admin_menu';

    // ── AJAX ────────────────────────────────────────────────────

    /** Prefix for authenticated AJAX hooks: HookEnum::WP_AJAX_PREFIX . 'my_action' */
    public const WP_AJAX_PREFIX   = 'wp_ajax_';

    /** Prefix for unauthenticated AJAX hooks */
    public const WP_AJAX_NOPRIV_PREFIX = 'wp_ajax_nopriv_';

    // ── Filters ─────────────────────────────────────────────────

    /** Filters the REST API response before sending */
    public const REST_POST_DISPATCH = 'rest_post_dispatch';

    /** Filters the plugin action links on the Plugins page */
    public const PLUGIN_ACTION_LINKS = 'plugin_action_links';

    /** Filters the update_plugins site transient before it is set */
    public const PRE_SET_SITE_TRANSIENT_UPDATE_PLUGINS = 'pre_set_site_transient_update_plugins';

    /** Filters plugin information for the "View Details" modal */
    public const PLUGINS_API = 'plugins_api';

    /** Filters custom cron schedule intervals */
    public const CRON_SCHEDULES = 'cron_schedules';
}
