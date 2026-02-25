<?php
/**
 * PluginExportTrait — Export self and export any plugin as ZIP.
 *
 * @package RiseupAsia\Traits\Plugin
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use RiseupAsia\Upload\UploadIgnore;
use WP_REST_Response;
use Throwable;
use ZipArchive;
use RiseupAsia\Enums\ActionType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

trait PluginExportTrait
{
    public function handleExportSelf(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Export-self endpoint called');

        try {
            $pluginDir = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value;
            $ignore = UploadIgnore::fromDirectory($pluginDir);
            $zipContent = $this->createPluginZip($pluginDir, PluginConfigType::Slug->value, $ignore);

            if ($zipContent === null) {
                return $this->errorResponse('Failed to create or read ZIP file', HttpStatusType::ServerError->value);
            }

            $this->logger->logPluginAction(ActionType::ExportSelf->value, PluginConfigType::Slug->value, StatusType::Success->value, array(
                'size' => strlen($zipContent),
            ));

            return new WP_REST_Response(ResultHelper::ok(array(
                ResponseKeyType::PluginZip->value => base64_encode($zipContent),
                'slug'       => PluginConfigType::Slug->value,
                'version'    => PluginConfigType::Version->value,
            )), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Export-self error');

            return $this->errorResponse('Export failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    public function handleExportPlugin(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');

        if (empty($slug)) {
            return $this->errorResponse('Plugin slug is required in JSON body', HttpStatusType::BadRequest->value);
        }

        return $this->safeExecute(function () use ($slug) {
            return $this->exportPluginBySlug($slug);
        });
    }

    private function createPluginZip(
        string $pluginDir,
        string $slug,
        \RiseupUploadIgnore $ignore,
    ): ?string {
        $tempDir = $this->getTempDir();
        $zipFile = $tempDir . '/' . $slug . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $this->addDirToZip($zip, $pluginDir, $slug, $ignore);
        $zip->close();

        $zipContent = file_get_contents($zipFile);
        @unlink($zipFile);

        return ($zipContent !== false) ? $zipContent : null;
    }

    private function exportPluginBySlug(string $slug) {
        $pluginsDir = WP_PLUGIN_DIR;
        $pluginDir  = PathHelper::join($pluginsDir, $slug);

        if (PathHelper::isDirMissing($pluginDir)) {
            return $this->errorResponse('Plugin not found: ' . $slug, HttpStatusType::NotFound->value);
        }

        if (PathHelper::isPathMissing($pluginDir, $pluginsDir)) {
            return $this->errorResponse('Invalid plugin slug', HttpStatusType::BadRequest->value);
        }

        $ignore = UploadIgnore::fromDirectory($pluginDir);
        $zipContent = $this->createPluginZip($pluginDir, $slug . '-backup', $ignore);

        if ($zipContent === null) {
            return $this->errorResponse('Failed to create or read ZIP file', HttpStatusType::ServerError->value);
        }

        $this->logger->logPluginAction(ActionType::ExportPlugin->value, $slug, StatusType::Success->value, array(
            'size' => strlen($zipContent),
        ));

        return new WP_REST_Response(ResultHelper::ok(array(
            ResponseKeyType::PluginZip->value => base64_encode($zipContent),
            'slug'       => $slug,
            'size'       => strlen($zipContent),
        )), HttpStatusType::Ok->value);
    }
}
