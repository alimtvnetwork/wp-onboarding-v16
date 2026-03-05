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

use RiseupAsia\Enums\LicenseStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;

$hasLicenseKey = BooleanHelpers::hasValue($licenseKey ?? null);
$hasStatus = BooleanHelpers::hasValue($licenseStatus ?? null);
$hasCheckedAt = BooleanHelpers::hasValue($checkedAt ?? null);
$isActive = ($licenseStatus === LicenseStatusType::Active->value);
$isInactive = ($licenseStatus === LicenseStatusType::Inactive->value);
$isExpired = ($licenseStatus === LicenseStatusType::Expired->value);

// Validation result from LicenseManager::validateLicense()
$hasValidation = isset($validation) && is_array($validation);
$validationPlan = $hasValidation && isset($validation['plan']) ? $validation['plan'] : '';
$validationExpiry = $hasValidation && isset($validation['expires_at']) ? $validation['expires_at'] : '';
$validationDomain = $hasValidation && isset($validation['domain']) ? $validation['domain'] : '';
$hasValidationPlan = BooleanHelpers::hasValue($validationPlan);
$hasValidationExpiry = BooleanHelpers::hasValue($validationExpiry);
?>
<div class="wrap riseup-admin">
    <?php
    $pageIcon = 'dashicons-admin-network';
    $pageTitle = $pluginName . ' - ' . __('License', $pluginSlug);
    $pageDescription = __('Manage your license key to unlock premium features and receive automatic updates.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

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
