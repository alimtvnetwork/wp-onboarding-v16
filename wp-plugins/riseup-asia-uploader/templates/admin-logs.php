<?php
/**
 * Admin Logs Page Template
 *
 * @package RiseupAsiaUploader
 * @since   1.5.0
 * @updated 1.9.0 - Added source machine and triggered_by columns
 */

use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\LogColumnType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Enums\TriggerSourceType;
use RiseupAsia\Enums\UploadSourceType;

if (!defined('ABSPATH')) {
    exit;
}

$pluginName = PluginConfigType::Name->value;
$pluginSlug = PluginConfigType::Slug->value;

// Trigger source labels for display
$triggerLabels = array(
    TriggerSourceType::Api->value       => __('API', $pluginSlug),
    TriggerSourceType::Dashboard->value => __('Dashboard', $pluginSlug),
    TriggerSourceType::Agent->value     => __('Agent Push', $pluginSlug),
    TriggerSourceType::Cron->value      => __('Cron', $pluginSlug),
    TriggerSourceType::Cli->value       => __('WP-CLI', $pluginSlug),
);

// Trigger source CSS classes for color coding
$triggerClasses = array(
    TriggerSourceType::Api->value       => 'trigger-api',
    TriggerSourceType::Dashboard->value => 'trigger-dashboard',
    TriggerSourceType::Agent->value     => 'trigger-agent',
    TriggerSourceType::Cron->value      => 'trigger-cron',
    TriggerSourceType::Cli->value       => 'trigger-cli',
);

// Upload source labels for display
$uploadSourceLabels = array(
    UploadSourceType::Script->value  => __('Upload Script', $pluginSlug),
    UploadSourceType::RestApi->value => __('REST API', $pluginSlug),
    UploadSourceType::AdminUi->value => __('Admin UI', $pluginSlug),
    UploadSourceType::WpCli->value   => __('WP-CLI', $pluginSlug),
);

