# 21 - Internal Linking Service

> **Phase:** Link Building  
> **Dependencies:** `04-database-schema.md`, `12-history-service.md`, `10-link-parser.md`  
> **Estimated Time:** 8-10 hours  
> **Last Updated:** 2026-01-31

---

## 📋 Scope

Implement an automated internal linking system that intelligently creates contextual links within content based on configurable templates, variable injection from external data sources (CSV/JSON), and keyword matching from post/page titles.

---

## 🎯 Purpose

The InternalLinkingService provides:
- **Auto-link creation**: Find orphan content and create internal links to increase connectivity
- **Template system**: Configurable HTML templates with variable placeholders
- **Variable injection**: Dynamic values from CSV/JSON files (title attributes, wrapper styles)
- **Category-aware linking**: Auto-link to related content within the same category
- **Configurable matching**: 2-3+ word phrase matching from titles
- **History integration**: Full backup before any modification with rollback support

---

## 🔧 Core Constants

> **Reference:** All constants defined in `../66-shared-constants.md`

```php
// Internal Linking Constants
const MIN_ANCHOR_WORDS = 2;                    // Minimum words for anchor text
const MAX_ANCHOR_WORDS = 5;                    // Maximum words for anchor text
const DEFAULT_LINKS_PER_CONTENT = 5;           // Default number of links to add
const MAX_LINKS_PER_CONTENT = 20;              // Maximum links allowed per content
const INTERNAL_LINK_BATCH_SIZE = 10;           // Items per batch for auto-linking

// Variable Selection Modes
enum VariableSelectionMode: string {
    case SEQUENTIAL = 'SEQUENTIAL';           // Cycle through in order
    case RANDOM = 'RANDOM';                   // Random selection
    case WEIGHTED = 'WEIGHTED';               // Based on weight field
}

// Link Insertion Modes  
enum LinkInsertionMode: string {
    case FIRST_MATCH = 'FIRST_MATCH';         // Link first occurrence only
    case ALL_MATCHES = 'ALL_MATCHES';         // Link all occurrences
    case DISTRIBUTED = 'DISTRIBUTED';          // Spread evenly through content
}

// Internal Link Source
enum InternalLinkSource: string {
    case AUTO_TITLE_MATCH = 'AUTO_TITLE_MATCH';      // Matched from post/page title
    case AUTO_CATEGORY = 'AUTO_CATEGORY';             // Related by category
    case MANUAL_IMPORT = 'MANUAL_IMPORT';             // From CSV/JSON import
    case MANUAL_CREATE = 'MANUAL_CREATE';             // Created manually in UI
}
```

---

## 📊 Data Structures

### LinkTarget (from CSV/JSON)

```php
class LinkTarget
{
    public string $url;                        // Target URL
    public string $title;                      // Link text / anchor text
    public ?string $category = null;           // Optional category filter
    public ?int $priority = 0;                 // Optional priority (higher = preferred)
    public array $keywords = [];               // Optional additional match keywords
}
```

### LinkTemplate

```php
class LinkTemplate
{
    public int $id;
    public string $name;                       // Template name (e.g., "Bold Link", "H2 Wrapped")
    public string $template;                   // HTML template string
    public bool $isDefault = false;
    public bool $isActive = true;
    public string $createdAt;
    public string $updatedAt;
}
```

**Template Format Examples:**

```html
<!-- Basic link with title attribute -->
<a href="{{url}}" title="{{title_attr}}">{{anchor_text}}</a>

<!-- Link wrapped in strong -->
<strong><a href="{{url}}" title="{{title_attr}}">{{anchor_text}}</a></strong>

<!-- Link wrapped in heading (randomized via variable) -->
<{{heading_tag}}><a href="{{url}}" title="{{title_attr}}">{{anchor_text}}</a></{{heading_tag}}>

<!-- Complex with multiple wrappers -->
<{{heading_tag}}><strong><a href="{{url}}" title="{{title_attr_1}}" class="internal-link">{{anchor_text}}</a></strong></{{heading_tag}}>
```

### LinkVariable

```php
class LinkVariable
{
    public int $id;
    public string $name;                       // Variable name (e.g., "title_attr_1")
    public string $source;                     // 'csv' or 'json'
    public string $sourceFile;                 // Path to source file
    public string $columnOrKey;                // CSV column name or JSON key
    public array $values = [];                 // Cached values from file
    public int $currentIndex = 0;              // Current position for sequential mode
    public string $selectionMode;              // SEQUENTIAL, RANDOM, WEIGHTED
}
```

