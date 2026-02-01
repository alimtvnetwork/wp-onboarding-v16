# 14 - Modification Service

> **Phase:** Modifications  
> **Dependencies:** `12-history-service.md`, `11-elementor-integration.md`  
> **Estimated Time:** 6-8 hours  
> **Last Updated:** 2026-01-31

---

## 📋 Scope

Implement the link modification engine that enables removing, changing, and updating links across posts, pages, and categories with full history tracking.

---

## 🎯 Purpose

The ModificationService provides:
- **Link removal**: Remove `<a>` tag keeping text, or remove href only
- **Title attribute management**: Add, remove, or bulk-update title attributes
- **URL changes**: Replace link URLs
- **Wrapper tag removal**: Remove H1-H6, strong, em tags while keeping content
- **Bulk operations**: Apply modifications to multiple links at once

---

## 🔧 Core Interface

```php
<?php
namespace LinkManager\Services;

use LinkManager\Enums\{ContentType, ModificationType};

interface ModificationServiceInterface
{
    /**
     * Remove a link, keeping anchor text
     *
     * @param int $linkId Link record ID
     * @param int|null $userId User performing modification
     * @return bool Success status
     */
    public function removeLinkKeepText(int $linkId, ?int $userId = null): bool;
    
    /**
     * Remove href attribute only, keeping <a> tag
     *
     * @param int $linkId Link record ID
     * @param int|null $userId User performing modification
     * @return bool Success status
     */
    public function removeHrefOnly(int $linkId, ?int $userId = null): bool;
    
    /**
     * Remove title attribute from link
     *
     * @param int $linkId Link record ID
     * @param int|null $userId User performing modification
     * @return bool Success status
     */
    public function removeTitleAttribute(int $linkId, ?int $userId = null): bool;
    
    /**
     * Add or update title attribute
     *
     * @param int $linkId Link record ID
     * @param string $title New title value
     * @param int|null $userId User performing modification
     * @return bool Success status
     */
    public function setTitleAttribute(int $linkId, string $title, ?int $userId = null): bool;
    
    /**
     * Change link URL
     *
     * @param int $linkId Link record ID
     * @param string $newUrl New URL value
     * @param int|null $userId User performing modification
     * @return bool Success status
     */
    public function changeUrl(int $linkId, string $newUrl, ?int $userId = null): bool;
    
    /**
     * Remove wrapper tags (H1-H6, strong, em) from link
     *
     * @param int $linkId Link record ID
     * @param array $tagsToRemove Tags to remove ['H2', 'STRONG']
     * @param int|null $userId User performing modification
     * @return bool Success status
     */
    public function removeWrapperTags(int $linkId, array $tagsToRemove, ?int $userId = null): bool;
    
    /**
     * Bulk remove title attributes
     *
     * @param array $linkIds Array of link IDs
     * @param int|null $userId User performing modification
     * @return array Results per link ID
     */
    public function bulkRemoveTitleAttributes(array $linkIds, ?int $userId = null): array;
    
    /**
     * Bulk set title attributes from list
     *
     * @param array $linkIds Array of link IDs
     * @param array $titles Array of title values (randomly assigned if fewer than links)
     * @param int|null $userId User performing modification
     * @return array Results per link ID
     */
    public function bulkSetTitleAttributes(array $linkIds, array $titles, ?int $userId = null): array;
    
    /**
     * Bulk remove links keeping text
     *
     * @param array $linkIds Array of link IDs
     * @param int|null $userId User performing modification
     * @return array Results per link ID
     */
    public function bulkRemoveLinks(array $linkIds, ?int $userId = null): array;
}
```

---

## 🏗️ Implementation

**File:** `src/Services/ModificationService.php`

