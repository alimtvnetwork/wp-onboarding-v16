<?php
/**
 * Agent CRUD Trait — Shell delegating to AgentCrudWriteTrait and AgentCrudReadTrait.
 *
 * @package RiseupAsia\Agent\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Agent\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait AgentCrudTrait {
    use AgentCrudWriteTrait;
    use AgentCrudReadTrait;
}
