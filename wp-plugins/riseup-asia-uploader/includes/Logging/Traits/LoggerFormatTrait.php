<?php
/**
 * Logger Format Trait — Entry formatting, backtrace formatting, context enrichment.
 *
 * @package RiseupAsia\Logging\Traits
 * @since   1.4.0
 */

namespace RiseupAsia\Logging\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\DateHelper;

trait LoggerFormatTrait {
    /** Format a log entry. */
    private function formatEntry(
        string $level,
        string $message,
        string $file,
        int $line,
        array $context = array(),
    ): string {
        $timestamp = DateHelper::nowUtc();
        $basename  = basename($file);

        $entry = sprintf("[%s] [%s] %s (%s:%d)", $timestamp, $level, $message, $basename, $line);

        if (BooleanHelpers::hasValue($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return $entry . PHP_EOL;
    }

    /** Format a debug_backtrace array into a readable string. */
    private function formatBacktrace(array $trace): string {
        $lines = array();

        foreach ($trace as $i => $frame) {
            $file  = isset($frame['file']) ? basename($frame['file']) : self::TRACE_LABEL_INTERNAL;
            $line  = isset($frame['line']) ? $frame['line'] : self::DEFAULT_LINE_NUMBER;
            $class = isset($frame['class']) ? $frame['class'] . $frame['type'] : '';
            $func  = isset($frame['function']) ? $frame['function'] : self::TRACE_LABEL_UNKNOWN;
            $lines[] = sprintf(
                '#%d %s(%d): %s%s()',
                $i,
                $file,
                $line,
                $class,
                $func,
            );
        }

        return implode(PHP_EOL, $lines);
    }

    /** Gather HTTP request metadata (method, endpoint, user-agent, IP). */
    private function getRequestMetadata(): array {
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
            'script' => isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : self::TRACE_LABEL_UNKNOWN,
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
            'userAgent' => strlen($useragent) > self::USER_AGENT_MAX_LENGTH ? substr($useragent, 0, self::USER_AGENT_MAX_LENGTH) . '…' : $useragent,
            'ip'        => $ip,
        );
    }

    /** Merge request metadata into a context array (non-destructive). */
    private function enrichContextWithRequest(array $context): array {
        $meta = $this->getRequestMetadata();
        if (BooleanHelpers::isKeyMissing($context, '_request')) {
            $context = array_merge($meta, $context);
        }

        return $context;
    }

    /** Prepare context for logging by enriching with request metadata. */
    private function prepareContext(
        array $context,
        ?array $trace = null,
        bool $includeChain = false,
    ): array {
        $context = $this->enrichContextWithRequest($context);

        $shouldSkipChain = ($includeChain === false || $trace === null || isset($context['_invocation_chain']));

        if ($shouldSkipChain) {
            return $context;
        }

        $chain = $this->buildInvocationChain($trace);
        if (BooleanHelpers::hasValue($chain)) {
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
            if (BooleanHelpers::hasValue($entry)) {
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
            $entry['line'] = isset($frame['line']) ? $frame['line'] : self::DEFAULT_LINE_NUMBER;
        }

        return $entry;
    }
}
