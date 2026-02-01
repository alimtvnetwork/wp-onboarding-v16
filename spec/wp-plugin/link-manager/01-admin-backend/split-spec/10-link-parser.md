# 10 - Link Parser Service

> **Status:** Complete  
> **Priority:** Critical  
> **Updated:** 2026-01-31

---

## Purpose

Parses HTML and JSON-LD content to extract all hyperlinks with their context (wrapper tags, attributes, word count). Provides the foundational data structure for link categorization and modification.

---

## Core Interfaces

```php
<?php
declare(strict_types=1);

namespace LinkManager\Parser;

use LinkManager\Enum\LinkWrapperType;
use LinkManager\Enum\LinkStatus;

/**
 * Represents a parsed link with full context
 */
interface ParsedLinkInterface
{
    public function getUrl(): string;
    public function getAnchorText(): string;
    public function getWordCount(): int;
    public function getTitleAttribute(): ?string;
    public function getWrapperStack(): array; // [LinkWrapperType::H2, LinkWrapperType::STRONG]
    public function getSourceType(): string; // 'html' | 'json_ld'
    public function getJsonLdPath(): ?string; // e.g., '@graph[0].mainEntity.url'
    public function getStartPosition(): int;
    public function getEndPosition(): int;
    public function getOuterHtml(): string;
    public function getInnerHtml(): string;
}

/**
 * Result of parsing content
 */
interface ParseResultInterface
{
    public function getLinks(): array; // ParsedLinkInterface[]
    public function getHtmlErrors(): array; // Malformed HTML warnings
    public function getJsonLdBlocks(): array; // Raw JSON-LD for reference
    public function hasValidHtml(): bool;
    public function getLinkCount(): int;
    public function getLinksByWrapper(LinkWrapperType $wrapper): array;
}

/**
 * Main parser service
 */
interface LinkParserServiceInterface
{
    /**
     * Parse content and extract all links
     */
    public function parse(string $content): ParseResultInterface;
    
    /**
     * Parse only HTML links (skip JSON-LD)
     */
    public function parseHtmlOnly(string $content): ParseResultInterface;
    
    /**
     * Parse only JSON-LD links
     */
    public function parseJsonLdOnly(string $content): ParseResultInterface;
    
    /**
     * Validate HTML structure
     */
    public function validateHtml(string $content): array; // Returns error list
}
```

---

## Data Classes

```php
<?php
declare(strict_types=1);

namespace LinkManager\Parser\Data;

use LinkManager\Enum\LinkWrapperType;

final class ParsedLink implements ParsedLinkInterface
{
    public function __construct(
        private readonly string $url,
        private readonly string $anchorText,
        private readonly int $wordCount,
        private readonly ?string $titleAttribute,
        private readonly array $wrapperStack,
        private readonly string $sourceType,
        private readonly ?string $jsonLdPath,
        private readonly int $startPosition,
        private readonly int $endPosition,
        private readonly string $outerHtml,
        private readonly string $innerHtml
    ) {}
    
    // ... getter implementations
}

final class ParseResult implements ParseResultInterface
{
    /** @var ParsedLinkInterface[] */
    private array $links = [];
    
    /** @var array<string, string> */
    private array $htmlErrors = [];
    
    /** @var array<int, array> */
    private array $jsonLdBlocks = [];
    
    public function addLink(ParsedLinkInterface $link): void
    {
        $this->links[] = $link;
    }
    
    public function addHtmlError(string $error, string $context): void
    {
        $this->htmlErrors[$context] = $error;
    }
    
    public function getLinksByWrapper(LinkWrapperType $wrapper): array
    {
        return array_filter(
            $this->links,
            fn(ParsedLinkInterface $link) => in_array($wrapper, $link->getWrapperStack())
        );
    }
    
    // ... other implementations
}
```

---

## Wrapper Detection Algorithm

```php
<?php
declare(strict_types=1);

namespace LinkManager\Parser;

use LinkManager\Enum\LinkWrapperType;

final class WrapperDetector
{
    /**
     * Detects wrapper tag stack for an anchor element
     * 
     * Examples:
     * - <a href="#">text</a> → []
     * - <strong><a href="#">text</a></strong> → [STRONG]
     * - <h2><strong><a href="#">text</a></strong></h2> → [H2, STRONG]
     * - <h3><em><a href="#">text</a></em></h3> → [H3, EM]
     */
    public function detectWrappers(\DOMElement $anchor): array
    {
        $wrappers = [];
        $parent = $anchor->parentNode;
        
        // Traverse up to 5 levels (reasonable nesting depth)
        $maxDepth = 5;
        $depth = 0;
        
        while ($parent instanceof \DOMElement && $depth < $maxDepth) {
            $tagName = strtoupper($parent->tagName);
            
            $wrapper = match($tagName) {
                'H1' => LinkWrapperType::H1,
                'H2' => LinkWrapperType::H2,
                'H3' => LinkWrapperType::H3,
                'H4' => LinkWrapperType::H4,
                'H5' => LinkWrapperType::H5,
                'H6' => LinkWrapperType::H6,
                'STRONG', 'B' => LinkWrapperType::STRONG,
                'EM', 'I' => LinkWrapperType::EM,
                default => null
            };
            
            if ($wrapper !== null) {
                // Prepend to maintain hierarchy order (outermost first)
                array_unshift($wrappers, $wrapper);
            }
            
            // Stop at block-level containers
            if (in_array($tagName, ['DIV', 'P', 'ARTICLE', 'SECTION', 'BODY'])) {
                break;
            }
            
            $parent = $parent->parentNode;
            $depth++;
        }
        
        return $wrappers;
    }
}
```

