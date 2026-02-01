# 15 - CSV Import Service

> **Status:** Complete  
> **Priority:** Medium  
> **Updated:** 2026-01-31

---

## Purpose

Handles importing broken link data from external SEO tools (Screaming Frog, Ahrefs, SEMrush, etc.) via CSV files. Allows users to populate the link database without running a full scan.

---

## Supported CSV Formats

### Auto-Detected Columns

The import service attempts to match common column names:

| Data Type | Accepted Column Names |
|-----------|----------------------|
| Broken URL | `url`, `broken_url`, `link_url`, `destination`, `href`, `target_url` |
| Source Page | `source`, `source_url`, `source_page`, `page_url`, `from`, `article_url` |
| Status Code | `status`, `status_code`, `http_status`, `response_code`, `code` |
| Anchor Text | `anchor`, `anchor_text`, `link_text`, `text` |

---

## Core Interfaces

```php
<?php
declare(strict_types=1);

namespace LinkManager\Import;

/**
 * Represents a row from CSV import
 */
interface ImportRowInterface
{
    public function getBrokenUrl(): string;
    public function getSourceUrl(): ?string;
    public function getSourceSlug(): ?string;
    public function getSourcePostId(): ?int;
    public function getStatusCode(): ?int;
    public function getAnchorText(): ?string;
    public function getRowNumber(): int;
}

/**
 * Result of column detection
 */
interface ColumnMappingInterface
{
    public function getBrokenUrlColumn(): ?string;
    public function getSourceColumn(): ?string;
    public function getStatusCodeColumn(): ?string;
    public function getAnchorTextColumn(): ?string;
    public function getUnmappedColumns(): array;
    public function isValid(): bool;
    public function getValidationErrors(): array;
}

/**
 * Preview result before actual import
 */
interface ImportPreviewInterface
{
    public function getTotalRows(): int;
    public function getMatchedRows(): int;
    public function getUnmatchedRows(): int;
    public function getPreviewRows(): array; // First 10 rows with match status
    public function getColumnMapping(): ColumnMappingInterface;
}

/**
 * Final import result
 */
interface ImportResultInterface
{
    public function isSuccess(): bool;
    public function getImportedCount(): int;
    public function getSkippedCount(): int;
    public function getErrors(): array;
    public function getDuplicatesSkipped(): int;
}

/**
 * Main import service
 */
interface CsvImportServiceInterface
{
    /**
     * Detect columns in uploaded CSV
     */
    public function detectColumns(string $filePath): ColumnMappingInterface;
    
    /**
     * Override auto-detected column mapping
     */
    public function setColumnMapping(array $mapping): void;
    
    /**
     * Preview import without committing
     */
    public function preview(
        string $filePath,
        string $matchBy = 'url' // 'url' | 'slug' | 'post_id'
    ): ImportPreviewInterface;
    
    /**
     * Execute the import
     */
    public function execute(
        string $filePath,
        string $matchBy = 'url',
        bool $skipDuplicates = true
    ): ImportResultInterface;
}
```

---

## CSV Parser

```php
<?php
declare(strict_types=1);

namespace LinkManager\Import;

final class CsvParser
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_ROWS = 50000;
    
    private ?string $delimiter = null;
    private array $headers = [];
    
    /**
     * Parse CSV file and return iterator
     */
    public function parse(string $filePath): \Generator
    {
        $this->validateFile($filePath);
        $this->detectDelimiter($filePath);
        
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new ImportException('Failed to open CSV file', 14600);
        }
        
        try {
            // Read headers
            $this->headers = fgetcsv($handle, 0, $this->delimiter);
            if ($this->headers === false) {
                throw new ImportException('Failed to read CSV headers', 14601);
            }
            
            // Normalize headers
            $this->headers = array_map(
                fn($h) => strtolower(trim($h)),
                $this->headers
            );
            
            // Yield rows
            $rowNum = 1;
            while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
                if ($rowNum > self::MAX_ROWS) {
                    throw new ImportException(
                        "CSV exceeds maximum of " . self::MAX_ROWS . " rows",
                        14602
                    );
                }
                
                yield $rowNum => array_combine($this->headers, $row);
                $rowNum++;
            }
        } finally {
            fclose($handle);
        }
    }
    
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new ImportException('CSV file not found', 14603);
        }
        
        if (filesize($filePath) > self::MAX_FILE_SIZE) {
            throw new ImportException(
                'CSV file exceeds 10MB limit',
                14604
            );
        }
        
        $mimeType = mime_content_type($filePath);
        if (!in_array($mimeType, ['text/csv', 'text/plain', 'application/csv'])) {
            throw new ImportException(
                'Invalid file type. Expected CSV.',
                14605
            );
        }
    }
    
    private function detectDelimiter(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        $firstLine = fgets($handle);
        fclose($handle);
        
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];
        
        foreach ($delimiters as $d) {
            $counts[$d] = substr_count($firstLine, $d);
        }
        
        $this->delimiter = array_search(max($counts), $counts);
    }
}
```

