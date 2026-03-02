<?php
/**
 * Logger Context Trait — user info, IP, source machine resolution.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Logging\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Database\Database;

trait LoggerContextTrait {
    /** Get database instance (lazy loading). */
    private function getDb(): Database {
        if ($this->db === null) {
            $this->db = Database::getInstance();
        }

        return $this->db;
    }

    /** Get client IP address. */
    private function getClientIp(): string {
        $ipKeys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');

        foreach ($ipKeys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $ip = $_SERVER[$key];

            $hasMultipleIps = (strpos($ip, ',') !== false);

            if ($hasMultipleIps) {
                $parts = explode(',', $ip);
                $ip = trim($parts[0]);
            }

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return self::FALLBACK_IP;
    }

    /** Get source machine hostname from request header. */
    private function getSourceMachine(): ?string {
        $hasSourceHeader = !empty($_SERVER[self::SOURCE_MACHINE_HEADER] ?? null);

        if ($hasSourceHeader) {
            $machine = preg_replace('/[^a-zA-Z0-9.\\\\-_]/', '', $_SERVER[self::SOURCE_MACHINE_HEADER]);
            $hasMachine = !empty($machine);

            return $hasMachine ? $machine : null;
        }

        return null;
    }

    /** Get current user info. */
    private function getUserInfo(): array {
        if (BooleanHelpers::isFuncMissing('wp_get_current_user')) {
            return array('login' => self::ANONYMOUS_LOGIN, 'id' => self::ANONYMOUS_USER_ID);
        }

        $currentUser = wp_get_current_user();
        if ($currentUser && $currentUser->ID > 0) {
            return array('login' => $currentUser->user_login, 'id' => $currentUser->ID);
        }

        return array('login' => self::ANONYMOUS_LOGIN, 'id' => self::ANONYMOUS_USER_ID);
    }

    /** Build enhanced fields with source machine and plugin version. */
    private function buildEnhancedFields(array $extraEnhanced = array()): array {
        $enhanced = array();
        $sourceMachine = $this->getSourceMachine();

        if ($sourceMachine) {
            $enhanced['sourceMachine'] = $sourceMachine;
        }

        if (empty($enhanced['pluginVersion'])) {
            $enhanced['pluginVersion'] = PluginConfigType::Version->value;
        }

        $hasExtraEnhanced = !empty($extraEnhanced);

        if ($hasExtraEnhanced) {
            $enhanced = array_merge($enhanced, $extraEnhanced);
        }

        return $enhanced;
    }
}
