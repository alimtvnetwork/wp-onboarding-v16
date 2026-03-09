<?php
/**
 * ResponseTrait — Error response helpers for QUpload.
 *
 * @package QUpload\Traits\Core
 * @since   1.0.0
 */

namespace QUpload\Traits\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use WP_REST_Response;

use QUpload\Enums\HttpStatusType;
use QUpload\Helpers\EnvelopeBuilder;

trait ResponseTrait
{
    /** Create an error response with optional exception details. */
    private function errorResponse(
        string $message,
        int $status,
        ?Throwable $exception = null,
    ): WP_REST_Response {
        if ($exception instanceof Throwable) {
            $this->fileLogger->logException($exception, $message);
        } else {
            $this->fileLogger->error('Error response: ' . $message, ['status' => $status]);
        }

        $requestedAt = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return EnvelopeBuilder::error($message, $status, $exception)
            ->setRequestedAt($requestedAt)
            ->toResponse();
    }
}
