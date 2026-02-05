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
        <?php esc_html_e('Configure API endpoints and authentication requirements.', 'riseup-asia-uploader'); ?>
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
