# 22 - Cron Auto-Linking System

> **Status:** Complete  
> **Priority:** Medium  
> **Updated:** 2026-01-31  
> **Dependencies:** `16-cron-system.md`, `21-internal-linking-service.md`

---

## Purpose

Enables scheduled internal linking operations using WordPress Cron (WP-Cron). Supports automatic link creation for orphan content, periodic re-linking based on new targets, and configurable scheduling without keeping the browser open.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                   Auto-Linking Cron System                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐     │
│  │ Schedule Config │───▶│  Create Job     │───▶│  Schedule       │     │
│  │ (UI / API)      │    │  Record         │    │  WP-Cron        │     │
│  └─────────────────┘    └─────────────────┘    └────────┬────────┘     │
│                                                          │              │
│                                                          ▼              │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐     │
│  │   Update Job    │◀───│  Process Batch  │◀───│  Cron Fires     │     │
│  │   Progress      │    │  (10 items)     │    │  (interval)     │     │
│  └─────────────────┘    └────────┬────────┘    └─────────────────┘     │
│                                  │                                      │
│                                  ▼                                      │
│                         ┌─────────────────┐                             │
│                         │ For Each Item:  │                             │
│                         │ 1. Find phrases │                             │
│                         │ 2. Match targets│                             │
│                         │ 3. Apply template│                            │
│                         │ 4. Insert links │                             │
│                         └────────┬────────┘                             │
│                                  │                                      │
│                                  ▼                                      │
│                         ┌─────────────────┐                             │
│                         │ More items?     │──Yes──▶ Re-schedule         │
│                         └────────┬────────┘                             │
│                                  │ No                                   │
│                                  ▼                                      │
│                         ┌─────────────────┐                             │
│                         │ Complete Job    │                             │
│                         │ Send Notification│                            │
│                         └─────────────────┘                             │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Core Constants

> **Reference:** All constants defined in `../66-shared-constants.md`

```php
// Auto-Linking Cron Constants
const AUTO_LINK_CRON_HOOK = 'lm_auto_linking_batch';
const AUTO_LINK_SCHEDULE_HOOK = 'lm_scheduled_auto_link';
const AUTO_LINK_BATCH_SIZE = 10;              // Items per batch (smaller than scan)
const AUTO_LINK_CRON_INTERVAL = 120;          // 2 minutes between batches
const AUTO_LINK_DEFAULT_SCHEDULE = 'daily';   // Default schedule frequency
```

---

## Job Types Enum

```php
<?php
declare(strict_types=1);

namespace LinkManager\Enum;

enum AutoLinkJobType: string
{
    case LINK_ORPHAN_POSTS = 'link_orphan_posts';           // Link posts with < N internal links
    case LINK_ORPHAN_PAGES = 'link_orphan_pages';           // Link pages with < N internal links
    case LINK_ALL_ORPHAN = 'link_all_orphan';               // Link all orphan content
    case LINK_BY_CATEGORY = 'link_by_category';             // Link within specific categories
    case REPROCESS_NEW_TARGETS = 'reprocess_new_targets';   // Re-link using newly added targets
    case SCHEDULED_FULL = 'scheduled_full';                 // Scheduled full site auto-linking
}

enum AutoLinkSchedule: string
{
    case ONCE = 'once';                 // Run once immediately
    case HOURLY = 'hourly';             // Every hour
    case TWICEDAILY = 'twicedaily';     // Twice per day
    case DAILY = 'daily';               // Once per day
    case WEEKLY = 'weekly';             // Once per week
}
```

---

## Database Schema

