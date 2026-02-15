<?php
/**
 * ManagerSettingsTrait — Snapshot settings read/write.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\SnapshotFrequencyType;
use RiseupAsia\Enums\SnapshotProviderType;
use RiseupAsia\Enums\SnapshotScopeType;

trait ManagerSettingsTrait {

    public function getSettings(): array {
        $settings = $this->readSettingsFromDb();

        $defaults = array(
            'mode'               => 'per_table',
            'backup_type'        => 'incremental',
            'worker_count'       => 10,
            'storage_path'       => 'snapshots/',
            'include_plugins'    => true,
            'plugin_selection'   => 'all',
            'retention_days'     => SNAPSHOT_RETENTION_DAYS_DEFAULT,
            'retention_count'    => SNAPSHOT_RETENTION_COUNT_DEFAULT,
            'compression'        => true,
            'batch_size'         => SNAPSHOT_BATCH_SIZE,
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
        if (!$pdo) {
            return array();
        }

        try {
            $rows = $pdo->query("SELECT key, value, type FROM snapshot_settings")->fetchAll(PDO::FETCH_ASSOC);
            $settings = array();
            foreach ($rows as $row) {
                $key = str_replace('snapshot.', '', $row['key']);
                $settings[$key] = $this->castSettingValue($row['value'], $row['type']);
            }
            return $settings;
        } catch (Exception $e) {
            $this->log(LogLevelType::Warn->value, 'Failed to read snapshot_settings from SQLite', array('error' => $e->getMessage()));
            return array();
        }
    }

    public function updateSettings(array $settings): array {
        $pdo = $this->db->getPdo();

        if ($pdo) {
            try {
                $now = gmdate('Y-m-d\TH:i:s\Z');
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO snapshot_settings (key, value, type, updated_at) VALUES (?, ?, ?, ?)");

                foreach ($settings as $key => $value) {
                    $dbKey = 'snapshot.' . $key;
                    $type = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
                    $dbValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
                    $stmt->execute(array($dbKey, $dbValue, $type, $now));
                }
            } catch (Exception $e) {
                $this->log(LogLevelType::Error->value, 'Failed to update snapshot_settings', array('error' => $e->getMessage()));
            }
        }

        if (isset($settings['frequency'])) {
            $updated = $this->getSettings();
            $scheduler = RiseupSnapshotFactory::scheduler($this->logger, $this->db);
            $scheduler->syncSchedule($updated);
        }

        $result = $this->getSettings();
        $this->log(LogLevelType::Info->value, 'Snapshot settings updated', array('keys' => array_keys($settings)));

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
