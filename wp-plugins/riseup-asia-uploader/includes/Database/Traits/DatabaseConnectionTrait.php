<?php
/**
 * DatabaseConnectionTrait — initialization, connection, and migration orchestration.
 *
 * @package RiseupAsia\Database\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;
use PDO;
use PDOException;
use Throwable;

use RiseupAsia\Database\Orm;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Helpers\PathHelper;

trait DatabaseConnectionTrait {

    /**
     * Initialize database (lazy loading).
     *
     * @return bool True if successful.
     */
    public function init() {
        if ($this->isInitAttempted) {
            return $this->pdo !== null;
        }

        $this->isInitAttempted = true;

        try {
            $this->dbPath = $this->getDatabasePath();

            return $this->initDatabase();
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Database init failed');

            return false;
        }
    }

    /**
     * Get the database file path.
     */
    private function getDatabasePath() {
        $baseDir = $this->fileLogger->getBaseDir();

        $isDirCreationFailed = (PathHelper::makeDirectory($baseDir, true) === false);

        if ($isDirCreationFailed) {
            $this->fileLogger->error('Failed to create base directory', array('dir' => $baseDir));

            throw new Exception('Failed to create data directory: ' . $baseDir);
        }

        $dbPath = PathHelper::getDbPath();

        return $dbPath;
    }

    /**
     * Initialize the database connection and create tables.
     */
    private function initDatabase() {
        $initStart = microtime(true);
        $isVerbose = InitHelpers::isBootVerbose();

        try {
            if ($isVerbose) {
                $this->fileLogger->debug('[BOOT] Initializing PDO connection', array('path' => $this->dbPath));
            }

            $this->pdo = InitHelpers::initSqliteConnection($this->dbPath, $this->fileLogger);

            if ($this->pdo === null) {
                return false;
            }

            if ($isVerbose) {
                $this->fileLogger->debug('[BOOT] Configuring ORM');
            }

            Orm::configure($this->pdo);

            if ($isVerbose) {
                $this->fileLogger->debug('[BOOT] Running database migrations');
            }

            $this->createTables();

            $initMs = round((microtime(true) - $initStart) * 1000, 2);
            $this->fileLogger->info('Database ready', array(
                'path'   => $this->dbPath,
                'timeMs' => $initMs,
            ));

            return true;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Database initialization failed');
            $this->pdo = null;

            return false;
        }
    }

    /**
     * Create database tables (migration orchestrator).
     */
    private function createTables() {

        try {
            $this->ensureSchemaVersionTable();
            $current = $this->getCurrentSchemaVersion();

            $this->runAllMigrations($current);

            $newVersion = $this->getCurrentSchemaVersion();
            if ($newVersion > $current) {
                $this->fileLogger->info('Database migrated', array('from' => $current, 'to' => $newVersion));
            }
        } catch (PDOException $e) {
            $this->fileLogger->logCriticalException($e, 'Database migration failed');
        }
    }

    /** Create the schema_version table if missing. */
    private function ensureSchemaVersionTable() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
            version INTEGER PRIMARY KEY,
            applied_at TEXT NOT NULL
        )");
    }

    /** Get the current schema version from the database. */
    private function getCurrentSchemaVersion(): int {
        $stmt = $this->pdo->query("SELECT MAX(version) as v FROM schema_version");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['v'] ?? 0);
    }

    /** Run all pending migrations in sequence. */
    private function runAllMigrations(int $current) {
        $this->migrateV1Transactions($current);
        $this->createTransactionIndexes();
        $this->migrateV2AgentTables($current);
        $this->migrateV3EnhancedTransactions($current);
        $this->migrateV4SourceMachine($current);
        $this->migrateV5SnapshotTables($current);
        $this->migrateV6RemotePluginsCache($current);
        $this->migrateV7FileHashCache($current);
        $this->migrateV8SnapshotSettings($current);
        $this->migrateV9ErrorSessions($current);
        $this->migrateV10VersionTracking($current);
        $this->migrateV11SnapshotExports($current);
        $this->migrateV12PascalCaseEnumValues($current);
        $this->migrateV13PascalCaseTableAndColumnNames($current);
        $this->migrateV14PascalCaseRemainingValues($current);
        $this->migrateV15UploadSourceAbbreviationFix($current);
        $this->migrateV16ErrorSessionVersion($current);
        $this->migrateV17CloudStorageAccounts($current);
        $this->migrateV18CloudStorageSettings($current);
        $this->migrateV19CloudStorageBackupColumns($current);
        $this->migrateV20CloudStorageBackupHistory($current);
        $this->migrateV21CloudStorageBackupHistoryFolderColumns($current);
    }

    /**
     * Record a schema version.
     */
    private function recordMigration($version) {
        $this->pdo->exec("INSERT INTO schema_version (version, applied_at) VALUES ({$version}, '" . DateHelper::nowUtc() . "')");
        $this->fileLogger->info("Migration v{$version} applied successfully");
    }

    /**
     * Get PDO instance.
     *
     * @return PDO|null
     */
    public function getPdo() {
        $isInitPending = ($this->isInitAttempted === false);

        if ($isInitPending) {
            $this->init();
        }

        return $this->pdo;
    }

    /**
     * Check if database is ready.
     *
     * @return bool
     */
    public function isReady() {
        $isInitPending = ($this->isInitAttempted === false);

        if ($isInitPending) {
            $this->init();
        }

        return $this->pdo !== null;
    }
}
