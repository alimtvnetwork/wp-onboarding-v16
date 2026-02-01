# 16 - Cron System (Background Scanning)

> **Status:** Complete  
> **Priority:** Medium  
> **Updated:** 2026-01-31

---

## Purpose

Enables background link scanning using WordPress Cron (WP-Cron) so users don't need to keep the browser open. Supports scheduled scans, batched processing, and progress tracking.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                     WordPress Cron System                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐             │
│  │ User clicks │───▶│ Create Job  │───▶│ Schedule    │             │
│  │ "Scan All"  │    │   Record    │    │ WP-Cron     │             │
│  └─────────────┘    └─────────────┘    └──────┬──────┘             │
│                                               │                     │
│                                               ▼                     │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐             │
│  │   Update    │◀───│Process Batch│◀───│ Cron fires  │             │
│  │  Progress   │    │ (20 items)  │    │ (1 min)     │             │
│  └─────────────┘    └──────┬──────┘    └─────────────┘             │
│                            │                                        │
│                            ▼                                        │
│                     ┌─────────────┐                                 │
│                     │ More items? │──Yes──▶ Re-schedule             │
│                     └──────┬──────┘                                 │
│                            │ No                                     │
│                            ▼                                        │
│                     ┌─────────────┐                                 │
│                     │  Complete   │                                 │
│                     │    Job      │                                 │
│                     └─────────────┘                                 │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Core Interfaces

```php
<?php
declare(strict_types=1);

namespace LinkManager\Cron;

use LinkManager\Enum\JobStatus;
use LinkManager\Enum\JobType;

/**
 * Represents a background scan job
 */
interface ScanJobInterface
{
    public function getId(): string;
    public function getType(): JobType;
    public function getStatus(): JobStatus;
    public function getProgress(): JobProgressInterface;
    public function getCreatedAt(): \DateTimeImmutable;
    public function getStartedAt(): ?\DateTimeImmutable;
    public function getCompletedAt(): ?\DateTimeImmutable;
    public function getErrors(): array;
}

/**
 * Job progress tracking
 */
interface JobProgressInterface
{
    public function getTotal(): int;
    public function getCompleted(): int;
    public function getPercentage(): float;
    public function getCurrentItem(): ?string;
    public function getLinksFound(): int;
    public function getBrokenFound(): int;
    public function getEstimatedTimeRemaining(): ?int; // seconds
}

/**
 * Main cron service
 */
interface CronServiceInterface
{
    /**
     * Create and schedule a new scan job
     */
    public function createJob(JobType $type, array $options = []): ScanJobInterface;
    
    /**
     * Get job status
     */
    public function getJob(string $jobId): ?ScanJobInterface;
    
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
}
```

---

## Job Types Enum

```php
<?php
declare(strict_types=1);

namespace LinkManager\Enum;

enum JobType: string
{
    case SCAN_ALL = 'scan_all';
    case SCAN_BROKEN = 'scan_broken';
    case SCAN_POSTS = 'scan_posts';
    case SCAN_PAGES = 'scan_pages';
    case SCAN_CATEGORIES = 'scan_categories';
    case RECHECK_BROKEN = 'recheck_broken';
}

enum JobStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
```

---

## Database Schema

```sql
-- Jobs table (in link-manager.db)
CREATE TABLE IF NOT EXISTS ScanJobs (
    Id TEXT PRIMARY KEY,
    Type TEXT NOT NULL,
    Status TEXT NOT NULL DEFAULT 'pending',
    TotalItems INTEGER NOT NULL DEFAULT 0,
    CompletedItems INTEGER NOT NULL DEFAULT 0,
    CurrentItem TEXT DEFAULT NULL,
    LinksFound INTEGER NOT NULL DEFAULT 0,
    BrokenFound INTEGER NOT NULL DEFAULT 0,
    Options TEXT DEFAULT NULL, -- JSON
    Errors TEXT DEFAULT NULL,  -- JSON array
    CreatedAt TEXT NOT NULL DEFAULT (datetime('now')),
    StartedAt TEXT DEFAULT NULL,
    CompletedAt TEXT DEFAULT NULL,
    LastActivityAt TEXT DEFAULT NULL
);

CREATE INDEX idx_jobs_status ON ScanJobs(Status);
CREATE INDEX idx_jobs_created ON ScanJobs(CreatedAt DESC);

-- Job queue for batch processing
CREATE TABLE IF NOT EXISTS JobQueue (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    JobId TEXT NOT NULL,
    ItemType TEXT NOT NULL,      -- 'post', 'page', 'category'
    ItemId INTEGER NOT NULL,
    Processed INTEGER NOT NULL DEFAULT 0,
    ProcessedAt TEXT DEFAULT NULL,
    FOREIGN KEY (JobId) REFERENCES ScanJobs(Id) ON DELETE CASCADE
);

CREATE INDEX idx_queue_job ON JobQueue(JobId, Processed);
```

---

## Cron Service Implementation

