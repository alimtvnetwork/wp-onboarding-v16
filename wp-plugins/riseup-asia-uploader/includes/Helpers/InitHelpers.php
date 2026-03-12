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
use Throwable;

use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Helpers\Traits\InitDirTrait;
use RiseupAsia\Helpers\Traits\InitStartupTrait;
use RiseupAsia\Logging\FileLogger;

class InitHelpers {
    use InitDirTrait;
    use InitStartupTrait;

    /** @var array<string, bool> */
    private static $ensuredDirs = array();
    private static $isPdoWarningLogged = false;
    private static $startupResults = array();

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
        if (self::$isPdoWarningLogged) { return; }
        $logger->warn('[INIT] ' . $message . ' Database features will be skipped.');
        self::$isPdoWarningLogged = true;
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
            $logger->logException($e, '[INIT] SQLite connection failed');

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

    /**
     * Write a prefixed message to PHP's native error_log.
     *
     * Use this for early-boot logging where FileLogger is not yet available.
     */
    public static function errorLogWithPrefix(string $message): void {
        error_log(PluginConfigType::LogPrefix->value . ' ' . $message);
    }

    /**
     * Log an exception with context message to PHP's native error_log.
     *
     * Internally appends $e->getMessage() and $e->getTraceAsString().
     * Use this in catch blocks where FileLogger is not available.
     */
    public static function errorLog(Throwable $e, string $context): void {
        error_log($context . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    /**
     * Log an exception and re-throw it.
     *
     * Use this in boot, route registration, and infrastructure catch blocks
     * where silent failure causes cascading breakage. The throw happens internally —
     * call sites do not need a separate `throw $e;` statement.
     *
     * @throws Throwable Always re-throws the original exception after logging.
     */
    public static function errorLogAndThrow(Throwable $e, string $context): never {
        self::errorLog($e, $context);

        throw $e;
    }

    public static function reset(): void {
        self::$ensuredDirs = array();
        self::$startupResults = array();
        self::$isPdoWarningLogged = false;
    }
}
