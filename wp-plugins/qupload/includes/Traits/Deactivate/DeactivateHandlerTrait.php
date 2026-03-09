<?php
/**
 * DeactivateHandlerTrait — Cleanup handler on plugin deactivation.
 *
 * Clears temporary files created during upload operations.
 *
 * @package QUpload\Traits\Deactivate
 * @since   1.2.0
 */

namespace QUpload\Traits\Deactivate;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Helpers\PathHelper;

trait DeactivateHandlerTrait
{
    /**
     * Handle plugin deactivation — remove temp directory and its contents.
     */
    public function handleDeactivate(): void {
        $this->fileLogger->info('Plugin deactivation started — cleaning temp files');

        $tempDir = PathHelper::getTempDir();

        if (PathHelper::isFileMissing($tempDir)) {
            $this->fileLogger->debug('Temp directory does not exist, nothing to clean', ['path' => $tempDir]);

            return;
        }

        $deleted = $this->deleteTempDirectory($tempDir);

        if ($deleted) {
            $this->fileLogger->info('Temp directory cleaned successfully', ['path' => $tempDir]);
        } else {
            $this->fileLogger->warn('Failed to fully clean temp directory', ['path' => $tempDir]);
        }
    }

    /**
     * Recursively delete a directory and all its contents.
     */
    private function deleteTempDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $scanned = scandir($dir);

        if ($scanned === false) {
            return false;
        }

        $entries = array_diff($scanned, ['.', '..']);

        foreach ($entries as $entry) {
            $path = $dir . '/' . $entry;

            if (is_dir($path)) {
                $this->deleteTempDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }
}
