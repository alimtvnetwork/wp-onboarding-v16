<?php
/**
 * AuthTrait — Permission callbacks and authentication logic.
 *
 * Shell trait — logic delegated to sub-traits.
 *
 * @package RiseupAsia\Traits\Auth
 * @since   1.57.0
 */

namespace RiseupAsia\Traits\Auth;

if (!defined('ABSPATH')) {
    exit;
}

trait AuthTrait
{
    use AuthPermissionTrait;
    use AuthCredentialTrait;
}