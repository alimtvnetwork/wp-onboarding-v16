<?php
/**
 * AdminMenuTrait — Shell orchestrator composing menu registration and asset enqueuing sub-traits.
 *
 * @package RiseupAsia\Admin\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Admin\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait AdminMenuTrait {
    use AdminMenuRegistrationTrait;
    use AdminMenuEnqueueCoreTrait;
    use AdminMenuEnqueueErrorSettingsTrait;
    use AdminMenuEnqueueAgentsTrait;
    use AdminMenuEnqueueSnapshotsTrait;
    use AdminMenuEnqueueMiscTrait;
}
