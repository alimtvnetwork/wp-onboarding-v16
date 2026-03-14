<?php
/**
 * Admin Dashboard Template — QUpload status, endpoints, and quick log preview.
 *
 * @package QUpload
 * @since   2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Enums\AdminPageType;
use QUpload\Enums\EndpointType;
use QUpload\Enums\PathLogFileType;
use QUpload\Enums\PluginConfigType;
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\PathHelper;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
$restUrl    = rest_url(PluginConfigType::apiFullNamespace());
$logsDir    = PathHelper::getLogsDir();
$logFile    = $logsDir . PathLogFileType::Log->value;
$errorFile  = $logsDir . PathLogFileType::Error->value;

$logFileSize  = file_exists($logFile) ? size_format(filesize($logFile)) : '—';
$errorFileSize = file_exists($errorFile) ? size_format(filesize($errorFile)) : '—';
?>
<div class="wrap qupload-admin">
    <h1>
        <span class="dashicons dashicons-upload" style="font-size: 28px; margin-right: 8px;"></span>
        <?php echo esc_html($pluginName); ?>
        <span class="qupload-version-badge">v<?php echo esc_html(PluginConfigType::Version->value); ?></span>
    </h1>

    <!-- Status Card -->
    <div class="qupload-card">
        <h2><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e('Plugin Status', $pluginSlug); ?></h2>
        <table class="widefat striped" style="max-width: 600px;">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Version', $pluginSlug); ?></th>
                    <td><code><?php echo esc_html(PluginConfigType::Version->value); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('PHP Version', $pluginSlug); ?></th>
                    <td><code><?php echo esc_html(PHP_VERSION); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('WordPress', $pluginSlug); ?></th>
                    <td><code><?php echo esc_html(get_bloginfo('version')); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Server Time', $pluginSlug); ?> (<?php echo esc_html(DateHelper::getTimezoneLabel()); ?>)</th>
                    <td><code><?php echo esc_html(DateHelper::nowLogDisplay()); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Log File Size', $pluginSlug); ?></th>
                    <td><?php echo esc_html($logFileSize); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Error File Size', $pluginSlug); ?></th>
                    <td>
                        <?php echo esc_html($errorFileSize); ?>
                        <a href="<?php echo esc_url(AdminPageType::Errors->adminUrl()); ?>" class="button button-small" style="margin-left: 8px;">
                            <?php esc_html_e('View Errors', $pluginSlug); ?> →
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- REST API Endpoints -->
    <div class="qupload-card">
        <h2><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e('REST API Endpoints', $pluginSlug); ?></h2>
        <p><?php esc_html_e('Base URL:', $pluginSlug); ?> <code><?php echo esc_url($restUrl); ?></code></p>
        <table class="widefat striped qupload-endpoints-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Method', $pluginSlug); ?></th>
                    <th><?php esc_html_e('Endpoint', $pluginSlug); ?></th>
                    <th><?php esc_html_e('Description', $pluginSlug); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="qupload-method-badge method-get">GET</span></td>
                    <td><code><?php echo esc_html(EndpointType::Status->route()); ?></code></td>
                    <td><?php esc_html_e('Health check', $pluginSlug); ?></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-post">POST</span></td>
                    <td><code><?php echo esc_html(EndpointType::Upload->route()); ?></code></td>
                    <td><?php esc_html_e('Upload plugin ZIP', $pluginSlug); ?></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-put">PUT</span></td>
                    <td><code><?php echo esc_html(EndpointType::Activate->route()); ?></code></td>
                    <td><?php esc_html_e('Activate installed plugin', $pluginSlug); ?></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-put">PUT</span></td>
                    <td><code><?php echo esc_html(EndpointType::Deactivate->route()); ?></code></td>
                    <td><?php esc_html_e('Deactivate installed plugin', $pluginSlug); ?></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-get">GET</span></td>
                    <td><code><?php echo esc_html(EndpointType::Plugins->route()); ?></code></td>
                    <td><?php esc_html_e('List all installed plugins with status', $pluginSlug); ?></td>
                </tr>
                <tr class="qupload-endpoint-separator">
                    <td colspan="3"><strong><?php esc_html_e('Log Management', $pluginSlug); ?></strong></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-get">GET</span></td>
                    <td><code><?php echo esc_html(EndpointType::LogsStatus->route()); ?></code></td>
                    <td><?php esc_html_e('Log file sizes, line counts, archive info', $pluginSlug); ?></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-delete">DELETE</span></td>
                    <td><code><?php echo esc_html(EndpointType::LogsClear->route()); ?></code></td>
                    <td><?php esc_html_e('Request log clearing (returns confirmation token)', $pluginSlug); ?></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-post">POST</span></td>
                    <td><code><?php echo esc_html(EndpointType::LogsConfirm->route()); ?></code></td>
                    <td><?php esc_html_e('Confirm log clearing (consumes token)', $pluginSlug); ?></td>
                </tr>
                <tr>
                    <td><span class="qupload-method-badge method-post">POST</span></td>
                    <td><code><?php echo esc_html(EndpointType::LogsEmail->route()); ?></code></td>
                    <td><?php esc_html_e('Email log files as attachments', $pluginSlug); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Authentication Guide -->
    <div class="qupload-card">
        <h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Authentication', $pluginSlug); ?></h2>
        <p><?php esc_html_e('All endpoints require WordPress Application Passwords via HTTP Basic Auth.', $pluginSlug); ?></p>
        <ol>
            <li><?php esc_html_e('Go to Users → Profile', $pluginSlug); ?></li>
            <li><?php esc_html_e('Scroll to Application Passwords', $pluginSlug); ?></li>
            <li><?php esc_html_e('Enter a name (e.g. "QUpload") and click Add New', $pluginSlug); ?></li>
            <li><?php esc_html_e('Copy the generated password — it won\'t be shown again', $pluginSlug); ?></li>
        </ol>
        <p><?php esc_html_e('Use in requests as:', $pluginSlug); ?> <code>-u "username:app-password"</code></p>
    </div>
</div>
