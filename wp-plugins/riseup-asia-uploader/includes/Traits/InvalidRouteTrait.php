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

trait InvalidRouteTrait
{
    /**
     * Handle requests to invalid/unrecognized routes within our namespace.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Structured 404 error response.
     */
    public function handle_invalid_route($request) {
        $invalid_path = $request->get_param('invalid_path');
        $method = $request->get_method();

        $this->file_logger->warn('Invalid route requested', array('path' => $invalid_path, 'method' => $method));

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $trace = $this->buildInvalidRouteTrace($method, $invalid_path, $backtrace);

        return RiseupEnvelopeBuilder::error("No endpoint found for: {$method} /{$invalid_path}", HTTP_NOT_FOUND)
            ->setRequestedAt($_SERVER['REQUEST_URI'] ?? '')
            ->setErrors($trace)
            ->toResponse();
    }

    /**
     * Build structured trace data for invalid route diagnostics.
     *
     * @param string $method    HTTP method.
     * @param string $path      Requested path.
     * @param array  $backtrace Debug backtrace.
     * @return array Structured error trace.
     */
    private function buildInvalidRouteTrace(string $method, string $path, array $backtrace): array {
        $frames = function_exists('riseup_backtrace_to_frames') ? riseup_backtrace_to_frames($backtrace) : array();

        return array(
            'BackendMessage'             => "Route not found: {$method} /{$path}",
            'DelegatedServiceErrorStack' => $this->formatBacktraceLines($backtrace),
            'Backend'                    => $this->formatFramesSummary($frames),
            'Frontend'                   => array(),
        );
    }

    /**
     * Format backtrace into human-readable trace lines.
     *
     * @param array $backtrace Debug backtrace.
     * @return array Formatted trace lines.
     */
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

    /**
     * Format structured frames into summary strings.
     *
     * @param array $frames Structured frame objects.
     * @return array Summary strings.
     */
    private function formatFramesSummary(array $frames): array {
        return array_map(function($f) {
            $file = isset($f['fileBase']) ? $f['fileBase'] : '';
            $line = isset($f['line']) ? $f['line'] : 0;
            $fn   = isset($f['function']) ? $f['function'] : '';
            $cls  = isset($f['class']) ? $f['class'] . '::' : '';
            return "{$file}:{$line} {$cls}{$fn}";
        }, $frames);
    }

    /**
     * Enrich error responses from our namespace with plugin metadata.
     *
     * @param WP_REST_Response $response Response object.
     * @param WP_REST_Server   $server   REST server.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response Modified response.
     */
    public function enrich_error_response($response, $server, $request) {
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

    /**
     * Inject plugin metadata into error response data.
     *
     * @param array $data Response data.
     * @return array Modified data with metadata.
     */
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

    /**
     * Log a REST API error for audit trail.
     *
     * @param string $route  Request route.
     * @param int    $status HTTP status code.
     * @param array  $data   Response data.
     */
    private function logRestApiError(string $route, int $status, array $data) {
        $this->file_logger->error('REST API error response', array(
            'route'          => $route,
            'status'         => $status,
            'message'        => isset($data['message']) ? $data['message'] : (isset($data['Status']['Message']) ? $data['Status']['Message'] : 'Unknown'),
            'plugin_version' => PLUGIN_VERSION,
        ));
    }
}
