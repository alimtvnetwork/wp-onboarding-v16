<?php
/**
 * InvalidRouteTrait — Invalid route handling and error response enrichment.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HttpStatusType;

trait InvalidRouteTrait
{
    public function handleInvalidRoute(WP_REST_Request $request): WP_REST_Response {
        $invalidPath = $request->get_param('invalid_path');
        $method = $request->get_method();

        $this->fileLogger->warn('Invalid route requested', array('path' => $invalidPath, 'method' => $method));

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $trace = $this->buildInvalidRouteTrace($method, $invalidPath, $backtrace);

        return RiseupEnvelopeBuilder::error("No endpoint found for: {$method} /{$invalidPath}", HttpStatusType::NotFound->value)
            ->setRequestedAt($_SERVER['REQUEST_URI'] ?? '')
            ->setErrors($trace)
            ->toResponse();
    }

    private function buildInvalidRouteTrace(string $method, string $path, array $backtrace): array {
        $frames = class_exists('RiseupFrameBuilder') ? RiseupFrameBuilder::backtraceToFrames($backtrace) : array();

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

    public function enrichErrorResponse(WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request): WP_REST_Response {
        $route = $request->get_route();
        if (strpos($route, '/' . API_FULL_NAMESPACE) === false) {
            return $response;
        }

        $status = $response->get_status();
        if ($status < 400) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        $data = $this->injectErrorMetadata($data);
        $this->logRestApiError($route, $status, $data);
        $response->set_data($data);

        return $response;
    }

    private function injectErrorMetadata(array $data): array {
        if (!isset($data['plugin_version'])) {
            $data['plugin_version'] = PLUGIN_VERSION;
        }
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = gmdate('c');
        }
        if (!isset($data['log_hint'])) {
            $data['log_hint'] = 'Check the plugin error logs or the Activity Logs page for details.';
        }

        return $data;
    }

    private function logRestApiError(string $route, int $status, array $data): void {
        $this->fileLogger->error('REST API error response', array(
            'route'          => $route,
            'status'         => $status,
            'message'        => isset($data['message']) ? $data['message'] : (isset($data['Status']['Message']) ? $data['Status']['Message'] : 'Unknown'),
            'plugin_version' => PLUGIN_VERSION,
        ));
    }
}
