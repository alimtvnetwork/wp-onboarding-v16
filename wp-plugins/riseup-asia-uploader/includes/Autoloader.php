<?php
/**
 * PSR-4 Autoloader for the RiseupAsia namespace.
 *
 * Maps the RiseupAsia\ namespace prefix to the includes/ directory.
 *
 * @package RiseupAsia
 * @since   1.61.0
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'RiseupAsia\\';
    $baseDir = __DIR__ . '/';

    $prefixLength = strlen($prefix);
    if (strncmp($class, $prefix, $prefixLength) !== 0) {
        return;
    }

    $relativeClass = substr($class, $prefixLength);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
