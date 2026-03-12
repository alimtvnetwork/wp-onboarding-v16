<?php
/**
 * PSR-4 Autoloader for the QUpload namespace.
 *
 * Maps the QUpload\ namespace prefix to the includes/ directory.
 *
 * @package QUpload
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class QUploadAutoloader {
    private const NAMESPACE_PREFIX = 'QUpload\\';
    private const PREFIX_LENGTH = 8; // strlen('QUpload\\')
    private const LOG_PREFIX = '[QUpload] Autoloader: ';

    public static function register(): void {
        spl_autoload_register([self::class, 'load']);
    }

    private static function writeDiagnostic(string $message): void {
        $uploadsDir = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR . '/uploads/qupload/logs'
            : dirname(__DIR__, 3) . '/uploads/qupload/logs';

        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0775, true);
        }

        $line = '[' . gmdate('c') . '] ' . $message . PHP_EOL;
        @file_put_contents($uploadsDir . '/autoloader.log', $line, FILE_APPEND | LOCK_EX);
    }

    private static function load(string $class): void {
        $isOutsideNamespace = (strncmp($class, self::NAMESPACE_PREFIX, self::PREFIX_LENGTH) !== 0);

        if ($isOutsideNamespace) {
            return;
        }

        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, self::PREFIX_LENGTH)) . '.php';
        $isFileMissing = !file_exists($file);

        if ($isFileMissing) {
            $message = self::LOG_PREFIX . 'class file not found for "' . $class . '" — expected at "' . $file . '"';
            error_log($message);
            self::writeDiagnostic($message);

            return;
        }

        try {
            require_once $file;
            self::writeDiagnostic(self::LOG_PREFIX . 'loaded "' . $class . '" from "' . $file . '"');
        } catch (Throwable $e) {
            $message = self::LOG_PREFIX . 'failed to load "' . $class . '" — ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            error_log($message);
            self::writeDiagnostic($message);
        }
    }
}

QUploadAutoloader::register();
