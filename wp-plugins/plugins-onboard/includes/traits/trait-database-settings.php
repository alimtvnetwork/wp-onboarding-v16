<?php
/**
 * Database Settings Trait — CRUD for plugin_settings table.
 *
 * @package PluginsOnboard
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait OnboardDatabaseSettingsTrait
 *
 * Handles get/save/list operations for the plugin_settings key-value store.
 */
trait OnboardDatabaseSettingsTrait {

    /**
     * Get setting value.
     *
     * @param string $key Setting key.
     * @return mixed|null
     */
    public function get_setting($key) {
        if (!$this->connected) {
            return null;
        }

        try {
            $stmt = $this->query(
                'SELECT setting_value FROM plugin_settings WHERE setting_key = ?',
                array($key)
            );

            if ($stmt === false) {
                return null;
            }

            $result = $stmt->fetch();

            if ($result) {
                $decoded = json_decode($result['setting_value'], true);
                // If JSON decode failed, return raw value.
                return ($decoded !== null || $result['setting_value'] === 'null') ? $decoded : $result['setting_value'];
            }
            return null;
        } catch (Throwable $e) {
            OnboardErrorLog::log($e, 'Onboard get_setting error:');
            return null;
        }
    }

    /**
     * Save setting value.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     */
    public function save_setting($key, $value) {
        if (!$this->connected) {
            return false;
        }

        try {
            $json_value = json_encode($value);
            $now = gmdate('Y-m-d H:i:s');

            // Check if exists.
            $stmt = $this->query(
                'SELECT setting_key FROM plugin_settings WHERE setting_key = ?',
                array($key)
            );

            if ($stmt && $stmt->fetch()) {
                // Update.
                $this->query(
                    'UPDATE plugin_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
                    array($json_value, $now, $key)
                );
            } else {
                // Insert.
                $this->query(
                    'INSERT INTO plugin_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
                    array($key, $json_value, $now)
                );
            }
            return true;
        } catch (Throwable $e) {
            OnboardErrorLog::log($e, 'Onboard save_setting error:');
            return false;
        }
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_all_settings() {
        if (!$this->connected) {
            return array();
        }

        try {
            $stmt = $this->query('SELECT * FROM plugin_settings');
            if (!$stmt) {
                return array();
            }

            $results = $stmt->fetchAll();
            $settings = array();

            foreach ($results as $row) {
                $decoded = json_decode($row['setting_value'], true);
                $settings[$row['setting_key']] = ($decoded !== null || $row['setting_value'] === 'null') ? $decoded : $row['setting_value'];
            }
            return $settings;
        } catch (Exception $e) {
            OnboardErrorLog::log($e, 'Onboard get_all_settings error:');
            return array();
        }
    }
}
