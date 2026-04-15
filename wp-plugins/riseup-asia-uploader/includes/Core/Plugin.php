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
use RiseupAsia\Traits\Log\LogRotationStatusTrait;
use RiseupAsia\Traits\Log\LogClearingTrait;
use RiseupAsia\Traits\Log\LogClearAllTrait;
use RiseupAsia\Traits\Log\LogEmailTrait;
use RiseupAsia\Traits\Log\LogRetrievalTrait;
use RiseupAsia\Traits\Log\LogDedupRegistryTrait;
use RiseupAsia\Traits\Machine\MachineApprovalTrait;
use RiseupAsia\Traits\User\UserCrudTrait;
use RiseupAsia\Traits\CloudStorage\CloudStorageTrait;
use RiseupAsia\Traits\SiteSettings\SiteSettingsTrait;
use RiseupAsia\Traits\SiteSettings\SiteHealthSummaryTrait;
use RiseupAsia\Traits\Debug\DebugRoutesTrait;
use RiseupAsia\Helpers\Traits\TypeCheckerTrait;

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
    use LogStatusTrait;
    use LogRotationStatusTrait;
    use LogClearingTrait;
    use LogClearAllTrait;
    use LogEmailTrait;
    use LogRetrievalTrait;
    use LogDedupRegistryTrait;
    use MachineApprovalTrait;

    // Wave 4 traits
    use UserCrudTrait;

    // Wave 5 traits
    use CloudStorageTrait;

    // Wave 6 traits
    use SiteSettingsTrait;
    use SiteHealthSummaryTrait;
    use DebugRoutesTrait;

    private FileLogger $fileLogger;
    private ?Logger $logger = null;
    private ?Database $db = null;
    private ?PostManager $postManager = null;
    private static ?self $instance = null;

    /** Public accessor for the upload-in-progress guard (used by deactivation hook). */
    public static function isUploadInProgress(): bool {
        return self::$isUploadInProgress ?? false;
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->fileLogger = FileLogger::getInstance();
        $startMs = microtime(true);
        $isVerbose = InitHelpers::isBootVerbose();

        if ($isVerbose) {
            $this->fileLogger->debug('[BOOT] Constructor starting', [
                'version' => PluginConfigType::Version->value,
                'constant' => 'RISEUP_DEBUG_BOOT',
            ]);
        }

        SettingsMigrationHelper::migrateIfNeeded();

        if ($isVerbose) {
            $this->fileLogger->debug('[BOOT] Settings migration check complete');
        }

        // Register REST routes and lifecycle hooks BEFORE component init
        add_action(HookType::RestApiInit->value, [$this, 'registerRoutes']);
        add_action(
            HookType::ActivatedPlugin->value,
            [$this, 'onPluginActivated'],
            10,
            2,
        );
        add_action(
            HookType::DeactivatedPlugin->value,
            [$this, 'onPluginDeactivated'],
            10,
            2,
        );
        add_action(
            HookType::DeletedPlugin->value,
            [$this, 'onPluginDeleted'],
            10,
            2,
        );
        add_filter(
            HookType::RestPostDispatch->value,
            [$this, 'enrichErrorResponse'],
            10,
            3,
        );

        if ($isVerbose) {
            $this->fileLogger->debug('[BOOT] WordPress hooks registered');
        }

        $this->initComponents();

        $total = count(InitHelpers::getStartupResults());
        $failed = count(InitHelpers::getFailedStartups());
        $componentMs = InitHelpers::getTotalStartupTime();
        $elapsedMs = round((microtime(true) - $startMs) * 1000, 2);

        $summary = [
            'version'      => PluginConfigType::Version->value,
            'db_available' => $this->db !== null,
            'components'   => $total,
            'componentMs'  => $componentMs,
            'totalMs'      => $elapsedMs,
        ];

        if ($isVerbose) {
            $summary['bootVerbose'] = true;
        }

        if ($failed > 0) {
            $summary['failed'] = $failed;
            $summary['failures'] = array_map(function (array $r): string {
                return $r['name'] . ': ' . ($r[\RiseupAsia\Enums\ResponseKeyType::Error->value] ?? 'unknown');
            }, InitHelpers::getFailedStartups());
            $this->fileLogger->warn('Plugin initialized with failures', $summary);
        } else {
            $this->fileLogger->info('Plugin initialized', $summary);
        }
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
