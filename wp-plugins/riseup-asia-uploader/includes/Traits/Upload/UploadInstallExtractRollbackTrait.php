<?php
/**
 * UploadInstallExtractRollbackTrait — Rollback and external failure logging.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   2.37.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Response;

use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\SelfUpdateStatusType;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Update\SelfUpdateBackupHelper;

trait UploadInstallExtractRollbackTrait
{
    /**
     * Log an upload failure caused by an external (third-party) plugin.
     */
    private function logExternalPluginFailure(string $slug, string $phase, string $detail): void
    {
        $message = sprintf(
            'EXTERNAL PLUGIN FAILURE [%s] — The uploaded plugin "%s" failed during the %s phase. '
            . 'This error originates from the third-party plugin code, not from Riseup Asia Uploader. '
            . 'Riseup Asia Uploader has no control over external plugin code quality or compatibility. '
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

    /**
     * Attempt rollback from backup if available, then return the original error response.
     */
    private function rollbackIfNeeded(?string $backupDir, WP_REST_Response $errorResponse, SelfUpdateStatusType $reason): WP_REST_Response
    {
        if ($backupDir === null) {
            return $errorResponse;
        }

        $this->fileLogger->warn('Triggering self-update rollback', [
            'reason'     => $reason->value,
            'reasonLabel' => $reason->label(),
        ]);

        $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
        $rollbackSuccess = $backupHelper->rollback($backupDir);

        if ($rollbackSuccess) {
            $this->fileLogger->info('Self-update rollback succeeded — previous version restored');

            // Try to re-activate the restored version
            $pluginFile = $this->findPluginFile(PluginConfigType::Slug->value);

            if ($pluginFile) {
                activate_plugin($pluginFile);
            }
        } else {
            $this->fileLogger->error('Self-update rollback failed — plugin may be in broken state');
        }

        return $errorResponse;
    }

    /**
     * Perform rollback and build a detailed error response with diagnostics.
     */
    private function performRollbackAndBuildResponse(
        ?string $backupDir,
        string $slug,
        SelfUpdateStatusType $reason,
        array $diagnostics,
    ): WP_REST_Response {
        $rolledBack = false;
        $restoredVersion = '';
        $outcome = SelfUpdateStatusType::RollbackFailed;

        if ($backupDir !== null) {
            $backupHelper = new SelfUpdateBackupHelper($this->fileLogger);
            $restoredVersion = $backupHelper->getBackupVersion($backupDir);
            $rolledBack = $backupHelper->rollback($backupDir);

            if ($rolledBack) {
                $outcome = SelfUpdateStatusType::RolledBack;

                $this->fileLogger->info('Self-update rollback succeeded', [
                    'restoredVersion' => $restoredVersion,
                ]);

                // Re-activate the restored version
                $pluginFile = $this->findPluginFile($slug);

                if ($pluginFile) {
                    activate_plugin($pluginFile);
                }
            }
        }

        $requestedAt = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Use the correct diagnostics key based on the failure phase
        $diagnosticsKey = $reason->isHealthCheckError() || $reason === SelfUpdateStatusType::HealthCheckFailed
            ? ResponseKeyType::HealthCheck->value
            : ResponseKeyType::Validation->value;

        return EnvelopeBuilder::error($reason->label(), HttpStatusType::InternalServerError->value)
            ->setRequestedAt($requestedAt)
            ->setSingleResult([
                ResponseKeyType::SelfUpdateStatus->value => $outcome->value,
                ResponseKeyType::RollbackReason->value   => $reason->value,
                'rollback' => [
                    ResponseKeyType::RollbackAttempted->value => ($backupDir !== null),
                    ResponseKeyType::RollbackSuccess->value   => $rolledBack,
                    ResponseKeyType::RestoredVersion->value   => $restoredVersion,
                ],
                $diagnosticsKey => $diagnostics,
            ])
            ->toResponse();
    }
}
