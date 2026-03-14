<?php
/**
 * UploadFileSystemTrait — file system operations for plugin extraction.
 *
 * Consumed exclusively via UploadExtractTrait.
 *
 * @package QUpload\Traits\Upload
 * @since   1.0.0
 */

namespace QUpload\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Response;
use ZipArchive;

use QUpload\Enums\HttpStatusType;
use QUpload\Helpers\PathHelper;

trait UploadFileSystemTrait
{
    // ── ZIP Extraction ──────────────────────────────────────────

    /** Extract ZIP to temp dir, then move to correct plugin location. */
    private function extractToPluginsDir(string $tempFile, string $slug, string $targetDir): true|WP_REST_Response
    {
        $tempExtractDir = PathHelper::getTempDir() . '/extract_' . uniqid();
        $isTempExtractReady = PathHelper::ensureDirectory($tempExtractDir);

        if ($isTempExtractReady === false) {
            $this->fileLogger->error('Failed to create temp extraction directory', ['dir' => $tempExtractDir]);

            return $this->errorResponse('Upload failed: could not create extraction directory', HttpStatusType::ServerError->value);
        }

        $this->fileLogger->info('Temp extraction directory created', ['dir' => $tempExtractDir]);

        $extractError = $this->extractZipToTemp($tempFile, $tempExtractDir);

        if ($extractError instanceof WP_REST_Response) {
            return $extractError;
        }

        return $this->moveExtractedToTarget($tempExtractDir, $targetDir);
    }

    /** Open and extract the ZIP, cleaning up temp file. */
    private function extractZipToTemp(string $tempFile, string $tempExtractDir): ?WP_REST_Response
    {
        $this->traceStage('extractZipToTemp:start', ['tempFile' => $tempFile, 'extractDir' => $tempExtractDir]);
        $zip = new ZipArchive();
        $isOpened = $zip->open($tempFile) === true;

        if ($isOpened === false) {
            return $this->handleZipOpenFailure($tempFile, $tempExtractDir);
        }

        $isExtracted = $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempFile);

        if ($isExtracted === false) {
            return $this->handleZipExtractFailure($tempFile, $tempExtractDir);
        }

        $this->traceStage('extractZipToTemp:success', ['extractDir' => $tempExtractDir]);

