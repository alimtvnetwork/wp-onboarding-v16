<?php
/**
 * Settings Partial — Endpoint Configuration card.
 *
 * Variables expected: $pluginSlug, $settings, $endpointGroups.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Helpers\BooleanHelpers;
?>
<!-- Endpoint Configuration (Grouped) -->
<div class="riseup-card">
    <h2><?php esc_html_e('API Endpoints Configuration', $pluginSlug); ?></h2>
    <p class="description">
        <?php esc_html_e('Enable or disable specific API endpoints and configure authentication requirements.', $pluginSlug); ?>
    </p>

    <table class="wp-list-table widefat fixed striped riseup-endpoints-table">
        <thead>
            <tr>
                <th class="column-endpoint"><?php esc_html_e('Endpoint', $pluginSlug); ?></th>
                <th class="column-description"><?php esc_html_e('Description', $pluginSlug); ?></th>
                <th class="column-enabled"><?php esc_html_e('Enabled', $pluginSlug); ?></th>
                <th class="column-auth"><?php esc_html_e('Auth Required', $pluginSlug); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($endpointGroups as $groupKey => $group): ?>
                <tr class="endpoint-group-header">
                    <td colspan="4">
                        <span class="dashicons <?php echo esc_attr($group['icon']); ?>"></span>
                        <?php echo esc_html($group['label']); ?>
                    </td>
                </tr>
                <?php foreach ($group['endpoints'] as $endpoint => $meta): ?>
                    <tr>
                        <td class="column-endpoint">
                            <strong><?php echo esc_html($meta['label']); ?></strong>
                            <br>
                            <code class="endpoint-path">/<?php echo esc_html($endpoint); ?></code>
                        </td>
                        <td class="column-description">
                            <?php echo esc_html($meta['desc']); ?>
                        </td>
                        <td class="column-enabled">
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[endpoints][<?php echo esc_attr($endpoint); ?>][enabled]" 
                                       value="1" 
                                       <?php $isEpEnabled = BooleanHelpers::hasValue($settings['endpoints'][$endpoint]['enabled'] ?? null); checked($isEpEnabled); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </td>
                        <td class="column-auth">
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[endpoints][<?php echo esc_attr($endpoint); ?>][auth_required]" 
                                       value="1" 
                                       <?php $isAuthReq = BooleanHelpers::hasValue($settings['endpoints'][$endpoint]['auth_required'] ?? null); checked($isAuthReq); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="riseup-warning">
        <span class="dashicons dashicons-warning"></span>
        <?php esc_html_e('Warning: Disabling authentication can expose your site to unauthorized access. Only disable for development/testing purposes.', $pluginSlug); ?>
    </p>
</div>
