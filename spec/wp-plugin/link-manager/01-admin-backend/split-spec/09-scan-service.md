# 09 - Scan Service

> **Phase:** Core Engine  
> **Dependencies:** `04-database-schema.md`, `08-entity-models.md`  
> **Estimated Time:** 6-8 hours  
> **Last Updated:** 2026-01-31

---

## 📋 Scope

Implement the core link scanning engine that discovers, categorizes, and stores all hyperlinks from WordPress posts, pages, and category descriptions.

---

## 🎯 Purpose

The ScanService orchestrates the link discovery process across all content types, supporting:
- **All Links Mode**: Discover every hyperlink in content
- **Broken Links Only**: Only scan and verify broken links
- **CSV Import Mode**: Process links from uploaded CSV file

---

## 🔧 Core Interface

```php
<?php
namespace LinkManager\Services;

interface ScanServiceInterface
{
    /**
     * Start a new scan operation
     *
     * @param ScanMode $mode Scan mode (ALL_LINKS, BROKEN_ONLY, CSV_IMPORT)
     * @param array $contentTypes Content types to scan ['POST', 'PAGE', 'CATEGORY']
     * @param int|null $userId WordPress user ID who initiated scan
     * @return ScanHistory The created scan history record
     */
    public function startScan(
        ScanMode $mode,
        array $contentTypes = [ContentType::POST, ContentType::PAGE],
        ?int $userId = null
    ): ScanHistory;
    
    /**
     * Process a batch of content items
     *
     * @param int $scanHistoryId Active scan ID
     * @param int $batchSize Number of items per batch
     * @return bool True if more items remain
     */
    public function processBatch(int $scanHistoryId, int $batchSize = 50): bool;
    
    /**
     * Get current scan status
     */
    public function getScanStatus(int $scanHistoryId): array;
    
    /**
     * Cancel a running scan
     */
    public function cancelScan(int $scanHistoryId): bool;
    
    /**
     * Rescan specific content
     *
     * @param ContentType $type POST, PAGE, or CATEGORY
     * @param int $wpContentId WordPress content ID
     */
    public function rescanContent(ContentType $type, int $wpContentId): void;
}
```

---

## 🏗️ Implementation

**File:** `src/Services/ScanService.php`

