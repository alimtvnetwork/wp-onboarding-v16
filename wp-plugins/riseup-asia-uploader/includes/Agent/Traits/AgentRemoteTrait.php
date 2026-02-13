<?php
/**
 * Agent Remote Operations Trait — Shell delegating to AgentRemoteCoreTrait and AgentRemoteActionTrait.
 *
 * @package RiseupAsiaUploader
 * @since   1.57.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AgentRemoteCoreTrait.php';
require_once __DIR__ . '/AgentRemoteActionTrait.php';

trait AgentRemoteTrait {
    use AgentRemoteCoreTrait;
    use AgentRemoteActionTrait;
}
