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
use QUpload\Traits\Upload\UploadHandlerTrait;
use QUpload\Traits\Activate\ActivateHandlerTrait;
use QUpload\Traits\Deactivate\DeactivateHandlerTrait;

class Plugin {
    use AdminTrait;
    use AuthTrait;
    use RouteRegistrationTrait;
    use StatusHandlerTrait;
    use ResponseTrait;
    use UploadHandlerTrait;
    use ActivateHandlerTrait;
    use DeactivateHandlerTrait;

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
        $this->registerAdminPage();

        $this->fileLogger->info('Plugin constructor complete');
    }
}
