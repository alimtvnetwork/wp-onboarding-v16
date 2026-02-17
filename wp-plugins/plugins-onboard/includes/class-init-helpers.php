<?php
/**
 * Initialization Helper Functions
 *
 * Reusable functions for ensuring directories and database are properly initialized.
 *
 * @package PluginsOnboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OnboardInitHelpers
 *
 * Static helper methods for initialization tasks.
 */
class OnboardInitHelpers {

    /**
     * Directories initialized flag.
     *
     * @var bool
     */
    private static $is_directories_ready = false;

    /**
     * REUSABLE: Ensure all plugin directories exist and are protected.
     * This function can be called multiple times safely - only runs once.
     *
     * @return bool True if directories are ready, false otherwise.
     */
    public static function ensure_directories_exist() {
        // If already initialized, return success immediately.
        if (self::$is_directories_ready) {
            OnboardLogger::debug('[Directories] Already initialized, skipping');
            return true;
        }

        OnboardLogger::debug('===  ENSURING DIRECTORIES EXIST ===');

        try {
            // STEP 1: Check if OnboardPaths class exists.
            if (OnboardBooleanHelpers::isClassMissing('OnboardPaths')) {
                OnboardLogger::error('[Directories] OnboardPaths class not found');
                return false;
            }
            OnboardLogger::debug('[Directories] OnboardPaths class found');

            // STEP 2: Create all required plugin directories.
            OnboardLogger::debug('[Directories] Creating all required directories...');
            $is_created = OnboardPaths::create_all_directories();

            if (empty($is_created)) {
                OnboardLogger::error('[Directories] Failed to create some directories');
                return false;
            }
            OnboardLogger::debug('[Directories] All directories created');

            // STEP 3: Verify and protect each directory.
            $verified = 0;
            $failed = 0;

            foreach (OnboardPaths::REQUIRED_DIRECTORIES as $dir_type) {
                $dir_path = OnboardPaths::get($dir_type);

                OnboardLogger::debug("[Directories] Verifying: {$dir_type}");

                // Check if path is empty.
                if (empty($dir_path)) {
                    OnboardLogger::error("[Directories]   ✗ Path is empty for: {$dir_type}");
                    $failed++;
                    continue;
                }

                // Check if directory is missing.
                if (OnboardBooleanHelpers::isDirMissing($dir_path)) {
                    OnboardLogger::error("[Directories]   ✗ Does not exist: {$dir_path}");
                    $failed++;
                    continue;
                }

                // Check if directory is read-only.
                if (OnboardBooleanHelpers::isDirReadonly($dir_path)) {
                    OnboardLogger::error("[Directories]   ✗ Read-only: {$dir_path}");
                    $failed++;
                    continue;
                }

                // Protect directory with .htaccess and index.php.
                self::protect_directory($dir_path);
                $verified++;
                OnboardLogger::debug("[Directories]   ✓ Ready: {$dir_type}");
            }

            OnboardLogger::debug("[Directories] Summary: {$verified} ready, {$failed} failed");

            if ($failed > 0) {
                OnboardLogger::error('[Directories] Some directories failed verification');
                return false;
            }

            // Mark as initialized.
            self::$is_directories_ready = true;
            OnboardLogger::debug('=== ALL DIRECTORIES READY ===');
            return true;

        } catch (Exception $e) {
            OnboardLogger::critical('[Directories] Exception during initialization', $e);
            return false;
        }
    }

    /**
     * REUSABLE: Ensure database is initialized and ready.
     * This function can be called multiple times safely.
     *
     * @return OnboardDatabase|null Database instance or null on failure.
     */
    public static function ensure_database_ready() {
        OnboardLogger::debug('=== ENSURING DATABASE IS READY ===');

        try {
            // STEP 1: Ensure directories exist first (database needs directories).
            OnboardLogger::debug('[Database] Step 1: Checking directories...');
            if (!self::ensure_directories_exist()) {
                OnboardLogger::error('[Database] Cannot initialize: directories not ready');
                return null;
            }
            OnboardLogger::debug('[Database] ✓ Directories confirmed ready');

            // STEP 2: Check if OnboardDatabase class exists.
            OnboardLogger::debug('[Database] Step 2: Checking OnboardDatabase class...');
            if (OnboardBooleanHelpers::isClassMissing('OnboardDatabase')) {
                OnboardLogger::error('[Database] OnboardDatabase class not found');
                return null;
            }
            OnboardLogger::debug('[Database] ✓ OnboardDatabase class found');

            // STEP 3: Create database instance.
            OnboardLogger::debug('[Database] Step 3: Creating database instance...');
            $db = new OnboardDatabase();
            OnboardLogger::debug('[Database] ✓ Database instance created');

            // STEP 4: Verify connection.
            OnboardLogger::debug('[Database] Step 4: Verifying connection...');
            if (OnboardBooleanHelpers::isDbDisconnected($db)) {
                $error = $db->get_last_error();
                OnboardLogger::error('[Database] Connection failed: ' . $error);
                return null;
            }
            OnboardLogger::debug('[Database] ✓ Connection verified');

            // STEP 5: Create tables if needed.
            OnboardLogger::debug('[Database] Step 5: Ensuring tables exist...');
            $is_tables_created = $db->create_tables();
            if (empty($is_tables_created)) {
                OnboardLogger::error('[Database] Failed to create tables');
            }
            if ($is_tables_created) {
                OnboardLogger::debug('[Database] ✓ Tables ready');
            }

            OnboardLogger::debug('=== DATABASE READY ===');
            return $db;

        } catch (Exception $e) {
            OnboardLogger::critical('[Database] Exception during initialization', $e);
            return null;
        }
    }

    /**
     * Protect a directory with .htaccess and index.php.
     *
     * @param string $dir_path Directory path.
     */
    private static function protect_directory($dir_path) {
        // Return early if directory is missing.
        if (OnboardBooleanHelpers::isDirMissing($dir_path)) {
            return;
        }

        $htaccess_file = trailingslashit($dir_path) . '.htaccess';
        $index_file = trailingslashit($dir_path) . 'index.php';

        // Create .htaccess if it is missing.
        if (OnboardBooleanHelpers::isFileMissing($htaccess_file)) {
            $content = "Order deny,allow\nDeny from all";
            @file_put_contents($htaccess_file, $content);
            OnboardLogger::debug("[Directories]     → Created .htaccess");
        }

        // Create index.php if it is missing.
        if (OnboardBooleanHelpers::isFileMissing($index_file)) {
            $content = '<?php // Silence is golden.';
            @file_put_contents($index_file, $content);
            OnboardLogger::debug("[Directories]     → Created index.php");
        }
    }

    /**
     * Reset initialization state (for testing purposes).
     */
    public static function reset() {
        self::$is_directories_ready = false;
        OnboardLogger::debug('[InitHelpers] State reset');
    }
}
