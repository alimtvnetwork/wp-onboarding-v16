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

    private $isSuccess = true;
    private $code = 200; // Matches HttpStatusType::Ok->value; literal required for PHP property default
    private $message = 'OK';
    private $results = array();
    private $requestedAt = '';
    private $delegatedAt = '';
    private $hasErrors = false;
    private $totalRecords = 0;
    private $perPage = 0;
    private $totalPages = 0;
    private $currentPage = 0;
    private $navigation = null;
    private $errors = null;
    private $methodsStack = null;
}
