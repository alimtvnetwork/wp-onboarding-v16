<?php
/**
 * Admin License Page Template
 *
 * @package RiseupAsiaUploader
 * @since   2.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AjaxActionType;
use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;

$hasLicenseKey = BooleanHelpers::hasValue($licenseKey ?? null);
$hasStatus = BooleanHelpers::hasValue($licenseStatus ?? null);
$hasCheckedAt = BooleanHelpers::hasValue($checkedAt ?? null);
$isActive = ($licenseStatus === 'active');
$isInactive = ($licenseStatus === 'inactive');
$isExpired = ($licenseStatus === 'expired');

// Validation result from LicenseManager::validateLicense()
$hasValidation = isset($validation) && is_array($validation);
$validationPlan = $hasValidation && isset($validation['plan']) ? $validation['plan'] : '';
$validationExpiry = $hasValidation && isset($validation['expires_at']) ? $validation['expires_at'] : '';
$validationDomain = $hasValidation && isset($validation['domain']) ? $validation['domain'] : '';
$hasValidationPlan = BooleanHelpers::hasValue($validationPlan);
$hasValidationExpiry = BooleanHelpers::hasValue($validationExpiry);
?>
<div class="wrap riseup-admin">
    <h1>
        <span class="dashicons dashicons-admin-network"></span>
        <?php echo esc_html($pluginName . ' - ' . __('License', $pluginSlug)); ?>
        <span class="riseup-version-badge">v<?php echo esc_html(PluginConfigType::Version->value); ?></span>
    </h1>

    <p class="description">
        <?php esc_html_e('Manage your license key to unlock premium features and receive automatic updates.', $pluginSlug); ?>
    </p>

    <!-- License Status Card -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-shield"></span>
            <?php esc_html_e('License Status', $pluginSlug); ?>
        </h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Status', $pluginSlug); ?></th>
                <td>
                    <?php if ($isActive): ?>
                        <span class="license-status-badge license-active">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Active', $pluginSlug); ?>
                        </span>
                    <?php elseif ($isExpired): ?>
                        <span class="license-status-badge license-expired">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Expired', $pluginSlug); ?>
                        </span>
                    <?php elseif ($isInactive): ?>
                        <span class="license-status-badge license-inactive">
                            <span class="dashicons dashicons-minus"></span>
                            <?php esc_html_e('Inactive', $pluginSlug); ?>
                        </span>
                    <?php else: ?>
                        <span class="license-status-badge license-none">
                            <span class="dashicons dashicons-editor-help"></span>
                            <?php esc_html_e('No License', $pluginSlug); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($hasLicenseKey): ?>
            <tr>
                <th scope="row"><?php esc_html_e('License Key', $pluginSlug); ?></th>
                <td>
                    <code class="license-key-display"><?php echo esc_html(substr($licenseKey, 0, 8) . str_repeat('•', max(0, strlen($licenseKey) - 12)) . substr($licenseKey, -4)); ?></code>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($hasValidationPlan): ?>
            <tr>
                <th scope="row"><?php esc_html_e('Plan', $pluginSlug); ?></th>
                <td><strong><?php echo esc_html(ucfirst($validationPlan)); ?></strong></td>
            </tr>
            <?php endif; ?>
            <?php if ($hasValidationExpiry): ?>
            <tr>
                <th scope="row"><?php esc_html_e('Expires', $pluginSlug); ?></th>
                <td><?php echo esc_html($validationExpiry); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($hasCheckedAt): ?>
            <tr>
                <th scope="row"><?php esc_html_e('Last Checked', $pluginSlug); ?></th>
                <td><?php echo esc_html($checkedAt); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- License Key Input -->
    <div class="riseup-card">
        <h2>
            <span class="dashicons dashicons-admin-keys"></span>
            <?php esc_html_e('Enter License Key', $pluginSlug); ?>
        </h2>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="license_key_input"><?php esc_html_e('License Key', $pluginSlug); ?></label>
                </th>
                <td>
                    <input type="text" id="license_key_input" class="regular-text"
                           value="<?php echo esc_attr($licenseKey); ?>"
                           placeholder="<?php esc_attr_e('Enter your license key...', $pluginSlug); ?>">
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" id="btn-license-save" class="button button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php esc_html_e('Save & Validate', $pluginSlug); ?>
            </button>

            <?php if ($hasLicenseKey && !$isActive): ?>
            <button type="button" id="btn-license-activate" class="button button-secondary">
                <span class="dashicons dashicons-unlock"></span>
                <?php esc_html_e('Activate', $pluginSlug); ?>
            </button>
            <?php endif; ?>

            <?php if ($isActive): ?>
            <button type="button" id="btn-license-deactivate" class="button button-secondary">
                <span class="dashicons dashicons-lock"></span>
                <?php esc_html_e('Deactivate', $pluginSlug); ?>
            </button>
            <?php endif; ?>

            <button type="button" id="btn-license-refresh" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh Status', $pluginSlug); ?>
            </button>

            <?php if ($hasLicenseKey): ?>
            <button type="button" id="btn-license-remove" class="button button-link-delete">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Remove Key', $pluginSlug); ?>
            </button>
            <?php endif; ?>

            <span id="license-action-status" class="riseup-inline-status"></span>
        </p>
    </div>
</div>

<style>
.license-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}
.license-active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #a3d9a5;
}
.license-inactive {
    background: #f0f0f1;
    color: #50575e;
    border: 1px solid #c3c4c7;
}
.license-expired {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.license-none {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
}
.license-key-display {
    font-size: 13px;
    letter-spacing: 1px;
}
.riseup-inline-status {
    margin-left: 10px;
    font-size: 13px;
}
.riseup-inline-status.success { color: #46b450; }
.riseup-inline-status.error { color: #dc3232; }
.riseup-admin .button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
    margin-right: 2px;
}
</style>

<script type="text/javascript">
// =========================================================================
// ENUM CONSTANTS (from PHP — prevents magic strings in JS)
// =========================================================================
var LICENSE_AJAX = {
    SAVE:       '<?php echo esc_js(AjaxActionType::LicenseSave->value); ?>',
    ACTIVATE:   '<?php echo esc_js(AjaxActionType::LicenseActivate->value); ?>',
    DEACTIVATE: '<?php echo esc_js(AjaxActionType::LicenseDeactivate->value); ?>',
    REMOVE:     '<?php echo esc_js(AjaxActionType::LicenseRemove->value); ?>',
    REFRESH:    '<?php echo esc_js(AjaxActionType::LicenseRefresh->value); ?>'
};

var LICENSE_NONCE = 'riseup_license_nonce';

jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce('riseup_license_nonce'); ?>';
    var $status = $('#license-action-status');

    function showStatus(message, isError) {
        $status.text(message)
            .removeClass('success error')
            .addClass(isError ? 'error' : 'success')
            .show();
        setTimeout(function() { $status.fadeOut(); }, 5000);
    }

    function licenseRequest(action, extraData) {
        var data = $.extend({ action: action, _nonce: nonce }, extraData || {});
        return $.post(ajaxurl, data);
    }

    // Save & Validate
    $('#btn-license-save').on('click', function() {
        var key = $('#license_key_input').val().trim();
        if (!key) {
            showStatus('<?php echo esc_js(__('Please enter a license key.', 'riseup-asia-uploader')); ?>', true);
            return;
        }

        var $btn = $(this).prop('disabled', true);

        licenseRequest(LICENSE_AJAX.SAVE, { license_key: key })
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : '<?php echo esc_js(__('Validation failed.', 'riseup-asia-uploader')); ?>'), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus('<?php echo esc_js(__('Request failed.', 'riseup-asia-uploader')); ?>', true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Activate
    $('#btn-license-activate').on('click', function() {
        var $btn = $(this).prop('disabled', true);

        licenseRequest(LICENSE_AJAX.ACTIVATE)
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : '<?php echo esc_js(__('Activation failed.', 'riseup-asia-uploader')); ?>'), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus('<?php echo esc_js(__('Request failed.', 'riseup-asia-uploader')); ?>', true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Deactivate
    $('#btn-license-deactivate').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to deactivate this license?', 'riseup-asia-uploader')); ?>')) return;

        var $btn = $(this).prop('disabled', true);

        licenseRequest(LICENSE_AJAX.DEACTIVATE)
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : '<?php echo esc_js(__('Deactivation failed.', 'riseup-asia-uploader')); ?>'), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus('<?php echo esc_js(__('Request failed.', 'riseup-asia-uploader')); ?>', true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Remove
    $('#btn-license-remove').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Remove the license key entirely? This cannot be undone.', 'riseup-asia-uploader')); ?>')) return;

        var $btn = $(this).prop('disabled', true);

        licenseRequest(LICENSE_AJAX.REMOVE)
            .done(function(r) {
                showStatus(r.success ? r.data.message : '<?php echo esc_js(__('Removal failed.', 'riseup-asia-uploader')); ?>', !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus('<?php echo esc_js(__('Request failed.', 'riseup-asia-uploader')); ?>', true); })
            .always(function() { $btn.prop('disabled', false); });
    });

    // Refresh
    $('#btn-license-refresh').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        $btn.find('.dashicons').addClass('spin');

        licenseRequest(LICENSE_AJAX.REFRESH)
            .done(function(r) {
                showStatus(r.success ? r.data.message : (r.data ? r.data.message : '<?php echo esc_js(__('Refresh failed.', 'riseup-asia-uploader')); ?>'), !r.success);
                if (r.success) setTimeout(function() { location.reload(); }, 1500);
            })
            .fail(function() { showStatus('<?php echo esc_js(__('Request failed.', 'riseup-asia-uploader')); ?>', true); })
            .always(function() { $btn.prop('disabled', false).find('.dashicons').removeClass('spin'); });
    });
});
</script>

<style>
.dashicons.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    100% { transform: rotate(360deg); }
}
</style>
