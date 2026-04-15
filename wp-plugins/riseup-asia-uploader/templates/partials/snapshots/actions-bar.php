<?php
/**
 * Snapshots Partial — Actions bar with create, import, refresh buttons.
 *
 * Variables expected: $pluginSlug.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
?>
<!-- Actions Bar -->
<div class="riseup-card riseup-snapshots-actions">
    <div class="riseup-actions-row">
        <button type="button" id="btn_snapshot_now" class="button button-primary">
            <span class="dashicons dashicons-camera"></span>
            <?php esc_html_e('Snapshot Now', $pluginSlug); ?>
        </button>

        <button type="button" id="btn_incremental_now" class="button button-secondary">
            <span class="dashicons dashicons-randomize"></span>
            <?php esc_html_e('Incremental Backup', $pluginSlug); ?>
        </button>

        <button type="button" id="btn_import_snapshot" class="button button-secondary">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('Import Snapshot', $pluginSlug); ?>
        </button>

        <button type="button" id="btn_refresh_list" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Refresh', $pluginSlug); ?>
        </button>

        <span id="snapshot_action_status" class="riseup-inline-status"></span>
    </div>

    <!-- Snapshot Now Options (hidden by default) -->
    <div id="snapshot_options" class="riseup-snapshot-options" style="display: none;">
        <h3><?php esc_html_e('Snapshot Options', $pluginSlug); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="snapshot_scope"><?php esc_html_e('Scope', $pluginSlug); ?></label>
                </th>
                <td>
                    <select id="snapshot_scope">
                        <option value="<?php echo esc_attr(SnapshotScopeType::All->value); ?>"><?php esc_html_e('All Tables', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotScopeType::WordPress->value); ?>"><?php esc_html_e('WordPress Core Only', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotScopeType::Content->value); ?>"><?php esc_html_e('Content Only (Posts, Terms, Comments)', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotScopeType::Custom->value); ?>"><?php esc_html_e('Custom Selection', $pluginSlug); ?></option>
                    </select>
                </td>
            </tr>
            <tr id="custom_tables_row" style="display: none;">
                <th scope="row">
                    <label for="snapshot_tables"><?php esc_html_e('Tables', $pluginSlug); ?></label>
                </th>
                <td>
                    <div id="snapshot_tables_list" class="riseup-tables-list">
                        <em><?php esc_html_e('Loading tables...', $pluginSlug); ?></em>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="snapshot_provider"><?php esc_html_e('Provider', $pluginSlug); ?></label>
                </th>
                <td>
                    <select id="snapshot_provider">
                        <option value="<?php echo esc_attr(SnapshotProviderType::Auto->value); ?>"><?php esc_html_e('Auto-Detect (Recommended)', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(SnapshotProviderType::Native->value); ?>"><?php esc_html_e('Native SQLite', $pluginSlug); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" id="btn_confirm_snapshot" class="button button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php esc_html_e('Create Snapshot', $pluginSlug); ?>
            </button>
            <button type="button" id="btn_cancel_snapshot" class="button button-secondary">
                <?php esc_html_e('Cancel', $pluginSlug); ?>
            </button>
        </p>
    </div>

    <!-- Import form (hidden by default) -->
    <div id="import_form" style="display: none;">
        <h3><?php esc_html_e('Import Snapshot', $pluginSlug); ?></h3>
        <p class="description"><?php esc_html_e('Upload a snapshot ZIP archive to import.', $pluginSlug); ?></p>
        <input type="file" id="import_file" accept=".zip">
        <p>
            <button type="button" id="btn_confirm_import" class="button button-primary" disabled>
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e('Upload & Import', $pluginSlug); ?>
            </button>
            <button type="button" id="btn_cancel_import" class="button button-secondary">
                <?php esc_html_e('Cancel', $pluginSlug); ?>
            </button>
        </p>
    </div>
</div>
