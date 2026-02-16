<?php
/**
 * UploadPipelineTrait — Plugin upload orchestration and response building.
 *
 * Shell trait delegating parsing to UploadParserTrait.
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
use Throwable;
use RiseupAsia\Enums\EndpointType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\ErrorHandling\ErrorResponse;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait UploadPipelineTrait
{
    use UploadParserTrait;

    public function handleUpload(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Upload endpoint called');

        try {
            return $this->executeUploadPipeline($request);
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnEnvelope($this->fileLogger, $e, 'Upload failed: ' . $e->getMessage());
        }
    }

    private function executeUploadPipeline(WP_REST_Request $request): WP_REST_Response {
        $input = $this->parseUploadInput($request);
        if ($input instanceof WP_REST_Response) {
            return $input;
        }

        $this->logUploadInitiated($input);

        $zipResult = $this->validateAndWriteZip($input['zip_content'], $input['slug']);
        if ($zipResult instanceof WP_REST_Response) {
            return $zipResult;
        }

        $result = $this->processUploadExtraction($input, $zipResult);
        if ($result instanceof WP_REST_Response) {
            return $result;
        }

        return $this->buildUploadResponse($result, $input);
    }

    private function logUploadInitiated(array $input): void {
        if (empty($input['slug'])) {
            return;
        }

        $this->logger->logUploadInitiated($input['slug'], array(
            'activate'       => $input['activate'],
            'upload_source'  => $input['upload_source'],
            'client_version' => $input['client_plugin_version'],
            'file_size'      => strlen($input['zip_content']),
        ), array(
            'plugin_version' => $input['client_plugin_version'] ?: PluginConfigType::Version->value,
            'upload_source'  => $input['upload_source'],
        ));
    }

    private function buildUploadResponse(array $result, array $input): WP_REST_Response {
        $this->logUploadResult($result, $input);
        return $this->buildUploadEnvelope($result, $input);
    }

    private function logUploadResult(array $result, array $input): void {
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

    private function buildUploadEnvelope(array $result, array $input): WP_REST_Response {
        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Upload->route())
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