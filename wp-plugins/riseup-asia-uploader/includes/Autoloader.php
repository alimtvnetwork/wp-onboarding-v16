<?php
/**
 * PSR-4 Autoloader for the RiseupAsia namespace.
 *
 * Maps the RiseupAsia\ namespace prefix to the includes/ directory.
 * Cannot use PathHelper or Enums — they depend on this autoloader.
 *
 * @package RiseupAsia
 * @since   1.61.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RiseupAsiaAutoloader {
    private const NAMESPACE_PREFIX = 'RiseupAsia\\';
    private const PREFIX_LENGTH = 10; // strlen('RiseupAsia\\')
    private const LOG_PREFIX = '[Riseup Asia] Autoloader: ';

    /** @var array<int, array{class: string, file: string, error: string}> */
    private static array $failedClasses = [];

    public static function register(): void {
        spl_autoload_register([self::class, 'load']);
    }

    private static function load(string $class): void {
        $isOutsideNamespace = (strncmp($class, self::NAMESPACE_PREFIX, self::PREFIX_LENGTH) !== 0);
        if ($isOutsideNamespace) {
            return;
        }

        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, self::PREFIX_LENGTH)) . '.php';

        $isFileMissing = !file_exists($file);
        if ($isFileMissing) {
            $errorMsg = 'File not found';
            error_log(self::LOG_PREFIX . 'class file not found for "' . $class . '" — expected at "' . $file . '"');
            self::$failedClasses[] = ['class' => $class, 'file' => $file, 'error' => $errorMsg];
            self::reportToBootCollector('autoloader', 'Class file not found: ' . $class . ' — expected at ' . $file);

            return;
        }

        try {
            require_once $file;
        } catch (Throwable $e) {
            error_log(self::LOG_PREFIX . 'failed to load "' . $class . '" — ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            self::$failedClasses[] = ['class' => $class, 'file' => $file, 'error' => $e->getMessage()];
            self::reportToBootCollector('autoloader', 'Failed to load ' . $class . ': ' . $e->getMessage());
        }
    }

    /**
     * Returns classes that failed to load during this request.
     *
     * @return array<int, array{class: string, file: string, error: string}>
     */
    public static function getFailedClasses(): array {
        return self::$failedClasses;
    }

    /**
     * Scans the includes/ directory for all PHP files and validates them
     * using token_get_all() to detect parse errors without executing code.
     *
     * MUST remain self-contained — no Enums, no PathHelper, no helpers.
     *
     * @return array{loaded: string[], failed: array<int, array{file: string, error: string}>}
     */
    public static function runDiagnostics(): array {
        $includesDir = __DIR__;
        $loaded = [];
        $failed = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($includesDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            $isNotPhp = ($fileInfo->getExtension() !== 'php');
            if ($isNotPhp) {
                continue;
            }

            $filePath = $fileInfo->getPathname();

            // Skip this file (Autoloader.php itself)
            $isAutoloader = (realpath($filePath) === realpath(__FILE__));
            if ($isAutoloader) {
                continue;
            }

            $contents = @file_get_contents($filePath);
            $isReadFailed = ($contents === false);
            if ($isReadFailed) {
                $failed[] = ['file' => $filePath, 'error' => 'Unable to read file'];

                continue;
            }

            // Use token_get_all() to detect parse errors without executing
            try {
                $tokens = @token_get_all($contents, TOKEN_PARSE);
                $isTokenizeFailed = ($tokens === false);
                if ($isTokenizeFailed) {
                    $failed[] = ['file' => $filePath, 'error' => 'Tokenization failed'];

                    continue;
                }

                $loaded[] = $filePath;
            } catch (Throwable $e) {
                $failed[] = ['file' => $filePath, 'error' => 'Parse error: ' . $e->getMessage() . "\n" . $e->getTraceAsString()];
            }
        }

        $hasFailures = (count($failed) > 0);
        if ($hasFailures) {
            error_log(self::LOG_PREFIX . 'diagnostics completed — ' . count($loaded) . ' files OK, ' . count($failed) . ' failed');
            foreach ($failed as $entry) {
                error_log(self::LOG_PREFIX . 'diagnostic failure: ' . $entry['file'] . ' — ' . $entry['error']);
            }
        } else {
            error_log(self::LOG_PREFIX . 'diagnostics completed — all ' . count($loaded) . ' files OK');
        }

        return ['loaded' => $loaded, 'failed' => $failed];
    }

    /**
     * Report an error to the BootErrorCollector if available.
     * Must not trigger autoloading loops — checks class_exists with autoload=false.
     */
    private static function reportToBootCollector(string $context, string $message): void {
        $isCollectorLoaded = class_exists('RiseupAsia\\ErrorHandling\\BootErrorCollector', false);
        if ($isCollectorLoaded) {
            \RiseupAsia\ErrorHandling\BootErrorCollector::getInstance()->addError($context, $message);
        }
    }
}

RiseupAsiaAutoloader::register();
