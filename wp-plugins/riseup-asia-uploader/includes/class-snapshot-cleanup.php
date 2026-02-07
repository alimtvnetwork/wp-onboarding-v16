<?php
/**
 * Riseup Snapshot Cleanup Engine
 *
 * Implements retention-based cleanup, orphan file detection, and stuck snapshot handling.
 *
 * @package RiseupAsiaUploader
 * @since   1.17.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RiseupSnapshotCleanup {

    /** @var RiseupSnapshotCleanup|null */
    private static $instance = null;

    /** @var object Logger instance */
    private $logger;

    /** @var object Database instance */
    private $db;

    /**
     * Get singleton instance.
     *
     * @param object $logger Logger.
     * @param object $db     Database.
     * @return self
     */
    public static function getInstance($logger, $db) {
        if (self::$instance === null) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    private function __construct($logger, $db) {
        $this->logger = $logger;
        $this->db     = $db;
    }

    /**
     * Execute full cleanup: retention → orphans → stuck snapshots.
     *
     * @param array $options Override options (retention_type, retention_days, retention_count, dry_run).
     * @return array Cleanup result summary.
     */
    public function execute($options = array()) {
        $start = microtime(true);
        $results = array(
            'success'         => true,
            'retention'       => array('deleted' => 0, 'skipped_master' => 0),
            'orphans'         => array('removed' => 0, 'files' => array()),
            'stuck'           => array('cleaned' => 0, 'ids' => array()),
            'errors'          => array(),
            'dry_run'         => !empty($options['dry_run']),
        );

        $dry_run = !empty($options['dry_run']);

        try {
            // 1. Retention-based cleanup
            $results['retention'] = $this->cleanByRetention($options, $dry_run);
        } catch (Exception $e) {
            $results['errors'][] = 'Retention cleanup: ' . $e->getMessage();
            $this->logger->error('Retention cleanup failed: ' . $e->getMessage());
        }

        try {
            // 2. Orphan file detection and removal
            $results['orphans'] = $this->cleanOrphans($dry_run);
        } catch (Exception $e) {
            $results['errors'][] = 'Orphan cleanup: ' . $e->getMessage();
            $this->logger->error('Orphan cleanup failed: ' . $e->getMessage());
        }

        try {
            // 3. Stuck snapshot cleanup (in-progress > N hours)
            $results['stuck'] = $this->cleanStuck($dry_run);
        } catch (Exception $e) {
            $results['errors'][] = 'Stuck cleanup: ' . $e->getMessage();
            $this->logger->error('Stuck snapshot cleanup failed: ' . $e->getMessage());
        }

        $results['duration'] = round(microtime(true) - $start, 3);

        if (empty($results['errors'])) {
            $this->logger->info(sprintf(
                'Snapshot cleanup completed: %d retention-deleted, %d orphans removed, %d stuck cleaned (%.2fs)',
                $results['retention']['deleted'],
                $results['orphans']['removed'],
                $results['stuck']['cleaned'],
                $results['duration']
            ));
        }

        // Audit trail
        if (!$dry_run) {
            $this->logCleanup($results);
        }

        return $results;
    }

    /**
     * Delete snapshots exceeding retention policy.
     * Master (full/type=full) snapshots marked as permanent are never deleted.
     */
    private function cleanByRetention($options, $dry_run) {
        $settings = $this->loadSettings($options);
        $retention_type  = $settings['retention_type'];
        $retention_days  = (int) $settings['retention_days'];
        $retention_count = (int) $settings['retention_count'];

        $result = array('deleted' => 0, 'skipped_master' => 0, 'details' => array());

        if ($retention_type === RISEUP_RETENTION_TYPE_NONE) {
            return $result;
        }

        $pdo = $this->db->getPdo();
        $manager = RiseupSnapshotManager::getInstance($this->logger, $this->db);

        if ($retention_type === RISEUP_RETENTION_TYPE_DAYS && $retention_days > 0) {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
            $stmt = $pdo->prepare("SELECT * FROM snapshots WHERE created_at < :cutoff ORDER BY created_at ASC");
            $stmt->execute(array(':cutoff' => $cutoff));
            $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($expired as $snap) {
                // Never delete master snapshots
                if ($this->isMasterSnapshot($snap)) {
                    $result['skipped_master']++;
                    continue;
                }
                if (!$dry_run) {
                    $manager->deleteSnapshot($snap['id']);
                }
                $result['deleted']++;
                $result['details'][] = array(
                    'id'       => $snap['id'],
                    'filename' => $snap['filename'] ?? '',
                    'reason'   => "older than {$retention_days} days",
                );
            }
        }

        if ($retention_type === RISEUP_RETENTION_TYPE_COUNT && $retention_count > 0) {
            $stmt = $pdo->query("SELECT * FROM snapshots ORDER BY created_at DESC");
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $kept = 0;
            foreach ($all as $snap) {
                // Never delete master snapshots
                if ($this->isMasterSnapshot($snap)) {
                    continue;
                }
                $kept++;
                if ($kept > $retention_count) {
                    if (!$dry_run) {
                        $manager->deleteSnapshot($snap['id']);
                    }
                    $result['deleted']++;
                    $result['details'][] = array(
                        'id'       => $snap['id'],
                        'filename' => $snap['filename'] ?? '',
                        'reason'   => "exceeds max count of {$retention_count}",
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Find and remove orphan files in the snapshots directory that have no matching DB record.
     */
    private function cleanOrphans($dry_run) {
        $result = array('removed' => 0, 'files' => array());

        $snapshots_dir = $this->getSnapshotsDir();
        if (!is_dir($snapshots_dir)) {
            return $result;
        }

        // Get all known snapshot filenames and directories from DB
        $pdo = $this->db->getPdo();
        $stmt = $pdo->query("SELECT filename FROM snapshots");
        $known_files = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['filename'])) {
                $known_files[basename($row['filename'])] = true;
            }
        }

        // Scan filesystem
        $entries = scandir($snapshots_dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if ($entry === '.htaccess' || $entry === 'index.php') continue;

            // Check if this file/directory is tracked
            if (!isset($known_files[$entry])) {
                $full_path = $snapshots_dir . DIRECTORY_SEPARATOR . $entry;
                $result['files'][] = $entry;

                if (!$dry_run) {
                    if (is_dir($full_path)) {
                        $this->recursiveDelete($full_path);
                    } else {
                        @unlink($full_path);
                    }
                    $this->logger->info('Orphan snapshot removed: ' . $entry);
                }
                $result['removed']++;
            }
        }

        return $result;
    }

    /**
     * Clean stuck snapshots (status = running/in_progress for > RISEUP_SNAPSHOT_STUCK_HOURS).
     */
    private function cleanStuck($dry_run) {
        $result = array('cleaned' => 0, 'ids' => array());
        $stuck_hours = defined('RISEUP_SNAPSHOT_STUCK_HOURS') ? RISEUP_SNAPSHOT_STUCK_HOURS : 24;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$stuck_hours} hours"));

        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare(
            "SELECT id, filename, status FROM snapshots WHERE status IN (:s1, :s2) AND created_at < :cutoff"
        );
        $stmt->execute(array(
            ':s1'     => RISEUP_SNAPSHOT_STATUS_RUNNING,
            ':s2'     => 'in_progress',
            ':cutoff' => $cutoff,
        ));
        $stuck = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stuck as $snap) {
            $result['ids'][] = (int) $snap['id'];
            if (!$dry_run) {
                // Mark as failed instead of deleting (preserve for diagnostics)
                $update = $pdo->prepare("UPDATE snapshots SET status = :status, error = :error WHERE id = :id");
                $update->execute(array(
                    ':status' => RISEUP_SNAPSHOT_STATUS_FAILED,
                    ':error'  => "Auto-cleaned: stuck for >{$stuck_hours} hours",
                    ':id'     => $snap['id'],
                ));
                $this->logger->warn(sprintf('Stuck snapshot #%d marked as failed (was %s)', $snap['id'], $snap['status']));
            }
            $result['cleaned']++;
        }

        return $result;
    }

    /**
     * Determine if a snapshot is a master (permanent, never auto-deleted).
     */
    private function isMasterSnapshot($snap) {
        // Per-table full backups and scope=full are considered master
        if (isset($snap['scope']) && $snap['scope'] === 'full') return true;
        if (isset($snap['type']) && $snap['type'] === 'full') return true;
        // Check directory name convention: contains "_full_"
        if (isset($snap['filename']) && strpos($snap['filename'], '_full_') !== false) return true;
        return false;
    }

    /**
     * Load retention settings from options or overrides.
     */
    private function loadSettings($overrides) {
        $defaults = array(
            'retention_type'  => RISEUP_RETENTION_TYPE_DAYS,
            'retention_days'  => RISEUP_SNAPSHOT_RETENTION_DAYS_DEFAULT,
            'retention_count' => RISEUP_SNAPSHOT_RETENTION_COUNT_DEFAULT,
        );

        // Load from WP options
        $saved = get_option(RISEUP_OPTION_SNAPSHOT_SETTINGS, array());
        if (is_array($saved)) {
            $defaults = array_merge($defaults, $saved);
        }

        // Apply overrides
        return array_merge($defaults, array_filter($overrides, function($v) { return $v !== null; }));
    }

    /**
     * Get the snapshots directory path.
     */
    private function getSnapshotsDir() {
        return RiseupPathUtils::getSnapshotsDir();
    }

    /**
     * Recursively delete a directory.
     */
    private function recursiveDelete($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * Log cleanup to audit trail.
     */
    private function logCleanup($results) {
        try {
            $this->db->logTransaction(
                RISEUP_ACTION_SNAPSHOT_CLEANUP,
                json_encode(array(
                    'retention_deleted'   => $results['retention']['deleted'],
                    'retention_skipped'   => $results['retention']['skipped_master'],
                    'orphans_removed'     => $results['orphans']['removed'],
                    'stuck_cleaned'       => $results['stuck']['cleaned'],
                    'errors'              => count($results['errors']),
                )),
                empty($results['errors']) ? RISEUP_STATUS_SUCCESS : RISEUP_STATUS_FAILED,
                RISEUP_TRIGGERED_BY_API
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to log cleanup action: ' . $e->getMessage());
        }
    }
}