```php
<?php
declare(strict_types=1);

namespace LinkManager\Cron;

final class CronService implements CronServiceInterface
{
    private const CRON_HOOK = 'lm_process_scan_batch';
    private const BATCH_SIZE = 20;
    private const CRON_INTERVAL = 60; // 1 minute
    
    public function __construct(
        private readonly ConnectionManager $db,
        private readonly ScanServiceInterface $scanner,
        private readonly LoggerInterface $logger
    ) {
        // Register cron hook
        add_action(self::CRON_HOOK, [$this, 'handleCronEvent']);
    }
    
    public function createJob(JobType $type, array $options = []): ScanJobInterface
    {
        $jobId = wp_generate_uuid4();
        
        // Determine items to process
        $items = $this->getItemsForJob($type, $options);
        $totalItems = count($items);
        
        // Create job record
        $this->db->execute(
            'INSERT INTO ScanJobs (Id, Type, Status, TotalItems, Options)
             VALUES (?, ?, ?, ?, ?)',
            [
                $jobId,
                $type->value,
                JobStatus::PENDING->value,
                $totalItems,
                json_encode($options)
            ]
        );
        
        // Queue items
        $this->queueItems($jobId, $items);
        
        // Schedule first cron event
        $this->scheduleNextBatch($jobId);
        
        $this->logger->info('Scan job created', [
            'job_id' => $jobId,
            'type' => $type->value,
            'total_items' => $totalItems
        ]);
        
        return $this->getJob($jobId);
    }
    
    public function processBatch(string $jobId): void
    {
        $job = $this->getJobRecord($jobId);
        
        if (!$job || $job['Status'] === JobStatus::CANCELLED->value) {
            return;
        }
        
        // Update status to running
        if ($job['Status'] === JobStatus::PENDING->value) {
            $this->updateJobStatus($jobId, JobStatus::RUNNING, [
                'StartedAt' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Get next batch of items
        $items = $this->db->query(
            'SELECT * FROM JobQueue 
             WHERE JobId = ? AND Processed = 0 
             LIMIT ?',
            [$jobId, self::BATCH_SIZE]
        );
        
        $processedCount = 0;
        $linksFound = 0;
        $brokenFound = 0;
        $errors = [];
        
        while ($item = $items->fetchArray(SQLITE3_ASSOC)) {
            try {
                // Update current item
                $this->db->execute(
                    'UPDATE ScanJobs SET CurrentItem = ?, LastActivityAt = datetime("now") 
                     WHERE Id = ?',
                    [$this->getItemTitle($item), $jobId]
                );
                
                // Scan the item
                $result = $this->scanner->scanItem(
                    $item['ItemType'],
                    $item['ItemId']
                );
                
                $linksFound += $result->getLinkCount();
                $brokenFound += $result->getBrokenCount();
                
                // Mark as processed
                $this->db->execute(
                    'UPDATE JobQueue SET Processed = 1, ProcessedAt = datetime("now") 
                     WHERE Id = ?',
                    [$item['Id']]
                );
                
                $processedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'item' => $item['ItemId'],
                    'type' => $item['ItemType'],
                    'error' => $e->getMessage()
                ];
                
                $this->logger->error('Batch item failed', [
                    'job_id' => $jobId,
                    'item_id' => $item['ItemId'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Update job progress
        $this->updateJobProgress($jobId, $processedCount, $linksFound, $brokenFound, $errors);
        
        // Check if complete
        $remaining = $this->getRemainingCount($jobId);
        
        if ($remaining === 0) {
            $this->completeJob($jobId);
        } else {
            // Schedule next batch
            $this->scheduleNextBatch($jobId);
        }
    }
    
    public function cancelJob(string $jobId): bool
    {
        $this->updateJobStatus($jobId, JobStatus::CANCELLED);
        
        // Remove scheduled cron events
        wp_clear_scheduled_hook(self::CRON_HOOK, [$jobId]);
        
        $this->logger->info('Job cancelled', ['job_id' => $jobId]);
        
        return true;
    }
    
    private function scheduleNextBatch(string $jobId): void
    {
        // Schedule for immediate execution (or 1 minute if rate limited)
        if (!wp_next_scheduled(self::CRON_HOOK, [$jobId])) {
            wp_schedule_single_event(
                time() + self::CRON_INTERVAL,
                self::CRON_HOOK,
                [$jobId]
            );
        }
    }
    
    private function getItemsForJob(JobType $type, array $options): array
    {
        global $wpdb;
        
        $items = [];
        
        $postTypes = match($type) {
            JobType::SCAN_POSTS => ['post'],
            JobType::SCAN_PAGES => ['page'],
            JobType::SCAN_ALL => ['post', 'page'],
            default => ['post', 'page']
        };
        
        foreach ($postTypes as $postType) {
            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                     WHERE post_type = %s 
                     AND post_status = 'publish'",
                    $postType
                ),
                ARRAY_A
            );
            
            foreach ($posts as $post) {
                $items[] = [
                    'type' => $postType,
                    'id' => (int)$post['ID']
                ];
            }
        }
        
        // Include categories if scanning all
        if ($type === JobType::SCAN_ALL || $type === JobType::SCAN_CATEGORIES) {
            $categories = get_categories(['hide_empty' => false]);
            foreach ($categories as $cat) {
                $items[] = [
                    'type' => 'category',
                    'id' => $cat->term_id
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
                'INSERT INTO JobQueue (JobId, ItemType, ItemId) VALUES (?, ?, ?)',
                [$jobId, $item['type'], $item['id']]
            );
        }
        
        $this->db->execute('COMMIT');
    }
    
    private function completeJob(string $jobId): void
    {
        $this->updateJobStatus($jobId, JobStatus::COMPLETED, [
            'CompletedAt' => date('Y-m-d H:i:s'),
            'CurrentItem' => null
        ]);
        
        $this->logger->info('Scan job completed', ['job_id' => $jobId]);
        
        // Trigger completion hook for notifications
        do_action('lm_scan_job_completed', $jobId);
    }
    
    /**
     * WP-Cron event handler
     */
    public function handleCronEvent(string $jobId): void
    {
        $this->processBatch($jobId);
    }
}
```

