<?php
/**
 * UserSocialTrait — Read/write social profile meta for a user.
 *
 * @package RiseupAsia\Traits\User
 * @since   2.13.0
 */

namespace RiseupAsia\Traits\User;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\UserMetaKeyType;

trait UserSocialTrait {

    /**
     * Read all social meta for a user.
     *
     * @return array<string, string>
     */
    private function readSocialMeta(int $userId): array
    {
        $result = array();

        foreach (UserMetaKeyType::socialCases() as $meta) {
            $value = get_user_meta($userId, $meta->value, true);
            $result[$meta->jsonKey()] = is_string($value) ? $value : '';
        }

        return $result;
    }

    /**
     * Write social meta from request data.
     *
     * @param array<string, string> $socialData
     * @return string[] Modified field names.
     */
    private function writeSocialMeta(int $userId, array $socialData): array
    {
        $modified = array();

        foreach (UserMetaKeyType::socialCases() as $meta) {
            $jsonKey = $meta->jsonKey();
            $isProvided = array_key_exists($jsonKey, $socialData);

            if (!$isProvided) {
                continue;
            }

            update_user_meta($userId, $meta->value, sanitize_text_field($socialData[$jsonKey]));
            $modified[] = 'Social.' . $jsonKey;
        }

        return $modified;
    }
}
