<?php
/**
 * OrchestratorZipTrait — ZIP export creation and validation.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use RiseupAsia\Enums\LogLevelType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\InitHelpers;

trait OrchestratorZipTrait {
    private function createZipExport(string $snapshotDir, string $title): array {
        try {
            $zipFilename = sanitize_title($title) . '_' . DateHelper::nowFilenameDatetime() . '.zip';
            $zipPath = dirname($snapshotDir) . '/' . $zipFilename;

            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return array(
                    ResponseKeyType::Success->value => false,
                    ResponseKeyType::Error->value   => 'Failed to create ZIP',
                );
            }

            $fileCount = $this->addDirectoryToZip($zip, $snapshotDir);
            $zip->close();

            return $this->validateZipExport($zipPath, $zipFilename, $fileCount);
        } catch (Throwable $e) {
            InitHelpers::errorLog($e, 'OrchestratorZipTrait::createZipExport() failed:');

            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => $e->getMessage(),
            );
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

    private function validateZipExport(
        string $path,
        string $filename,
        int $files,
    ): array {
        $size = filesize($path);

        if ($size === 0) {
            @unlink($path);

            return array(
                ResponseKeyType::Success->value => false,
                ResponseKeyType::Error->value   => 'ZIP export is empty',
            );
        }

        $this->log(LogLevelType::Info->value, 'ZIP export created', array(
            ResponseKeyType::Filename->value => $filename,
            ResponseKeyType::Files->value    => $files,
            ResponseKeyType::Size->value     => $this->formatBytes($size),
        ));

        return array(
            ResponseKeyType::Success->value  => true,
            ResponseKeyType::Path->value     => $path,
            ResponseKeyType::Filename->value => $filename,
            ResponseKeyType::Size->value     => $size,
            ResponseKeyType::Files->value    => $files,
        );
    }
}