```php
<?php
namespace LinkManager\Services;

use LinkManager\Database\Connection;
use LinkManager\Database\Models\{Post, Page, Category, Link, ScanHistory};
use LinkManager\Enums\{ScanMode, ScanStatus, ContentType, LinkStatus};
use LinkManager\Utils\Logger;

class ScanService implements ScanServiceInterface
{
    private LinkParser $linkParser;
    private HttpChecker $httpChecker;
    private ElementorParser $elementorParser;
    
    public function __construct(
        LinkParser $linkParser,
        HttpChecker $httpChecker,
        ElementorParser $elementorParser
    ) {
        $this->linkParser = $linkParser;
        $this->httpChecker = $httpChecker;
        $this->elementorParser = $elementorParser;
    }
    
    /**
     * Start a new scan operation
     */
    public function startScan(
        ScanMode $mode,
        array $contentTypes = [ContentType::POST, ContentType::PAGE],
        ?int $userId = null
    ): ScanHistory {
        // Check if scan already running
        $existingRunning = ScanHistory::findRunning();
        if ($existingRunning !== null) {
            Logger::warning('Scan already running', ['existing_id' => $existingRunning->id]);
            throw new \RuntimeException('A scan is already in progress', 14100);
        }
        
        // Count total items to scan
        $totalItems = $this->countContentItems($contentTypes);
        if ($totalItems === 0) {
            throw new \RuntimeException('No content to scan', 14101);
        }
        
        // Create scan history record
        $scanHistory = ScanHistory::create([
            'ScanMode' => $mode->value,
            'ContentTypes' => json_encode(array_map(fn($t) => $t->value, $contentTypes)),
            'Status' => ScanStatus::RUNNING->value,
            'TotalItems' => $totalItems,
            'ProcessedItems' => 0,
            'StartedAt' => gmdate('Y-m-d H:i:s'),
            'InitiatedBy' => $userId,
            'IsCronJob' => $userId === null,
        ]);
        
        Logger::info('Scan started', [
            'scan_id' => $scanHistory->id,
            'mode' => $mode->value,
            'total_items' => $totalItems
        ]);
        
        return $scanHistory;
    }
    
    /**
     * Process a batch of content items
     */
    public function processBatch(int $scanHistoryId, int $batchSize = 50): bool
    {
        $scanHistory = ScanHistory::find($scanHistoryId);
        
        // Validate scan is running
        if ($scanHistory === null || $scanHistory->status !== ScanStatus::RUNNING->value) {
            return false;
        }
        
        $contentTypes = json_decode($scanHistory->contentTypes, true);
        $offset = $scanHistory->processedItems;
        
        try {
            $itemsProcessed = 0;
            $linksFound = 0;
            $brokenFound = 0;
            
            // Process each content type
            foreach ($contentTypes as $typeValue) {
                $type = ContentType::from($typeValue);
                $items = $this->getContentBatch($type, $offset, $batchSize);
                
                foreach ($items as $item) {
                    $result = $this->scanContentItem($type, $item, $scanHistoryId);
                    $itemsProcessed++;
                    $linksFound += $result['total'];
                    $brokenFound += $result['broken'];
                }
            }
            
            // Update progress
            $scanHistory->update([
                'ProcessedItems' => $offset + $itemsProcessed,
                'TotalLinksFound' => $scanHistory->totalLinksFound + $linksFound,
                'BrokenLinksFound' => $scanHistory->brokenLinksFound + $brokenFound,
            ]);
            
            // Check if complete
            $hasMore = ($offset + $itemsProcessed) < $scanHistory->totalItems;
            
            if (!$hasMore) {
                $this->completeScan($scanHistoryId);
            }
            
            return $hasMore;
            
        } catch (\Throwable $e) {
            Logger::error('Batch processing failed', [
                'file' => __FILE__,
                'action' => 'processBatch',
                'scan_id' => $scanHistoryId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            $this->failScan($scanHistoryId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Scan a single content item
     */
    private function scanContentItem(
        ContentType $type,
        array $wpContent,
        int $scanHistoryId
    ): array {
        $result = ['total' => 0, 'broken' => 0];
        
        try {
            // Get content based on type
            $content = match ($type) {
                ContentType::POST, ContentType::PAGE => $wpContent['post_content'],
                ContentType::CATEGORY => $wpContent['description'] ?? '',
            };
            
            $wpId = match ($type) {
                ContentType::POST, ContentType::PAGE => $wpContent['ID'],
                ContentType::CATEGORY => $wpContent['term_id'],
            };
            
            // Parse content for links
            $links = $this->linkParser->parse($content);
            
            // Check for Elementor content
            if ($type !== ContentType::CATEGORY) {
                $elementorData = get_post_meta($wpId, '_elementor_data', true);
                if (!empty($elementorData)) {
                    $elementorLinks = $this->elementorParser->extractLinks($elementorData);
                    $links = array_merge($links, $elementorLinks);
                }
            }
            
            // Parse JSON-LD if present
            $jsonLdLinks = $this->linkParser->parseJsonLd($content);
            $links = array_merge($links, $jsonLdLinks);
            
            // Check link status (parallel HTTP requests)
            $linksWithStatus = $this->httpChecker->checkLinks($links);
            
            // Store or update content record
            $contentRecord = $this->upsertContentRecord($type, $wpContent);
            
            // Store links
            foreach ($linksWithStatus as $index => $linkData) {
                $this->storeLink($type, $contentRecord->id, $wpId, $linkData, $index, $scanHistoryId);
                $result['total']++;
                
                if ($linkData['status'] === LinkStatus::BROKEN->value) {
                    $result['broken']++;
                }
            }
            
            // Update content record counts
            $this->updateContentCounts($type, $contentRecord->id);
            
            // Check for broken HTML
            $hasBrokenHtml = $this->linkParser->detectBrokenHtml($content);
            $contentRecord->update(['HasBrokenHtml' => $hasBrokenHtml ? 1 : 0]);
            
            return $result;
            
        } catch (\Throwable $e) {
            Logger::error('Content scan failed', [
                'file' => __FILE__,
                'action' => 'scanContentItem',
                'type' => $type->value,
                'wp_id' => $wpId ?? 'unknown',
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            return $result;
        }
    }
    
    /**
     * Store discovered link
     */
    private function storeLink(
        ContentType $type,
        int $contentId,
        int $wpContentId,
        array $linkData,
        int $positionIndex,
        int $scanHistoryId
    ): Link {
        return Link::create([
            'ContentType' => $type->value,
            'ContentId' => $contentId,
            'WpContentId' => $wpContentId,
            'Url' => $linkData['url'],
            'AnchorText' => $linkData['anchorText'] ?? null,
            'TitleAttribute' => $linkData['title'] ?? null,
            'Status' => $linkData['status'],
            'HttpStatusCode' => $linkData['statusCode'] ?? null,
            'LastCheckedAt' => gmdate('Y-m-d H:i:s'),
            'LinkSource' => $linkData['source'],
            'WordCount' => $this->categorizeWordCount($linkData['anchorText'] ?? ''),
            'WrapperTags' => json_encode($linkData['wrappers'] ?? []),
            'HasHeadingWrapper' => $this->hasHeadingWrapper($linkData['wrappers'] ?? []),
            'HasEmphasisWrapper' => $this->hasEmphasisWrapper($linkData['wrappers'] ?? []),
            'PositionIndex' => $positionIndex,
            'ElementorWidgetId' => $linkData['elementorWidgetId'] ?? null,
            'ScanHistoryId' => $scanHistoryId,
        ]);
    }
    
    /**
     * Categorize anchor text word count
     */
    private function categorizeWordCount(string $text): string
    {
        $words = str_word_count(trim($text));
        
        return match (true) {
            $words <= 1 => 'ONE_WORD',
            $words === 2 => 'TWO_WORDS',
            default => 'THREE_PLUS',
        };
    }
    
    /**
     * Check if wrappers include heading tags
     */
    private function hasHeadingWrapper(array $wrappers): bool
    {
        $headings = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'];
        return count(array_intersect($wrappers, $headings)) > 0;
    }
    
    /**
     * Check if wrappers include emphasis tags
     */
    private function hasEmphasisWrapper(array $wrappers): bool
    {
        $emphasis = ['STRONG', 'EM', 'B', 'I'];
        return count(array_intersect($wrappers, $emphasis)) > 0;
    }
    
    /**
     * Complete scan successfully
     */
    private function completeScan(int $scanHistoryId): void
    {
        $scanHistory = ScanHistory::find($scanHistoryId);
        $startedAt = strtotime($scanHistory->startedAt);
        $duration = time() - $startedAt;
        
        $scanHistory->update([
            'Status' => ScanStatus::COMPLETED->value,
            'CompletedAt' => gmdate('Y-m-d H:i:s'),
            'DurationSeconds' => $duration,
        ]);
        
        Logger::info('Scan completed', [
            'scan_id' => $scanHistoryId,
            'duration_seconds' => $duration,
            'total_links' => $scanHistory->totalLinksFound,
            'broken_links' => $scanHistory->brokenLinksFound
        ]);
        
        // Send notification if enabled
        $this->sendCompletionNotification($scanHistory);
    }
    
    /**
     * Mark scan as failed
     */
    private function failScan(int $scanHistoryId, string $error): void
    {
        $scanHistory = ScanHistory::find($scanHistoryId);
        
        $scanHistory->update([
            'Status' => ScanStatus::FAILED->value,
            'CompletedAt' => gmdate('Y-m-d H:i:s'),
            'ErrorMessage' => $error,
        ]);
        
        Logger::error('Scan failed', [
            'scan_id' => $scanHistoryId,
            'error' => $error
        ]);
    }
    
    /**
     * Get scan status
     */
    public function getScanStatus(int $scanHistoryId): array
    {
        $scanHistory = ScanHistory::find($scanHistoryId);
        
        if ($scanHistory === null) {
            return ['exists' => false];
        }
        
        $progress = $scanHistory->totalItems > 0
            ? round(($scanHistory->processedItems / $scanHistory->totalItems) * 100, 1)
            : 0;
        
        return [
            'exists' => true,
            'id' => $scanHistory->id,
            'status' => $scanHistory->status,
            'mode' => $scanHistory->scanMode,
            'progress' => $progress,
            'processedItems' => $scanHistory->processedItems,
            'totalItems' => $scanHistory->totalItems,
            'totalLinksFound' => $scanHistory->totalLinksFound,
            'brokenLinksFound' => $scanHistory->brokenLinksFound,
            'startedAt' => $scanHistory->startedAt,
            'completedAt' => $scanHistory->completedAt,
            'error' => $scanHistory->errorMessage,
        ];
    }
    
    /**
     * Cancel running scan
     */
    public function cancelScan(int $scanHistoryId): bool
    {
        $scanHistory = ScanHistory::find($scanHistoryId);
        
        if ($scanHistory === null || $scanHistory->status !== ScanStatus::RUNNING->value) {
            return false;
        }
        
        $scanHistory->update([
            'Status' => ScanStatus::CANCELLED->value,
            'CompletedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        
        Logger::info('Scan cancelled', ['scan_id' => $scanHistoryId]);
        return true;
    }
    
    /**
     * Rescan specific content
     */
    public function rescanContent(ContentType $type, int $wpContentId): void
    {
        Logger::info('Rescan requested', [
            'type' => $type->value,
            'wp_id' => $wpContentId
        ]);
        
        // Delete existing links for this content
        Link::deleteByContent($type, $wpContentId);
        
        // Get content from WordPress
        $wpContent = $this->getWpContent($type, $wpContentId);
        
        if ($wpContent === null) {
            throw new \RuntimeException('Content not found', 14101);
        }
        
        // Create temporary scan for tracking
        $scanHistory = ScanHistory::create([
            'ScanMode' => ScanMode::ALL_LINKS->value,
            'ContentTypes' => json_encode([$type->value]),
            'Status' => ScanStatus::RUNNING->value,
            'TotalItems' => 1,
            'ProcessedItems' => 0,
            'StartedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        
        // Scan the content
        $this->scanContentItem($type, $wpContent, $scanHistory->id);
        
        // Complete
        $this->completeScan($scanHistory->id);
    }
    
    // ... Additional helper methods
}
```

