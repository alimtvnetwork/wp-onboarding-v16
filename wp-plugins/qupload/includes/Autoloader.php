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

    private static function load(string $class): void {
        $isOutsideNamespace = (strncmp($class, self::NAMESPACE_PREFIX, self::PREFIX_LENGTH) !== 0);

        if ($isOutsideNamespace) {
            return;
        }

        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, self::PREFIX_LENGTH)) . '.php';

        $isFileMissing = !file_exists($file);

        if ($isFileMissing) {
            error_log(self::LOG_PREFIX . 'class file not found for "' . $class . '" — expected at "' . $file . '"');

            return;
        }

        try {
            require_once $file;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . 'failed to load "' . $class . '" — ' . $e->getMessage());
        }
    }
}

QUploadAutoloader::register();
