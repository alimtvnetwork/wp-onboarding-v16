<?php
/**
 * Settings Partial — Plugin Information card.
 *
 * Variables expected: $pluginSlug.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PluginConfigType;
?>
<!-- Plugin Info -->
<div class="riseup-card">
    <h2><?php esc_html_e('Plugin Information', $pluginSlug); ?></h2>
    <table class="form-table">
        <tr>
            <th><?php esc_html_e('Version', $pluginSlug); ?></th>
            <td><code><?php echo esc_html(PluginConfigType::Version->value); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e('API Namespace', $pluginSlug); ?></th>
            <td><code><?php echo esc_html(PluginConfigType::apiFullNamespace()); ?></code></td>
        </tr>
        <tr>
            <th><?php esc_html_e('REST API Base', $pluginSlug); ?></th>
            <td><code><?php echo esc_url(rest_url(PluginConfigType::apiFullNamespace())); ?></code></td>
        </tr>
    </table>
</div>
