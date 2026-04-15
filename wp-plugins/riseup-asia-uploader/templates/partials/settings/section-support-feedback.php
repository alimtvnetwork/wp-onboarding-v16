<?php
/**
 * Settings Partial — Support & Feedback card.
 *
 * Variables expected: $pluginSlug, $supportSettings.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\OptionNameType;
?>
<!-- Support & Feedback Settings -->
<div class="riseup-card">
    <h2>
        <span class="dashicons dashicons-feedback"></span>
        <?php esc_html_e('Support & Feedback', $pluginSlug); ?>
    </h2>
    <p class="description">
        <?php esc_html_e('Configure where feedback and bug reports are sent from the Report / Feedback page.', $pluginSlug); ?>
    </p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="support_email"><?php esc_html_e('Support Email', $pluginSlug); ?></label>
            </th>
            <td>
                <input type="email"
                       id="support_email"
                       name="<?php echo esc_attr(OptionNameType::SupportSettings->value); ?>[support_email]"
                       value="<?php echo esc_attr($supportSettings['support_email'] ?? ''); ?>"
                       class="regular-text"
                       placeholder="support@example.com">
                <p class="description"><?php esc_html_e('Email address where feedback and bug reports will be sent.', $pluginSlug); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="fallback_url"><?php esc_html_e('Fallback Ticket URL', $pluginSlug); ?></label>
            </th>
            <td>
                <input type="url"
                       id="fallback_url"
                       name="<?php echo esc_attr(OptionNameType::SupportSettings->value); ?>[fallback_url]"
                       value="<?php echo esc_attr($supportSettings['fallback_url'] ?? ''); ?>"
                       class="regular-text"
                       placeholder="https://support.example.com/tickets/new">
                <p class="description"><?php esc_html_e('If email is not configured, users will see a link to this URL for manual ticket submission.', $pluginSlug); ?></p>
            </td>
        </tr>
    </table>
</div>
