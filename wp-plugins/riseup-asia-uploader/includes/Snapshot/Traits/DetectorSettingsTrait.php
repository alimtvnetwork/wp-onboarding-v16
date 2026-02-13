<?php
/**
 * DetectorSettingsTrait — provider selection, settings management, and validation.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DetectorSettingsTrait {

    /**
     * Get the preferred provider based on settings.
     *
     * @return string Provider ID.
     */
    public function getPreferredProvider() {
        $settings = get_option(OPTION_SNAPSHOT_SETTINGS, array());
        $preferred = isset($settings['preferred_provider']) ? $settings['preferred_provider'] : SNAPSHOT_PROVIDER_AUTO;

        if ($preferred === SNAPSHOT_PROVIDER_AUTO) {
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
    public function getBestAvailableProvider() {
        $providers = $this->detectAvailableProviders();
        $priority = array(SNAPSHOT_PROVIDER_WP_RESET, SNAPSHOT_PROVIDER_UPDRAFT, SNAPSHOT_PROVIDER_NATIVE);

        foreach ($priority as $provider_id) {
            foreach ($providers as $provider) {
                if ($provider['id'] === $provider_id && $provider['available']) {
                    return $provider_id;
                }
            }
        }
        return SNAPSHOT_PROVIDER_NATIVE;
    }

    /**
     * Get a provider instance.
     *
     * @param string|null $provider_id Provider ID, or null for preferred.
     * @return RiseupSnapshotProviderInterface Provider instance.
     * @throws Exception If provider not available.
     */
    public function getProviderInstance($provider_id = null) {
        if ($provider_id === null) {
            $provider_id = $this->getPreferredProvider();
        }

        if (isset($this->provider_instances[$provider_id])) {
            return $this->provider_instances[$provider_id];
        }

        $providers = $this->detectAvailableProviders();
        $available = false;
        foreach ($providers as $provider) {
            if ($provider['id'] === $provider_id && $provider['available']) {
                $available = true;
                break;
            }
        }

        if (!$available) {
            throw new Exception(sprintf('Snapshot provider "%s" is not available', $provider_id));
        }

        $instance = $this->instantiateProvider($provider_id);
        $this->provider_instances[$provider_id] = $instance;
        return $instance;
    }

    /**
     * Instantiate a provider by ID.
     *
     * @param string $provider_id Provider ID.
     * @return RiseupSnapshotProviderInterface
     */
    private function instantiateProvider(string $provider_id) {
        switch ($provider_id) {
            case SNAPSHOT_PROVIDER_WP_RESET:
                require_once dirname(__FILE__) . '/../SnapshotProviderWpReset.php';
                return new RiseupSnapshotProviderWPReset($this->logger, $this->db);
            case SNAPSHOT_PROVIDER_UPDRAFT:
                require_once dirname(__FILE__) . '/../SnapshotProviderUpdraft.php';
                return new RiseupSnapshotProviderUpdraft($this->logger, $this->db);
            case SNAPSHOT_PROVIDER_NATIVE:
            default:
                require_once dirname(__FILE__) . '/../SnapshotProviderNative.php';
                return new RiseupSnapshotProviderNative($this->logger, $this->db);
        }
    }

    /**
     * Get snapshot settings with defaults.
     *
     * @return array Snapshot settings.
     */
    public function getSettings() {
        $defaults = array(
            'preferred_provider' => SNAPSHOT_PROVIDER_AUTO,
            'schedule_enabled' => false, 'schedule_frequency' => SNAPSHOT_FREQ_DAILY,
            'schedule_time' => '03:00', 'schedule_day' => 1,
            'default_scope' => SNAPSHOT_SCOPE_WORDPRESS, 'custom_tables' => array(),
            'retention_type' => 'days', 'retention_days' => SNAPSHOT_RETENTION_DAYS_DEFAULT,
            'retention_count' => SNAPSHOT_RETENTION_COUNT_DEFAULT,
            'pre_restore_backup' => true, 'require_restore_confirm' => true,
            'max_snapshot_size_mb' => SNAPSHOT_MAX_SIZE_MB, 'batch_size' => SNAPSHOT_BATCH_SIZE,
            'worker_pool_size' => SNAPSHOT_WORKER_POOL_DEFAULT, 'storage_mode' => 'per-table',
        );

        $saved = get_option(OPTION_SNAPSHOT_SETTINGS, array());
        return array_merge($defaults, $saved);
    }

    /**
     * Update snapshot settings.
     *
     * @param array $settings Settings to update.
     * @return bool True if settings were updated.
     */
    public function updateSettings($settings) {
        $current = $this->getSettings();
        $updated = $this->validateSettings(array_merge($current, $settings));

        $result = update_option(OPTION_SNAPSHOT_SETTINGS, $updated);
        if ($result) {
            $this->logger->info('[SNAPSHOT] Settings updated', array('changed_keys' => array_keys(array_diff_assoc($settings, $current))));
        }
        return $result;
    }

    /**
     * Validate and sanitize settings.
     *
     * @param array $settings Settings to validate.
     * @return array Validated settings.
     */
    private function validateSettings($settings) {
        $validations = array(
            'preferred_provider' => array(SNAPSHOT_PROVIDER_AUTO, SNAPSHOT_PROVIDER_WP_RESET, SNAPSHOT_PROVIDER_UPDRAFT, SNAPSHOT_PROVIDER_NATIVE),
            'schedule_frequency' => array(SNAPSHOT_FREQ_MANUAL, SNAPSHOT_FREQ_DAILY, SNAPSHOT_FREQ_WEEKLY, SNAPSHOT_FREQ_MONTHLY),
            'default_scope' => array(SNAPSHOT_SCOPE_ALL, SNAPSHOT_SCOPE_WORDPRESS, SNAPSHOT_SCOPE_CONTENT, SNAPSHOT_SCOPE_CUSTOM),
            'retention_type' => array('days', 'count', 'none'),
        );

        $defaults = array('preferred_provider' => SNAPSHOT_PROVIDER_AUTO, 'schedule_frequency' => SNAPSHOT_FREQ_DAILY, 'default_scope' => SNAPSHOT_SCOPE_WORDPRESS, 'retention_type' => 'days');

        foreach ($validations as $key => $valid) {
            if (!in_array($settings[$key], $valid)) {
                $settings[$key] = $defaults[$key];
            }
        }

        $settings['retention_days'] = max(1, min(365, intval($settings['retention_days'])));
        $settings['retention_count'] = max(1, min(100, intval($settings['retention_count'])));
        $settings['schedule_day'] = max(1, min(28, intval($settings['schedule_day'])));
        $settings['max_snapshot_size_mb'] = max(50, min(2000, intval($settings['max_snapshot_size_mb'])));
        $settings['batch_size'] = max(100, min(10000, intval($settings['batch_size'])));
        $settings['worker_pool_size'] = max(SNAPSHOT_WORKER_POOL_MIN, min(SNAPSHOT_WORKER_POOL_MAX, intval($settings['worker_pool_size'] ?? SNAPSHOT_WORKER_POOL_DEFAULT)));

        $valid_storage_modes = array('single', 'per-table');
        if (!in_array($settings['storage_mode'] ?? 'per-table', $valid_storage_modes)) {
            $settings['storage_mode'] = 'per-table';
        }

        $settings['schedule_enabled'] = (bool) $settings['schedule_enabled'];
        $settings['pre_restore_backup'] = (bool) $settings['pre_restore_backup'];
        $settings['require_restore_confirm'] = (bool) $settings['require_restore_confirm'];

        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings['schedule_time'])) {
            $settings['schedule_time'] = '03:00';
        }

        if (!is_array($settings['custom_tables'])) {
            $settings['custom_tables'] = array();
        }

        return $settings;
    }
}
