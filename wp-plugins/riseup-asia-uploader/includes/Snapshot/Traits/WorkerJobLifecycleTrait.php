<?php
/**
 * WorkerJobLifecycleTrait — Job creation, status updates, batch progress, and finalization.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;

trait WorkerJobLifecycleTrait {

    /**
     * Create a snapshot job record in the jobs table.
     *
     * @param string $snapshot_dir Snapshot directory.
     * @param array  $tables       Ordered table list.
     * @param array  $config       Original config.
     * @return int|false Job ID.
     */
    private function createJob($snapshot_dir, $tables, $config) {
        $pdo = $this->db->get_pdo();
        if (!$pdo) return false;

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS " . TABLE_SNAPSHOT_JOBS . " (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_dir TEXT NOT NULL,
                tables_json TEXT NOT NULL,
                pool_size INTEGER NOT NULL DEFAULT " . SNAPSHOT_WORKER_POOL_DEFAULT . ",
                current_batch INTEGER NOT NULL DEFAULT 0,
                tables_exported INTEGER NOT NULL DEFAULT 0,
                total_rows INTEGER NOT NULL DEFAULT 0,
                errors_json TEXT DEFAULT '[]',
                status TEXT NOT NULL DEFAULT '" . SNAPSHOT_JOB_STATUS_QUEUED . "',
                config_json TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                completed_at TEXT
            )");

            $now = gmdate('c');
            $stmt = $pdo->prepare("INSERT INTO " . TABLE_SNAPSHOT_JOBS . "
                (snapshot_dir, tables_json, pool_size, status, config_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute(array(
                $snapshot_dir, json_encode($tables), $this->poolSize,
                SNAPSHOT_JOB_STATUS_QUEUED, json_encode($config), $now, $now,
            ));

            return (int) $pdo->lastInsertId();
        } catch (Exception $e) {
            $this->log(LogLevelType::Error->value, 'Failed to create snapshot job', array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Load a job record.
     *
     * @param PDO $pdo    Database connection.
     * @param int $job_id Job ID.
     * @return array|null Job record.
     */
    private function getJob($pdo, $job_id) {
        $stmt = $pdo->prepare("SELECT * FROM " . TABLE_SNAPSHOT_JOBS . " WHERE id = ?");
        $stmt->execute(array($job_id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update job status.
     *
     * @param PDO         $pdo    Database connection.
     * @param int         $job_id Job ID.
     * @param string      $status New status.
     * @param string|null $error  Error message (if failed).
     */
    private function updateJobStatus($pdo, $job_id, $status, $error = null) {
        $now = gmdate('c');
        $completed = ($status === SNAPSHOT_JOB_STATUS_COMPLETE || $status === SNAPSHOT_JOB_STATUS_FAILED) ? $now : null;

        $stmt = $pdo->prepare("UPDATE " . TABLE_SNAPSHOT_JOBS . "
            SET status = ?, updated_at = ?, completed_at = COALESCE(?, completed_at) WHERE id = ?");
        $stmt->execute(array($status, $now, $completed, $job_id));

        if ($error) {
            $job = $this->getJob($pdo, $job_id);
            $errors = json_decode($job['errors_json'] ?? '[]', true);
            $errors[] = $error;
            $stmt2 = $pdo->prepare("UPDATE " . TABLE_SNAPSHOT_JOBS . " SET errors_json = ? WHERE id = ?");
            $stmt2->execute(array(json_encode($errors), $job_id));
        }
    }

    /**
     * Update job after a batch completes.
     *
     * @param PDO   $pdo            Database connection.
     * @param int   $job_id         Job ID.
     * @param int   $next_batch     Next batch index.
     * @param int   $batch_exported Tables exported in this batch.
     * @param int   $batch_rows     Rows exported in this batch.
     * @param array $batch_errors   Errors from this batch.
     */
    private function updateJobBatchProgress($pdo, $job_id, $next_batch, $batch_exported, $batch_rows, $batch_errors) {
        $now = gmdate('c');
        $job = $this->getJob($pdo, $job_id);
        $all_errors = array_merge(json_decode($job['errors_json'] ?? '[]', true), $batch_errors);

        $stmt = $pdo->prepare("UPDATE " . TABLE_SNAPSHOT_JOBS . "
            SET current_batch = ?, tables_exported = tables_exported + ?,
                total_rows = total_rows + ?, errors_json = ?, updated_at = ?
            WHERE id = ?");

        $stmt->execute(array($next_batch, $batch_exported, $batch_rows, json_encode($all_errors), $now, $job_id));
    }

    /**
     * Finalize a completed job.
     *
     * @param PDO    $pdo          Database connection.
     * @param int    $job_id       Job ID.
     * @param string $snapshot_dir Snapshot directory.
     */
    private function finalizeJob($pdo, $job_id, $snapshot_dir) {
        $job = $this->getJob($pdo, $job_id);

        $root_path = $snapshot_dir . '/a-root.db';
        if (file_exists($root_path)) {
            try {
                $rootPdo = new PDO('sqlite:' . $root_path);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->rootDb->updateStats($rootPdo, (int) $job['tables_exported'], (int) $job['total_rows']);
                $rootPdo = null;
            } catch (Exception $e) {
                $this->log(LogLevelType::Warn->value, 'Failed to finalize a-root.db stats', array('error' => $e->getMessage()));
            }
        }

        $this->updateJobStatus($pdo, $job_id, SNAPSHOT_JOB_STATUS_COMPLETE);

        $errors = json_decode($job['errors_json'] ?? '[]', true);
        $this->log(LogLevelType::Info->value, 'Snapshot job complete', array(
            'job_id' => $job_id, 'tables_exported' => $job['tables_exported'],
            'total_rows' => $job['total_rows'], 'errors' => count($errors),
        ));
    }

    /**
     * Schedule the next worker batch via WP-Cron.
     *
     * @param int $job_id Job ID.
     */
    private function scheduleNextBatch($job_id) {
        wp_schedule_single_event(time() + 5, CRON_SNAPSHOT_WORKER_BATCH, array(array('job_id' => $job_id)));
    }
}
