<?php
/**
 * UploadExtractTrait — ZIP validation, extraction, activation, and version detection.
 *
 * @package QUpload\Traits\Upload
 * @since   1.0.0
 */

namespace QUpload\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use Throwable;
use WP_REST_Response;
use QUpload\Enums\HttpStatusType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\PathHelper;

trait UploadExtractTrait
{
    /** Write ZIP content to temp file and validate its structure. */
    private function validateAndWriteZip(string $zipContent, string $slug): array|WP_REST_Response {
        $tempDir = PathHelper::getTempDir();
        PathHelper::ensureDirectory($tempDir);

        $tempFile = $tempDir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

        if (file_put_contents($tempFile, $zipContent) === false) {
            $this->fileLogger->error('Failed to write temp file');

            return $this->errorResponse('Upload failed: could not write temp file', HttpStatusType::ServerError->value);
        }

        $detectedSlug = $this->validateZipStructure($tempFile, $slug);

        if ($detectedSlug instanceof WP_REST_Response) {
            return $detectedSlug;
        }

        $finalSlug = !empty($slug) ? $slug : $detectedSlug;
        $this->fileLogger->info('Plugin slug determined', ['slug' => $finalSlug]);

        return [ResponseKeyType::TempFile->value => $tempFile, ResponseKeyType::Slug->value => $finalSlug];
    }

    /** Validate ZIP archive and detect plugin slug. */
    private function validateZipStructure(string $tempFile, string $slug): string|WP_REST_Response {
        $zip = new ZipArchive();

        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            $this->fileLogger->error('Invalid ZIP archive');

            return $this->errorResponse('Invalid ZIP archive', HttpStatusType::BadRequest->value);
        }

        $detectedSlug = $this->detectPluginSlugFromZip($zip);
        $zip->close();

        if ($detectedSlug === null) {
            @unlink($tempFile);
            $this->fileLogger->error('Could not detect plugin in ZIP');

            return $this->errorResponse('Could not detect plugin in ZIP', HttpStatusType::BadRequest->value);
        }

