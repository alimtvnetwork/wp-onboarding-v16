<?php
/**
 * UploadExtractTrait — ZIP validation, extraction, activation, and version detection.
 *
 * Composes UploadFileSystemTrait and UploadActivationTrait for a complete
 * upload pipeline. Consuming classes only need to `use UploadExtractTrait`.
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
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\PathHelper;
use QUpload\Helpers\UploadBackupHelper;

trait UploadExtractTrait
{
    use UploadFileSystemTrait;
    use UploadActivationTrait;

    private const MAX_SYNTAX_CHECK_FILES = 500;

    // ── Trace Logging ───────────────────────────────────────────

    /** Write emergency stage trace outside the main logger path. */
    private function traceStage(string $stage, array $context = []): void
    {
        $isReady = $this->ensureTraceDirectories();

        if ($isReady === false) {
            @error_log('[QUpload Stage] trace directory setup failed for stage: ' . $stage);

            return;
        }

        $line = $this->buildTraceLogLine($stage, $context);
        $this->writeTraceLineToFile($line, $stage);
    }

    /** Ensure base and trace parent directories exist. */
    private function ensureTraceDirectories(): bool
    {
        $baseDir = PathHelper::getBaseDir();
        $traceFile = PathHelper::getStageTraceFile();
        $isBaseReady = PathHelper::ensureDirectory($baseDir);
        $isTraceParentReady = PathHelper::ensureFileParentDirectory($traceFile);

        return $isBaseReady !== false && $isTraceParentReady !== false;
    }

    /** Build a formatted trace log line. */
    private function buildTraceLogLine(string $stage, array $context): string
    {
        $contextJson = empty($context) ? '' : json_encode($context, JSON_UNESCAPED_SLASHES);

        return sprintf("[%s] %s %s%s", DateHelper::nowLogDisplay(), $stage, $contextJson, PHP_EOL);
    }

    /** Write trace line to file and system error log. */
    private function writeTraceLineToFile(string $line, string $stage): void
    {
        $traceFile = PathHelper::getStageTraceFile();
        $isWritten = @file_put_contents($traceFile, $line, FILE_APPEND | LOCK_EX);

        if ($isWritten === false) {
            @error_log('[QUpload Stage] trace write failed for stage: ' . $stage . ' file: ' . $traceFile);
        }

        @error_log('[QUpload Stage] ' . rtrim($line));
    }

    // ── ZIP Validation & Writing ────────────────────────────────

    /** Write ZIP content to temp file and validate its structure. */
    private function validateAndWriteZip(string $zipContent, string $slug): array|WP_REST_Response
    {
        $this->traceStage('validateAndWriteZip:start', ['slug' => $slug, 'bytes' => strlen($zipContent)]);

        $dirError = $this->ensureUploadDirectories();

        if ($dirError instanceof WP_REST_Response) {
            return $dirError;
        }

        $tempFile = $this->writeZipToTempFile($zipContent, $slug);

        if ($tempFile instanceof WP_REST_Response) {
            return $tempFile;
        }

        return $this->validateAndResolveSlug($tempFile, $slug);
    }

    /** Ensure base, logs, and temp directories exist. */
    private function ensureUploadDirectories(): ?WP_REST_Response
    {
        $directories = [
            ['path' => PathHelper::getBaseDir(), 'label' => 'base'],
            ['path' => PathHelper::getLogsDir(), 'label' => 'logs'],
            ['path' => PathHelper::getTempDir(), 'label' => 'temp'],
        ];

        foreach ($directories as $dir) {
            $error = $this->ensureRequiredDirectory($dir['path'], $dir['label']);

            if ($error instanceof WP_REST_Response) {
                return $error;
            }
        }

        $this->fileLogger->info('Directories verified', ['base' => $directories[0]['path'], 'temp' => $directories[2]['path']]);
        $this->traceStage('validateAndWriteZip:directories-ready', ['base' => $directories[0]['path'], 'temp' => $directories[2]['path']]);

        return null;
    }

    /** Ensure a single required directory exists. */
    private function ensureRequiredDirectory(string $path, string $label): ?WP_REST_Response
    {
        $isReady = PathHelper::ensureDirectory($path);

        if ($isReady === false) {
            $this->fileLogger->error('Failed to create ' . $label . ' directory', ['dir' => $path]);

            return $this->errorResponse('Upload failed: could not create ' . $label . ' directory', HttpStatusType::ServerError->value);
        }

        return null;
    }

    /** Write ZIP content to a temp file on disk. */
    private function writeZipToTempFile(string $zipContent, string $slug): string|WP_REST_Response
    {
        $tempDir = PathHelper::getTempDir();
        $tempFile = $tempDir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';
        $isWritten = file_put_contents($tempFile, $zipContent);

        if ($isWritten === false) {
            $this->traceStage('validateAndWriteZip:temp-write-failed', ['path' => $tempFile]);
            $this->fileLogger->error('Failed to write temp file', ['path' => $tempFile]);

            return $this->errorResponse('Upload failed: could not write temp file', HttpStatusType::ServerError->value);
        }

        $this->traceStage('validateAndWriteZip:zip-written', ['path' => $tempFile]);

        return $tempFile;
    }

    /** Validate ZIP structure and resolve the final plugin slug. */
    private function validateAndResolveSlug(string $tempFile, string $slug): array|WP_REST_Response
    {
        $detectedSlug = $this->validateZipStructure($tempFile, $slug);

        if ($detectedSlug instanceof WP_REST_Response) {
            return $detectedSlug;
        }

        $finalSlug = $slug ?: $detectedSlug;
        $this->traceStage('validateAndWriteZip:slug-detected', ['slug' => $finalSlug]);
        $this->fileLogger->info('Plugin slug determined', ['slug' => $finalSlug]);

        return [ResponseKeyType::TempFile->value => $tempFile, ResponseKeyType::Slug->value => $finalSlug];
    }

    /** Validate ZIP archive and detect plugin slug. */
    private function validateZipStructure(string $tempFile, string $slug): string|WP_REST_Response
    {
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

    // ── ZIP Slug Detection ──────────────────────────────────────

    /** Detect plugin slug from ZIP archive contents. */
    private function detectPluginSlugFromZip(ZipArchive $zip): ?string
    {
        $fallbackSlug = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = trim((string) $zip->getNameIndex($i), '/');
            $isSkippable = $name === '' || str_ends_with($name, '/');

            if ($isSkippable) {
                continue;
            }

            $fallbackSlug ??= $this->extractSlugFromZipPath($name);
            $headerSlug = $this->detectPluginHeaderAtIndex($zip, $name, $i);

            if ($headerSlug !== null) {
                return $headerSlug;
            }
        }

        return $fallbackSlug;
    }

    /** Check a single ZIP entry for a WordPress plugin header. */
    private function detectPluginHeaderAtIndex(ZipArchive $zip, string $name, int $index): ?string
    {
        $isPhp = strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) === 'php';

        if ($isPhp === false) {
            return null;
        }

        $contents = $zip->getFromIndex($index);

        if ($contents === false) {
            return null;
        }

        $hasHeader = $this->hasWordPressPluginHeader($contents);

        return $hasHeader ? $this->extractSlugFromZipPath($name) : null;
    }

    /** Extract plugin slug from a ZIP entry path. */
    private function extractSlugFromZipPath(string $path): ?string
    {
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
    private function hasWordPressPluginHeader(string $contents): bool
    {
        $headerSample = substr($contents, 0, 8192);

        return preg_match('/^[ \t\/*#@]*Plugin Name:\s*.+$/mi', $headerSample) === 1;
    }

    // ── Extraction Orchestration ────────────────────────────────

    /** Process extraction, activation, and version detection with rollback on failure. */
    private function processExtraction(array $input, array $zipResult): array|WP_REST_Response
    {
        $slug     = $zipResult[ResponseKeyType::Slug->value];
        $tempFile = $zipResult[ResponseKeyType::TempFile->value];
        $this->traceStage('processExtraction:start', ['slug' => $slug, 'tempFile' => $tempFile]);
        $targetDir = WP_PLUGIN_DIR . '/' . $slug;
        $isUpdate = is_dir($targetDir);

        // Log upload activity
        $this->fileLogger->info('Plugin upload processing started', [
            'slug' => $slug,
            'isUpdate' => $isUpdate,
            'activate' => $input['activate'],
        ]);

        // Create backup before replacing (rollback safety net)
        $backupHelper = new UploadBackupHelper($this->fileLogger);
        $backupDir = $isUpdate ? $backupHelper->createBackup($slug) : false;

        $isPreviouslyActive = $this->deactivateIfUpdating($slug, $isUpdate, $targetDir);
        $extractResult = $this->extractToPluginsDir($tempFile, $slug, $targetDir);

        if ($extractResult instanceof WP_REST_Response) {
            $this->logExternalPluginFailure($slug, 'extraction', 'ZIP extraction to plugins directory failed');

            return $this->rollbackOnFailure($backupHelper, $backupDir, $slug, $isPreviouslyActive, $extractResult);
        }

        $result = $this->resolvePluginAfterExtract($slug, $isUpdate, $input['activate'], $isPreviouslyActive);

        if ($result instanceof WP_REST_Response) {
            $this->logExternalPluginFailure($slug, 'activation', 'Plugin activation or post-extract validation failed');

            return $this->rollbackOnFailure($backupHelper, $backupDir, $slug, $isPreviouslyActive, $result);
        }

        // Success — clean up backup
        if ($backupDir !== false) {
            $backupHelper->cleanup($backupDir);
        }

        $this->fileLogger->info('Plugin upload completed successfully', [
            'slug' => $slug,
            'version' => $result[ResponseKeyType::PluginVersion->value] ?? '',
            'isUpdate' => $isUpdate,
            'activated' => $result[ResponseKeyType::Activated->value] ?? false,
        ]);

        return $result;
    }

    /**
     * Log an upload failure caused by an external (third-party) plugin.
     *
     * This writes to both the error log and stack trace with an explicit disclaimer
     * that the failure originates from the uploaded plugin's own code, not from QUpload.
     */
    private function logExternalPluginFailure(string $slug, string $phase, string $detail): void
    {
        $message = sprintf(
            'EXTERNAL PLUGIN FAILURE [%s] — The uploaded plugin "%s" failed during the %s phase. '
            . 'This error originates from the third-party plugin code, not from QUpload. '
            . 'QUpload has no control over external plugin code quality or compatibility. '
            . 'Detail: %s',
            strtoupper($phase),
            $slug,
            $phase,
            $detail,
        );

        $this->fileLogger->error($message, [
            'slug' => $slug,
            'phase' => $phase,
            'source' => 'external-plugin',
        ]);
    }

    /** Roll back to backup on upload failure and return the original error response. */
    private function rollbackOnFailure(
        UploadBackupHelper $backupHelper,
        string|false $backupDir,
        string $slug,
        bool $wasPreviouslyActive,
        WP_REST_Response $errorResponse,
    ): WP_REST_Response {
        if ($backupDir === false) {
            $this->traceStage('rollbackOnFailure:no-backup', ['slug' => $slug]);
            $this->fileLogger->warn('Upload failed with no backup available — cannot rollback', ['slug' => $slug]);

            return $errorResponse;
        }

        $this->traceStage('rollbackOnFailure:start', ['slug' => $slug, 'backupDir' => $backupDir]);
        $this->fileLogger->warn('Upload failed — initiating rollback to previous version', ['slug' => $slug]);
        $isRolledBack = $backupHelper->rollback($backupDir, $slug);

        if ($isRolledBack && $wasPreviouslyActive) {
            $backupHelper->reactivateAfterRollback($slug);
            $this->fileLogger->info('Rollback complete — previous version restored and re-activated', ['slug' => $slug]);
        } elseif ($isRolledBack) {
            $this->fileLogger->info('Rollback complete — previous version restored (was not active)', ['slug' => $slug]);
        } else {
            $this->fileLogger->error('Rollback FAILED — plugin may be in a broken state', ['slug' => $slug]);
        }

        $this->traceStage('rollbackOnFailure:complete', ['slug' => $slug, 'success' => $isRolledBack]);

        return $errorResponse;
    }

    /** Find plugin file, activate if needed, and build the result. */
    private function resolvePluginAfterExtract(string $slug, bool $isUpdate, bool $activate, bool $wasActive): array|WP_REST_Response
    {
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

    /** Build the final extraction result array. */
    private function buildExtractionResult(string $slug, bool $isUpdate, array $activation, string $pluginFile): array
    {
        return [
            ResponseKeyType::Slug->value          => $slug,
            ResponseKeyType::IsUpdate->value      => $isUpdate,
            ResponseKeyType::Activated->value     => $activation['activated'],
            ResponseKeyType::PluginVersion->value => $this->detectInstalledVersion($pluginFile),
        ];
    }
}
