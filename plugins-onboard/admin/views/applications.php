<?php
/**
 * Applications admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Applications', 'plugins-onboard'); ?>
    </h1>

    <?php if ($success) : ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <?php
            switch ($success) {
                case 'app_created':
                    esc_html_e('Application created successfully.', 'plugins-onboard');
                    break;
                case 'app_deleted':
                    esc_html_e('Application deleted successfully.', 'plugins-onboard');
                    break;
                case 'ip_approved':
                    esc_html_e('IP address approved successfully.', 'plugins-onboard');
                    break;
                case 'ip_rejected':
                    esc_html_e('IP address rejected.', 'plugins-onboard');
                    break;
                default:
                    esc_html_e('Operation completed successfully.', 'plugins-onboard');
            }
            ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if ($error) : ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($error); ?></p>
    </div>
    <?php endif; ?>

    <!-- New App Credentials (shown once after creation) -->
    <?php if ($new_app) : ?>
    <div class="notice notice-warning">
        <h3><?php esc_html_e('New Application Credentials', 'plugins-onboard'); ?></h3>
        <p><strong><?php esc_html_e('IMPORTANT: Save these credentials now. The client secret will not be shown again!', 'plugins-onboard'); ?></strong></p>
        <table class="widefat" style="max-width: 600px;">
            <tr>
                <td><strong><?php esc_html_e('App Name', 'plugins-onboard'); ?></strong></td>
                <td><?php echo esc_html($new_app['app_name']); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Client ID', 'plugins-onboard'); ?></strong></td>
                <td><code><?php echo esc_html($new_app['client_id']); ?></code></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e('Client Secret', 'plugins-onboard'); ?></strong></td>
                <td><code style="background: #fff3cd; padding: 5px;"><?php echo esc_html($new_app['client_secret']); ?></code></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Pending IP Approvals -->
    <?php if (!empty($pending_approvals)) : ?>
    <div class="onboard-section">
        <h2>
            <span class="dashicons dashicons-warning" style="color: orange;"></span>
            <?php esc_html_e('Pending IP Approvals', 'plugins-onboard'); ?>
            <span class="count">(<?php echo count($pending_approvals); ?>)</span>
        </h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Application', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('IP Address', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Requested', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Expires', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Actions', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_approvals as $approval) : ?>
                <tr>
                    <td><strong><?php echo esc_html($approval['app_name']); ?></strong></td>
                    <td><code><?php echo esc_html($approval['ip_address']); ?></code></td>
                    <td><?php echo esc_html(onboard_format_date($approval['requested_at'])); ?></td>
                    <td><?php echo esc_html(onboard_format_date($approval['expires_at'])); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=plugins-onboard-applications&action=approve_ip&approval_id=' . $approval['approval_id']); ?>" class="button button-primary button-small">
                            <?php esc_html_e('Approve', 'plugins-onboard'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=plugins-onboard-applications&action=reject_ip&approval_id=' . $approval['approval_id']); ?>" class="button button-small">
                            <?php esc_html_e('Reject', 'plugins-onboard'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Create New Application -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Create New Application', 'plugins-onboard'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('create_app'); ?>
            <input type="hidden" name="onboard_action" value="create_app">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="app_name"><?php esc_html_e('Application Name', 'plugins-onboard'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="app_name" id="app_name" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="description"><?php esc_html_e('Description', 'plugins-onboard'); ?></label>
                    </th>
                    <td>
                        <textarea name="description" id="description" class="large-text" rows="3"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="redirect_uri"><?php esc_html_e('Redirect URI', 'plugins-onboard'); ?> *</label>
                    </th>
                    <td>
                        <input type="url" name="redirect_uri" id="redirect_uri" class="regular-text" required placeholder="https://example.com/callback">
                        <p class="description"><?php esc_html_e('OAuth callback URL for your application.', 'plugins-onboard'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Create Application', 'plugins-onboard'); ?>
                </button>
            </p>
        </form>
    </div>

    <!-- Registered Applications -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Registered Applications', 'plugins-onboard'); ?></h2>
        <?php if (empty($applications)) : ?>
            <p><?php esc_html_e('No applications registered yet.', 'plugins-onboard'); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;"><?php esc_html_e('Name', 'plugins-onboard'); ?></th>
                        <th style="width: 20%;"><?php esc_html_e('Client ID', 'plugins-onboard'); ?></th>
                        <th style="width: 20%;"><?php esc_html_e('Redirect URI', 'plugins-onboard'); ?></th>
                        <th style="width: 10%;"><?php esc_html_e('Status', 'plugins-onboard'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Created', 'plugins-onboard'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Actions', 'plugins-onboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($app['app_name']); ?></strong>
                            <?php if (!empty($app['description'])) : ?>
                            <br><small><?php echo esc_html(wp_trim_words($app['description'], 10)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($app['client_id']); ?></code></td>
                        <td><small><?php echo esc_html($app['redirect_uri']); ?></small></td>
                        <td>
                            <span class="status-badge status-<?php echo $app['status'] === 'active' ? 'success' : 'inactive'; ?>">
                                <?php echo esc_html($app['status']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(onboard_format_date($app['created_at'])); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=plugins-onboard-applications&action=delete_app&app_id=' . $app['app_id']),
                                'delete_app'
                            ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this application?', 'plugins-onboard'); ?>');">
                                <?php esc_html_e('Delete', 'plugins-onboard'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- API Endpoints -->
    <div class="onboard-section">
        <h2><?php esc_html_e('OAuth Endpoints', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th><?php esc_html_e('Endpoint', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('URL', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Authorization Request', 'plugins-onboard'); ?></strong></td>
                    <td><code><?php echo rest_url('onboard-plugin/v1/auth/request'); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Token Exchange', 'plugins-onboard'); ?></strong></td>
                    <td><code><?php echo rest_url('onboard-plugin/v1/auth/callback'); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Token Refresh', 'plugins-onboard'); ?></strong></td>
                    <td><code><?php echo rest_url('onboard-plugin/v1/refresh-token'); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Request Mutation Token', 'plugins-onboard'); ?></strong></td>
                    <td><code><?php echo rest_url('onboard-plugin/v1/request-mutation'); ?></code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
