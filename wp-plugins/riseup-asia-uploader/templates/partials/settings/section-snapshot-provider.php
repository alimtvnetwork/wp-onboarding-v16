<?php
/**
 * Settings Partial — Snapshot Provider & Scheduling.
 *
 * Variables expected: $pluginSlug, $snapshotSettings, $snapshotProviders,
 * plus extracted locals from parent partial.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Helpers\BooleanHelpers;
?>
<!-- Provider Selection -->
<h3><?php esc_html_e('Snapshot Provider', $pluginSlug); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="snap_preferred_provider"><?php esc_html_e('Preferred Provider', $pluginSlug); ?></label>
        </th>
        <td>
            <select id="snap_preferred_provider">
                <option value="<?php echo esc_attr(SnapshotProviderType::Auto->value); ?>" <?php selected($preferredProvider, SnapshotProviderType::Auto->value); ?>>
                    <?php esc_html_e('Auto-detect (recommended)', $pluginSlug); ?>
                </option>
                <?php foreach ($snapshotProviders as $provider): ?>
                    <option value="<?php echo esc_attr($provider['id']); ?>" 
                            <?php selected($preferredProvider, $provider['id']); ?>
                            <?php $isProviderUnavailable = ($provider['available'] === false); disabled($isProviderUnavailable); ?>>
                        <?php echo esc_html($provider['name']); ?>
                        <?php if ($isProviderUnavailable): ?>(<?php esc_html_e('not installed', $pluginSlug); ?>)<?php endif; ?>
                        <?php $hasProviderVersion = BooleanHelpers::hasValue($provider['version'] ?? null); if ($hasProviderVersion): ?>(v<?php echo esc_html($provider['version']); ?>)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Priority: WP Reset > UpdraftPlus > Native SQLite.', $pluginSlug); ?></p>
        </td>
    </tr>
</table>

<!-- Scheduling -->
<h3><?php esc_html_e('Scheduling', $pluginSlug); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="snap_schedule_enabled"><?php esc_html_e('Enable Scheduled Snapshots', $pluginSlug); ?></label>
        </th>
        <td>
            <label class="toggle-switch">
                <input type="checkbox" id="snap_schedule_enabled" value="1" <?php checked($scheduleEnabled); ?>>
                <span class="toggle-slider"></span>
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="snap_schedule_frequency"><?php esc_html_e('Frequency', $pluginSlug); ?></label>
        </th>
        <td>
            <select id="snap_schedule_frequency">
                <option value="<?php echo esc_attr(SnapshotFrequencyType::Manual->value); ?>" <?php selected($scheduleFrequency, SnapshotFrequencyType::Manual->value); ?>><?php esc_html_e('Manual Only', $pluginSlug); ?></option>
                <option value="<?php echo esc_attr(SnapshotFrequencyType::Hourly->value); ?>" <?php selected($scheduleFrequency, SnapshotFrequencyType::Hourly->value); ?>><?php esc_html_e('Hourly', $pluginSlug); ?></option>
                <option value="<?php echo esc_attr(SnapshotFrequencyType::Daily->value); ?>" <?php selected($scheduleFrequency, SnapshotFrequencyType::Daily->value); ?>><?php esc_html_e('Daily', $pluginSlug); ?></option>
                <option value="<?php echo esc_attr(SnapshotFrequencyType::Weekly->value); ?>" <?php selected($scheduleFrequency, SnapshotFrequencyType::Weekly->value); ?>><?php esc_html_e('Weekly', $pluginSlug); ?></option>
                <option value="<?php echo esc_attr(SnapshotFrequencyType::Monthly->value); ?>" <?php selected($scheduleFrequency, SnapshotFrequencyType::Monthly->value); ?>><?php esc_html_e('Monthly', $pluginSlug); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="snap_schedule_time"><?php esc_html_e('Time', $pluginSlug); ?></label>
        </th>
        <td>
            <input type="time" id="snap_schedule_time" value="<?php echo esc_attr($scheduleTime); ?>">
        </td>
    </tr>
    <tr id="snap_day_row" style="<?php $isHiddenFreq = SnapshotFrequencyType::tryFrom($scheduleFrequency); echo ($isHiddenFreq !== null && $isHiddenFreq->isAnyOf(SnapshotFrequencyType::Hourly, SnapshotFrequencyType::Daily, SnapshotFrequencyType::Manual)) ? 'display:none;' : ''; ?>">
        <th scope="row">
            <label for="snap_schedule_day"><?php esc_html_e('Day', $pluginSlug); ?></label>
        </th>
        <td>
            <input type="number" id="snap_schedule_day" min="1" max="28" value="<?php echo esc_attr($scheduleDay); ?>" class="small-text">
            <p class="description"><?php esc_html_e('Day of week (1=Mon, 7=Sun) for weekly, or day of month (1-28) for monthly.', $pluginSlug); ?></p>
        </td>
    </tr>
</table>

<!-- Default Scope -->
<h3><?php esc_html_e('Default Scope', $pluginSlug); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="snap_default_scope"><?php esc_html_e('Tables to Snapshot', $pluginSlug); ?></label>
        </th>
        <td>
            <select id="snap_default_scope">
                <option value="<?php echo esc_attr(SnapshotScopeType::All->value); ?>" <?php selected($defaultScope, SnapshotScopeType::All->value); ?>><?php esc_html_e('All Tables', $pluginSlug); ?></option>
                <option value="<?php echo esc_attr(SnapshotScopeType::WordPress->value); ?>" <?php selected($defaultScope, SnapshotScopeType::WordPress->value); ?>><?php esc_html_e('WordPress Core Only', $pluginSlug); ?></option>
                <option value="<?php echo esc_attr(SnapshotScopeType::Content->value); ?>" <?php selected($defaultScope, SnapshotScopeType::Content->value); ?>><?php esc_html_e('Content Only (posts, terms, comments)', $pluginSlug); ?></option>
                <option value="<?php echo esc_attr(SnapshotScopeType::Custom->value); ?>" <?php selected($defaultScope, SnapshotScopeType::Custom->value); ?>><?php esc_html_e('Custom Selection', $pluginSlug); ?></option>
            </select>
        </td>
    </tr>
</table>
