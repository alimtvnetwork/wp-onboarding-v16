<?php
/**
 * UploadInstallActivateTrait — OPcache reset, activation, and version detection.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   2.0.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use WP_REST_Response;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\EnvelopeBuilder;


trait UploadInstallActivateTrait
{
    /** Reset OPcache and locate the plugin's main file. */
    private function resetOpcacheAndFindPlugin(string $slug): string|WP_REST_Response {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $pluginFile = $this->findPluginFile($slug);
        $hasPluginFile = !empty($pluginFile);

        if ($hasPluginFile) {
            $this->invalidatePluginCache($pluginFile, $slug);
        }

        $isPluginFileMissing = ($pluginFile === null || $pluginFile === '' || $pluginFile === false);

        if ($isPluginFileMissing) {
            $this->logger->logUploadFailed($slug, 'Could not find plugin file after extraction');

            return $this->errorResponse('Could not find plugin file after extraction', HttpStatusType::ServerError->value);
        }

        return $pluginFile;
    }

    /** Invalidate OPcache entries and WP plugin cache for the given plugin. */
    private function invalidatePluginCache(string $pluginFile, string $slug): void {
        $fullPluginPath = WP_PLUGIN_DIR . '/' . $pluginFile;

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fullPluginPath, true);
        }

        $constantsFile = WP_PLUGIN_DIR . '/' . $slug . '/includes/constants.php';
        $shouldInvalidateConstants = file_exists($constantsFile) && function_exists('opcache_invalidate');

        if ($shouldInvalidateConstants) {
            opcache_invalidate($constantsFile, true);
        }

        wp_cache_delete('plugins', 'plugins');
    }

    /**
     * Activate the plugin if requested or if it was previously active.
     *
     * Phase 3: Wraps activate_plugin() in try-catch for safe activation.
     * Captures fatal errors, WP_Error, and uncaught exceptions with full
     * stack trace so the caller gets actionable diagnostics.
     */
    private function activateIfNeeded(
        string $pluginFile,
        string $slug,
        bool $activate,
        bool $wasActive,
        bool $isUpdate,
    ): array|WP_REST_Response {
        $isActivationSkipped = ($activate === false) && ($wasActive === false);

        if ($isActivationSkipped) {
            return array('activated' => false);
        }

        try {
            $result = activate_plugin($pluginFile);
        } catch (Throwable $e) {
            $this->fileLogger->error('Plugin activation threw an exception', array(
                'slug'       => $slug,
                'pluginFile' => $pluginFile,
                'exception'  => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace'      => $this->buildSafeStackTrace($e),
            ));

            return $this->buildActivationFailureResponse(
                $slug,
                $isUpdate,
                'Activation exception: ' . $e->getMessage(),
                $e,
            );
        }

        if (is_wp_error($result)) {
            $this->fileLogger->error('Plugin activation returned WP_Error', array(
                'slug'      => $slug,
                'errorCode' => $result->get_error_code(),
                'errorMsg'  => $result->get_error_message(),
            ));

            return $this->buildActivationFailureResponse(
                $slug,
                $isUpdate,
                $result->get_error_message(),
            );
        }

        return array('activated' => true);
    }

    /**
     * Build response for failed activation after upload.
     *
     * Includes stack trace and root-cause diagnostics when a Throwable is provided.
     */
    private function buildActivationFailureResponse(
        string $slug,
        bool $isUpdate,
        string $errorMsg,
        ?Throwable $exception = null,
    ): WP_REST_Response {
        $this->logger->logUploadFailed($slug, ResponseMessageType::ActivationFailed->value . ': ' . $errorMsg);

        $resultPayload = array(
            'pluginSlug'      => $slug,
            'isUpdate'        => $isUpdate,
            'activated'       => false,
            'activationError' => $errorMsg,
        );

        if ($exception !== null) {
            $resultPayload['rootCause'] = array(
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => $this->buildSafeStackTrace($exception),
            );
        }

        return EnvelopeBuilder::success('Plugin uploaded but activation failed', HttpStatusType::Ok->value)
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . '/' . EndpointType::Upload->value)
            ->setSingleResult($resultPayload)
            ->toResponse();
    }

    /**
     * Build a safe, serializable stack trace from a Throwable (max 15 frames).
     *
     * @return array<int, array{file: string, line: int, function: string}>
     */
    private function buildSafeStackTrace(Throwable $e): array
    {
        $trace = array();
        $frames = $e->getTrace();
        $maxFrames = min(count($frames), 15);

        for ($i = 0; $i < $maxFrames; $i++) {
            $frame = $frames[$i];

            $trace[] = array(
                'file'     => $frame['file'] ?? '(internal)',
                'line'     => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            );
        }

        return $trace;
    }

    /** Detect the installed plugin version from disk. */
    private function detectInstalledVersion(
        string $pluginFile,
        string $slug,
        bool $isSelfUpdate,
        string $clientVersion,
    ): array {
        $installedVersion = $this->readVersionFromFile($pluginFile);

        if (empty($installedVersion)) {
            $pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
            $installedVersion = $pluginData['Version'] ?? '';
        }

        $version = $this->resolveEffectiveVersion($installedVersion, $clientVersion, $isSelfUpdate);

        return array(ResponseKeyType::Version->value => $version);
    }

    /** Read the version header from a plugin's main PHP file. */
    private function readVersionFromFile(string $pluginFile): string {
        $fullPath = WP_PLUGIN_DIR . '/' . $pluginFile;
        clearstatcache(true, $fullPath);

        if (PathHelper::isFileMissing($fullPath)) {
            return '';
        }

        $fileContents = file_get_contents($fullPath, false, null, 0, 8192);

        if ($fileContents !== false && preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $fileContents, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /** Resolve the effective version based on self-update status and available sources. */
    private function resolveEffectiveVersion(
        string $installed,
        string $client,
        bool $isSelfUpdate,
    ): string {
        if ($isSelfUpdate) {
            return $client ?: ($installed ?: PluginConfigType::Version->value);
        }

        return $installed ?: ($client ?: PluginConfigType::Version->value);
    }
}