```sql
-- Auto-Linking Jobs table (in link-manager.db)
CREATE TABLE IF NOT EXISTS AutoLinkJobs (
    Id TEXT PRIMARY KEY,
    Type TEXT NOT NULL,
    Status TEXT NOT NULL DEFAULT 'pending',
    Schedule TEXT DEFAULT 'once',
    TotalItems INTEGER NOT NULL DEFAULT 0,
    CompletedItems INTEGER NOT NULL DEFAULT 0,
    CurrentItem TEXT DEFAULT NULL,
    LinksCreated INTEGER NOT NULL DEFAULT 0,
    ContentUpdated INTEGER NOT NULL DEFAULT 0,
    TemplateId INTEGER DEFAULT NULL,
    LinksPerContent INTEGER NOT NULL DEFAULT 5,
    MaxInternalLinks INTEGER NOT NULL DEFAULT 5,
    CategoryIds TEXT DEFAULT NULL,              -- JSON array
    Options TEXT DEFAULT NULL,                  -- JSON
    Errors TEXT DEFAULT NULL,                   -- JSON array
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    StartedAt TEXT DEFAULT NULL,
    CompletedAt TEXT DEFAULT NULL,
    LastActivityAt TEXT DEFAULT NULL,
    NextScheduledAt TEXT DEFAULT NULL,
    FOREIGN KEY (TemplateId) REFERENCES LinkTemplate(Id)
);

CREATE INDEX idx_autolink_jobs_status ON AutoLinkJobs(Status);
CREATE INDEX idx_autolink_jobs_schedule ON AutoLinkJobs(NextScheduledAt);
CREATE INDEX idx_autolink_jobs_created ON AutoLinkJobs(CreatedAt DESC);

-- Auto-Linking Job Queue
CREATE TABLE IF NOT EXISTS AutoLinkQueue (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    JobId TEXT NOT NULL,
    ContentType TEXT NOT NULL,          -- 'post', 'page', 'category'
    ContentId INTEGER NOT NULL,
    CurrentLinkCount INTEGER DEFAULT 0,
    Processed INTEGER NOT NULL DEFAULT 0,
    LinksAdded INTEGER DEFAULT 0,
    ProcessedAt TEXT DEFAULT NULL,
    ErrorMessage TEXT DEFAULT NULL,
    FOREIGN KEY (JobId) REFERENCES AutoLinkJobs(Id) ON DELETE CASCADE
);

CREATE INDEX idx_autolink_queue_job ON AutoLinkQueue(JobId, Processed);

-- Scheduled Auto-Link Configs
CREATE TABLE IF NOT EXISTS AutoLinkSchedules (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Name TEXT NOT NULL,
    JobType TEXT NOT NULL,
    Schedule TEXT NOT NULL,
    IsActive INTEGER NOT NULL DEFAULT 1,
    TemplateId INTEGER DEFAULT NULL,
    LinksPerContent INTEGER NOT NULL DEFAULT 5,
    MaxInternalLinks INTEGER NOT NULL DEFAULT 5,
    CategoryIds TEXT DEFAULT NULL,          -- JSON array
    LastRunAt TEXT DEFAULT NULL,
    NextRunAt TEXT DEFAULT NULL,
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    UpdatedAt TEXT DEFAULT NULL,
    FOREIGN KEY (TemplateId) REFERENCES LinkTemplate(Id)
);

CREATE INDEX idx_autolink_schedules_active ON AutoLinkSchedules(IsActive, NextRunAt);
```

---

## Core Interfaces