// Upload source CSS classes for color coding
$uploadSourceClasses = array(
    UploadSourceType::Script->value  => 'source-script',
    UploadSourceType::RestApi->value => 'source-api',
    UploadSourceType::AdminUi->value => 'source-admin',
    UploadSourceType::WpCli->value   => 'source-cli',
);
?>
<div class="wrap riseup-admin" style="padding: 10px 20px 20px 10px;">
    <?php
    $pageIcon = 'dashicons-list-view';
    $pageTitle = $pluginName . ' - ' . __('Activity Logs', $pluginSlug);
    $pageDescription = __('View all API activity and operations performed through this plugin.', $pluginSlug);
    include __DIR__ . '/partials/shared/page-header.php';
    ?>

    <!-- Filters -->
    <div class="riseup-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr(AdminPageType::Logs->value); ?>">
            
            <div class="filter-row">
                <label>
                    <span><?php esc_html_e('Action:', $pluginSlug); ?></span>
                    <select name="filter_action">
                        <option value=""><?php esc_html_e('All Actions', $pluginSlug); ?></option>
                        <?php foreach ($actionLabels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($filters['action'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Status:', $pluginSlug); ?></span>
                    <select name="filter_status">
                        <option value=""><?php esc_html_e('All Statuses', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(StatusType::Success->value); ?>" <?php selected($filters['status'], StatusType::Success->value); ?>><?php esc_html_e('Success', $pluginSlug); ?></option>
                        <option value="<?php echo esc_attr(StatusType::Failed->value); ?>" <?php selected($filters['status'], StatusType::Failed->value); ?>><?php esc_html_e('Failed', $pluginSlug); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Trigger:', $pluginSlug); ?></span>
                    <select name="filter_triggered_by">
                        <option value=""><?php esc_html_e('All Sources', $pluginSlug); ?></option>
                        <?php foreach ($triggerLabels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($filters['triggered_by']) ? $filters['triggered_by'] : '', $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Upload Via:', $pluginSlug); ?></span>
                    <select name="filter_upload_source">
                        <option value=""><?php esc_html_e('All Methods', $pluginSlug); ?></option>
                        <?php foreach ($uploadSourceLabels as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($filters['upload_source']) ? $filters['upload_source'] : '', $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('User:', $pluginSlug); ?></span>
                    <input type="text" name="filter_user" value="<?php echo esc_attr($filters['user']); ?>" placeholder="<?php esc_attr_e('Username', $pluginSlug); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('Plugin:', $pluginSlug); ?></span>
                    <input type="text" name="filter_plugin" value="<?php echo esc_attr($filters['plugin']); ?>" placeholder="<?php esc_attr_e('Plugin slug', $pluginSlug); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('Source Machine:', $pluginSlug); ?></span>
                    <input type="text" name="filter_source_machine" value="<?php echo esc_attr(isset($filters['source_machine']) ? $filters['source_machine'] : ''); ?>" placeholder="<?php esc_attr_e('Hostname', $pluginSlug); ?>">
                </label>
            </div>
            
            <div class="filter-row filter-row-secondary">
                <label>
                    <span><?php esc_html_e('From:', $pluginSlug); ?></span>
                    <input type="date" name="filter_from" value="<?php echo esc_attr($filters['from']); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('To:', $pluginSlug); ?></span>
                    <input type="date" name="filter_to" value="<?php echo esc_attr($filters['to']); ?>">
                </label>

                <button type="submit" class="button button-primary"><?php esc_html_e('Filter', $pluginSlug); ?></button>
                <a href="<?php echo esc_url(AdminPageType::Logs->adminUrl()); ?>" class="button"><?php esc_html_e('Reset', $pluginSlug); ?></a>
            </div>
        </form>
    </div>

    <!-- Stats -->
    <div class="riseup-stats">
        <span class="stat-item">
            <strong><?php echo esc_html($total); ?></strong>
            <?php esc_html_e('total records', $pluginSlug); ?>
        </span>
        <?php if ($page > 1 || $page < $totalPages): ?>
            <span class="stat-item">
                <?php esc_html_e('Page', $pluginSlug); ?> <?php echo esc_html($page); ?> 
                <?php esc_html_e('of', $pluginSlug); ?> <?php echo esc_html($totalPages); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Logs Table -->
    <table class="wp-list-table widefat fixed striped riseup-logs-table">
        <thead>
            <tr>
                <th class="column-id"><?php esc_html_e('ID', $pluginSlug); ?></th>
                <th class="column-timestamp"><?php esc_html_e('Time', $pluginSlug); ?></th>
                <th class="column-action"><?php esc_html_e('Action', $pluginSlug); ?></th>
                <th class="column-plugin"><?php esc_html_e('Plugin/Target', $pluginSlug); ?></th>
                <th class="column-version"><?php esc_html_e('Version', $pluginSlug); ?></th>
                <th class="column-trigger"><?php esc_html_e('Trigger', $pluginSlug); ?></th>
                <th class="column-upload-source"><?php esc_html_e('Upload Via', $pluginSlug); ?></th>
                <th class="column-source"><?php esc_html_e('Source', $pluginSlug); ?></th>
                <th class="column-user"><?php esc_html_e('User', $pluginSlug); ?></th>
                <th class="column-status"><?php esc_html_e('Status', $pluginSlug); ?></th>
                <th class="column-details"><?php esc_html_e('Details', $pluginSlug); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="11" class="no-items"><?php esc_html_e('No activity logs found.', $pluginSlug); ?></td>
                </tr>
            <?php else: ?>
                <?php 
                $currentDateGroup = '';
                foreach ($logs as $log): 
                    $triggeredBy = $this->logString($log, LogColumnType::TriggeredBy);
                    $sourceMachine = $this->logString($log, LogColumnType::SourceMachine);
                    $pluginVersion = $this->logString($log, LogColumnType::PluginVersion);
                    $uploadSource = $this->logString($log, LogColumnType::UploadSource);
                    $triggerClass = isset($triggerClasses[$triggeredBy]) ? $triggerClasses[$triggeredBy] : 'trigger-unknown';
                    $triggerLabel = isset($triggerLabels[$triggeredBy]) ? $triggerLabels[$triggeredBy] : ($triggeredBy ?: '—');
                    $uploadSourceClass = isset($uploadSourceClasses[$uploadSource]) ? $uploadSourceClasses[$uploadSource] : 'source-unknown';
                    $uploadSourceLabel = isset($uploadSourceLabels[$uploadSource]) ? $uploadSourceLabels[$uploadSource] : ($uploadSource ?: '—');

                    // Extract named booleans for P3 compliance
                    $hasLogDetails = BooleanHelpers::hasValue($log[LogColumnType::Details->value] ?? null);
                    $hasErrorMsg = BooleanHelpers::hasValue($log[LogColumnType::ErrorMsg->value] ?? null);
                    $hasDetailsOrError = $hasLogDetails || $hasErrorMsg;
                    $hasPluginSlug = BooleanHelpers::hasValue($log[LogColumnType::PluginSlug->value] ?? null);
                    $hasPluginFile = BooleanHelpers::hasValue($log[LogColumnType::PluginFile->value] ?? null) && ($log[LogColumnType::PluginFile->value] ?? '') !== ($log[LogColumnType::PluginSlug->value] ?? '');
                    $hasPostId = BooleanHelpers::hasValue($log[LogColumnType::PostId->value] ?? null);
                    $hasPluginVersion = BooleanHelpers::hasValue($pluginVersion);
                    $hasTriggeredBy = BooleanHelpers::hasValue($triggeredBy);
                    $hasUploadSource = BooleanHelpers::hasValue($uploadSource);
                    $hasSourceMachine = BooleanHelpers::hasValue($sourceMachine);
                    $hasIpAddress = BooleanHelpers::hasValue($log[LogColumnType::IpAddress->value] ?? null) && ($log[LogColumnType::IpAddress->value] ?? '') !== '0.0.0.0';
                    $hasUserId = BooleanHelpers::hasValue($log[LogColumnType::UserId->value] ?? null);
                    
                    // Date grouping
                    $logTimestamp = strtotime($log[LogColumnType::CreatedAt->value]);
                    $logDate = DateHelper::formatDateOnly($logTimestamp);
                    $logDateDisplay = DateHelper::formatDisplayDate($logTimestamp);
                    $logTimeDisplay = DateHelper::formatDisplayTime($logTimestamp);
                    
                    // Insert date group header when date changes
                    if ($logDate !== $currentDateGroup):
                        $currentDateGroup = $logDate;
                        $relativeDayKey = DateHelper::relativeDayKey($logTimestamp);
                        if ($relativeDayKey === 'today') {
                            $dateLabel = __('Today', $pluginSlug) . ' — ' . $logDateDisplay;
                        } elseif ($relativeDayKey === 'yesterday') {
                            $dateLabel = __('Yesterday', $pluginSlug) . ' — ' . $logDateDisplay;
                        } else {
                            $dateLabel = $logDateDisplay;
                        }
                ?>
                    <tr class="date-group-header">
                        <td colspan="11">
                            <span class="date-group-label"><?php echo esc_html($dateLabel); ?></span>
                        </td>
                    </tr>
                <?php endif; ?>
                    <tr class="riseup-log-row <?php echo $hasDetailsOrError ? 'has-details' : ''; ?>" 
                        <?php if ($hasLogDetails): ?>
                            data-details="<?php echo esc_attr(json_encode($log[LogColumnType::Details->value])); ?>"
                        <?php elseif ($hasErrorMsg): ?>
                            data-details="<?php echo esc_attr(json_encode(array(ResponseKeyType::Error->value => $log[LogColumnType::ErrorMsg->value]))); ?>"
                        <?php endif; ?>>
                        <td class="column-id"><?php echo esc_html($log[LogColumnType::Id->value]); ?></td>
                        <td class="column-timestamp">
                            <span class="timestamp" title="<?php echo esc_attr($log[LogColumnType::CreatedAt->value]); ?>">
                                <?php echo esc_html($logTimeDisplay); ?>
                            </span>
                        </td>
                        <td class="column-action">
                            <span class="action-badge action-<?php echo esc_attr($log[LogColumnType::Action->value]); ?>">
                                <?php echo esc_html($actionLabels[$log[LogColumnType::Action->value]] ?? $log[LogColumnType::Action->value]); ?>
                            </span>
                        </td>
                        <td class="column-plugin">
                            <?php if ($hasPluginSlug): ?>
                                <span class="plugin-target-badge"><?php echo esc_html($log[LogColumnType::PluginSlug->value]); ?></span>
                                <?php if ($hasPluginFile): ?>
                                    <br><small class="plugin-file" title="<?php echo esc_attr($log[LogColumnType::PluginFile->value]); ?>"><?php echo esc_html($log[LogColumnType::PluginFile->value]); ?></small>
                                <?php endif; ?>
                            <?php elseif ($hasPostId): ?>
                                <span class="plugin-target-badge target-post"><?php esc_html_e('Post', $pluginSlug); ?> #<?php echo esc_html($log[LogColumnType::PostId->value]); ?></span>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-version">
                            <?php if ($hasPluginVersion): ?>
                                <?php $isCurrentVersion = ($pluginVersion === PluginConfigType::Version->value); ?>
                                <?php if ($isCurrentVersion): ?>
                                    <code class="version-badge version-current">v<?php echo esc_html($pluginVersion); ?></code>
                                <?php else: ?>
                                    <code class="version-badge version-old">v<?php echo esc_html($pluginVersion); ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-trigger">
                            <?php if ($hasTriggeredBy): ?>
                                <span class="trigger-badge <?php echo esc_attr($triggerClass); ?>" title="<?php echo esc_attr($triggeredBy); ?>">
                                    <?php echo esc_html($triggerLabel); ?>
                                </span>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-upload-source">
                            <?php if ($hasUploadSource): ?>
                                <span class="upload-source-badge <?php echo esc_attr($uploadSourceClass); ?>" title="<?php echo esc_attr($uploadSource); ?>">
                                    <?php echo esc_html($uploadSourceLabel); ?>
                                </span>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-source">
                            <?php if ($hasSourceMachine): ?>
                                <span class="source-badge" title="<?php esc_attr_e('Management Server Hostname', $pluginSlug); ?>">
                                    <?php echo esc_html($sourceMachine); ?>
                                </span>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                            <?php if ($hasIpAddress): ?>
                                <br><small class="ip-address"><?php echo esc_html($log[LogColumnType::IpAddress->value]); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="column-user">
                            <span class="user-info">
                                <?php echo esc_html($log[LogColumnType::UserLogin->value]); ?>
                                <?php if ($hasUserId): ?>
                                    <small>(#<?php echo esc_html($log[LogColumnType::UserId->value]); ?>)</small>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="column-status">
                            <span class="status-badge status-<?php echo esc_attr($log[LogColumnType::Status->value]); ?>">
                                <?php echo esc_html($log[LogColumnType::Status->value]); ?>
                            </span>
                        </td>
                        <td class="column-details">
                            <?php if ($hasErrorMsg): ?>
                                <span class="error-msg" title="<?php echo esc_attr($log[LogColumnType::ErrorMsg->value]); ?>">
                                    <?php echo esc_html(wp_trim_words($log[LogColumnType::ErrorMsg->value], 10)); ?>
                                </span>
                            <?php elseif ($hasLogDetails): ?>
                                <button type="button" class="button button-small toggle-details" data-details="<?php echo esc_attr(json_encode($log[LogColumnType::Details->value])); ?>">
                                    <?php esc_html_e('View', $pluginSlug); ?>
                                </button>
                            <?php else: ?>
                                <span class="na">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php include __DIR__ . '/partials/shared/pagination.php'; ?>

    <!-- Details Modal -->
    <div id="riseup-details-modal" class="riseup-modal" style="display: none;">
        <div class="riseup-modal-content">
            <div class="riseup-modal-header">
                <h3><?php esc_html_e('Details', $pluginSlug); ?></h3>
                <button type="button" class="riseup-modal-close">&times;</button>
            </div>
            <div class="riseup-modal-body">
                <pre id="riseup-details-content"></pre>
            </div>
        </div>
    </div>


</div>
