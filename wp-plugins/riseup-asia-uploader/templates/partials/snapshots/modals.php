<?php
/**
 * Snapshot Dashboard — Modals Partial
 *
 * Contains Restore, Download Error, and Delete confirmation modals.
 * Included by admin-snapshots.php. Inherits $pluginSlug from parent scope.
 *
 * @package RiseupAsiaUploader
 * @since   2.6.0
 */

use RiseupAsia\Enums\RestoreModeType;

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Restore Confirmation Modal -->
<div id="restore_modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
            <?php esc_html_e('Restore Database', $pluginSlug); ?>
        </h2>
        <p class="riseup-warning-text">
            <?php esc_html_e('This will replace your current database tables with the snapshot data. A pre-restore backup will be created automatically.', $pluginSlug); ?>
        </p>
        <div id="restore_incremental_warning" style="display: none;">
            <p class="riseup-warning-text" style="background: #fff8e5; border-left: 4px solid #dba617; padding: 10px 14px; color: #664d03;">
                <span class="dashicons dashicons-randomize" style="vertical-align: middle;"></span>
                <?php esc_html_e('This is an incremental snapshot. It will be merged with its parent full snapshot during restoration.', $pluginSlug); ?>
            </p>
        </div>
        <div id="restore_options">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Snapshot', $pluginSlug); ?></th>
                    <td><strong id="restore_snapshot_name"></strong></td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="restore_mode"><?php esc_html_e('Mode', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <select id="restore_mode">
                            <option value="<?php echo RestoreModeType::Full->value; ?>"><?php esc_html_e('Full Restore (All Tables)', $pluginSlug); ?></option>
                            <option value="<?php echo RestoreModeType::Selective->value; ?>"><?php esc_html_e('Selective (Choose Tables)', $pluginSlug); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>
                            <input type="checkbox" id="restore_create_backup" checked>
                            <?php esc_html_e('Create Pre-Restore Backup', $pluginSlug); ?>
                        </label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e('Strongly recommended. Creates a snapshot before restoring.', $pluginSlug); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <p class="riseup-modal-actions">
            <button type="button" id="btn_confirm_restore" class="button button-primary" style="background: #d63638; border-color: #d63638;">
                <span class="dashicons dashicons-database-import"></span>
                <?php esc_html_e('Restore Now', $pluginSlug); ?>
            </button>
            <button type="button" id="btn_cancel_restore" class="button button-secondary">
                <?php esc_html_e('Cancel', $pluginSlug); ?>
            </button>
        </p>
    </div>
</div>

<!-- Download Error Modal -->
<div id="download_error_modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content" style="max-width: 640px;">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
            <?php esc_html_e('Download Failed', $pluginSlug); ?>
        </h2>
        <div id="download_error_summary" style="margin-bottom: 12px;">
            <p id="download_error_message" style="color: #d63638; font-weight: 500;"></p>
            <table class="form-table" style="margin: 0;">
                <tr>
                    <th scope="row" style="padding: 6px 10px 6px 0; width: 120px;"><?php esc_html_e('HTTP Status', $pluginSlug); ?></th>
                    <td style="padding: 6px 0;"><code id="download_error_status"></code></td>
                </tr>
                <tr>
                    <th scope="row" style="padding: 6px 10px 6px 0;"><?php esc_html_e('Plugin Version', $pluginSlug); ?></th>
                    <td style="padding: 6px 0;"><code id="download_error_version"></code></td>
                </tr>
                <tr>
                    <th scope="row" style="padding: 6px 10px 6px 0;"><?php esc_html_e('Timestamp', $pluginSlug); ?></th>
                    <td style="padding: 6px 0;"><code id="download_error_timestamp"></code></td>
                </tr>
            </table>
        </div>
        <div id="download_error_stack_section" style="display: none;">
            <h3 style="margin: 10px 0 6px; font-size: 13px; color: #7b1fa2;">
                <span class="dashicons dashicons-editor-code" style="vertical-align: middle; color: #7b1fa2;"></span>
                <?php esc_html_e('PHP Stack Trace', $pluginSlug); ?>
            </h3>
            <pre id="download_error_stack" class="riseup-stack-trace"></pre>
        </div>
        <div id="download_error_backend_section" style="display: none;">
            <h3 style="margin: 10px 0 6px; font-size: 13px; color: #b45309;">
                <span class="dashicons dashicons-admin-tools" style="vertical-align: middle; color: #b45309;"></span>
                <?php esc_html_e('Backend Details', $pluginSlug); ?>
            </h3>
            <pre id="download_error_backend" class="riseup-stack-trace" style="border-color: #fbbf24; background: #fffbeb;"></pre>
        </div>
        <p class="riseup-modal-actions">
            <button type="button" id="btn_copy_download_error" class="button button-secondary">
                <span class="dashicons dashicons-clipboard" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                <?php esc_html_e('Copy Report', $pluginSlug); ?>
            </button>
            <button type="button" id="btn_close_download_error" class="button button-primary">
                <?php esc_html_e('Close', $pluginSlug); ?>
            </button>
        </p>
    </div>
</div>

<!-- Delete Confirmation Modal (for cascade warnings) -->
<div id="delete_modal" class="riseup-modal" style="display: none;">
    <div class="riseup-modal-overlay"></div>
    <div class="riseup-modal-content">
        <h2>
            <span class="dashicons dashicons-trash" style="color: #d63638;"></span>
            <?php esc_html_e('Delete Snapshot', $pluginSlug); ?>
        </h2>
        <p id="delete_message"></p>
        <div id="delete_cascade_warning" style="display: none;">
            <p class="riseup-warning-text" style="background: #fef3f2; border-left: 4px solid #d63638; padding: 10px 14px;">
                <span class="dashicons dashicons-warning" style="vertical-align: middle; color: #d63638;"></span>
                <span id="delete_cascade_text"></span>
            </p>
        </div>
        <p class="riseup-modal-actions">
            <button type="button" id="btn_confirm_delete" class="button button-primary" style="background: #d63638; border-color: #d63638;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Delete', $pluginSlug); ?>
            </button>
            <button type="button" id="btn_cancel_delete" class="button button-secondary">
                <?php esc_html_e('Cancel', $pluginSlug); ?>
            </button>
        </p>
    </div>
</div>