```php
<?php
declare(strict_types=1);

namespace LinkManager\Cron;

use LinkManager\Enum\{AutoLinkJobType, AutoLinkSchedule, JobStatus};

/**
 * Represents an auto-linking background job
 */
interface AutoLinkJobInterface
{
    public function getId(): string;
    public function getType(): AutoLinkJobType;
    public function getStatus(): JobStatus;
    public function getProgress(): AutoLinkProgressInterface;
    public function getTemplateId(): ?int;
    public function getLinksPerContent(): int;
    public function getMaxInternalLinks(): int;
    public function getCategoryIds(): array;
    public function getCreatedAt(): \DateTimeImmutable;
    public function getStartedAt(): ?\DateTimeImmutable;
    public function getCompletedAt(): ?\DateTimeImmutable;
    public function getNextScheduledAt(): ?\DateTimeImmutable;
    public function getErrors(): array;
}

/**
 * Auto-linking progress tracking
 */
interface AutoLinkProgressInterface
{
    public function getTotal(): int;
    public function getCompleted(): int;
    public function getPercentage(): float;
    public function getCurrentItem(): ?string;
    public function getLinksCreated(): int;
    public function getContentUpdated(): int;
    public function getEstimatedTimeRemaining(): ?int; // seconds
}

/**
 * Scheduled auto-link configuration
 */
interface AutoLinkScheduleConfigInterface
{
    public function getId(): int;
    public function getName(): string;
    public function getJobType(): AutoLinkJobType;
    public function getSchedule(): AutoLinkSchedule;
    public function isActive(): bool;
    public function getTemplateId(): ?int;
    public function getLinksPerContent(): int;
    public function getMaxInternalLinks(): int;
    public function getCategoryIds(): array;
    public function getLastRunAt(): ?\DateTimeImmutable;
    public function getNextRunAt(): ?\DateTimeImmutable;
}

/**
 * Main cron auto-linking service
 */
interface CronAutoLinkServiceInterface
{
    // ========== One-Time Jobs ==========
    
    /**
     * Create and schedule a new auto-linking job
     */
    public function createJob(
        AutoLinkJobType $type,
        array $options = []
    ): AutoLinkJobInterface;
    
    /**
     * Get job status
     */
    public function getJob(string $jobId): ?AutoLinkJobInterface;
    
    /**
     * Get all active jobs
     */
    public function getActiveJobs(): array;
    
    /**
     * Cancel a running job
     */
    public function cancelJob(string $jobId): bool;
    
    /**
     * Process next batch (called by WP-Cron)
     */
    public function processBatch(string $jobId): void;
    
    // ========== Scheduled Configs ==========
    
    /**
     * Create a recurring schedule configuration
     */
    public function createSchedule(
        string $name,
        AutoLinkJobType $type,
        AutoLinkSchedule $schedule,
        array $options = []
    ): AutoLinkScheduleConfigInterface;
    
    /**
     * Get all schedule configurations
     */
    public function getSchedules(bool $activeOnly = true): array;
    
    /**
     * Update a schedule configuration
     */
    public function updateSchedule(int $scheduleId, array $updates): bool;
    
    /**
     * Delete a schedule configuration
     */
    public function deleteSchedule(int $scheduleId): bool;
    
    /**
     * Enable/disable a schedule
     */
    public function setScheduleActive(int $scheduleId, bool $active): bool;
    
    /**
     * Run a scheduled config immediately (manual trigger)
     */
    public function runScheduleNow(int $scheduleId): AutoLinkJobInterface;
    
    // ========== Job History ==========
    
    /**
     * Get job history with pagination
     */
    public function getJobHistory(
        int $page = 1,
        int $perPage = 20,
        ?AutoLinkJobType $type = null
    ): PaginatedResult;
    
    /**
     * Cleanup old completed jobs
     */
    public function cleanupOldJobs(int $daysOld = 30): int;
}
```

---

## Implementation

**File:** `src/Cron/CronAutoLinkService.php`

