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
            $temp_dir   = $this->get_temp_dir();
            $zip_file   = $temp_dir . '/' . PLUGIN_SLUG . '.zip';

            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return $this->error_response('Failed to create ZIP file', HTTP_SERVER_ERROR);
            }

            $ignore = RiseupUploadIgnore::fromDirectory($plugin_dir);
            $this->add_dir_to_zip($zip, $plugin_dir, PLUGIN_SLUG, $ignore);
            $zip->close();

            $zip_content = file_get_contents($zip_file);
            @unlink($zip_file);

            if ($zip_content === false) {
                return $this->error_response('Failed to read ZIP file', HTTP_SERVER_ERROR);
            }

            $this->logger->log_plugin_action(ACTION_EXPORT_SELF, PLUGIN_SLUG, STATUS_SUCCESS, array(
                'size' => strlen($zip_content),
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => PLUGIN_SLUG,
                'version'    => PLUGIN_VERSION,
            ), HTTP_OK);
        } catch (Throwable $e) {
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
            $plugins_dir = WP_PLUGIN_DIR;
            $plugin_dir  = RiseupPathUtils::join($plugins_dir, $slug);

            if (!RiseupPathUtils::dirExists($plugin_dir)) {
                return $this->error_response('Plugin not found: ' . $slug, HTTP_NOT_FOUND);
            }

            if (!RiseupPathUtils::isSafePath($plugin_dir, $plugins_dir)) {
                return $this->error_response('Invalid plugin slug', HTTP_BAD_REQUEST);
            }

            $temp_dir = $this->get_temp_dir();
            $zip_file = RiseupPathUtils::join($temp_dir, $slug . '-backup.zip');

            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return $this->error_response('Failed to create ZIP file', HTTP_SERVER_ERROR);
            }

            $ignore = RiseupUploadIgnore::fromDirectory($plugin_dir);
            $this->add_dir_to_zip($zip, $plugin_dir, $slug, $ignore);
            $file_count = $zip->numFiles;
            $zip->close();

            $zip_content = file_get_contents($zip_file);
            @unlink($zip_file);

            if ($zip_content === false) {
                return $this->error_response('Failed to read ZIP file', HTTP_SERVER_ERROR);
            }

            $this->logger->log_plugin_action(ACTION_EXPORT_PLUGIN, $slug, STATUS_SUCCESS, array(
                'size'  => strlen($zip_content),
                'files' => $file_count,
            ));

            return new WP_REST_Response(array(
                'success'    => true,
                'plugin_zip' => base64_encode($zip_content),
                'slug'       => $slug,
                'file_count' => $file_count,
                'size'       => strlen($zip_content),
            ), HTTP_OK);
        });
    }
}
