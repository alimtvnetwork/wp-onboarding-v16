<?php
/**
 * FileSystemTrait — file system utilities.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsiaUploader
 */

// Load sub-traits
require_once __DIR__ . '/FileSystemPluginTrait.php';
require_once __DIR__ . '/FileSystemDirTrait.php';

trait FileSystemTrait {
    use FileSystemPluginTrait;
    use FileSystemDirTrait;
}
