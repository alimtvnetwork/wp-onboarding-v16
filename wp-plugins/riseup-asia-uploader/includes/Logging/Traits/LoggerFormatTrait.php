<?php
/**
 * Logger Format Trait
 *
 * Entry formatting, backtrace formatting, context enrichment.
 *
 * @package RiseupAsiaUploader
 * @since   1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait LoggerFormatTrait {

    /**
     * Format a log entry.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     * @param string $file    Source file.
     * @param int    $line    Source line number.
     * @param array  $context Additional context.
     * @return string Formatted log entry.
     */
    private function format_entry($level, $message, $file, $line, $context = array()) {
        $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
        $basename  = basename($file);

        $entry = sprintf("[%s] [%s] %s (%s:%d)", $timestamp, $level, $message, $basename, $line);

        if (!empty($context)) {
            $json_flags = defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0;
            $entry .= ' ' . json_encode($context, $json_flags);
        }

        return $entry . PHP_EOL;
    }

    /**
     * Format a debug_backtrace array into a readable string.
     *
     * @param array $trace debug_backtrace result.
     * @return string Formatted stack trace.
     */
    private function format_backtrace($trace) {
        $lines = array();
        foreach ($trace as $i => $frame) {
            $file = isset($frame['file']) ? basename($frame['file']) : '<internal>';
            $line = isset($frame['line']) ? $frame['line'] : 0;
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $func = isset($frame['function']) ? $frame['function'] : '<unknown>';
            $lines[] = sprintf('#%d %s(%d): %s%s()', $i, $file, $line, $class, $func);
        }
        return implode(PHP_EOL, $lines);
    }

    /**
     * Gather HTTP request metadata (method, endpoint, user-agent, IP).
     *
     * @return array Associative array with request metadata keys.
     */
    private function get_request_metadata() {
        if ($this->request_metadata_cache !== null) {
            return $this->request_metadata_cache;
        }

        $meta = array();

        if (php_sapi_name() === 'cli') {
            $meta['_request'] = array(
                'method' => 'CLI',
                'script' => isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : 'unknown',
            );
            $this->request_metadata_cache = $meta;
            return $meta;
        }

        $method    = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN';
        $uri       = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
        $query     = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
        $useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip        = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        $meta['_request'] = array(
            'method'    => $method,
            'endpoint'  => $uri . $query,
            'userAgent' => strlen($useragent) > 200 ? substr($useragent, 0, 200) . '…' : $useragent,
            'ip'        => $ip,
        );

        $this->request_metadata_cache = $meta;
        return $meta;
    }

    /**
     * Merge request metadata into a context array (non-destructive).
     *
     * @param array $context Existing context.
     * @return array Context enriched with request metadata.
     */
    private function enrich_context_with_request($context) {
        $meta = $this->get_request_metadata();
        if (!isset($context['_request'])) {
            $context = array_merge($meta, $context);
        }
        return $context;
    }

    /**
     * Prepare context for logging by enriching with request metadata and
     * optionally building an invocation chain from a backtrace.
     *
     * @param array      $context       Existing context array.
     * @param array|null $trace         Optional debug_backtrace result.
     * @param bool       $include_chain Whether to build _invocation_chain.
     * @return array Enriched context.
     */
    private function prepare_context($context, $trace = null, $include_chain = false) {
        $context = $this->enrich_context_with_request($context);

        if (!$include_chain || $trace === null || isset($context['_invocation_chain'])) {
            return $context;
        }

        $chain = array();
        foreach ($trace as $i => $frame) {
            if ($i === 0) {
                continue;
            }

            $entry = array();
            if (isset($frame['class'])) {
                $entry['class'] = $frame['class'];
            }
            if (isset($frame['function'])) {
                $entry['function'] = $frame['function'];
            }
            if (isset($frame['file'])) {
                $entry['file'] = basename($frame['file']);
                $entry['line'] = isset($frame['line']) ? $frame['line'] : 0;
            }

            if (!empty($entry)) {
                $chain[] = $entry;
            }
        }

        if (!empty($chain)) {
            $context['_invocation_chain'] = $chain;
        }

        return $context;
    }
}