---

## 🔄 Scan Flow Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           SCAN FLOW                                       │
└──────────────────────────────────────────────────────────────────────────┘

     User/Cron                   ScanService                    Database
         │                            │                             │
         │    startScan(mode)         │                             │
         │───────────────────────────>│                             │
         │                            │    Check existing scan      │
         │                            │────────────────────────────>│
         │                            │<────────────────────────────│
         │                            │                             │
         │                            │    Create ScanHistory       │
         │                            │────────────────────────────>│
         │                            │<────────────────────────────│
         │<───────────────────────────│                             │
         │    ScanHistory             │                             │
         │                            │                             │
    ┌────┴────┐                       │                             │
    │ Cron or │                       │                             │
    │  Loop   │    processBatch()     │                             │
    └────┬────┘───────────────────────>│                             │
         │                            │    Get content batch        │
         │                            │─────────────────────────────>│
         │                            │<─────────────────────────────│
         │                            │                             │
         │                            │    For each content:        │
         │                            │    ┌──────────────────┐     │
         │                            │    │ Parse HTML       │     │
         │                            │    │ Parse Elementor  │     │
         │                            │    │ Parse JSON-LD    │     │
         │                            │    │ Check URLs       │     │
         │                            │    │ Store Links      │     │
         │                            │    └──────────────────┘     │
         │                            │                             │
         │                            │    Update progress          │
         │                            │────────────────────────────>│
         │<───────────────────────────│                             │
         │    hasMore: true/false     │                             │
         │                            │                             │
         │    (repeat until done)     │                             │
         │                            │                             │
