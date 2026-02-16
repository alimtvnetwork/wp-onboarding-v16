<?php
/**
 * AgentHandlerTrait — REST handlers for agent site management.
 *
 * Shell trait — logic delegated to AgentHandlerCrudTrait and AgentHandlerActionTrait.
 *
 * @package RiseupAsia\Traits\Agent
 */

namespace RiseupAsia\Traits\Agent;

if (!defined('ABSPATH')) {
    exit;
}

trait AgentHandlerTrait {
    use AgentHandlerCrudTrait;
    use AgentHandlerActionTrait;
}