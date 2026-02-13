<?php
/**
 * DetectorProviderTrait — provider detection (WP Reset, Updraft, Native).
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait DetectorProviderTrait {

    /**
     * Detect all available snapshot providers.
     *
     * @return array Provider detection results.
     */
    public function detectAvailableProviders() {
        $providers = array(
            $this->detectWPReset(),
            $this->detectUpdraft(),
            $this->detectNative(),
        );
        $this->logDetectionResults($providers);
        return $providers;
    }

    /**
     * Detect WP Reset plugin.
     *
     * @return array Provider detection result.
     */
    private function detectWPReset() {
        $result = array(
            'id' => SNAPSHOT_PROVIDER_WP_RESET, 'name' => 'WP Reset', 'available' => false,
            'capabilities' => array(), 'version' => null, 'detection_method' => null,
        );

        if (class_exists('WP_Reset')) {
            $result['available'] = true;
            $result['detection_method'] = 'class_exists';
        }

        if (!$result['available']) {
            $plugin_file = 'wp-reset/wp-reset.php';
            if (is_plugin_active($plugin_file) || is_plugin_active_for_network($plugin_file)) {
                $result['available'] = true;
                $result['detection_method'] = 'plugin_active';
            }
        }

        if (!$result['available'] && class_exists('WP_Reset_Pro')) {
            $result['available'] = true;
            $result['name'] = 'WP Reset Pro';
            $result['detection_method'] = 'class_exists_pro';
        }

        if ($result['available']) {
            if (defined('WP_RESET_VERSION')) {
                $result['version'] = WP_RESET_VERSION;
            }
            $result['capabilities'] = array(
                'full_site' => true, 'database_only' => true, 'selective' => true,
                'scheduled' => false, 'restore' => true, 'export' => true, 'import' => true,
            );
        }

        return $result;
    }

    /**
     * Detect Updraft Plus plugin.
     *
     * @return array Provider detection result.
     */
    private function detectUpdraft() {
        $result = array(
            'id' => SNAPSHOT_PROVIDER_UPDRAFT, 'name' => 'UpdraftPlus', 'available' => false,
            'capabilities' => array(), 'version' => null, 'detection_method' => null,
        );

        if (class_exists('UpdraftPlus')) {
            $result['available'] = true;
            $result['detection_method'] = 'class_exists';
        }

        if (!$result['available'] && isset($GLOBALS['updraftplus'])) {
            $result['available'] = true;
            $result['detection_method'] = 'global_instance';
        }

        if (!$result['available']) {
            $plugin_files = array('updraftplus/updraftplus.php', 'updraftplus-premium/updraftplus.php');
            foreach ($plugin_files as $plugin_file) {
                if (is_plugin_active($plugin_file) || is_plugin_active_for_network($plugin_file)) {
                    $result['available'] = true;
                    $result['detection_method'] = 'plugin_active';
                    if (strpos($plugin_file, 'premium') !== false) {
                        $result['name'] = 'UpdraftPlus Premium';
                    }
                    break;
                }
            }
        }

        if ($result['available']) {
            if (defined('UPDRAFTPLUS_VERSION')) {
                $result['version'] = UPDRAFTPLUS_VERSION;
            }
            $is_premium = strpos($result['name'], 'Premium') !== false;
            $result['capabilities'] = array(
                'full_site' => true, 'database_only' => true, 'selective' => $is_premium,
                'scheduled' => true, 'restore' => true, 'export' => true, 'import' => true,
            );
        }

        return $result;
    }

    /**
     * Detect native SQLite provider (always available).
     *
     * @return array Provider detection result.
     */
    private function detectNative() {
        $has_sqlite = extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
        return array(
            'id' => SNAPSHOT_PROVIDER_NATIVE, 'name' => 'Native SQLite', 'available' => $has_sqlite,
            'capabilities' => array(
                'full_site' => false, 'database_only' => true, 'selective' => true,
                'scheduled' => true, 'restore' => true, 'export' => true, 'import' => true,
            ),
            'version' => PLUGIN_VERSION,
            'detection_method' => $has_sqlite ? 'extension_loaded' : 'extension_missing',
            'sqlite_version' => $has_sqlite ? $this->getSqliteVersion() : null,
        );
    }

    /**
     * Get SQLite version.
     *
     * @return string|null SQLite library version.
     */
    private function getSqliteVersion() {
        if (class_exists('SQLite3')) {
            $version = SQLite3::version();
            return $version['versionString'];
        }
        if (extension_loaded('pdo_sqlite')) {
            try {
                $pdo = new PDO('sqlite::memory:');
                return $pdo->query('SELECT sqlite_version()')->fetchColumn();
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Log detection results.
     *
     * @param array $providers Detected providers.
     */
    private function logDetectionResults($providers) {
        $available = array_filter($providers, function($p) { return $p['available']; });
        $this->logger->info('[SNAPSHOT] Provider detection complete', array(
            'total' => count($providers), 'available' => count($available),
            'providers' => array_map(function($p) {
                return array('id' => $p['id'], 'available' => $p['available'], 'version' => $p['version']);
            }, $providers),
        ));
    }
}
