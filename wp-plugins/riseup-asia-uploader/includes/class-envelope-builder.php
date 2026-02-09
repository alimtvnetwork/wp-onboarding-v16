<?php
/**
 * Universal Response Envelope Builder
 *
 * Constructs API responses conforming to the Universal Response Envelope specification.
 * All JSON keys use PascalCase. Results is always an array.
 *
 * @package RiseupAsiaUploader
 * @since   1.33.0
 * @see     spec/response-envelope/README.md
 * @schema  spec/response-envelope/envelope.schema.json v1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RiseupEnvelopeBuilder
 *
 * Fluent builder for the Universal Response Envelope format.
 * Uses PHPStan/Psalm @template annotations for static analysis type safety.
 *
 * Usage:
 *   /** @var RiseupEnvelopeBuilder<array{status: string, version: string}> $envelope {@*}
 *   return RiseupEnvelopeBuilder::success()
 *       ->setResults(array($data))
 *       ->setRequestedAt('/status')
 *       ->toResponse();
 *
 *   return RiseupEnvelopeBuilder::error('Something failed', 500, $exception)
 *       ->setRequestedAt('/plugins/enable')
 *       ->setDelegatedAt($delegated_url)
 *       ->toResponse();
 *
 * @template T of array
 * @phpstan-template T of array
 * @psalm-template T of array
 */
class RiseupEnvelopeBuilder {

    /** @var bool */
    private $is_success = true;

    /** @var int */
    private $code = 200;

    /** @var string */
    private $message = 'OK';

    /** @var array<int, T> */
    private $results = array();

    /** @var string */
    private $requested_at = '';

    /** @var string */
    private $delegated_at = '';

    /** @var bool */
    private $has_errors = false;

    // Pagination
    /** @var int */
    private $total_records = 0;
    /** @var int */
    private $per_page = 0;
    /** @var int */
    private $total_pages = 0;
    /** @var int */
    private $current_page = 0;

    // Navigation (optional)
    /** @var array|null */
    private $navigation = null;

    // Errors (optional)
    /** @var array|null */
    private $errors = null;

    // MethodsStack (optional)
    /** @var array|null */
    private $methods_stack = null;

    // =========================================================================
    // STATIC FACTORY METHODS
    // =========================================================================

    /**
     * Create a success envelope.
     *
     * @param string $message Optional success message.
     * @param int    $code    HTTP status code (default 200).
     *
     * @return static<T>
     * @phpstan-return static<T>
     */
    public static function success($message = 'OK', $code = 200) {
        $builder = new self();
        $builder->is_success = true;
        $builder->code = $code;
        $builder->message = $message;
        return $builder;
    }

    /**
     * Create an error envelope.
     *
     * @param string         $message   Error message.
     * @param int            $code      HTTP status code (default 500).
     * @param Throwable|null $exception Optional exception for stack trace extraction.
     *
     * @return static<T>
     * @phpstan-return static<T>
     */
    public static function error($message, $code = 500, $exception = null) {
        $builder = new self();
        $builder->is_success = false;
        $builder->code = $code;
        $builder->message = $message;
        $builder->has_errors = true;

        // Build Errors block
        $errors = array(
            'BackendMessage'              => $message,
            'DelegatedServiceErrorStack'  => array(),
            'Backend'                     => array(),
            'Frontend'                    => array(),
        );

        if ($exception instanceof Throwable) {
            // PHP stack trace as DelegatedServiceErrorStack lines
            $trace_lines = explode("\n", $exception->getTraceAsString());
            $errors['DelegatedServiceErrorStack'] = $trace_lines;

            // Structured frames for Backend
            if (function_exists('riseup_exception_to_frames')) {
                $frames = riseup_exception_to_frames($exception);
                $backend_lines = array();
                foreach ($frames as $frame) {
                    $file = isset($frame['fileBase']) ? $frame['fileBase'] : '';
                    $line = isset($frame['line']) ? $frame['line'] : 0;
                    $fn = isset($frame['function']) ? $frame['function'] : '';
                    $class = isset($frame['class']) ? $frame['class'] . '::' : '';
                    $backend_lines[] = "{$file}:{$line} {$class}{$fn}";
                }
                $errors['Backend'] = $backend_lines;
            }
        } else {
            // Generate backtrace-based error context
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);
            if (function_exists('riseup_backtrace_to_frames')) {
                $frames = riseup_backtrace_to_frames($backtrace);
                $backend_lines = array();
                foreach ($frames as $frame) {
                    $file = isset($frame['fileBase']) ? $frame['fileBase'] : '';
                    $line = isset($frame['line']) ? $frame['line'] : 0;
                    $fn = isset($frame['function']) ? $frame['function'] : '';
                    $class = isset($frame['class']) ? $frame['class'] . '::' : '';
                    $backend_lines[] = "{$file}:{$line} {$class}{$fn}";
                }
                $errors['Backend'] = $backend_lines;
            }
        }

