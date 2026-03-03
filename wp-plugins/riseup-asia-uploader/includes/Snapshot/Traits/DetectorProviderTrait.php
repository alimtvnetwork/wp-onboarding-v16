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
            $this->detectWpReset(),
            $this->detectUpdraft(),
            $this->detectNative(),
        );
        $this->logDetectionResults($providers);

        return $providers;
    }

    private function detectWpReset(): array {
        $result = array(
            ResponseKeyType::Id->value                 => SnapshotProviderType::WpReset->value,
            ResponseKeyType::Name->value               => 'WP Reset',
            ResponseKeyType::Available->value          => false,
            ResponseKeyType::Capabilities->value       => array(),
            ResponseKeyType::Version->value            => null,
            ResponseKeyType::DetectionMethod->value    => null,
        );

        if (class_exists('WP_Reset')) {
            $result[ResponseKeyType::Available->value] = true;
            $result[ResponseKeyType::DetectionMethod->value] = 'class_exists';
        }

        $isStillUnavailable = ($result[ResponseKeyType::Available->value] === false);

        if ($isStillUnavailable) {
            $pluginFile = 'wp-reset/wp-reset.php';

            if (is_plugin_active($pluginFile) || is_plugin_active_for_network($pluginFile)) {
                $result[ResponseKeyType::Available->value] = true;
                $result[ResponseKeyType::DetectionMethod->value] = 'plugin_active';
            }
        }

        $isStillUnavailableForPro = ($result[ResponseKeyType::Available->value] === false) && class_exists('WP_Reset_Pro');

        if ($isStillUnavailableForPro) {
            $result[ResponseKeyType::Available->value] = true;
            $result[ResponseKeyType::Name->value] = 'WP Reset Pro';
            $result[ResponseKeyType::DetectionMethod->value] = 'class_exists_pro';
        }

        if ($result[ResponseKeyType::Available->value]) {
            if (defined('WP_RESET_VERSION')) {
                $result[ResponseKeyType::Version->value] = WP_RESET_VERSION;
            }

            $result[ResponseKeyType::Capabilities->value] = array(
                ResponseKeyType::FullSite->value     => true,
                ResponseKeyType::DatabaseOnly->value => true,
                ResponseKeyType::Selective->value    => true,
                ResponseKeyType::Scheduled->value    => false,
                ResponseKeyType::Restore->value      => true,
                ResponseKeyType::Export->value       => true,
                ResponseKeyType::Import->value       => true,
            );
        }

        return $result;
    }

    private function detectUpdraft(): array {
        $result = array(
            ResponseKeyType::Id->value                 => SnapshotProviderType::Updraft->value,
            ResponseKeyType::Name->value               => 'UpdraftPlus',
            ResponseKeyType::Available->value          => false,
            ResponseKeyType::Capabilities->value       => array(),
            ResponseKeyType::Version->value            => null,
            ResponseKeyType::DetectionMethod->value    => null,
        );

        if (class_exists('UpdraftPlus')) {
            $result[ResponseKeyType::Available->value] = true;
            $result[ResponseKeyType::DetectionMethod->value] = 'class_exists';
        }

        $isStillUnavailableWithGlobal = ($result[ResponseKeyType::Available->value] === false) && isset($GLOBALS['updraftplus']);

        if ($isStillUnavailableWithGlobal) {
            $result[ResponseKeyType::Available->value] = true;
            $result[ResponseKeyType::DetectionMethod->value] = 'global_instance';
        }

        $isStillUnavailable = ($result[ResponseKeyType::Available->value] === false);

        if ($isStillUnavailable) {
            $pluginFiles = array('updraftplus/updraftplus.php', 'updraftplus-premium/updraftplus.php');

            foreach ($pluginFiles as $pluginFile) {
                if (is_plugin_active($pluginFile) || is_plugin_active_for_network($pluginFile)) {
                    $result[ResponseKeyType::Available->value] = true;
                    $result[ResponseKeyType::DetectionMethod->value] = 'plugin_active';

                    $isPremiumPlugin = (strpos($pluginFile, 'premium') !== false);

                    if ($isPremiumPlugin) {
                        $result[ResponseKeyType::Name->value] = 'UpdraftPlus Premium';
                    }

                    break;
                }
            }
        }

        if ($result[ResponseKeyType::Available->value]) {
            if (defined('UPDRAFTPLUS_VERSION')) {
                $result[ResponseKeyType::Version->value] = UPDRAFTPLUS_VERSION;
            }

            $isPremium = (strpos($result[ResponseKeyType::Name->value], 'Premium') !== false);

            $result[ResponseKeyType::Capabilities->value] = array(
                ResponseKeyType::FullSite->value     => true,
                ResponseKeyType::DatabaseOnly->value => true,
                ResponseKeyType::Selective->value    => $isPremium,
                ResponseKeyType::Scheduled->value    => true,
                ResponseKeyType::Restore->value      => true,
                ResponseKeyType::Export->value       => true,
                ResponseKeyType::Import->value       => true,
            );
        }

        return $result;
    }

    private function detectNative(): array {
        $hasSqlite = extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');

        return array(
            ResponseKeyType::Id->value                 => SnapshotProviderType::Native->value,
            ResponseKeyType::Name->value               => 'Native SQLite',
            ResponseKeyType::Available->value          => $hasSqlite,
            ResponseKeyType::Capabilities->value       => array(
                ResponseKeyType::FullSite->value     => false,
                ResponseKeyType::DatabaseOnly->value => true,
                ResponseKeyType::Selective->value    => true,
                ResponseKeyType::Scheduled->value    => true,
                ResponseKeyType::Restore->value      => true,
                ResponseKeyType::Export->value       => true,
                ResponseKeyType::Import->value       => true,
            ),
            ResponseKeyType::Version->value            => PluginConfigType::Version->value,
            ResponseKeyType::DetectionMethod->value    => $hasSqlite ? 'extension_loaded' : 'extension_missing',
            ResponseKeyType::SqliteVersion->value      => $hasSqlite ? $this->getSqliteVersion() : null,
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
                error_log('DetectorProviderTrait: SQLite version check failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                return null;
            }
        }

        return null;
    }

    private function logDetectionResults(array $providers): void {
        $available = array_filter($providers, function($p) {
            $isAvailable = $p[ResponseKeyType::Available->value];

            return $isAvailable;
        });

        $this->logger->info('[SNAPSHOT] Provider detection complete', array(
            'total'     => count($providers),
            'available' => count($available),
            ResponseKeyType::Providers->value => array_map(function($p) {
                return array(
                    ResponseKeyType::Id->value        => $p[ResponseKeyType::Id->value],
                    ResponseKeyType::Available->value => $p[ResponseKeyType::Available->value],
                    ResponseKeyType::Version->value   => $p[ResponseKeyType::Version->value],
                );
            }, $providers),
        ));
    }
}