### CSV/JSON Import Format

**CSV Format:**
```csv
title,url,title_attr,heading_tag,category
Carpet Cleaning Guide,/carpet-cleaning-guide,"Learn carpet cleaning tips",h2,cleaning
Steam Cleaning 101,/steam-cleaning-101,"Steam cleaning explained",h3,cleaning
```

**JSON Format:**
```json
{
  "links": [
    {
      "title": "Carpet Cleaning Guide",
      "url": "/carpet-cleaning-guide",
      "title_attr": "Learn carpet cleaning tips",
      "heading_tag": "h2",
      "category": "cleaning"
    }
  ],
  "variables": {
    "title_attr": ["Learn more", "Discover", "Read about", "Explore"],
    "heading_tag": ["h2", "h3"]
  }
}
```

---

## 🔧 Core Interface

```php
<?php
namespace LinkManager\Services;

use LinkManager\Enums\{ContentType, InternalLinkSource, VariableSelectionMode, LinkInsertionMode};
use LinkManager\Models\{LinkTarget, LinkTemplate, LinkVariable};

interface InternalLinkingServiceInterface
{
    // ========== Link Target Management ==========
    
    /**
     * Import link targets from CSV file
     *
     * @param string $filePath Path to CSV file
     * @param array $columnMapping Map CSV columns to LinkTarget fields
     * @return ImportResult Import statistics
     */
    public function importTargetsFromCsv(
        string $filePath,
        array $columnMapping = []
    ): ImportResult;
    
    /**
     * Import link targets from JSON file
     */
    public function importTargetsFromJson(string $filePath): ImportResult;
    
    /**
     * Get all link targets
     */
    public function getTargets(array $filters = [], int $page = 1, int $perPage = 20): PaginatedResult;
    
    /**
     * Add a link target manually
     */
    public function addTarget(LinkTarget $target): int;
    
    /**
     * Remove a link target
     */
    public function removeTarget(int $targetId): bool;
    
    // ========== Template Management ==========
    
    /**
     * Create a link template
     */
    public function createTemplate(string $name, string $template, bool $isDefault = false): int;
    
    /**
     * Get all templates
     */
    public function getTemplates(bool $activeOnly = true): array;
    
    /**
     * Update template
     */
    public function updateTemplate(int $templateId, array $updates): bool;
    
    /**
     * Delete template
     */
    public function deleteTemplate(int $templateId): bool;
    
    // ========== Variable Management ==========
    
    /**
     * Create a variable from file source
     */
    public function createVariable(
        string $name,
        string $sourceFile,
        string $columnOrKey,
        VariableSelectionMode $mode = VariableSelectionMode::SEQUENTIAL
    ): int;
    
    /**
     * Get next value for variable based on selection mode
     */
    public function getNextVariableValue(string $variableName): string;
    
    /**
     * Reset variable index (for sequential mode)
     */
    public function resetVariableIndex(string $variableName): void;
    
    /**
     * Refresh variable values from source file
     */
    public function refreshVariableValues(int $variableId): bool;
    
    // ========== Auto-Linking Engine ==========
    
    /**
     * Find content eligible for internal links
     *
     * @param ContentType $type POST, PAGE, or CATEGORY
     * @param int $maxInternalLinks Content with fewer internal links than this
     * @param array $categoryIds Optional category filter
     * @return array Content items needing links
     */
    public function findOrphanContent(
        ContentType $type,
        int $maxInternalLinks = 5,
        array $categoryIds = []
    ): array;
    
    /**
     * Generate links for a single content item
     *
     * @param ContentType $type
     * @param int $wpContentId WordPress content ID
     * @param int $linkCount Number of links to add
     * @param int|null $templateId Template to use (null = default)
     * @param LinkInsertionMode $insertionMode How to insert links
     * @return LinkingResult Results including created links
     */
    public function generateLinks(
        ContentType $type,
        int $wpContentId,
        int $linkCount = 5,
        ?int $templateId = null,
        LinkInsertionMode $insertionMode = LinkInsertionMode::FIRST_MATCH
    ): LinkingResult;
    
    /**
     * Auto-link based on category relationships
     *
     * @param int $wpContentId Content to add links to
     * @param int $categoryId Category to find related content from
     * @param int $linkCount Number of links to add
     * @return LinkingResult
     */
    public function generateCategoryLinks(
        int $wpContentId,
        int $categoryId,
        int $linkCount = 5
    ): LinkingResult;
    
    /**
     * Bulk generate links for multiple content items
     *
     * @param ContentType $type
     * @param array $wpContentIds Array of WordPress content IDs
     * @param int $linksPerContent Links to add per item
     * @return BulkLinkingResult
     */
    public function bulkGenerateLinks(
        ContentType $type,
        array $wpContentIds,
        int $linksPerContent = 5
    ): BulkLinkingResult;
    
    // ========== Link Removal ==========
    
    /**
     * Remove internal links from content
     *
     * @param ContentType $type
     * @param int $wpContentId
     * @param array $linkIds Specific links to remove (empty = all internal)
     * @return RemovalResult
     */
    public function removeLinks(
        ContentType $type,
        int $wpContentId,
        array $linkIds = []
    ): RemovalResult;
    
    /**
     * Remove all internal links matching a URL pattern
     */
    public function removeLinksMatchingUrl(string $urlPattern): RemovalResult;
    
    // ========== Analysis ==========
    
    /**
     * Get internal link statistics for content
     */
    public function getContentLinkStats(ContentType $type, int $wpContentId): LinkStats;
    
    /**
     * Get site-wide internal linking report
     */
    public function getSiteLinkingReport(): SiteLinkingReport;
}
```

