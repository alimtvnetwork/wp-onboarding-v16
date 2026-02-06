<?php
/**
 * Admin Settings Page Template
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap riseup-admin">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e('Riseup Asia Uploader - Settings', 'riseup-asia-uploader'); ?>
    </h1>

    <p class="description">
        <?php esc_html_e('Configure API endpoints, authentication requirements, and auto-update settings.', 'riseup-asia-uploader'); ?>
    </p>

    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', 'riseup-asia-uploader'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('riseup_asia_settings_group'); ?>

        <!-- Plugin Info -->
        <div class="riseup-card">
            <h2><?php esc_html_e('Plugin Information', 'riseup-asia-uploader'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Version', 'riseup-asia-uploader'); ?></th>
                    <td><code><?php echo esc_html(RISEUP_VERSION); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('API Namespace', 'riseup-asia-uploader'); ?></th>
                    <td><code><?php echo esc_html(RISEUP_API_FULL_NAMESPACE); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('REST API Base', 'riseup-asia-uploader'); ?></th>
                    <td><code><?php echo esc_url(rest_url(RISEUP_API_FULL_NAMESPACE)); ?></code></td>
                </tr>
            </table>
        </div>

        <!-- Auto-Update Configuration -->
        <div class="riseup-card">
            <h2>
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Auto-Update Settings', 'riseup-asia-uploader'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Configure automatic updates with 301 redirect URL resolution. The master URL will be resolved through redirects and cached for faster subsequent checks.', 'riseup-asia-uploader'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="update_enabled"><?php esc_html_e('Enable Auto-Update', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   id="update_enabled"
                                   name="<?php echo esc_attr(Riseup_Update_Resolver::OPTION_NAME); ?>[enabled]" 
                                   value="1" 
                                   <?php checked(!empty($update_settings['enabled'])); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e('Enable automatic update checking via the configured master URL.', 'riseup-asia-uploader'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="master_url"><?php esc_html_e('Master Update URL', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               id="master_url"
                               name="<?php echo esc_attr(Riseup_Update_Resolver::OPTION_NAME); ?>[master_url]" 
                               value="<?php echo esc_attr($update_settings['master_url']); ?>" 
                               class="regular-text"
                               placeholder="https://updates.example.com/plugin">
                        <p class="description"><?php esc_html_e('The URL that will be resolved through 301 redirects to find the actual update endpoint.', 'riseup-asia-uploader'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cache_days"><?php esc_html_e('Cache Duration', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="cache_days" name="<?php echo esc_attr(Riseup_Update_Resolver::OPTION_NAME); ?>[cache_days]">
                            <option value="1" <?php selected($update_settings['cache_days'], 1); ?>>1 <?php esc_html_e('day', 'riseup-asia-uploader'); ?></option>
                            <option value="7" <?php selected($update_settings['cache_days'], 7); ?>>7 <?php esc_html_e('days', 'riseup-asia-uploader'); ?></option>
                            <option value="14" <?php selected($update_settings['cache_days'], 14); ?>>14 <?php esc_html_e('days', 'riseup-asia-uploader'); ?></option>
                            <option value="30" <?php selected($update_settings['cache_days'], 30); ?>>30 <?php esc_html_e('days', 'riseup-asia-uploader'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('How long to cache the resolved URL before re-resolving through redirects.', 'riseup-asia-uploader'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Resolved URL (Cached)', 'riseup-asia-uploader'); ?></th>
                    <td>
                        <?php if (!empty($update_settings['resolved_url'])): ?>
                            <code id="resolved_url_display"><?php echo esc_html($update_settings['resolved_url']); ?></code>
                            <br>
                            <small class="text-muted">
                                <?php 
                                if (!empty($update_settings['resolved_at'])) {
                                    printf(
                                        esc_html__('Cached on: %s', 'riseup-asia-uploader'),
                                        esc_html($update_settings['resolved_at'])
                                    );
                                }
                                ?>
                            </small>
                        <?php else: ?>
                            <em><?php esc_html_e('Not resolved yet', 'riseup-asia-uploader'); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Last Check', 'riseup-asia-uploader'); ?></th>
                    <td>
                        <?php if (!empty($update_settings['last_check'])): ?>
                            <?php echo esc_html($update_settings['last_check']); ?>
                        <?php else: ?>
                            <em><?php esc_html_e('Never', 'riseup-asia-uploader'); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($update_settings['last_error'])): ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Last Error', 'riseup-asia-uploader'); ?></th>
                    <td>
                        <span class="riseup-error-text"><?php echo esc_html($update_settings['last_error']); ?></span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($update_settings['new_version'])): ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Available Version', 'riseup-asia-uploader'); ?></th>
                    <td>
                        <strong><?php echo esc_html($update_settings['new_version']); ?></strong>
                        <?php if (version_compare($update_settings['new_version'], RISEUP_VERSION, '>')): ?>
                            <span class="dashicons dashicons-arrow-up-alt" style="color: #46b450;"></span>
                            <span style="color: #46b450;"><?php esc_html_e('Update available!', 'riseup-asia-uploader'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Actions', 'riseup-asia-uploader'); ?></th>
                    <td>
                        <button type="button" id="btn_test_connection" class="button button-secondary">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Test Connection', 'riseup-asia-uploader'); ?>
                        </button>
                        <button type="button" id="btn_clear_cache" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear Cache', 'riseup-asia-uploader'); ?>
                        </button>
                        <button type="button" id="btn_check_updates" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Check Now', 'riseup-asia-uploader'); ?>
                        </button>
                        <span id="update_action_status" style="margin-left: 10px;"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Endpoint Configuration -->
        <div class="riseup-card">
            <h2><?php esc_html_e('API Endpoints Configuration', 'riseup-asia-uploader'); ?></h2>
            <p class="description">
                <?php esc_html_e('Enable or disable specific API endpoints and configure authentication requirements.', 'riseup-asia-uploader'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped riseup-endpoints-table">
                <thead>
                    <tr>
                        <th class="column-endpoint"><?php esc_html_e('Endpoint', 'riseup-asia-uploader'); ?></th>
                        <th class="column-description"><?php esc_html_e('Description', 'riseup-asia-uploader'); ?></th>
                        <th class="column-enabled"><?php esc_html_e('Enabled', 'riseup-asia-uploader'); ?></th>
                        <th class="column-auth"><?php esc_html_e('Auth Required', 'riseup-asia-uploader'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($endpoints_meta as $endpoint => $meta): ?>
                        <tr>
                            <td class="column-endpoint">
                                <strong><?php echo esc_html($meta['label']); ?></strong>
                                <br>
                                <code class="endpoint-path">/<?php echo esc_html($endpoint); ?></code>
                            </td>
                            <td class="column-description">
                                <?php echo esc_html($meta['desc']); ?>
                            </td>
                            <td class="column-enabled">
                                <label class="toggle-switch">
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr(Riseup_Admin::OPTION_NAME); ?>[endpoints][<?php echo esc_attr($endpoint); ?>][enabled]" 
                                           value="1" 
                                           <?php checked(!empty($settings['endpoints'][$endpoint]['enabled'])); ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="column-auth">
                                <label class="toggle-switch">
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr(Riseup_Admin::OPTION_NAME); ?>[endpoints][<?php echo esc_attr($endpoint); ?>][auth_required]" 
                                           value="1" 
                                           <?php checked(!empty($settings['endpoints'][$endpoint]['auth_required'])); ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="riseup-warning">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Warning: Disabling authentication can expose your site to unauthorized access. Only disable for development/testing purposes.', 'riseup-asia-uploader'); ?>
            </p>
        </div>

        <?php submit_button(__('Save Settings', 'riseup-asia-uploader')); ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var ajaxNonce = '<?php echo wp_create_nonce('riseup_admin_nonce'); ?>';
    var $status = $('#update_action_status');

    function showStatus(message, isError) {
        $status.html(message).css('color', isError ? '#dc3232' : '#46b450');
        setTimeout(function() { $status.fadeOut(); }, 5000);
        $status.show();
    }

    $('#btn_test_connection').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-yes-alt').addClass('dashicons-update spin');
        
        $.post(ajaxurl, {
            action: 'riseup_test_update_connection',
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showStatus('✓ ' + response.data.message + (response.data.resolved_url ? ' → ' + response.data.resolved_url : ''), false);
                if (response.data.resolved_url) {
                    $('#resolved_url_display').text(response.data.resolved_url);
                }
            } else {
                showStatus('✗ ' + (response.data.message || 'Connection failed'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-yes-alt');
        });
    });

    $('#btn_clear_cache').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'riseup_clear_update_cache',
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                showStatus('✓ ' + response.data.message, false);
                $('#resolved_url_display').text('');
            } else {
                showStatus('✗ ' + (response.data.message || 'Failed to clear cache'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    $('#btn_check_updates').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.post(ajaxurl, {
            action: 'riseup_check_for_updates',
            nonce: ajaxNonce
        }, function(response) {
            if (response.success) {
                var msg = response.data.message;
                if (response.data.update_info && response.data.update_info.version) {
                    msg += ' (v' + response.data.update_info.version + ')';
                }
                showStatus('✓ ' + msg, false);
            } else {
                showStatus('✗ ' + (response.data.message || 'Update check failed'), true);
            }
        }).fail(function() {
            showStatus('✗ Request failed', true);
        }).always(function() {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
        });
    });
});
</script>

<style>
.dashicons.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    100% { transform: rotate(360deg); }
}
.riseup-error-text {
    color: #dc3232;
}
</style>
