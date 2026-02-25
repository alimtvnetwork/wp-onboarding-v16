<?php
/**
 * UploadZipTrait — ZIP validation, duplicate removal, and pre-logging.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use WP_REST_Response;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\BooleanHelpers;

trait UploadZipTrait
{
    /** Write ZIP content to temp file and validate its structure. */
    private function validateAndWriteZip($zipContent, $slug) {
        $tempFile = $this->writeZipToTemp($zipContent, $slug);

        if ($tempFile instanceof WP_REST_Response) {
            return $tempFile;
        }

        $detectedSlug = $this->validateZipStructure($tempFile, $slug);

        if ($detectedSlug instanceof WP_REST_Response) {
            return $detectedSlug;
        }

        $hasSlug = BooleanHelpers::hasValue($slug);
        $finalSlug = $hasSlug ? $slug : $detectedSlug;
        $this->fileLogger->info('Plugin slug determined', array('slug' => $finalSlug));

        return array(ResponseKeyType::TempFile->value => $tempFile, ResponseKeyType::Slug->value => $finalSlug);
    }

    /** Write ZIP content to a temp file. */
    private function writeZipToTemp(string $zipContent, string $slug) {
        $tempDir  = $this->getTempDir();
        $tempFile = $tempDir . '/' . ($slug ?: 'plugin_' . time()) . '.zip';

        $this->fileLogger->debug('Writing temp file', array('path' => $tempFile));
        if (file_put_contents($tempFile, $zipContent) === false) {
            $this->fileLogger->error('Failed to write temp file');
            $this->logger->logUploadFailed($slug, 'Failed to write temp file');

            return $this->errorResponse(ResponseMessageType::UploadFailed->value, HttpStatusType::ServerError->value);
        }

        return $tempFile;
    }

    /** Validate ZIP archive and detect plugin slug. */
    private function validateZipStructure(string $tempFile, string $slug) {
        $this->fileLogger->debug('Validating ZIP archive');
        $zip = new ZipArchive();

        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            $this->fileLogger->error('Invalid ZIP archive');
            $this->logger->logUploadFailed($slug, 'Invalid ZIP archive');

            return $this->errorResponse('Invalid ZIP archive', HttpStatusType::BadRequest->value);
        }

        $detectedSlug = $this->detectPluginSlugFromZip($zip);
        $zip->close();

        $isSlugMissing = ($detectedSlug === null);

        if ($isSlugMissing) {
            @unlink($tempFile);
            $this->fileLogger->error('Could not detect plugin in ZIP');
            $this->logger->logUploadFailed($slug, 'Could not detect plugin in ZIP');

            return $this->errorResponse('Could not detect plugin in ZIP', HttpStatusType::BadRequest->value);
        }

        return $detectedSlug;
    }

    /** Remove duplicate plugin folders that share the same slug or TextDomain. */
    private function removeDuplicatePlugins($slug, $pluginsDir) {
        if (BooleanHelpers::isFuncMissing('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $duplicatesRemoved = 0;

        foreach ($allPlugins as $pfile => $pdata) {
            $removed = $this->removeSingleDuplicate($pfile, $pdata, $slug, $pluginsDir);
            $duplicatesRemoved += $removed ? 1 : 0;
        }

        if ($duplicatesRemoved > 0) {
            wp_cache_delete('plugins', 'plugins');
        }

        return $duplicatesRemoved;
    }

    /** Check and remove a single duplicate plugin. Returns true if removed. */
    private function removeSingleDuplicate(
        string $pfile,
        array $pdata,
        string $slug,
        string $pluginsDir,
    ): bool {
        $pdir = dirname($pfile);
        if ($pdir === '.' || $pdir === $slug) {
            return false;
        }

        $isUniquePlugin = ($this->isDuplicatePlugin($pdata, $pfile, $slug) === false);

        if ($isUniquePlugin) {
            return false;
        }

        $dupDir = $pluginsDir . '/' . $pdir;
        $this->fileLogger->warn('Duplicate plugin folder detected', array('duplicateDir' => $pdir, 'targetSlug' => $slug));

        if (is_plugin_active($pfile)) {
            deactivate_plugins($pfile);
        }

        if (is_dir($dupDir)) {
            $this->deleteDirectory($dupDir);

            return true;
        }

        return false;
    }

    /** Check if a plugin entry is a duplicate of the target slug. */
    private function isDuplicatePlugin(
        array $pdata,
        string $pfile,
        string $slug,
    ): bool {
        $hasMatchingTextDomain = (isset($pdata['TextDomain']) && $pdata['TextDomain'] === $slug);
        $hasMatchingSlugInPath = (isset($pdata['Name']) && strpos(strtolower($pfile), $slug) !== false);

        return $hasMatchingTextDomain || $hasMatchingSlugInPath;
    }

    /** Pre-log self-update activity before files are replaced. */
    private function preLogSelfUpdate(
        $slug,
        $uploadSource,
        $clientVersion,
        $fileSize,
    ) {
        $oldVersion = PluginConfigType::Version->value;
        $this->fileLogger->info('Self-update detected, pre-logging activity', array('oldVersion' => $oldVersion));

        $this->logger->logPluginAction(
            ActionType::Upload->value, $slug, StatusType::Success->value,
            array(
                ResponseKeyType::IsUpdate->value => true, ResponseKeyType::IsSelfUpdate->value => true,
                'oldVersion' => $oldVersion, 'newVersion' => $clientVersion,
                'fileSize' => $fileSize, 'note' => 'Pre-logged before self-update to ensure audit trail',
            ),
            null,
            array(ResponseKeyType::PluginVersion->value => $clientVersion ?: $oldVersion, 'uploadSource' => $uploadSource)
        );
    }
}