```

---

## ✅ Acceptance Criteria

### Scan Initialization
- [ ] Prevents multiple simultaneous scans
- [ ] Correctly counts content items
- [ ] Creates accurate ScanHistory record
- [ ] Logs scan start with all parameters

### Batch Processing
- [ ] Processes configured batch size
- [ ] Updates progress after each batch
- [ ] Handles errors gracefully per item
- [ ] Continues after individual failures

### Link Discovery
- [ ] Finds links in post content
- [ ] Finds links in Elementor data
- [ ] Finds links in JSON-LD
- [ ] Detects wrapper tags (H1-H6, strong, em)
- [ ] Categorizes word count correctly

### Link Verification
- [ ] Parallel HTTP requests (configurable limit)
- [ ] Respects timeout settings
- [ ] Handles redirects correctly
- [ ] Categorizes status accurately

### Error Handling
- [ ] Full stack traces in logs
- [ ] Graceful degradation on failures
- [ ] Scan marked as failed on unrecoverable errors
- [ ] Individual content errors don't stop scan

---

## 📝 Related Specifications

- `10-link-parser.md` - HTML parsing logic
- `11-elementor-integration.md` - Elementor content handling
- `16-cron-system.md` - Background scan jobs
- `66-shared-constants.md` - Configuration values

---

*All logging follows the pattern from `07-logging-system.md`*
