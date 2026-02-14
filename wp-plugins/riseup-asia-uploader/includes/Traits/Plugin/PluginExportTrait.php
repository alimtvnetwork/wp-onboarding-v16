<?php
/**
 * PluginExportTrait — Export self and export any plugin as ZIP.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ActionType;

trait PluginExportTrait
{
    /**
     * Handle export-self (export this plugin as ZIP).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_export_self($request) {
        $this->file_logger->info('Export-self endpoint called');

        try {
            $plugin_dir = WP_PLUGIN_DIR . '/' . PLUGIN_SLUG;
            $ignore = RiseupUploadIgnore::fromDirectory($plugin_dir);
            $zip_content = $this->createPluginZip($plugin_dir, PLUGIN_SLUG, $ignore);

            if ($zip_content === null) {
                return $this->error_response('Failed to create or read ZIP file', HTTP_SERVER_ERROR);
            }

            $this->logger->log_plugin_action(ActionType::ExportSelf->value, PLUGIN_SLUG, STATUS_SUCCESS, array(
                'size' => strlen($zip_content),
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => PLUGIN_SLUG,
                'version'    => PLUGIN_VERSION,
            ), HTTP_OK);
        } catch (\Throwable $e) {
            $this->file_logger->log_exception($e, 'Export-self error');
            return $this->error_response('Export failed: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /**
     * Export any installed plugin as a base64-encoded ZIP.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_export_plugin($request) {
        $body = $request->get_json_params();
        $slug = isset($body['plugin']) ? sanitize_text_field($body['plugin']) : $request->get_param('slug');
        if (empty($slug)) {
            return $this->error_response('Plugin slug is required in JSON body', HTTP_BAD_REQUEST);
        }

        return $this->safe_execute(function () use ($slug) {
            return $this->exportPluginBySlug($slug);
        });
    }

    /**
     * Create a ZIP archive of a plugin directory and return its binary content.
     *
     * @param string             $plugin_dir Plugin directory path.
     * @param string             $slug       Plugin slug.
     * @param RiseupUploadIgnore $ignore     Ignore patterns.
     * @return string|null ZIP content or null on failure.
     */
    private function createPluginZip(string $plugin_dir, string $slug, $ignore) {
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
        $plugin_dir  = RiseupPathUtils::join($plugins_dir, $slug);

        if (!RiseupPathUtils::dirExists($plugin_dir)) {
            return $this->error_response('Plugin not found: ' . $slug, HTTP_NOT_FOUND);
        }

        if (!RiseupPathUtils::isSafePath($plugin_dir, $plugins_dir)) {
            return $this->error_response('Invalid plugin slug', HTTP_BAD_REQUEST);
        }

        $ignore = RiseupUploadIgnore::fromDirectory($plugin_dir);
        $zip_content = $this->createPluginZip($plugin_dir, $slug . '-backup', $ignore);

        if ($zip_content === null) {
            return $this->error_response('Failed to create or read ZIP file', HTTP_SERVER_ERROR);
        }

        $this->logger->log_plugin_action(ActionType::ExportPlugin->value, $slug, STATUS_SUCCESS, array(
            'size' => strlen($zip_content),
        ));

        return new WP_REST_Response(array(
            'success'    => true,
            'plugin_zip' => base64_encode($zip_content),
            'slug'       => $slug,
            'size'       => strlen($zip_content),
        ), HTTP_OK);
    }
}
