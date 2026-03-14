<?php
/**
 * Main plugin shell class.
 *
 * All logic lives in domain-specific traits; this class composes them
 * and wires up WordPress hooks during construction.
 *
 * @package RiseupAsia\Core
 * @since   2.0.3
 */

namespace RiseupAsia\Core;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\HookType;
use RiseupAsia\Enums\PluginConfigType;

use RiseupAsia\Helpers\InitHelpers;
use RiseupAsia\Helpers\SettingsMigrationHelper;
use RiseupAsia\Logging\FileLogger;
use RiseupAsia\Database\Database;
use RiseupAsia\Logging\Logger;
use RiseupAsia\Post\PostManager;
use RiseupAsia\Update\UpdateResolver;
use RiseupAsia\Snapshot\SnapshotScheduler;
use RiseupAsia\Admin\Admin;

// Trait imports
use RiseupAsia\Traits\Core\LifecycleHooksTrait;
use RiseupAsia\Traits\Route\RouteRegistrationTrait;
use RiseupAsia\Traits\Plugin\PluginRoutesTrait;
use RiseupAsia\Traits\Route\InvalidRouteTrait;
use RiseupAsia\Traits\Auth\AuthTrait;
use RiseupAsia\Traits\Status\StatusHandlerTrait;
use RiseupAsia\Traits\Upload\UploadPipelineTrait;
use RiseupAsia\Traits\Upload\UploadExtractionTrait;
use RiseupAsia\Traits\Plugin\PluginListTrait;
use RiseupAsia\Traits\Plugin\PluginExportTrait;
use RiseupAsia\Traits\Core\PostHandlerTrait;
use RiseupAsia\Traits\Plugin\PluginLifecycleTrait;
use RiseupAsia\Traits\Plugin\PluginBackupHandlerTrait;
use RiseupAsia\Traits\Sync\SyncHandlerTrait;
use RiseupAsia\Traits\Core\ResponseTrait;
use RiseupAsia\Traits\Error\ErrorLogTrait;
use RiseupAsia\Traits\Agent\AgentHandlerTrait;
use RiseupAsia\Traits\Snapshot\SnapshotCrudTrait;
use RiseupAsia\Traits\Snapshot\SnapshotExportTrait;
use RiseupAsia\Traits\Snapshot\SnapshotBackupTrait;
use RiseupAsia\Traits\FileSystem\FileSystemTrait;
use RiseupAsia\Traits\Log\LogStatusTrait;
use RiseupAsia\Traits\Log\LogClearingTrait;

class Plugin {
    // Wave 1 traits
    use LifecycleHooksTrait;
    use RouteRegistrationTrait;
    use PluginRoutesTrait;
    use InvalidRouteTrait;
    use AuthTrait;
    use StatusHandlerTrait;

    // Wave 2 traits
    use UploadPipelineTrait;
    use UploadExtractionTrait;
    use PluginListTrait;
    use PluginExportTrait;
    use PostHandlerTrait;
    use PluginLifecycleTrait;
    use PluginBackupHandlerTrait;
    use SyncHandlerTrait;

    // Wave 3 traits
    use ResponseTrait;
    use ErrorLogTrait;
    use AgentHandlerTrait;
    use SnapshotCrudTrait;
    use SnapshotExportTrait;
    use SnapshotBackupTrait;
    use FileSystemTrait;

    private FileLogger $fileLogger;
    private ?Logger $logger = null;
    private ?Database $db = null;
    private ?PostManager $postManager = null;
    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $this->fileLogger->info('Plugin constructor starting', array('version' => PluginConfigType::Version->value));

        SettingsMigrationHelper::migrateIfNeeded();

        // Register REST routes and lifecycle hooks BEFORE component init
        add_action(HookType::RestApiInit->value, array($this, 'registerRoutes'));
        add_action(
            HookType::ActivatedPlugin->value,
            array($this, 'onPluginActivated'),
            10,
            2,
        );
        add_action(
            HookType::DeactivatedPlugin->value,
            array($this, 'onPluginDeactivated'),
            10,
            2,
        );
        add_action(
            HookType::DeletedPlugin->value,
            array($this, 'onPluginDeleted'),
            10,
            2,
        );
        add_filter(
            HookType::RestPostDispatch->value,
            array($this, 'enrichErrorResponse'),
            10,
            3,
        );

        $this->fileLogger->info('REST routes and lifecycle hooks registered (pre-init)');

        $this->initComponents();

        InitHelpers::logStartupSummary($this->fileLogger);
        $this->fileLogger->info('Plugin constructor complete', array(
            'db_available' => $this->db !== null,
        ));
    }

    private function initComponents(): void {
        $this->db = InitHelpers::initComponent('Database', function () {
            $db = Database::getInstance();
            return $db->init() ? $db : null;
        });

        $this->logger = InitHelpers::initComponent('TransactionLogger', function () {
            return Logger::getInstance();
        });

        $this->postManager = InitHelpers::initComponent('PostManager', function () {
            return PostManager::getInstance();
        });

        InitHelpers::initComponent('UpdateResolver', function () {
            return UpdateResolver::getInstance();
        });

        if ($this->db !== null) {
            InitHelpers::initComponent('SnapshotScheduler', function () {
                $scheduler = SnapshotScheduler::getInstance($this->fileLogger, $this->db);
                $scheduler->init();
                return $scheduler;
            });
        } else {
            $this->fileLogger->info('SnapshotScheduler skipped - database not available');
        }
    }
}
