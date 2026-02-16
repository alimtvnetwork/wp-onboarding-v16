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
use RiseupAsia\Enums\StatusType;
use RiseupAsia\Helpers\PathHelper;

trait PluginExportTrait
{
    /** Handle export-self (export this plugin as ZIP). */
    public function handleExportSelf(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Export-self endpoint called');

        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . PluginConfigType::Slug->value;
            $ignore = UploadIgnore::fromDirectory($plugin_dir);
            $zip_content = $this->createPluginZip($plugin_dir, PluginConfigType::Slug->value, $ignore);

            if ($zip_content === null) {
                return $this->errorResponse('Failed to create or read ZIP file', HttpStatusType::ServerError->value);
            }

            $this->logger->logPluginAction(ActionType::ExportSelf->value, PluginConfigType::Slug->value, StatusType::Success->value, array(
                'size' => strlen($zip_content),
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => PluginConfigType::Slug->value,
                'version'    => PluginConfigType::Version->value,
            ), HttpStatusType::Ok->value);
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Export-self error');
            return $this->errorResponse('Export failed: ' . $e->getMessage(), HttpStatusType::ServerError->value, $e);
        }
    }

    /** Export any installed plugin as a base64-encoded ZIP. */
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

    /** Create a ZIP archive of a plugin directory and return its binary content. */
    private function createPluginZip(string $plugin_dir, string $slug, \RiseupUploadIgnore $ignore): ?string {
        $temp_dir = $this->get_temp_dir();
        $zip_file = $temp_dir . '/' . $slug . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $this->add_dir_to_zip($zip, $plugin_dir, $slug, $ignore);
        $zip->close();

        $zip_content = file_get_contents($zip_file);
        @unlink($zip_file);

        return ($zip_content !== false) ? $zip_content : null;
    }

    /**
     * Export a plugin by slug: validate, zip, and return response.
     *
     * @param string $slug Plugin slug.
     * @return WP_REST_Response
     */
    private function exportPluginBySlug(string $slug) {
        $plugins_dir = WP_PLUGIN_DIR;
        $plugin_dir  = PathHelper::join($plugins_dir, $slug);

        if (PathHelper::isDirMissing($plugin_dir)) {
            return $this->errorResponse('Plugin not found: ' . $slug, HttpStatusType::NotFound->value);
        }

        if (PathHelper::isPathMissing($plugin_dir, $plugins_dir)) {
            return $this->errorResponse('Invalid plugin slug', HttpStatusType::BadRequest->value);
        }

        $ignore = UploadIgnore::fromDirectory($plugin_dir);
        $zip_content = $this->createPluginZip($plugin_dir, $slug . '-backup', $ignore);

        if ($zip_content === null) {
            return $this->errorResponse('Failed to create or read ZIP file', HttpStatusType::ServerError->value);
        }

        $this->logger->logPluginAction(ActionType::ExportPlugin->value, $slug, StatusType::Success->value, array(
            'size' => strlen($zip_content),
        ));

        return new WP_REST_Response(array(
            'success'    => true,
            'plugin_zip' => base64_encode($zip_content),
            'slug'       => $slug,
            'size'       => strlen($zip_content),
        ), HttpStatusType::Ok->value);
    }
}