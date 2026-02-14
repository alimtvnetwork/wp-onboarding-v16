<?php
/**
 * AgentHandlerTrait — REST handlers for agent site management.
 *
 * Shell trait — logic delegated to AgentHandlerCrudTrait and AgentHandlerActionTrait.
 *
 * @package RiseupAsiaUploader
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AgentHandlerCrudTrait.php';
require_once __DIR__ . '/AgentHandlerActionTrait.php';

trait AgentHandlerTrait {
    use AgentHandlerCrudTrait;
    use AgentHandlerActionTrait;
}
