<?php
/**
 * HttpMethodEnum — HTTP Method Constants
 *
 * Centralizes HTTP method strings for REST route registration.
 * Replaces WP_REST_Server::READABLE, WP_REST_Server::CREATABLE, etc.
 * and raw 'GET', 'POST' string literals.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HTTP method constants for REST API route registration.
 */
class HttpMethodEnum {

    /** GET — Read operations */
    public const GET    = 'GET';

    /** POST — Create operations */
    public const POST   = 'POST';

    /** PUT — Full update operations */
    public const PUT    = 'PUT';

    /** PATCH — Partial update operations */
    public const PATCH  = 'PATCH';

    /** DELETE — Remove operations */
    public const DELETE = 'DELETE';

    /** Editable methods (PUT + PATCH) for WordPress route registration */
    public const EDITABLE = 'PUT, PATCH';
}