        return $detectedSlug;
    }

    /** Detect plugin slug from ZIP archive contents. */
    private function detectPluginSlugFromZip(ZipArchive $zip): ?string {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $parts = explode('/', $name);
            $hasPluginFolder = (count($parts) >= 2 && !empty($parts[0]));

            if ($hasPluginFolder) {
                return $parts[0];
            }
        }

        return null;
    }

    /** Process extraction, activation, and version detection. */
    private function processExtraction(array $input, array $zipResult): array|WP_REST_Response {
        $slug     = $zipResult[ResponseKeyType::Slug->value];
        $tempFile = $zipResult[ResponseKeyType::TempFile->value];
        $targetDir = WP_PLUGIN_DIR . '/' . $slug;
        $isUpdate = is_dir($targetDir);

        $isPreviouslyActive = $this->deactivateIfUpdating($slug, $isUpdate, $targetDir);

        $extractResult = $this->extractToPluginsDir($tempFile, $slug, $targetDir);

        if ($extractResult instanceof WP_REST_Response) {
            return $extractResult;
        }

        $pluginFile = $this->resetOpcacheAndFindPlugin($slug);

        if ($pluginFile instanceof WP_REST_Response) {
            return $pluginFile;
        }

        $activation = $this->activateIfNeeded($pluginFile, $slug, $input['activate'], $isPreviouslyActive);

        if ($activation instanceof WP_REST_Response) {
            return $activation;
        }

        $version = $this->detectInstalledVersion($pluginFile);

        return [
            ResponseKeyType::Slug->value          => $slug,
            ResponseKeyType::IsUpdate->value      => $isUpdate,
            ResponseKeyType::Activated->value     => $activation['activated'],
            ResponseKeyType::PluginVersion->value => $version,
        ];
    }

    /** Deactivate and remove old directory if updating. */
    private function deactivateIfUpdating(string $slug, bool $isUpdate, string $targetDir): bool {
        $this->fileLogger->info($isUpdate ? 'Updating existing plugin' : 'Installing new plugin', ['slug' => $slug]);

        if ($isUpdate === false) {
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

    /** Extract ZIP to temp dir, then move to correct plugin location. */
    private function extractToPluginsDir(string $tempFile, string $slug, string $targetDir): true|WP_REST_Response {
        $tempExtractDir = PathHelper::getTempDir() . '/extract_' . uniqid();
        wp_mkdir_p($tempExtractDir);

        $zip = new ZipArchive();

        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            $this->deleteDirectory($tempExtractDir);

            return $this->errorResponse('Failed to open ZIP for extraction', HttpStatusType::ServerError->value);
        }

        $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempFile);

        $extractedFolders = glob($tempExtractDir . '/*', GLOB_ONLYDIR);

        if (empty($extractedFolders)) {
            $this->deleteDirectory($tempExtractDir);

            return $this->errorResponse('No folder found in extracted ZIP', HttpStatusType::ServerError->value);
        }

        $this->moveExtractedPlugin($extractedFolders[0], $targetDir);
        $this->deleteDirectory($tempExtractDir);

        return true;
    }

    /** Move extracted folder to target, with copy fallback. */
    private function moveExtractedPlugin(string $extractedFolder, string $targetDir): void {
        if (rename($extractedFolder, $targetDir)) {
            $this->fileLogger->info('Plugin installed to correct location');

            return;
        }

        $this->copyDirectory($extractedFolder, $targetDir);
        $this->deleteDirectory($extractedFolder);
    }

    /** Reset OPcache and locate the plugin's main file. */
    private function resetOpcacheAndFindPlugin(string $slug): string|WP_REST_Response {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $pluginFile = $this->findPluginFile($slug);

        if (empty($pluginFile)) {
            $this->fileLogger->error('Could not find plugin file after extraction', ['slug' => $slug]);

            return $this->errorResponse('Could not find plugin file after extraction', HttpStatusType::ServerError->value);
        }

        wp_cache_delete('plugins', 'plugins');

        return $pluginFile;
    }

    /** Activate the plugin if requested or if previously active. */
    private function activateIfNeeded(
        string $pluginFile,
        string $slug,
        bool $activate,
        bool $wasActive,
    ): array|WP_REST_Response {
        $isActivationSkipped = ($activate === false) && ($wasActive === false);

        if ($isActivationSkipped) {
            return ['activated' => false];
        }

        try {
            $result = activate_plugin($pluginFile);
        } catch (Throwable $e) {
            $this->fileLogger->error('Plugin activation threw an exception', [
                'slug'      => $slug,
                'exception' => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            $this->fileLogger->logException($e, 'Activation exception');

            return $this->errorResponse(
                'Plugin uploaded but activation failed: ' . $e->getMessage(),
                HttpStatusType::ServerError->value,
                $e,
            );
        }

        if (is_wp_error($result)) {
            $this->fileLogger->error('Plugin activation returned WP_Error', [
                'slug'     => $slug,
                'errorMsg' => $result->get_error_message(),
            ]);

            return $this->errorResponse(
                'Plugin uploaded but activation failed: ' . $result->get_error_message(),
                HttpStatusType::ServerError->value,
            );
        }

        return ['activated' => true];
    }

    /** Detect the installed plugin version from disk. */
    private function detectInstalledVersion(string $pluginFile): string {
        $fullPath = WP_PLUGIN_DIR . '/' . $pluginFile;
        clearstatcache(true, $fullPath);

        if (PathHelper::isFileMissing($fullPath)) {
            return '';
        }

        $pluginData = get_plugin_data($fullPath, false, false);

        return $pluginData['Version'] ?? '';
    }

    /** Find plugin main file by slug. */
    private function findPluginFile(string $slug): ?string {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();

        foreach ($allPlugins as $file => $data) {
            $dir = dirname($file);

            if ($dir === $slug) {
                return $file;
            }
        }

        // Fallback: check for {slug}/{slug}.php directly
        $candidate = $slug . '/' . $slug . '.php';

        if (file_exists(WP_PLUGIN_DIR . '/' . $candidate)) {
            return $candidate;
        }

        return null;
    }

    /** Recursively delete a directory. */
    private function deleteDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return true;
        }

        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        return @rmdir($dir);
    }

    /** Recursively copy a directory. */
    private function copyDirectory(string $source, string $dest): void {
        wp_mkdir_p($dest);
        $items = array_diff(scandir($source), ['.', '..']);

        foreach ($items as $item) {
            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
}
