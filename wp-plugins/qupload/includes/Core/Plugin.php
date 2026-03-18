<?php
/**
 * Main plugin class for Quick Upload.
 *
 * Composes all functionality via traits and wires up WordPress hooks.
 *
 * @package QUpload\Core
 * @since   1.0.0
 */

namespace QUpload\Core;

if (!defined('ABSPATH')) {
    exit;
}

use QUpload\Enums\HookType;
use QUpload\Enums\PluginConfigType;
use QUpload\Logging\FileLogger;
use QUpload\Traits\Auth\AuthTrait;
use QUpload\Traits\Route\RouteRegistrationTrait;
use QUpload\Traits\Core\StatusHandlerTrait;
use QUpload\Traits\Core\ResponseTrait;
use QUpload\Traits\Core\PluginInventoryTrait;
use QUpload\Traits\Upload\UploadHandlerTrait;
use QUpload\Traits\Activate\ActivateHandlerTrait;
use QUpload\Traits\Activate\DeactivateEndpointTrait;
use QUpload\Traits\Deactivate\DeactivateHandlerTrait;
use QUpload\Traits\Log\LogStatusTrait;
use QUpload\Traits\Log\LogRotationStatusTrait;
use QUpload\Traits\Log\LogClearingTrait;
use QUpload\Traits\Log\LogEmailTrait;
use QUpload\Traits\Log\LogRetrievalTrait;
use QUpload\Traits\Machine\MachineApprovalTrait;

class Plugin {
    use AuthTrait;
    use RouteRegistrationTrait;
    use StatusHandlerTrait;
    use ResponseTrait;
    use PluginInventoryTrait;
    use UploadHandlerTrait;
    use ActivateHandlerTrait;
    use DeactivateEndpointTrait;
    use DeactivateHandlerTrait;
    use LogStatusTrait;
    use LogRotationStatusTrait;
    use LogClearingTrait;
    use LogEmailTrait;
    use LogRetrievalTrait;
    use MachineApprovalTrait;

    private FileLogger $fileLogger;
    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->fileLogger->info('Plugin constructor starting', ['version' => PluginConfigType::Version->value]);

        add_action(HookType::RestApiInit->value, [$this, 'registerRoutes']);

        $this->fileLogger->info('Plugin constructor complete');
    }
}
