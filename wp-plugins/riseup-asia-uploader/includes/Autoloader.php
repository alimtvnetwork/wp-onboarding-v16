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

    public static function register(): void {
        spl_autoload_register([self::class, 'load']);
    }

    private static function load(string $class): void {
        if (strncmp($class, self::NAMESPACE_PREFIX, self::PREFIX_LENGTH) !== 0) {
            return;
        }

        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, self::PREFIX_LENGTH)) . '.php';

        if (file_exists($file)) {
            require_once $file;
        } else {
            error_log('[Riseup Asia] Autoloader: class file not found for "' . $class . '" — expected at "' . $file . '"');
        }
    }
}

RiseupAsiaAutoloader::register();
