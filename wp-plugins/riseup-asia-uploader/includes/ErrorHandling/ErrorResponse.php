<?php
/**
 * Error Response Helper
 *
 * Consolidates exception logging and standardized error array return
 * into a single call, reducing boilerplate in catch blocks.
 *
 * @package RiseupAsiaUploader
 * @since   1.60.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ErrorResponse {

    /**
     * Log exception and return standardized error array.
     *
     * Uses the exception's own file/line for accurate stack traces,
     * so no frame skipping is needed for exception-based logging.
     *
     * @param RiseupFileLogger $logger  File logger instance.
     * @param Throwable        $e       The caught exception.
     * @param string           $context Descriptive context message (e.g., 'Post creation exception').
     * @return array Standardized error response: ['success' => false, 'error' => string].
     */
    public static function logAndReturn(RiseupFileLogger $logger, Throwable $e, string $context = ''): array {
        $logger->logException($e, $context);

        return array(
            'success' => false,
            'error'   => $e->getMessage(),
        );
    }
}