        return null;
    }

    /** Clean up after a failed ZIP open. */
    private function handleZipOpenFailure(string $tempFile, string $tempExtractDir): WP_REST_Response
    {
        @unlink($tempFile);
        $this->deleteDirectory($tempExtractDir);

        return $this->errorResponse('Failed to open ZIP for extraction', HttpStatusType::ServerError->value);
    }

    /** Clean up after a failed ZIP extraction. */
    private function handleZipExtractFailure(string $tempFile, string $tempExtractDir): WP_REST_Response
    {
        $this->traceStage('extractZipToTemp:extract-failed', ['tempFile' => $tempFile, 'extractDir' => $tempExtractDir]);
        $this->deleteDirectory($tempExtractDir);
        $this->fileLogger->error('ZIP extraction failed');

        return $this->errorResponse('Failed to extract ZIP contents', HttpStatusType::ServerError->value);
    }

    // ── Move Extracted Files ────────────────────────────────────

    /** Locate extracted content and move it to the target plugin directory. */
    private function moveExtractedToTarget(string $tempExtractDir, string $targetDir): true|WP_REST_Response
    {
        $extractedEntries = glob($tempExtractDir . '/*');
        $hasNoContent = $extractedEntries === false || empty($extractedEntries);

        if ($hasNoContent) {
            $this->deleteDirectory($tempExtractDir);

            return $this->errorResponse('No content found in extracted ZIP', HttpStatusType::ServerError->value);
        }

        $isMoved = $this->moveExtractedEntries($extractedEntries, $tempExtractDir, $targetDir);
        $this->deleteDirectory($tempExtractDir);

        if ($isMoved === false) {
            return $this->errorResponse('Failed to move plugin to target directory', HttpStatusType::ServerError->value);
        }

        return true;
    }

    /** Choose the right strategy for moving extracted entries. */
    private function moveExtractedEntries(array $entries, string $tempExtractDir, string $targetDir): bool
    {
        $extractedFolders = array_values(array_filter($entries, 'is_dir'));
        $isSingleFolder = count($extractedFolders) === 1 && count($entries) === 1;

        if ($isSingleFolder) {
            return $this->moveExtractedPlugin($extractedFolders[0], $targetDir);
        }

        return $this->moveExtractedContentsToTarget($tempExtractDir, $targetDir);
    }

    /** Move extracted root contents into the target plugin directory. */
    private function moveExtractedContentsToTarget(string $sourceDir, string $targetDir): bool
    {
        $isTargetReady = PathHelper::ensureDirectory($targetDir);

        if ($isTargetReady === false) {
            $this->fileLogger->error('Failed to create target directory', ['dir' => $targetDir]);

            return false;
        }

        $items = $this->listDirectoryItems($sourceDir);

        if ($items === null) {
            return false;
        }

        return $this->moveDirectoryItems($items, $sourceDir, $targetDir);
    }

    /** Move each item from source to target directory. */
    private function moveDirectoryItems(array $items, string $sourceDir, string $targetDir): bool
    {
        foreach ($items as $item) {
            $isMoved = $this->moveSingleItem($sourceDir . '/' . $item, $targetDir . '/' . $item);

            if ($isMoved === false) {
                return false;
            }
        }

        return true;
    }

    /** Move a single file or directory to target. */
    private function moveSingleItem(string $srcPath, string $dstPath): bool
    {
        if (is_dir($srcPath)) {
            return $this->moveDirectoryItem($srcPath, $dstPath);
        }

        return $this->moveFileItem($srcPath, $dstPath);
    }

    /** Move a directory item with copy fallback. */
    private function moveDirectoryItem(string $srcPath, string $dstPath): bool
    {
        $isMoved = @rename($srcPath, $dstPath);

        if ($isMoved) {
            return true;
        }

        $isCopied = $this->copyDirectory($srcPath, $dstPath);
        $this->deleteDirectory($srcPath);

        if ($isCopied === false) {
            $this->fileLogger->error('Failed to move directory item', ['source' => $srcPath, 'dest' => $dstPath]);
        }

        return $isCopied;
    }

    /** Move a file item with copy fallback. */
    private function moveFileItem(string $srcPath, string $dstPath): bool
    {
        $isMoved = @rename($srcPath, $dstPath);

        if ($isMoved) {
            return true;
        }

        $isCopied = @copy($srcPath, $dstPath);

        if ($isCopied) {
            @unlink($srcPath);

            return true;
        }

        $this->fileLogger->error('Failed to move file item', ['source' => $srcPath, 'dest' => $dstPath]);

        return false;
    }

    /** Move extracted folder to target, with copy fallback. */
    private function moveExtractedPlugin(string $extractedFolder, string $targetDir): bool
    {
        $parentDir = dirname($targetDir);
        $isParentReady = PathHelper::ensureDirectory($parentDir);

        if ($isParentReady === false) {
            $this->fileLogger->error('Failed to create plugin parent directory', ['dir' => $parentDir]);

            return false;
        }

        $isRenamed = rename($extractedFolder, $targetDir);

        if ($isRenamed) {
            $this->fileLogger->info('Plugin installed to correct location', ['target' => $targetDir]);

            return true;
        }

        return $this->movePluginViaCopyFallback($extractedFolder, $targetDir);
    }

    /** Copy fallback when rename fails during plugin move. */
    private function movePluginViaCopyFallback(string $source, string $targetDir): bool
    {
        $this->fileLogger->warn('Rename failed, falling back to copy', ['from' => $source, 'to' => $targetDir]);
        $isTargetReady = PathHelper::ensureDirectory($targetDir);

        if ($isTargetReady === false) {
            $this->fileLogger->error('Failed to create target directory for copy fallback', ['dir' => $targetDir]);

            return false;
        }

        $isCopied = $this->copyDirectory($source, $targetDir);
        $this->deleteDirectory($source);

        if ($isCopied === false) {
            $this->fileLogger->error('Copy fallback failed during plugin move', ['from' => $source, 'to' => $targetDir]);
        }

        return $isCopied;
    }

    // ── Directory Operations ────────────────────────────────────

    /** Recursively delete a directory. */
    private function deleteDirectory(string $dir): bool
    {
        $isDirExists = is_dir($dir);

        if ($isDirExists === false) {
            return true;
        }

        $items = $this->listDirectoryItems($dir);

        if ($items === null) {
            return false;
        }

        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            $isDeleted = is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);

            if ($isDeleted === false) {
                $this->fileLogger->error('Failed to delete path', ['path' => $path]);

                return false;
            }
        }

        return $this->removeEmptyDirectory($dir);
    }

    /** Remove an empty directory after its contents are deleted. */
    private function removeEmptyDirectory(string $dir): bool
    {
        $isRemoved = @rmdir($dir);

        if ($isRemoved === false) {
            $this->fileLogger->error('Failed to remove directory', ['dir' => $dir]);
        }

        return $isRemoved;
    }

    /** List directory items excluding dot entries. */
    private function listDirectoryItems(string $dir): ?array
    {
        $entries = scandir($dir);

        if ($entries === false) {
            $this->fileLogger->error('Failed to read directory', ['dir' => $dir]);

            return null;
        }

        return array_values(array_diff($entries, ['.', '..']));
    }

    /** Recursively copy a directory. */
    private function copyDirectory(string $source, string $dest): bool
    {
        $isDestReady = PathHelper::ensureDirectory($dest);

        if ($isDestReady === false) {
            $this->fileLogger->error('Failed to create destination directory', ['dest' => $dest]);

            return false;
        }

        $items = $this->listDirectoryItems($source);

        if ($items === null) {
            return false;
        }

        return $this->copyDirectoryItems($items, $source, $dest);
    }

    /** Copy each item from source to destination directory. */
    private function copyDirectoryItems(array $items, string $source, string $dest): bool
    {
        foreach ($items as $item) {
            $isCopied = $this->copySingleEntry($source . '/' . $item, $dest . '/' . $item);

            if ($isCopied === false) {
                return false;
            }
        }

        return true;
    }

    /** Copy a single file or directory entry. */
    private function copySingleEntry(string $srcPath, string $dstPath): bool
    {
        if (is_dir($srcPath)) {
            return $this->copyDirectory($srcPath, $dstPath);
        }

        $isFileParentReady = PathHelper::ensureFileParentDirectory($dstPath);

        if ($isFileParentReady === false) {
            $this->fileLogger->error('Failed to create parent directory for copied file', ['dest' => $dstPath]);

            return false;
        }

        $isCopied = @copy($srcPath, $dstPath);

        if ($isCopied === false) {
            $this->fileLogger->error('Failed to copy file', ['source' => $srcPath, 'dest' => $dstPath]);
        }

        return $isCopied;
    }
}
