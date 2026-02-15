<?php
/**
 * Agent Remote Operations Trait — Shell delegating to AgentRemoteCoreTrait and AgentRemoteActionTrait.
 *
 * @package RiseupAsia\Agent\Traits
 * @since   1.57.0
 */

namespace RiseupAsia\Agent\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait AgentRemoteTrait {
    use AgentRemoteCoreTrait;
    use AgentRemoteActionTrait;
}