---

## JSON-LD Parser

```php
<?php
declare(strict_types=1);

namespace LinkManager\Parser;

final class JsonLdParser
{
    private const URL_PROPERTIES = [
        'url', 'sameAs', 'mainEntityOfPage', 'image', 'logo',
        'contentUrl', 'embedUrl', 'thumbnailUrl', 'downloadUrl',
        'significantLink', 'relatedLink', 'isPartOf', 'hasPart'
    ];
    
    /**
     * Extract all URLs from JSON-LD with their paths
     */
    public function extractUrls(string $jsonLd): array
    {
        $data = json_decode($jsonLd, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        $urls = [];
        $this->traverseRecursively($data, '', $urls);
        return $urls;
    }
    
    private function traverseRecursively(
        mixed $data, 
        string $path, 
        array &$urls
    ): void {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $newPath = is_int($key) 
                    ? "{$path}[{$key}]" 
                    : ($path ? "{$path}.{$key}" : $key);
                
                // Check if this is a URL property
                if (is_string($key) && in_array($key, self::URL_PROPERTIES)) {
                    if (is_string($value) && $this->isUrl($value)) {
                        $urls[] = [
                            'url' => $value,
                            'path' => $newPath,
                            'property' => $key
                        ];
                    } elseif (is_array($value)) {
                        // Handle array of URLs (e.g., sameAs)
                        foreach ($value as $i => $url) {
                            if (is_string($url) && $this->isUrl($url)) {
                                $urls[] = [
                                    'url' => $url,
                                    'path' => "{$newPath}[{$i}]",
                                    'property' => $key
                                ];
                            }
                        }
                    }
                }
                
                $this->traverseRecursively($value, $newPath, $urls);
            }
        }
    }
    
    private function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
```

---

## HTML Validation

```php
<?php
declare(strict_types=1);

namespace LinkManager\Parser;

final class HtmlValidator
{
    /**
     * Validates HTML and returns list of errors
     */
    public function validate(string $html): array
    {
        $errors = [];
        
        // Use DOMDocument with error suppression + capture
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        
        foreach (libxml_get_errors() as $error) {
            $errors[] = [
                'message' => trim($error->message),
                'line' => $error->line,
                'column' => $error->column,
                'level' => match($error->level) {
                    LIBXML_ERR_WARNING => 'warning',
                    LIBXML_ERR_ERROR => 'error',
                    LIBXML_ERR_FATAL => 'fatal',
                    default => 'info'
                }
            ];
        }
        
        libxml_clear_errors();
        
        return $errors;
    }
    
    /**
     * Check for common broken HTML patterns
     */
    public function checkCommonIssues(string $html): array
    {
        $issues = [];
        
        // Unclosed anchor tags
        if (preg_match_all('/<a\s[^>]*>/i', $html) !== preg_match_all('/<\/a>/i', $html)) {
            $issues[] = 'Mismatched anchor tag count';
        }
        
        // Nested anchor tags (invalid HTML)
        if (preg_match('/<a\s[^>]*>[^<]*<a\s/i', $html)) {
            $issues[] = 'Nested anchor tags detected';
        }
        
        // Empty href attributes
        if (preg_match('/<a\s[^>]*href\s*=\s*[\"\'][\"\'][^>]*>/i', $html)) {
            $issues[] = 'Empty href attribute found';
        }
        
        return $issues;
    }
}
```

---

## Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| 14200 | ERR_PARSE_HTML_LOAD_FAILED | Failed to load HTML into DOM |
| 14201 | ERR_PARSE_INVALID_HTML | HTML validation failed with errors |
| 14202 | ERR_PARSE_JSON_LD_INVALID | JSON-LD is malformed |
| 14203 | ERR_PARSE_ENCODING_ERROR | Character encoding issue |
| 14204 | ERR_PARSE_NESTED_ANCHORS | Invalid nested anchor tags |
| 14205 | ERR_PARSE_DEPTH_EXCEEDED | Wrapper detection depth exceeded |

---

## Acceptance Criteria

**Done when:**
- [ ] All anchor tags extracted with correct positions
- [ ] Wrapper stack accurately reflects tag hierarchy
- [ ] Word count matches actual visible text words
- [ ] JSON-LD URLs extracted with correct paths
- [ ] HTML validation catches malformed markup
- [ ] Performance: < 50ms for average post (5KB content)

---

## Dependencies

- `66-shared-constants.md` - LinkWrapperType enum
- PHP DOMDocument extension
- libxml for error handling
