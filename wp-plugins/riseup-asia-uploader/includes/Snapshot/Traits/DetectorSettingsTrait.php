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

use RiseupAsia\Enums\OptionNameType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Snapshot\SnapshotProviderWPReset;
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
        $preferred = isset($settings['preferred_provider']) ? $settings['preferred_provider'] : SnapshotProviderType::Auto->value;

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
        $priority = array(SnapshotProviderType::WpReset->value, SnapshotProviderType::Updraft->value, SnapshotProviderType::Native->value);

        foreach ($priority as $provider_id) {
            foreach ($providers as $provider) {
                if ($provider['id'] === $provider_id && $provider['available']) {
                    return $provider_id;
                }
            }
        }
        return SnapshotProviderType::Native->value;
    }

    /**
     * Get a provider instance.
     *
     * @param string|null $provider_id Provider ID, or null for preferred.
     * @return RiseupSnapshotProviderInterface Provider instance.
     * @throws Exception If provider not available.
     */
    public function getProviderInstance(?string $providerId = null): SnapshotProviderInterface {
        if ($providerId === null) {
            $providerId = $this->getPreferredProvider();
        }

        if (isset($this->provider_instances[$providerId])) {
            return $this->provider_instances[$providerId];
        }

        $this->assertProviderAvailable($providerId);

        $instance = $this->instantiateProvider($providerId);
        $this->provider_instances[$providerId] = $instance;

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

        throw new \Exception(sprintf('Snapshot provider "%s" is not available', $providerId));
    }

    /**
     * Instantiate a provider by ID.
     *
     * @return SnapshotProviderInterface
     */
    private function instantiateProvider(string $providerId): SnapshotProviderInterface {
        switch ($providerId) {
            case SnapshotProviderType::WpReset->value:
                return new SnapshotProviderWPReset($this->logger, $this->db);
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
            'preferred_provider' => SnapshotProviderType::Auto->value,
            'schedule_enabled' => false, 'schedule_frequency' => SnapshotFrequencyType::Daily->value,
            'schedule_time' => '03:00', 'schedule_day' => 1,
            'default_scope' => SnapshotScopeType::WordPress->value, 'custom_tables' => array(),
            'retention_type' => 'days', 'retention_days' => SNAPSHOT_RETENTION_DAYS_DEFAULT,
            'retention_count' => SNAPSHOT_RETENTION_COUNT_DEFAULT,
            'pre_restore_backup' => true, 'require_restore_confirm' => true,
            'max_snapshot_size_mb' => SNAPSHOT_MAX_SIZE_MB, 'batch_size' => SNAPSHOT_BATCH_SIZE,
            'worker_pool_size' => SNAPSHOT_WORKER_POOL_DEFAULT, 'storage_mode' => 'per-table',
        );

        $saved = get_option(OptionNameType::SnapshotSettings->value, array());
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
        $updated = $this->validateSettings(array_merge($current, $settings));

        $result = update_option(OptionNameType::SnapshotSettings->value, $updated);
        if ($result) {
            $this->logger->info('[SNAPSHOT] Settings updated', array('changed_keys' => array_keys(array_diff_assoc($settings, $current))));
        }
        return $result;
    }
}
