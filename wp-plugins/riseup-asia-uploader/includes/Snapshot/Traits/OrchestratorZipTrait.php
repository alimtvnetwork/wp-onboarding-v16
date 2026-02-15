<?php
/**
 * OrchestratorZipTrait — ZIP export creation and validation.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

use RiseupAsia\Enums\LogLevelType;

trait OrchestratorZipTrait {

    private function createZipExport(string $snapshotDir, string $title): array {
        try {
            $zip_filename = sanitize_title($title) . '_' . date('Y-m-d_His') . '.zip';
            $zip_path = dirname($snapshotDir) . '/' . $zip_filename;

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array('success' => false, 'error' => 'Failed to create ZIP');
            }

            $file_count = $this->addDirectoryToZip($zip, $snapshotDir);
            $zip->close();

            return $this->validateZipExport($zip_path, $zip_filename, $file_count);
        } catch (Throwable $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

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

    private function validateZipExport(string $path, string $filename, int $files): array {
        $size = filesize($path);
        if ($size === 0) {
            @unlink($path);
            return array('success' => false, 'error' => 'ZIP export is empty');
        }

        $this->log(LogLevelType::Info->value, 'ZIP export created', array('filename' => $filename, 'files' => $files, 'size' => $this->formatBytes($size)));
        return array('success' => true, 'path' => $path, 'filename' => $filename, 'size' => $size, 'files' => $files);
    }
}
