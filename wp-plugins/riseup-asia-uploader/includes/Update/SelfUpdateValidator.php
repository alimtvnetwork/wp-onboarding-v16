<?php
/**
 * SelfUpdateValidator — Pre-activation validation for self-updates.
 *
 * Performs syntax checks (token_get_all) and verifies critical files exist
 * in the newly extracted plugin directory before activation is attempted.
 *
 * @package RiseupAsia\Update
 * @since   2.4.0
 */

namespace RiseupAsia\Update;

if (!defined('ABSPATH')) {
    exit;
}

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SelfUpdateStatusType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Logging\FileLogger;
use Throwable;

class SelfUpdateValidator
{
    /** Critical files that must exist for the plugin to boot. */
    private const CRITICAL_FILES = array(
        'riseup-asia-uploader.php',
        'includes/Autoloader.php',
        'includes/Core/Plugin.php',
        'includes/Enums/PluginConfigType.php',
        'includes/Enums/HookType.php',
        'includes/Enums/ResponseKeyType.php',
        'includes/Logging/FileLogger.php',
        'includes/Logging/Logger.php',
        'includes/ErrorHandling/BootErrorCollector.php',
        'includes/ErrorHandling/FatalErrorHandler.php',
        'includes/Database/Database.php',
        'includes/Post/PostManager.php',
        'includes/Update/UpdateResolver.php',
        'includes/Helpers/PathHelper.php',
        'includes/Helpers/InitHelpers.php',
        'includes/Helpers/EnvelopeBuilder.php',
    );

    /** Maximum number of PHP files to syntax-check (safety limit). */
    private const MAX_SYNTAX_CHECK_FILES = 500;

    private FileLogger $fileLogger;

    /** @var array<int, array{code: string, message: string}> Collected validation errors. */
    private array $errors = array();

    public function __construct(FileLogger $fileLogger)
    {
        $this->fileLogger = $fileLogger;
    }

    /**
     * Run all validation checks on the extracted plugin directory.
     *
     * @param string $pluginDir Absolute path to the extracted plugin directory.
     *
     * @return bool True if all checks pass, false if any fail.
     */
    public function validate(string $pluginDir): bool
    {
        $this->errors = array();

        $this->fileLogger->info('Starting self-update validation', array('dir' => $pluginDir));

        if (PathHelper::isDirMissing($pluginDir)) {
            $this->addError(SelfUpdateStatusType::DirectoryMissing, 'Plugin directory does not exist: ' . $pluginDir);
            $this->fileLogger->error('Self-update validation failed: directory missing', array('dir' => $pluginDir));

            return false;
        }

        $this->validateCriticalFiles($pluginDir);
        $this->validatePhpSyntax($pluginDir);

        $hasErrors = !empty($this->errors);

        if ($hasErrors) {
            $this->fileLogger->error('Self-update validation failed', array(
                'errorCount' => count($this->errors),
                'errors'     => $this->errors,
            ));

            return false;
        }

        $this->fileLogger->info('Self-update validation passed');

        return true;
    }

    /**
     * Get the list of validation errors from the last run.
     *
     * @return array<int, array{code: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get a structured diagnostics array for REST API responses.
     *
     * @return array{Passed: bool, ErrorCount: int, Errors: array<int, array{code: string, message: string}>}
     */
    public function getDiagnostics(): array
    {
        return array(
            ResponseKeyType::Passed->value     => empty($this->errors),
            ResponseKeyType::ErrorCount->value => count($this->errors),
            ResponseKeyType::Errors->value     => $this->errors,
        );
    }

    /**
     * Verify that all critical files exist in the plugin directory.
     */
    private function validateCriticalFiles(string $pluginDir): void
    {
        foreach (self::CRITICAL_FILES as $relativeFile) {
            $fullPath = $pluginDir . '/' . $relativeFile;

            if (!file_exists($fullPath)) {
                $this->addError(SelfUpdateStatusType::CriticalFileMissing, 'Critical file missing: ' . $relativeFile);
                $this->fileLogger->error('Critical file missing after extraction', array(
                    'file' => $relativeFile,
                    'path' => $fullPath,
                ));
            }
        }
    }

    /**
     * Syntax-check all PHP files using token_get_all().
     *
     * This catches parse errors without executing the code.
     */
    private function validatePhpSyntax(string $pluginDir): void
    {
        $phpFiles = $this->collectPhpFiles($pluginDir);
        $checkedCount = 0;

        foreach ($phpFiles as $filePath) {
            if ($checkedCount >= self::MAX_SYNTAX_CHECK_FILES) {
                $this->fileLogger->info('Syntax check limit reached', array(
                    'limit'   => self::MAX_SYNTAX_CHECK_FILES,
                    'checked' => $checkedCount,
                ));

                break;
            }

            $this->checkFileSyntax($filePath, $pluginDir);
            $checkedCount++;
        }

        $this->fileLogger->info('Syntax check completed', array('filesChecked' => $checkedCount));
    }

    /**
     * Check a single PHP file for syntax errors using token_get_all().
     */
    private function checkFileSyntax(string $filePath, string $pluginDir): void
    {
        $content = @file_get_contents($filePath);

        if ($content === false) {
            $relativePath = str_replace($pluginDir . '/', '', $filePath);
            $this->addError(SelfUpdateStatusType::FileUnreadable, 'Cannot read file: ' . $relativePath);

            return;
        }

        try {
            @token_get_all($content, TOKEN_PARSE);
        } catch (Throwable $e) {
            $relativePath = str_replace($pluginDir . '/', '', $filePath);
            $this->addError(SelfUpdateStatusType::SyntaxError, 'Syntax error in ' . $relativePath . ': ' . $e->getMessage());
            $this->fileLogger->error('Syntax error detected in new version', array(
                'file'    => $relativePath,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ));
        }
    }

    /**
     * Record a typed validation error.
     */
    private function addError(SelfUpdateStatusType $code, string $message): void
    {
        $this->errors[] = array(
            'code'    => $code->value,
            'message' => $message,
        );
    }

    /**
     * Collect all .php file paths from the plugin directory.
     *
     * @return array<string>
     */
    private function collectPhpFiles(string $pluginDir): array
    {
        $files = array();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            $isPhpFile = $file->isFile() && strtolower($file->getExtension()) === 'php';

            if ($isPhpFile) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
