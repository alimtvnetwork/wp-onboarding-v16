<?php
/**
 * UploadParserTrait — Upload input parsing for multipart and base64 requests.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait UploadParserTrait {

    /**
     * Parse upload input from multipart or base64 JSON request.
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_REST_Response Parsed input array, or error response.
     */
    private function parse_upload_input($request) {
        $files = $request->get_file_params();
        $is_multipart = !empty($files['plugin_zip']);

        if ($is_multipart) {
            return $this->parse_multipart_input($files, $request);
        }

        return $this->parse_base64_input($request);
    }

    /**
     * Parse multipart/form-data upload.
     *
     * @param array           $files   File params from request.
     * @param WP_REST_Request $request Request object.
     * @return array|WP_REST_Response Parsed input or error response.
     */
    private function parse_multipart_input($files, $request) {
        $this->file_logger->info('Processing multipart upload');
        $upload = $files['plugin_zip'];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $this->file_logger->error('Multipart upload error', array('code' => $upload['error']));
            return $this->error_response('File upload failed (error code: ' . $upload['error'] . ')', HTTP_BAD_REQUEST);
        }

        $zip_content = file_get_contents($upload['tmp_name']);
        if ($zip_content === false) {
            $this->file_logger->error('Failed to read uploaded file');
            return $this->error_response('Failed to read uploaded file', HTTP_SERVER_ERROR);
        }

        $body_params = $request->get_body_params();
        return $this->build_upload_params($zip_content, $body_params);
    }

    /**
     * Parse base64 JSON upload (legacy).
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_REST_Response Parsed input or error response.
     */
    private function parse_base64_input($request) {
        $data = $request->get_json_params();

        if (empty($data['plugin_zip'])) {
            $this->file_logger->warn('Upload failed: plugin_zip required');
            return $this->error_response(MSG_INVALID_REQUEST . ': plugin_zip is required (send as multipart file or base64 JSON)', HTTP_BAD_REQUEST);
        }

        $this->file_logger->info('Processing base64 JSON upload');
        $zip_content = base64_decode($data['plugin_zip']);
        if ($zip_content === false) {
            $this->file_logger->error('Invalid base64 data');
            return $this->error_response('Invalid base64 data', HTTP_BAD_REQUEST);
        }

        return $this->build_upload_params($zip_content, $data);
    }

    /**
     * Build normalized upload parameters from raw data.
     *
     * @param string $zip_content Raw ZIP bytes.
     * @param array  $data        Form/JSON params.
     * @return array Normalized upload parameters.
     */
    private function build_upload_params($zip_content, $data) {
        $slug     = sanitize_file_name($data['slug'] ?? '');
        $activate = !empty($data['activate']);
        $upload_source = $this->resolveUploadSource($data);
        $client_plugin_version = isset($data['plugin_version']) ? sanitize_text_field($data['plugin_version']) : '';

        $this->file_logger->debug('Upload parameters', array(
            'slug' => $slug, 'activate' => $activate,
            'upload_source' => $upload_source, 'client_version' => $client_plugin_version,
            'file_size' => strlen($zip_content),
        ));

        return array(
            'zip_content' => $zip_content, 'slug' => $slug, 'activate' => $activate,
            'upload_source' => $upload_source, 'client_plugin_version' => $client_plugin_version,
        );
    }

    /**
     * Resolve and validate the upload source from request data.
     *
     * @param array $data Request data.
     * @return string Validated upload source.
     */
    private function resolveUploadSource(array $data): string {
        $source = isset($data['upload_source']) ? sanitize_text_field($data['upload_source']) : UPLOAD_SOURCE_REST_API;
        $valid_sources = json_decode(UPLOAD_SOURCES_VALID, true);
        return in_array($source, $valid_sources, true) ? $source : UPLOAD_SOURCE_REST_API;
    }
}
