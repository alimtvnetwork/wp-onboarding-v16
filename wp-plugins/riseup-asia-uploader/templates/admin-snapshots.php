<?php
/**
 * Admin Snapshots Dashboard Template
 *
 * @package RiseupAsiaUploader
 * @since   1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap riseup-admin">
    <h1>
        <span class="dashicons dashicons-database"></span>
        <?php esc_html_e('Database Snapshots', 'riseup-asia-uploader'); ?>
        <span class="riseup-version-badge">v<?php echo esc_html(RISEUP_VERSION); ?></span>
    </h1>

    <p class="description">
        <?php esc_html_e('Create, manage, and restore database snapshots. Snapshots are stored as SQLite files and can be exported/imported as ZIP archives.', 'riseup-asia-uploader'); ?>
    </p>

    <!-- Actions Bar -->
    <div class="riseup-card riseup-snapshots-actions">
        <div class="riseup-actions-row">
            <button type="button" id="btn_snapshot_now" class="button button-primary">
                <span class="dashicons dashicons-camera"></span>
                <?php esc_html_e('Snapshot Now', 'riseup-asia-uploader'); ?>
            </button>

            <button type="button" id="btn_import_snapshot" class="button button-secondary">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e('Import Snapshot', 'riseup-asia-uploader'); ?>
            </button>

            <button type="button" id="btn_refresh_list" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'riseup-asia-uploader'); ?>
            </button>

            <span id="snapshot_action_status" class="riseup-inline-status"></span>
        </div>

        <!-- Snapshot Now Options (hidden by default) -->
        <div id="snapshot_options" class="riseup-snapshot-options" style="display: none;">
            <h3><?php esc_html_e('Snapshot Options', 'riseup-asia-uploader'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snapshot_scope"><?php esc_html_e('Scope', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="snapshot_scope">
                            <option value="all"><?php esc_html_e('All Tables', 'riseup-asia-uploader'); ?></option>
                            <option value="wordpress"><?php esc_html_e('WordPress Core Only', 'riseup-asia-uploader'); ?></option>
                            <option value="content"><?php esc_html_e('Content Only (Posts, Terms, Comments)', 'riseup-asia-uploader'); ?></option>
                            <option value="custom"><?php esc_html_e('Custom Selection', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="custom_tables_row" style="display: none;">
                    <th scope="row">
                        <label for="snapshot_tables"><?php esc_html_e('Tables', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <div id="snapshot_tables_list" class="riseup-tables-list">
                            <em><?php esc_html_e('Loading tables...', 'riseup-asia-uploader'); ?></em>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="snapshot_provider"><?php esc_html_e('Provider', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="snapshot_provider">
                            <option value="auto"><?php esc_html_e('Auto-Detect (Recommended)', 'riseup-asia-uploader'); ?></option>
                            <option value="native"><?php esc_html_e('Native SQLite', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" id="btn_confirm_snapshot" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Create Snapshot', 'riseup-asia-uploader'); ?>
                </button>
                <button type="button" id="btn_cancel_snapshot" class="button button-secondary">
                    <?php esc_html_e('Cancel', 'riseup-asia-uploader'); ?>
                </button>
            </p>
        </div>

        <!-- Import form (hidden by default) -->
        <div id="import_form" style="display: none;">
            <h3><?php esc_html_e('Import Snapshot', 'riseup-asia-uploader'); ?></h3>
            <p class="description"><?php esc_html_e('Upload a snapshot ZIP archive to import.', 'riseup-asia-uploader'); ?></p>
            <input type="file" id="import_file" accept=".zip">
            <p>
                <button type="button" id="btn_confirm_import" class="button button-primary" disabled>
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Upload & Import', 'riseup-asia-uploader'); ?>
                </button>
                <button type="button" id="btn_cancel_import" class="button button-secondary">
                    <?php esc_html_e('Cancel', 'riseup-asia-uploader'); ?>
                </button>
            </p>
        </div>
    </div>

    <!-- Snapshot List -->
    <div class="riseup-card">
        <h2><?php esc_html_e('Snapshots', 'riseup-asia-uploader'); ?></h2>

        <div id="snapshots_loading" style="display: none;">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Loading snapshots...', 'riseup-asia-uploader'); ?>
        </div>

        <div id="snapshots_empty" style="display: none;">
            <p><em><?php esc_html_e('No snapshots found. Click "Snapshot Now" to create your first backup.', 'riseup-asia-uploader'); ?></em></p>
        </div>

        <table id="snapshots_table" class="wp-list-table widefat fixed striped" style="display: none;">
            <thead>
                <tr>
                    <th class="column-id" style="width: 50px;">#</th>
                    <th class="column-filename"><?php esc_html_e('Filename', 'riseup-asia-uploader'); ?></th>
                    <th class="column-scope"><?php esc_html_e('Scope', 'riseup-asia-uploader'); ?></th>
                    <th class="column-provider"><?php esc_html_e('Provider', 'riseup-asia-uploader'); ?></th>
                    <th class="column-tables" style="width: 60px;"><?php esc_html_e('Tables', 'riseup-asia-uploader'); ?></th>
                    <th class="column-rows" style="width: 80px;"><?php esc_html_e('Rows', 'riseup-asia-uploader'); ?></th>
                    <th class="column-size" style="width: 80px;"><?php esc_html_e('Size', 'riseup-asia-uploader'); ?></th>
                    <th class="column-status" style="width: 80px;"><?php esc_html_e('Status', 'riseup-asia-uploader'); ?></th>
                    <th class="column-date"><?php esc_html_e('Created', 'riseup-asia-uploader'); ?></th>
                    <th class="column-actions" style="width: 200px;"><?php esc_html_e('Actions', 'riseup-asia-uploader'); ?></th>
                </tr>
            </thead>
            <tbody id="snapshots_tbody">
            </tbody>
        </table>

        <div id="snapshots_pagination" class="tablenav bottom" style="display: none;">
            <div class="tablenav-pages">
                <span class="displaying-num" id="snapshots_count"></span>
                <span class="pagination-links" id="snapshots_pages"></span>
            </div>
        </div>
    </div>

    <!-- Snapshot Settings -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e('Snapshot Settings', 'riseup-asia-uploader'); ?>
        </h2>

        <div id="settings_loading">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Loading settings...', 'riseup-asia-uploader'); ?>
        </div>

        <div id="settings_form" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="setting_schedule"><?php esc_html_e('Schedule', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_schedule">
                            <option value="manual"><?php esc_html_e('Manual Only', 'riseup-asia-uploader'); ?></option>
                            <option value="daily"><?php esc_html_e('Daily', 'riseup-asia-uploader'); ?></option>
                            <option value="weekly"><?php esc_html_e('Weekly', 'riseup-asia-uploader'); ?></option>
                            <option value="monthly"><?php esc_html_e('Monthly', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="setting_retention_type"><?php esc_html_e('Retention Policy', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_retention_type">
                            <option value="none"><?php esc_html_e('None (Manual Cleanup)', 'riseup-asia-uploader'); ?></option>
                            <option value="days"><?php esc_html_e('Keep for N Days', 'riseup-asia-uploader'); ?></option>
                            <option value="count"><?php esc_html_e('Keep Last N Snapshots', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="retention_value_row" style="display: none;">
                    <th scope="row">
                        <label for="setting_retention_value"><?php esc_html_e('Retention Value', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="setting_retention_value" min="1" max="365" value="30" class="small-text">
                        <span id="retention_value_label"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="setting_scope"><?php esc_html_e('Default Scope', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_scope">
                            <option value="all"><?php esc_html_e('All Tables', 'riseup-asia-uploader'); ?></option>
                            <option value="wordpress"><?php esc_html_e('WordPress Core', 'riseup-asia-uploader'); ?></option>
                            <option value="content"><?php esc_html_e('Content Only', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="setting_provider"><?php esc_html_e('Default Provider', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="setting_provider">
                            <option value="auto"><?php esc_html_e('Auto-Detect', 'riseup-asia-uploader'); ?></option>
                            <option value="native"><?php esc_html_e('Native SQLite', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" id="btn_save_settings" class="button button-primary">
                    <?php esc_html_e('Save Settings', 'riseup-asia-uploader'); ?>
                </button>
                <span id="settings_status" class="riseup-inline-status"></span>
            </p>
        </div>
    </div>

    <!-- Providers Info -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-plugins-checked"></span>
            <?php esc_html_e('Available Providers', 'riseup-asia-uploader'); ?>
        </h2>
        <div id="providers_loading">
            <span class="spinner is-active" style="float: none;"></span>
            <?php esc_html_e('Detecting providers...', 'riseup-asia-uploader'); ?>
        </div>
        <div id="providers_list" style="display: none;"></div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="restore_modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
            <?php esc_html_e('Restore Database', 'riseup-asia-uploader'); ?>
        </h2>
        <p class="riseup-warning-text">
            <?php esc_html_e('This will replace your current database tables with the snapshot data. A pre-restore backup will be created automatically.', 'riseup-asia-uploader'); ?>
        </p>
        <div id="restore_options">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Snapshot', 'riseup-asia-uploader'); ?></th>
                    <td><strong id="restore_snapshot_name"></strong></td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="restore_mode"><?php esc_html_e('Mode', 'riseup-asia-uploader'); ?></label>
                    </th>
                    <td>
                        <select id="restore_mode">
                            <option value="full"><?php esc_html_e('Full Restore (All Tables)', 'riseup-asia-uploader'); ?></option>
                            <option value="selective"><?php esc_html_e('Selective (Choose Tables)', 'riseup-asia-uploader'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>
                            <input type="checkbox" id="restore_create_backup" checked>
                            <?php esc_html_e('Create Pre-Restore Backup', 'riseup-asia-uploader'); ?>
                        </label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e('Strongly recommended. Creates a snapshot before restoring.', 'riseup-asia-uploader'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <p class="riseup-modal-actions">
            <button type="button" id="btn_confirm_restore" class="button button-primary" style="background: #d63638; border-color: #d63638;">
                <span class="dashicons dashicons-database-import"></span>
                <?php esc_html_e('Restore Now', 'riseup-asia-uploader'); ?>
            </button>
            <button type="button" id="btn_cancel_restore" class="button button-secondary">
                <?php esc_html_e('Cancel', 'riseup-asia-uploader'); ?>
            </button>
        </p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var ajaxNonce = '<?php echo wp_create_nonce('riseup_admin_nonce'); ?>';
    var restBase = '<?php echo esc_url(rest_url(RISEUP_API_FULL_NAMESPACE)); ?>';
    var $status = $('#snapshot_action_status');
    var currentPage = 1;
    var currentRestoreId = null;

    // Status helper
    function showStatus($el, message, isError) {
        $el.html(message).css('color', isError ? '#d63638' : '#00a32a').show();
        setTimeout(function() { $el.fadeOut(); }, 5000);
    }

    // Format file size
    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        var k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Status badge
    function statusBadge(status) {
        var colors = {
            'complete': '#00a32a', 'running': '#2271b1', 'pending': '#dba617',
            'scheduled': '#2271b1', 'failed': '#d63638'
        };
        var color = colors[status] || '#666';
        return '<span class="riseup-badge" style="background:' + color + ';">' + status + '</span>';
    }

    // Load snapshots
    function loadSnapshots(page) {
        page = page || 1;
        currentPage = page;
        var limit = 20;
        var offset = (page - 1) * limit;

        $('#snapshots_loading').show();
        $('#snapshots_table, #snapshots_empty, #snapshots_pagination').hide();

        $.ajax({
            url: restBase + '/snapshots?limit=' + limit + '&offset=' + offset,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                $('#snapshots_loading').hide();
                var snapshots = response.snapshots || [];

                if (snapshots.length === 0 && page === 1) {
                    $('#snapshots_empty').show();
                    return;
                }

                var html = '';
                snapshots.forEach(function(s) {
                    html += '<tr data-id="' + s.id + '">';
                    html += '<td>' + (s.sequence || s.id) + '</td>';
                    html += '<td><code>' + (s.filename || '-') + '</code></td>';
                    html += '<td>' + (s.scope || 'all') + '</td>';
                    html += '<td>' + (s.provider || '-') + '</td>';
                    html += '<td>' + (s.table_count || '-') + '</td>';
                    html += '<td>' + (s.total_rows ? s.total_rows.toLocaleString() : '-') + '</td>';
                    html += '<td>' + formatBytes(s.file_size) + '</td>';
                    html += '<td>' + statusBadge(s.status || 'complete') + '</td>';
                    html += '<td>' + (s.created_at || '-') + '</td>';
                    html += '<td class="riseup-snapshot-actions">';
                    if (s.status === 'complete' || !s.status) {
                        html += '<button class="button button-small btn-restore" data-id="' + s.id + '" data-name="' + (s.filename || '#' + s.id) + '" title="Restore">';
                        html += '<span class="dashicons dashicons-database-import"></span></button> ';
                        html += '<button class="button button-small btn-export" data-id="' + s.id + '" title="Export ZIP">';
                        html += '<span class="dashicons dashicons-download"></span></button> ';
                    }
                    html += '<button class="button button-small btn-delete-snapshot" data-id="' + s.id + '" title="Delete">';
                    html += '<span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>';
                    html += '</td>';
                    html += '</tr>';
                });

                $('#snapshots_tbody').html(html);
                $('#snapshots_table').show();

                // Pagination
                var total = response.total || snapshots.length;
                var totalPages = Math.ceil(total / limit);
                if (totalPages > 1) {
                    $('#snapshots_count').text(total + ' items');
                    var pagesHtml = '';
                    for (var i = 1; i <= totalPages; i++) {
                        if (i === page) {
                            pagesHtml += '<span class="tablenav-pages-navspan button disabled">' + i + '</span> ';
                        } else {
                            pagesHtml += '<a class="button page-link" data-page="' + i + '">' + i + '</a> ';
                        }
                    }
                    $('#snapshots_pages').html(pagesHtml);
                    $('#snapshots_pagination').show();
                }
            },
            error: function(xhr) {
                $('#snapshots_loading').hide();
                showStatus($status, '✗ Failed to load snapshots', true);
            }
        });
    }

    // Load settings
    function loadSettings() {
        $.ajax({
            url: restBase + '/snapshots/settings',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(settings) {
                $('#settings_loading').hide();
                $('#settings_form').show();
                if (settings.schedule) $('#setting_schedule').val(settings.schedule);
                if (settings.retention_type) $('#setting_retention_type').val(settings.retention_type).trigger('change');
                if (settings.retention_value) $('#setting_retention_value').val(settings.retention_value);
                if (settings.default_scope) $('#setting_scope').val(settings.default_scope);
                if (settings.default_provider) $('#setting_provider').val(settings.default_provider);
            },
            error: function() {
                $('#settings_loading').html('<em>Failed to load settings.</em>');
            }
        });
    }

    // Load providers
    function loadProviders() {
        $.ajax({
            url: restBase + '/snapshots/providers',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(providers) {
                $('#providers_loading').hide();
                var html = '<table class="wp-list-table widefat striped"><thead><tr>';
                html += '<th>Provider</th><th>Available</th><th>Priority</th>';
                html += '</tr></thead><tbody>';
                (providers || []).forEach(function(p) {
                    var icon = p.available ? '✓' : '✗';
                    var color = p.available ? '#00a32a' : '#999';
                    html += '<tr>';
                    html += '<td><strong>' + p.name + '</strong></td>';
                    html += '<td style="color:' + color + ';">' + icon + '</td>';
                    html += '<td>' + (p.priority || '-') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                $('#providers_list').html(html).show();
            },
            error: function() {
                $('#providers_loading').html('<em>Failed to detect providers.</em>');
            }
        });
    }

    // Snapshot Now toggle
    $('#btn_snapshot_now').on('click', function() {
        $('#snapshot_options').slideToggle();
        $('#import_form').slideUp();
    });

    // Cancel snapshot
    $('#btn_cancel_snapshot').on('click', function() {
        $('#snapshot_options').slideUp();
    });

    // Scope change - show/hide custom tables
    $('#snapshot_scope').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#custom_tables_row').show();
            loadTables();
        } else {
            $('#custom_tables_row').hide();
        }
    });

    // Load available tables
    function loadTables() {
        $.ajax({
            url: restBase + '/snapshots/tables',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(tables) {
                var html = '';
                (tables || []).forEach(function(t) {
                    html += '<label class="riseup-table-checkbox"><input type="checkbox" value="' + t + '" checked> ' + t + '</label> ';
                });
                $('#snapshot_tables_list').html(html);
            }
        });
    }

    // Confirm create snapshot
    $('#btn_confirm_snapshot').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var data = {
            scope: $('#snapshot_scope').val(),
            provider: $('#snapshot_provider').val()
        };
        if (data.scope === 'custom') {
            data.tables = [];
            $('#snapshot_tables_list input:checked').each(function() {
                data.tables.push($(this).val());
            });
        }

        $.ajax({
            url: restBase + '/snapshots/schedule',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                showStatus($status, '✓ Snapshot created successfully', false);
                $('#snapshot_options').slideUp();
                loadSnapshots(1);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Snapshot creation failed';
                showStatus($status, '✗ ' + msg, true);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Import toggle
    $('#btn_import_snapshot').on('click', function() {
        $('#import_form').slideToggle();
        $('#snapshot_options').slideUp();
    });
    $('#btn_cancel_import').on('click', function() {
        $('#import_form').slideUp();
    });

    // File input change
    $('#import_file').on('change', function() {
        $('#btn_confirm_import').prop('disabled', !this.files.length);
    });

    // Confirm import
    $('#btn_confirm_import').on('click', function() {
        var file = $('#import_file')[0].files[0];
        if (!file) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Importing...');

        var formData = new FormData();
        formData.append('file', file);

        $.ajax({
            url: restBase + '/snapshots/import',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                showStatus($status, '✓ Snapshot imported successfully', false);
                $('#import_form').slideUp();
                $('#import_file').val('');
                loadSnapshots(1);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Import failed';
                showStatus($status, '✗ ' + msg, true);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Upload & Import');
            }
        });
    });

    // Refresh
    $('#btn_refresh_list').on('click', function() {
        loadSnapshots(currentPage);
    });

    // Pagination clicks
    $(document).on('click', '.page-link', function() {
        loadSnapshots(parseInt($(this).data('page')));
    });

    // Restore button
    $(document).on('click', '.btn-restore', function() {
        currentRestoreId = $(this).data('id');
        $('#restore_snapshot_name').text($(this).data('name'));
        $('#restore_modal').show();
    });

    // Cancel restore
    $('#btn_cancel_restore, .riseup-modal-overlay').on('click', function() {
        $('#restore_modal').hide();
        currentRestoreId = null;
    });

    // Confirm restore
    $('#btn_confirm_restore').on('click', function() {
        if (!currentRestoreId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('Restoring...');

        $.ajax({
            url: restBase + '/snapshots/' + currentRestoreId + '/restore',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                confirm: true,
                mode: $('#restore_mode').val(),
                create_backup: $('#restore_create_backup').is(':checked')
            }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                $('#restore_modal').hide();
                showStatus($status, '✓ Database restored successfully!', false);
                loadSnapshots(currentPage);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Restore failed';
                showStatus($status, '✗ ' + msg, true);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> Restore Now');
                currentRestoreId = null;
            }
        });
    });

    // Export button
    $(document).on('click', '.btn-export', function() {
        var id = $(this).data('id');
        window.open(restBase + '/snapshots/' + id + '/export?_wpnonce=<?php echo wp_create_nonce('wp_rest'); ?>', '_blank');
    });

    // Delete button
    $(document).on('click', '.btn-delete-snapshot', function() {
        var id = $(this).data('id');
        if (!confirm('<?php esc_html_e('Are you sure you want to delete this snapshot? This cannot be undone.', 'riseup-asia-uploader'); ?>')) {
            return;
        }

        $.ajax({
            url: restBase + '/snapshots/' + id,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function() {
                showStatus($status, '✓ Snapshot deleted', false);
                loadSnapshots(currentPage);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Delete failed';
                showStatus($status, '✗ ' + msg, true);
            }
        });
    });

    // Retention type change
    $('#setting_retention_type').on('change', function() {
        var val = $(this).val();
        if (val === 'days' || val === 'count') {
            $('#retention_value_row').show();
            $('#retention_value_label').text(val === 'days' ? 'days' : 'snapshots');
        } else {
            $('#retention_value_row').hide();
        }
    });

    // Save settings
    $('#btn_save_settings').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);

        var data = {
            schedule: $('#setting_schedule').val(),
            retention_type: $('#setting_retention_type').val(),
            retention_value: parseInt($('#setting_retention_value').val()) || 30,
            default_scope: $('#setting_scope').val(),
            default_provider: $('#setting_provider').val()
        };

        $.ajax({
            url: restBase + '/snapshots/settings',
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function() {
                showStatus($('#settings_status'), '✓ Settings saved', false);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Save failed';
                showStatus($('#settings_status'), '✗ ' + msg, true);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Initial load
    loadSnapshots(1);
    loadSettings();
    loadProviders();
});
</script>

<style>
.riseup-actions-row {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.riseup-actions-row .button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
    margin-right: 2px;
}
.riseup-inline-status {
    font-weight: 500;
    margin-left: 10px;
}
.riseup-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.riseup-snapshot-actions .button {
    padding: 2px 6px;
    min-height: 28px;
}
.riseup-snapshot-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    vertical-align: middle;
}
.riseup-snapshot-options,
#import_form {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}
.riseup-tables-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 8px;
    background: #f9f9f9;
}
.riseup-table-checkbox {
    display: inline-block;
    margin: 2px 10px 2px 0;
}
.riseup-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.riseup-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}
.riseup-modal-content {
    position: relative;
    background: #fff;
    padding: 20px 30px;
    border-radius: 4px;
    max-width: 600px;
    width: 90%;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}
.riseup-modal-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}
.riseup-warning-text {
    color: #d63638;
    font-weight: 500;
}
</style>