```php
<?php
namespace LinkManager\Services;

use LinkManager\Database\Models\Link;
use LinkManager\Enums\{ContentType, ModificationType};
use LinkManager\Utils\{Logger, HtmlValidator};

class ModificationService implements ModificationServiceInterface
{
    private HistoryService $historyService;
    private SnapshotService $snapshotService;
    private ElementorParser $elementorParser;
    private HtmlValidator $htmlValidator;
    
    public function __construct(
        HistoryService $historyService,
        SnapshotService $snapshotService,
        ElementorParser $elementorParser,
        HtmlValidator $htmlValidator
    ) {
        $this->historyService = $historyService;
        $this->snapshotService = $snapshotService;
        $this->elementorParser = $elementorParser;
        $this->htmlValidator = $htmlValidator;
    }
    
    /**
     * Remove a link, keeping anchor text
     */
    public function removeLinkKeepText(int $linkId, ?int $userId = null): bool
    {
        return $this->modifyLink(
            $linkId,
            ModificationType::REMOVE_LINK_KEEP_TEXT,
            function (string $content, Link $link): string {
                return $this->performRemoveLinkKeepText($content, $link);
            },
            $userId
        );
    }
    
    /**
     * Remove href attribute only, keeping <a> tag
     */
    public function removeHrefOnly(int $linkId, ?int $userId = null): bool
    {
        return $this->modifyLink(
            $linkId,
            ModificationType::REMOVE_HREF_ONLY,
            function (string $content, Link $link): string {
                return $this->performRemoveHrefOnly($content, $link);
            },
            $userId
        );
    }
    
    /**
     * Remove title attribute from link
     */
    public function removeTitleAttribute(int $linkId, ?int $userId = null): bool
    {
        return $this->modifyLink(
            $linkId,
            ModificationType::REMOVE_TITLE_ATTR,
            function (string $content, Link $link): string {
                return $this->performRemoveTitleAttribute($content, $link);
            },
            $userId
        );
    }
    
    /**
     * Add or update title attribute
     */
    public function setTitleAttribute(int $linkId, string $title, ?int $userId = null): bool
    {
        return $this->modifyLink(
            $linkId,
            ModificationType::ADD_TITLE_ATTR,
            function (string $content, Link $link) use ($title): string {
                return $this->performSetTitleAttribute($content, $link, $title);
            },
            $userId,
            ['newTitle' => $title]
        );
    }
    
    /**
     * Change link URL
     */
    public function changeUrl(int $linkId, string $newUrl, ?int $userId = null): bool
    {
        return $this->modifyLink(
            $linkId,
            ModificationType::CHANGE_URL,
            function (string $content, Link $link) use ($newUrl): string {
                return $this->performChangeUrl($content, $link, $newUrl);
            },
            $userId,
            ['newUrl' => $newUrl]
        );
    }
    
    /**
     * Remove wrapper tags from link
     */
    public function removeWrapperTags(int $linkId, array $tagsToRemove, ?int $userId = null): bool
    {
        return $this->modifyLink(
            $linkId,
            ModificationType::REMOVE_WRAPPER_TAG,
            function (string $content, Link $link) use ($tagsToRemove): string {
                return $this->performRemoveWrapperTags($content, $link, $tagsToRemove);
            },
            $userId,
            ['tagsRemoved' => $tagsToRemove]
        );
    }
    
    /**
     * Bulk remove title attributes
     */
    public function bulkRemoveTitleAttributes(array $linkIds, ?int $userId = null): array
    {
        $results = [];
        
        // Check auto-snapshot setting
        $this->checkAutoSnapshot($linkIds);
        
        foreach ($linkIds as $linkId) {
            try {
                $results[$linkId] = $this->removeTitleAttribute($linkId, $userId);
            } catch (\Throwable $e) {
                Logger::error('Bulk remove title failed for link', [
                    'link_id' => $linkId,
                    'error' => $e->getMessage()
                ]);
                $results[$linkId] = false;
            }
        }
        
        return $results;
    }
    
    /**
     * Bulk set title attributes from list
     */
    public function bulkSetTitleAttributes(array $linkIds, array $titles, ?int $userId = null): array
    {
        $results = [];
        $titleCount = count($titles);
        
        $this->checkAutoSnapshot($linkIds);
        
        foreach ($linkIds as $index => $linkId) {
            try {
                // Pick title (random if fewer titles than links)
                $title = $titles[$index % $titleCount] ?? $titles[array_rand($titles)];
                $results[$linkId] = $this->setTitleAttribute($linkId, $title, $userId);
            } catch (\Throwable $e) {
                Logger::error('Bulk set title failed for link', [
                    'link_id' => $linkId,
                    'error' => $e->getMessage()
                ]);
                $results[$linkId] = false;
            }
        }
        
        return $results;
    }
    
    /**
     * Bulk remove links keeping text
     */
    public function bulkRemoveLinks(array $linkIds, ?int $userId = null): array
    {
        $results = [];
        
        $this->checkAutoSnapshot($linkIds);
        
        foreach ($linkIds as $linkId) {
            try {
                $results[$linkId] = $this->removeLinkKeepText($linkId, $userId);
            } catch (\Throwable $e) {
                Logger::error('Bulk remove link failed', [
                    'link_id' => $linkId,
                    'error' => $e->getMessage()
                ]);
                $results[$linkId] = false;
            }
        }
        
        return $results;
    }
    
    // ===== Core Modification Engine =====
    
    /**
     * Generic modification wrapper with history tracking
     */
    private function modifyLink(
        int $linkId,
        ModificationType $type,
        callable $modifyFn,
        ?int $userId = null,
        array $extraDetails = []
    ): bool {
        try {
            // Get link record
            $link = Link::find($linkId);
            if ($link === null) {
                throw new \RuntimeException('Link not found', 14300);
            }
            
            $contentType = ContentType::from($link->contentType);
            $wpContentId = $link->wpContentId;
            $slug = $this->getSlug($contentType, $wpContentId);
            
            // Get current content
            $currentContent = $this->getCurrentContent($contentType, $wpContentId);
            
            // Validate link still exists in content
            if (!$this->validateLinkExists($currentContent, $link)) {
                throw new \RuntimeException('Content changed since scan', 14301);
            }
            
            // Check auto-snapshot on first modification
            $this->checkFirstModificationSnapshot($contentType, $wpContentId, $slug, $userId);
            
            // Create version record (before)
            $versionNumber = $this->historyService->createVersion(
                $contentType,
                $wpContentId,
                $slug,
                $currentContent,
                $type,
                $userId
            );
            
            // Perform modification
            $modifiedContent = $modifyFn($currentContent, $link);
            
            // Validate HTML integrity
            if (!$this->htmlValidator->isValid($modifiedContent)) {
                Logger::warning('Modified content has broken HTML', [
                    'link_id' => $linkId,
                    'type' => $type->value
                ]);
                // Continue but flag the content
            }
            
            // Save to WordPress
            $this->saveContent($contentType, $wpContentId, $modifiedContent);
            
            // Handle Elementor if applicable
            if ($link->elementorWidgetId && $contentType !== ContentType::CATEGORY) {
                $this->modifyElementorContent($wpContentId, $link, $type, $extraDetails);
            }
            
            // Complete version record (after)
            $this->historyService->completeVersion(
                $contentType,
                $wpContentId,
                $versionNumber,
                $modifiedContent,
                array_merge([
                    'linkUrl' => $link->url,
                    'anchorTextBefore' => $link->anchorText,
                    'modificationType' => $type->value,
                ], $extraDetails)
            );
            
            // Update link record
            $this->updateLinkRecord($link, $type, $extraDetails);
            
            Logger::info('Link modified', [
                'link_id' => $linkId,
                'type' => $type->value,
                'content_type' => $contentType->value,
                'wp_id' => $wpContentId
            ]);
            
            return true;
            
        } catch (\Throwable $e) {
            Logger::error('Modification failed', [
                'file' => __FILE__,
                'action' => 'modifyLink',
                'link_id' => $linkId,
                'type' => $type->value,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    // ===== Modification Implementations =====
    
    /**
     * Remove <a> tag but keep inner text
     * Input:  <a href="https://example.com">Click here</a>
     * Output: Click here
     */
    private function performRemoveLinkKeepText(string $content, Link $link): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $anchors = $xpath->query("//a[@href='" . htmlspecialchars($link->url) . "']");
        
        // Find the correct anchor by position
        $targetAnchor = null;
        $count = 0;
        foreach ($anchors as $anchor) {
            if ($count === $link->positionIndex) {
                $targetAnchor = $anchor;
                break;
            }
            $count++;
        }
        
        if ($targetAnchor === null) {
            // Try matching by anchor text as fallback
            foreach ($anchors as $anchor) {
                if (trim($anchor->textContent) === trim($link->anchorText)) {
                    $targetAnchor = $anchor;
                    break;
                }
            }
        }
        
        if ($targetAnchor) {
            // Replace anchor with its text content
            $textNode = $dom->createTextNode($targetAnchor->textContent);
            $targetAnchor->parentNode->replaceChild($textNode, $targetAnchor);
        }
        
        return $this->extractBodyContent($dom);
    }
    
    /**
     * Remove href attribute only
     * Input:  <a href="https://example.com" title="Example">Click here</a>
     * Output: <a title="Example">Click here</a>
     */
    private function performRemoveHrefOnly(string $content, Link $link): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $anchor = $this->findAnchor($dom, $link);
        
        if ($anchor) {
            $anchor->removeAttribute('href');
        }
        
        return $this->extractBodyContent($dom);
    }
    
    /**
     * Remove title attribute from link
     */
    private function performRemoveTitleAttribute(string $content, Link $link): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $anchor = $this->findAnchor($dom, $link);
        
        if ($anchor) {
            $anchor->removeAttribute('title');
        }
        
        return $this->extractBodyContent($dom);
    }
    
    /**
     * Set or update title attribute
     */
    private function performSetTitleAttribute(string $content, Link $link, string $title): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $anchor = $this->findAnchor($dom, $link);
        
        if ($anchor) {
            $anchor->setAttribute('title', htmlspecialchars($title));
        }
        
        return $this->extractBodyContent($dom);
    }
    
    /**
     * Change link URL
     */
    private function performChangeUrl(string $content, Link $link, string $newUrl): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $anchor = $this->findAnchor($dom, $link);
        
        if ($anchor) {
            $anchor->setAttribute('href', htmlspecialchars($newUrl));
        }
        
        return $this->extractBodyContent($dom);
    }
    
    /**
     * Remove wrapper tags from around a link
     * Input:  <h2><strong><a href="...">Click</a></strong></h2>
     * Output: <a href="...">Click</a>
     */
    private function performRemoveWrapperTags(string $content, Link $link, array $tagsToRemove): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $anchor = $this->findAnchor($dom, $link);
        
        if (!$anchor) {
            return $content;
        }
        
        // Walk up the DOM tree removing specified wrapper tags
        $current = $anchor->parentNode;
        while ($current && $current->nodeName !== 'body') {
            $tagName = strtoupper($current->nodeName);
            
            if (in_array($tagName, $tagsToRemove)) {
                // Move all children to parent
                $parent = $current->parentNode;
                while ($current->firstChild) {
                    $parent->insertBefore($current->firstChild, $current);
                }
                $parent->removeChild($current);
                $current = $parent;
            } else {
                $current = $current->parentNode;
            }
        }
        
        return $this->extractBodyContent($dom);
    }
    
    // ===== Helper Methods =====
    
    private function findAnchor(\DOMDocument $dom, Link $link): ?\DOMElement
    {
        $xpath = new \DOMXPath($dom);
        $anchors = $xpath->query("//a[@href='" . htmlspecialchars($link->url) . "']");
        
        $count = 0;
        foreach ($anchors as $anchor) {
            if ($count === $link->positionIndex) {
                return $anchor;
            }
            $count++;
        }
        
        // Fallback: match by anchor text
        foreach ($anchors as $anchor) {
            if (trim($anchor->textContent) === trim($link->anchorText)) {
                return $anchor;
            }
        }
        
        return null;
    }
    
    private function extractBodyContent(\DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $dom->saveHTML();
        }
        
        $content = '';
        foreach ($body->childNodes as $child) {
            $content .= $dom->saveHTML($child);
        }
        
        return $content;
    }
    
    private function validateLinkExists(string $content, Link $link): bool
    {
        return strpos($content, $link->url) !== false;
    }
    
    private function getSlug(ContentType $type, int $wpContentId): string
    {
        return match ($type) {
            ContentType::POST, ContentType::PAGE => get_post_field('post_name', $wpContentId),
            ContentType::CATEGORY => get_term_field('slug', $wpContentId, 'category'),
        };
    }
    
    private function getCurrentContent(ContentType $type, int $wpContentId): string
    {
        return match ($type) {
            ContentType::POST, ContentType::PAGE => get_post_field('post_content', $wpContentId),
            ContentType::CATEGORY => term_description($wpContentId, 'category'),
        };
    }
    
    private function saveContent(ContentType $type, int $wpContentId, string $content): void
    {
        match ($type) {
            ContentType::POST, ContentType::PAGE => wp_update_post([
                'ID' => $wpContentId,
                'post_content' => $content,
            ]),
            ContentType::CATEGORY => wp_update_term($wpContentId, 'category', [
                'description' => $content,
            ]),
        };
    }
    
    private function checkAutoSnapshot(array $linkIds): void
    {
        $autoSnapshot = get_option('lm_auto_snapshot', false);
        if (!$autoSnapshot) {
            return;
        }
        
        // Get unique content items
        $contentItems = [];
        foreach ($linkIds as $linkId) {
            $link = Link::find($linkId);
            if ($link) {
                $key = $link->contentType . '_' . $link->wpContentId;
                $contentItems[$key] = [
                    'type' => ContentType::from($link->contentType),
                    'wpId' => $link->wpContentId,
                ];
            }
        }
        
        // Create snapshot if this is first modification
        // (Snapshot service handles the "already exists" check)
        $this->snapshotService->createAutoSnapshot('Before bulk modification');
    }
    
    private function checkFirstModificationSnapshot(
        ContentType $type,
        int $wpContentId,
        string $slug,
        ?int $userId
    ): void {
        $autoSnapshot = get_option('lm_auto_snapshot', false);
        if (!$autoSnapshot) {
            return;
        }
        
        // Check if this content has any history
        if (!$this->historyService->hasHistory($type, $wpContentId, $slug)) {
            // First modification - suggest/create snapshot
            Logger::info('First modification detected, auto-snapshot enabled', [
                'type' => $type->value,
                'wp_id' => $wpContentId
            ]);
        }
    }
    
    private function updateLinkRecord(Link $link, ModificationType $type, array $details): void
    {
        $updates = ['UpdatedAt' => gmdate('Y-m-d H:i:s')];
        
        match ($type) {
            ModificationType::REMOVE_LINK_KEEP_TEXT => $link->delete(),
            ModificationType::REMOVE_HREF_ONLY => $link->update(['Url' => '']),
            ModificationType::REMOVE_TITLE_ATTR => $link->update(['TitleAttribute' => null]),
            ModificationType::ADD_TITLE_ATTR => $link->update(['TitleAttribute' => $details['newTitle'] ?? null]),
            ModificationType::CHANGE_URL => $link->update(['Url' => $details['newUrl'] ?? $link->url]),
            ModificationType::REMOVE_WRAPPER_TAG => $link->update([
                'WrapperTags' => json_encode([]),
                'HasHeadingWrapper' => 0,
                'HasEmphasisWrapper' => 0,
            ]),
        };
    }
    
    private function modifyElementorContent(
        int $wpContentId,
        Link $link,
        ModificationType $type,
        array $details
    ): void {
        $elementorData = get_post_meta($wpContentId, '_elementor_data', true);
        if (empty($elementorData)) {
            return;
        }
        
        $modifiedData = $this->elementorParser->modifyLink(
            $elementorData,
            $link->elementorWidgetId,
            $type,
            $details
        );
        
        if ($modifiedData !== $elementorData) {
            update_post_meta($wpContentId, '_elementor_data', $modifiedData);
        }
    }
}
```

