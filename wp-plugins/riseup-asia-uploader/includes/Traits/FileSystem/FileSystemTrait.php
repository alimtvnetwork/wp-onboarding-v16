<?php
/**
 * FileSystemTrait — file system utilities.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\FileSystem
 */

namespace RiseupAsia\Traits\FileSystem;

if (!defined('ABSPATH')) {
    exit;
}

trait FileSystemTrait {
    use FileSystemPluginTrait;
    use FileSystemDirTrait;
}