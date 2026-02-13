<?php
/**
 * Universal Response Envelope Builder
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.33.0
 * @see     spec/response-envelope/README.md
 * @schema  spec/response-envelope/envelope.schema.json v1.0.0
 *
 * @template T of array
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/EnvelopeFactoryTrait.php';
require_once __DIR__ . '/Traits/EnvelopeSettersTrait.php';
require_once __DIR__ . '/Traits/EnvelopeBuildTrait.php';

/**
 * Class RiseupEnvelopeBuilder
 *
 * Fluent builder for the Universal Response Envelope format.
 */
class RiseupEnvelopeBuilder {

    use EnvelopeFactoryTrait;
    use EnvelopeSettersTrait;
    use EnvelopeBuildTrait;

    /** @var bool */
    private $is_success = true;
    /** @var int */
    private $code = 200;
    /** @var string */
    private $message = 'OK';
    /** @var array */
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

    // Optional sections
    /** @var array|null */
    private $navigation = null;
    /** @var array|null */
    private $errors = null;
    /** @var array|null */
    private $methods_stack = null;
}
