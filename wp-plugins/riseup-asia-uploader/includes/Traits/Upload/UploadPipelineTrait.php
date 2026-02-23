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
use RiseupAsia\Enums\ResponseKeyType;
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

    /** Handle upload-active endpoint — upload and force-activate in one call. */
    public function handleUploadActive(WP_REST_Request $request): WP_REST_Response {
        $this->fileLogger->info('Upload-active endpoint called');
        $request->set_param('activate', true);

        try {
            return $this->executeUploadPipeline($request);
        } catch (Throwable $e) {
            return ErrorResponse::logAndReturnEnvelope($this->fileLogger, $e, 'Upload-active failed: ' . $e->getMessage());
        }
    }

    private function executeUploadPipeline(WP_REST_Request $request): WP_REST_Response {
        $input = $this->parseUploadInput($request);
        if ($input instanceof WP_REST_Response) {
            return $input;
        }

        $this->logUploadInitiated($input);

        $zipResult = $this->validateAndWriteZip($input['zipContent'], $input['slug']);
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
            'uploadSource'   => $input['uploadSource'],
            'clientVersion'  => $input['clientPluginVersion'],
            'fileSize'       => strlen($input['zipContent']),
        ), array(
            'plugin_version' => $input['clientPluginVersion'] ?: PluginConfigType::Version->value,
            'upload_source'  => $input['uploadSource'],
        ));
    }

    private function buildUploadResponse(array $result, array $input): WP_REST_Response {
        $this->logUploadResult($result, $input);

        return $this->buildUploadEnvelope($result, $input);
    }

    private function logUploadResult(array $result, array $input): void {
        $isExternalUpload = ($result[ResponseKeyType::IsSelfUpdate->value] === false);
        if ($isExternalUpload) {
            $this->logger->logUpload($result[ResponseKeyType::Slug->value], array(
                ResponseKeyType::IsUpdate->value => $result[ResponseKeyType::IsUpdate->value],
                ResponseKeyType::Activated->value => $result[ResponseKeyType::Activated->value],
                'fileSize' => strlen($input['zipContent']),
                ResponseKeyType::PluginVersion->value => $result[ResponseKeyType::PluginVersion->value],
            ), array(
                ResponseKeyType::PluginVersion->value => $result[ResponseKeyType::PluginVersion->value],
                'upload_source' => $input['uploadSource'],
            ));
        }

        $this->fileLogger->info('Upload complete', array(
            ResponseKeyType::Slug->value => $result[ResponseKeyType::Slug->value],
            ResponseKeyType::IsUpdate->value => $result[ResponseKeyType::IsUpdate->value],
            ResponseKeyType::Activated->value => $result[ResponseKeyType::Activated->value],
            ResponseKeyType::PluginVersion->value => $result[ResponseKeyType::PluginVersion->value],
            'uploadSource' => $input['uploadSource'],
        ));
    }

    private function buildUploadEnvelope(array $result, array $input): WP_REST_Response {
        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Upload->route())
            ->setSingleResult(array(
                ResponseKeyType::PluginSlug->value => $result[ResponseKeyType::Slug->value],
                ResponseKeyType::IsUpdate->value => $result[ResponseKeyType::IsUpdate->value],
                ResponseKeyType::Activated->value => $result[ResponseKeyType::Activated->value],
                ResponseKeyType::PluginVersion->value => $result[ResponseKeyType::PluginVersion->value],
                'uploadSource' => $input['uploadSource'],
            ))
            ->toResponse();
    }
}