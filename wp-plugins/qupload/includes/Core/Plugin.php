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
use QUpload\Traits\Log\LogDedupRegistryTrait;
use QUpload\Traits\Machine\MachineApprovalTrait;
use QUpload\Traits\Debug\DebugRoutesTrait;

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
    use DebugRoutesTrait;

    private FileLogger $fileLogger;
    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check if verbose boot logging is enabled via wp-config.php constant.
     *
     * Add `define('QUPLOAD_DEBUG_BOOT', true);` to wp-config.php to enable
     * per-component init logging for troubleshooting startup issues.
     */
    public static function isBootVerbose(): bool {
        return defined('QUPLOAD_DEBUG_BOOT') && QUPLOAD_DEBUG_BOOT === true;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $startMs = microtime(true);
        $isVerbose = self::isBootVerbose();

        if ($isVerbose) {
            $this->fileLogger->debug('[BOOT] Constructor starting', [
                'version' => PluginConfigType::Version->value,
                'constant' => 'QUPLOAD_DEBUG_BOOT',
            ]);
        }

        add_action(HookType::RestApiInit->value, [$this, 'registerRoutes']);

        if ($isVerbose) {
            $this->fileLogger->debug('[BOOT] WordPress hooks registered');
        }

        $elapsedMs = round((microtime(true) - $startMs) * 1000, 2);
        $summary = [
            'version' => PluginConfigType::Version->value,
            'timeMs'  => $elapsedMs,
        ];

        if ($isVerbose) {
            $summary['bootVerbose'] = true;
        }

        $this->fileLogger->info('Plugin initialized', $summary);
    }
}
