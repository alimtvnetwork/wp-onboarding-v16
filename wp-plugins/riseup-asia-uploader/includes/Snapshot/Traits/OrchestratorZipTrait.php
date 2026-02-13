<?php
/**
 * OrchestratorZipTrait — ZIP export creation and validation.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait OrchestratorZipTrait {

    /**
     * Create a ZIP export of the entire snapshot directory.
     */
    private function createZipExport($snapshot_dir, $title) {
        try {
            $zip_filename = sanitize_title($title) . '_' . date('Y-m-d_His') . '.zip';
            $zip_path = dirname($snapshot_dir) . '/' . $zip_filename;

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array('success' => false, 'error' => 'Failed to create ZIP');
            }

            $file_count = $this->addDirectoryToZip($zip, $snapshot_dir);
            $zip->close();

            return $this->validateZipExport($zip_path, $zip_filename, $file_count);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /** Recursively add files to a ZIP archive. */
    private function addDirectoryToZip(ZipArchive $zip, string $dir): int {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $count = 0;
        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($dir) + 1));
            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($item->getPathname(), $relative);
                $count++;
            }
        }

        return $count;
    }

    /** Validate a ZIP export file. */
    private function validateZipExport(string $path, string $filename, int $files): array {
        $size = filesize($path);
        if ($size === 0) {
            @unlink($path);
            return array('success' => false, 'error' => 'ZIP export is empty');
        }

        $this->log('INFO', 'ZIP export created', array('filename' => $filename, 'files' => $files, 'size' => $this->formatBytes($size)));
        return array('success' => true, 'path' => $path, 'filename' => $filename, 'size' => $size, 'files' => $files);
    }
}
