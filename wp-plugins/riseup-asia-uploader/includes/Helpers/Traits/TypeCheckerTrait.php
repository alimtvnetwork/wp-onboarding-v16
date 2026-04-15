<?php
/**
 * TypeCheckerTrait — Fail-fast type checking helpers for REST input validation.
 *
 * Used by REST handlers to validate input types before sanitization.
 * Per Phase 6 §6.1: validate all inputs at the top of the handler,
 * before any business logic or sanitization.
 *
 * @package RiseupAsia\Helpers\Traits
 * @since   2.34.0
 */

namespace RiseupAsia\Helpers\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;
use RiseupAsia\Enums\HttpStatusType;
use RiseupAsia\Enums\PhpNativeType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\EnvelopeBuilder;

trait TypeCheckerTrait {

    /**
     * Check if a value is a string.
     */
    protected function isString(mixed $value): bool {
        return gettype($value) === PhpNativeType::PhpString->value;
    }

    /**
     * Check if a value is an integer.
     */
    protected function isInteger(mixed $value): bool {
        return gettype($value) === PhpNativeType::PhpInteger->value;
    }

    /**
     * Check if a value is an array.
     */
    protected function isArray(mixed $value): bool {
        return gettype($value) === PhpNativeType::PhpArray->value;
    }

    /**
     * Check if a value is a boolean.
     */
    protected function isBoolean(mixed $value): bool {
        return gettype($value) === PhpNativeType::PhpBoolean->value;
    }

    /**
     * Check if a value is numeric (integer or float).
     */
    protected function isNumeric(mixed $value): bool {
        $type = gettype($value);

        return $type === PhpNativeType::PhpInteger->value || $type === PhpNativeType::PhpDouble->value;
    }

    /**
     * Check if a value is a non-empty string.
     */
    protected function isNonEmptyString(mixed $value): bool {
        return $this->isString($value) && $value !== '';
    }

    /**
     * Check if a value is a positive integer.
     */
    protected function isPositiveInteger(mixed $value): bool {
        return $this->isInteger($value) && $value > 0;
    }

    /**
     * Return a 400 validation error response.
     *
     * @param string          $message  Human-readable validation failure message.
     * @param WP_REST_Request $request  The current request (for logging context).
     *
     * @return WP_REST_Response
     */
    protected function validationError(string $message, WP_REST_Request $request): WP_REST_Response {
        $requestedAt = $request->get_route();

        $this->fileLogger->warning('Validation failed', [
            'message' => $message,
            'route'   => $requestedAt,
            'method'  => $request->get_method(),
        ]);

        return EnvelopeBuilder::error($message, HttpStatusType::BadRequest->value)
            ->setRequestedAt($requestedAt)
            ->toResponse();
    }

    /**
     * Validate that the request body exists and is a JSON object (array).
     *
     * @return array|null Returns the body array if valid, null if invalid.
     */
    protected function extractValidBody(WP_REST_Request $request): ?array {
        $body = $request->get_json_params();
        $hasBody = ($body !== null && $this->isArray($body));

        return $hasBody ? $body : null;
    }

    /**
     * Validate a required string field from the body.
     *
     * @return string|WP_REST_Response Returns the string value or an error response.
     */
    protected function requireString(array $body, string $field, WP_REST_Request $request): string|WP_REST_Response {
        $hasField = isset($body[$field]);

        if (!$hasField) {
            return $this->validationError("Missing required field: \"{$field}\"", $request);
        }

        $isValid = $this->isNonEmptyString($body[$field]);

        if (!$isValid) {
            return $this->validationError("Field \"{$field}\" must be a non-empty string", $request);
        }

        return $body[$field];
    }

    /**
     * Validate a required integer field from the body.
     *
     * @return int|WP_REST_Response Returns the integer value or an error response.
     */
    protected function requireInteger(array $body, string $field, WP_REST_Request $request): int|WP_REST_Response {
        $hasField = isset($body[$field]);

        if (!$hasField) {
            return $this->validationError("Missing required field: \"{$field}\"", $request);
        }

        $isValid = $this->isInteger($body[$field]);

        if (!$isValid) {
            return $this->validationError("Field \"{$field}\" must be an integer", $request);
        }

        return $body[$field];
    }
}
