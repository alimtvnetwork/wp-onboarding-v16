<?php
/**
 * UploadInstallExtractZipTrait — ZIP extraction, error messages, and file operations.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   2.37.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;

use RiseupAsia\Enums\HttpStatusType;

trait UploadInstallExtractZipTrait
{
    /**
     * Deactivate plugin and remove old directory if this is an update.
     */
    private function deactivateIfUpdating(
        $slug,
        $isUpdate,
        $targetDir,
    ) {
        $this->fileLogger->info($isUpdate ? 'Updating existing plugin' : 'Installing new plugin', ['slug' => $slug]);

        $isFreshInstall = ($isUpdate === false);

        if ($isFreshInstall) {
            return false;
        }

        $pluginFile = $this->findPluginFile($slug);
        $isPreviouslyActive = false;

        if ($pluginFile) {
            $isPreviouslyActive = is_plugin_active($pluginFile);

            if ($isPreviouslyActive) {
                deactivate_plugins($pluginFile);
            }
        }

        $this->deleteDirectory($targetDir);

        return $isPreviouslyActive;
    }

    /**
     * Extract ZIP to a temp directory, then move to the correct plugin location.
     */
    private function extractToPluginsDir(
        $tempFile,
        $slug,
        $targetDir,
    ) {
        $tempExtractDir = $this->getTempDir() . '/extract_' . uniqid();
        wp_mkdir_p($tempExtractDir);

        $extractError = $this->openAndExtractZip($tempFile, $tempExtractDir);

        if ($extractError) {
            return $extractError;
        }

        $extractedFolders = glob($tempExtractDir . '/*', GLOB_ONLYDIR);

        if (empty($extractedFolders)) {
            $this->deleteDirectory($tempExtractDir);
            $this->logger->logUploadFailed($slug, 'No folder found in extracted ZIP');

            return $this->errorResponse('No folder found in extracted ZIP', HttpStatusType::InternalServerError->value);
        }

        $isMoved = $this->moveExtractedPlugin($extractedFolders[0], $targetDir);
        $this->deleteDirectory($tempExtractDir);

        if ($isMoved === false) {
            $this->logger->logUploadFailed($slug, 'Failed to move plugin to target directory');

            return $this->errorResponse('Failed to move plugin to target directory', HttpStatusType::InternalServerError->value);
        }

        return true;
    }

    /** Open ZIP, extract to temp dir, and clean up the ZIP file. */
    private function openAndExtractZip(string $tempFile, string $tempExtractDir) {
        $isFileExists = file_exists($tempFile);

        if ($isFileExists === false) {
            $this->fileLogger->error('Temp ZIP file does not exist', ['path' => $tempFile]);
            $this->deleteDirectory($tempExtractDir);

            return $this->errorResponse('Failed to open ZIP for extraction — file does not exist', HttpStatusType::InternalServerError->value);
        }

        $tempFileSize = @filesize($tempFile);
        $this->fileLogger->info('Opening ZIP file', [
            'path'   => $tempFile,
            'size'   => $tempFileSize,
            'exists' => true,
        ]);

        $zip = new ZipArchive();
        $openResult = $zip->open($tempFile);
        $isOpened = ($openResult === true);

        if ($isOpened === false) {
            $errorMsg = $this->zipErrorMessage($openResult);

            $this->fileLogger->error('ZipArchive::open() failed', [
                'path'      => $tempFile,
                'errorCode' => $openResult,
                'errorMsg'  => $errorMsg,
                'fileSize'  => $tempFileSize,
            ]);

            @unlink($tempFile);
            $this->deleteDirectory($tempExtractDir);

            $detail = "Failed to open ZIP for extraction — {$errorMsg} (code: {$openResult}), fileSize: {$tempFileSize} bytes";

            return $this->errorResponse($detail, HttpStatusType::InternalServerError->value);
        }

        $isExtracted = $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempFile);

        if ($isExtracted === false) {
            $this->deleteDirectory($tempExtractDir);
            $this->fileLogger->error('ZIP extraction failed');

            return $this->errorResponse('Failed to extract ZIP contents', HttpStatusType::InternalServerError->value);
        }

        return null;
    }

    /** Translate ZipArchive error code to human-readable message. */
    private function zipErrorMessage(int|bool $code): string
    {
        if ($code === true) {
            return 'OK';
        }

        $messages = [
            ZipArchive::ER_EXISTS   => 'File already exists',
            ZipArchive::ER_INCONS   => 'Inconsistent ZIP archive',
            ZipArchive::ER_INVAL    => 'Invalid argument',
            ZipArchive::ER_MEMORY   => 'Memory allocation failure',
            ZipArchive::ER_NOENT    => 'No such file',
            ZipArchive::ER_NOZIP    => 'Not a ZIP archive',
            ZipArchive::ER_OPEN     => 'Cannot open file',
            ZipArchive::ER_READ     => 'Read error',
            ZipArchive::ER_SEEK     => 'Seek error',
        ];

        return $messages[$code] ?? 'Unknown error (code: ' . $code . ')';
    }

    /** Move extracted plugin folder to target, with copy fallback. */
    private function moveExtractedPlugin(string $extractedFolder, string $targetDir): bool {
        if (rename($extractedFolder, $targetDir)) {
            $this->fileLogger->info('Plugin installed to correct location');

            return true;
        }

        $this->fileLogger->info('Rename failed, falling back to copy');
        $isCopied = $this->copyDirectory($extractedFolder, $targetDir);
        $this->deleteDirectory($extractedFolder);

        if ($isCopied === false) {
            $this->fileLogger->error('Copy fallback failed during plugin move');
        }

        return $isCopied;
    }

    /** Detect the currently installed version of a plugin by slug. */
    private function detectInstalledVersionBySlug(string $slug): ?string
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        wp_cache_delete('plugins', 'plugins');
        $allPlugins = get_plugins();

        foreach ($allPlugins as $file => $data) {
            $isMatch = (dirname($file) === $slug);

            if ($isMatch) {
                return $data['Version'] ?? null;
            }
        }

        return null;
    }
}
