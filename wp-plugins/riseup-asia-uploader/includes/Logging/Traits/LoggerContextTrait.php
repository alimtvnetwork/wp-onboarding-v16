<?php
/**
 * Logger Context Trait — user info, IP, source machine resolution.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait LoggerContextTrait {

    /**
     * Get database instance (lazy loading).
     *
     * @return RiseupDatabase
     */
    private function getDb() {
        if ($this->db === null) {
            $this->db = RiseupDatabase::get_instance();
        }
        return $this->db;
    }

    /**
     * Get client IP address.
     *
     * @return string IP address.
     */
    private function getClientIp() {
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
        return '0.0.0.0';
    }

    /**
     * Get source machine hostname from request header.
     *
     * @return string|null Source machine hostname or null.
     */
    private function getSourceMachine() {
        $headerKey = 'HTTP_X_RISEUP_SOURCE_MACHINE';
        if (!empty($_SERVER[$headerKey])) {
            $machine = preg_replace('/[^a-zA-Z0-9.\\\\-_]/', '', $_SERVER[$headerKey]);
            return !empty($machine) ? $machine : null;
        }
        return null;
    }

    /**
     * Get current user info.
     *
     * @return array User info with 'login' and 'id'.
     */
    private function getUserInfo() {
        if (RiseupBooleanHelpers::is_func_missing('wp_get_current_user')) {
            return array('login' => 'anonymous', 'id' => 0);
        }

        $currentUser = wp_get_current_user();
        if ($currentUser && $currentUser->ID > 0) {
            return array('login' => $currentUser->user_login, 'id' => $currentUser->ID);
        }
        return array('login' => 'anonymous', 'id' => 0);
    }

    /**
     * Build enhanced fields with source machine and plugin version.
     *
     * @param array $extraEnhanced Extra enhanced fields to merge.
     * @return array Enhanced fields.
     */
    private function buildEnhancedFields(array $extraEnhanced = array()): array {
        $enhanced = array();
        $sourceMachine = $this->getSourceMachine();
        if ($sourceMachine) {
            $enhanced['source_machine'] = $sourceMachine;
        }
        if (empty($enhanced['plugin_version']) && defined('PLUGIN_VERSION')) {
            $enhanced['plugin_version'] = PLUGIN_VERSION;
        }
        if (!empty($extraEnhanced)) {
            $enhanced = array_merge($enhanced, $extraEnhanced);
        }
        return $enhanced;
    }
}