```php
<?php
declare(strict_types=1);

namespace LinkManager\Cron;

use LinkManager\Database\ConnectionManager;
use LinkManager\Services\InternalLinkingServiceInterface;
use LinkManager\Enums\{ContentType, AutoLinkJobType, AutoLinkSchedule, JobStatus};
use LinkManager\Utils\Logger;

final class CronAutoLinkService implements CronAutoLinkServiceInterface
{
    private const CRON_HOOK = 'lm_auto_linking_batch';
    private const SCHEDULE_HOOK = 'lm_scheduled_auto_link';
    private const BATCH_SIZE = 10;
    private const CRON_INTERVAL = 120; // 2 minutes
    
    public function __construct(
        private readonly ConnectionManager $db,
        private readonly InternalLinkingServiceInterface $linkingService,
        private readonly LoggerInterface $logger
    ) {
        // Register cron hooks
        add_action(self::CRON_HOOK, [$this, 'handleBatchEvent']);
        add_action(self::SCHEDULE_HOOK, [$this, 'handleScheduledEvent']);
    }
    
    /**
     * Create and schedule a new auto-linking job
     */
    public function createJob(
        AutoLinkJobType $type,
        array $options = []
    ): AutoLinkJobInterface {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        $jobId = wp_generate_uuid4();
        
        // Extract options
        $templateId = $options['template_id'] ?? null;
        $linksPerContent = $options['links_per_content'] ?? DEFAULT_LINKS_PER_CONTENT;
        $maxInternalLinks = $options['max_internal_links'] ?? 5;
        $categoryIds = $options['category_ids'] ?? [];
        
        // Determine items to process
        $items = $this->getItemsForJob($type, $maxInternalLinks, $categoryIds);
        $totalItems = count($items);
        
        if ($totalItems === 0) {
            Logger::info('No items to process for auto-linking job', [
                'function' => $functionName,
                'file' => $fileName,
                'type' => $type->value
            ]);
        }
        
        // Create job record
        $this->db->execute(
            'INSERT INTO AutoLinkJobs 
             (Id, Type, Status, TotalItems, TemplateId, LinksPerContent, MaxInternalLinks, CategoryIds, Options)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $jobId,
                $type->value,
                JobStatus::PENDING->value,
                $totalItems,
                $templateId,
                $linksPerContent,
                $maxInternalLinks,
                json_encode($categoryIds),
                json_encode($options)
            ]
        );
        
        // Queue items
        $this->queueItems($jobId, $items);
        
        // Schedule first cron event
        $this->scheduleNextBatch($jobId);
        
        Logger::info('Auto-linking job created', [
            'function' => $functionName,
            'file' => $fileName,
            'job_id' => $jobId,
            'type' => $type->value,
            'total_items' => $totalItems,
            'links_per_content' => $linksPerContent
        ]);
        
        return $this->getJob($jobId);
    }
    
    /**
     * Process next batch of items
     */
    public function processBatch(string $jobId): void
    {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        $job = $this->getJobRecord($jobId);
        
        if (!$job || $job['Status'] === JobStatus::CANCELLED->value) {
            Logger::info('Job cancelled or not found, skipping batch', [
                'function' => $functionName,
                'file' => $fileName,
                'job_id' => $jobId
            ]);
            return;
        }
        
        // Acquire lock
        if (!$this->acquireLock($jobId)) {
            Logger::warning('Could not acquire lock for job', [
                'function' => $functionName,
                'file' => $fileName,
                'job_id' => $jobId
            ]);
            return;
        }
        
        try {
            // Update status to running
            if ($job['Status'] === JobStatus::PENDING->value) {
                $this->updateJobStatus($jobId, JobStatus::RUNNING, [
                    'StartedAt' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Get next batch of items
            $items = $this->db->query(
                'SELECT * FROM AutoLinkQueue 
                 WHERE JobId = ? AND Processed = 0 
                 LIMIT ?',
                [$jobId, self::BATCH_SIZE]
            );
            
            $processedCount = 0;
            $linksCreated = 0;
            $contentUpdated = 0;
            $errors = [];
            
            $templateId = $job['TemplateId'];
            $linksPerContent = (int) $job['LinksPerContent'];
            
            while ($item = $items->fetchArray(SQLITE3_ASSOC)) {
                try {
                    // Update current item
                    $this->db->execute(
                        'UPDATE AutoLinkJobs SET CurrentItem = ?, LastActivityAt = datetime("now") 
                         WHERE Id = ?',
                        [$this->getItemTitle($item), $jobId]
                    );
                    
                    // Generate internal links for this item
                    $contentType = $this->mapContentType($item['ContentType']);
                    $result = $this->linkingService->generateLinks(
                        $contentType,
                        (int) $item['ContentId'],
                        $linksPerContent,
                        $templateId
                    );
                    
                    $itemLinksCreated = $result->getLinksCreated();
                    $linksCreated += $itemLinksCreated;
                    
                    if ($itemLinksCreated > 0) {
                        $contentUpdated++;
                    }
                    
                    // Mark as processed
                    $this->db->execute(
                        'UPDATE AutoLinkQueue 
                         SET Processed = 1, LinksAdded = ?, ProcessedAt = datetime("now") 
                         WHERE Id = ?',
                        [$itemLinksCreated, $item['Id']]
                    );
                    
                    $processedCount++;
                    
                    Logger::info('Content auto-linked', [
                        'function' => $functionName,
                        'file' => $fileName,
                        'job_id' => $jobId,
                        'content_id' => $item['ContentId'],
                        'content_type' => $item['ContentType'],
                        'links_created' => $itemLinksCreated
                    ]);
                    
                } catch (\Throwable $e) {
                    $errorMsg = $e->getMessage();
                    $errors[] = [
                        'item' => $item['ContentId'],
                        'type' => $item['ContentType'],
                        'error' => $errorMsg
                    ];
                    
                    // Mark as processed with error
                    $this->db->execute(
                        'UPDATE AutoLinkQueue 
                         SET Processed = 1, ErrorMessage = ?, ProcessedAt = datetime("now") 
                         WHERE Id = ?',
                        [$errorMsg, $item['Id']]
                    );
                    
                    Logger::error('Auto-linking batch item failed', [
                        'function' => $functionName,
                        'file' => $fileName,
                        'job_id' => $jobId,
                        'item_id' => $item['ContentId'],
                        'error' => $errorMsg,
                        'stack_trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            // Update job progress
            $this->updateJobProgress($jobId, $processedCount, $linksCreated, $contentUpdated, $errors);
            
            // Check if complete
            $remaining = $this->getRemainingCount($jobId);
            
            if ($remaining === 0) {
                $this->completeJob($jobId);
            } else {
                // Schedule next batch
                $this->scheduleNextBatch($jobId);
            }
            
        } finally {
            $this->releaseLock($jobId);
        }
    }
    
    /**
     * Handle scheduled auto-link event
     */
    public function handleScheduledEvent(int $scheduleId): void
    {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        $schedule = $this->getScheduleRecord($scheduleId);
        
        if (!$schedule || !$schedule['IsActive']) {
            return;
        }
        
        Logger::info('Running scheduled auto-linking', [
            'function' => $functionName,
            'file' => $fileName,
            'schedule_id' => $scheduleId,
            'name' => $schedule['Name']
        ]);
        
        try {
            // Create job from schedule config
            $job = $this->createJob(
                AutoLinkJobType::from($schedule['JobType']),
                [
                    'template_id' => $schedule['TemplateId'],
                    'links_per_content' => $schedule['LinksPerContent'],
                    'max_internal_links' => $schedule['MaxInternalLinks'],
                    'category_ids' => json_decode($schedule['CategoryIds'] ?? '[]', true),
                    'from_schedule' => $scheduleId
                ]
            );
            
            // Update schedule record
            $nextRun = $this->calculateNextRun(AutoLinkSchedule::from($schedule['Schedule']));
            $this->db->execute(
                'UPDATE AutoLinkSchedules 
                 SET LastRunAt = datetime("now"), NextRunAt = ?, UpdatedAt = datetime("now") 
                 WHERE Id = ?',
                [$nextRun?->format('Y-m-d H:i:s'), $scheduleId]
            );
            
            // Schedule next occurrence
            if ($nextRun !== null) {
                wp_schedule_single_event(
                    $nextRun->getTimestamp(),
                    self::SCHEDULE_HOOK,
                    [$scheduleId]
                );
            }
            
        } catch (\Throwable $e) {
            Logger::error('Scheduled auto-linking failed', [
                'function' => $functionName,
                'file' => $fileName,
                'schedule_id' => $scheduleId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Create a recurring schedule configuration
     */
    public function createSchedule(
        string $name,
        AutoLinkJobType $type,
        AutoLinkSchedule $schedule,
        array $options = []
    ): AutoLinkScheduleConfigInterface {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        $templateId = $options['template_id'] ?? null;
        $linksPerContent = $options['links_per_content'] ?? DEFAULT_LINKS_PER_CONTENT;
        $maxInternalLinks = $options['max_internal_links'] ?? 5;
        $categoryIds = $options['category_ids'] ?? [];
        
        // Calculate next run time
        $nextRun = $this->calculateNextRun($schedule);
        
        $this->db->execute(
            'INSERT INTO AutoLinkSchedules 
             (Name, JobType, Schedule, TemplateId, LinksPerContent, MaxInternalLinks, CategoryIds, NextRunAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                $type->value,
                $schedule->value,
                $templateId,
                $linksPerContent,
                $maxInternalLinks,
                json_encode($categoryIds),
                $nextRun?->format('Y-m-d H:i:s')
            ]
        );
        
        $scheduleId = (int) $this->db->lastInsertId();
        
        // Schedule first WP-Cron event
        if ($nextRun !== null) {
            wp_schedule_single_event(
                $nextRun->getTimestamp(),
                self::SCHEDULE_HOOK,
                [$scheduleId]
            );
        }
        
        Logger::info('Auto-link schedule created', [
            'function' => $functionName,
            'file' => $fileName,
            'schedule_id' => $scheduleId,
            'name' => $name,
            'type' => $type->value,
            'schedule' => $schedule->value
        ]);
        
        return $this->getSchedule($scheduleId);
    }
    
    // ========== Private Helper Methods ==========
    
    private function getItemsForJob(
        AutoLinkJobType $type,
        int $maxInternalLinks,
        array $categoryIds
    ): array {
        $items = [];
        
        // Determine content types to process
        $contentTypes = match($type) {
            AutoLinkJobType::LINK_ORPHAN_POSTS => ['post'],
            AutoLinkJobType::LINK_ORPHAN_PAGES => ['page'],
            AutoLinkJobType::LINK_ALL_ORPHAN,
            AutoLinkJobType::SCHEDULED_FULL => ['post', 'page'],
            AutoLinkJobType::LINK_BY_CATEGORY => ['post', 'page'],
            AutoLinkJobType::REPROCESS_NEW_TARGETS => ['post', 'page'],
        };
        
        foreach ($contentTypes as $contentType) {
            // Find orphan content (with fewer internal links than max)
            $orphans = $this->linkingService->findOrphanContent(
                $this->mapContentType($contentType),
                $maxInternalLinks,
                $categoryIds
            );
            
            foreach ($orphans as $orphan) {
                $items[] = [
                    'type' => $contentType,
                    'id' => $orphan['wp_id'],
                    'current_links' => $orphan['internal_link_count'] ?? 0
                ];
            }
        }
        
        return $items;
    }
    
    private function queueItems(string $jobId, array $items): void
    {
        $this->db->execute('BEGIN TRANSACTION');
        
        foreach ($items as $item) {
            $this->db->execute(
                'INSERT INTO AutoLinkQueue (JobId, ContentType, ContentId, CurrentLinkCount) 
                 VALUES (?, ?, ?, ?)',
                [
                    $jobId,
                    $item['type'],
                    $item['id'],
                    $item['current_links'] ?? 0
                ]
            );
        }
        
        $this->db->execute('COMMIT');
    }
    
    private function scheduleNextBatch(string $jobId): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK, [$jobId])) {
            wp_schedule_single_event(
                time() + self::CRON_INTERVAL,
                self::CRON_HOOK,
                [$jobId]
            );
        }
    }
    
    private function completeJob(string $jobId): void
    {
        $this->updateJobStatus($jobId, JobStatus::COMPLETED, [
            'CompletedAt' => date('Y-m-d H:i:s'),
            'CurrentItem' => null
        ]);
        
        $job = $this->getJobRecord($jobId);
        
        Logger::info('Auto-linking job completed', [
            'function' => __FUNCTION__,
            'file' => __FILE__,
            'job_id' => $jobId,
            'links_created' => $job['LinksCreated'] ?? 0,
            'content_updated' => $job['ContentUpdated'] ?? 0
        ]);
        
        // Trigger completion hook for notifications
        do_action('lm_auto_link_job_completed', $jobId, $job);
    }
    
    private function calculateNextRun(AutoLinkSchedule $schedule): ?\DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        
        return match($schedule) {
            AutoLinkSchedule::ONCE => null,
            AutoLinkSchedule::HOURLY => $now->modify('+1 hour'),
            AutoLinkSchedule::TWICEDAILY => $now->modify('+12 hours'),
            AutoLinkSchedule::DAILY => $now->modify('+1 day'),
            AutoLinkSchedule::WEEKLY => $now->modify('+1 week'),
        };
    }
    
    private function mapContentType(string $type): ContentType
    {
        return match($type) {
            'post' => ContentType::POST,
            'page' => ContentType::PAGE,
            'category' => ContentType::CATEGORY,
            default => throw new \InvalidArgumentException("Unknown content type: {$type}")
        };
    }
    
    private function acquireLock(string $jobId): bool
    {
        $lockKey = "lm_autolink_lock_{$jobId}";
        if (get_transient($lockKey)) {
            return false;
        }
        set_transient($lockKey, true, 300); // 5 minute lock
        return true;
    }
    
    private function releaseLock(string $jobId): void
    {
        delete_transient("lm_autolink_lock_{$jobId}");
    }
    
    /**
     * WP-Cron event handler for batches
     */
    public function handleBatchEvent(string $jobId): void
    {
        $this->processBatch($jobId);
    }
}
```

