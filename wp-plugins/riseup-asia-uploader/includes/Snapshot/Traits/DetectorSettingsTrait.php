<?php
/**
 * DetectorSettingsTrait — provider selection and settings management.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\RetentionType;
use RiseupAsia\Enums\SettingsKeyType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\StorageModeType;
use RiseupAsia\Snapshot\SnapshotProviderWpReset;
use RiseupAsia\Snapshot\SnapshotProviderUpdraft;
use RiseupAsia\Snapshot\SnapshotProviderNative;
use RiseupAsia\Snapshot\SnapshotProviderInterface;

trait DetectorSettingsTrait {
    use DetectorValidationTrait;

    /**
     * Get the preferred provider based on settings.
     *
     * @return string Provider ID.
     */
    public function getPreferredProvider(): string {
        $settings = get_option(OptionNameType::SnapshotSettings->value, array());
        $settings = SettingsKeyType::migrateArray($settings);
        $preferred = isset($settings[SettingsKeyType::PreferredProvider->value]) ? $settings[SettingsKeyType::PreferredProvider->value] : SnapshotProviderType::Auto->value;

        if ($preferred === SnapshotProviderType::Auto->value) {
            return $this->getBestAvailableProvider();
        }

        $providers = $this->detectAvailableProviders();

        foreach ($providers as $provider) {
            if ($provider['id'] === $preferred && $provider['available']) {
                return $preferred;
            }
        }

        $this->logger->warn('[SNAPSHOT] Preferred provider not available, falling back', array('preferred' => $preferred));

        return $this->getBestAvailableProvider();
    }

    /**
     * Get the best available provider (priority: WP Reset > Updraft > Native).
     *
     * @return string Provider ID.
     */
    public function getBestAvailableProvider(): string {
        $providers = $this->detectAvailableProviders();
        $priority = array(
            SnapshotProviderType::WpReset->value,
            SnapshotProviderType::Updraft->value,
            SnapshotProviderType::Native->value,
        );

        foreach ($priority as $candidateId) {
            foreach ($providers as $provider) {
                if ($provider['id'] === $candidateId && $provider['available']) {
                    return $candidateId;
                }
            }
        }

        return SnapshotProviderType::Native->value;
    }

    /**
     * @param string|null $providerId Provider ID, or null for preferred.
     * @return SnapshotProviderInterface Provider instance.
     * @throws Exception If provider not available.
     */
    public function getProviderInstance(?string $providerId = null): SnapshotProviderInterface {
        if ($providerId === null) {
            $providerId = $this->getPreferredProvider();
        }

        if (isset($this->providerInstances[$providerId])) {
            return $this->providerInstances[$providerId];
        }

        $this->assertProviderAvailable($providerId);

        $instance = $this->instantiateProvider($providerId);
        $this->providerInstances[$providerId] = $instance;

        return $instance;
    }

    /** Assert a provider is available, throwing if not. */
    private function assertProviderAvailable(string $providerId): void {
        $providers = $this->detectAvailableProviders();

        foreach ($providers as $provider) {
            $isMatch = ($provider['id'] === $providerId && $provider['available']);

            if ($isMatch) {
                return;
            }
        }

        throw new Exception(sprintf('Snapshot provider "%s" is not available', $providerId));
    }

    /**
     * Instantiate a provider by ID.
     *
     * @return SnapshotProviderInterface
     */
    private function instantiateProvider(string $providerId): SnapshotProviderInterface {
        switch ($providerId) {
            case SnapshotProviderType::WpReset->value:
                return new SnapshotProviderWpReset($this->logger, $this->db);
            case SnapshotProviderType::Updraft->value:
                return new SnapshotProviderUpdraft($this->logger, $this->db);
            case SnapshotProviderType::Native->value:
            default:
                return new SnapshotProviderNative($this->logger, $this->db);
        }
    }

    /**
     * Get snapshot settings with defaults.
     *
     * @return array Snapshot settings.
     */
    public function getSettings(): array {
        $defaults = array(
            SettingsKeyType::PreferredProvider->value     => SnapshotProviderType::Auto->value,
            SettingsKeyType::ScheduleEnabled->value       => false,
            SettingsKeyType::ScheduleFrequency->value     => SnapshotFrequencyType::Daily->value,
            SettingsKeyType::ScheduleTime->value          => '03:00',
            SettingsKeyType::ScheduleDay->value           => 1,
            SettingsKeyType::DefaultScope->value          => SnapshotScopeType::WordPress->value,
            SettingsKeyType::CustomTables->value          => array(),
            SettingsKeyType::RetentionType->value         => RetentionType::Days->value,
            SettingsKeyType::RetentionDays->value         => SnapshotConfigType::RetentionDaysDefault->value,
            SettingsKeyType::RetentionCount->value        => SnapshotConfigType::RetentionCountDefault->value,
            SettingsKeyType::PreRestoreBackup->value      => true,
            SettingsKeyType::RequireRestoreConfirm->value => true,
            SettingsKeyType::MaxSnapshotSizeMb->value     => SnapshotConfigType::MaxSizeMb->value,
            SettingsKeyType::BatchSize->value             => SnapshotConfigType::BatchSize->value,
            SettingsKeyType::WorkerPoolSize->value        => SnapshotConfigType::WorkerPoolDefault->value,
            SettingsKeyType::StorageMode->value           => StorageModeType::PerTable->value,
        );

        $saved = get_option(OptionNameType::SnapshotSettings->value, array());
        $saved = SettingsKeyType::migrateArray($saved);

        return array_merge($defaults, $saved);
    }

    /**
     * Update snapshot settings.
     *
     * @param array $settings Settings to update.
     * @return bool True if settings were updated.
     */
    public function updateSettings(array $settings): bool {
        $current = $this->getSettings();
        $settings = SettingsKeyType::migrateArray($settings);
        $updated = $this->validateSettings(array_merge($current, $settings));

        $result = update_option(OptionNameType::SnapshotSettings->value, $updated);

        if ($result) {
            $this->logger->info('[SNAPSHOT] Settings updated', array('changedKeys' => array_keys(array_diff_assoc($settings, $current))));
        }

        return $result;
    }
}