---

## Progress Polling

```php
<?php
declare(strict_types=1);

namespace LinkManager\Cron;

/**
 * REST endpoint for polling job progress
 */
final class JobProgressController
{
    public function __construct(
        private readonly CronServiceInterface $cronService
    ) {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    
    public function registerRoutes(): void
    {
        register_rest_route('lm/v1', '/jobs/(?P<id>[a-z0-9-]+)/progress', [
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
            'status' => $job->getStatus()->value,
            'progress' => [
                'total' => $progress->getTotal(),
                'completed' => $progress->getCompleted(),
                'percentage' => $progress->getPercentage(),
                'current_item' => $progress->getCurrentItem(),
                'links_found' => $progress->getLinksFound(),
                'broken_found' => $progress->getBrokenFound(),
                'eta_seconds' => $progress->getEstimatedTimeRemaining()
            ],
            'started_at' => $job->getStartedAt()?->format('c'),
            'errors' => $job->getErrors()
        ]);
    }
}
```

---

## Scheduled Scans

```php
<?php
declare(strict_types=1);

namespace LinkManager\Cron;

/**
 * Handles scheduled recurring scans
 */
final class ScheduledScanService
{
    private const SCHEDULE_HOOK = 'lm_scheduled_scan';
    
    public function __construct(
        private readonly CronServiceInterface $cronService,
        private readonly SettingsServiceInterface $settings
    ) {
        add_action(self::SCHEDULE_HOOK, [$this, 'runScheduledScan']);
    }
    
    /**
     * Enable/disable scheduled scans
     */
    public function setSchedule(?string $frequency): void
    {
        // Clear existing schedule
        wp_clear_scheduled_hook(self::SCHEDULE_HOOK);
        
        if ($frequency && in_array($frequency, ['daily', 'weekly', 'monthly'])) {
            wp_schedule_event(
                $this->getNextRunTime($frequency),
                $frequency,
                self::SCHEDULE_HOOK
            );
            
            $this->settings->set('scheduled_scan_frequency', $frequency);
        } else {
            $this->settings->delete('scheduled_scan_frequency');
        }
    }
    
    public function runScheduledScan(): void
    {
        // Create a scan job for broken links only (lighter)
        $this->cronService->createJob(JobType::RECHECK_BROKEN);
    }
    
    private function getNextRunTime(string $frequency): int
    {
        // Schedule for 3 AM local time
        $hour = 3;
        $now = current_time('timestamp');
        $today3am = strtotime("today {$hour}:00", $now);
        
        if ($today3am < $now) {
            return strtotime("tomorrow {$hour}:00", $now);
        }
        
        return $today3am;
    }
}
```

---

## REST API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `lm/v1/jobs` | Create new scan job |
| GET | `lm/v1/jobs` | List all jobs |
| GET | `lm/v1/jobs/{id}` | Get job details |
| GET | `lm/v1/jobs/{id}/progress` | Poll job progress |
| POST | `lm/v1/jobs/{id}/cancel` | Cancel running job |
| GET | `lm/v1/schedule` | Get scan schedule |
| PUT | `lm/v1/schedule` | Set scan schedule |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 14700 | ERR_CRON_JOB_NOT_FOUND | Job ID not found |
| 14701 | ERR_CRON_JOB_ALREADY_RUNNING | Another job is already running |
| 14702 | ERR_CRON_SCHEDULE_FAILED | Failed to schedule cron event |
| 14703 | ERR_CRON_BATCH_FAILED | Batch processing failed |
| 14704 | ERR_CRON_CANCEL_FAILED | Failed to cancel job |

---

## Acceptance Criteria

**Done when:**
- [ ] Jobs run in background without browser
- [ ] Progress updates every batch (20 items)
- [ ] Polling endpoint returns real-time progress
- [ ] Job survives page refresh
- [ ] Cancel stops processing immediately
- [ ] Scheduled scans run at configured frequency
- [ ] Failed items don't block entire job
- [ ] Completion notification fires

---

## Dependencies

- `09-scan-service.md` - Actual scanning logic
- `04-database-schema.md` - Job storage
- WordPress Cron API
