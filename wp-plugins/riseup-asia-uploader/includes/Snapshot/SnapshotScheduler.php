<?php
namespace RiseupAsia\Snapshot;

if (!defined('ABSPATH')) { exit; }

use RiseupAsia\Enums\HookType;
use RiseupAsia\Snapshot\Traits\SchedulerCronTrait;
use RiseupAsia\Snapshot\Traits\SchedulerExecutorTrait;
use RiseupAsia\Snapshot\Traits\SchedulerTimingTrait;
use RiseupAsia\Snapshot\Traits\SchedulerTriggerTrait;
use RiseupAsia\Snapshot\Traits\SchedulerConfigTrait;
use RiseupAsia\Database\Database;
use Throwable;
use RiseupAsia\Logging\FileLogger;

class SnapshotScheduler {
    use SchedulerCronTrait;
    use SchedulerExecutorTrait;
    use SchedulerTimingTrait;
    use SchedulerTriggerTrait;
    use SchedulerConfigTrait;

    private FileLogger $logger;
    private Database $db;
    private SnapshotDetector $detector;
    private static ?SnapshotScheduler $instance = null;

    public static function getInstance(?FileLogger $logger = null, ?Database $db = null): static {
        $isReadyToInit = self::$instance === null && $logger !== null && $db !== null;
        if ($isReadyToInit) {
            self::$instance = new self($logger, $db);
        }
        return self::$instance;
    }

    private function __construct(?FileLogger $logger = null, ?Database $db = null) {
        $this->logger = $logger ?: FileLogger::getInstance();
        $this->db = $db ?: Database::getInstance();
        $this->detector = SnapshotFactory::detector($this->logger, $this->db);
    }

    public function init() {
        $this->logger->info('[SCHEDULER] Initializing snapshot scheduler');
        add_filter(HookType::CronSchedules->value, array($this, 'registerCronSchedules'));
        add_action(HookType::CronSnapshotScheduled->value, array($this, 'executeScheduledSnapshot'));
        add_action(HookType::CronSnapshotImmediate->value, array($this, 'executeImmediateSnapshot'));
        add_action(HookType::CronSnapshotCleanup->value, array($this, 'executeCleanup'));
        add_action(HookType::CronSnapshotWorkerBatch->value, array($this, 'executeWorkerBatch'));
        add_action(HookType::CronSnapshotRestore->value, array($this, 'executeCronRestore'));
        add_action(HookType::CronSnapshotIncremental->value, array($this, 'executeCronIncremental'));
        $this->ensureCleanupScheduled();
        $this->syncScheduleWithSettings();
        $this->logger->info('[SCHEDULER] Scheduler initialized');
    }

    public function executeWorkerBatch($args) {
        $this->logger->info('[SCHEDULER] Executing worker batch', $args);
        try {
            $worker = SnapshotFactory::worker($this->logger, $this->db);
            $worker->processWorkerBatch($args);
        } catch (Throwable $e) {
            $this->logger->error('[SCHEDULER] Worker batch exception', array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
        }
    }

    public function executeScheduledSnapshot() { $this->executeCronJob('scheduled snapshot', fn() => $this->runScheduledSnapshot()); }
    public function executeImmediateSnapshot($args) { $this->executeCronJob('immediate snapshot', fn() => $this->runImmediateSnapshot($args)); }
    public function executeCronRestore($args) { $this->executeCronJob('cron restore', fn() => $this->runCronRestore($args)); }
    public function executeCronIncremental($args) { $this->executeCronJob('cron incremental backup', fn() => $this->runCronIncremental($args)); }
    public function executeCleanup() { $this->executeCronJob('snapshot cleanup', fn() => $this->runCleanup()); }
}