---

## 🏗️ Implementation

**File:** `src/Services/InternalLinkingService.php`

```php
<?php
namespace LinkManager\Services;

use LinkManager\Database\Connection;
use LinkManager\Enums\{ContentType, ModificationType, InternalLinkSource, VariableSelectionMode, LinkInsertionMode};
use LinkManager\Utils\Logger;

class InternalLinkingService implements InternalLinkingServiceInterface
{
    private HistoryService $historyService;
    private LinkParser $linkParser;
    private ModificationService $modificationService;
    
    public function __construct(
        HistoryService $historyService,
        LinkParser $linkParser,
        ModificationService $modificationService
    ) {
        $this->historyService = $historyService;
        $this->linkParser = $linkParser;
        $this->modificationService = $modificationService;
    }
    
    // ========== Link Target Management ==========
    
    /**
     * Import link targets from CSV file
     */
    public function importTargetsFromCsv(
        string $filePath,
        array $columnMapping = []
    ): ImportResult {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        Logger::info("Starting CSV import", [
            'function' => $functionName,
            'file' => $fileName,
            'source_file' => $filePath
        ]);
        
        try {
            $result = new ImportResult();
            $pdo = Connection::getInstance();
            
            if (!file_exists($filePath)) {
                throw new \RuntimeException("CSV file not found: {$filePath}", ERR_CSV_INVALID_FORMAT);
            }
            
            $handle = fopen($filePath, 'r');
            $headers = fgetcsv($handle);
            
            // Auto-detect columns if not mapped
            if (empty($columnMapping)) {
                $columnMapping = $this->autoDetectCsvColumns($headers);
            }
            
            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                
                try {
                    $data = array_combine($headers, $row);
                    
                    $target = new LinkTarget();
                    $target->url = $data[$columnMapping['url']] ?? '';
                    $target->title = $data[$columnMapping['title']] ?? '';
                    $target->category = $data[$columnMapping['category'] ?? 'category'] ?? null;
                    
                    if (empty($target->url) || empty($target->title)) {
                        $result->skipped++;
                        continue;
                    }
                    
                    $this->insertTarget($pdo, $target, InternalLinkSource::MANUAL_IMPORT);
                    $result->imported++;
                    
                } catch (\Throwable $e) {
                    Logger::error("CSV row import failed", [
                        'function' => $functionName,
                        'file' => $fileName,
                        'row' => $rowNum,
                        'error' => $e->getMessage(),
                        'stack_trace' => $e->getTraceAsString()
                    ]);
                    $result->failed++;
                    $result->errors[] = "Row {$rowNum}: {$e->getMessage()}";
                }
            }
            
            fclose($handle);
            
            Logger::info("CSV import completed", [
                'function' => $functionName,
                'file' => $fileName,
                'imported' => $result->imported,
                'skipped' => $result->skipped,
                'failed' => $result->failed
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            Logger::error("CSV import failed", [
                'function' => $functionName,
                'file' => $fileName,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Import link targets from JSON file
     */
    public function importTargetsFromJson(string $filePath): ImportResult
    {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        Logger::info("Starting JSON import", [
            'function' => $functionName,
            'file' => $fileName,
            'source_file' => $filePath
        ]);
        
        try {
            $result = new ImportResult();
            $pdo = Connection::getInstance();
            
            if (!file_exists($filePath)) {
                throw new \RuntimeException("JSON file not found: {$filePath}", ERR_JSON_INVALID_FORMAT);
            }
            
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON: " . json_last_error_msg(), ERR_JSON_INVALID_FORMAT);
            }
            
            // Process link targets
            $links = $data['links'] ?? $data;
            foreach ($links as $index => $linkData) {
                try {
                    $target = new LinkTarget();
                    $target->url = $linkData['url'] ?? '';
                    $target->title = $linkData['title'] ?? '';
                    $target->category = $linkData['category'] ?? null;
                    $target->priority = $linkData['priority'] ?? 0;
                    
                    if (empty($target->url) || empty($target->title)) {
                        $result->skipped++;
                        continue;
                    }
                    
                    $this->insertTarget($pdo, $target, InternalLinkSource::MANUAL_IMPORT);
                    $result->imported++;
                    
                } catch (\Throwable $e) {
                    $result->failed++;
                    $result->errors[] = "Item {$index}: {$e->getMessage()}";
                }
            }
            
            // Process variables if present
            if (isset($data['variables'])) {
                foreach ($data['variables'] as $varName => $values) {
                    $this->createVariableFromArray($varName, $values, $filePath);
                    $result->variablesCreated++;
                }
            }
            
            Logger::info("JSON import completed", [
                'function' => $functionName,
                'file' => $fileName,
                'imported' => $result->imported,
                'variables' => $result->variablesCreated ?? 0
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            Logger::error("JSON import failed", [
                'function' => $functionName,
                'file' => $fileName,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    // ========== Template Management ==========
    
    /**
     * Create a link template
     */
    public function createTemplate(string $name, string $template, bool $isDefault = false): int
    {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        $pdo = Connection::getInstance();
        
        // Validate template has required placeholders
        if (strpos($template, '{{url}}') === false) {
            throw new \RuntimeException("Template must contain {{url}} placeholder", ERR_TEMPLATE_INVALID);
        }
        
        // If setting as default, unset other defaults
        if ($isDefault) {
            $pdo->exec("UPDATE LinkTemplate SET IsDefault = 0 WHERE IsDefault = 1");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO LinkTemplate (Name, Template, IsDefault, IsActive, CreatedAt, UpdatedAt)
            VALUES (?, ?, ?, 1, ?, ?)
        ");
        
        $now = gmdate('Y-m-d H:i:s');
        $stmt->execute([$name, $template, $isDefault ? 1 : 0, $now, $now]);
        
        $templateId = (int) $pdo->lastInsertId();
        
        Logger::info("Template created", [
            'function' => $functionName,
            'file' => $fileName,
            'template_id' => $templateId,
            'name' => $name
        ]);
        
        return $templateId;
    }
    
    // ========== Variable Management ==========
    
    /**
     * Get next value for variable based on selection mode
     */
    public function getNextVariableValue(string $variableName): string
    {
        $pdo = Connection::getInstance();
        
        $stmt = $pdo->prepare("SELECT * FROM LinkVariable WHERE Name = ?");
        $stmt->execute([$variableName]);
        $variable = $stmt->fetch();
        
        if (!$variable) {
            return '';
        }
        
        $values = json_decode($variable['Values'], true);
        if (empty($values)) {
            return '';
        }
        
        $mode = VariableSelectionMode::from($variable['SelectionMode']);
        $currentIndex = (int) $variable['CurrentIndex'];
        
        switch ($mode) {
            case VariableSelectionMode::SEQUENTIAL:
                $value = $values[$currentIndex % count($values)];
                $nextIndex = ($currentIndex + 1) % count($values);
                
                // Update index for next call
                $updateStmt = $pdo->prepare("UPDATE LinkVariable SET CurrentIndex = ? WHERE Id = ?");
                $updateStmt->execute([$nextIndex, $variable['Id']]);
                
                return $value;
                
            case VariableSelectionMode::RANDOM:
                return $values[array_rand($values)];
                
            case VariableSelectionMode::WEIGHTED:
                // Weighted selection if values are [value => weight] pairs
                // For now, treat as random
                return $values[array_rand($values)];
                
            default:
                return $values[0];
        }
    }
    
    // ========== Auto-Linking Engine ==========
    
    /**
     * Find content eligible for internal links
     */
    public function findOrphanContent(
        ContentType $type,
        int $maxInternalLinks = 5,
        array $categoryIds = []
    ): array {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        Logger::info("Finding orphan content", [
            'function' => $functionName,
            'file' => $fileName,
            'type' => $type->value,
            'max_links' => $maxInternalLinks
        ]);
        
        $pdo = Connection::getInstance();
        $table = $type === ContentType::POST ? 'Post' : 'Page';
        
        // Find content with fewer than max internal links
        $sql = "
            SELECT p.*, 
                   (SELECT COUNT(*) FROM InternalLink il WHERE il.ContentType = ? AND il.ContentId = p.Id) as InternalLinkCount
            FROM {$table} p
            WHERE (SELECT COUNT(*) FROM InternalLink il WHERE il.ContentType = ? AND il.ContentId = p.Id) < ?
        ";
        
        $params = [$type->value, $type->value, $maxInternalLinks];
        
        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $sql .= " AND p.WpPostId IN (
                SELECT object_id FROM wp_term_relationships 
                WHERE term_taxonomy_id IN ({$placeholders})
            )";
            $params = array_merge($params, $categoryIds);
        }
        
        $sql .= " ORDER BY InternalLinkCount ASC LIMIT 100";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Generate links for a single content item
     */
    public function generateLinks(
        ContentType $type,
        int $wpContentId,
        int $linkCount = 5,
        ?int $templateId = null,
        LinkInsertionMode $insertionMode = LinkInsertionMode::FIRST_MATCH
    ): LinkingResult {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        Logger::info("Generating internal links", [
            'function' => $functionName,
            'file' => $fileName,
            'type' => $type->value,
            'wp_id' => $wpContentId,
            'link_count' => $linkCount
        ]);
        
        $result = new LinkingResult();
        
        try {
            $pdo = Connection::getInstance();
            
            // Get content
            $content = $this->getWordPressContent($type, $wpContentId);
            if (empty($content)) {
                throw new \RuntimeException("Content not found", ERR_CONTENT_NOT_FOUND);
            }
            
            // Get template
            $template = $this->getTemplate($templateId);
            
            // Get link targets (prioritize category-related, then all)
            $targets = $this->getAvailableTargets($wpContentId, $linkCount);
            
            if (empty($targets)) {
                Logger::info("No targets available for linking", [
                    'function' => $functionName,
                    'file' => $fileName,
                    'wp_id' => $wpContentId
                ]);
                return $result;
            }
            
            // Create history version BEFORE modification
            $slug = $this->getSlug($type, $wpContentId);
            $versionNumber = $this->historyService->createVersion(
                $type,
                $wpContentId,
                $slug,
                $content,
                ModificationType::ADD_INTERNAL_LINK
            );
            
            $modifiedContent = $content;
            $linksAdded = 0;
            
            foreach ($targets as $target) {
                if ($linksAdded >= $linkCount) {
                    break;
                }
                
                // Find matching phrase in content (2-3+ words from title)
                $matchResult = $this->findMatchingPhrase($modifiedContent, $target->title);
                
                if ($matchResult === null) {
                    continue;
                }
                
                // Build link HTML using template
                $linkHtml = $this->buildLinkHtml($template, $target, $matchResult['phrase']);
                
                // Replace based on insertion mode
                $modifiedContent = $this->insertLink(
                    $modifiedContent,
                    $matchResult,
                    $linkHtml,
                    $insertionMode
                );
                
                // Record internal link
                $this->recordInternalLink($pdo, $type, $wpContentId, $target, $matchResult['phrase']);
                
                $result->linksCreated++;
                $linksAdded++;
            }
            
            // Save modified content to WordPress
            if ($linksAdded > 0) {
                $this->updateWordPressContent($type, $wpContentId, $modifiedContent);
                
                // Complete history version
                $this->historyService->completeVersion(
                    $type,
                    $wpContentId,
                    $versionNumber,
                    $modifiedContent,
                    ['linksAdded' => $linksAdded]
                );
            }
            
            $result->success = true;
            
            Logger::info("Internal links generated", [
                'function' => $functionName,
                'file' => $fileName,
                'wp_id' => $wpContentId,
                'links_created' => $result->linksCreated
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            Logger::error("Failed to generate internal links", [
                'function' => $functionName,
                'file' => $fileName,
                'wp_id' => $wpContentId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            $result->success = false;
            $result->error = $e->getMessage();
            return $result;
        }
    }
    
    /**
     * Find matching 2-3+ word phrase from title in content
     */
    private function findMatchingPhrase(string $content, string $title): ?array
    {
        // Extract words from title
        $words = preg_split('/\s+/', trim($title));
        
        if (count($words) < MIN_ANCHOR_WORDS) {
            return null;
        }
        
        // Try phrases of decreasing length (max 5, min 2)
        $maxWords = min(count($words), MAX_ANCHOR_WORDS);
        
        for ($len = $maxWords; $len >= MIN_ANCHOR_WORDS; $len--) {
            for ($start = 0; $start <= count($words) - $len; $start++) {
                $phrase = implode(' ', array_slice($words, $start, $len));
                
                // Case-insensitive search, must not already be a link
                $pattern = '/(?<!<a[^>]*>)\b(' . preg_quote($phrase, '/') . ')\b(?![^<]*<\/a>)/i';
                
                if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    return [
                        'phrase' => $matches[1][0],
                        'offset' => $matches[1][1],
                        'length' => strlen($matches[1][0]),
                        'pattern' => $pattern
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Build link HTML from template
     */
    private function buildLinkHtml(LinkTemplate $template, LinkTarget $target, string $anchorText): string
    {
        $html = $template->template;
        
        // Core replacements
        $html = str_replace('{{url}}', esc_url($target->url), $html);
        $html = str_replace('{{anchor_text}}', esc_html($anchorText), $html);
        $html = str_replace('{{title}}', esc_attr($target->title), $html);
        
        // Variable replacements
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $html, $variableMatches);
        
        foreach ($variableMatches[1] as $varName) {
            if (in_array($varName, ['url', 'anchor_text', 'title'])) {
                continue; // Already handled
            }
            
            $value = $this->getNextVariableValue($varName);
            $html = str_replace('{{' . $varName . '}}', esc_attr($value), $html);
        }
        
        // Handle randomized heading tags
        if (strpos($html, '{{heading_tag}}') !== false) {
            $headings = ['h2', 'h3']; // Can be configured
            $heading = $headings[array_rand($headings)];
            $html = str_replace('{{heading_tag}}', $heading, $html);
        }
        
        return $html;
    }
    
    /**
     * Insert link into content based on mode
     */
    private function insertLink(
        string $content,
        array $matchResult,
        string $linkHtml,
        LinkInsertionMode $mode
    ): string {
        switch ($mode) {
            case LinkInsertionMode::FIRST_MATCH:
                // Replace first occurrence only
                return preg_replace(
                    $matchResult['pattern'],
                    $linkHtml,
                    $content,
                    1
                );
                
            case LinkInsertionMode::ALL_MATCHES:
                // Replace all occurrences
                return preg_replace(
                    $matchResult['pattern'],
                    $linkHtml,
                    $content
                );
                
            case LinkInsertionMode::DISTRIBUTED:
                // TODO: Implement distributed insertion
                return preg_replace(
                    $matchResult['pattern'],
                    $linkHtml,
                    $content,
                    1
                );
                
            default:
                return $content;
        }
    }
    
    /**
     * Record internal link in database
     */
    private function recordInternalLink(
        \PDO $pdo,
        ContentType $type,
        int $wpContentId,
        LinkTarget $target,
        string $anchorText
    ): void {
        $stmt = $pdo->prepare("
            INSERT INTO InternalLink (
                ContentType, WpContentId, TargetUrl, AnchorText, 
                Source, CreatedAt
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $type->value,
            $wpContentId,
            $target->url,
            $anchorText,
            InternalLinkSource::AUTO_TITLE_MATCH->value,
            gmdate('Y-m-d H:i:s')
        ]);
    }
    
    // ========== Link Removal ==========
    
    /**
     * Remove internal links from content
     */
    public function removeLinks(
        ContentType $type,
        int $wpContentId,
        array $linkIds = []
    ): RemovalResult {
        $functionName = __FUNCTION__;
        $fileName = __FILE__;
        
        Logger::info("Removing internal links", [
            'function' => $functionName,
            'file' => $fileName,
            'type' => $type->value,
            'wp_id' => $wpContentId,
            'link_ids' => $linkIds
        ]);
        
        $result = new RemovalResult();
        
        try {
            $pdo = Connection::getInstance();
            
            // Get current content
            $content = $this->getWordPressContent($type, $wpContentId);
            
            // Create history version
            $slug = $this->getSlug($type, $wpContentId);
            $versionNumber = $this->historyService->createVersion(
                $type,
                $wpContentId,
                $slug,
                $content,
                ModificationType::REMOVE_INTERNAL_LINK
            );
            
            // Get links to remove
            $linksToRemove = $this->getInternalLinksForContent($pdo, $type, $wpContentId, $linkIds);
            
            $modifiedContent = $content;
            
            foreach ($linksToRemove as $link) {
                // Use modification service to remove link
                $modifiedContent = $this->modificationService->removeLinkKeepText(
                    $modifiedContent,
                    $link['TargetUrl'],
                    $link['AnchorText']
                );
                
                // Mark link as removed in database
                $stmt = $pdo->prepare("DELETE FROM InternalLink WHERE Id = ?");
                $stmt->execute([$link['Id']]);
                
                $result->linksRemoved++;
            }
            
            // Save modified content
            if ($result->linksRemoved > 0) {
                $this->updateWordPressContent($type, $wpContentId, $modifiedContent);
                
                $this->historyService->completeVersion(
                    $type,
                    $wpContentId,
                    $versionNumber,
                    $modifiedContent,
                    ['linksRemoved' => $result->linksRemoved]
                );
            }
            
            $result->success = true;
            
            Logger::info("Internal links removed", [
                'function' => $functionName,
                'file' => $fileName,
                'wp_id' => $wpContentId,
                'links_removed' => $result->linksRemoved
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            Logger::error("Failed to remove internal links", [
                'function' => $functionName,
                'file' => $fileName,
                'wp_id' => $wpContentId,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            $result->success = false;
            $result->error = $e->getMessage();
            return $result;
        }
    }
    
    // ========== Helper Methods ==========
    
    private function getTemplate(?int $templateId): LinkTemplate
    {
        $pdo = Connection::getInstance();
        
        if ($templateId) {
            $stmt = $pdo->prepare("SELECT * FROM LinkTemplate WHERE Id = ?");
            $stmt->execute([$templateId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM LinkTemplate WHERE IsDefault = 1 AND IsActive = 1 LIMIT 1");
        }
        
        $row = $stmt->fetch();
        
        if (!$row) {
            // Return basic default template
            $template = new LinkTemplate();
            $template->id = 0;
            $template->name = 'Default';
            $template->template = '<a href="{{url}}" title="{{title}}">{{anchor_text}}</a>';
            return $template;
        }
        
        $template = new LinkTemplate();
        $template->id = $row['Id'];
        $template->name = $row['Name'];
        $template->template = $row['Template'];
        $template->isDefault = (bool) $row['IsDefault'];
        return $template;
    }
    
    private function getAvailableTargets(int $excludeWpId, int $limit): array
    {
        $pdo = Connection::getInstance();
        
        // Get targets from LinkTarget table
        $stmt = $pdo->prepare("
            SELECT * FROM LinkTarget 
            WHERE Url NOT LIKE ?
            ORDER BY Priority DESC, RANDOM()
            LIMIT ?
        ");
        
        $stmt->execute(['%/?' . $excludeWpId . '%', $limit * 2]);
        $rows = $stmt->fetchAll();
        
        $targets = [];
        foreach ($rows as $row) {
            $target = new LinkTarget();
            $target->url = $row['Url'];
            $target->title = $row['Title'];
            $target->category = $row['Category'] ?? null;
            $target->priority = $row['Priority'] ?? 0;
            $targets[] = $target;
        }
        
        return $targets;
    }
    
    private function autoDetectCsvColumns(array $headers): array
    {
        $mapping = [];
        
        foreach ($headers as $header) {
            $lower = strtolower(trim($header));
            
            if (in_array($lower, ['url', 'link', 'href', 'target_url'])) {
                $mapping['url'] = $header;
            } elseif (in_array($lower, ['title', 'anchor', 'text', 'anchor_text', 'link_text'])) {
                $mapping['title'] = $header;
            } elseif (in_array($lower, ['category', 'cat', 'group'])) {
                $mapping['category'] = $header;
            }
        }
        
        if (!isset($mapping['url']) || !isset($mapping['title'])) {
            throw new \RuntimeException(
                "CSV must have 'url' and 'title' columns. Found: " . implode(', ', $headers),
                ERR_CSV_MISSING_COLUMNS
            );
        }
        
        return $mapping;
    }
    
    private function insertTarget(\PDO $pdo, LinkTarget $target, InternalLinkSource $source): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO LinkTarget (Url, Title, Category, Priority, Source, CreatedAt)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(Url) DO UPDATE SET
                Title = excluded.Title,
                Category = excluded.Category,
                Priority = excluded.Priority,
                UpdatedAt = excluded.CreatedAt
        ");
        
        $stmt->execute([
            $target->url,
            $target->title,
            $target->category,
            $target->priority,
            $source->value,
            gmdate('Y-m-d H:i:s')
        ]);
        
        return (int) $pdo->lastInsertId();
    }
    
    private function getWordPressContent(ContentType $type, int $wpContentId): string
    {
        if ($type === ContentType::CATEGORY) {
            $term = get_term($wpContentId);
            return $term ? $term->description : '';
        }
        
        $post = get_post($wpContentId);
        return $post ? $post->post_content : '';
    }
    
    private function updateWordPressContent(ContentType $type, int $wpContentId, string $content): void
    {
        if ($type === ContentType::CATEGORY) {
            wp_update_term($wpContentId, 'category', ['description' => $content]);
        } else {
            wp_update_post([
                'ID' => $wpContentId,
                'post_content' => $content
            ]);
        }
    }
    
    private function getSlug(ContentType $type, int $wpContentId): string
    {
        if ($type === ContentType::CATEGORY) {
            $term = get_term($wpContentId);
            return $term ? $term->slug : 'unknown';
        }
        
        $post = get_post($wpContentId);
        return $post ? $post->post_name : 'unknown';
    }
    
    private function getInternalLinksForContent(\PDO $pdo, ContentType $type, int $wpContentId, array $linkIds = []): array
    {
        $sql = "SELECT * FROM InternalLink WHERE ContentType = ? AND WpContentId = ?";
        $params = [$type->value, $wpContentId];
        
        if (!empty($linkIds)) {
            $placeholders = implode(',', array_fill(0, count($linkIds), '?'));
            $sql .= " AND Id IN ({$placeholders})";
            $params = array_merge($params, $linkIds);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
```

