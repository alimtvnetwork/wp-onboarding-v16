<?php
/**
 * LicenseManager — High-level license lifecycle management.
 *
 * Provides validate, activate, deactivate with WordPress option caching
 * and site domain detection.
 *
 * @package RiseupAsia\Licensing
 * @since   2.7.0
 */

namespace RiseupAsia\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LicenseOptionType;
use RiseupAsia\Enums\LicenseStatusType;
use RiseupAsia\Enums\HookType;

class LicenseManager
{
    private const CACHE_TTL_HOURS = 12;
    private const CRON_INTERVAL = 'twicedaily';

    private LicenseClient $client;
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $baseUrl = defined('RISEUP_LICENSE_API_URL')
            ? RISEUP_LICENSE_API_URL
            : 'https://license.riseupasia.com';

        $hmacSecret = defined('RISEUP_LICENSE_HMAC_SECRET')
            ? RISEUP_LICENSE_HMAC_SECRET
            : '';

        $this->client = new LicenseClient($baseUrl, $hmacSecret);

        $this->scheduleCron();
        add_action(HookType::CronLicenseRevalidate->value, [self::class, 'cronRevalidate']);
    }

    /**
     * WP-Cron callback — revalidate the stored license key.
     */
    public static function cronRevalidate(): void
    {
        $manager = self::getInstance();
        $manager->validateLicense();
    }

    /**
     * Ensure the revalidation cron event is scheduled.
     */
    private function scheduleCron(): void
    {
        $hook = HookType::CronLicenseRevalidate->value;

        if (!function_exists('wp_next_scheduled')) {
            return;
        }

        $hasKey = !empty($this->getStoredKey());

        if ($hasKey && !wp_next_scheduled($hook)) {
            wp_schedule_event(time(), self::CRON_INTERVAL, $hook);
        }

        if (!$hasKey && wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Check whether the current site has a valid, active license.
     *
     * Uses cached data when available and fresh. Falls back to API call.
     */
    public function isLicensed(): bool
    {
        $cached = $this->getCachedStatus();

        if ($cached !== null) {
            return $cached->isUsable();
        }

        $result = $this->validateLicense();

        if ($result === null) {
            return false;
        }

        return $result['valid'] ?? false;
    }

    /**
     * Validate the stored license key against the API.
     *
     * @return array|null Validation response or null on failure.
     */
    public function validateLicense(): ?array
    {
        $key = $this->getStoredKey();

        if (empty($key)) {
            return null;
        }

        $result = $this->client->validate($key);

        if ($result === null) {
            return null;
        }

        $this->cacheResult($result);

        return $result;
    }

    /**
     * Activate the stored license key on this site's domain.
     *
     * @return array|null Activation record or null on failure.
     */
    public function activateLicense(): ?array
    {
        $key = $this->getStoredKey();

        if (empty($key)) {
            return null;
        }

        $domain = $this->getSiteDomain();
        $result = $this->client->activate($key, $domain);

        if ($result === null) {
            return null;
        }

        $this->clearCache();
        $this->validateLicense();

        return $result;
    }

    /**
     * Deactivate the stored license key from this site's domain.
     *
     * @return array|null Deactivation response or null on failure.
     */
    public function deactivateLicense(): ?array
    {
        $key = $this->getStoredKey();

        if (empty($key)) {
            return null;
        }

        $domain = $this->getSiteDomain();
        $result = $this->client->deactivate($key, $domain);

        if ($result === null) {
            return null;
        }

        $this->clearCache();

        return $result;
    }

    /**
     * Get full license status including activation list.
     *
     * @return array|null Status response or null on failure.
     */
    public function getLicenseStatus(): ?array
    {
        $key = $this->getStoredKey();

        if (empty($key)) {
            return null;
        }

        return $this->client->status($key);
    }

    /**
     * Store a new license key and immediately validate it.
     *
     * @return array|null Validation response or null on failure.
     */
    public function setLicenseKey(string $key): ?array
    {
        $sanitized = sanitize_text_field(trim($key));
        update_option(LicenseOptionType::LicenseKey->value, $sanitized);
        $this->clearCache();
        $this->scheduleCron();

        return $this->validateLicense();
    }

    /**
     * Remove the stored license key and clear all cached data.
     */
    public function removeLicenseKey(): void
    {
        $this->deactivateLicense();
        delete_option(LicenseOptionType::LicenseKey->value);
        $this->clearCache();
        $this->scheduleCron();
    }

    /**
     * Get the cached license status if still fresh.
     */
    private function getCachedStatus(): ?LicenseStatusType
    {
        $checkedAt = get_option(LicenseOptionType::LicenseCheckedAt->value, '');

        if (empty($checkedAt)) {
            return null;
        }

        $elapsed = time() - (int) $checkedAt;
        $ttlSeconds = self::CACHE_TTL_HOURS * 3600;

        $isStale = $elapsed > $ttlSeconds;

        if ($isStale) {
            return null;
        }

        $status = get_option(LicenseOptionType::LicenseStatus->value, '');

        if (empty($status)) {
            return null;
        }

        return LicenseStatusType::tryFrom($status) ?? LicenseStatusType::Unknown;
    }

    /**
     * Cache the validation result in WordPress options.
     */
    private function cacheResult(array $result): void
    {
        $status = $result['status'] ?? 'unknown';
        update_option(LicenseOptionType::LicenseStatus->value, $status);
        update_option(LicenseOptionType::LicenseData->value, $result);
        update_option(LicenseOptionType::LicenseCheckedAt->value, (string) time());
    }

    /**
     * Clear all cached license data.
     */
    private function clearCache(): void
    {
        delete_option(LicenseOptionType::LicenseStatus->value);
        delete_option(LicenseOptionType::LicenseData->value);
        delete_option(LicenseOptionType::LicenseCheckedAt->value);
    }

    /**
     * Get the stored license key from WordPress options.
     */
    private function getStoredKey(): string
    {
        return (string) get_option(LicenseOptionType::LicenseKey->value, '');
    }

    /**
     * Extract the site domain from the WordPress site URL.
     */
    private function getSiteDomain(): string
    {
        $siteUrl = get_site_url();
        $parsed = wp_parse_url($siteUrl);

        return $parsed['host'] ?? 'unknown';
    }
}
