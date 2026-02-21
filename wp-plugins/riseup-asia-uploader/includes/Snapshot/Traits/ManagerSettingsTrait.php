<?php
/**
 * ManagerSettingsTrait — Snapshot settings read/write.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\PluginSelectionType;
use RiseupAsia\Enums\SnapshotConfigType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Snapshot\SnapshotFactory;
use RiseupAsia\Enums\SnapshotModeType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;
use RiseupAsia\Enums\SnapshotWorkerModeType;
use RiseupAsia\Enums\TableType;

trait ManagerSettingsTrait {

    public function getSettings(): array {
        $settings = $this->readSettingsFromDb();

        $defaults = array(
            'mode'               => SnapshotWorkerModeType::PerTable->value,
            'backup_type'        => SnapshotModeType::Incremental->value,
            'worker_count'       => 10,
            'storage_path'       => 'snapshots/',
            'include_plugins'    => true,
            'plugin_selection'   => PluginSelectionType::All->value,
            'retention_days'     => SnapshotConfigType::RetentionDaysDefault->value,
            'retention_count'    => SnapshotConfigType::RetentionCountDefault->value,
            'compression'        => true,
            'batch_size'         => SnapshotConfigType::BatchSize->value,
            'provider'           => SnapshotProviderType::Auto->value,
            'scope'              => SnapshotScopeType::WordPress->value,
            'frequency'          => SnapshotFrequencyType::Manual->value,
            'schedule_time'      => '03:00',
            'pre_restore_backup' => true,
            'custom_tables'      => array(),
        );

        return array_merge($defaults, $settings);
    }

    private function readSettingsFromDb(): array {
        $pdo = $this->db->getPdo();
        $isPdoMissing = ($pdo === null);

        if ($isPdoMissing) {
            return array();
        }

        try {
            $rows = $pdo->query("SELECT Key, Value, Type FROM " . TableType::SnapshotSettings->value)->fetchAll(PDO::FETCH_ASSOC);
            $settings = array();

            foreach ($rows as $row) {
                $key = str_replace('snapshot.', '', $row['Key']);
                $settings[$key] = $this->castSettingValue($row['Value'], $row['Type']);
            }

            return $settings;
        } catch (Throwable $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to read SnapshotSettings from SQLite', array(
                'error' => $e->getMessage(),
            ));

            return array();
        }
    }

    public function updateSettings(array $settings): array {
        $pdo = $this->db->getPdo();

        if ($pdo) {
            try {
                $now = DateHelper::nowUtc();
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO " . TableType::SnapshotSettings->value . " (Key, Value, Type, UpdatedAt) VALUES (?, ?, ?, ?)");

                foreach ($settings as $key => $value) {
                    $dbKey = 'snapshot.' . $key;
                    $type = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
                    $dbValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
                    $stmt->execute(array(
                        $dbKey,
                        $dbValue,
                        $type,
                        $now,
                    ));
                }
            } catch (Throwable $e) {
                $this->log(LogLevelType::Error->value, 'Failed to update SnapshotSettings', array(
                    'error' => $e->getMessage(),
                ));
            }
        }

        if (isset($settings['frequency'])) {
            $updated = $this->getSettings();
            $scheduler = SnapshotFactory::scheduler($this->logger, $this->db);
            $scheduler->syncSchedule($updated);
        }

        $result = $this->getSettings();
        $this->log(LogLevelType::Info->value, 'Snapshot settings updated', array(
            'keys' => array_keys($settings),
        ));

        return $result;
    }

    private function castSettingValue(string $value, string $type): string|int|bool|array {
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'bool':
                return $value === '1' || $value === 'true';
            case 'json':
                return json_decode($value, true) ?: array();
            default:
                return $value;
        }
    }
}
