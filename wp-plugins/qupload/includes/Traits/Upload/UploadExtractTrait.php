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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use WP_REST_Response;
use ZipArchive;

use QUpload\Enums\HttpStatusType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\PathHelper;

trait UploadExtractTrait
{
    private const MAX_SYNTAX_CHECK_FILES = 500;

    /** Write emergency stage trace outside the main logger path. */
    private function traceStage(string $stage, array $context = []): void {
        $baseDir = PathHelper::getBaseDir();
        $traceFile = PathHelper::getStageTraceFile();
        $isBaseReady = PathHelper::ensureDirectory($baseDir);
        $isTraceParentReady = PathHelper::ensureFileParentDirectory($traceFile);

        if ($isBaseReady === false || $isTraceParentReady === false) {
            @error_log('[QUpload Stage] trace directory setup failed for stage: ' . $stage);

            return;
        }

        $line = sprintf(
            "[%s] %s %s%s",
            DateHelper::nowUtc(),
            $stage,
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_SLASHES),
            PHP_EOL,
        );

        $isWritten = @file_put_contents($traceFile, $line, FILE_APPEND | LOCK_EX);

        if ($isWritten === false) {
            @error_log('[QUpload Stage] trace write failed for stage: ' . $stage . ' file: ' . $traceFile);
        }

