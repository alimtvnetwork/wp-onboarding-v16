<?php
/**
 * UploadHandlerTrait — Upload endpoint handler for QUpload.
 *
 * Accepts a plugin ZIP, extracts, replaces existing version, and activates.
 *
 * @package QUpload\Traits\Upload
 * @since   1.0.0
 */

namespace QUpload\Traits\Upload;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use Throwable;

use QUpload\Enums\EndpointType;
use QUpload\Enums\HttpStatusType;
use QUpload\Enums\PluginConfigType;
use QUpload\Enums\RequestFieldType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Helpers\EnvelopeBuilder;
use QUpload\Helpers\PathHelper;

trait UploadHandlerTrait
{
    use UploadExtractTrait;

    /** Handle POST /upload endpoint. */
    public function handleUpload(WP_REST_Request $request): WP_REST_Response {
        $baseDir = PathHelper::getBaseDir();
        PathHelper::ensureDirectory($baseDir);
        @file_put_contents(
            PathHelper::getStageTraceFile(),
            '[ENTRY] handleUpload reached ' . gmdate('c') . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
        @error_log('[QUpload Stage] handleUpload reached');

        $this->fileLogger->info('Upload endpoint called');
        $this->primeHttpStatusEnum();

        return $this->safeExecute(
            fn () => $this->executeUploadPipeline($request),
            'handleUpload',
            ['endpoint' => 'upload'],
        );
    }

    private function executeUploadPipeline(WP_REST_Request $request): WP_REST_Response {
        $this->traceStage('executeUploadPipeline:start');
        $input = $this->parseUploadInput($request);

        if ($input instanceof WP_REST_Response) {
            return $input;
        }

        $this->fileLogger->info('Upload initiated', [
            RequestFieldType::Slug->value => $input[RequestFieldType::Slug->value],
            RequestFieldType::Activate->value => $input[RequestFieldType::Activate->value],
            'fileSize' => strlen($input['zipContent']),
        ]);

        $zipResult = $this->validateAndWriteZip($input['zipContent'], $input[RequestFieldType::Slug->value]);

        if ($zipResult instanceof WP_REST_Response) {
            return $zipResult;
        }

        $result = $this->processExtraction($input, $zipResult);

        if ($result instanceof WP_REST_Response) {
            return $result;
        }

        return $this->buildUploadResponse($result);
    }

    private function parseUploadInput(WP_REST_Request $request): array|WP_REST_Response {
        $this->traceStage('parseUploadInput:start');
        $files = $request->get_file_params();
        $isMultipart = !empty($files[RequestFieldType::PluginZip->value]);

        if ($isMultipart) {
            return $this->parseMultipartUpload($files, $request);
        }

        return $this->parseBase64Upload($request);
    }

    private function parseMultipartUpload(array $files, WP_REST_Request $request): array|WP_REST_Response {
        $this->traceStage('parseMultipartUpload:start');
        $this->fileLogger->info('Processing multipart upload');
        $upload = $files[RequestFieldType::PluginZip->value];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $this->fileLogger->error('Upload error', ['code' => $upload['error']]);

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

    private function parseBase64Upload(WP_REST_Request $request): array|WP_REST_Response {
        $this->traceStage('parseBase64Upload:start');
        $data = $request->get_json_params();

        if (empty($data[RequestFieldType::PluginZip->value])) {
            $this->fileLogger->warn('Missing plugin_zip in request');

            return $this->errorResponse(RequestFieldType::PluginZip->value . ' is required (multipart file or base64 JSON)', HttpStatusType::BadRequest->value);
        }

        $this->fileLogger->info('Processing base64 JSON upload');
        $zipContent = base64_decode($data[RequestFieldType::PluginZip->value]);

        if ($zipContent === false) {
            $this->fileLogger->error('Invalid base64 data');

            return $this->errorResponse('Invalid base64 data', HttpStatusType::BadRequest->value);
        }

        return $this->buildUploadParams($zipContent, $data);
    }

    private function buildUploadParams(string $zipContent, array $data): array {
        $slug = sanitize_file_name($data[RequestFieldType::Slug->value] ?? '');
        $activate = !isset($data[RequestFieldType::Activate->value]) || !empty($data[RequestFieldType::Activate->value]);

        return ['zipContent' => $zipContent, RequestFieldType::Slug->value => $slug, RequestFieldType::Activate->value => $activate];
    }

    private function primeHttpStatusEnum(): void {
        if (enum_exists(HttpStatusType::class, false)) {
            return;
        }

        $enumPath = dirname(__DIR__, 2) . '/Enums/HttpStatusType.php';

        if (!is_file($enumPath)) {
            $this->fileLogger->error('HttpStatusType enum file missing', ['path' => $enumPath]);

            return;
        }

        try {
            require_once $enumPath;
        } catch (Throwable $e) {
            $this->fileLogger->logException($e, 'Failed to preload HttpStatusType enum');

            throw $e;
        }
    }


    private function buildUploadResponse(array $result): WP_REST_Response {
        $this->fileLogger->info('Upload complete', [
            'slug' => $result[ResponseKeyType::Slug->value],
            'isUpdate' => $result[ResponseKeyType::IsUpdate->value],
            'activated' => $result[ResponseKeyType::Activated->value],
        ]);

        return EnvelopeBuilder::success()
            ->setRequestedAt('/' . PluginConfigType::apiFullNamespace() . EndpointType::Upload->route())
            ->setSingleResult([
                ResponseKeyType::PluginSlug->value    => $result[ResponseKeyType::Slug->value],
                ResponseKeyType::IsUpdate->value      => $result[ResponseKeyType::IsUpdate->value],
                ResponseKeyType::Activated->value     => $result[ResponseKeyType::Activated->value],
                ResponseKeyType::PluginVersion->value => $result[ResponseKeyType::PluginVersion->value],
            ])
            ->toResponse();
    }
}

