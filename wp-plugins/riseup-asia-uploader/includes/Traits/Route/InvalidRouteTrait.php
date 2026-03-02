<?php
/**
 * InvalidRouteTrait — Invalid route handling and error response enrichment.
 *
 * @package RiseupAsia\Traits\Route
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Route;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PluginConfigType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Enums\WpErrorCodeType;
use RiseupAsia\ErrorHandling\FrameBuilder;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;

trait InvalidRouteTrait
{
    public function handleInvalidRoute(WP_REST_Request $request): WP_REST_Response {
        $invalidPath = $request->get_param('invalid_path');
        $method = $request->get_method();

        $this->fileLogger->warn('Invalid route requested', array(ResponseKeyType::Path->value => $invalidPath, 'method' => $method));

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $trace = $this->buildInvalidRouteTrace($method, $invalidPath, $backtrace);

        return EnvelopeBuilder::error("No endpoint found for: {$method} /{$invalidPath}", HttpStatusType::NotFound->value)
            ->setRequestedAt($_SERVER['REQUEST_URI'] ?? '')
            ->setErrors($trace)
            ->toResponse();
    }

    private function buildInvalidRouteTrace(
        string $method,
        string $path,
        array $backtrace,
    ): array {
        $frames = class_exists(FrameBuilder::class) ? FrameBuilder::backtraceToFrames($backtrace) : array();

        return array(
            'BackendMessage'             => "Route not found: {$method} /{$path}",
            'DelegatedServiceErrorStack' => $this->formatBacktraceLines($backtrace),
            'Backend'                    => $this->formatFramesSummary($frames),
            'Frontend'                   => array(),
        );
    }

    private function formatBacktraceLines(array $backtrace): array {
        $lines = array();
        foreach ($backtrace as $i => $frame) {
            $file  = $frame['file'] ?? '[internal]';
            $line  = $frame['line'] ?? '?';
            $func  = $frame['function'] ?? '';
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $lines[] = "#{$i} " . basename($file) . "({$line}): {$class}{$func}()";
        }

        return $lines;
    }

    private function formatFramesSummary(array $frames): array {

        return array_map(function($f) {
            $file = $f['fileBase'] ?? '';
            $line = $f['line'] ?? 0;
            $fn   = $f['function'] ?? '';
            $cls  = isset($f['class']) ? $f['class'] . '::' : '';

            return "{$file}:{$line} {$cls}{$fn}";
        }, $frames);
    }

    public function enrichErrorResponse(
        WP_REST_Response $response,
        WP_REST_Server $server,
        WP_REST_Request $request,
    ): WP_REST_Response {
        $route = $request->get_route();
        $isOtherNamespace = (strpos($route, '/' . PluginConfigType::apiFullNamespace()) === false);

        if ($isOtherNamespace) {

            return $response;
        }

        $status = $response->get_status();
        $isSuccessStatus = ($status < 400);

        if ($isSuccessStatus) {

            return $response;
        }

        $data = $response->get_data();
        $isDataInvalid = (is_array($data) === false);

        if ($isDataInvalid) {

            return $response;
        }

        $data = $this->injectErrorMetadata($data);
        $errorCode = $this->resolveErrorCode($data);
        $data = $this->injectErrorCategory($data, $errorCode);
        $this->logRestApiError($route, $status, $data, $errorCode);
        $response->set_data($data);

        return $response;
    }

    private function resolveErrorCode(array $data): ?WpErrorCodeType {
        $code = $data[ResponseKeyType::Code->value] ?? null;
        if ($code === null) {

            return null;
        }

        return WpErrorCodeType::tryFrom($code);
    }

    private function injectErrorCategory(array $data, ?WpErrorCodeType $errorCode): array {
        if ($errorCode === null) {

            return $data;
        }

        $data[ResponseKeyType::ErrorCategory->value] = $this->classifyErrorCode($errorCode);

        return $data;
    }

    private function classifyErrorCode(WpErrorCodeType $errorCode): string {
        if ($errorCode->isAuthError()) {

            return 'authentication';
        }
        if ($errorCode->isDatabaseError()) {

            return 'database';
        }
        if ($errorCode->isValidationError()) {

            return 'validation';
        }
        if ($errorCode->isNetworkError()) {

            return 'network';
        }

        return 'general';
    }

    private function injectErrorMetadata(array $data): array {
        if (BooleanHelpers::isKeyMissing($data, ResponseKeyType::PluginVersion->value)) {
            $data[ResponseKeyType::PluginVersion->value] = PluginConfigType::Version->value;
        }
        if (BooleanHelpers::isKeyMissing($data, ResponseKeyType::Timestamp->value)) {
            $data[ResponseKeyType::Timestamp->value] = DateHelper::nowIso();
        }
        if (BooleanHelpers::isKeyMissing($data, ResponseKeyType::LogHint->value)) {
            $data[ResponseKeyType::LogHint->value] = 'Check the plugin error logs or the Activity Logs page for details.';
        }

        return $data;
    }

    private function logRestApiError(
        string $route,
        int $status,
        array $data,
        ?WpErrorCodeType $errorCode,
    ): void {
        $context = array(
            'route'          => $route,
            'status'         => $status,
            ResponseKeyType::ErrorCategory->value => $data[ResponseKeyType::ErrorCategory->value] ?? 'unknown',
            ResponseKeyType::Message->value => $data[ResponseKeyType::Message->value] ?? $data['Status']['Message'] ?? 'Unknown',
            ResponseKeyType::PluginVersion->value => PluginConfigType::Version->value,
        );

        $isWarnLevel = ($errorCode !== null && ($errorCode->isAuthError() || $errorCode->isValidationError()));

        if ($isWarnLevel) {
            $this->fileLogger->warn('REST API error response', $context);

            return;
        }

        $this->fileLogger->error('REST API error response', $context);
    }
}