        $builder->errors = $errors;
        return $builder;
    }

    // =========================================================================
    // FLUENT SETTERS
    // =========================================================================

    /**
     * Set the Results array.
     *
     * @param array<int, T> $results Array of result items (always an array, even for single).
     * @return static<T>
     * @phpstan-return static<T>
     */
    public function setResults(array $results) {
        $this->results = $results;
        return $this;
    }

    /**
     * Set a single result item (wraps in array).
     *
     * @param T $item Single result item.
     * @return static<T>
     * @phpstan-return static<T>
     */
    public function setSingleResult(array $item) {
        $this->results = array($item);
        return $this;
    }

    /**
     * Set the RequestedAt path (the endpoint that handled this request).
     *
     * @param string $path Endpoint path.
     * @return static<T>
     */
    public function setRequestedAt($path) {
        $this->requested_at = $path;
        return $this;
    }

    /**
     * Auto-detect RequestedAt from the current REST request URI.
     *
     * @return static<T>
     */
    public function autoDetectRequestedAt() {
        if (isset($_SERVER['REQUEST_URI'])) {
            $this->requested_at = $_SERVER['REQUEST_URI'];
        }
        return $this;
    }

    /**
     * Set the RequestDelegatedAt URL (downstream endpoint if proxied).
     *
     * @param string $url Full downstream URL.
     * @return static<T>
     */
    public function setDelegatedAt($url) {
        $this->delegated_at = $url;
        return $this;
    }

    /**
     * Set pagination metadata.
     *
     * @param int $total_records Total record count.
     * @param int $per_page      Records per page.
     * @param int $current_page  Current page number.
     * @return static<T>
     */
    public function setPagination($total_records, $per_page, $current_page) {
        $this->total_records = $total_records;
        $this->per_page = $per_page;
        $this->current_page = $current_page;
        $this->total_pages = ($per_page > 0) ? (int) ceil($total_records / $per_page) : 0;
        return $this;
    }

    /**
     * Set navigation links for paginated responses.
     *
     * @param string|null $next_page    URL for next page.
     * @param string|null $prev_page    URL for previous page.
     * @param array       $closer_links Array of nearby page URLs.
     * @return static<T>
     */
    public function setNavigation($next_page = null, $prev_page = null, $closer_links = array()) {
        $this->navigation = array(
            'NextPage'     => $next_page,
            'PrevPage'     => $prev_page,
            'CloserLinks'  => $closer_links,
        );
        return $this;
    }

    /**
     * Set the MethodsStack for debug/traversal mode.
     *
     * @param array $backend_stack  Array of {Method, File, LineNumber} entries.
     * @param array $frontend_stack Array of frontend call chain entries (usually empty).
     * @return static<T>
     */
    public function setMethodsStack(array $backend_stack, array $frontend_stack = array()) {
        $this->methods_stack = array(
            'Backend'  => $backend_stack,
            'Frontend' => $frontend_stack,
        );
        return $this;
    }

    /**
     * Override or extend the Errors block.
     *
     * @param array $errors Errors data.
     * @return static<T>
     */
    public function setErrors(array $errors) {
        $this->errors = $errors;
        $this->has_errors = true;
        return $this;
    }

    // =========================================================================
    // BUILD & RESPOND
    // =========================================================================

    /**
     * Build the envelope array.
     *
     * @return array{Status: array, Attributes: array, Results: array<int, T>}
     * @phpstan-return array{Status: array{IsSuccess: bool, IsFailed: bool, Code: int, Message: string, Timestamp: string}, Attributes: array, Results: array<int, T>}
     */
    public function build() {
        $result_count = count($this->results);

        $envelope = array(
            'Status' => array(
                'IsSuccess' => $this->is_success,
                'IsFailed'  => !$this->is_success,
                'Code'      => $this->code,
                'Message'   => $this->message,
                'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ),
            'Attributes' => array(
                'RequestedAt'      => $this->requested_at,
                'RequestDelegatedAt' => $this->delegated_at,
                'HasAnyErrors'     => $this->has_errors,
                'IsSingle'         => ($result_count === 1),
                'IsMultiple'       => ($result_count > 1),
                'TotalRecords'     => $this->total_records > 0 ? $this->total_records : $result_count,
                'PerPage'          => $this->per_page,
                'TotalPages'       => $this->total_pages,
                'CurrentPage'      => $this->current_page,
            ),
            'Results' => $this->results,
        );

        // Conditional sections (omit when null)
        if ($this->navigation !== null) {
            $envelope['Navigation'] = $this->navigation;
        }

        if ($this->errors !== null) {
            $envelope['Errors'] = $this->errors;
        }

        if ($this->methods_stack !== null) {
            $envelope['MethodsStack'] = $this->methods_stack;
        }

        return $envelope;
    }

    /**
     * Build and return as a WP_REST_Response.
     *
     * @return WP_REST_Response
     */
    public function toResponse() {
        return new WP_REST_Response($this->build(), $this->code);
    }
}