---

## REST API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `lm/v1/auto-link/jobs` | Create new auto-linking job |
| GET | `lm/v1/auto-link/jobs` | List all jobs (with pagination) |
| GET | `lm/v1/auto-link/jobs/{id}` | Get job details |
| GET | `lm/v1/auto-link/jobs/{id}/progress` | Get job progress |
| POST | `lm/v1/auto-link/jobs/{id}/cancel` | Cancel running job |
| DELETE | `lm/v1/auto-link/jobs/{id}` | Delete job record |
| POST | `lm/v1/auto-link/schedules` | Create schedule config |
| GET | `lm/v1/auto-link/schedules` | List all schedules |
| GET | `lm/v1/auto-link/schedules/{id}` | Get schedule details |
| PUT | `lm/v1/auto-link/schedules/{id}` | Update schedule |
| DELETE | `lm/v1/auto-link/schedules/{id}` | Delete schedule |
| POST | `lm/v1/auto-link/schedules/{id}/toggle` | Enable/disable schedule |
| POST | `lm/v1/auto-link/schedules/{id}/run-now` | Run schedule immediately |
| DELETE | `lm/v1/auto-link/jobs/cleanup` | Cleanup old job records |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 14703 | ERR_CRON_AUTO_LINK_FAILED | Auto-linking batch processing failed |
| 14704 | ERR_CRON_SCHEDULE_INVALID | Invalid schedule configuration |
| 14705 | ERR_CRON_NO_ORPHAN_CONTENT | No orphan content found to process |
| 14706 | ERR_CRON_TEMPLATE_REQUIRED | Template required for scheduled job |