---

## ✅ Acceptance Criteria

### Link Removal
- [ ] Removes `<a>` tag keeping inner text
- [ ] Preserves surrounding HTML structure
- [ ] Works with nested wrappers
- [ ] Updates history correctly

### Title Attribute Management
- [ ] Removes title attribute completely
- [ ] Adds new title attribute
- [ ] Updates existing title attribute
- [ ] Bulk operations work correctly

### URL Changes
- [ ] Changes href value
- [ ] Validates new URL format
- [ ] Updates link record

### Wrapper Removal
- [ ] Removes specified wrapper tags only
- [ ] Keeps other wrappers intact
- [ ] Handles nested structures

### History Integration
- [ ] Creates version before modification
- [ ] Completes version after modification
- [ ] Stores modification details
- [ ] Enables rollback

### Bulk Operations
- [ ] Processes multiple links
- [ ] Returns per-link results
- [ ] Handles individual failures gracefully
- [ ] Triggers auto-snapshot if enabled

### Error Handling
- [ ] Validates link still exists
- [ ] Detects content changes since scan
- [ ] Full stack traces in logs
- [ ] Proper error codes (14300-14399)

---

## 📝 Related Specifications

- `12-history-service.md` - Version tracking
- `13-snapshot-service.md` - Database snapshots
- `11-elementor-integration.md` - Elementor modifications
- `10-link-parser.md` - HTML parsing

---

*Zero data loss principle: every modification is tracked and reversible.*
