<?php
/**
 * Admin Agent Sites Page Template
 *
 * @package RiseupAsiaUploader
 * @since   1.8.0
 */

use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\AgentStatusType;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\NonceType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\StatusType;

if (!defined('ABSPATH')) {
    exit;
}

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<div class="wrap riseup-admin">
    <h1>
        <span class="dashicons dashicons-networking"></span>
        <?php echo esc_html($pluginName . ' - ' . __('Agent Sites', $pluginSlug)); ?>
        <span class="riseup-version-badge">v<?php echo esc_html(PluginConfigType::Version->value); ?></span>
    </h1>

    <p class="description">
        <?php esc_html_e('Manage remote WordPress sites. Agent sites allow this plugin to control plugins on other WordPress installations.', 'riseup-asia-uploader'); ?>
    </p>

    <!-- Add Agent Form -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e('Add New Agent Site', 'riseup-asia-uploader'); ?>
        </h2>
        
        <form id="add-agent-form" class="riseup-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="agent_name"><?php esc_html_e('Name', 'riseup-asia-uploader'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="agent_name" name="name" class="regular-text" required 
                               placeholder="<?php esc_attr_e('My Production Site', 'riseup-asia-uploader'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_url"><?php esc_html_e('Site URL', 'riseup-asia-uploader'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="url" id="agent_url" name="url" class="regular-text" required 
                               placeholder="https://example.com">
                        <p class="description"><?php esc_html_e('The WordPress site URL (without /wp-admin)', 'riseup-asia-uploader'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_username"><?php esc_html_e('Username', 'riseup-asia-uploader'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="agent_username" name="username" class="regular-text" required 
                               placeholder="admin">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_app_password"><?php esc_html_e('Application Password', 'riseup-asia-uploader'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="password" id="agent_app_password" name="app_password" class="regular-text" required 
                               placeholder="xxxx xxxx xxxx xxxx xxxx xxxx">
                        <p class="description">
                            <?php esc_html_e('Generate at: Users → Your Profile → Application Passwords', 'riseup-asia-uploader'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="agent_redirect_url"><?php esc_html_e('Redirect URL (Optional)', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="agent_redirect_url" name="redirect_url" class="regular-text" 
                               placeholder="https://redirect.example.com/site">
                        <p class="description">
                            <?php esc_html_e('If the site URL may change, provide a 301 redirect URL that will resolve to the current location.', 'riseup-asia-uploader'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-plus"></span>
                    <?php esc_html_e('Add Agent Site', 'riseup-asia-uploader'); ?>
                </button>
                <span id="add-agent-status" class="riseup-status"></span>
            </p>
        </form>
    </div>

    <!-- Agent Sites List -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-admin-site"></span>
            <?php esc_html_e('Registered Agent Sites', 'riseup-asia-uploader'); ?>
            <button type="button" id="btn-refresh-agents" class="button button-secondary" style="margin-left: 10px;">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'riseup-asia-uploader'); ?>
            </button>
        </h2>

        <div id="agents-loading" style="display: none;">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Loading...', 'riseup-asia-uploader'); ?>
        </div>

        <table class="wp-list-table widefat fixed striped" id="agents-table">
            <thead>
                <tr>
                    <th class="column-name"><?php esc_html_e('Name', 'riseup-asia-uploader'); ?></th>
                    <th class="column-url"><?php esc_html_e('URL', 'riseup-asia-uploader'); ?></th>
                    <th class="column-status"><?php esc_html_e('Status', 'riseup-asia-uploader'); ?></th>
                    <th class="column-sync"><?php esc_html_e('Last Sync', 'riseup-asia-uploader'); ?></th>
                    <th class="column-actions"><?php esc_html_e('Actions', 'riseup-asia-uploader'); ?></th>
                </tr>
            </thead>
            <tbody id="agents-tbody">
                <tr class="no-agents">
                    <td colspan="5"><?php esc_html_e('No agent sites registered yet.', 'riseup-asia-uploader'); ?></td>
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
                    <?php esc_html_e('Loading plugins...', 'riseup-asia-uploader'); ?>
                </div>
                <table class="wp-list-table widefat fixed striped" id="plugins-table" style="display: none;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Plugin', 'riseup-asia-uploader'); ?></th>
                            <th><?php esc_html_e('Version', 'riseup-asia-uploader'); ?></th>
                            <th><?php esc_html_e('Status', 'riseup-asia-uploader'); ?></th>
                            <th><?php esc_html_e('Actions', 'riseup-asia-uploader'); ?></th>
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
                            <th><?php esc_html_e('Time', 'riseup-asia-uploader'); ?></th>
                            <th><?php esc_html_e('Action', 'riseup-asia-uploader'); ?></th>
                            <th><?php esc_html_e('Plugin', 'riseup-asia-uploader'); ?></th>
                            <th><?php esc_html_e('Status', 'riseup-asia-uploader'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.riseup-form .required { color: #dc3232; }
.riseup-status { margin-left: 10px; }
.riseup-status.success { color: #46b450; }
.riseup-status.error { color: #dc3232; }

.column-name { width: 20%; }
.column-url { width: 30%; }
.column-status { width: 10%; }
.column-sync { width: 15%; }
.column-actions { width: 25%; }

.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
.status-<?php echo AgentStatusType::Pending->value; ?> { background: #f0f0f1; color: #50575e; }
.status-<?php echo AgentStatusType::Connected->value; ?> { background: #d4edda; color: #155724; }
.status-<?php echo AgentStatusType::Error->value; ?> { background: #f8d7da; color: #721c24; }

.riseup-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.riseup-modal-content {
    background: #fff;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    border-radius: 4px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.riseup-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.riseup-modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.riseup-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}
.riseup-modal-close:hover { color: #dc3232; }
.riseup-modal-body {
    padding: 20px;
    overflow-y: auto;
}

.riseup-admin .button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
    margin-right: 2px;
}
.action-btn { margin-right: 5px; }
.action-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var apiBase = '<?php echo esc_js(rest_url(PluginConfigType::apiFullNamespace())); ?>';
    var nonce = '<?php echo wp_create_nonce(NonceType::WpRest->value); ?>';
    var currentAgentId = null;

    // =========================================================================
    // ENUM CONSTANTS (from PHP — prevents magic strings in JS)
    // =========================================================================

    var ENDPOINTS = {
        agents:        '<?php echo esc_js(EndpointType::Agents->value); ?>',
        agentsAdd:     '<?php echo esc_js(EndpointType::AgentsAdd->value); ?>',
        agentsRemove:  '<?php echo esc_js(EndpointType::AgentsRemove->value); ?>',
        agentsTest:    '<?php echo esc_js(EndpointType::AgentsTest->value); ?>',
        agentsSync:    '<?php echo esc_js(EndpointType::AgentsSync->value); ?>',
        agentsPlugins: '<?php echo esc_js(EndpointType::AgentsPlugins->value); ?>',
        agentAction:   '<?php echo esc_js(EndpointType::AgentAction->value); ?>',
        agentHistory:  '<?php echo esc_js(EndpointType::AgentHistory->value); ?>'
    };

    var AGENT_STATUS = {
        pending:   '<?php echo esc_js(AgentStatusType::Pending->value); ?>',
        connected: '<?php echo esc_js(AgentStatusType::Connected->value); ?>',
        error:     '<?php echo esc_js(AgentStatusType::Error->value); ?>'
    };

    var STATUS = {
        success: '<?php echo esc_js(StatusType::Success->value); ?>'
    };

    var RESPONSE_KEYS = {
        agents:  '<?php echo esc_js(ResponseKeyType::Agents->value); ?>',
        actions: '<?php echo esc_js(ResponseKeyType::Actions->value); ?>',
        plugins: '<?php echo esc_js(ResponseKeyType::Plugins->value); ?>',
        count:   '<?php echo esc_js(ResponseKeyType::Count->value); ?>',
        message: '<?php echo esc_js(ResponseKeyType::Message->value); ?>',
        success: '<?php echo esc_js(ResponseKeyType::Success->value); ?>',
        error:   '<?php echo esc_js(ResponseKeyType::Error->value); ?>'
    };

    var PLUGIN_STATUS = {
        active: '<?php echo esc_js(__("active", "riseup-asia-uploader")); ?>'
    };

    var ACTIONS = {
        enable:  '<?php echo esc_js(strtolower(ActionType::Enable->value)); ?>',
        disable: '<?php echo esc_js(strtolower(ActionType::Disable->value)); ?>',
        delete_: '<?php echo esc_js(strtolower(ActionType::Delete->value)); ?>'
    };

    var LABELS = {
        active:              '<?php echo esc_js(__("Active", "riseup-asia-uploader")); ?>',
        inactive:            '<?php echo esc_js(__("Inactive", "riseup-asia-uploader")); ?>',
        enable:              '<?php echo esc_js(__("Enable", "riseup-asia-uploader")); ?>',
        disable:             '<?php echo esc_js(__("Disable", "riseup-asia-uploader")); ?>',
        deleteBtn:           '<?php echo esc_js(__("Delete", "riseup-asia-uploader")); ?>',
        noPluginsFound:      '<?php echo esc_js(__("No plugins found", "riseup-asia-uploader")); ?>',
        failedLoadPlugins:   '<?php echo esc_js(__("Failed to load plugins", "riseup-asia-uploader")); ?>',
        confirmDeletePlugin: '<?php echo esc_js(__("Are you sure you want to delete this plugin from the remote site?", "riseup-asia-uploader")); ?>',
        noActionHistory:     '<?php echo esc_js(__("No action history", "riseup-asia-uploader")); ?>',
        failedLoadHistory:   '<?php echo esc_js(__("Failed to load history", "riseup-asia-uploader")); ?>',
        confirmRemoveAgent:  '<?php echo esc_js(__("Remove agent site \"%s\"? This cannot be undone.", "riseup-asia-uploader")); ?>',
        connectionSuccess:   '<?php echo esc_js(__("Connection successful!", "riseup-asia-uploader")); ?>',
        connectionFailed:    '<?php echo esc_js(__("Connection failed:", "riseup-asia-uploader")); ?>',
        testFailed:          '<?php echo esc_js(__("Test failed:", "riseup-asia-uploader")); ?>',
        synced:              '<?php echo esc_js(__("Synced %d plugins", "riseup-asia-uploader")); ?>',
        syncFailed:          '<?php echo esc_js(__("Sync failed:", "riseup-asia-uploader")); ?>',
        actionFailed:        '<?php echo esc_js(__("Action failed:", "riseup-asia-uploader")); ?>',
        failedToRemove:      '<?php echo esc_js(__("Failed to remove:", "riseup-asia-uploader")); ?>',
        failedToLoadAgents:  '<?php echo esc_js(__("Failed to load agents:", "riseup-asia-uploader")); ?>',
        failedToAddAgent:    '<?php echo esc_js(__("Failed to add agent", "riseup-asia-uploader")); ?>',
        unknownError:        '<?php echo esc_js(__("Unknown error", "riseup-asia-uploader")); ?>',
        pluginsSuffix:       '<?php echo esc_js(__("Plugins", "riseup-asia-uploader")); ?>',
        historySuffix:       '<?php echo esc_js(__("Action History", "riseup-asia-uploader")); ?>'
    };

    // Helper: Build per-agent endpoint URL (agents/{id}/{suffix})
    function buildAgentUrl(id, suffix) {
        return ENDPOINTS.agents + '/' + id + (suffix ? '/' + suffix : '');
    }

    // Helper: Extract action suffix from endpoint value (e.g., 'agents/test' → 'test')
    function endpointSuffix(endpointValue) {
        var parts = endpointValue.split('/');

        return parts[parts.length - 1];
    }

    // Helper: AJAX request
    function apiRequest(method, endpoint, data) {
        return $.ajax({
            url: apiBase + '/' + endpoint,
            method: method,
            contentType: 'application/json',
            data: data ? JSON.stringify(data) : null,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            }
        });
    }

    // Helper: Show status message
    function showStatus(selector, message, isError) {
        $(selector).text(message)
            .removeClass('success error')
            .addClass(isError ? 'error' : 'success')
            .show();
        setTimeout(function() { $(selector).fadeOut(); }, 5000);
    }

    // Load agent sites
    function loadAgents() {
        $('#agents-loading').show();
        $('#agents-table').hide();
        
        apiRequest('GET', ENDPOINTS.agents).done(function(response) {
            var $tbody = $('#agents-tbody').empty();
            
            if (!response[RESPONSE_KEYS.agents] || response[RESPONSE_KEYS.agents].length === 0) {
                $tbody.append('<tr class="no-agents"><td colspan="5"><?php esc_html_e('No agent sites registered yet.', 'riseup-asia-uploader'); ?></td></tr>');
            } else {
                response[RESPONSE_KEYS.agents].forEach(function(agent) {
                    var statusClass = 'status-' + agent.status; // AgentStatusType PascalCase values
                    var statusLabel = agent.status;
                    var lastSync = agent.last_sync ? new Date(agent.last_sync).toLocaleString() : '-';
                    
                    var row = '<tr data-id="' + agent.id + '">' +
                        '<td><strong>' + escapeHtml(agent.name) + '</strong></td>' +
                        '<td><a href="' + escapeHtml(agent.url) + '" target="_blank">' + escapeHtml(agent.url) + '</a></td>' +
                        '<td><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></td>' +
                        '<td>' + lastSync + '</td>' +
                        '<td>' +
                            '<button type="button" class="button action-btn btn-test" title="Test Connection"><span class="dashicons dashicons-yes-alt"></span></button>' +
                            '<button type="button" class="button action-btn btn-sync" title="Sync Plugins"><span class="dashicons dashicons-update"></span></button>' +
                            '<button type="button" class="button action-btn btn-plugins" title="View Plugins"><span class="dashicons dashicons-admin-plugins"></span></button>' +
                            '<button type="button" class="button action-btn btn-history" title="Action History"><span class="dashicons dashicons-backup"></span></button>' +
                            '<button type="button" class="button action-btn btn-remove" title="Remove"><span class="dashicons dashicons-trash"></span></button>' +
                        '</td>' +
                    '</tr>';
                    $tbody.append(row);
                });
            }
        }).fail(function(xhr) {
            showStatus('#add-agent-status', LABELS.failedToLoadAgents + ' ' + (xhr.responseJSON?.error?.message || LABELS.unknownError), true);
        }).always(function() {
            $('#agents-loading').hide();
            $('#agents-table').show();
        });
    }

    // Escape HTML
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    // Add agent form
    $('#add-agent-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        
        var data = {
            name: $('#agent_name').val(),
            url: $('#agent_url').val(),
            username: $('#agent_username').val(),
            app_password: $('#agent_app_password').val(),
            redirect_url: $('#agent_redirect_url').val() || null
        };
        
        apiRequest('POST', ENDPOINTS.agents, data).done(function(response) {
            showStatus('#add-agent-status', response[RESPONSE_KEYS.message], false);
            $form[0].reset();
            loadAgents();
        }).fail(function(xhr) {
            showStatus('#add-agent-status', xhr.responseJSON?.error?.message || LABELS.failedToAddAgent, true);
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Refresh button
    $('#btn-refresh-agents').on('click', loadAgents);

    // Test connection
    $(document).on('click', '.btn-test', function() {
        var $row = $(this).closest('tr');
        var id = $row.data('id');
        var $btn = $(this).prop('disabled', true);
        
        apiRequest('POST', buildAgentUrl(id, endpointSuffix(ENDPOINTS.agentsTest))).done(function(response) {
            alert(response[RESPONSE_KEYS.success] ? LABELS.connectionSuccess : LABELS.connectionFailed + ' ' + response[RESPONSE_KEYS.message]);
            loadAgents();
        }).fail(function(xhr) {
            alert(LABELS.testFailed + ' ' + (xhr.responseJSON?.error?.message || LABELS.unknownError));
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Sync plugins
    $(document).on('click', '.btn-sync', function() {
        var $row = $(this).closest('tr');
        var id = $row.data('id');
        var $btn = $(this).prop('disabled', true);
        
        apiRequest('POST', buildAgentUrl(id, endpointSuffix(ENDPOINTS.agentsSync))).done(function(response) {
            alert(LABELS.synced.replace('%d', response[RESPONSE_KEYS.count]));
            loadAgents();
        }).fail(function(xhr) {
            alert(LABELS.syncFailed + ' ' + (xhr.responseJSON?.error?.message || LABELS.unknownError));
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // View plugins
    $(document).on('click', '.btn-plugins', function() {
        var $row = $(this).closest('tr');
        var id = $row.data('id');
        var name = $row.find('td:first strong').text();
        
        currentAgentId = id;
        $('#modal-agent-name').text(name + ' - ' + LABELS.pluginsSuffix);
        $('#plugins-loading').show();
        $('#plugins-table').hide();
        $('#agent-plugins-modal').show();
        
        apiRequest('POST', buildAgentUrl(id, endpointSuffix(ENDPOINTS.agentsSync))).done(function(response) {
            var $tbody = $('#plugins-tbody').empty();
            
            if (!response[RESPONSE_KEYS.plugins] || response[RESPONSE_KEYS.plugins].length === 0) {
                $tbody.append('<tr><td colspan="4">' + LABELS.noPluginsFound + '</td></tr>');
            } else {
                response[RESPONSE_KEYS.plugins].forEach(function(plugin) {
                    var isActive = plugin.status === PLUGIN_STATUS.active;
                    var statusBadge = isActive 
                        ? '<span class="status-badge status-connected">' + LABELS.active + '</span>'
                        : '<span class="status-badge status-pending">' + LABELS.inactive + '</span>';
                    
                    var row = '<tr data-slug="' + escapeHtml(plugin.slug) + '">' +
                        '<td><strong>' + escapeHtml(plugin.name) + '</strong><br><code>' + escapeHtml(plugin.slug) + '</code></td>' +
                        '<td>' + escapeHtml(plugin.version || '-') + '</td>' +
                        '<td>' + statusBadge + '</td>' +
                        '<td>' +
                            (isActive 
                                ? '<button type="button" class="button action-btn btn-plugin-disable">' + LABELS.disable + '</button>'
                                : '<button type="button" class="button action-btn btn-plugin-enable">' + LABELS.enable + '</button>') +
                            '<button type="button" class="button action-btn btn-plugin-delete" style="color:#dc3232;">' + LABELS.deleteBtn + '</button>' +
                        '</td>' +
                    '</tr>';
                    $tbody.append(row);
                });
            }
        }).fail(function(xhr) {
            $('#plugins-tbody').html('<tr><td colspan="4">' + LABELS.failedLoadPlugins + '</td></tr>');
        }).always(function() {
            $('#plugins-loading').hide();
            $('#plugins-table').show();
        });
    });

    // Plugin actions
    $(document).on('click', '.btn-plugin-enable, .btn-plugin-disable, .btn-plugin-delete', function() {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var slug = $row.data('slug');
        var action = $btn.hasClass('btn-plugin-enable') ? ACTIONS.enable 
                   : $btn.hasClass('btn-plugin-disable') ? ACTIONS.disable : ACTIONS.delete_;
        
        if (action === ACTIONS.delete_ && !confirm(LABELS.confirmDeletePlugin)) {
            return;
        }
        
        $btn.prop('disabled', true);
        
        apiRequest('POST', buildAgentUrl(currentAgentId, endpointSuffix(ENDPOINTS.agentAction)), {
            action: action,
            slug: slug
        }).done(function(response) {
            alert(response[RESPONSE_KEYS.message]);
            // Refresh plugin list
            $('.btn-plugins').filter(function() {
                return $(this).closest('tr').data('id') === currentAgentId;
            }).click();
        }).fail(function(xhr) {
            alert(LABELS.actionFailed + ' ' + (xhr.responseJSON?.error?.message || LABELS.unknownError));
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // View history
    $(document).on('click', '.btn-history', function() {
        var $row = $(this).closest('tr');
        var id = $row.data('id');
        var name = $row.find('td:first strong').text();
        
        $('#history-agent-name').text(name + ' - ' + LABELS.historySuffix);
        $('#agent-history-modal').show();
        
        apiRequest('GET', buildAgentUrl(id, endpointSuffix(ENDPOINTS.agentHistory))).done(function(response) {
            var $tbody = $('#history-tbody').empty();
            
            if (!response[RESPONSE_KEYS.actions] || response[RESPONSE_KEYS.actions].length === 0) {
                $tbody.append('<tr><td colspan="4">' + LABELS.noActionHistory + '</td></tr>');
            } else {
                response[RESPONSE_KEYS.actions].forEach(function(action) {
                    var statusClass = action.status === STATUS.success ? 'status-connected' : 'status-error';
                    var time = new Date(action.created_at).toLocaleString();
                    
                    var row = '<tr>' +
                        '<td>' + time + '</td>' +
                        '<td>' + escapeHtml(action.action) + '</td>' +
                        '<td>' + escapeHtml(action.target_plugin || '-') + '</td>' +
                        '<td><span class="status-badge ' + statusClass + '">' + action.status + '</span></td>' +
                    '</tr>';
                    $tbody.append(row);
                });
            }
        }).fail(function() {
            $('#history-tbody').html('<tr><td colspan="4">' + LABELS.failedLoadHistory + '</td></tr>');
        });
    });

    // Remove agent
    $(document).on('click', '.btn-remove', function() {
        var $row = $(this).closest('tr');
        var id = $row.data('id');
        var name = $row.find('td:first strong').text();
        
        if (!confirm(LABELS.confirmRemoveAgent.replace('%s', name))) {
            return;
        }
        
        var $btn = $(this).prop('disabled', true);
        
        apiRequest('DELETE', buildAgentUrl(id)).done(function() {
            loadAgents();
        }).fail(function(xhr) {
            alert(LABELS.failedToRemove + ' ' + (xhr.responseJSON?.error?.message || LABELS.unknownError));
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Close modals
    $('.riseup-modal-close').on('click', function() {
        $(this).closest('.riseup-modal').hide();
    });
    
    $(document).on('click', '.riseup-modal', function(e) {
        if ($(e.target).hasClass('riseup-modal')) {
            $(this).hide();
        }
    });

    // Initial load
    loadAgents();
});
</script>
