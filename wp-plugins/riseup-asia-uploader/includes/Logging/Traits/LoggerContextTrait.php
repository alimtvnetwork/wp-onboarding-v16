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
    private function get_db() {
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
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
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
    private function get_source_machine() {
        $header_key = 'HTTP_X_RISEUP_SOURCE_MACHINE';
        if (!empty($_SERVER[$header_key])) {
            $machine = preg_replace('/[^a-zA-Z0-9.\\\\-_]/', '', $_SERVER[$header_key]);
            return !empty($machine) ? $machine : null;
        }
        return null;
    }

    /**
     * Get current user info.
     *
     * @return array User info with 'login' and 'id'.
     */
    private function get_user_info() {
        if (RiseupBooleanHelpers::is_func_missing('wp_get_current_user')) {
            return array('login' => 'anonymous', 'id' => 0);
        }

        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID > 0) {
            return array('login' => $current_user->user_login, 'id' => $current_user->ID);
        }
        return array('login' => 'anonymous', 'id' => 0);
    }

    /**
     * Build enhanced fields with source machine and plugin version.
     *
     * @param array $extra_enhanced Extra enhanced fields to merge.
     * @return array Enhanced fields.
     */
    private function buildEnhancedFields(array $extra_enhanced = array()): array {
        $enhanced = array();
        $source_machine = $this->get_source_machine();
        if ($source_machine) {
            $enhanced['source_machine'] = $source_machine;
        }
        if (empty($enhanced['plugin_version']) && defined('PLUGIN_VERSION')) {
            $enhanced['plugin_version'] = PLUGIN_VERSION;
        }
        if (!empty($extra_enhanced)) {
            $enhanced = array_merge($enhanced, $extra_enhanced);
        }
        return $enhanced;
    }
}
