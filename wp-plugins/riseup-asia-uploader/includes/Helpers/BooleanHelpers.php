<?php
/**
 * Boolean Helper Functions
 *
 * Shell class — logic delegated to domain-specific traits.
 *
 * @package RiseupAsiaUploader
 * @since   1.18.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait files
require_once __DIR__ . '/Traits/BooleanDomainTrait.php';

/**
 * Class RiseupBooleanHelpers
 *
 * Centralized boolean check functions for domain-specific guards.
 */
class RiseupBooleanHelpers {

    use BooleanDomainTrait;
}
