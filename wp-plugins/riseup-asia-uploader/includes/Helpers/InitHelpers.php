<?php
/**
 * Riseup Asia Uploader - Initialization Helpers
 *
 * @package RiseupAsia\Helpers
 * @since   1.19.0
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use PDOException;
use RiseupAsia\Helpers\Traits\InitDirTrait;
use RiseupAsia\Helpers\Traits\InitStartupTrait;
use RiseupAsia\Logging\FileLogger;

class InitHelpers {

    use InitDirTrait;
    use InitStartupTrait;

    /** @var array<string, bool> */
    private static $ensured_dirs = array();
    private static $pdo_unavailable_warned = false;
    private static $startup_results = array();

    public static function initSqliteConnection(string $dbPath, FileLogger $logger): ?PDO {
        $prereqError = self::checkSqlitePrerequisites($dbPath, $logger);
        if ($prereqError) {
            return null;
        }

        return self::createPdoConnection($dbPath, $logger);
    }

    private static function checkSqlitePrerequisites(string $dbPath, FileLogger $logger): bool {
        if (BooleanHelpers::isClassMissing('PDO')) {
            self::warnPdoUnavailable($logger, 'PDO class not found - PHP PDO extension not installed.');
            return true;
        }

        if (BooleanHelpers::isExtensionMissing('pdo_sqlite')) {
            self::warnPdoUnavailable($logger, 'PDO SQLite extension not loaded.');
            return true;
        }

        return false;
    }

    private static function warnPdoUnavailable(FileLogger $logger, string $message): void {
        if (self::$pdo_unavailable_warned) { return; }
        $logger->warn('[INIT] ' . $message . ' Database features will be skipped.');
        self::$pdo_unavailable_warned = true;
    }

    private static function createPdoConnection(string $dbPath, FileLogger $logger): ?PDO {
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::applySqlitePragmas($pdo);
            $logger->info('[INIT] SQLite connection established', array('path' => $dbPath));

            return $pdo;
        } catch (PDOException $e) {
            $logger->error('[INIT] SQLite connection failed: ' . $e->getMessage(), array('path' => $dbPath, 'code' => $e->getCode()));

            return null;
        }
    }

    private const DB_WAL_MODE = true;

    private static function applySqlitePragmas(PDO $pdo): void {
        if (self::DB_WAL_MODE) {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
        $pdo->exec('PRAGMA auto_vacuum = INCREMENTAL');
    }

    public static function reset(): void {
        self::$ensured_dirs = array();
        self::$startup_results = array();
        self::$pdo_unavailable_warned = false;
    }
}
