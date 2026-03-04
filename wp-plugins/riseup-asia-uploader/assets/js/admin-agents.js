/**
 * Admin Agent Sites — Scripts
 *
 * Uses RiseupAgents localized object for all PHP-dependent values.
 *
 * @package RiseupAsiaUploader
 * @since   2.11.0
 */
jQuery(document).ready(function($) {
    var C = window.RiseupAgents;
    var apiBase = C.apiBase;
    var nonce = C.nonce;
    var currentAgentId = null;

    var ENDPOINTS = C.endpoints;
    var AGENT_STATUS = C.agentStatus;
    var STATUS = C.status;
    var RESPONSE_KEYS = C.responseKeys;
    var PLUGIN_STATUS = C.pluginStatus;
    var ACTIONS = C.pluginActions;
    var LABELS = C.i18n;

    // Helper: Build per-agent endpoint URL (agents/{id}/{suffix})
    function buildAgentUrl(id, suffix) {
        return ENDPOINTS.agents + '/' + id + (suffix ? '/' + suffix : '');
    }

    // Helper: Extract action suffix from endpoint value
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
                $tbody.append('<tr class="no-agents"><td colspan="5">' + LABELS.noAgentsYet + '</td></tr>');
            } else {
                response[RESPONSE_KEYS.agents].forEach(function(agent) {
                    var statusClass = 'status-' + agent.status;
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
        }).fail(function() {
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