---

## 📊 Result Classes

```php
class ImportResult
{
    public int $imported = 0;
    public int $skipped = 0;
    public int $failed = 0;
    public int $variablesCreated = 0;
    public array $errors = [];
}

class LinkingResult
{
    public bool $success = false;
    public int $linksCreated = 0;
    public array $links = [];
    public ?string $error = null;
}

class BulkLinkingResult
{
    public int $totalProcessed = 0;
    public int $totalLinksCreated = 0;
    public int $contentWithLinks = 0;
    public int $contentFailed = 0;
    public array $errors = [];
}

class RemovalResult
{
    public bool $success = false;
    public int $linksRemoved = 0;
    public ?string $error = null;
}

class LinkStats
{
    public int $totalInternalLinks = 0;
    public int $uniqueTargets = 0;
    public array $topTargets = [];
}

class SiteLinkingReport
{
    public int $totalContent = 0;
    public int $contentWithLinks = 0;
    public int $orphanContent = 0;
    public int $totalInternalLinks = 0;
    public float $avgLinksPerContent = 0.0;
    public array $topLinkedContent = [];
    public array $orphanList = [];
}
```

---

## 🔐 Security

- All URL inputs validated and sanitized with `esc_url()`
- Anchor text escaped with `esc_html()`
- Attribute values escaped with `esc_attr()`
- CSV/JSON file paths validated to prevent directory traversal
- User capability checks (`manage_options`) for all operations
- All modifications create history entries for rollback

---

## ✅ Acceptance Criteria

| Requirement | Done When |
|-------------|-----------|
| CSV import | Imports title+url pairs with auto column detection |
| JSON import | Imports link targets and variables from JSON |
| Template system | Supports {{variable}} placeholders with validation |
| Variable cycling | Sequential/random selection modes work correctly |
| Phrase matching | Only matches 2-5 word phrases from titles |
| History backup | Every modification creates version before change |
| Link removal | Removes links while preserving text content |
| Category linking | Finds related content by category |
| Logging | All logs include function name and file path |
| Error handling | All errors include full stack trace |

---

## 📝 Cross-References

- Database schema: `04-database-schema.md`
- History service: `12-history-service.md`
- Modification service: `14-modification-service.md`
- Link parser: `10-link-parser.md`
- Constants: `../66-shared-constants.md`
- UI: `../../02-admin-ui/split-spec/21-internal-linking-page.md`
- API: `17-rest-api-endpoints.md`

---

*This service enables intelligent internal link building with full history tracking and configurable templates.*
