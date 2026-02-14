<?php
/**
 * Riseup Asia Uploader - Initialization Helpers
 *
 * Shell class delegating to InitDirTrait and InitStartupTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.19.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Traits/InitDirTrait.php';
require_once __DIR__ . '/Traits/InitStartupTrait.php';

/**
 * Class RiseupInitHelpers
 *
 * Centralized initialization utilities for idempotent setup operations.
 */
class RiseupInitHelpers {

    use InitDirTrait;
    use InitStartupTrait;

    /** @var array<string, bool> */
    private static $ensured_dirs = array();

    /** @var bool */
    private static $pdo_unavailable_warned = false;

    /** @var array */
    private static $startup_results = array();

    /**
     * Initialize a PDO SQLite connection with standard settings.
     *
     * @param string            $db_path Path to SQLite database file.
     * @param RiseupFileLogger  $logger  Logger for diagnostics.
     * @return PDO|null PDO instance on success, null on failure.
     */
    public static function initSqliteConnection($db_path, $logger) {
        $prereqError = self::checkSqlitePrerequisites($logger);
        if ($prereqError) {
            return null;
        }

        return self::createPdoConnection($db_path, $logger);
    }

    /** Check that PDO and pdo_sqlite extensions are available. */
    private static function checkSqlitePrerequisites($logger): bool {
        if (RiseupBooleanHelpers::isClassMissing('PDO')) {
            self::warnPdoUnavailable($logger, 'PDO class not found - PHP PDO extension not installed.');

            return true;
        }

        if (RiseupBooleanHelpers::isExtensionMissing('pdo_sqlite')) {
            self::warnPdoUnavailable($logger, 'PDO SQLite extension not loaded.');

            return true;
        }

        return false;
    }

    /** Log a PDO unavailability warning once. */
    private static function warnPdoUnavailable($logger, string $message) {
        if (self::$pdo_unavailable_warned) {
            return;
        }

        $logger->warn('[INIT] ' . $message . ' Database features will be skipped.');
        self::$pdo_unavailable_warned = true;
    }

    /** Create and configure a PDO SQLite connection. */
    private static function createPdoConnection(string $db_path, $logger): ?PDO {
        try {
            $pdo = new PDO('sqlite:' . $db_path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::applySqlitePragmas($pdo);

            $logger->info('[INIT] SQLite connection established', array('path' => $db_path));

            return $pdo;
        } catch (PDOException $e) {
            $logger->error('[INIT] SQLite connection failed: ' . $e->getMessage(), array('path' => $db_path, 'code' => $e->getCode()));

            return null;
        }
    }

    /** Apply PRAGMA settings to a SQLite connection. */
    private static function applySqlitePragmas(PDO $pdo) {
        if (defined('DB_WAL_MODE') && DB_WAL_MODE) {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        $pdo->exec('PRAGMA auto_vacuum = INCREMENTAL');
    }

    /**
     * Reset all tracked state (primarily for testing).
     */
    public static function reset() {
        self::$ensured_dirs = array();
        self::$startup_results = array();
        self::$pdo_unavailable_warned = false;
    }
}