        @error_log('[QUpload Stage] ' . $stage . (empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES)));
    }

    /** Write ZIP content to temp file and validate its structure. */
    private function validateAndWriteZip(string $zipContent, string $slug): array|WP_REST_Response {
        $this->traceStage('validateAndWriteZip:start', ['slug' => $slug, 'bytes' => strlen($zipContent)]);

        // Ensure base uploads dir exists first
        $baseDir = PathHelper::getBaseDir();
        $isBaseDirReady = PathHelper::ensureDirectory($baseDir);

        if ($isBaseDirReady === false) {
            $this->fileLogger->error('Failed to create base uploads directory', ['dir' => $baseDir]);

            return $this->errorResponse('Upload failed: could not create base directory', HttpStatusType::ServerError->value);
        }

        // Ensure logs dir exists so all subsequent logging works
        $logsDir = PathHelper::getLogsDir();
        $isLogsDirReady = PathHelper::ensureDirectory($logsDir);

        if ($isLogsDirReady === false) {
            $this->traceStage('validateAndWriteZip:logs-dir-failed', ['dir' => $logsDir]);

            return $this->errorResponse('Upload failed: could not create logs directory', HttpStatusType::ServerError->value);
        }

        // Ensure temp dir exists
        $tempDir = PathHelper::getTempDir();
        $isTempDirReady = PathHelper::ensureDirectory($tempDir);

        if ($isTempDirReady === false) {
            $this->fileLogger->error('Failed to create temp directory', ['dir' => $tempDir]);

            return $this->errorResponse('Upload failed: could not create temp directory', HttpStatusType::ServerError->value);
        }

        $this->fileLogger->info('Directories verified', ['base' => $baseDir, 'temp' => $tempDir]);
        $this->traceStage('validateAndWriteZip:directories-ready', ['base' => $baseDir, 'temp' => $tempDir]);

        $tempFile = $tempDir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

        if (file_put_contents($tempFile, $zipContent) === false) {
            $this->traceStage('validateAndWriteZip:temp-write-failed', ['path' => $tempFile]);
            $this->fileLogger->error('Failed to write temp file', ['path' => $tempFile]);

            return $this->errorResponse('Upload failed: could not write temp file', HttpStatusType::ServerError->value);
        }

        $detectedSlug = $this->validateZipStructure($tempFile, $slug);
        $this->traceStage('validateAndWriteZip:zip-written', ['path' => $tempFile]);

        if ($detectedSlug instanceof WP_REST_Response) {
            return $detectedSlug;
        }

        $finalSlug = !empty($slug) ? $slug : $detectedSlug;
        $this->traceStage('validateAndWriteZip:slug-detected', ['slug' => $finalSlug]);
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
        $fallbackSlug = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = trim((string) $zip->getNameIndex($i), '/');

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            if ($fallbackSlug === null) {
                $fallbackSlug = $this->extractSlugFromZipPath($name);
            }

            if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }

            $contents = $zip->getFromIndex($i);

            if ($contents === false) {
                continue;
            }

            if ($this->hasWordPressPluginHeader($contents)) {
                return $this->extractSlugFromZipPath($name);
            }
        }

        return $fallbackSlug;
    }

    /** Extract plugin slug from a ZIP entry path. */
    private function extractSlugFromZipPath(string $path): ?string {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');

        if ($normalizedPath === '') {
            return null;
        }

        $parts = explode('/', $normalizedPath);
        $slug = count($parts) > 1 ? $parts[0] : pathinfo($normalizedPath, PATHINFO_FILENAME);
        $slug = sanitize_file_name($slug);

        return $slug !== '' ? $slug : null;
    }

    /** Check whether PHP contents contain a WordPress plugin header. */
    private function hasWordPressPluginHeader(string $contents): bool {
        $headerSample = substr($contents, 0, 8192);

        return preg_match('/^[ \t\/*#@]*Plugin Name:\s*.+$/mi', $headerSample) === 1;
    }

    /** Process extraction, activation, and version detection. */
    private function processExtraction(array $input, array $zipResult): array|WP_REST_Response {
        $slug     = $zipResult[ResponseKeyType::Slug->value];
        $tempFile = $zipResult[ResponseKeyType::TempFile->value];
        $this->traceStage('processExtraction:start', ['slug' => $slug, 'tempFile' => $tempFile]);
        $targetDir = WP_PLUGIN_DIR . '/' . $slug;
        $isUpdate = is_dir($targetDir);

        $isPreviouslyActive = $this->deactivateIfUpdating($slug, $isUpdate, $targetDir);
        $extractResult = $this->extractToPluginsDir($tempFile, $slug, $targetDir);

        if ($extractResult instanceof WP_REST_Response) {
            return $extractResult;
        }

        return $this->resolvePluginAfterExtract($slug, $isUpdate, $input['activate'], $isPreviouslyActive);
    }

    /** Find plugin file, activate if needed, and build the result. */
    private function resolvePluginAfterExtract(string $slug, bool $isUpdate, bool $activate, bool $wasActive): array|WP_REST_Response {
        $pluginFile = $this->resetOpcacheAndFindPlugin($slug);

        if ($pluginFile instanceof WP_REST_Response) {
            return $pluginFile;
        }

        $validationError = $this->validateExtractedPluginBeforeActivation($slug);

        if ($validationError instanceof WP_REST_Response) {
            return $validationError;
        }

        $activation = $this->activateIfNeeded($pluginFile, $slug, $activate, $wasActive);

        if ($activation instanceof WP_REST_Response) {
            return $activation;
        }

        return $this->buildExtractionResult($slug, $isUpdate, $activation, $pluginFile);
    }

    /** Validate extracted plugin PHP files before activation to prevent fatal crashes. */
    private function validateExtractedPluginBeforeActivation(string $slug): ?WP_REST_Response {
        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

        if (!is_dir($pluginDir)) {
            $this->fileLogger->error('Plugin directory missing before activation validation', ['slug' => $slug, 'dir' => $pluginDir]);

            return $this->errorResponse('Plugin directory missing before activation', HttpStatusType::ServerError->value);
        }

        $phpFiles = $this->collectPhpFiles($pluginDir);
        $checkedCount = 0;

        foreach ($phpFiles as $filePath) {
            if ($checkedCount >= self::MAX_SYNTAX_CHECK_FILES) {
                $this->fileLogger->warn('Syntax check limit reached before activation', [
                    'slug' => $slug,
                    'limit' => self::MAX_SYNTAX_CHECK_FILES,
                    'checked' => $checkedCount,
                ]);

                break;
            }

            $syntaxError = $this->checkPhpFileSyntax($filePath, $pluginDir, $slug);

            if ($syntaxError instanceof WP_REST_Response) {
                return $syntaxError;
            }

            $checkedCount++;
        }

        $this->fileLogger->info('Pre-activation syntax validation passed', ['slug' => $slug, 'filesChecked' => $checkedCount]);

        return null;
    }

    /** Check one PHP file for parse errors without executing it. */
    private function checkPhpFileSyntax(string $filePath, string $pluginDir, string $slug): ?WP_REST_Response {
        $content = @file_get_contents($filePath);
        $relativePath = str_replace($pluginDir . '/', '', $filePath);

        if ($content === false) {
            $this->fileLogger->error('Cannot read extracted PHP file before activation', ['slug' => $slug, 'file' => $relativePath]);

            return $this->errorResponse('Cannot read plugin file before activation: ' . $relativePath, HttpStatusType::ServerError->value);
        }

        try {
            @token_get_all($content, TOKEN_PARSE);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Plugin syntax validation failed for ' . $slug . ' file: ' . $relativePath);

            return $this->errorResponse(
                'Plugin uploaded but activation was blocked due to PHP syntax error in ' . $relativePath . ': ' . $e->getMessage(),
                HttpStatusType::BadRequest->value,
                $e,
            );
        }

        return null;
    }

    /** Collect all PHP files from a plugin directory. */
    private function collectPhpFiles(string $pluginDir): array {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** Build the final extraction result array. */
    private function buildExtractionResult(string $slug, bool $isUpdate, array $activation, string $pluginFile): array {
        return [
            ResponseKeyType::Slug->value          => $slug,
            ResponseKeyType::IsUpdate->value      => $isUpdate,
            ResponseKeyType::Activated->value     => $activation['activated'],
            ResponseKeyType::PluginVersion->value => $this->detectInstalledVersion($pluginFile),
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
        $isTempExtractReady = PathHelper::ensureDirectory($tempExtractDir);

        if ($isTempExtractReady === false) {
            $this->fileLogger->error('Failed to create temp extraction directory', ['dir' => $tempExtractDir]);

            return $this->errorResponse('Upload failed: could not create extraction directory', HttpStatusType::ServerError->value);
        }

        $this->fileLogger->info('Temp extraction directory created', ['dir' => $tempExtractDir]);

        $extractError = $this->extractZipToTemp($tempFile, $tempExtractDir);

        if ($extractError !== null) {
            return $extractError;
        }

        return $this->moveExtractedToTarget($tempExtractDir, $targetDir);
    }

    /** Open and extract the ZIP, cleaning up temp file. */
    private function extractZipToTemp(string $tempFile, string $tempExtractDir): ?WP_REST_Response {
        $this->traceStage('extractZipToTemp:start', ['tempFile' => $tempFile, 'extractDir' => $tempExtractDir]);
        $zip = new ZipArchive();

        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            $this->deleteDirectory($tempExtractDir);

            return $this->errorResponse('Failed to open ZIP for extraction', HttpStatusType::ServerError->value);
        }

        $isExtracted = $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempFile);

        if ($isExtracted === false) {
            $this->traceStage('extractZipToTemp:extract-failed', ['tempFile' => $tempFile, 'extractDir' => $tempExtractDir]);
            $this->deleteDirectory($tempExtractDir);
            $this->fileLogger->error('ZIP extraction failed');

            return $this->errorResponse('Failed to extract ZIP contents', HttpStatusType::ServerError->value);
        }

        $this->traceStage('extractZipToTemp:success', ['extractDir' => $tempExtractDir]);
        return null;
    }

    /** Locate extracted content and move it to the target plugin directory. */
    private function moveExtractedToTarget(string $tempExtractDir, string $targetDir): true|WP_REST_Response {
        $extractedEntries = glob($tempExtractDir . '/*');

        if ($extractedEntries === false || empty($extractedEntries)) {
            $this->deleteDirectory($tempExtractDir);

            return $this->errorResponse('No content found in extracted ZIP', HttpStatusType::ServerError->value);
        }

        $extractedFolders = array_values(array_filter($extractedEntries, 'is_dir'));
        $isSingleFolder = count($extractedFolders) === 1 && count($extractedEntries) === 1;

        if ($isSingleFolder) {
            $isMoved = $this->moveExtractedPlugin($extractedFolders[0], $targetDir);
        } else {
            $isMoved = $this->moveExtractedContentsToTarget($tempExtractDir, $targetDir);
        }

        $this->deleteDirectory($tempExtractDir);

        if ($isMoved === false) {
            return $this->errorResponse('Failed to move plugin to target directory', HttpStatusType::ServerError->value);
        }

        return true;
    }

    /** Move extracted root contents into the target plugin directory. */
    private function moveExtractedContentsToTarget(string $sourceDir, string $targetDir): bool {
        $isTargetReady = PathHelper::ensureDirectory($targetDir);

        if ($isTargetReady === false) {
            $this->fileLogger->error('Failed to create target directory for extracted contents', ['dir' => $targetDir]);

            return false;
        }

        $entries = scandir($sourceDir);

        if ($entries === false) {
            $this->fileLogger->error('Failed to read extracted source directory', ['dir' => $sourceDir]);

            return false;
        }

        $items = array_diff($entries, ['.', '..']);

        foreach ($items as $item) {
            $srcPath = $sourceDir . '/' . $item;
            $dstPath = $targetDir . '/' . $item;

            if (is_dir($srcPath)) {
                $isMoved = @rename($srcPath, $dstPath);

                if ($isMoved === false) {
                    $isMoved = $this->copyDirectory($srcPath, $dstPath);
                    $this->deleteDirectory($srcPath);
                }
            } else {
                $isMoved = @rename($srcPath, $dstPath);

                if ($isMoved === false) {
                    $isMoved = copy($srcPath, $dstPath);

                    if ($isMoved !== false) {
                        @unlink($srcPath);
                    }
                }
            }

            if ($isMoved === false) {
                $this->fileLogger->error('Failed to move extracted item to target directory', ['source' => $srcPath, 'dest' => $dstPath]);

                return false;
            }
        }

        return true;
    }

    /** Move extracted folder to target, with copy fallback. */
    private function moveExtractedPlugin(string $extractedFolder, string $targetDir): bool {
        // Ensure parent directory of target exists (WP_PLUGIN_DIR)
        $parentDir = dirname($targetDir);
        $isParentReady = PathHelper::ensureDirectory($parentDir);

        if ($isParentReady === false) {
            $this->fileLogger->error('Failed to create plugin parent directory', ['dir' => $parentDir]);

            return false;
        }

        if (rename($extractedFolder, $targetDir)) {
            $this->fileLogger->info('Plugin installed to correct location', ['target' => $targetDir]);

            return true;
        }

        $this->fileLogger->warn('Rename failed, falling back to copy', ['from' => $extractedFolder, 'to' => $targetDir]);

        // Ensure target dir exists for copy fallback
        $isTargetReady = PathHelper::ensureDirectory($targetDir);

        if ($isTargetReady === false) {
            $this->fileLogger->error('Failed to create target directory for copy fallback', ['dir' => $targetDir]);

            return false;
        }

        $isCopied = $this->copyDirectory($extractedFolder, $targetDir);
        $this->deleteDirectory($extractedFolder);

        if ($isCopied === false) {
            $this->fileLogger->error('Copy fallback failed during plugin move', ['from' => $extractedFolder, 'to' => $targetDir]);
        }

        return $isCopied;
    }

    /** Reset OPcache and locate the plugin's main file. */
    private function resetOpcacheAndFindPlugin(string $slug): string|WP_REST_Response {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $pluginFile = $this->findPluginMainFileByHeader($slug);

        if (empty($pluginFile)) {
            $pluginFile = $this->findPluginFile($slug);
        }

        if (empty($pluginFile)) {
            $this->fileLogger->error('Could not find plugin file after extraction', ['slug' => $slug]);

            return $this->errorResponse('Could not find plugin file after extraction', HttpStatusType::ServerError->value);
        }

        wp_cache_delete('plugins', 'plugins');

        return $pluginFile;
    }

    /** Find plugin main file directly from WordPress plugin headers on disk. */
    private function findPluginMainFileByHeader(string $slug): ?string {
        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

        if (!is_dir($pluginDir)) {
            return null;
        }

        $phpFiles = $this->collectPhpFiles($pluginDir);

        foreach ($phpFiles as $filePath) {
            $contents = @file_get_contents($filePath);

            if ($contents === false) {
                $this->fileLogger->warn('Could not read PHP file while locating plugin header', ['slug' => $slug, 'file' => $filePath]);

                continue;
            }

            if ($this->hasWordPressPluginHeader($contents)) {
                $relativePath = str_replace($pluginDir . '/', '', $filePath);
                $relativePath = str_replace('\\', '/', $relativePath);

                return $slug . '/' . ltrim($relativePath, '/');
            }
        }

        return null;
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

        $activationError = $this->tryActivatePlugin($pluginFile, $slug);

        if ($activationError !== null) {
            return $activationError;
        }

        return ['activated' => true];
    }

    /** Attempt plugin activation, returning error response on failure. */
    private function tryActivatePlugin(string $pluginFile, string $slug): ?WP_REST_Response {
        $this->traceStage('tryActivatePlugin:start', ['slug' => $slug, 'pluginFile' => $pluginFile]);
        $this->fileLogger->info('Attempting plugin activation', ['slug' => $slug, 'file' => $pluginFile]);

        // Register a shutdown handler to capture fatal errors during activation.
        // activate_plugin() loads the target plugin's code, which may trigger
        // a fatal error (parse error, missing class, etc.) that kills PHP
        // before the try/catch below can execute.
        $fatalLogged = false;
        $logger = $this->fileLogger;
        $shutdownHandler = register_shutdown_function(function () use ($slug, $pluginFile, $logger, &$fatalLogged) {
            $error = error_get_last();
            $isFatal = $error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);

            if ($isFatal && $fatalLogged === false) {
                $fatalLogged = true;
                $thisStage = [
                    'slug' => $slug,
                    'pluginFile' => $pluginFile,
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                ];
                @file_put_contents(PathHelper::getStageTraceFile(), sprintf("[%s] %s %s%s", DateHelper::nowUtc(), 'tryActivatePlugin:fatal', json_encode($thisStage, JSON_UNESCAPED_SLASHES), PHP_EOL), FILE_APPEND | LOCK_EX);
                @error_log('[QUpload Stage] tryActivatePlugin:fatal ' . json_encode($thisStage, JSON_UNESCAPED_SLASHES));
                $message = sprintf(
                    'FATAL during activation of "%s" (%s): %s in %s on line %d',
                    $slug,
                    $pluginFile,
                    $error['message'],
                    $error['file'],
                    $error['line'],
                );
                $logger->error($message);
            }
        });

        try {
            $result = activate_plugin($pluginFile);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Plugin activation threw an exception for slug: ' . $slug);

            return $this->errorResponse(
                'Plugin uploaded but activation failed: ' . $e->getMessage(),
                HttpStatusType::ServerError->value,
                $e,
            );
        }

        $fatalLogged = true; // Prevent shutdown handler from firing on normal exit

        if (is_wp_error($result)) {
            $this->traceStage('tryActivatePlugin:wp-error', ['slug' => $slug, 'message' => $result->get_error_message()]);
            return $this->buildActivationWpError($slug, $result);
        }

        $this->traceStage('tryActivatePlugin:success', ['slug' => $slug]);
        $this->fileLogger->info('Plugin activated successfully', ['slug' => $slug]);

        return null;
    }

    /** Build error response from WP_Error activation result. */
    private function buildActivationWpError(string $slug, object $result): WP_REST_Response {
        $this->fileLogger->error('Plugin activation returned WP_Error', [
            'slug'     => $slug,
            'errorMsg' => $result->get_error_message(),
        ]);

        return $this->errorResponse(
            'Plugin uploaded but activation failed: ' . $result->get_error_message(),
            HttpStatusType::ServerError->value,
        );
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

        $entries = scandir($dir);

        if ($entries === false) {
            $this->fileLogger->error('Failed to read directory for deletion', ['dir' => $dir]);

            return false;
        }

        $items = array_diff($entries, ['.', '..']);

        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            $isDeleted = is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);

            if ($isDeleted === false) {
                $this->fileLogger->error('Failed to delete path while removing directory', ['path' => $path]);

                return false;
            }
        }

        $isRemoved = @rmdir($dir);

        if ($isRemoved === false) {
            $this->fileLogger->error('Failed to remove directory', ['dir' => $dir]);
        }

        return $isRemoved;
    }

    /** Recursively copy a directory. */
    private function copyDirectory(string $source, string $dest): bool {
        $isDestReady = PathHelper::ensureDirectory($dest);

        if ($isDestReady === false) {
            $this->fileLogger->error('Failed to create destination directory for copy', ['dest' => $dest]);

            return false;
        }

        $entries = scandir($source);

        if ($entries === false) {
            $this->fileLogger->error('Failed to read source directory for copy', ['source' => $source]);

            return false;
        }

        $items = array_diff($entries, ['.', '..']);

        foreach ($items as $item) {
            $srcPath = $source . '/' . $item;
            $dstPath = $dest . '/' . $item;

            if (is_dir($srcPath)) {
                $isCopied = $this->copyDirectory($srcPath, $dstPath);
            } else {
                $isFileParentReady = PathHelper::ensureFileParentDirectory($dstPath);

                if ($isFileParentReady === false) {
                    $this->fileLogger->error('Failed to create parent directory for copied file', ['dest' => $dstPath]);

                    return false;
                }

                $isCopied = @copy($srcPath, $dstPath);
            }

            if ($isCopied === false) {
                $this->fileLogger->error('Failed to copy file during extraction', ['source' => $srcPath, 'dest' => $dstPath]);

                return false;
            }
        }

        return true;
    }
}
