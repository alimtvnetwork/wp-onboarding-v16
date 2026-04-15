<?php
/**
 * Errors Partial — Error Sessions tab (table view with filters, stats, pagination).
 *
 * Variables expected from parent: $pluginSlug, $filterLevel, $filterSearch,
 * $total, $page, $totalPages, $errors, $lastSeenId, $levelColors.
 *
 * @package RiseupAsiaUploader
 * @since   2.33.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\AdminTabType;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;
?>
<!-- Filters -->
<div class="riseup-filters">
    <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr(AdminPageType::Errors->value); ?>">
        <input type="hidden" name="tab" value="<?php echo esc_attr(AdminTabType::Sessions->value); ?>">
        <div class="filter-row">
            <label>
                <span><?php esc_html_e('Level:', $pluginSlug); ?></span>
                <select name="filter_level">
                    <option value=""><?php esc_html_e('All Levels', $pluginSlug); ?></option>
                    <option value="<?php echo esc_attr(LogLevelType::Error->value); ?>" <?php selected($filterLevel, LogLevelType::Error->value); ?>><?php esc_html_e('Error', $pluginSlug); ?></option>
                    <option value="<?php echo esc_attr(LogLevelType::Warn->value); ?>" <?php selected($filterLevel, LogLevelType::Warn->value); ?>><?php esc_html_e('Warning', $pluginSlug); ?></option>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Search:', $pluginSlug); ?></span>
                <input type="text" name="filter_search" value="<?php echo esc_attr($filterSearch); ?>" placeholder="<?php esc_attr_e('Search messages...', $pluginSlug); ?>">
            </label>
            <button type="submit" class="button button-primary"><?php esc_html_e('Filter', $pluginSlug); ?></button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminPageType::Errors->value . '&tab=' . AdminTabType::Sessions->value)); ?>" class="button"><?php esc_html_e('Reset', $pluginSlug); ?></a>
            <button type="button" id="riseup-clear-errors" class="button button-link-delete" style="margin-left: auto;">
                <?php esc_html_e('Clear All Errors', $pluginSlug); ?>
            </button>
        </div>
    </form>
</div>

<!-- Stats -->
<div class="riseup-stats">
    <span class="stat-item">
        <strong><?php echo esc_html($total); ?></strong>
        <?php esc_html_e('total errors', $pluginSlug); ?>
    </span>
    <?php if ($page > 1 || $page < $totalPages): ?>
        <span class="stat-item">
            <?php esc_html_e('Page', $pluginSlug); ?> <?php echo esc_html($page); ?>
            <?php esc_html_e('of', $pluginSlug); ?> <?php echo esc_html($totalPages); ?>
        </span>
    <?php endif; ?>
</div>

<!-- Error Log Table -->
<table class="wp-list-table widefat fixed striped riseup-error-table">
    <thead>
        <tr>
            <th class="column-id" style="width: 50px;"><?php esc_html_e('ID', $pluginSlug); ?></th>
            <th class="column-timestamp" style="width: 160px;"><?php esc_html_e('Timestamp', $pluginSlug); ?></th>
            <th class="column-level" style="width: 70px;"><?php esc_html_e('Level', $pluginSlug); ?></th>
            <th class="column-version" style="width: 70px;"><?php esc_html_e('Version', $pluginSlug); ?></th>
            <th class="column-file" style="width: 180px;"><?php esc_html_e('Source', $pluginSlug); ?></th>
            <th class="column-message"><?php esc_html_e('Message', $pluginSlug); ?></th>
            <th class="column-actions" style="width: 80px;"><?php esc_html_e('Details', $pluginSlug); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($errors)): ?>
            <tr>
                <td colspan="7" class="no-items"><?php esc_html_e('No errors found. 🎉', $pluginSlug); ?></td>
            </tr>
        <?php else: ?>
            <?php foreach ($errors as $error): ?>
                <?php
                $isNew = ($error['Id'] > $lastSeenId);
                $level  = $error['Level'];
                $color  = isset($levelColors[$level]) ? $levelColors[$level] : '#6c757d';
                $hasFile = BooleanHelpers::hasValue($error['File'] ?? null);
                $hasContext = BooleanHelpers::hasValue($error['ContextJson'] ?? null);
                $hasStackTrace = BooleanHelpers::hasValue($error['StackTrace'] ?? null);
                $hasDetailsData = $hasContext || $hasStackTrace;
                $sourceDisplay = $hasFile ? basename($error['File']) . ':' . $error['Line'] : '—';
                ?>
                <tr class="<?php echo $isNew ? 'error-row-new' : ''; ?>">
                    <td class="column-id">
                        <?php echo esc_html($error['Id']); ?>
                        <?php if ($isNew): ?>
                            <span class="new-badge">NEW</span>
                        <?php endif; ?>
                    </td>
                    <td class="column-timestamp">
                        <span class="timestamp"><?php echo esc_html(DateHelper::formatLogDisplay(strtotime($error['CreatedAt']))); ?></span>
                    </td>
                    <td class="column-level">
                        <span class="level-badge" style="background: <?php echo esc_attr($color); ?>;">
                            <?php echo esc_html($level); ?>
                        </span>
                    </td>
                    <td class="column-version">
                        <?php $hasVersion = !empty($error['PluginVersion']); ?>
                        <?php if ($hasVersion): ?>
                            <code class="version-tag">v<?php echo esc_html($error['PluginVersion']); ?></code>
                        <?php else: ?>
                            <span class="na">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="column-file">
                        <?php if ($hasFile): ?>
                            <code class="source-file"><?php echo esc_html(basename($error['File'])); ?>:<?php echo esc_html($error['Line']); ?></code>
                        <?php else: ?>
                            <span class="na">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="column-message">
                        <span class="error-message"><?php echo esc_html($error['Message']); ?></span>
                    </td>
                    <td class="column-actions">
                        <?php if ($hasDetailsData): ?>
                            <button type="button" class="button button-small toggle-error-details"
                                data-context="<?php echo esc_attr($error['ContextJson'] ?: '{}'); ?>"
                                data-stack="<?php echo esc_attr($error['StackTrace'] ?: ''); ?>"
                                data-level="<?php echo esc_attr($level); ?>"
                                data-message="<?php echo esc_attr($error['Message']); ?>"
                                data-source="<?php echo esc_attr($sourceDisplay); ?>"
                                data-timestamp="<?php echo esc_attr(DateHelper::formatLogDisplay(strtotime($error['CreatedAt']))); ?>">
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
<?php
$paginationArgs = [
    'base'      => add_query_arg(['paged' => '%#%', 'tab' => AdminTabType::Sessions->value]),
    'format'    => '',
    'prev_text' => '&laquo;',
    'next_text' => '&raquo;',
    'total'     => $totalPages,
    'current'   => $page,
];
include __DIR__ . '/../shared/pagination.php';
?>
