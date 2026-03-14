<?php
/**
 * UserYoastTrait — Read/write Yoast SEO user meta.
 *
 * Silently skips if Yoast SEO is not active.
 *
 * @package RiseupAsia\Traits\User
 * @since   2.13.0
 */

namespace RiseupAsia\Traits\User;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\UserMetaKeyType;

trait UserYoastTrait {

    /**
     * Check if Yoast SEO plugin is active.
     */
    private function isYoastActive(): bool
    {
        $isClassAvailable = class_exists('WPSEO_Meta', false);

        if ($isClassAvailable) {
            return true;
        }

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('wordpress-seo/wp-seo.php');
    }

    /**
     * Read all Yoast meta for a user. Returns null if Yoast is not active.
     *
     * @return array<string, string>|null
     */
    private function readYoastMeta(int $userId): ?array
    {
        $isActive = $this->isYoastActive();

        if (!$isActive) {
            return null;
        }

        $result = array();

        foreach (UserMetaKeyType::yoastCases() as $meta) {
            $value = get_user_meta($userId, $meta->value, true);
            $result[$meta->jsonKey()] = is_string($value) ? $value : '';
        }

        return $result;
    }

    /**
     * Write Yoast meta from request data. Silently skips if Yoast is absent.
     *
     * @param array<string, string> $yoastData
     * @return string[] Modified field names.
     */
    private function writeYoastMeta(int $userId, array $yoastData): array
    {
        $isActive = $this->isYoastActive();

        if (!$isActive) {
            return array();
        }

        $modified = array();

        foreach (UserMetaKeyType::yoastCases() as $meta) {
            $jsonKey = $meta->jsonKey();
            $isProvided = array_key_exists($jsonKey, $yoastData);

            if (!$isProvided) {
                continue;
            }

            update_user_meta($userId, $meta->value, sanitize_text_field($yoastData[$jsonKey]));
            $modified[] = 'Yoast.' . $jsonKey;
        }

        return $modified;
    }
}
