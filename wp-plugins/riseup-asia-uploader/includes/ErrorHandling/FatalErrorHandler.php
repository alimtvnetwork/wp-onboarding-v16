<?php
namespace RiseupAsia\ErrorHandling;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ContentTypeValueType;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PathLogFileType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\DateHelper;

/**
 * Detects fatal PHP errors during REST requests and emits structured JSON responses.
 *
 * @since 1.57.0
 */
class FatalErrorHandler
{
    private const FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
    ];
    private const WP_JSON_PATH = 'wp-json';
    private const ERROR_CODE_FATAL = 'FATAL_ERROR';
    private const ERROR_CODE_ENCODING_FAILED = 'FATAL_ERROR_ENCODING_FAILED';
    private const MESSAGE_TRUNCATE_LENGTH = 500;

    private const ERROR_TYPE_MAP = [
        E_ERROR             => 'E_ERROR',
        E_PARSE             => 'E_PARSE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_WARNING           => 'E_WARNING',
        E_NOTICE            => 'E_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
    ];

    public static function handle(): void {
        $error = error_get_last();

        $isNonFatalRestError = (self::isFatalRestError($error) === false);

        if ($isNonFatalRestError) {
            return;
        }

        self::logToFile($error);
        self::cleanOutputBuffers();
        self::emitJsonResponse($error);
    }

    public static function isFatalRestError(?array $error): bool {
        if ($error === null) {
            return false;
        }

        $isFatalType = in_array($error['type'], self::FATAL_TYPES, true);
        $isNonFatalType = ($isFatalType === false);

        if ($isNonFatalType) {
            return false;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $hasPluginSlug = str_contains($requestUri, PluginConfigType::Slug->value);
        $hasWpJsonPath = str_contains($requestUri, self::WP_JSON_PATH);
        $isPluginRequest =
            $hasPluginSlug ||
            $hasWpJsonPath;

        return $isPluginRequest;
    }

    public static function errorTypeToString(int $type): string {
        return self::ERROR_TYPE_MAP[$type] ?? 'UNKNOWN_ERROR_TYPE';
    }

    private static function buildResponse(
        array $error,
        array $traceLines,
        array $frames,
    ): array {
        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => array(
                'code'    => self::ERROR_CODE_FATAL,
                'message' => 'A fatal error occurred in the plugin: ' . $error['message'],
                'details' => FrameBuilder::buildFatalDetails($error, $traceLines, $frames),
            ),
        );
    }

    private static function logToFile(array $error): void {
        $logEntry = sprintf(
            "[%s] FATAL ERROR in %s:%d - %s (type: %s)\n",
            DateHelper::nowDatetime(),
            $error['file'],
            $error['line'],
            $error['message'],
            self::errorTypeToString($error['type']),
        );
        $uploads = wp_upload_dir();
        $logFile = $uploads['basedir']
            . '/' . PluginConfigType::Slug->value
            . PathLogFileType::FatalError->value;

        @file_put_contents(
            $logFile,
            $logEntry,
            FILE_APPEND | LOCK_EX,
        );
    }

    private static function cleanOutputBuffers(): void {
        while (ob_get_level()) {
            @ob_end_clean();
        }
    }

    private static function emitJsonResponse(array $error): void {
        $isHeadersUnsent = (headers_sent() === false);

        if ($isHeadersUnsent) {
            header('Content-Type: ' . ContentTypeValueType::JsonUtf8->value);
            http_response_code(HttpStatusType::ServerError->value);
        }

        $frameData = FrameBuilder::buildFatalFrames($error);
        $response = self::buildResponse($error, $frameData['trace_lines'], $frameData['frames']);

        $json = @json_encode($response, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            echo json_encode(self::buildFallback($error));
        } else {
            echo $json;
        }

        exit;
    }

    private static function buildFallback(array $error): array {
        return array(
            ResponseKeyType::Success->value => false,
            ResponseKeyType::Error->value   => array(
                'code'    => self::ERROR_CODE_ENCODING_FAILED,
                'message' => 'Fatal error occurred and JSON encoding also failed',
                'details' => array(
                    'originalMessage' => substr($error['message'], 0, self::MESSAGE_TRUNCATE_LENGTH),
                    'file'            => basename($error['file']),
                    'line'            => $error['line'],
                    'jsonError'       => json_last_error_msg(),
                ),
            ),
        );
    }
}

register_shutdown_function([FatalErrorHandler::class, 'handle']);
