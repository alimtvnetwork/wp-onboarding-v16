<?php
/**
 * Restore Validation Trait
 *
 * Prereq validation, restore order preparation, and safety backup creation.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   1.15.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use Exception;
use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;

trait RestoreValidationTrait {

    private function validateRestorePrereqs(string $snapshotDir, array $options): ?array {
        if (empty($options['confirm']) || $options['confirm'] !== true) {
            return array(
                'success' => false,
                'error'   => 'Restore requires explicit confirmation (confirm=true)',
                'code'    => SnapshotErrorType::RestoreNoConfirm->value,
            );
        }

        $root_path = $snapshotDir . '/a-root.db';
        if (PathHelper::isFileMissing($root_path)) {
            return array(
                'success' => false,
                'error'   => 'Snapshot a-root.db not found at: ' . basename($snapshotDir),
            );
        }

        return null;
    }

    private function prepareRestoreOrder(PDO $rootPdo, array $options): array {
        $mode = $options['mode'] ?? RestoreModeType::Full->value;
        $selected_tables = $options['tables'] ?? array();

        $table_inventory = $this->getTableInventory($rootPdo);
        $restore_order = $this->getRestoreOrder($rootPdo, $table_inventory);

        $isSelectiveWithTables = $mode === RestoreModeType::Selective->value && BooleanHelpers::hasValue($selected_tables);
        if ($isSelectiveWithTables) {
            $restore_order = array_values(array_filter($restore_order, function($t) use ($selected_tables) {
                return in_array($t, $selected_tables);
            }));

            if (empty($restore_order)) {
                return array('success' => false, 'error' => 'None of the selected tables exist in the snapshot');
            }
        }

        $this->log(LogLevelType::Info->value, 'Restore order determined', array(
            'tables' => count($restore_order), 'order' => array_slice($restore_order, 0, 10),
        ));

        return array('success' => true, 'tables' => $restore_order, 'inventory' => $table_inventory);
    }

    private function createSafetyBackup(array $options): ?int {
        $create_backup = $options['create_backup'] ?? true;

        $isBackupSkipped = ($create_backup === false) || ($this->orchestrator === null);
        if ($isBackupSkipped) {
            return null;
        }

        $this->log(LogLevelType::Info->value, 'Creating pre-restore safety backup');
        $result = $this->orchestrator->executeFullBackup(array(
            'title'           => 'Pre-Restore Safety Backup ' . date('Y-m-d H:i'),
            'compression'     => false,
            'include_plugins' => false,
        ));

        if ($result['success']) {
            $this->log(LogLevelType::Info->value, 'Pre-restore backup complete', array('backup_id' => $result['snapshot_id'] ?? null));
            return $result['snapshot_id'] ?? null;
        }

        $this->log(LogLevelType::Warn->value, 'Pre-restore backup failed (continuing)', array('error' => $result['error'] ?? 'Unknown'));

        $isBackupRequired = BooleanHelpers::hasValue($options['require_backup'] ?? null);
        if ($isBackupRequired) {
            throw new Exception('Pre-restore backup failed: ' . ($result['error'] ?? 'Unknown'));
        }

        return null;
    }
}