---

## Progress Polling

```php
<?php
declare(strict_types=1);

namespace LinkManager\Cron;

/**
 * REST endpoint for polling auto-link job progress
 */
final class AutoLinkProgressController
{
    public function __construct(
        private readonly CronAutoLinkServiceInterface $cronService
    ) {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    
    public function registerRoutes(): void
    {
        register_rest_route('lm/v1', '/auto-link/jobs/(?P<id>[a-z0-9-]+)/progress', [
            'methods' => 'GET',
            'callback' => [$this, 'getProgress'],
            'permission_callback' => [$this, 'checkPermission']
        ]);
    }
    
    public function getProgress(\WP_REST_Request $request): \WP_REST_Response
    {
        $jobId = $request->get_param('id');
        $job = $this->cronService->getJob($jobId);
        
        if (!$job) {
            return new \WP_REST_Response(['error' => 'Job not found'], 404);
        }
        
        $progress = $job->getProgress();
        
        return new \WP_REST_Response([
            'id' => $job->getId(),
            'type' => $job->getType()->value,
            'status' => $job->getStatus()->value,
            'progress' => [
                'total' => $progress->getTotal(),
                'completed' => $progress->getCompleted(),
                'percentage' => $progress->getPercentage(),
                'current_item' => $progress->getCurrentItem(),
                'links_created' => $progress->getLinksCreated(),
                'content_updated' => $progress->getContentUpdated(),
                'eta_seconds' => $progress->getEstimatedTimeRemaining()
            ],
            'started_at' => $job->getStartedAt()?->format('c'),
            'completed_at' => $job->getCompletedAt()?->format('c'),
            'errors' => $job->getErrors()
        ]);
    }
    
    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }
}
```

---

## Acceptance Criteria

**Done when:**
- [ ] One-time auto-linking jobs can be created and processed in batches
- [ ] Recurring schedules (hourly/daily/weekly) trigger job creation automatically
- [ ] Progress can be polled via REST API with real-time updates
- [ ] Jobs can be cancelled mid-execution
- [ ] Lock mechanism prevents duplicate batch processing
- [ ] Orphan content detection works correctly (finds content with < N links)
- [ ] Category filtering works for targeted auto-linking
- [ ] Error handling logs full stack traces per requirements
- [ ] Job history is maintained for audit purposes
- [ ] Old completed jobs can be cleaned up

---

## Dependencies

- `16-cron-system.md` - Base WP-Cron patterns and job management
- `21-internal-linking-service.md` - Internal linking engine
- `04-database-schema.md` - Database tables
- WordPress Cron API (`wp_schedule_single_event`, `add_action`)
