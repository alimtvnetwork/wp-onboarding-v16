<?php
/**
 * Admin Feedback Page Template
 *
 * Dedicated page for submitting feedback / bug reports.
 * Form is always visible. A warning banner shows if email is not configured.
 *
 * @package RiseupAsiaUploader
 * @since   2.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\PluginConfigType;

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;
?>
<div class="wrap riseup-admin riseup-feedback-page">
    <?php
    $pageIcon = 'dashicons-feedback';
    $pageTitle = $pluginName . ' - ' . __('Report / Feedback', $pluginSlug);
    $pageDescription = __('Submit bug reports, feature requests, or general feedback to the support team.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <div id="riseup-feedback-status" style="display: none;"></div>

    <!-- Warning banner — shown only when email is not configured -->
    <div id="riseup-feedback-email-warning" class="riseup-card riseup-feedback-warning-card" style="display: none;">
        <div class="riseup-feedback-warning-inner">
            <span class="dashicons dashicons-warning"></span>
            <div class="riseup-feedback-warning-content">
                <strong><?php esc_html_e('Email Not Configured', $pluginSlug); ?></strong>
                <p><?php esc_html_e('A support email address must be configured before feedback can be sent. You can still fill out the form below.', $pluginSlug); ?></p>
                <div class="riseup-feedback-warning-actions">
                    <a href="<?php echo esc_url(AdminPageType::Settings->adminUrl()); ?>" class="button button-small">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Go to Settings', $pluginSlug); ?>
                    </a>
                    <span id="riseup-feedback-fallback" style="display: none;">
                        <?php esc_html_e('Or submit a ticket manually:', $pluginSlug); ?>
                        <a id="riseup-feedback-fallback-link" href="#" target="_blank" rel="noopener noreferrer"></a>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form is always visible -->
    <div id="riseup-feedback-form-container" class="riseup-card">
        <h2>
            <span class="dashicons dashicons-email-alt"></span>
            <?php esc_html_e('Send Feedback', $pluginSlug); ?>
        </h2>

        <form id="riseup-feedback-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="feedback-subject"><?php esc_html_e('Subject', $pluginSlug); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="feedback-subject" name="subject" class="regular-text" maxlength="200" required
                               placeholder="<?php esc_attr_e('Brief summary of the issue or feedback', $pluginSlug); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="feedback-description"><?php esc_html_e('Description', $pluginSlug); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <textarea id="feedback-description" name="description" rows="8" class="large-text" minlength="20" required
                                  placeholder="<?php esc_attr_e('Describe the issue or feedback in detail (minimum 20 characters)', $pluginSlug); ?>"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="feedback-screenshots"><?php esc_html_e('Screenshots', $pluginSlug); ?></label>
                    </th>
                    <td>
                        <input type="file" id="feedback-screenshots" name="screenshots[]" multiple accept="image/jpeg,image/png,image/gif,image/webp">
                        <p class="description">
                            <?php esc_html_e('Optional. Up to 3 images, max 2 MB each. Accepted: JPG, PNG, GIF, WebP.', $pluginSlug); ?>
                        </p>
                        <div id="feedback-file-list" class="feedback-file-list"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Include Logs', $pluginSlug); ?></th>
                    <td>
                        <label class="riseup-feedback-checkbox-label">
                            <input type="checkbox" id="feedback-include-logs" name="include_logs" value="1">
                            <?php esc_html_e('Attach log files as a ZIP (error log, stack trace, info log)', $pluginSlug); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('If checked, the current log files will be compressed and attached to the email automatically.', $pluginSlug); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('System Information', $pluginSlug); ?></th>
                    <td>
                        <label class="riseup-feedback-checkbox-label">
                            <input type="checkbox" id="feedback-system-info" name="include_system_info" value="1" checked>
                            <?php esc_html_e('Include system info (PHP version, WordPress version, plugin version, site URL)', $pluginSlug); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" id="feedback-submit-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e('Send Feedback', $pluginSlug); ?>
                </button>
                <span id="feedback-spinner" class="spinner" style="float: none;"></span>
            </p>
        </form>
    </div>
</div>
