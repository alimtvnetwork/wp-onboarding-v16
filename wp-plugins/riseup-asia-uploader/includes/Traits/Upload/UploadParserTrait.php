<?php
/**
 * UploadParserTrait — Upload input parsing for multipart and base64 requests.
 *
 * @package RiseupAsia\Traits\Upload
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\ResponseMessageType;
use RiseupAsia\Enums\UploadSourceType;

trait UploadParserTrait {

    /**
     * Parse upload input from multipart or base64 JSON request.
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_REST_Response Parsed input array, or error response.
     */
    private function parseUploadInput($request) {
        $files = $request->get_file_params();
        $isMultipart = !empty($files['plugin_zip']);

        if ($isMultipart) {
            return $this->parseMultipartInput($files, $request);
        }

        return $this->parseBase64Input($request);
    }

    /**
     * Parse multipart/form-data upload.
     *
     * @param array           $files   File params from request.
     * @param WP_REST_Request $request Request object.
     * @return array|WP_REST_Response Parsed input or error response.
     */
    private function parseMultipartInput($files, $request) {
        $this->fileLogger->info('Processing multipart upload');
        $upload = $files['plugin_zip'];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $this->fileLogger->error('Multipart upload error', array('code' => $upload['error']));
            return $this->errorResponse('File upload failed (error code: ' . $upload['error'] . ')', HttpStatusType::BadRequest->value);
        }

        $zipContent = file_get_contents($upload['tmp_name']);
        if ($zipContent === false) {
            $this->fileLogger->error('Failed to read uploaded file');
            return $this->errorResponse('Failed to read uploaded file', HttpStatusType::ServerError->value);
        }

        $bodyParams = $request->get_body_params();

        return $this->buildUploadParams($zipContent, $bodyParams);
    }

    /**
     * Parse base64 JSON upload (legacy).
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_REST_Response Parsed input or error response.
     */
    private function parseBase64Input($request) {
        $data = $request->get_json_params();

        if (empty($data['plugin_zip'])) {
            $this->fileLogger->warn('Upload failed: plugin_zip required');
            return $this->errorResponse(ResponseMessageType::InvalidRequest->value . ': plugin_zip is required (send as multipart file or base64 JSON)', HttpStatusType::BadRequest->value);
        }

        $this->fileLogger->info('Processing base64 JSON upload');
        $zipContent = base64_decode($data['plugin_zip']);
        if ($zipContent === false) {
            $this->fileLogger->error('Invalid base64 data');
            return $this->errorResponse('Invalid base64 data', HttpStatusType::BadRequest->value);
        }

        return $this->buildUploadParams($zipContent, $data);
    }

    /**
     * Build normalized upload parameters from raw data.
     *
     * @param string $zipContent Raw ZIP bytes.
     * @param array  $data       Form/JSON params.
     * @return array Normalized upload parameters.
     */
    private function buildUploadParams($zipContent, $data) {
        $slug     = sanitize_file_name($data['slug'] ?? '');
        $activate = !empty($data['activate']);
        $uploadSource = $this->resolveUploadSource($data);
        $clientPluginVersion = isset($data['plugin_version']) ? sanitize_text_field($data['plugin_version']) : '';

        $this->fileLogger->debug('Upload parameters', array(
            'slug' => $slug, 'activate' => $activate,
            'uploadSource' => $uploadSource, 'clientVersion' => $clientPluginVersion,
            'fileSize' => strlen($zipContent),
        ));

        return array(
            'zipContent' => $zipContent, 'slug' => $slug, 'activate' => $activate,
            'uploadSource' => $uploadSource, 'clientPluginVersion' => $clientPluginVersion,
        );
    }

    /**
     * Resolve and validate the upload source from request data.
     *
     * @param array $data Request data.
     * @return string Validated upload source.
     */
    private function resolveUploadSource(array $data): string {
        $source = isset($data['upload_source']) ? sanitize_text_field($data['upload_source']) : UploadSourceType::RestApi->value;
        $validSources = UploadSourceType::validValues();

        return in_array($source, $validSources, true) ? $source : UploadSourceType::RestApi->value;
    }
}