---

## Column Detection

```php
<?php
declare(strict_types=1);

namespace LinkManager\Import;

final class ColumnDetector
{
    private const URL_PATTERNS = [
        'url', 'broken_url', 'link_url', 'destination', 
        'href', 'target_url', 'broken', 'dead_link'
    ];
    
    private const SOURCE_PATTERNS = [
        'source', 'source_url', 'source_page', 'page_url', 
        'from', 'article_url', 'page', 'origin'
    ];
    
    private const STATUS_PATTERNS = [
        'status', 'status_code', 'http_status', 
        'response_code', 'code', 'http_code'
    ];
    
    private const ANCHOR_PATTERNS = [
        'anchor', 'anchor_text', 'link_text', 'text', 'label'
    ];
    
    public function detect(array $headers): ColumnMappingInterface
    {
        $mapping = new ColumnMapping();
        
        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));
            
            if ($this->matches($normalized, self::URL_PATTERNS)) {
                $mapping->setBrokenUrlColumn($header);
            } elseif ($this->matches($normalized, self::SOURCE_PATTERNS)) {
                $mapping->setSourceColumn($header);
            } elseif ($this->matches($normalized, self::STATUS_PATTERNS)) {
                $mapping->setStatusCodeColumn($header);
            } elseif ($this->matches($normalized, self::ANCHOR_PATTERNS)) {
                $mapping->setAnchorTextColumn($header);
            } else {
                $mapping->addUnmappedColumn($header);
            }
        }
        
        return $mapping;
    }
    
    private function matches(string $header, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($header === $pattern || str_contains($header, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
```

---

## Source Matching

```php
<?php
declare(strict_types=1);

namespace LinkManager\Import;

final class SourceMatcher
{
    public function __construct(
        private readonly \wpdb $wpdb
    ) {}
    
    /**
     * Match source identifier to WordPress post
     */
    public function match(
        string $sourceValue,
        string $matchBy
    ): ?int {
        return match($matchBy) {
            'url' => $this->matchByUrl($sourceValue),
            'slug' => $this->matchBySlug($sourceValue),
            'post_id' => $this->matchByPostId($sourceValue),
            default => throw new \InvalidArgumentException("Invalid match type: {$matchBy}")
        };
    }
    
    private function matchByUrl(string $url): ?int
    {
        // Normalize URL
        $url = untrailingslashit($url);
        $path = parse_url($url, PHP_URL_PATH);
        
        if ($path) {
            // Try matching by path
            return $this->matchBySlug(basename($path));
        }
        
        return null;
    }
    
    private function matchBySlug(string $slug): ?int
    {
        $slug = sanitize_title($slug);
        
        $postId = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ID FROM {$this->wpdb->posts} 
                 WHERE post_name = %s 
                 AND post_status = 'publish'
                 LIMIT 1",
                $slug
            )
        );
        
        return $postId ? (int)$postId : null;
    }
    
    private function matchByPostId(string $value): ?int
    {
        $postId = (int)$value;
        
        if ($postId <= 0) {
            return null;
        }
        
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ID FROM {$this->wpdb->posts} 
                 WHERE ID = %d 
                 AND post_status = 'publish'",
                $postId
            )
        );
        
        return $exists ? $postId : null;
    }
}
```

---

## Import Execution

