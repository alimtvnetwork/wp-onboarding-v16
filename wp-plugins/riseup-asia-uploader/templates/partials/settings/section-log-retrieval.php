<?php
/**
 * Settings Partial — PHP Log Retrieval card.
 *
 * Variables expected: $pluginSlug, $settings.
 *
 * @package RiseupAsiaUploader
 * @since   1.64.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;
?>
<!-- PHP Log Retrieval Settings -->
<div class="riseup-card">
    <h2>
        <span class="dashicons dashicons-media-text"></span>
        <?php esc_html_e('PHP Log Retrieval (Remote API)', $pluginSlug); ?>
    </h2>
    <p class="description">
        <?php esc_html_e('Controls which log files are included when the Go backend requests PHP logs via the /error-logs endpoint. This endpoint returns the raw log file contents as JSON for remote diagnostics.', $pluginSlug); ?>
    </p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="log_include_error"><?php esc_html_e('Include Error Log', $pluginSlug); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox" 
                           id="log_include_error"
                           name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][include_error_log]" 
                           value="1" 
                           <?php $isIncludeErrorLog = BooleanHelpers::hasValue($settings['log_retrieval']['include_error_log'] ?? null); checked($isIncludeErrorLog); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Include error.txt (errors and warnings only). Enabled by default — this is the most important log for diagnostics.', $pluginSlug); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="log_include_full"><?php esc_html_e('Include Full Log', $pluginSlug); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox" 
                           id="log_include_full"
                           name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][include_full_log]" 
                           value="1" 
                           <?php $isIncludeFullLog = BooleanHelpers::hasValue($settings['log_retrieval']['include_full_log'] ?? null); checked($isIncludeFullLog); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Include log.txt (all log levels including INFO and DEBUG). Disabled by default — can be very large.', $pluginSlug); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="log_include_stacktrace"><?php esc_html_e('Include Stack Trace Log', $pluginSlug); ?></label>
            </th>
            <td>
                <label class="toggle-switch">
                    <input type="checkbox" 
                           id="log_include_stacktrace"
                           name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][include_stacktrace]" 
                           value="1" 
                           <?php $isIncludeStacktrace = BooleanHelpers::hasValue($settings['log_retrieval']['include_stacktrace'] ?? null); checked($isIncludeStacktrace); ?>>
                    <span class="toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Include stacktrace.txt (10-frame PHP backtraces for every error). Enabled by default — essential for deep diagnostics.', $pluginSlug); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="log_max_lines"><?php esc_html_e('Max Lines', $pluginSlug); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="log_max_lines"
                       name="<?php echo esc_attr(OptionNameType::PluginSettings->value); ?>[log_retrieval][max_lines]" 
                       value="<?php echo esc_attr($settings['log_retrieval']['max_lines']); ?>" 
                       min="50" max="5000" step="50"
                       class="small-text">
                <p class="description">
                    <?php esc_html_e('Maximum number of lines to return per log file (most recent lines, tail). Range: 50–5000.', $pluginSlug); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Endpoint', $pluginSlug); ?></th>
            <td>
                <code><?php echo esc_html(rest_url(PluginConfigType::apiFullNamespace() . '/' . EndpointType::ErrorLogs->value)); ?></code>
                <p class="description"><?php esc_html_e('GET request with Basic Auth. Returns JSON with error_log, full_log, and/or stacktrace_log fields.', $pluginSlug); ?></p>
            </td>
        </tr>
    </table>
</div>
