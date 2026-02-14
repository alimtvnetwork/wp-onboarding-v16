<?php
/**
 * UploadPipelineTrait — Plugin upload orchestration and response building.
 *
 * Shell trait delegating parsing to UploadParserTrait.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/UploadParserTrait.php';

trait UploadPipelineTrait
{
    use UploadParserTrait;

    /** Handle plugin upload (multipart or base64 ZIP). */
    public function handleUpload($request) {
        $this->fileLogger->info('Upload endpoint called');

        try {
            return $this->executeUploadPipeline($request);
        } catch (Throwable $e) {
            $this->fileLogger->log_exception($e, 'Upload error');
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), HTTP_SERVER_ERROR, $e);
        }
    }

    /** Execute the full upload pipeline: parse, validate, extract, activate, respond. */
    private function executeUploadPipeline($request) {
        $input = $this->parseUploadInput($request);
        if ($input instanceof WP_REST_Response) {
            return $input;
        }

        $this->logUploadInitiated($input);

        $zip_result = $this->validateAndWriteZip($input['zip_content'], $input['slug']);
        if ($zip_result instanceof WP_REST_Response) {
            return $zip_result;
        }

        $result = $this->processUploadExtraction($input, $zip_result);
        if ($result instanceof WP_REST_Response) {
            return $result;
        }

        return $this->buildUploadResponse($result, $input);
    }

    /** Log upload initiated event if slug is known. */
    private function logUploadInitiated(array $input) {
        if (empty($input['slug'])) {
            return;
        }

        $this->logger->logUploadInitiated($input['slug'], array(
            'activate'       => $input['activate'],
            'upload_source'  => $input['upload_source'],
            'client_version' => $input['client_plugin_version'],
            'file_size'      => strlen($input['zip_content']),
        ), array(
            'plugin_version' => $input['client_plugin_version'] ?: PLUGIN_VERSION,
            'upload_source'  => $input['upload_source'],
        ));
    }

    /** Build the final upload success response and log the result. */
    private function buildUploadResponse(array $result, array $input) {
        $this->logUploadResult($result, $input);
        return $this->buildUploadEnvelope($result, $input);
    }

    /** Log the upload result to the activity logger. */
    private function logUploadResult(array $result, array $input) {
        if (!$result['is_self_update']) {
            $this->logger->logUpload($result['slug'], array(
                'is_update' => $result['is_update'], 'activated' => $result['activated'],
                'file_size' => strlen($input['zip_content']), 'plugin_version' => $result['plugin_version'],
            ), array(
                'plugin_version' => $result['plugin_version'], 'upload_source' => $input['upload_source'],
            ));
        }

        $this->fileLogger->info('Upload complete', array(
            'slug' => $result['slug'], 'is_update' => $result['is_update'],
            'activated' => $result['activated'], 'plugin_version' => $result['plugin_version'],
            'upload_source' => $input['upload_source'],
        ));
    }

    /** Build the upload envelope response. */
    private function buildUploadEnvelope(array $result, array $input): WP_REST_Response {
        return RiseupEnvelopeBuilder::success()
            ->setRequestedAt('/' . API_FULL_NAMESPACE . '/' . ENDPOINT_UPLOAD)
            ->setSingleResult(array(
                'plugin_slug'    => $result['slug'],
                'is_update'      => $result['is_update'],
                'activated'      => $result['activated'],
                'plugin_version' => $result['plugin_version'],
                'upload_source'  => $input['upload_source'],
            ))
            ->toResponse();
    }
}
