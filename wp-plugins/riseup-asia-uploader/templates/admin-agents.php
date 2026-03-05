<?php
/**
 * Admin Agent Sites Page Template
 *
 * @package RiseupAsiaUploader
 * @since   1.8.0
 */

use RiseupAsia\Enums\PluginConfigType;

if (!defined('ABSPATH')) {
    exit;
}

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<div class="wrap riseup-admin">
    <?php
    $pageIcon = 'dashicons-networking';
    $pageTitle = $pluginName . ' - ' . __('Agent Sites', $pluginSlug);
    $pageDescription = __('Manage remote WordPress sites. Agent sites allow this plugin to control plugins on other WordPress installations.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <!-- Add Agent Form -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e('Add New Agent Site', $pluginSlug); ?>
        </h2>
        
        <form id="add-agent-form" class="riseup-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="agent_name"><?php esc_html_e('Name', $pluginSlug); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="agent_name" name="name" class="regular-text" required 
                               placeholder="<?php esc_attr_e('My Production Site', $pluginSlug); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_url"><?php esc_html_e('Site URL', $pluginSlug); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="url" id="agent_url" name="url" class="regular-text" required 
                               placeholder="https://example.com">
                        <p class="description"><?php esc_html_e('The WordPress site URL (without /wp-admin)', $pluginSlug); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_username"><?php esc_html_e('Username', $pluginSlug); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="agent_username" name="username" class="regular-text" required 
                               placeholder="admin">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_app_password"><?php esc_html_e('Application Password', $pluginSlug); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="password" id="agent_app_password" name="app_password" class="regular-text" required 
                               placeholder="xxxx xxxx xxxx xxxx xxxx xxxx">
                        <p class="description">
                            <?php esc_html_e('Generate at: Users → Your Profile → Application Passwords', $pluginSlug); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_redirect_url"><?php esc_html_e('Redirect URL (Optional)', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="url" id="agent_redirect_url" name="redirect_url" class="regular-text" 
                               placeholder="https://redirect.example.com/site">
                        <p class="description">
                            <?php esc_html_e('If the site URL may change, provide a 301 redirect URL that will resolve to the current location.', $pluginSlug); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e('Add Agent Site', $pluginSlug); ?>
                </button>
                <span id="add-agent-status" class="riseup-status"></span>
            </p>
        </form>
    </div>

    <!-- Agent Sites List -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-admin-site"></span>
            <?php esc_html_e('Registered Agent Sites', $pluginSlug); ?>
            <button type="button" id="btn-refresh-agents" class="button button-secondary" style="margin-left: 10px;">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', $pluginSlug); ?>
            </button>
        </h2>

        <div id="agents-loading" style="display: none;">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Loading...', $pluginSlug); ?>
        </div>

        <table class="wp-list-table widefat fixed striped" id="agents-table">
            <thead>
                <tr>
                    <th class="column-name"><?php esc_html_e('Name', $pluginSlug); ?></th>
                    <th class="column-url"><?php esc_html_e('URL', $pluginSlug); ?></th>
                    <th class="column-status"><?php esc_html_e('Status', $pluginSlug); ?></th>
                    <th class="column-sync"><?php esc_html_e('Last Sync', $pluginSlug); ?></th>
                    <th class="column-actions"><?php esc_html_e('Actions', $pluginSlug); ?></th>
                </tr>
            </thead>
            <tbody id="agents-tbody">
                <tr class="no-agents">
                    <td colspan="5"><?php esc_html_e('No agent sites registered yet.', $pluginSlug); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Agent Plugins Modal -->
    <div id="agent-plugins-modal" class="riseup-modal" style="display: none;">
        <div class="riseup-modal-content">
            <div class="riseup-modal-header">
                <h3>
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <span id="modal-agent-name">Agent Plugins</span>
                </h3>
                <button type="button" class="riseup-modal-close">&times;</button>
            </div>
            <div class="riseup-modal-body">
                <div id="plugins-loading" style="display: none;">
                    <span class="spinner is-active" style="float: none;"></span>
                    <?php esc_html_e('Loading plugins...', $pluginSlug); ?>
                </div>
                <table class="wp-list-table widefat fixed striped" id="plugins-table" style="display: none;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Plugin', $pluginSlug); ?></th>
                            <th><?php esc_html_e('Version', $pluginSlug); ?></th>
                            <th><?php esc_html_e('Status', $pluginSlug); ?></th>
                            <th><?php esc_html_e('Actions', $pluginSlug); ?></th>
                        </tr>
                    </thead>
                    <tbody id="plugins-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Action History Modal -->
    <div id="agent-history-modal" class="riseup-modal" style="display: none;">
        <div class="riseup-modal-content">
            <div class="riseup-modal-header">
                <h3>
                    <span class="dashicons dashicons-backup"></span>
                    <span id="history-agent-name">Action History</span>
                </h3>
                <button type="button" class="riseup-modal-close">&times;</button>
            </div>
            <div class="riseup-modal-body">
                <table class="wp-list-table widefat fixed striped" id="history-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Time', $pluginSlug); ?></th>
                            <th><?php esc_html_e('Action', $pluginSlug); ?></th>
                            <th><?php esc_html_e('Plugin', $pluginSlug); ?></th>
                            <th><?php esc_html_e('Status', $pluginSlug); ?></th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

