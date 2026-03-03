<?php
/**
 * AdminPage — Minimal WordPress admin page for QUpload.
 *
 * Displays plugin status, recent logs, endpoint info, and auth guidance.
 *
 * @package QUpload\Admin
 * @since   1.1.0
 */

namespace QUpload\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Enums\EndpointType;
use QUpload\Enums\PathLogFileType;
use QUpload\Enums\PluginConfigType;
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\PathHelper;

class AdminPage
{
    private const MENU_SLUG    = 'qupload';
    private const CAPABILITY   = 'activate_plugins';
    private const LOG_TAIL_LINES = 50;

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPage']);
    }

    public static function addMenuPage(): void
    {
        add_management_page(
            PluginConfigType::Name->value,
            PluginConfigType::Name->value,
            self::CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'renderPage'],
        );
    }

    public static function renderPage(): void
    {
        $restUrl   = rest_url(PluginConfigType::apiFullNamespace());
        $logsDir   = PathHelper::getLogsDir();
        $logFile   = $logsDir . PathLogFileType::Log->value;
        $errorFile = $logsDir . PathLogFileType::Error->value;

        $recentLogs  = self::tailFile($logFile, self::LOG_TAIL_LINES);
        $recentErrors = self::tailFile($errorFile, self::LOG_TAIL_LINES);
        $logFileSize  = file_exists($logFile) ? size_format(filesize($logFile)) : '—';
        $errorFileSize = file_exists($errorFile) ? size_format(filesize($errorFile)) : '—';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(PluginConfigType::Name->value); ?></h1>

            <!-- Status Card -->
            <div class="card" style="max-width:720px;">
                <h2>Plugin Status</h2>
                <table class="widefat striped" style="max-width:600px;">
                    <tbody>
                        <tr>
                            <th scope="row">Version</th>
                            <td><code><?php echo esc_html(PluginConfigType::Version->value); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">PHP Version</th>
                            <td><code><?php echo esc_html(PHP_VERSION); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">WordPress</th>
                            <td><code><?php echo esc_html(get_bloginfo('version')); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">Server Time (UTC)</th>
                            <td><code><?php echo esc_html(DateHelper::nowUtc()); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">Logs Directory</th>
                            <td><code><?php echo esc_html($logsDir); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">Log File Size</th>
                            <td><?php echo esc_html($logFileSize); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Error File Size</th>
                            <td><?php echo esc_html($errorFileSize); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- REST API Endpoints -->
            <div class="card" style="max-width:720px; margin-top:16px;">
                <h2>REST API Endpoints</h2>
                <p>Base URL: <code><?php echo esc_url($restUrl); ?></code></p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Endpoint</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>GET</code></td>
                            <td><code><?php echo esc_html(EndpointType::Status->route()); ?></code></td>
                            <td>Health check</td>
                        </tr>
                        <tr>
                            <td><code>POST</code></td>
                            <td><code><?php echo esc_html(EndpointType::Upload->route()); ?></code></td>
                            <td>Upload plugin ZIP</td>
                        </tr>
                        <tr>
                            <td><code>POST</code></td>
                            <td><code><?php echo esc_html(EndpointType::Activate->route()); ?></code></td>
                            <td>Activate installed plugin</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Authentication Guide -->
            <div class="card" style="max-width:720px; margin-top:16px;">
                <h2>Authentication</h2>
                <p>All endpoints require <strong>WordPress Application Passwords</strong> via HTTP Basic Auth.</p>
                <ol>
                    <li>Go to <strong>Users → Profile</strong></li>
                    <li>Scroll to <strong>Application Passwords</strong></li>
                    <li>Enter a name (e.g. "QUpload") and click <strong>Add New</strong></li>
                    <li>Copy the generated password — it won't be shown again</li>
                </ol>
                <p>Use in requests as: <code>-u "username:app-password"</code></p>
            </div>

            <!-- Recent Errors -->
            <div class="card" style="max-width:720px; margin-top:16px;">
                <h2>Recent Errors (last <?php echo self::LOG_TAIL_LINES; ?> lines)</h2>
                <?php if (empty($recentErrors)): ?>
                    <p style="color:#46b450;"><strong>✓ No errors recorded</strong></p>
                <?php else: ?>
                    <textarea readonly rows="12" style="width:100%; font-family:monospace; font-size:12px; background:#2d2d2d; color:#f1f1f1; padding:8px; border:1px solid #ccc;"><?php echo esc_textarea($recentErrors); ?></textarea>
                <?php endif; ?>
            </div>

            <!-- Recent Logs -->
            <div class="card" style="max-width:720px; margin-top:16px;">
                <h2>Recent Logs (last <?php echo self::LOG_TAIL_LINES; ?> lines)</h2>
                <?php if (empty($recentLogs)): ?>
                    <p style="color:#999;">No logs yet.</p>
                <?php else: ?>
                    <textarea readonly rows="16" style="width:100%; font-family:monospace; font-size:12px; background:#2d2d2d; color:#f1f1f1; padding:8px; border:1px solid #ccc;"><?php echo esc_textarea($recentLogs); ?></textarea>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Read the last N lines of a file.
     */
    private static function tailFile(string $filePath, int $lines): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        $content = file_get_contents($filePath);

        if ($content === false || $content === '') {
            return '';
        }

        $allLines = explode("\n", $content);
        $tail = array_slice($allLines, -$lines);

        return implode("\n", $tail);
    }
}
