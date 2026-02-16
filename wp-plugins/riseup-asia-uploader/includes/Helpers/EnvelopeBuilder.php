<?php
/**
 * Universal Response Envelope Builder
 *
 * @package RiseupAsia\Helpers
 * @since   1.33.0
 * @template T of array
 */

namespace RiseupAsia\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Helpers\Traits\EnvelopeFactoryTrait;
use RiseupAsia\Helpers\Traits\EnvelopeSettersTrait;
use RiseupAsia\Helpers\Traits\EnvelopeBuildTrait;

class EnvelopeBuilder {

    use EnvelopeFactoryTrait;
    use EnvelopeSettersTrait;
    use EnvelopeBuildTrait;

    private $is_success = true;
    private $code = 200;
    private $message = 'OK';
    private $results = array();
    private $requested_at = '';
    private $delegated_at = '';
    private $has_errors = false;
    private $total_records = 0;
    private $per_page = 0;
    private $total_pages = 0;
    private $current_page = 0;
    private $navigation = null;
    private $errors = null;
    private $methods_stack = null;
}
