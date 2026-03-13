<?php
/**
 * Settings admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Settings', 'plugins-onboard'); ?>
    </h1>

    <?php if ($success === 'settings_saved') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Settings saved successfully.', 'plugins-onboard'); ?></p>
    </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('save_settings'); ?>
        <input type="hidden" name="onboard_action" value="save_settings">

        <!-- General Settings -->
        <div class="onboard-section">
            <h2><?php esc_html_e('General Settings', 'plugins-onboard'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="admin_email"><?php esc_html_e('Admin Email', 'plugins-onboard'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="admin_email" id="admin_email" class="regular-text"
                            value="<?php echo esc_attr(isset($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email')); ?>">
                        <p class="description"><?php esc_html_e('Email address for IP approval notifications.', 'plugins-onboard'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Backup Settings -->
        <div class="onboard-section">
            <h2><?php esc_html_e('Backup Settings', 'plugins-onboard'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Auto Backup', 'plugins-onboard'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_backup_enabled" value="1"
                                <?php checked(!empty($settings['auto_backup_enabled'])); ?>>
                            <?php esc_html_e('Enable automatic backups before mutations', 'plugins-onboard'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snapshot_retention_count"><?php esc_html_e('Retention Count', 'plugins-onboard'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="snapshot_retention_count" id="snapshot_retention_count" class="small-text" min="1" max="100"
                            value="<?php echo esc_attr(isset($settings['snapshot_retention_count']) ? $settings['snapshot_retention_count'] : ONBOARD_SNAPSHOT_RETENTION_COUNT); ?>">
                        <p class="description"><?php esc_html_e('Number of snapshots to keep per plugin.', 'plugins-onboard'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Backup Triggers', 'plugins-onboard'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="backup_trigger_upload" value="1"
                                    <?php checked(!isset($settings['backup_trigger_upload']) || $settings['backup_trigger_upload']); ?>>
                                <?php esc_html_e('Before plugin upload', 'plugins-onboard'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="backup_trigger_enable" value="1"
                                    <?php checked(!isset($settings['backup_trigger_enable']) || $settings['backup_trigger_enable']); ?>>
                                <?php esc_html_e('Before plugin enable', 'plugins-onboard'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="backup_trigger_disable" value="1"
                                    <?php checked(!isset($settings['backup_trigger_disable']) || $settings['backup_trigger_disable']); ?>>
                                <?php esc_html_e('Before plugin disable', 'plugins-onboard'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="backup_trigger_delete" value="1"
                                    <?php checked(!isset($settings['backup_trigger_delete']) || $settings['backup_trigger_delete']); ?>>
                                <?php esc_html_e('Before plugin delete', 'plugins-onboard'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Security Settings -->
        <div class="onboard-section">
            <h2><?php esc_html_e('Security Settings', 'plugins-onboard'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Require HTTPS', 'plugins-onboard'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="require_https" value="1"
                                <?php checked(!empty($settings['require_https'])); ?>>
                            <?php esc_html_e('Require HTTPS for all API requests', 'plugins-onboard'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('IP Whitelist', 'plugins-onboard'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="ip_whitelist_enabled" value="1"
                                <?php checked(!isset($settings['ip_whitelist_enabled']) || $settings['ip_whitelist_enabled']); ?>>
                            <?php esc_html_e('Enable IP whitelist for mutation requests', 'plugins-onboard'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Auto-Approve IPs', 'plugins-onboard'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="ip_auto_approve" value="1"
                                <?php checked(!empty($settings['ip_auto_approve'])); ?>>
                            <?php esc_html_e('Automatically approve new IPs (not recommended)', 'plugins-onboard'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('When disabled, new IPs require admin approval.', 'plugins-onboard'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Logging Settings -->
        <div class="onboard-section">
            <h2><?php esc_html_e('Logging Settings', 'plugins-onboard'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="audit_log_retention_days"><?php esc_html_e('Log Retention', 'plugins-onboard'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="audit_log_retention_days" id="audit_log_retention_days" class="small-text" min="1" max="3650"
                            value="<?php echo esc_attr(isset($settings['audit_log_retention_days']) ? $settings['audit_log_retention_days'] : ONBOARD_AUDIT_LOG_RETENTION_DAYS); ?>">
                        <?php esc_html_e('days', 'plugins-onboard'); ?>
                        <p class="description"><?php esc_html_e('Number of days to keep audit logs.', 'plugins-onboard'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Token Settings (Read-only info) -->
        <div class="onboard-section">
            <h2><?php esc_html_e('Token Settings (Constants)', 'plugins-onboard'); ?></h2>
            <p class="description"><?php esc_html_e('These values are defined as constants and cannot be changed via this interface.', 'plugins-onboard'); ?></p>
            <table class="widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Access Token TTL', 'plugins-onboard'); ?></strong></td>
                        <td><?php echo ONBOARD_ACCESS_TOKEN_TTL; ?> <?php esc_html_e('seconds', 'plugins-onboard'); ?> (<?php echo ONBOARD_ACCESS_TOKEN_TTL / 3600; ?> <?php esc_html_e('hour(s)', 'plugins-onboard'); ?>)</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Refresh Token TTL', 'plugins-onboard'); ?></strong></td>
                        <td><?php echo ONBOARD_REFRESH_TOKEN_TTL; ?> <?php esc_html_e('seconds', 'plugins-onboard'); ?> (<?php echo ONBOARD_REFRESH_TOKEN_TTL / 86400; ?> <?php esc_html_e('day(s)', 'plugins-onboard'); ?>)</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Mutation Token TTL', 'plugins-onboard'); ?></strong></td>
                        <td><?php echo ONBOARD_MUTATION_TOKEN_TTL; ?> <?php esc_html_e('seconds', 'plugins-onboard'); ?> (<?php echo ONBOARD_MUTATION_TOKEN_TTL / 60; ?> <?php esc_html_e('minute(s)', 'plugins-onboard'); ?>)</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Auth Code TTL', 'plugins-onboard'); ?></strong></td>
                        <td><?php echo ONBOARD_AUTH_CODE_TTL; ?> <?php esc_html_e('seconds', 'plugins-onboard'); ?> (<?php echo ONBOARD_AUTH_CODE_TTL / 60; ?> <?php esc_html_e('minute(s)', 'plugins-onboard'); ?>)</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Rate Limit (Auth)', 'plugins-onboard'); ?></strong></td>
                        <td><?php echo ONBOARD_RATE_LIMIT_AUTH_REQUESTS; ?> <?php esc_html_e('requests/hour', 'plugins-onboard'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Rate Limit (Mutation)', 'plugins-onboard'); ?></strong></td>
                        <td><?php echo ONBOARD_RATE_LIMIT_MUTATION_REQUESTS; ?> <?php esc_html_e('requests/hour', 'plugins-onboard'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Settings', 'plugins-onboard'); ?>
            </button>
        </p>
    </form>
</div>
