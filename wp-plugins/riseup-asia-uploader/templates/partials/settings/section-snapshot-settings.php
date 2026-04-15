<?php
/**
 * Settings Partial — Database Snapshot Settings card (orchestrator).
 *
 * Delegates provider/scheduling and retention/worker sections to sub-partials.
 *
 * Variables expected: $pluginSlug, $snapshotSettings, $snapshotProviders.
 *
 * @package RiseupAsiaUploader
 * @since   1.64.0
 * @updated 2.33.0 - Split into sub-partials for Phase 11 compliance
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\AdminPageType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;

$preferredProvider = $snapshotSettings[SettingsKeyType::PreferredProvider->value] ?? SnapshotProviderType::Auto->value;
$scheduleEnabled   = $snapshotSettings[SettingsKeyType::ScheduleEnabled->value] ?? false;
$scheduleFrequency = $snapshotSettings[SettingsKeyType::ScheduleFrequency->value] ?? SnapshotFrequencyType::Daily->value;
$scheduleTime      = $snapshotSettings[SettingsKeyType::ScheduleTime->value] ?? '03:00';
$scheduleDay       = $snapshotSettings[SettingsKeyType::ScheduleDay->value] ?? 1;
$defaultScope      = $snapshotSettings[SettingsKeyType::DefaultScope->value] ?? SnapshotScopeType::WordPress->value;
$retentionType     = $snapshotSettings[SettingsKeyType::RetentionType->value] ?? RetentionType::Days->value;
$retentionDays     = $snapshotSettings[SettingsKeyType::RetentionDays->value] ?? SnapshotConfigType::RetentionDaysDefault->value;
$retentionCount    = $snapshotSettings[SettingsKeyType::RetentionCount->value] ?? SnapshotConfigType::RetentionCountDefault->value;
$preRestoreBackup  = $snapshotSettings[SettingsKeyType::PreRestoreBackup->value] ?? true;
$maxSnapshotSizeMb = $snapshotSettings[SettingsKeyType::MaxSnapshotSizeMb->value] ?? SnapshotConfigType::MaxSizeMb->value;
$batchSize         = $snapshotSettings[SettingsKeyType::BatchSize->value] ?? SnapshotConfigType::BatchSize->value;
$workerPoolSize    = $snapshotSettings[SettingsKeyType::WorkerPoolSize->value] ?? SnapshotConfigType::WorkerPoolDefault->value;
$storageMode       = $snapshotSettings[SettingsKeyType::StorageMode->value] ?? StorageModeType::PerTable->value;
?>
<!-- Database Snapshot Settings -->
<div class="riseup-card">
    <h2>
        <span class="dashicons dashicons-database"></span>
        <?php esc_html_e('Database Snapshot Settings', $pluginSlug); ?>
    </h2>
    <p class="description">
        <?php esc_html_e('Configure automated database snapshots, retention policies, and provider preferences. Manage snapshots from the', $pluginSlug); ?>
        <a href="<?php echo esc_url(AdminPageType::Snapshots->adminUrl()); ?>"><?php esc_html_e('Snapshots Dashboard', $pluginSlug); ?></a>.
    </p>

    <?php include __DIR__ . '/section-snapshot-provider.php'; ?>
    <?php include __DIR__ . '/section-snapshot-retention.php'; ?>
</div>
