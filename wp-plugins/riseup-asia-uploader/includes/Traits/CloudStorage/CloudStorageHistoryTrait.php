<?php
/**
 * CloudStorageHistoryTrait — Backup history CRUD for CloudStorageBackupHistory.
 *
 * @package RiseupAsia\Traits\CloudStorage
 * @since   2.16.0
 */

namespace RiseupAsia\Traits\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;

use RiseupAsia\Enums\CloudStorageBackupStatusType;
use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\TableType;

trait CloudStorageHistoryTrait {

    /** GET /cloud-storage/backup-history */
    public function handleListBackupHistory(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $accountId = (int) $request->get_param('account_id');
            $page      = max(1, (int) ($request->get_param('page') ?? 1));
            $perPage   = max(1, min(100, (int) ($request->get_param('per_page') ?? 20)));
            $offset    = ($page - 1) * $perPage;

            $table    = TableType::CloudStorageBackupHistory->value;
            $countSql = "SELECT COUNT(*) FROM {$table} WHERE AccountId = :accountId";
            $total    = (int) $this->db->queryScalar($countSql, array('accountId' => $accountId));

            $listSql = sprintf(
                "SELECT * FROM %s WHERE AccountId = :accountId ORDER BY CreatedAt DESC LIMIT :limit OFFSET :offset",
                $table,
            );

            $rows = $this->db->queryAll($listSql, array(
                'accountId' => $accountId,
                'limit'     => $perPage,
                'offset'    => $offset,
            ));

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                'BackupHistory'                 => $rows,
                ResponseKeyType::Total->value   => $total,
                'Page'                          => $page,
                'PerPage'                       => $perPage,
            ), HttpStatusType::Ok->value);

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to list backup history');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** GET /cloud-storage/backup-history/{id} */
    public function handleGetBackupHistoryRecord(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id    = (int) $request->get_param('id');
            $table = TableType::CloudStorageBackupHistory->value;
            $row   = $this->db->queryOne("SELECT * FROM {$table} WHERE Id = :id", array('id' => $id));

            $isNotFound = ($row === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Backup record not found',
                ), HttpStatusType::NotFound->value);
            }

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                'Backup'                        => $row,
            ), HttpStatusType::Ok->value);

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to get backup history record');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    /** DELETE /cloud-storage/backup-history/{id} */
    public function handleDeleteBackupHistoryRecord(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $id    = (int) $request->get_param('id');
            $table = TableType::CloudStorageBackupHistory->value;
            $row   = $this->db->queryOne("SELECT * FROM {$table} WHERE Id = :id", array('id' => $id));

            $isNotFound = ($row === false);

            if ($isNotFound) {
                return new WP_REST_Response(array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Backup record not found',
                ), HttpStatusType::NotFound->value);
            }

            $this->db->execute("DELETE FROM {$table} WHERE Id = :id", array('id' => $id));

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Message->value => 'Backup record deleted',
            ), HttpStatusType::Ok->value);

        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to delete backup history record');

            return new WP_REST_Response(array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            ), HttpStatusType::InternalServerError->value);
        }
    }

    // ── Internal helpers ─────────────────────────────────────────

    /** Insert a new backup history record. Returns the new row ID. */
    private function insertBackupHistory(array $data): int
    {
        $table = TableType::CloudStorageBackupHistory->value;

        $sql = sprintf(
            "INSERT INTO %s (AccountId, BackupType, FileName, RemotePath, RemoteUrl, CommitSha, BranchName, BaseFullBackupId, FileSizeBytes, TablesChanged, RowsChanged, Duration, Status, ErrorMessage)
             VALUES (:accountId, :backupType, :fileName, :remotePath, :remoteUrl, :commitSha, :branchName, :baseFullBackupId, :fileSizeBytes, :tablesChanged, :rowsChanged, :duration, :status, :errorMessage)",
            $table,
        );

        $this->db->execute($sql, array(
            'accountId'        => $data['AccountId'],
            'backupType'       => $data['BackupType'],
            'fileName'         => $data['FileName'],
            'remotePath'       => $data['RemotePath'],
            'remoteUrl'        => $data['RemoteUrl'] ?? '',
            'commitSha'        => $data['CommitSha'] ?? '',
            'branchName'       => $data['BranchName'] ?? 'main',
            'baseFullBackupId' => $data['BaseFullBackupId'] ?? null,
            'fileSizeBytes'    => $data['FileSizeBytes'] ?? 0,
            'tablesChanged'    => $data['TablesChanged'] ?? '',
            'rowsChanged'      => $data['RowsChanged'] ?? 0,
            'duration'         => $data['Duration'] ?? 0,
            'status'           => $data['Status'] ?? CloudStorageBackupStatusType::Pending->value,
            'errorMessage'     => $data['ErrorMessage'] ?? '',
        ));

        return (int) $this->db->lastInsertId();
    }

    /** Update backup history record status and optional fields. */
    private function updateBackupHistoryStatus(
        int $id,
        CloudStorageBackupStatusType $status,
        array $extra = array()
    ): void {
        $table = TableType::CloudStorageBackupHistory->value;
        $sets  = array('Status = :status');
        $params = array('status' => $status->value, 'id' => $id);

        foreach ($extra as $key => $value) {
            $sets[]           = "{$key} = :{$key}";
            $params[$key]     = $value;
        }

        $setSql = implode(', ', $sets);
        $this->db->execute("UPDATE {$table} SET {$setSql} WHERE Id = :id", $params);
    }

    /** Get the latest full backup for an account. */
    private function getLatestFullBackup(int $accountId): array|false
    {
        $table = TableType::CloudStorageBackupHistory->value;

        return $this->db->queryOne(
            "SELECT * FROM {$table} WHERE AccountId = :accountId AND BackupType = :type AND Status = :status ORDER BY CreatedAt DESC LIMIT 1",
            array(
                'accountId' => $accountId,
                'type'      => CloudStorageBackupType::Full->value,
                'status'    => CloudStorageBackupStatusType::Success->value,
            ),
        );
    }

    /** Get a backup history record by ID. */
    private function getBackupHistoryById(int $id): array|false
    {
        $table = TableType::CloudStorageBackupHistory->value;

        return $this->db->queryOne(
            "SELECT * FROM {$table} WHERE Id = :id",
            array('id' => $id),
        );
    }
}
