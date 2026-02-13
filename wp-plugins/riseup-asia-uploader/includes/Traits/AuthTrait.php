<?php
/**
 * AuthTrait — Permission callbacks and authentication logic.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/AuthPermissionTrait.php';
require_once dirname(__FILE__) . '/AuthCredentialTrait.php';

trait AuthTrait
{
    use AuthPermissionTrait;
    use AuthCredentialTrait;
}
