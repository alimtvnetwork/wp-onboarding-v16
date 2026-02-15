<?php
/**
 * Restore Incremental Trait
 *
 * Incremental backup application during restore.
 *
 * @package RiseupAsiaUploader
 * @since   1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\RestoreModeType;

trait RestoreIncrementalTrait {

    private function applyIncrementalsPhase(PDO $rootPdo, string $snapshotDir, array $restoreOrder, string $mode, bool $applyIncrementals): array {
        $shouldApply = ($applyIncrementals && $mode !== RestoreModeType::Incremental->value) || $mode === RestoreModeType::Incremental->value;

        if (!$shouldApply) {
            return array('applied' => 0, 'total_rows' => 0, 'errors' => array());
        }

        return $this->applyIncrementals($rootPdo, $snapshotDir, $restoreOrder);
    }

    private function applyIncrementals(PDO $rootPdo, string $snapshotDir, array $restoreOrder): array {
        $incrementals = $rootPdo->query(
            "SELECT sequence_num, folder_name, relative_path FROM incremental_backups ORDER BY sequence_num ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($incrementals)) {
            return array('applied' => 0, 'total_rows' => 0, 'errors' => array());
        }

        $this->log(LogLevelType::Info->value, 'Applying incrementals', array('count' => count($incrementals)));

        $applied = 0;
        $total_rows = 0;
        $errors = array();

        foreach ($incrementals as $inc) {
            $result = $this->applySingleIncremental($inc, $snapshotDir, $restoreOrder);
            $total_rows += $result['rows'];
            $applied++;
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        return array('applied' => $applied, 'total_rows' => $total_rows, 'errors' => $errors);
    }

    private function applySingleIncremental(array $inc, string $snapshotDir, array $restoreOrder): array {
        $inc_dir = $snapshotDir . '/' . rtrim($inc['relative_path'], '/');
        if (RiseupBooleanHelpers::isDirMissing($inc_dir)) {
            $this->log(LogLevelType::Warn->value, 'Incremental directory missing', array('folder' => $inc['folder_name']));
            return array('rows' => 0, 'errors' => array('Incremental directory missing: ' . $inc['folder_name']));
        }

        $this->log(LogLevelType::Info->value, 'Applying incremental: ' . $inc['folder_name']);

        $sqlite_files = glob($inc_dir . '/*.sqlite');
        $inc_rows = 0;
        $errors = array();

        foreach ($sqlite_files as $sqlite_file) {
            $table = basename($sqlite_file, '.sqlite');
            if (RiseupBooleanHelpers::isNotInList($table, $restoreOrder)) {
                continue;
            }

            $result = $this->restoreTableFromFile($sqlite_file, $table, 'merge');
            if ($result['success']) {
                $inc_rows += $result['rows'];
                $this->log(LogLevelType::Info->value, sprintf('Incremental %s: %s (+%d rows)', $inc['folder_name'], $table, $result['rows']));
            } else {
                $errors[] = sprintf('Incremental %s/%s: %s', $inc['folder_name'], $table, $result['error']);
            }
        }

        return array('rows' => $inc_rows, 'errors' => $errors);
    }
}
