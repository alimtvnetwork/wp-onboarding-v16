<?php
/**
 * Restore Validation Trait
 *
 * Prereq validation, restore order preparation, and safety backup creation.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait RestoreValidationTrait {

    /**
     * Validate restore prerequisites (confirmation and root db existence).
     *
     * @param string $snapshotDir Snapshot directory.
     * @param array  $options     Options.
     * @return array|null Error result or null if valid.
     */
    private function validateRestorePrereqs(string $snapshotDir, array $options): ?array {
        if (empty($options['confirm']) || $options['confirm'] !== true) {
            return array(
                'success' => false,
                'error'   => 'Restore requires explicit confirmation (confirm=true)',
                'code'    => ERR_RESTORE_NO_CONFIRM,
            );
        }

        $root_path = $snapshotDir . '/a-root.db';
        if (RiseupBooleanHelpers::isFileMissing($root_path)) {
            return array(
                'success' => false,
                'error'   => 'Snapshot a-root.db not found at: ' . basename($snapshotDir),
            );
        }

        return null;
    }

    /**
     * Prepare the restore order from inventory and dependency graph.
     *
     * @param PDO   $rootPdo Root DB connection.
     * @param array $options Restore options.
     * @return array Result with success, tables, inventory.
     */
    private function prepareRestoreOrder(PDO $rootPdo, array $options): array {
        $mode = $options['mode'] ?? 'full';
        $selected_tables = $options['tables'] ?? array();

        $table_inventory = $this->getTableInventory($rootPdo);
        $restore_order = $this->getRestoreOrder($rootPdo, $table_inventory);

        if ($mode === 'selective' && !empty($selected_tables)) {
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

    /**
     * Create a pre-restore safety backup if requested.
     *
     * @param array $options Restore options.
     * @return int|null Backup ID or null.
     */
    private function createSafetyBackup(array $options): ?int {
        $create_backup = $options['create_backup'] ?? true;

        if (!$create_backup || !$this->orchestrator) {
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

        if (!empty($options['require_backup'])) {
            throw new Exception('Pre-restore backup failed: ' . ($result['error'] ?? 'Unknown'));
        }

        return null;
    }
}