```php
<?php
declare(strict_types=1);

namespace LinkManager\Import;

final class CsvImportService implements CsvImportServiceInterface
{
    public function __construct(
        private readonly CsvParser $parser,
        private readonly ColumnDetector $detector,
        private readonly SourceMatcher $matcher,
        private readonly ConnectionManager $db,
        private readonly LoggerInterface $logger
    ) {}
    
    public function execute(
        string $filePath,
        string $matchBy = 'url',
        bool $skipDuplicates = true
    ): ImportResultInterface {
        $mapping = $this->detectColumns($filePath);
        
        if (!$mapping->isValid()) {
            throw new ImportException(
                'Invalid column mapping: ' . implode(', ', $mapping->getValidationErrors()),
                14606
            );
        }
        
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];
        
        $this->db->execute('BEGIN TRANSACTION');
        
        try {
            foreach ($this->parser->parse($filePath) as $rowNum => $row) {
                $brokenUrl = $row[$mapping->getBrokenUrlColumn()] ?? null;
                $sourceValue = $row[$mapping->getSourceColumn()] ?? null;
                
                if (empty($brokenUrl)) {
                    $skipped++;
                    continue;
                }
                
                // Match source to post
                $postId = null;
                if ($sourceValue) {
                    $postId = $this->matcher->match($sourceValue, $matchBy);
                    if ($postId === null) {
                        $skipped++;
                        $errors[] = "Row {$rowNum}: Could not match source '{$sourceValue}'";
                        continue;
                    }
                }
                
                // Check for duplicates
                if ($skipDuplicates && $this->isDuplicate($brokenUrl, $postId)) {
                    $duplicates++;
                    continue;
                }
                
                // Insert link record
                $this->insertLink($brokenUrl, $postId, $row, $mapping);
                $imported++;
            }
            
            $this->db->execute('COMMIT');
            
            $this->logger->info('CSV import completed', [
                'imported' => $imported,
                'skipped' => $skipped,
                'duplicates' => $duplicates
            ]);
            
            return new ImportResult(true, $imported, $skipped, $errors, $duplicates);
            
        } catch (\Exception $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
    }
    
    private function isDuplicate(string $url, ?int $postId): bool
    {
        $result = $this->db->querySingle(
            'SELECT COUNT(*) FROM Links WHERE Url = ? AND PostId = ?',
            [$url, $postId]
        );
        return $result > 0;
    }
    
    private function insertLink(
        string $url,
        ?int $postId,
        array $row,
        ColumnMappingInterface $mapping
    ): void {
        $statusCode = null;
        if ($mapping->getStatusCodeColumn()) {
            $statusCode = (int)($row[$mapping->getStatusCodeColumn()] ?? 0);
        }
        
        $anchorText = null;
        if ($mapping->getAnchorTextColumn()) {
            $anchorText = $row[$mapping->getAnchorTextColumn()] ?? null;
        }
        
        $this->db->execute(
            'INSERT INTO Links (Url, PostId, AnchorText, StatusCode, Status, Source, CreatedAt)
             VALUES (?, ?, ?, ?, ?, ?, datetime("now"))',
            [
                $url,
                $postId,
                $anchorText,
                $statusCode,
                $statusCode >= 400 ? 'broken' : 'unknown',
                'csv_import'
            ]
        );
    }
}
```

---

## REST API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `lm/v1/import/upload` | Upload CSV file |
| POST | `lm/v1/import/detect-columns` | Auto-detect column mapping |
| POST | `lm/v1/import/preview` | Preview import results |
| POST | `lm/v1/import/execute` | Execute import |

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 14600 | ERR_CSV_OPEN_FAILED | Failed to open CSV file |
| 14601 | ERR_CSV_HEADER_FAILED | Failed to read headers |
| 14602 | ERR_CSV_TOO_MANY_ROWS | Exceeds row limit |
| 14603 | ERR_CSV_NOT_FOUND | File not found |
| 14604 | ERR_CSV_TOO_LARGE | Exceeds size limit |
| 14605 | ERR_CSV_INVALID_TYPE | Invalid file type |
| 14606 | ERR_CSV_MAPPING_INVALID | Column mapping invalid |
| 14607 | ERR_CSV_IMPORT_FAILED | Import execution failed |

---

## Acceptance Criteria

**Done when:**
- [ ] Supports comma, semicolon, tab delimiters
- [ ] Auto-detects common column names
- [ ] Manual column mapping override works
- [ ] Preview shows match percentage
- [ ] Duplicate detection prevents re-import
- [ ] Imports link with correct status
- [ ] Handles 50,000+ row files efficiently
- [ ] Progress reporting for large files

---

## Dependencies

- `04-database-schema.md` - Links table
- `20-settings-page.md` - Import UI
