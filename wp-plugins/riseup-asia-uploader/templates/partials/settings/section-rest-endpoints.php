<?php
/**
 * Settings Partial — REST API Endpoints reference table.
 *
 * Variables expected: $pluginSlug.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\PluginConfigType;
?>
<!-- REST API Endpoints -->
<div class="riseup-card">
    <h2>
        <span class="dashicons dashicons-rest-api"></span>
        <?php esc_html_e('REST API Endpoints', $pluginSlug); ?>
    </h2>
    <p class="description">
        <?php esc_html_e('Base URL:', $pluginSlug); ?> <code><?php echo esc_url(rest_url(PluginConfigType::apiFullNamespace())); ?></code>
    </p>

    <table class="wp-list-table widefat fixed striped riseup-endpoints-table">
        <thead>
            <tr>
                <th class="column-method"><?php esc_html_e('Method', $pluginSlug); ?></th>
                <th class="column-endpoint"><?php esc_html_e('Endpoint', $pluginSlug); ?></th>
                <th class="column-description"><?php esc_html_e('Description', $pluginSlug); ?></th>
            </tr>
        </thead>
        <tbody>
            <!-- Core -->
            <tr class="endpoint-group-header"><td colspan="3"><strong><?php esc_html_e('Core', $pluginSlug); ?></strong></td></tr>
            <tr>
                <td><span class="riseup-method-badge method-get">GET</span></td>
                <td><code><?php echo esc_html(EndpointType::Status->route()); ?></code></td>
                <td><?php esc_html_e('Health check & version info', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-get">GET</span></td>
                <td><code><?php echo esc_html(EndpointType::Openapi->route()); ?></code></td>
                <td><?php esc_html_e('OpenAPI specification', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::OpcacheReset->route()); ?></code></td>
                <td><?php esc_html_e('Reset PHP OPcache', $pluginSlug); ?></td>
            </tr>

            <!-- Upload & Plugin Management -->
            <tr class="endpoint-group-header"><td colspan="3"><strong><?php esc_html_e('Upload & Plugin Management', $pluginSlug); ?></strong></td></tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::Upload->route()); ?></code></td>
                <td><?php esc_html_e('Upload plugin ZIP', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::UploadActive->route()); ?></code></td>
                <td><?php esc_html_e('Upload & activate in one step', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-get">GET</span></td>
                <td><code><?php echo esc_html(EndpointType::Plugins->route()); ?></code></td>
                <td><?php esc_html_e('List all plugins', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::PluginEnable->route()); ?></code></td>
                <td><?php esc_html_e('Activate a plugin', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::PluginDisable->route()); ?></code></td>
                <td><?php esc_html_e('Deactivate a plugin', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::PluginDelete->route()); ?></code></td>
                <td><?php esc_html_e('Delete a plugin', $pluginSlug); ?></td>
            </tr>

            <!-- Sync -->
            <tr class="endpoint-group-header"><td colspan="3"><strong><?php esc_html_e('Sync', $pluginSlug); ?></strong></td></tr>
            <tr>
                <td><span class="riseup-method-badge method-get">GET</span></td>
                <td><code><?php echo esc_html(EndpointType::SyncManifest->route()); ?></code></td>
                <td><?php esc_html_e('Get sync manifest (file checksums)', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::Sync->route()); ?></code></td>
                <td><?php esc_html_e('Push delta file sync', $pluginSlug); ?></td>
            </tr>

            <!-- Log Management -->
            <tr class="endpoint-group-header"><td colspan="3"><strong><?php esc_html_e('Log Management', $pluginSlug); ?></strong></td></tr>
            <tr>
                <td><span class="riseup-method-badge method-get">GET</span></td>
                <td><code><?php echo esc_html(EndpointType::Logs->route()); ?></code></td>
                <td><?php esc_html_e('Query activity logs', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-get">GET</span></td>
                <td><code><?php echo esc_html(EndpointType::LogsStatus->route()); ?></code></td>
                <td><?php esc_html_e('Log file sizes, line counts, archive info', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-delete">DELETE</span></td>
                <td><code><?php echo esc_html(EndpointType::LogsClear->route()); ?></code></td>
                <td><?php esc_html_e('Request log clearing (returns confirmation token)', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::LogsConfirm->route()); ?></code></td>
                <td><?php esc_html_e('Confirm log clearing (consumes token)', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-post">POST</span></td>
                <td><code><?php echo esc_html(EndpointType::LogsEmail->route()); ?></code></td>
                <td><?php esc_html_e('Email log files as attachments', $pluginSlug); ?></td>
            </tr>
            <tr>
                <td><span class="riseup-method-badge method-get">GET</span></td>
                <td><code><?php echo esc_html(EndpointType::ErrorLogs->route()); ?></code></td>
                <td><?php esc_html_e('Query error log entries', $pluginSlug); ?></td>
            </tr>
        </tbody>
    </table>
</div>
