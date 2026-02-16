<?php
/**
 * Logger Context Trait — user info, IP, source machine resolution.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Logging\Traits;

use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\BooleanHelpers;

trait LoggerContextTrait {

    /** Get database instance (lazy loading). */
    private function getDb(): \RiseupDatabase {
        if ($this->db === null) {
            $this->db = \RiseupDatabase::getInstance();
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
            if (strpos($ip, ',') !== false) {
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
        if (!empty($_SERVER[self::SOURCE_MACHINE_HEADER])) {
            $machine = preg_replace('/[^a-zA-Z0-9.\\\\-_]/', '', $_SERVER[self::SOURCE_MACHINE_HEADER]);

            return !empty($machine) ? $machine : null;
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
            $enhanced['source_machine'] = $sourceMachine;
        }
        if (empty($enhanced['plugin_version'])) {
            $enhanced['plugin_version'] = PluginConfigType::Version->value;
        }
        if (!empty($extraEnhanced)) {
            $enhanced = array_merge($enhanced, $extraEnhanced);
        }

        return $enhanced;
    }
}
