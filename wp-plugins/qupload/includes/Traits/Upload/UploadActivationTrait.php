<?php
/**
 * UploadActivationTrait — plugin activation, syntax validation, and discovery.
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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use WP_REST_Response;

use QUpload\Enums\HttpStatusType;
use QUpload\Helpers\DateHelper;
use QUpload\Helpers\PathHelper;

trait UploadActivationTrait
{
    // ── Pre-Activation Syntax Validation ────────────────────────

    /** Validate extracted plugin PHP files before activation to prevent fatal crashes. */
    private function validateExtractedPluginBeforeActivation(string $slug): ?WP_REST_Response
    {
        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;
        $isDirMissing = !is_dir($pluginDir);

        if ($isDirMissing) {
            $this->fileLogger->error('Plugin directory missing before activation validation', ['slug' => $slug, 'dir' => $pluginDir]);

            return $this->errorResponse('Plugin directory missing before activation', HttpStatusType::ServerError->value);
        }

        return $this->runSyntaxCheckLoop($pluginDir, $slug);
    }

    /** Run syntax checks on PHP files up to the configured limit. */
    private function runSyntaxCheckLoop(string $pluginDir, string $slug): ?WP_REST_Response
    {
        $phpFiles = $this->collectPhpFiles($pluginDir);
        $checkedCount = 0;

        foreach ($phpFiles as $filePath) {
            if ($checkedCount >= self::MAX_SYNTAX_CHECK_FILES) {
                $this->fileLogger->warn('Syntax check limit reached', ['slug' => $slug, 'limit' => self::MAX_SYNTAX_CHECK_FILES]);
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
    private function checkPhpFileSyntax(string $filePath, string $pluginDir, string $slug): ?WP_REST_Response
    {
        $content = @file_get_contents($filePath);
        $relativePath = str_replace($pluginDir . '/', '', $filePath);

        if ($content === false) {
            $this->fileLogger->error('Cannot read extracted PHP file', ['slug' => $slug, 'file' => $relativePath]);

            return $this->errorResponse('Cannot read plugin file before activation: ' . $relativePath, HttpStatusType::ServerError->value);
        }

        $isTemplate = $this->isTemplatePhpFile($content);

        if ($isTemplate) {
            return null;
        }

        return $this->validatePhpTokenSyntax($content, $relativePath, $slug);
    }

    /** Check whether a PHP file is a mixed HTML/PHP template. */
    private function isTemplatePhpFile(string $content): bool
    {
        $trimmedContent = ltrim($content);
        $isPhpOnly = str_starts_with($trimmedContent, '<?php');
        $hasClosingTag = str_contains($content, '?>');

        return $isPhpOnly && $hasClosingTag;
    }

    /** Run token_get_all with TOKEN_PARSE and catch syntax errors. */
    private function validatePhpTokenSyntax(string $content, string $relativePath, string $slug): ?WP_REST_Response
    {
        try {
            @token_get_all($content, TOKEN_PARSE);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Plugin syntax validation failed for ' . $slug . ' file: ' . $relativePath);

            return $this->errorResponse(
                'Plugin uploaded but activation was blocked due to PHP syntax error in ' . $relativePath . ': ' . $e->getMessage(),
                HttpStatusType::ServerError->value,
                $e,
            );
        }

        return null;
    }

    /** Collect all PHP files from a plugin directory. */
    private function collectPhpFiles(string $pluginDir): array
    {
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

    // ── Deactivation ────────────────────────────────────────────

    /** Deactivate and remove old directory if updating. */
    private function deactivateIfUpdating(string $slug, bool $isUpdate, string $targetDir): bool
    {
        $logMessage = $isUpdate ? 'Updating existing plugin' : 'Installing new plugin';
        $this->fileLogger->info($logMessage, ['slug' => $slug]);

        if ($isUpdate === false) {
            return false;
        }

        $isPreviouslyActive = $this->deactivateExistingPlugin($slug);
        $this->deleteDirectory($targetDir);

        return $isPreviouslyActive;
    }

    /** Deactivate an existing plugin and return whether it was active. */
    private function deactivateExistingPlugin(string $slug): bool
    {
        $pluginFile = $this->findPluginFile($slug);

        if (empty($pluginFile)) {
            return false;
        }

        $isActive = is_plugin_active($pluginFile);

        if ($isActive) {
            deactivate_plugins($pluginFile);
        }

        return $isActive;
    }

    // ── Activation ──────────────────────────────────────────────

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
    private function tryActivatePlugin(string $pluginFile, string $slug): ?WP_REST_Response
    {
        $this->traceStage('tryActivatePlugin:start', ['slug' => $slug, 'pluginFile' => $pluginFile]);
        $this->fileLogger->info('Attempting plugin activation', ['slug' => $slug, 'file' => $pluginFile]);

        $fatalLogged = false;
        $logger = $this->fileLogger;
        $this->registerActivationShutdownHandler($slug, $pluginFile, $logger, $fatalLogged);

        return $this->executePluginActivation($pluginFile, $slug, $fatalLogged);
    }

    /** Register a shutdown handler to capture fatal errors during activation. */
    private function registerActivationShutdownHandler(string $slug, string $pluginFile, object $logger, bool &$fatalLogged): void
    {
        register_shutdown_function(function () use ($slug, $pluginFile, $logger, &$fatalLogged) {
            $error = error_get_last();
            $isFatal = $error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);

            if ($isFatal && $fatalLogged === false) {
                $fatalLogged = true;
                self::logFatalActivationError($slug, $pluginFile, $error, $logger);
            }
        });
    }

    /** Log fatal error details during plugin activation. */
    private static function logFatalActivationError(string $slug, string $pluginFile, array $error, object $logger): void
    {
        $context = [
            'slug'       => $slug,
            'pluginFile' => $pluginFile,
            'message'    => $error['message'],
            'file'       => $error['file'],
            'line'       => $error['line'],
        ];

        $traceLine = sprintf("[%s] %s %s%s", DateHelper::nowLogDisplay(), 'tryActivatePlugin:fatal', json_encode($context, JSON_UNESCAPED_SLASHES), PHP_EOL);
        @file_put_contents(PathHelper::getStageTraceFile(), $traceLine, FILE_APPEND | LOCK_EX);
        @error_log('[QUpload Stage] tryActivatePlugin:fatal ' . json_encode($context, JSON_UNESCAPED_SLASHES));

        $message = sprintf('FATAL during activation of "%s" (%s): %s in %s on line %d', $slug, $pluginFile, $error['message'], $error['file'], $error['line']);
        $logger->error($message);
    }

    /** Execute activate_plugin with try/catch and handle the result. */
    private function executePluginActivation(string $pluginFile, string $slug, bool &$fatalLogged): ?WP_REST_Response
    {
        try {
            $result = activate_plugin($pluginFile);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Plugin activation threw an exception for slug: ' . $slug);
            return $this->errorResponse('Plugin uploaded but activation failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }

        $fatalLogged = true;

        return $this->handleActivationResult($result, $slug);
    }

    /** Handle the result of activate_plugin. */
    private function handleActivationResult(mixed $result, string $slug): ?WP_REST_Response
    {
        if (is_wp_error($result)) {
            $this->traceStage('tryActivatePlugin:wp-error', ['slug' => $slug, 'message' => $result->get_error_message()]);
            return $this->buildActivationWpError($slug, $result);
        }

        $this->traceStage('tryActivatePlugin:success', ['slug' => $slug]);
        $this->fileLogger->info('Plugin activated successfully', ['slug' => $slug]);
        return null;
    }

    /** Build error response from WP_Error activation result. */
    private function buildActivationWpError(string $slug, object $result): WP_REST_Response
    {
        $this->fileLogger->error('Plugin activation returned WP_Error', [
            'slug'     => $slug,
            'errorMsg' => $result->get_error_message(),
        ]);

        return $this->errorResponse(
            'Plugin uploaded but activation failed: ' . $result->get_error_message(),
            HttpStatusType::ServerError->value,
        );
    }

    // ── Plugin Discovery ────────────────────────────────────────

    /** Reset OPcache and locate the plugin's main file. */
    private function resetOpcacheAndFindPlugin(string $slug): string|WP_REST_Response
    {
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
    private function findPluginMainFileByHeader(string $slug): ?string
    {
        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;
        $isDirMissing = !is_dir($pluginDir);

        if ($isDirMissing) {
            return null;
        }

        $phpFiles = $this->collectPhpFiles($pluginDir);

        foreach ($phpFiles as $filePath) {
            $match = $this->matchPluginHeaderInFile($filePath, $pluginDir, $slug);

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /** Check a single file for a WordPress plugin header and return relative path. */
    private function matchPluginHeaderInFile(string $filePath, string $pluginDir, string $slug): ?string
    {
        $contents = @file_get_contents($filePath);

        if ($contents === false) {
            $this->fileLogger->warn('Could not read PHP file while locating plugin header', ['slug' => $slug, 'file' => $filePath]);

            return null;
        }

        $hasHeader = $this->hasWordPressPluginHeader($contents);

        if ($hasHeader === false) {
            return null;
        }

        $relativePath = str_replace($pluginDir . '/', '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);

        return $slug . '/' . ltrim($relativePath, '/');
    }

    /** Find plugin main file by slug from the WordPress registry. */
    private function findPluginFile(string $slug): ?string
    {
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

        return $this->findPluginFileFallback($slug);
    }

    /** Fallback: check for {slug}/{slug}.php directly on disk. */
    private function findPluginFileFallback(string $slug): ?string
    {
        $candidate = $slug . '/' . $slug . '.php';
        $isFileExists = file_exists(WP_PLUGIN_DIR . '/' . $candidate);

        return $isFileExists ? $candidate : null;
    }

    /** Detect the installed plugin version from disk. */
    private function detectInstalledVersion(string $pluginFile): string
    {
        $fullPath = WP_PLUGIN_DIR . '/' . $pluginFile;
        clearstatcache(true, $fullPath);

        if (PathHelper::isFileMissing($fullPath)) {
            return '';
        }

        $pluginData = get_plugin_data($fullPath, false, false);

        return $pluginData['Version'] ?? '';
    }
}
