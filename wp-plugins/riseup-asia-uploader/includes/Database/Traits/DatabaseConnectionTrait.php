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
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Database\ORM;
use RiseupAsia\Helpers\DateHelper;

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
        $this->fileLogger->info('Starting database initialization');

        try {
            $this->dbPath = $this->getDatabasePath();
            $this->fileLogger->info('Database path resolved', array('path' => $this->dbPath));

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
        $this->fileLogger->debug('Resolving database path');
        $baseDir = $this->fileLogger->getBaseDir();
        $this->fileLogger->debug('Base directory', array('dir' => $baseDir));

        $isDirCreationFailed = (PathHelper::makeDirectory($baseDir, true) === false);

        if ($isDirCreationFailed) {
            $this->fileLogger->error('Failed to create base directory', array('dir' => $baseDir));

            throw new Exception('Failed to create data directory: ' . $baseDir);
        }

        $dbPath = PathHelper::getDbPath();
        $this->fileLogger->info('Database path set', array('path' => $dbPath));

        return $dbPath;
    }

    /**
     * Initialize the database connection and create tables.
     */
    private function initDatabase() {
        $this->fileLogger->info('Initializing PDO connection');

        try {
            $this->pdo = InitHelpers::initSqliteConnection($this->dbPath, $this->fileLogger);

            if ($this->pdo === null) {
                return false;
            }

            $this->fileLogger->debug('Configuring ORM');
            ORM::configure($this->pdo);
            $this->fileLogger->info('ORM configured');

            $this->createTables();
            $this->fileLogger->info('Database initialization complete');

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
        $this->fileLogger->info('Running database migration - creating/updating tables');

        try {
            $this->ensureSchemaVersionTable();
            $current = $this->getCurrentSchemaVersion();
            $this->fileLogger->info('Current schema version', array('version' => $current));

            $this->runAllMigrations($current);

            $this->fileLogger->info('Database migration complete');
        } catch (PDOException $e) {
            $this->fileLogger->logException($e, 'Database migration failed');

            throw $e;
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
