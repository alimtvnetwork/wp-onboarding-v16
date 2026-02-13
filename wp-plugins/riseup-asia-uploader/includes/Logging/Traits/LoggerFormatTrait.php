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
    private function formatEntry($level, $message, $file, $line, $context = array()) {
        $timestamp = gmdate('Y-m-d\TH:i:s') . 'Z';
        $basename  = basename($file);

        $entry = sprintf("[%s] [%s] %s (%s:%d)", $timestamp, $level, $message, $basename, $line);

        if (!empty($context)) {
            $jsonFlags = defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0;
            $entry .= ' ' . json_encode($context, $jsonFlags);
        }

        return $entry . PHP_EOL;
    }

    /**
     * Format a debug_backtrace array into a readable string.
     *
     * @param array $trace debug_backtrace result.
     * @return string Formatted stack trace.
     */
    private function formatBacktrace($trace) {
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
    private function getRequestMetadata() {
        if ($this->requestMetadataCache !== null) {
            return $this->requestMetadataCache;
        }

        $meta = array();
        $meta['_request'] = (php_sapi_name() === 'cli')
            ? $this->buildCliRequestMeta()
            : $this->buildHttpRequestMeta();

        $this->requestMetadataCache = $meta;

        return $meta;
    }

    /** Build request metadata for CLI context. */
    private function buildCliRequestMeta(): array {
        return array(
            'method' => 'CLI',
            'script' => isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : 'unknown',
        );
    }

    /** Build request metadata for HTTP context. */
    private function buildHttpRequestMeta(): array {
        $method    = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN';
        $uri       = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
        $query     = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
        $useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip        = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        return array(
            'method'    => $method,
            'endpoint'  => $uri . $query,
            'userAgent' => strlen($useragent) > 200 ? substr($useragent, 0, 200) . '…' : $useragent,
            'ip'        => $ip,
        );
    }

    /**
     * Merge request metadata into a context array (non-destructive).
     *
     * @param array $context Existing context.
     * @return array Context enriched with request metadata.
     */
    private function enrichContextWithRequest($context) {
        $meta = $this->getRequestMetadata();
        if (!isset($context['_request'])) {
            $context = array_merge($meta, $context);
        }
        return $context;
    }

    /**
     * Prepare context for logging by enriching with request metadata and
     * optionally building an invocation chain from a backtrace.
     *
     * @param array      $context      Existing context array.
     * @param array|null $trace        Optional debug_backtrace result.
     * @param bool       $includeChain Whether to build _invocation_chain.
     * @return array Enriched context.
     */
    private function prepareContext($context, $trace = null, $includeChain = false) {
        $context = $this->enrichContextWithRequest($context);

        $shouldSkipChain = !$includeChain || $trace === null || isset($context['_invocation_chain']);
        if ($shouldSkipChain) {
            return $context;
        }

        $chain = $this->buildInvocationChain($trace);
        if (!empty($chain)) {
            $context['_invocation_chain'] = $chain;
        }

        return $context;
    }

    /** Build an invocation chain from a backtrace (skipping frame 0). */
    private function buildInvocationChain(array $trace): array {
        $chain = array();
        foreach ($trace as $i => $frame) {
            if ($i === 0) {
                continue;
            }

            $entry = $this->extractChainEntry($frame);
            if (!empty($entry)) {
                $chain[] = $entry;
            }
        }

        return $chain;
    }

    /** Extract a single chain entry from a backtrace frame. */
    private function extractChainEntry(array $frame): array {
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

        return $entry;
    }
}
