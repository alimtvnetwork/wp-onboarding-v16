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
use RiseupAsia\Enums\PathDatabaseType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\RestoreModeType;
use RiseupAsia\Enums\SnapshotErrorType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\BooleanHelpers;

trait RestoreValidationTrait {
    private function validateRestorePrereqs(string $snapshotDir, array $options): ?array {
        if (empty($options[ResponseKeyType::Confirm->value]) || $options[ResponseKeyType::Confirm->value] !== true) {
            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => 'Restore requires explicit confirmation (Confirm=true)',
                ResponseKeyType::Code->value    => SnapshotErrorType::RestoreNoConfirm->value,
            );
        }

        $rootPath = $snapshotDir . PathDatabaseType::Root->value;

        if (PathHelper::isFileMissing($rootPath)) {
            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => 'Snapshot a-root.db not found at: ' . basename($snapshotDir),
            );
        }

        return null;
    }

    private function prepareRestoreOrder(PDO $rootPdo, array $options): array {
        $mode = $options[ResponseKeyType::Mode->value] ?? RestoreModeType::Full->value;
        $selectedTables = $options[ResponseKeyType::Tables->value] ?? array();

        $tableInventory = $this->getTableInventory($rootPdo);
        $restoreOrder = $this->getRestoreOrder($rootPdo, $tableInventory);

        $isSelectiveWithTables = $mode === RestoreModeType::Selective->value && BooleanHelpers::hasValue($selectedTables);

        if ($isSelectiveWithTables) {
            $restoreOrder = array_values(array_filter($restoreOrder, function($t) use ($selectedTables) {
                return in_array($t, $selectedTables);
            }));

            if (empty($restoreOrder)) {
                return array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'None of the selected tables exist in the snapshot',
                );
            }
        }

        $this->log(LogLevelType::Info->value, 'Restore order determined', array(
            ResponseKeyType::Tables->value => count($restoreOrder),
            ResponseKeyType::Order->value  => array_slice($restoreOrder, 0, 10),
        ));

        return array(
            ResponseKeyType::Success->value   => true,
            ResponseKeyType::Tables->value    => $restoreOrder,
            ResponseKeyType::Inventory->value => $tableInventory,
        );
    }

    private function createSafetyBackup(array $options): ?int {
        $createBackup = $options[ResponseKeyType::CreateBackup->value] ?? true;

        $isBackupSkipped = ($createBackup === false) || ($this->orchestrator === null);

        if ($isBackupSkipped) {
            return null;
        }

        $this->log(LogLevelType::Info->value, 'Creating pre-restore safety backup');

        $result = $this->orchestrator->executeFullBackup(array(
            ResponseKeyType::Title->value          => 'Pre-Restore Safety Backup ' . DateHelper::nowCompactDatetime(),
            ResponseKeyType::Compression->value     => false,
            ResponseKeyType::IncludePlugins->value  => false,
        ));

        if ($result[ResponseKeyType::Success->value]) {
            $this->log(LogLevelType::Info->value, 'Pre-restore backup complete', array(
                ResponseKeyType::BackupId->value => $result[ResponseKeyType::SnapshotId->value] ?? null,
            ));

            return $result[ResponseKeyType::SnapshotId->value] ?? null;
        }

        $this->log(LogLevelType::Warn->value, 'Pre-restore backup failed (continuing)', array(
            ResponseKeyType::Error->value => $result[ResponseKeyType::Error->value] ?? 'Unknown',
        ));

        $isBackupRequired = BooleanHelpers::hasValue($options[ResponseKeyType::RequireBackup->value] ?? null);

        if ($isBackupRequired) {

            throw new Exception('Pre-restore backup failed: ' . ($result[ResponseKeyType::Error->value] ?? 'Unknown'));
        }

        return null;
    }
}
