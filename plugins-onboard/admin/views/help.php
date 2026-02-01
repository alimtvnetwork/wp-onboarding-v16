<?php
/**
 * Help admin view.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap onboard-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Help & Documentation', 'plugins-onboard'); ?>
    </h1>

    <!-- Quick Start -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Quick Start Guide', 'plugins-onboard'); ?></h2>
        <ol>
            <li>
                <strong><?php esc_html_e('Create an Application', 'plugins-onboard'); ?></strong>
                <p><?php esc_html_e('Go to Applications > Create New Application to register your external app.', 'plugins-onboard'); ?></p>
            </li>
            <li>
                <strong><?php esc_html_e('Save Credentials', 'plugins-onboard'); ?></strong>
                <p><?php esc_html_e('Copy the Client ID and Client Secret. The secret is only shown once!', 'plugins-onboard'); ?></p>
            </li>
            <li>
                <strong><?php esc_html_e('Authenticate', 'plugins-onboard'); ?></strong>
                <p><?php esc_html_e('Use the OAuth endpoints to obtain an access token.', 'plugins-onboard'); ?></p>
            </li>
            <li>
                <strong><?php esc_html_e('Request Mutation Token', 'plugins-onboard'); ?></strong>
                <p><?php esc_html_e('Before any plugin operation, request a one-time mutation token.', 'plugins-onboard'); ?></p>
            </li>
            <li>
                <strong><?php esc_html_e('Perform Operations', 'plugins-onboard'); ?></strong>
                <p><?php esc_html_e('Use the mutation token to enable, disable, delete, or upload plugins.', 'plugins-onboard'); ?></p>
            </li>
        </ol>
    </div>

    <!-- API Reference -->
    <div class="onboard-section">
        <h2><?php esc_html_e('API Reference', 'plugins-onboard'); ?></h2>
        <p><?php esc_html_e('Base URL:', 'plugins-onboard'); ?> <code><?php echo rest_url('onboard-plugin/v1/'); ?></code></p>

        <h3><?php esc_html_e('Authentication Endpoints', 'plugins-onboard'); ?></h3>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th><?php esc_html_e('Endpoint', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Method', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Auth', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Description', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>/auth/request</code></td>
                    <td>GET</td>
                    <td>None</td>
                    <td><?php esc_html_e('Initiate OAuth flow', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/auth/callback</code></td>
                    <td>POST</td>
                    <td>None</td>
                    <td><?php esc_html_e('Exchange code for tokens', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/refresh-token</code></td>
                    <td>POST</td>
                    <td>None</td>
                    <td><?php esc_html_e('Refresh access token', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/request-mutation</code></td>
                    <td>GET</td>
                    <td>Bearer Token</td>
                    <td><?php esc_html_e('Request one-time mutation token', 'plugins-onboard'); ?></td>
                </tr>
            </tbody>
        </table>

        <h3><?php esc_html_e('Plugin Management Endpoints', 'plugins-onboard'); ?></h3>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th><?php esc_html_e('Endpoint', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Method', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Auth', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Description', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>/plugins/list</code></td>
                    <td>GET</td>
                    <td>Bearer Token</td>
                    <td><?php esc_html_e('List all plugins', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/{slug}/enable</code></td>
                    <td>POST</td>
                    <td>Mutation Token</td>
                    <td><?php esc_html_e('Enable plugin', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/{slug}/disable</code></td>
                    <td>POST</td>
                    <td>Mutation Token</td>
                    <td><?php esc_html_e('Disable plugin', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/{slug}/delete</code></td>
                    <td>POST</td>
                    <td>Mutation Token</td>
                    <td><?php esc_html_e('Delete plugin', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/upload</code></td>
                    <td>POST</td>
                    <td>Mutation Token</td>
                    <td><?php esc_html_e('Upload and install plugin', 'plugins-onboard'); ?></td>
                </tr>
            </tbody>
        </table>

        <h3><?php esc_html_e('Backup Endpoints', 'plugins-onboard'); ?></h3>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th><?php esc_html_e('Endpoint', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Method', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Auth', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Description', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>/plugins/{slug}/backups</code></td>
                    <td>GET</td>
                    <td>Bearer Token</td>
                    <td><?php esc_html_e('List snapshots for plugin', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/mutations/{token}/plugins/{slug}/restore</code></td>
                    <td>POST</td>
                    <td>Mutation Token</td>
                    <td><?php esc_html_e('Restore plugin from snapshot', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/plugins/backups/download-all</code></td>
                    <td>GET</td>
                    <td>Bearer Token</td>
                    <td><?php esc_html_e('Download all plugins as ZIP', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>/plugins/backups/download-snapshots</code></td>
                    <td>GET</td>
                    <td>Bearer Token</td>
                    <td><?php esc_html_e('Download all snapshots as ZIP', 'plugins-onboard'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Authentication Flow -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Authentication Flow', 'plugins-onboard'); ?></h2>
        <div class="onboard-code-example">
            <h4><?php esc_html_e('Step 1: Request Authorization Code', 'plugins-onboard'); ?></h4>
            <pre><code>GET /wp-json/onboard-plugin/v1/auth/request?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI</code></pre>

            <h4><?php esc_html_e('Step 2: Exchange Code for Tokens', 'plugins-onboard'); ?></h4>
            <pre><code>POST /wp-json/onboard-plugin/v1/auth/callback
Content-Type: application/json

{
    "code": "AUTHORIZATION_CODE",
    "client_id": "YOUR_CLIENT_ID",
    "client_secret": "YOUR_CLIENT_SECRET"
}</code></pre>

            <h4><?php esc_html_e('Response:', 'plugins-onboard'); ?></h4>
            <pre><code>{
    "access_token": "eyJhbGc...",
    "refresh_token": "refresh_...",
    "token_type": "Bearer",
    "expires_in": 3600
}</code></pre>
        </div>
    </div>

    <!-- Mutation Token Flow -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Mutation Token Flow', 'plugins-onboard'); ?></h2>
        <div class="onboard-code-example">
            <h4><?php esc_html_e('Step 1: Request Mutation Token', 'plugins-onboard'); ?></h4>
            <pre><code>GET /wp-json/onboard-plugin/v1/request-mutation?action=enable
Authorization: Bearer YOUR_ACCESS_TOKEN</code></pre>

            <h4><?php esc_html_e('Response:', 'plugins-onboard'); ?></h4>
            <pre><code>{
    "mutation_token": "a7f2d8e9c3b4a1f6d2e8c9b4a5f6d7e8",
    "mutation_endpoint": "https://site.com/wp-json/onboard-plugin/v1/mutations/a7f2d8e9c3b4a1f6d2e8c9b4a5f6d7e8/plugins/{slug}/enable",
    "expires_in": 1200,
    "action": "enable"
}</code></pre>

            <h4><?php esc_html_e('Step 2: Use Mutation Token', 'plugins-onboard'); ?></h4>
            <pre><code>POST /wp-json/onboard-plugin/v1/mutations/MUTATION_TOKEN/plugins/woocommerce/enable</code></pre>

            <p><strong><?php esc_html_e('Important:', 'plugins-onboard'); ?></strong> <?php esc_html_e('Mutation tokens are single-use. After one successful request, the token is invalidated.', 'plugins-onboard'); ?></p>
        </div>
    </div>

    <!-- Security Best Practices -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Security Best Practices', 'plugins-onboard'); ?></h2>
        <ul>
            <li><?php esc_html_e('Always use HTTPS in production environments.', 'plugins-onboard'); ?></li>
            <li><?php esc_html_e('Keep client secrets secure and never expose them in client-side code.', 'plugins-onboard'); ?></li>
            <li><?php esc_html_e('Enable IP whitelisting to restrict API access to known IPs.', 'plugins-onboard'); ?></li>
            <li><?php esc_html_e('Regularly review audit logs for suspicious activity.', 'plugins-onboard'); ?></li>
            <li><?php esc_html_e('Set appropriate retention policies for snapshots and logs.', 'plugins-onboard'); ?></li>
            <li><?php esc_html_e('Regenerate client secrets if you suspect they have been compromised.', 'plugins-onboard'); ?></li>
        </ul>
    </div>

    <!-- Error Codes -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Error Codes', 'plugins-onboard'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Code', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('HTTP Status', 'plugins-onboard'); ?></th>
                    <th><?php esc_html_e('Description', 'plugins-onboard'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>invalid_credentials</code></td>
                    <td>401</td>
                    <td><?php esc_html_e('Invalid client credentials', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>invalid_token</code></td>
                    <td>401</td>
                    <td><?php esc_html_e('Invalid or expired access token', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>invalid_mutation_token</code></td>
                    <td>401</td>
                    <td><?php esc_html_e('Invalid or expired mutation token', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>ip_pending_approval</code></td>
                    <td>403</td>
                    <td><?php esc_html_e('IP address requires admin approval', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>ip_mismatch</code></td>
                    <td>403</td>
                    <td><?php esc_html_e('Request IP does not match token IP', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>action_mismatch</code></td>
                    <td>403</td>
                    <td><?php esc_html_e('Token action does not match request', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>rate_limit_exceeded</code></td>
                    <td>429</td>
                    <td><?php esc_html_e('Too many requests', 'plugins-onboard'); ?></td>
                </tr>
                <tr>
                    <td><code>plugin_not_found</code></td>
                    <td>404</td>
                    <td><?php esc_html_e('Plugin does not exist', 'plugins-onboard'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Support -->
    <div class="onboard-section">
        <h2><?php esc_html_e('Support', 'plugins-onboard'); ?></h2>
        <p>
            <?php esc_html_e('For support and documentation, visit:', 'plugins-onboard'); ?>
            <a href="https://riseup.asia/" target="_blank">https://riseup.asia/</a>
        </p>
        <p>
            <strong><?php esc_html_e('Plugin Author:', 'plugins-onboard'); ?></strong> MD ALIM UL KARIM / Riseup Asia LLC
        </p>
    </div>
</div>
