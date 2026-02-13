<?php
/**
 * Agent CRUD Trait — Shell delegating to AgentCrudWriteTrait and AgentCrudReadTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AgentCrudWriteTrait.php';
require_once __DIR__ . '/AgentCrudReadTrait.php';

trait AgentCrudTrait {
    use AgentCrudWriteTrait;
    use AgentCrudReadTrait;
}
