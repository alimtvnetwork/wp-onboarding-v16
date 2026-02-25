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

    private bool $isSuccess = true;
    private int $code = 200; // Matches HttpStatusType::Ok->value; literal required for PHP property default
    private string $message = 'OK';
    private array $results = array();
    private string $requestedAt = '';
    private string $delegatedAt = '';
    private bool $hasErrors = false;
    private int $totalRecords = 0;
    private int $perPage = 0;
    private int $totalPages = 0;
    private int $currentPage = 0;
    private ?array $navigation = null;
    private ?array $errors = null;
    private ?array $methodsStack = null;
}
