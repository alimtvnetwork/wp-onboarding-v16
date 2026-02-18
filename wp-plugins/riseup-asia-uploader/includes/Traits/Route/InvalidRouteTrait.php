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
use RiseupAsia\ErrorHandling\FrameBuilder;
use RiseupAsia\Helpers\EnvelopeBuilder;
use RiseupAsia\Helpers\BooleanHelpers;

trait InvalidRouteTrait
{
    public function handleInvalidRoute(WP_REST_Request $request): WP_REST_Response {
        $invalidPath = $request->get_param('invalid_path');
        $method = $request->get_method();

        $this->fileLogger->warn('Invalid route requested', array('path' => $invalidPath, 'method' => $method));

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
            $file  = isset($frame['file']) ? basename($frame['file']) : '[internal]';
            $line  = isset($frame['line']) ? $frame['line'] : '?';
            $func  = isset($frame['function']) ? $frame['function'] : '';
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $lines[] = "#{$i} {$file}({$line}): {$class}{$func}()";
        }
        return $lines;
    }

    private function formatFramesSummary(array $frames): array {
        return array_map(function($f) {
            $file = isset($f['fileBase']) ? $f['fileBase'] : '';
            $line = isset($f['line']) ? $f['line'] : 0;
            $fn   = isset($f['function']) ? $f['function'] : '';
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
        if (strpos($route, '/' . PluginConfigType::apiFullNamespace()) === false) {
            return $response;
        }

        $status = $response->get_status();
        if ($status < 400) {
            return $response;
        }

        $data = $response->get_data();
        $isDataInvalid = (is_array($data) === false);
        if ($isDataInvalid) {
            return $response;
        }

        $data = $this->injectErrorMetadata($data);
        $this->logRestApiError($route, $status, $data);
        $response->set_data($data);

        return $response;
    }

    private function injectErrorMetadata(array $data): array {
        if (BooleanHelpers::isKeyMissing($data, 'plugin_version')) {
            $data['plugin_version'] = PluginConfigType::Version->value;
        }
        if (BooleanHelpers::isKeyMissing($data, 'timestamp')) {
            $data['timestamp'] = gmdate('c');
        }
        if (BooleanHelpers::isKeyMissing($data, 'log_hint')) {
            $data['log_hint'] = 'Check the plugin error logs or the Activity Logs page for details.';
        }

        return $data;
    }

    private function logRestApiError(
        string $route,
        int $status,
        array $data,
    ): void {
        $this->fileLogger->error('REST API error response', array(
            'route'          => $route,
            'status'         => $status,
            'message'        => isset($data['message']) ? $data['message'] : (isset($data['Status']['Message']) ? $data['Status']['Message'] : 'Unknown'),
            'plugin_version' => PluginConfigType::Version->value,
        ));
    }
}
