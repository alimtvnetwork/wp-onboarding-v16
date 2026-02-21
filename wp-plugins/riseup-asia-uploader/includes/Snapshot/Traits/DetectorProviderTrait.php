<?php
/**
 * DetectorProviderTrait — provider detection (WP Reset, Updraft, Native).
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SnapshotProviderType;

trait DetectorProviderTrait {

    public function detectAvailableProviders(): array {
        $providers = array(
            $this->detectWPReset(),
            $this->detectUpdraft(),
            $this->detectNative(),
        );
        $this->logDetectionResults($providers);

        return $providers;
    }

    private function detectWPReset(): array {
        $result = array(
            'id'                                  => SnapshotProviderType::WpReset->value,
            'name'                                => 'WP Reset',
            'available'                           => false,
            'capabilities'                        => array(),
            'version'                             => null,
            ResponseKeyType::DetectionMethod->value => null,
        );

        if (class_exists('WP_Reset')) {
            $result['available'] = true;
            $result[ResponseKeyType::DetectionMethod->value] = 'class_exists';
        }

        $isStillUnavailable = ($result['available'] === false);

        if ($isStillUnavailable) {
            $plugin_file = 'wp-reset/wp-reset.php';

            if (is_plugin_active($plugin_file) || is_plugin_active_for_network($plugin_file)) {
                $result['available'] = true;
                $result[ResponseKeyType::DetectionMethod->value] = 'plugin_active';
            }
        }

        $isStillUnavailableForPro = ($result['available'] === false) && class_exists('WP_Reset_Pro');

        if ($isStillUnavailableForPro) {
            $result['available'] = true;
            $result['name'] = 'WP Reset Pro';
            $result[ResponseKeyType::DetectionMethod->value] = 'class_exists_pro';
        }

        if ($result['available']) {
            if (defined('WP_RESET_VERSION')) {
                $result['version'] = WP_RESET_VERSION;
            }

            $result['capabilities'] = array(
                'fullSite'     => true,
                'databaseOnly' => true,
                'selective'    => true,
                'scheduled'    => false,
                'restore'      => true,
                'export'       => true,
                'import'       => true,
            );
        }

        return $result;
    }

    private function detectUpdraft(): array {
        $result = array(
            'id'                                  => SnapshotProviderType::Updraft->value,
            'name'                                => 'UpdraftPlus',
            'available'                           => false,
            'capabilities'                        => array(),
            'version'                             => null,
            ResponseKeyType::DetectionMethod->value => null,
        );

        if (class_exists('UpdraftPlus')) {
            $result['available'] = true;
            $result[ResponseKeyType::DetectionMethod->value] = 'class_exists';
        }

        $isStillUnavailableWithGlobal = ($result['available'] === false) && isset($GLOBALS['updraftplus']);

        if ($isStillUnavailableWithGlobal) {
            $result['available'] = true;
            $result[ResponseKeyType::DetectionMethod->value] = 'global_instance';
        }

        $isStillUnavailable = ($result['available'] === false);

        if ($isStillUnavailable) {
            $plugin_files = array('updraftplus/updraftplus.php', 'updraftplus-premium/updraftplus.php');

            foreach ($plugin_files as $plugin_file) {
                if (is_plugin_active($plugin_file) || is_plugin_active_for_network($plugin_file)) {
                    $result['available'] = true;
                    $result[ResponseKeyType::DetectionMethod->value] = 'plugin_active';

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
                'fullSite'     => true,
                'databaseOnly' => true,
                'selective'    => $is_premium,
                'scheduled'    => true,
                'restore'      => true,
                'export'       => true,
                'import'       => true,
            );
        }

        return $result;
    }

    private function detectNative(): array {
        $has_sqlite = extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');

        return array(
            'id'                                    => SnapshotProviderType::Native->value,
            'name'                                  => 'Native SQLite',
            'available'                             => $has_sqlite,
            'capabilities'                          => array(
                'fullSite'     => false,
                'databaseOnly' => true,
                'selective'    => true,
                'scheduled'    => true,
                'restore'      => true,
                'export'       => true,
                'import'       => true,
            ),
            'version'                               => PluginConfigType::Version->value,
            ResponseKeyType::DetectionMethod->value => $has_sqlite ? 'extension_loaded' : 'extension_missing',
            ResponseKeyType::SqliteVersion->value   => $has_sqlite ? $this->getSqliteVersion() : null,
        );
    }

    private function getSqliteVersion(): ?string {
        if (class_exists('SQLite3')) {
            $version = SQLite3::version();

            return $version['versionString'];
        }

        if (extension_loaded('pdo_sqlite')) {
            try {
                $pdo = new PDO('sqlite::memory:');

                return $pdo->query('SELECT sqlite_version()')->fetchColumn();
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function logDetectionResults(array $providers): void {
        $available = array_filter($providers, function($p) { return $p['available']; });

        $this->logger->info('[SNAPSHOT] Provider detection complete', array(
            'total'     => count($providers),
            'available' => count($available),
            'providers' => array_map(function($p) {
                return array(
                    'id'        => $p['id'],
                    'available' => $p['available'],
                    'version'   => $p['version'],
                );
            }, $providers),
        ));
    }
}
